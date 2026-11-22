<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ikan;
use App\Models\KeadaanZona;
use App\Models\Kolam;
use App\Models\ZonaKolam;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SimulasiController extends Controller
{
    public function __construct(
        private LingkunganController $lingkungan,
        private PakanController $pakan,
        private IkanController $ikan,
    ) {}

    /**
     * Menjalankan satu tick simulasi.
     */
    public function tick(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id): JsonResponse {
            $kolam = Kolam::query()
                ->lockForUpdate()
                ->find($id);

            if (! $kolam instanceof Kolam) {
                return response()->json([
                    'message' => 'Kolam tidak ditemukan',
                ], 404);
            }

            $waktuSebelum = Carbon::parse($kolam->waktu_sandbox_sekarang);
            $tickMenit = (int) config('sandbox.tick_menit');

            if ($tickMenit <= 0) {
                return response()->json([
                    'message' => 'Konfigurasi tick_menit tidak valid',
                ], 500);
            }

            $waktuTick = $waktuSebelum->format('Y-m-d H:i:s');
            $waktuBerikutnya = $waktuSebelum->copy()->addMinutes($tickMenit);

            // 1. Perbarui kondisi lingkungan.
            $this->lingkungan->perbarui($id, $waktuTick);

            // 2. Jalankan jadwal pemberian pakan.
            $this->pakan->jalankanJadwal($id, $waktuTick);

            // 3. Pergerakan ikan dan akumulasi paparan.
            $this->ikan->gerak($id, $waktuTick);

            // 4. Jika tanggal virtual berganti, proses hari yang selesai.
            $hariBerganti = $waktuSebelum->toDateString()
                !== $waktuBerikutnya->toDateString();

            if ($hariBerganti) {
                $this->ikan->prosesHarian(
                    $id,
                    $waktuSebelum->toDateString(),
                );
            }

            // 5. Simpan waktu virtual berikutnya.
            $kolam->update([
                'waktu_sandbox_sekarang' => $waktuBerikutnya,
            ]);

            return response()->json([
                'message' => 'Tick simulasi berhasil dijalankan',
                'waktu_sebelum' => $waktuTick,
                'waktu_sekarang' => $waktuBerikutnya->format('Y-m-d H:i:s'),
                'tick_menit' => $tickMenit,
                'proses_harian' => $hariBerganti,
            ]);
        });
    }

    /**
     * Melihat status dan waktu simulasi.
     */
    public function status(int $id): JsonResponse
    {
        $kolam = Kolam::query()
            ->select([
                'id',
                'kode',
                'nama',
                'waktu_mulai_sandbox',
                'waktu_sandbox_sekarang',
                'kecepatan_waktu',
            ])
            ->find($id);

        if (! $kolam instanceof Kolam) {
            return response()->json([
                'message' => 'Kolam tidak ditemukan',
            ], 404);
        }

        $ikan = Ikan::query()
            ->where('kolam_id', $id)
            ->get(['status', 'bobot_g']);

        $hidup = $ikan->where('status', 'hidup');
        $mati = $ikan->where('status', 'mati');

        $ringkasanIkan = [
            'jumlah_awal' => $ikan->count(),
            'jumlah_hidup' => $hidup->count(),
            'jumlah_mati' => $mati->count(),
            'biomassa_hidup_g' => round((float) $hidup->sum('bobot_g'), 2),
        ];

        $lingkungan = ZonaKolam::query()
            ->with('keadaanZona')
            ->where('kolam_id', $id)
            ->orderBy('id')
            ->get()
            ->filter(fn (ZonaKolam $zona): bool => $zona->keadaanZona instanceof KeadaanZona)
            ->map(fn (ZonaKolam $zona): array => [
                'nama' => $zona->nama,
                'suhu_asli' => (float) $zona->keadaanZona->suhu_asli,
                'ph_asli' => (float) $zona->keadaanZona->ph_asli,
                'do_asli' => (float) $zona->keadaanZona->do_asli,
                'amonia_asli' => (float) $zona->keadaanZona->amonia_asli,
                'sisa_pakan_g' => (float) $zona->keadaanZona->sisa_pakan_g,
            ])
            ->values();

        return response()->json([
            'kolam' => $kolam,
            'ikan' => $ringkasanIkan,
            'lingkungan' => $lingkungan,
        ]);
    }
}
