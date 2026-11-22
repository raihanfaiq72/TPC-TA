<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ikan;
use App\Models\JadwalPakan;
use App\Models\KeadaanZona;
use App\Models\Kolam;
use App\Models\LogPakan;
use App\Models\ZonaKolam;
use Illuminate\Support\Carbon;

class PakanController extends Controller
{
    /**
     * Jalankan jadwal pakan yang jatuh pada waktu tick.
     */
    public function jalankanJadwal(int $kolamId, string $waktu): void
    {
        $kolam = Kolam::query()->find($kolamId);

        if (! $kolam instanceof Kolam) {
            return;
        }

        $waktuTick = Carbon::parse($waktu);

        $tickMenit = max(1, (int) config('sandbox.tick_menit', 10));
        $menitTick = $waktuTick->hour * 60 + $waktuTick->minute;

        $jadwal = JadwalPakan::query()
            ->where('kolam_id', $kolamId)
            ->where('aktif', true)
            ->get();

        $jadwal = $jadwal
            ->filter(function (JadwalPakan $item) use ($menitTick, $tickMenit): bool {
                $jam = Carbon::parse((string) $item->jam);
                $menitJadwal = $jam->hour * 60 + $jam->minute;
                $selisih = ($menitTick - $menitJadwal + 1440) % 1440;

                return $selisih < $tickMenit;
            })
            ->values();

        // Jangan membaca seluruh populasi pada tick yang tidak ada jadwal pakan.
        if ($jadwal->isEmpty()) {
            return;
        }

        $biomassa = (float) Ikan::query()
            ->where('kolam_id', $kolamId)
            ->where('status', 'hidup')
            ->sum('bobot_g');

        if ($biomassa <= 0) {
            return;
        }

        $ikan = Ikan::query()
            ->where('kolam_id', $kolamId)
            ->where('status', 'hidup')
            ->get(['id', 'bobot_g', 'isi_lambung_persen', 'hari_pakan_g']);

        $kapasitasLambung = (float) config('sandbox.ikan.kapasitas_lambung_bobot', 0.035);

        $zonaAtas = ZonaKolam::query()
            ->where('kolam_id', $kolamId)
            ->where('nama', 'atas')
            ->with('keadaanZona')
            ->first();

        if (! $zonaAtas instanceof ZonaKolam
            || ! $zonaAtas->keadaanZona instanceof KeadaanZona) {
            return;
        }

        foreach ($jadwal as $item) {
            $jumlah = $biomassa
                * ((float) $kolam->dosis_harian_persen / 100)
                * ((float) $item->porsi_dosis_persen / 100);

            if ($jumlah <= 0) {
                continue;
            }

            $jadwalWaktu = $waktuTick->copy()->setTimeFromTimeString((string) $item->jam);

            if ($jadwalWaktu->greaterThan($waktuTick)) {
                $jadwalWaktu->subDay();
            }

            $sudahDicatat = LogPakan::query()
                ->where('kolam_id', $kolamId)
                ->where('jadwal_id', $item->id)
                ->where('waktu', '>=', $jadwalWaktu)
                ->where('waktu', '<=', $waktuTick)
                ->exists();

            if ($sudahDicatat) {
                continue;
            }

            $dimakan = 0.0;

            if ($kapasitasLambung > 0) {
                foreach ($ikan as $itemIkan) {
                    $bobot = (float) $itemIkan->bobot_g;
                    $kapasitas = $kapasitasLambung * $bobot;

                    if ($kapasitas <= 0) {
                        continue;
                    }

                    $bagian = $jumlah * ($bobot / $biomassa);
                    $isiSekarang = ((float) $itemIkan->isi_lambung_persen / 100) * $kapasitas;
                    $masuk = min($bagian, max(0.0, $kapasitas - $isiSekarang));

                    if ($masuk <= 0) {
                        continue;
                    }

                    $itemIkan->update([
                        'isi_lambung_persen' => min(100.0, (($isiSekarang + $masuk) / $kapasitas) * 100),
                        'hari_pakan_g' => (float) $itemIkan->hari_pakan_g + $masuk,
                    ]);

                    $dimakan += $masuk;
                }
            }

            $sisa = max(0.0, $jumlah - $dimakan);
            $zonaAtas->keadaanZona->increment('sisa_pakan_g', round($sisa, 3));

            LogPakan::query()->create([
                'kolam_id' => $kolamId,
                'jadwal_id' => $item->id,
                'jenis_pakan_id' => $item->jenis_pakan_id,
                'waktu' => $waktuTick,
                'jumlah_g' => round($jumlah, 2),
                'pemicu' => 'jadwal',
            ]);
        }
    }
}
