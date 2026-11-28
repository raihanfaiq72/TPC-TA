<?php

namespace App\Services;

use App\Http\Controllers\Api\KolamController;
use App\Models\Ikan;
use App\Models\Kolam;
use App\Models\LogPakan;
use App\Models\RekomendasiPanen;
use App\Models\SamplingPertumbuhan;
use App\Models\SiklusKolam;
use Illuminate\Support\Carbon;

class KolamCycleService
{
    /**
     * Menyimpan rencana siklus dari form Tambah kolam tanpa mereset ikan.
     *
     * @param  array<int, array{jumlah_tebar: int, target_bobot_g: float|int}>  $cycles
     */
    public function createConfigured(Kolam $kolam, array $cycles): void
    {
        if ($cycles === []) {
            $cycles = [[
                'jumlah_tebar' => max(1, (int) $kolam->jumlah_ikan_awal),
                'target_bobot_g' => max(0.01, (float) $kolam->bobot_target_panen_g),
            ]];
        }

        $mulai = Carbon::parse($kolam->waktu_sandbox_sekarang);
        $first = null;

        foreach (array_values($cycles) as $index => $cycle) {
            $row = SiklusKolam::query()->create([
                'kolam_id' => $kolam->id,
                'urutan' => $index + 1,
                'jumlah_tebar' => max(1, (int) $cycle['jumlah_tebar']),
                'target_bobot_g' => max(0.01, (float) $cycle['target_bobot_g']),
                'status' => $index === 0 ? 'aktif' : 'pending',
                'tanggal_mulai' => $index === 0 ? $mulai : null,
            ]);

            $first ??= $row;
        }

        if ($first instanceof SiklusKolam) {
            $kolam->update([
                'jumlah_ikan_awal' => $first->jumlah_tebar,
                'bobot_target_panen_g' => $first->target_bobot_g,
            ]);
        }
    }

    /**
     * Menyelaraskan rencana siklus dari form Edit kolam.
     *
     * @param  array<int, array{jumlah_tebar: int, target_bobot_g: float|int}>  $cycles
     */
    public function syncConfigured(Kolam $kolam, array $cycles): void
    {
        $existing = SiklusKolam::query()
            ->where('kolam_id', $kolam->id)
            ->whereIn('status', ['aktif', 'pending'])
            ->orderBy('urutan')
            ->get();

        $mulai = Carbon::parse($kolam->waktu_sandbox_sekarang);
        $active = $existing->firstWhere('status', 'aktif');

        foreach (array_values($cycles) as $index => $cycle) {
            $row = $existing->get($index);

            if ($row instanceof SiklusKolam) {
                $row->update([
                    'jumlah_tebar' => max(1, (int) $cycle['jumlah_tebar']),
                    'target_bobot_g' => max(0.01, (float) $cycle['target_bobot_g']),
                ]);

                if ($row->status === 'aktif') {
                    $active = $row;
                }
            } else {
                SiklusKolam::query()->create([
                    'kolam_id' => $kolam->id,
                    'urutan' => ((int) SiklusKolam::query()->where('kolam_id', $kolam->id)->max('urutan')) + 1,
                    'jumlah_tebar' => max(1, (int) $cycle['jumlah_tebar']),
                    'target_bobot_g' => max(0.01, (float) $cycle['target_bobot_g']),
                    'status' => 'pending',
                ]);
            }
        }

        $existing->slice(count($cycles))->each(fn (SiklusKolam $row) => $row->delete());

        if (! $active instanceof SiklusKolam) {
            $active = SiklusKolam::query()
                ->where('kolam_id', $kolam->id)
                ->whereIn('status', ['aktif', 'pending'])
                ->orderBy('urutan')
                ->first();

            if ($active instanceof SiklusKolam) {
                $active->update([
                    'status' => 'aktif',
                    'tanggal_mulai' => $mulai,
                ]);
            }
        }

        if ($active instanceof SiklusKolam) {
            $kolam->update([
                'jumlah_ikan_awal' => $active->jumlah_tebar,
                'bobot_target_panen_g' => $active->target_bobot_g,
            ]);
        }
    }

    /**
     * Memastikan ada siklus aktif tanpa mereset ikan yang sedang berjalan.
     */
    public function ensureActive(Kolam $kolam): ?SiklusKolam
    {
        $active = SiklusKolam::query()
            ->where('kolam_id', $kolam->id)
            ->where('status', 'aktif')
            ->orderByDesc('urutan')
            ->first();

        if ($active instanceof SiklusKolam) {
            return $active;
        }

        $pending = SiklusKolam::query()
            ->where('kolam_id', $kolam->id)
            ->where('status', 'pending')
            ->orderBy('urutan')
            ->first();

        if ($pending instanceof SiklusKolam) {
            $pending->update([
                'status' => 'aktif',
                'tanggal_mulai' => Carbon::parse($kolam->waktu_sandbox_sekarang),
            ]);
            $this->resetForCycle($kolam, $pending);

            return $pending->fresh();
        }

        return SiklusKolam::query()->create([
            'kolam_id' => $kolam->id,
            'urutan' => ((int) SiklusKolam::query()->where('kolam_id', $kolam->id)->max('urutan')) + 1,
            'jumlah_tebar' => max(1, (int) $kolam->jumlah_ikan_awal),
            'target_bobot_g' => max(0.01, (float) $kolam->bobot_target_panen_g),
            'status' => 'aktif',
            'tanggal_mulai' => Carbon::parse($kolam->waktu_sandbox_sekarang),
        ]);
    }

    /**
     * Membuat rencana siklus dan langsung memulai siklus pertama.
     *
     * @param  array<int, array{jumlah_tebar: int, target_bobot_g: float|int}>  $cycles
     */
    public function startPlan(Kolam $kolam, array $cycles): ?SiklusKolam
    {
        if ($cycles === []) {
            return null;
        }

        // Rencana yang belum selesai diganti; riwayat selesai tetap disimpan.
        SiklusKolam::query()
            ->where('kolam_id', $kolam->id)
            ->whereIn('status', ['pending', 'aktif'])
            ->delete();

        $urutanAwal = (int) SiklusKolam::query()
            ->where('kolam_id', $kolam->id)
            ->max('urutan') + 1;
        $mulai = Carbon::parse($kolam->waktu_sandbox_sekarang);

        $first = null;

        foreach (array_values($cycles) as $index => $cycle) {
            $row = SiklusKolam::query()->create([
                'kolam_id' => $kolam->id,
                'urutan' => $urutanAwal + $index,
                'jumlah_tebar' => max(1, (int) $cycle['jumlah_tebar']),
                'target_bobot_g' => max(0.01, (float) $cycle['target_bobot_g']),
                'status' => $index === 0 ? 'aktif' : 'pending',
                'tanggal_mulai' => $index === 0 ? $mulai : null,
            ]);

            $first ??= $row;
        }

        if (! $first instanceof SiklusKolam) {
            return null;
        }

        $this->resetForCycle($kolam, $first);

        return $first->fresh();
    }

    /**
     * Menutup siklus aktif dan menyiapkan siklus berikutnya.
     *
     * @return array{selesai: bool, siklus: SiklusKolam|null}
     */
    public function advance(Kolam $kolam): array
    {
        $aktif = SiklusKolam::query()
            ->where('kolam_id', $kolam->id)
            ->where('status', 'aktif')
            ->orderByDesc('urutan')
            ->first();

        if ($aktif instanceof SiklusKolam) {
            $hidup = Ikan::query()
                ->where('kolam_id', $kolam->id)
                ->where('status', 'hidup');
            $mati = Ikan::query()
                ->where('kolam_id', $kolam->id)
                ->where('status', 'mati')
                ->count();

            $aktif->update([
                'status' => 'selesai',
                'tanggal_selesai' => Carbon::parse($kolam->waktu_sandbox_sekarang),
                'bobot_akhir_g' => round((float) ($hidup->avg('bobot_g') ?? 0), 2),
                'biomassa_akhir_g' => round((float) $hidup->sum('bobot_g'), 2),
                'jumlah_mati' => $mati,
            ]);
        }

        $berikutnya = SiklusKolam::query()
            ->where('kolam_id', $kolam->id)
            ->where('status', 'pending')
            ->orderBy('urutan')
            ->first();

        if (! $berikutnya instanceof SiklusKolam) {
            $kolam->update([
                'status' => 'selesai',
                'auto_pilot_aktif' => false,
            ]);

            return ['selesai' => true, 'siklus' => null];
        }

        $berikutnya->update([
            'status' => 'aktif',
            'tanggal_mulai' => Carbon::parse($kolam->waktu_sandbox_sekarang),
        ]);

        $this->resetForCycle($kolam, $berikutnya);

        return ['selesai' => false, 'siklus' => $berikutnya->fresh()];
    }

    private function resetForCycle(Kolam $kolam, SiklusKolam $siklus): void
    {
        $mulai = Carbon::parse($kolam->waktu_sandbox_sekarang);

        $kolam->update([
            'jumlah_ikan_awal' => $siklus->jumlah_tebar,
            'bobot_target_panen_g' => $siklus->target_bobot_g,
            'waktu_mulai_sandbox' => $mulai,
            'waktu_sandbox_sekarang' => $mulai,
            'status' => 'aktif',
            'auto_pilot_aktif' => true,
        ]);

        // Reset yang sudah ada menangani ikan, lingkungan, sensor, dan perangkat.
        app(KolamController::class)->reset($kolam->id);

        // Data milik siklus lama tidak boleh tercampur ke siklus baru.
        SamplingPertumbuhan::query()->where('kolam_id', $kolam->id)->delete();
        LogPakan::query()->where('kolam_id', $kolam->id)->delete();
        RekomendasiPanen::query()->where('kolam_id', $kolam->id)->delete();

        $kolam->update([
            'status' => 'aktif',
            'auto_pilot_aktif' => true,
        ]);
    }
}
