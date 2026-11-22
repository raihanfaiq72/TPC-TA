<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ikan;
use App\Models\KeadaanZona;
use App\Models\Kolam;
use App\Models\SkenarioSimulasi;
use App\Models\ZonaKolam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

class LingkunganController extends Controller
{
    public function perbarui(int $kolamId, string $waktu): void
    {
        /** @var array<string, float|int>|null $config */
        $config = config('sandbox.lingkungan');

        if (! is_array($config)) {
            throw new RuntimeException(
                'Konfigurasi sandbox.lingkungan belum tersedia.',
            );
        }

        $dtHari = (float) config('sandbox.tick_menit') / 1440;
        $dtJam = (float) config('sandbox.tick_menit') / 60;

        $kolam = Kolam::query()->select(['id', 'volume_air_m3'])->findOrFail($kolamId);

        $biomassaPerZona = Ikan::query()->selectRaw('zona_id, SUM(bobot_g) as biomassa_g')->where('kolam_id', $kolamId)->where('status', 'hidup')->groupBy('zona_id')->pluck('biomassa_g', 'zona_id');

        $zona = ZonaKolam::query()->where('kolam_id', $kolamId)->with(['keadaanZona', 'profilLingkungans'])->orderBy('id')->get();

        // skenario aktif waktu ini selain ganti air
        $skenario = SkenarioSimulasi::query()->where('kolam_id', $kolamId)->where('mulai', '<=', $waktu)->where('selesai', '>', $waktu)->where('jenis_kejadian', '!=', 'ganti_air')->get();

        $dampakHujan = 0.0;
        $dampakAerator = 0.0;
        $ppmJamAmonia = 0.0;

        foreach ($skenario as $skena) {
            if ($skena->jenis_kejadian === 'hujan_malam') {
                $dampakHujan += (float) $skena->besar_dampak;
            }

            if ($skena->jenis_kejadian === 'aerator_mati') {
                $dampakAerator = max($dampakAerator, (float) $skena->besar_dampak);
            }

            if ($skena->jenis_kejadian === 'lonjakan_amonia') {
                $ppmJamAmonia += (float) $skena->besar_dampak;
            }
        }

        // ganti air (diterapkan sekali)
        $gantiAir = SkenarioSimulasi::query()->where('kolam_id', $kolamId)->where('jenis_kejadian', 'ganti_air')->where('sudah_diterapkan', false)->where('mulai', '<=', $waktu)->get();

        $fraksiGanti = (float) $gantiAir->max(fn (SkenarioSimulasi $gege) => min(1.0, max(0.0, (float) $gege->besar_dampak)));

        $waktuCarbon = Carbon::parse($waktu);
        $jam = $waktuCarbon->hour + $waktuCarbon->minute / 60;
        $fase = $config['puncak_suhu_jam'] - 6;

        // hitung keadaan baru per zona

        /**
         * @var array<string, array{
         *     keadaan: KeadaanZona,
         *     vol: float,
         *     suhu: float,
         *     do: float,
         *     amonia: float,
         *     sisa: float,
         *     ph_dasar: float,
         *     ph: float,
         *     jenuh: float
         * }> $baru
         */
        $baru = [];
        foreach ($zona as $z) {
            $keadaan = $z->keadaanZona;

            if (! $keadaan instanceof KeadaanZona) {
                continue;
            }

            $pSuhu = $z->profilLingkungans->firstWhere('parameter', 'suhu');
            $pPh = $z->profilLingkungans->firstWhere('parameter', 'ph');

            if (! $pSuhu || ! $pPh) {
                throw new RuntimeException(
                    "Profil lingkungan zona {$z->id} tidak lengkap.",
                );
            }

            $vol = max(0.01, $kolam->volume_air_m3 * $z->porsi_volume);
            $biomassa = (float) ($biomassaPerZona[$z->id] ?? 0);

            // suhu
            $target = $pSuhu->nilai_dasar + $pSuhu->offset_zona
                + $pSuhu->amplitudo_harian * sin(2 * M_PI * ($jam - $fase) / 24)
                + $dampakHujan;

            $suhu = $keadaan->suhu_asli
                + ($target - $keadaan->suhu_asli) * $config['suhu_relaksasi_per_tick'];

            // DO
            $kgm3 = ($biomassa / 1000) / $vol;
            $jenuh = $config['do_jenuh_ref'] - $config['do_jenuh_per_derajat'] * ($suhu - 20);
            $rea = ($z->nama === 'atas'
                ? $config['rea_atas_per_hari']
                : $config['rea_bawah_per_hari']) * (1 - $dampakAerator);

            $fSuhuResp = max(0.2, 1 + $config['resp_koef_suhu'] * ($suhu - 28));

            $dDo = $rea * ($jenuh - $keadaan->do_asli) * $dtHari
                - $config['resp_per_kg_m3_jam'] * $kgm3 * $fSuhuResp * $dtJam
                - $config['busuk_do_per_g_m3_jam']
                    * ($keadaan->sisa_pakan_g / $vol) * $dtJam;

            $do = max(0.0, $keadaan->do_asli + $dDo);

            // sisa pakan membusuk -> amonia
            $busukG = $keadaan->sisa_pakan_g * $config['sisa_pakan_peluruhan_tick'];
            $sisa = $keadaan->sisa_pakan_g - $busukG;

            // amonia
            $amonia = $keadaan->amonia_asli
                * exp(-$config['amonia_hilang_per_hari'] * $dtHari)
                + $config['amonia_dari_sisa_busuk'] * $busukG / $vol
                + $ppmJamAmonia * $dtJam;

            $baru[$z->nama] = [
                'keadaan' => $keadaan,
                'vol' => $vol,
                'suhu' => $suhu,
                'do' => $do,
                'amonia' => $amonia,
                'sisa' => $sisa,
                'ph_dasar' => $pPh->nilai_dasar + $pPh->offset_zona,
                'ph' => $keadaan->ph_asli,
                'jenuh' => $jenuh,
            ];
        }
        // ---- sisa pakan mengendap atas -> bawah
        if (isset($baru['atas'], $baru['bawah'])) {
            $pindah = $baru['atas']['sisa'] * $config['sisa_pakan_mengendap_tick'];

            $baru['atas']['sisa'] -= $pindah;
            $baru['bawah']['sisa'] += $pindah;

            // ---- pencampuran DO & amonia
            $m = $config['campur_antar_zona'];

            foreach (['do', 'amonia'] as $k) {
                $a = $baru['atas'][$k];
                $b = $baru['bawah'][$k];

                $baru['atas'][$k] = $a + $m * ($b - $a);
                $baru['bawah'][$k] = $b + $m * ($a - $b);
            }
        }

        // ---- ganti air (sekali), lalu pH, lalu tulis
        foreach ($baru as &$b) {
            if ($fraksiGanti > 0) {
                $b['amonia'] *= (1 - $fraksiGanti);
                $b['sisa'] *= (1 - $fraksiGanti);
                $b['do'] += ($b['jenuh'] - $b['do']) * $fraksiGanti;
                $b['ph'] += ($b['ph_dasar'] - $b['ph']) * $fraksiGanti;
            }

            $phTarget = $b['ph_dasar']
                - $config['ph_turun_per_ppm_amonia'] * min($b['amonia'], 3.0);

            $b['ph'] = $b['ph'] + ($phTarget - $b['ph']) * $config['ph_relaksasi_per_tick'];

            $b['keadaan']->update([
                'suhu_asli' => round($b['suhu'], 3),
                'ph_asli' => round($b['ph'], 3),
                'do_asli' => round($b['do'], 3),
                'amonia_asli' => round($b['amonia'], 3),
                'sisa_pakan_g' => round(max(0, $b['sisa']), 3),
                'diperbarui_pada' => $waktu,
            ]);
        }
        unset($b);

        foreach ($gantiAir as $g) {
            $g->update(['sudah_diterapkan' => true]);
        }
    }

    public function show(int $kolamId): JsonResponse
    {
        $kolam = Kolam::query()
            ->select(['id', 'kode', 'nama', 'volume_air_m3', 'waktu_sandbox_sekarang'])
            ->find($kolamId);

        if (! $kolam instanceof Kolam) {
            return response()->json([
                'message' => 'Kolam tidak ditemukan.',
            ], 404);
        }

        $zona = ZonaKolam::query()
            ->where('kolam_id', $kolamId)
            ->with(['keadaanZona', 'profilLingkungans'])
            ->orderBy('id')
            ->get()
            ->map(fn (ZonaKolam $z): array => [
                'id' => $z->id,
                'nama' => $z->nama,
                'kedalaman_cm' => $z->kedalaman_cm,
                'porsi_volume' => $z->porsi_volume,
                'keadaan' => $z->keadaanZona,
                'profil' => $z->profilLingkungans,
            ]);

        $skenario = SkenarioSimulasi::query()
            ->where('kolam_id', $kolamId)
            ->orderBy('mulai')
            ->get();

        return response()->json([
            'kolam' => $kolam,
            'zona' => $zona,
            'skenario' => $skenario,
        ]);
    }

    public function tick(Request $request, int $kolamId): JsonResponse
    {
        $data = $request->validate([
            'waktu' => ['required', 'date'],
        ]);

        if (! is_array(config('sandbox.lingkungan'))) {
            return response()->json([
                'message' => 'Konfigurasi sandbox.lingkungan belum tersedia.',
            ], 503);
        }

        if (! Kolam::query()->whereKey($kolamId)->exists()) {
            return response()->json([
                'message' => 'Kolam tidak ditemukan.',
            ], 404);
        }

        $this->perbarui($kolamId, Carbon::parse($data['waktu'])->format('Y-m-d H:i:s'));

        return $this->show($kolamId);
    }

    public function amonia(Request $request, int $kolamId): JsonResponse
    {
        $data = $request->validate([
            'gram_tan' => ['required', 'numeric', 'gt:0'],
        ]);

        if (! Kolam::query()->whereKey($kolamId)->exists()) {
            return response()->json([
                'message' => 'Kolam tidak ditemukan.',
            ], 404);
        }

        $this->tambahAmonia($kolamId, (float) $data['gram_tan']);

        return $this->show($kolamId);
    }

    public function sisaPakan(Request $request, int $kolamId): JsonResponse
    {
        $data = $request->validate([
            'gram' => ['required', 'numeric', 'gt:0'],
        ]);

        if (! Kolam::query()->whereKey($kolamId)->exists()) {
            return response()->json([
                'message' => 'Kolam tidak ditemukan.',
            ], 404);
        }

        $this->tambahSisaPakan($kolamId, (float) $data['gram']);

        return $this->show($kolamId);
    }

    public function tambahAmonia(int $kolamId, float $gramTan): void
    {
        if ($gramTan <= 0) {
            return;
        }

        $kolam = Kolam::query()
            ->select(['id', 'volume_air_m3'])
            ->findOrFail($kolamId);

        $delta = round($gramTan / max(0.01, $kolam->volume_air_m3), 4);

        KeadaanZona::query()
            ->where('kolam_id', $kolamId)
            ->increment('amonia_asli', $delta);
    }

    public function tambahSisaPakan(int $kolamId, float $gram): void
    {
        if ($gram <= 0) {
            return;
        }

        $zonaAtasId = ZonaKolam::query()
            ->where('kolam_id', $kolamId)
            ->where('nama', 'atas')
            ->value('id');

        if (! $zonaAtasId) {
            return;
        }

        KeadaanZona::query()
            ->whereKey($zonaAtasId)
            ->increment('sisa_pakan_g', round($gram, 3));
    }
}
