<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ikan;
use App\Models\Kolam;
use App\Models\SamplingPertumbuhan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SamplingController extends Controller
{
    public function __construct(private AnalisisController $analisis) {}

    public function ambilSampel(Request $request, int $id): JsonResponse
    {
        $kolam = Kolam::query()->find($id);

        if (! $kolam instanceof Kolam) {
            return response()->json([
                'message' => 'Kolam tidak ditemukan.',
            ], 404);
        }

        $n = max(1, min(200, (int) $request->query('n', 30)));

        $ikan = Ikan::query()
            ->where('kolam_id', $id)
            ->where('status', 'hidup')
            ->inRandomOrder()
            ->limit($n)
            ->get(['kode', 'bobot_g', 'panjang_cm']);

        $jumlah = $ikan->count();
        $bobotTotal = (float) $ikan->sum('bobot_g');
        $panjangRata = $jumlah > 0
            ? (float) $ikan->avg('panjang_cm')
            : 0.0;

        return response()->json([
            'jumlah_sampel' => $jumlah,
            'bobot_total_sampel_g' => round($bobotTotal, 2),
            'panjang_rata_cm' => round($panjangRata, 2),
            'ikan' => $ikan->map(fn (Ikan $sampel): array => [
                'kode' => $sampel->kode,
                'bobot_g' => (float) $sampel->bobot_g,
                'panjang_cm' => (float) $sampel->panjang_cm,
            ])->values(),
        ]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'jumlah_sampel' => ['required', 'integer', 'min:1'],
            'bobot_total_sampel_g' => ['required', 'numeric', 'gt:0'],
            'panjang_rata_cm' => ['required', 'numeric', 'gt:0'],
        ]);

        $kolam = Kolam::query()->find($id);

        if (! $kolam instanceof Kolam) {
            return response()->json([
                'message' => 'Kolam tidak ditemukan.',
            ], 404);
        }

        $hidup = Ikan::query()
            ->where('kolam_id', $id)
            ->where('status', 'hidup')
            ->count();

        if ((int) $data['jumlah_sampel'] > $hidup) {
            return response()->json([
                'error' => "jumlah_sampel melebihi ikan hidup ({$hidup})",
            ], 422);
        }

        $waktu = Carbon::parse($kolam->waktu_sandbox_sekarang);
        $tanggal = $waktu->toDateString();

        SamplingPertumbuhan::query()->create([
            'kolam_id' => $kolam->id,
            'tanggal' => $tanggal,
            'jumlah_sampel' => (int) $data['jumlah_sampel'],
            'bobot_total_sampel_g' => (float) $data['bobot_total_sampel_g'],
            'panjang_rata_cm' => (float) $data['panjang_rata_cm'],
        ]);

        $rekomendasi = $this->analisis->rekomendasiPanen(
            $kolam->id,
            $waktu->toDateTimeString(),
        );

        return response()->json([
            'tanggal' => $tanggal,
            'rekomendasi' => $rekomendasi,
        ]);
    }
}
