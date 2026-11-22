<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ikan;
use App\Models\KeadaanZona;
use App\Models\Kolam;
use App\Models\LogPakan;
use App\Models\ZonaKolam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SimulasiController extends Controller
{
    public function __construct(
        private LingkunganController $lingkungan,
        private PakanController $pakan,
        private IkanController $ikan,
        private SensorController $sensor,
        private AnalisisController $analisis,
    ) {}

    /**
     * Menjalankan satu atau beberapa tick simulasi.
     *
     * Body opsional:
     *   { "menit": 600 } // 10 jam / 60 tick pada tick_menit=10
     *
     * Jumlah tick dibatasi config sandbox.maks_tick_per_eksekusi dan
     * waktu eksekusi dibatasi sandbox.maks_detik_per_eksekusi. Frontend
     * mengirim batch berikutnya sampai target menit tercapai.
     */
    public function tick(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'menit' => ['sometimes', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($id, $data): JsonResponse {
            $kolam = Kolam::query()
                ->lockForUpdate()
                ->find($id);

            if (! $kolam instanceof Kolam) {
                return response()->json([
                    'message' => 'Kolam tidak ditemukan',
                ], 404);
            }

            $waktuAwal = Carbon::parse($kolam->waktu_sandbox_sekarang);
            $tickMenit = (int) config('sandbox.tick_menit');

            if ($tickMenit <= 0) {
                return response()->json([
                    'message' => 'Konfigurasi tick_menit tidak valid',
                ], 500);
            }

            $menitDiminta = (int) ($data['menit'] ?? $tickMenit);
            $tickDiminta = (int) ceil($menitDiminta / $tickMenit);
            $maksTick = max(1, (int) config('sandbox.maks_tick_per_eksekusi', 720));
            $jumlahTick = min($tickDiminta, $maksTick);
            $batasDetik = max(1.0, (float) config('sandbox.maks_detik_per_eksekusi', 15));

            $waktuSekarang = $waktuAwal->copy();
            $prosesHarian = false;
            $jumlahAlert = 0;
            $tickDijalankan = 0;
            $batasWaktu = microtime(true) + $batasDetik;

            for ($i = 0; $i < $jumlahTick; $i++) {
                if ($tickDijalankan > 0 && microtime(true) >= $batasWaktu) {
                    break;
                }

                $waktuTick = $waktuSekarang->format('Y-m-d H:i:s');
                $waktuBerikutnya = $waktuSekarang->copy()->addMinutes($tickMenit);

                // 1. Perbarui kondisi lingkungan.
                $this->lingkungan->perbarui($id, $waktuTick);

                // 2. Jalankan jadwal pemberian pakan.
                $this->pakan->jalankanJadwal($id, $waktuTick);

                // 3. Baca sensor dan evaluasi alert dari pembacaan baru.
                $this->sensor->bacaSemua($id, $waktuTick);
                $jumlahAlert += $this->analisis->evaluasiAlert($id, $waktuTick);

                // 4. Pergerakan ikan dan akumulasi paparan.
                $this->ikan->gerak($id, $waktuTick);

                // 5. Jika tanggal virtual berganti, proses hari yang selesai.
                $hariBerganti = $waktuSekarang->toDateString()
                    !== $waktuBerikutnya->toDateString();

                if ($hariBerganti) {
                    $this->ikan->prosesHarian(
                        $id,
                        $waktuSekarang->toDateString(),
                    );

                    // Evaluasi rekomendasi panen mengikuti pertumbuhan harian.
                    $this->analisis->rekomendasiPanen(
                        $id,
                        $waktuBerikutnya->format('Y-m-d H:i:s'),
                    );

                    $prosesHarian = true;
                }

                $waktuSekarang = $waktuBerikutnya;
                $tickDijalankan++;
            }

            // 6. Simpan waktu virtual paling akhir sekali saja.
            $kolam->update([
                'waktu_sandbox_sekarang' => $waktuSekarang,
            ]);

            $pakan = LogPakan::query()
                ->where('kolam_id', $id)
                ->where('waktu', '>=', $waktuAwal)
                ->where('waktu', '<', $waktuSekarang)
                ->get(['waktu', 'jumlah_g']);

            return response()->json([
                'message' => 'Tick simulasi berhasil dijalankan',
                'waktu_sebelum' => $waktuAwal->format('Y-m-d H:i:s'),
                'waktu_sekarang' => $waktuSekarang->format('Y-m-d H:i:s'),
                'menit_dijalankan' => $tickDijalankan * $tickMenit,
                'tick_dijalankan' => $tickDijalankan,
                'tick_menit' => $tickMenit,
                'selesai' => $tickDijalankan >= $tickDiminta,
                'proses_harian' => $prosesHarian,
                'pakan_kejadian' => $pakan->count(),
                'pakan_g' => round((float) $pakan->sum('jumlah_g'), 2),
                'pakan_events' => $pakan->map(fn (LogPakan $item): array => [
                    'waktu' => $item->waktu->format('Y-m-d H:i:s'),
                    'gram' => round((float) $item->jumlah_g, 2),
                ])->values(),
                'alert_baru' => $jumlahAlert,
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
