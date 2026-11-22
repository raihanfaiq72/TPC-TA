<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ikan;
use App\Models\IkanLogHarian;
use App\Models\JadwalPakan;
use App\Models\KeadaanZona;
use App\Models\Kolam;
use App\Models\ZonaKolam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

class IkanController extends Controller
{
    private const TICK_PER_JAM = 6.0;

    public function __construct(private LingkunganController $lingkungan) {}

    public static function faktorSuhu(float $t): float
    {
        if ($t >= 28 && $t <= 31) {
            return 1.0;
        }

        return $t < 28
            ? max(0.0, ($t - 20) / 8)
            : max(0.0, (36 - $t) / 5);
    }

    public static function faktorDo(float $do): float
    {
        if ($do >= 5) {
            return 1.0;
        }

        if ($do >= 3) {
            return 0.5 + 0.5 * ($do - 3) / 2;
        }

        return max(0.05, 0.5 * $do / 3);
    }

    private static function faktorPh(float $ph): float
    {
        if ($ph >= 6.5 && $ph <= 8.5) {
            return 1.0;
        }

        return $ph < 6.5
            ? max(0.0, ($ph - 5) / 1.5)
            : max(0.0, (10 - $ph) / 1.5);
    }

    private static function faktorAmonia(float $amonia): float
    {
        if ($amonia <= 0.5) {
            return 1.0;
        }

        if ($amonia <= 1.5) {
            return 1 - 0.3 * ($amonia - 0.5);
        }

        return max(0.05, 0.7 - 0.5 * ($amonia - 1.5));
    }

    /**
     * @return array<string, float|int>
     */
    private function konfigurasiIkan(): array
    {
        /** @var array<string, float|int>|null $config */
        $config = config('sandbox.ikan');

        if (! is_array($config)) {
            throw new RuntimeException('Konfigurasi sandbox.ikan belum tersedia.');
        }

        return $config;
    }

    private function acak(): float
    {
        return mt_rand() / (mt_getrandmax() + 1);
    }

    public function gerak(int $kolamId, string $waktu): void
    {
        $c = $this->konfigurasiIkan();

        $zona = ZonaKolam::query()
            ->where('kolam_id', $kolamId)
            ->get(['id', 'nama'])
            ->keyBy('nama');

        $atas = $zona->get('atas');
        $bawah = $zona->get('bawah');

        if (! $atas instanceof ZonaKolam || ! $bawah instanceof ZonaKolam) {
            throw new RuntimeException("Zona atas/bawah kolam {$kolamId} tidak lengkap.");
        }

        $keadaan = KeadaanZona::query()
            ->where('kolam_id', $kolamId)
            ->get([
                'zona_id',
                'suhu_asli',
                'ph_asli',
                'do_asli',
                'amonia_asli',
            ])
            ->keyBy('zona_id');

        $keadaanAtas = $keadaan->get($atas->id);
        $keadaanBawah = $keadaan->get($bawah->id);

        if (! $keadaanAtas instanceof KeadaanZona || ! $keadaanBawah instanceof KeadaanZona) {
            throw new RuntimeException("Keadaan zona kolam {$kolamId} tidak lengkap.");
        }

        $bonusDo = (float) $keadaanBawah->do_asli < (float) $c['ambang_do_bawah']
            ? (float) $c['bonus_naik_do_bawah_rendah']
            : 0.0;

        $penaltiSuhu = (float) $keadaanAtas->suhu_asli > (float) $c['ambang_suhu_atas']
            ? (float) $c['penalti_naik_suhu_atas']
            : 0.0;

        $waktuCarbon = Carbon::parse($waktu);

        $dekatPakan = JadwalPakan::query()
            ->where('kolam_id', $kolamId)
            ->where('aktif', true)
            ->get(['jam'])
            ->contains(function (JadwalPakan $jadwal) use ($waktuCarbon): bool {
                $jamPakan = $waktuCarbon->copy()
                    ->startOfDay()
                    ->setTimeFromTimeString($jadwal->jam);

                return abs($waktuCarbon->diffInMinutes($jamPakan)) <= 30;
            });

        $bonusPakan = $dekatPakan ? (float) $c['bonus_naik_dekat_pakan'] : 0.0;
        $bonusLapar = (float) $c['bonus_naik_lapar'];
        $peluangPindah = (float) $c['peluang_pindah_tick'];

        $tickMenit = max(0.0001, (float) config('sandbox.tick_menit'));
        $jamLambungKosong = max(0.0001, (float) $c['jam_lambung_kosong']);
        $lajuLambung = 100 / ($jamLambungKosong * 60 / $tickMenit);

        $daftarIkan = Ikan::query()
            ->where('kolam_id', $kolamId)
            ->where('status', 'hidup')
            ->get([
                'id',
                'zona_id',
                'preferensi_zona',
                'isi_lambung_persen',
                'hari_tick_atas',
                'hari_tick_bawah',
                'hari_sum_suhu',
                'hari_sum_ph',
                'hari_sum_do',
                'hari_sum_amonia',
            ]);

        if ($daftarIkan->isEmpty()) {
            return;
        }

        $baris = [];

        foreach ($daftarIkan as $ikan) {
            $zonaId = (int) $ikan->zona_id;

            // 1) pindah zona
            if ($this->acak() < $peluangPindah) {
                $peluangAtas = (float) $ikan->preferensi_zona
                    + $bonusPakan
                    + $bonusLapar * (1 - ((float) $ikan->isi_lambung_persen / 100))
                    + $bonusDo
                    - $penaltiSuhu;

                $peluangAtas = max(0.0, min(1.0, $peluangAtas));

                $zonaId = $this->acak() < $peluangAtas
                    ? (int) $atas->id
                    : (int) $bawah->id;
            }

            // 2) mengosong bertahap
            $isiLambung = max(
                0.0,
                (float) $ikan->isi_lambung_persen - $lajuLambung,
            );

            // 3) catat paparan pada zona yang sedang ditempati
            $tickAtas = (int) $ikan->hari_tick_atas;
            $tickBawah = (int) $ikan->hari_tick_bawah;
            $sumSuhu = (float) $ikan->hari_sum_suhu;
            $sumPh = (float) $ikan->hari_sum_ph;
            $sumDo = (float) $ikan->hari_sum_do;
            $sumAmonia = (float) $ikan->hari_sum_amonia;

            $keadaanZona = $keadaan->get($zonaId);

            if ($keadaanZona instanceof KeadaanZona) {
                if ($zonaId === (int) $atas->id) {
                    $tickAtas++;
                } else {
                    $tickBawah++;
                }

                $sumSuhu += (float) $keadaanZona->suhu_asli;
                $sumPh += (float) $keadaanZona->ph_asli;
                $sumDo += (float) $keadaanZona->do_asli;
                $sumAmonia += (float) $keadaanZona->amonia_asli;
            }

            $baris[] = [
                'id' => $ikan->id,
                'zona_id' => $zonaId,
                'isi_lambung_persen' => $isiLambung,
                'hari_tick_atas' => $tickAtas,
                'hari_tick_bawah' => $tickBawah,
                'hari_sum_suhu' => $sumSuhu,
                'hari_sum_ph' => $sumPh,
                'hari_sum_do' => $sumDo,
                'hari_sum_amonia' => $sumAmonia,
            ];
        }

        foreach ($baris as $row) {
            $ikanId = (int) $row['id'];
            unset($row['id']);

            Ikan::query()
                ->whereKey($ikanId)
                ->update($row);
        }
    }

    // ------------------------------------------------------------------------------------

    /**
     * Dipanggil saat tanggal virtual berganti.
     * $tanggal = hari yang BARU SELESAI (Y-m-d).
     */
    public function prosesHarian(int $kolamId, string $tanggal): void
    {
        $c = $this->konfigurasiIkan();

        $kolam = Kolam::query()
            ->select(['id', 'koef_a'])
            ->find($kolamId);

        if (! $kolam instanceof Kolam) {
            throw new RuntimeException("Kolam {$kolamId} tidak ditemukan.");
        }

        $koefA = (float) $kolam->koef_a;

        if ($koefA <= 0) {
            throw new RuntimeException("Koefisien a kolam {$kolamId} tidak valid.");
        }

        $tanggalLog = Carbon::parse($tanggal)->startOfDay();

        $kebutuhanPakan = (float) $c['kebutuhan_pakan_harian'];
        $sgrRef = (float) $c['sgr_ref'];
        $bobotRef = max(0.0001, (float) $c['bobot_ref_g']);
        $sgrEksponen = (float) $c['sgr_eksponen'];
        $pemulihanStres = (float) $c['pemulihan_stres_per_hari'];

        $daftarIkan = Ikan::query()
            ->where('kolam_id', $kolamId)
            ->where('status', 'hidup')
            ->where(function (Builder $query): void {
                $query->where('hari_tick_atas', '>', 0)
                    ->orWhere('hari_tick_bawah', '>', 0);
            })
            ->get([
                'id',
                'bobot_g',
                'panjang_cm',
                'faktor_genetik',
                'stres',
                'hari_tick_atas',
                'hari_tick_bawah',
                'hari_pakan_g',
                'hari_sum_suhu',
                'hari_sum_ph',
                'hari_sum_do',
                'hari_sum_amonia',
            ]);

        $logRows = [];
        $hasil = [];

        foreach ($daftarIkan as $ikan) {
            $totalTick = (int) $ikan->hari_tick_atas + (int) $ikan->hari_tick_bawah;

            if ($totalTick <= 0) {
                continue;
            }

            $suhuAv = (float) $ikan->hari_sum_suhu / $totalTick;
            $phAv = (float) $ikan->hari_sum_ph / $totalTick;
            $doAv = (float) $ikan->hari_sum_do / $totalTick;
            $amoniaAv = (float) $ikan->hari_sum_amonia / $totalTick;

            $bobot = (float) $ikan->bobot_g;
            $bobotAman = max(0.0001, $bobot);
            $pakan = (float) $ikan->hari_pakan_g;

            $fRansum = $kebutuhanPakan > 0
                ? min(1.0, $pakan / ($kebutuhanPakan * $bobotAman))
                : 0.0;

            $fSuhu = self::faktorSuhu($suhuAv);
            $fDo = self::faktorDo($doAv);
            $fPh = self::faktorPh($phAv);
            $fAmonia = self::faktorAmonia($amoniaAv);

            $sgrMaks = $sgrRef * pow($bobotAman / $bobotRef, -$sgrEksponen);

            $stresNaikAir = 0.0;

            if ($doAv < 3) {
                $stresNaikAir += 15;
            } elseif ($doAv < 4) {
                $stresNaikAir += 5;
            }

            if ($amoniaAv > 1.5) {
                $stresNaikAir += 15;
            } elseif ($amoniaAv > 1) {
                $stresNaikAir += 5;
            }

            if ($suhuAv < 22 || $suhuAv > 34) {
                $stresNaikAir += 15;
            }

            if ($phAv < 5 || $phAv > 9) {
                $stresNaikAir += 15;
            }

            $sgrEff = $sgrMaks
                * $fRansum
                * $fSuhu
                * $fDo
                * $fPh
                * $fAmonia
                * (float) $ikan->faktor_genetik;

            $stresNaik = $stresNaikAir + ($fRansum < 0.3 ? 8 : 0);

            $bobotBaru = $bobot * exp($sgrEff / 100);
            $panjangBaru = max(
                (float) $ikan->panjang_cm,
                pow($bobotBaru / $koefA, 1.0 / 3.0),
            );
            $stresBaru = min(
                100.0,
                max(0.0, (float) $ikan->stres + $stresNaik - $pemulihanStres),
            );

            $logRows[] = [
                'ikan_id' => $ikan->id,
                'tanggal_sandbox' => $tanggalLog,
                'bobot_g' => $bobotBaru,
                'panjang_cm' => $panjangBaru,
                'sgr_hari_persen' => $sgrEff,
                'jam_di_atas' => (int) $ikan->hari_tick_atas / self::TICK_PER_JAM,
                'jam_di_bawah' => (int) $ikan->hari_tick_bawah / self::TICK_PER_JAM,
                'suhu_terpapar' => $suhuAv,
                'ph_terpapar' => $phAv,
                'do_terpapar' => $doAv,
                'amonia_terpapar' => $amoniaAv,
                'pakan_dimakan_g' => $pakan,
                'faktor_ransum' => $fRansum,
                'stres' => $stresBaru,
            ];

            $hasil[$ikan->id] = [
                'bobot_g' => $bobotBaru,
                'panjang_cm' => $panjangBaru,
                'stres' => $stresBaru,
                'faktor_ransum' => $fRansum,
            ];
        }

        // 1) simpan log harian dan terapkan hasil pertumbuhan ke ikan
        if ($logRows !== []) {
            foreach ($logRows as $row) {
                $ikanId = (int) $row['ikan_id'];
                $tanggalLogRow = $row['tanggal_sandbox'];
                unset($row['ikan_id'], $row['tanggal_sandbox']);

                IkanLogHarian::query()->updateOrCreate(
                    [
                        'ikan_id' => $ikanId,
                        'tanggal_sandbox' => $tanggalLogRow,
                    ],
                    $row,
                );
            }

            foreach ($hasil as $ikanId => $row) {
                Ikan::query()
                    ->whereKey($ikanId)
                    ->update([
                        'bobot_g' => $row['bobot_g'],
                        'panjang_cm' => $row['panjang_cm'],
                        'stres' => $row['stres'],
                    ]);
            }
        }

        // 2) kematian
        $bobotMaks = (float) Ikan::query()
            ->where('kolam_id', $kolamId)
            ->where('status', 'hidup')
            ->max('bobot_g');

        $ambangKecil = (float) $c['rasio_ukuran_kanibal'] * $bobotMaks;
        $ambangStres = (float) $c['ambang_stres_mati'];
        $denomStres = max(0.0001, 100 - $ambangStres);

        $matiRows = [];

        foreach ($hasil as $ikanId => $row) {
            $kecil = $row['bobot_g'] < $ambangKecil;

            $peluangMati = (float) $c['peluang_mati_dasar'];

            if ($row['stres'] > $ambangStres) {
                $peluangMati += (($row['stres'] - $ambangStres) / $denomStres)
                    * (float) $c['peluang_mati_stres_maks'];
            }

            if ($row['faktor_ransum'] < 0.3 && $kecil) {
                $peluangMati += (float) $c['peluang_kanibal'];
            }

            if ($this->acak() >= $peluangMati) {
                continue;
            }

            $penyebab = 'penyakit_acak';

            if ($row['faktor_ransum'] < 0.3 && $kecil) {
                $penyebab = 'kanibalisme';
            } elseif ($row['stres'] > $ambangStres) {
                $penyebab = 'stres_kualitas_air';
            }

            $matiRows[] = [
                'id' => $ikanId,
                'status' => 'mati',
                'tanggal_mati' => $tanggalLog,
                'penyebab_mati' => $penyebab,
            ];
        }

        if ($matiRows !== []) {
            foreach ($matiRows as $row) {
                $ikanId = (int) $row['id'];
                unset($row['id']);

                Ikan::query()
                    ->whereKey($ikanId)
                    ->update($row);
            }
        }

        // 3) bangkai menambah amonia
        $bobotMati = (float) Ikan::query()
            ->where('kolam_id', $kolamId)
            ->where('status', 'mati')
            ->whereDate('tanggal_mati', $tanggalLog)
            ->sum('bobot_g');

        $amoniaDariBangkai = (float) config('sandbox.lingkungan.amonia_dari_bangkai', 0);

        $this->lingkungan->tambahAmonia(
            $kolamId,
            $amoniaDariBangkai * $bobotMati,
        );

        // 4) reset akumulator harian
        Ikan::query()
            ->where('kolam_id', $kolamId)
            ->update([
                'hari_tick_atas' => 0,
                'hari_tick_bawah' => 0,
                'hari_sum_suhu' => 0,
                'hari_sum_ph' => 0,
                'hari_sum_do' => 0,
                'hari_sum_amonia' => 0,
                'hari_pakan_g' => 0,
            ]);
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

        $ikan = Ikan::query()
            ->where('kolam_id', $kolamId)
            ->with('zona:id,nama')
            ->orderBy('id')
            ->get();

        $hidup = $ikan->where('status', 'hidup');
        $mati = $ikan->where('status', 'mati');

        return response()->json([
            'kolam' => $kolam,
            'ringkasan' => [
                'jumlah_total' => $ikan->count(),
                'jumlah_hidup' => $hidup->count(),
                'jumlah_mati' => $mati->count(),
                'biomassa_hidup_g' => round((float) $hidup->sum('bobot_g'), 3),
                'bobot_rata_hidup_g' => round((float) ($hidup->avg('bobot_g') ?? 0), 3),
                'stres_rata_hidup' => round((float) ($hidup->avg('stres') ?? 0), 2),
            ],
            'ikan' => $ikan,
        ]);
    }

    public function riwayat(int $kolamId): JsonResponse
    {
        if (! Kolam::query()->whereKey($kolamId)->exists()) {
            return response()->json(['message' => 'Kolam tidak ditemukan.'], 404);
        }

        $logs = IkanLogHarian::query()
            ->whereHas('ikan', fn (Builder $query) => $query->where('kolam_id', $kolamId))
            ->orderBy('tanggal_sandbox')
            ->get();

        $kematian = Ikan::query()
            ->where('kolam_id', $kolamId)
            ->where('status', 'mati')
            ->get(['tanggal_mati', 'penyebab_mati']);

        $rows = $logs
            ->groupBy(fn (IkanLogHarian $log): string => $log->tanggal_sandbox->toDateString())
            ->map(function ($daily, string $tanggal) use ($kematian): array {
                $matiHariIni = $kematian->filter(
                    fn (Ikan $ikan): bool => $ikan->tanggal_mati?->toDateString() === $tanggal,
                );

                return [
                    'tanggal' => $tanggal,
                    'bobot_rata_g' => round((float) $daily->avg('bobot_g'), 2),
                    'biomassa_g' => round((float) $daily->sum('bobot_g'), 2),
                    'stres_rata' => round((float) $daily->avg('stres'), 2),
                    'jumlah_mati' => $matiHariIni->count(),
                    'penyebab_mati' => $matiHariIni
                        ->groupBy(fn (Ikan $ikan): string => $ikan->penyebab_mati ?? 'tidak diketahui')
                        ->map(fn ($rows, string $penyebab): array => [
                            'penyebab' => $penyebab,
                            'jumlah' => $rows->count(),
                        ])
                        ->values(),
                ];
            })
            ->values();

        return response()->json([
            'riwayat' => $rows,
            'kematian' => $kematian
                ->groupBy(fn (Ikan $ikan): string => $ikan->penyebab_mati ?? 'tidak diketahui')
                ->map(fn ($rows, string $penyebab): array => [
                    'penyebab' => $penyebab,
                    'jumlah' => $rows->count(),
                ])
                ->values(),
        ]);
    }

    public function gerakIkan(Request $request, int $kolamId): JsonResponse
    {
        $data = $request->validate([
            'waktu' => ['required', 'date'],
        ]);

        if (! is_array(config('sandbox.ikan'))) {
            return response()->json([
                'message' => 'Konfigurasi sandbox.ikan belum tersedia.',
            ], 503);
        }

        if (! Kolam::query()->whereKey($kolamId)->exists()) {
            return response()->json([
                'message' => 'Kolam tidak ditemukan.',
            ], 404);
        }

        $this->gerak($kolamId, Carbon::parse($data['waktu'])->format('Y-m-d H:i:s'));

        return $this->show($kolamId);
    }

    public function prosesHarianIkan(Request $request, int $kolamId): JsonResponse
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
        ]);

        if (! is_array(config('sandbox.ikan'))) {
            return response()->json([
                'message' => 'Konfigurasi sandbox.ikan belum tersedia.',
            ], 503);
        }

        if (! Kolam::query()->whereKey($kolamId)->exists()) {
            return response()->json([
                'message' => 'Kolam tidak ditemukan.',
            ], 404);
        }

        $this->prosesHarian($kolamId, $data['tanggal']);

        return $this->show($kolamId);
    }
}
