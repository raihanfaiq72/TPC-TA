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
        $jamTick = $waktuTick->format('H:i');
        $biomassa = (float) Ikan::query()
            ->where('kolam_id', $kolamId)
            ->where('status', 'hidup')
            ->sum('bobot_g');

        if ($biomassa <= 0) {
            return;
        }

        $jadwal = JadwalPakan::query()
            ->where('kolam_id', $kolamId)
            ->where('aktif', true)
            ->get();
        $jadwal = $jadwal
            ->filter(fn (JadwalPakan $item): bool => substr((string) $item->jam, 0, 5) === $jamTick)
            ->values();

        if ($jadwal->isEmpty()) {
            return;
        }

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

            $sudahDicatat = LogPakan::query()
                ->where('kolam_id', $kolamId)
                ->where('jadwal_id', $item->id)
                ->where('waktu', $waktuTick)
                ->exists();

            if ($sudahDicatat) {
                continue;
            }

            $zonaAtas->keadaanZona->increment('sisa_pakan_g', round($jumlah, 3));

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
