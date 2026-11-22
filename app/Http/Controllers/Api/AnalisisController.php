<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\AturanAmbang;
use App\Models\Ikan;
use App\Models\Kolam;
use App\Models\PembacaanSensor;
use App\Models\RekomendasiPanen;
use App\Models\SamplingPertumbuhan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AnalisisController extends Controller
{
    public function evaluasiAlert(int $kolamId, string $waktu): int
    {
        $waktuCarbon = Carbon::parse($waktu);

        $pembacaan = PembacaanSensor::query()
            ->with('zona:id,nama')
            ->where('kolam_id', $kolamId)
            ->where('waktu_ukur', $waktuCarbon)
            ->where('kualitas_data', 'normal')
            ->whereNotNull('nilai')
            ->get();

        if ($pembacaan->isEmpty()) {
            return 0;
        }

        $aturan = AturanAmbang::query()
            ->whereIn('parameter', $pembacaan->pluck('parameter')->unique()->values())
            ->get()
            ->groupBy('parameter');

        $batas = $waktuCarbon->copy()->subHours(6);
        $jumlahAlert = 0;

        foreach ($pembacaan as $baca) {
            $kandidat = $aturan->get($baca->parameter, collect());

            $rule = $kandidat->first(
                fn (AturanAmbang $item): bool => $item->tingkat !== 'aman'
                    && $this->cocokDenganPita($baca, $item),
            );

            if (! $rule instanceof AturanAmbang) {
                continue;
            }

            $duplikat = Alert::query()
                ->where('kolam_id', $kolamId)
                ->where('parameter', $baca->parameter)
                ->where('tingkat', $rule->tingkat)
                ->where('status', 'baru')
                ->where('waktu', '>', $batas)
                ->when(
                    $baca->zona_id === null,
                    fn ($query) => $query->whereNull('zona_id'),
                    fn ($query) => $query->where('zona_id', $baca->zona_id),
                )
                ->exists();

            if ($duplikat) {
                continue;
            }

            Alert::query()->create([
                'kolam_id' => $kolamId,
                'zona_id' => $baca->zona_id,
                'aturan_id' => $rule->id,
                'parameter' => $baca->parameter,
                'nilai' => $baca->nilai,
                'tingkat' => $rule->tingkat,
                'waktu' => $baca->waktu_ukur,
                'status' => 'baru',
            ]);

            $jumlahAlert++;
        }

        return $jumlahAlert;
    }

    private function cocokDenganPita(PembacaanSensor $pembacaan, AturanAmbang $aturan): bool
    {
        if ($aturan->parameter !== $pembacaan->parameter) {
            return false;
        }

        if ($aturan->zona !== null && $aturan->zona !== $pembacaan->zona?->nama) {
            return false;
        }

        if ($pembacaan->nilai === null) {
            return false;
        }

        if ($aturan->batas_min !== null && $pembacaan->nilai < $aturan->batas_min) {
            return false;
        }

        if ($aturan->batas_maks !== null && $pembacaan->nilai >= $aturan->batas_maks) {
            return false;
        }

        return true;
    }

    /**
     * @return array{status: string, skor_kesiapan: float, alasan: string}|null
     */
    public function rekomendasiPanen(int $kolamId, string $waktu): ?array
    {
        /** @var array<string, float|int>|null $cfg */
        $cfg = config('sandbox.rekomendasi');

        if (! is_array($cfg)) {
            return null;
        }

        $kolam = Kolam::query()
            ->select(['id', 'bobot_target_panen_g'])
            ->find($kolamId);

        if (! $kolam instanceof Kolam) {
            return null;
        }

        $samp = SamplingPertumbuhan::query()
            ->where('kolam_id', $kolamId)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(2)
            ->get(['tanggal', 'bobot_rata_g']);

        if ($samp->isEmpty()) {
            return null;
        }

        $waktuCarbon = Carbon::parse($waktu);
        $target = max(0.0001, (float) $kolam->bobot_target_panen_g);
        $bobot = (float) $samp[0]->bobot_rata_g;
        $skorBobot = min(100.0, $bobot / $target * 100);

        $jendelaAir = max(1, (int) $cfg['jendela_air_hari']);
        $parameter = ['suhu', 'ph', 'do', 'amonia'];

        $pembacaan = PembacaanSensor::query()
            ->with('zona:id,nama')
            ->where('kolam_id', $kolamId)
            ->whereIn('parameter', $parameter)
            ->where('kualitas_data', 'normal')
            ->where('waktu_ukur', '>', $waktuCarbon->copy()->subDays($jendelaAir))
            ->get();

        $aturanAman = AturanAmbang::query()
            ->whereIn('parameter', $parameter)
            ->where('tingkat', 'aman')
            ->get();

        $totalPembacaan = $pembacaan->count();
        $pembacaanAman = $pembacaan
            ->filter(fn (PembacaanSensor $baca): bool => $aturanAman
                ->contains(fn (AturanAmbang $aturan): bool => $this->cocokDenganPita($baca, $aturan)))
            ->count();

        $skorAir = $totalPembacaan > 0
            ? $pembacaanAman / $totalPembacaan * 100
            : 0.0;

        $bobotSkor = (float) $cfg['bobot_skor'];
        $airSkor = (float) $cfg['air_skor'];
        $skor = $bobotSkor * $skorBobot + $airSkor * $skorAir;

        $hampirSiap = (float) $cfg['hampir_siap'];

        $status = $bobot >= $target
            ? 'siap panen'
            : ($bobot >= $hampirSiap * $target ? 'hampir siap' : 'belum siap');

        $hari = null;

        if ($samp->count() === 2 && $bobot < $target) {
            $t0 = Carbon::parse($samp[0]->tanggal);
            $t1 = Carbon::parse($samp[1]->tanggal);
            $dt = ((int) $t0->timestamp - (int) $t1->timestamp) / 86400;
            $w0 = (float) $samp[1]->bobot_rata_g;

            if ($dt > 0 && $w0 > 0 && $bobot > $w0) {
                $sgr = (log($bobot) - log($w0)) / $dt;
                $hari = round(log($target / $bobot) / $sgr, 1);
            }
        }

        $alasan = sprintf(
            'Bobot rata-rata sampling %.1f g dari target %.0f g (%.0f%%); kualitas air aman %.0f%% selama %d hari terakhir.',
            $bobot,
            $target,
            $skorBobot,
            $skorAir,
            $jendelaAir,
        );

        if ($hari !== null) {
            $alasan .= sprintf(
                ' Estimasi %.1f hari lagi mencapai target pada laju tumbuh terakhir.',
                $hari,
            );
        }

        RekomendasiPanen::query()->updateOrCreate(
            [
                'kolam_id' => $kolamId,
                'tanggal_evaluasi' => $waktuCarbon,
            ],
            [
                'bobot_rata_g' => round($bobot, 3),
                'skor_bobot' => round($skorBobot, 2),
                'skor_air' => round($skorAir, 2),
                'skor_kesiapan' => round($skor, 2),
                'estimasi_hari_ke_target' => $hari,
                'status' => $status,
                'alasan' => $alasan,
            ],
        );

        return [
            'status' => $status,
            'skor_kesiapan' => round($skor, 2),
            'alasan' => $alasan,
        ];
    }

    public function daftarAlert(int $id): JsonResponse
    {
        $alerts = Alert::query()
            ->with('zona:id,nama')
            ->where('kolam_id', $id)
            ->orderByDesc('waktu')
            ->limit(200)
            ->get()
            ->map(fn (Alert $alert): array => [
                'waktu' => $alert->waktu->toDateTimeString(),
                'zona' => $alert->zona?->nama,
                'parameter' => $alert->parameter,
                'nilai' => (float) $alert->nilai,
                'tingkat' => $alert->tingkat,
                'status' => $alert->status,
            ])
            ->values();

        return response()->json($alerts);
    }

    public function rekomendasiTerbaru(int $id): JsonResponse
    {
        $rekomendasi = RekomendasiPanen::query()
            ->where('kolam_id', $id)
            ->orderByDesc('id')
            ->first();

        return response()->json($rekomendasi);
    }

    /**
     * VALIDASI SAJA: kebenaran dunia sandbox, jangan dipakai sistem keputusan.
     */
    public function ringkasanValidasi(int $id): JsonResponse
    {
        $ikan = Ikan::query()
            ->where('kolam_id', $id)
            ->get(['status', 'bobot_g', 'panjang_cm', 'penyebab_mati']);

        $total = $ikan->count();
        $hidup = $ikan->where('status', 'hidup')->values();

        $bobotRata = $hidup->isEmpty()
            ? null
            : round((float) $hidup->avg('bobot_g'), 2);

        $panjangRata = $hidup->isEmpty()
            ? null
            : round((float) $hidup->avg('panjang_cm'), 2);

        $bobotSd = null;

        if ($hidup->isNotEmpty()) {
            $rata = (float) $hidup->avg('bobot_g');
            $jumlahKuadrat = $hidup->sum(
                fn (Ikan $item): float => ((float) $item->bobot_g - $rata) ** 2,
            );

            $bobotSd = round(sqrt($jumlahKuadrat / $hidup->count()), 2);
        }

        $ringkasan = [
            'awal' => $total,
            'hidup' => $hidup->count(),
            'sr_persen' => $total > 0
                ? round($hidup->count() / $total * 100, 2)
                : null,
            'bobot_rata_g' => $bobotRata,
            'bobot_sd_g' => $bobotSd,
            'panjang_rata_cm' => $panjangRata,
            'biomassa_g' => $hidup->isEmpty()
                ? null
                : round((float) $hidup->sum('bobot_g'), 1),
        ];

        $sebab = $ikan
            ->where('status', 'mati')
            ->groupBy(fn (Ikan $item): string => $item->penyebab_mati ?? '')
            ->map(fn ($rows, $cause): array => [
                'penyebab_mati' => $cause === '' ? null : $cause,
                'n' => $rows->count(),
            ])
            ->values();

        return response()->json([
            'ringkasan' => $ringkasan,
            'penyebab_mati' => $sebab,
        ]);
    }
}
