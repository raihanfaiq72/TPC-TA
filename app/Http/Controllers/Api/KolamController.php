<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ikan;
use App\Models\IkanLogHarian;
use App\Models\JenisPakan;
use App\Models\JenisPerangkat;
use App\Models\KeadaanZona;
use App\Models\Kolam;
use App\Models\PembacaanSensor;
use App\Models\ProfilLingkungan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KolamController extends Controller
{
    private function gauss(): float
    {
        $random_1 = (mt_rand() + 1) / (mt_getrandmax() + 1);
        $random_2 = mt_rand() / mt_getrandmax();

        return sqrt(-2 * log($random_1)) * cos(2 * M_PI * $random_2);
    }

    public function index(): JsonResponse
    {
        $kolam = Kolam::query()->withCount(['ikans as jumlah_ikan_hidup' => fn (Builder $query) => $query->where('status', 'hidup'), 'zonas as jumlah_zona'])->orderByDesc('id')->get();

        return response()->json($kolam);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kode' => 'required|string|max:20|unique:kolams,kode',
            'nama' => 'required|string|max:100',
            'panjang_m' => 'required|numeric|gt:0',
            'lebar_m' => 'required|numeric|gt:0',
            'tinggi_air_m' => 'required|numeric|gt:0',
            'jumlah_ikan_awal' => 'required|integer|min:1|max:20000',
            'bobot_awal_rata_g' => 'required|numeric|gt:0',
            'bobot_awal_sd_g' => 'required|numeric|min:0',
            'panjang_awal_rata_cm' => 'required|numeric|gt:0',
            'kecepatan_waktu' => 'nullable|numeric|gt:0',
            'waktu_mulai' => 'nullable|date',
            'dosis_harian_persen' => 'nullable|numeric|gt:0',
            'bobot_target_panen_g' => 'nullable|numeric|gt:0',
        ]);

        $mulai = isset($data['waktu_mulai']) ? Carbon::parse($data['waktu_mulai']) : Carbon::today()->setTime(6, 0);

        $koefisien_a = $data['bobot_awal_rata_g'] / ($data['panjang_awal_rata_cm'] ** 3);

        $kolam = DB::transaction(function () use ($data, $mulai, $koefisien_a): Kolam {
            // 1. kolam e
            $kolam = Kolam::create([
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'jenis' => 'terpal',
                'panjang_m' => $data['panjang_m'],
                'lebar_m' => $data['lebar_m'],
                'tinggi_air_m' => $data['tinggi_air_m'],
                'jumlah_ikan_awal' => $data['jumlah_ikan_awal'],
                'bobot_awal_rata_g' => $data['bobot_awal_rata_g'],
                'bobot_awal_sd_g' => $data['bobot_awal_sd_g'],
                'panjang_awal_rata_cm' => $data['panjang_awal_rata_cm'],
                'koef_a' => round($koefisien_a, 8),
                'dosis_harian_persen' => $data['dosis_harian_persen'] ?? 5.0,
                'bobot_target_panen_g' => $data['bobot_target_panen_g'] ?? 100.0,
                'waktu_mulai_sandbox' => $mulai,
                'waktu_sandbox_sekarang' => $mulai,
                'kecepatan_waktu' => $data['kecepatan_waktu'] ?? 60,
            ]);

            // Zona, atas 10 cm bawah 40 cm

            $zona = $kolam->zonas()->createMany([
                [
                    'nama' => 'atas',
                    'kedalaman_cm' => 10,
                    'porsi_volume' => 0.40,
                ],
                [
                    'nama' => 'bawah',
                    'kedalaman_cm' => 40,
                    'porsi_volume' => 0.60,
                ],
            ])->keyBy('nama');

            // 3. perangkat: jenis zona dipasang 2x , jenis kolam 1x

            foreach (JenisPerangkat::query()->orderBy('id')->get() as $jenis) {
                if ($jenis->tingkat === 'zona') {
                    foreach ($zona as $nama => $zon) {
                        $kolam->perangkats()->create([
                            'kode' => strtoupper("{$data['kode']}-{$nama}-{$jenis->kode}"),
                            'zona_id' => $zon->id,
                            'jenis_perangkat_id' => $jenis->id,
                            'interval_menit' => $jenis->interval_default_menit,
                            'tanggal_kalibrasi' => $mulai->toDateString(),
                        ]);
                    }

                    continue;
                }

                $kolam->perangkats()->create([
                    'kode' => strtoupper("{$data['kode']}-{$jenis->kode}"),
                    'zona_id' => null,
                    'jenis_perangkat_id' => $jenis->id,
                    'interval_menit' => $jenis->interval_default_menit,
                    'tanggal_kalibrasi' => $mulai->toDateString(),
                ]);
            }

            // 4. profil lingkungan dan keadaan awal per zona; parameter : dasar, amplitudp, offset atas dan offset bawah

            $profil = [
                'suhu' => [29.0, 2.0, 0.0, -1.0],
                'ph' => [7.2,  0.0, 0.0, -0.1],
                'do' => [6.5,  0.0, 0.0, -0.3],
                'amonia' => [0.05, 0.0, 0.0,  0.02],
            ];

            /** @var array<string, array<string, float>> $awal */
            $awal = [];

            foreach ($profil as $parameter => [$dasar,$amplitudo, $offsetAtas, $offsetBawah]) {
                foreach (['atas' => $offsetAtas, 'bawah' => $offsetBawah] as $nama => $offset) {
                    $kolam->profilLingkungans()->create([
                        'zona_id' => $zona[$nama]->id,
                        'parameter' => $parameter,
                        'nilai_dasar' => $dasar,
                        'amplitudo_harian' => $amplitudo,
                        'offset_zona' => $offset,
                    ]);

                    $awal[$nama][$parameter] = $dasar + $offset;
                }
            }

            foreach ($zona as $nama => $zon) {
                KeadaanZona::create([
                    'zona_id' => $zon->id,
                    'kolam_id' => $kolam->id,
                    'suhu_asli' => $awal[$nama]['suhu'],
                    'ph_asli' => $awal[$nama]['ph'],
                    'do_asli' => $awal[$nama]['do'],
                    'amonia_asli' => $awal[$nama]['amonia'],
                    'sisa_pakan_g' => 0,
                    'diperbarui_pada' => $mulai,
                ]);
            }

            // 5. pakan 3 kali sehari

            $pakan = JenisPakan::query()->orderBy('id')->firstOrFail();

            $kolam->jadwalPakans()->createMany([
                [
                    'jenis_pakan_id' => $pakan->id,
                    'jam' => '07:00:00',
                    'porsi_dosis_persen' => 33.33,
                    'aktif' => true,
                ],
                [
                    'jenis_pakan_id' => $pakan->id,
                    'jam' => '12:00:00',
                    'porsi_dosis_persen' => 33.33,
                    'aktif' => true,
                ],
                [
                    'jenis_pakan_id' => $pakan->id,
                    'jam' => '19:00:00',
                    'porsi_dosis_persen' => 33.34,
                    'aktif' => true,
                ],
            ]);

            // 6. Populasi ikan - bulk insert per 500 baris

            $total = (int) $data['jumlah_ikan_awal'];
            $min_bobot = 0.3 * $data['bobot_awal_rata_g'];
            $baris = [];

            for ($i = 1; $i <= $total; $i++) {
                $bobot = max($min_bobot, $data['bobot_awal_rata_g'] + $this->gauss() * $data['bobot_awal_sd_g']);

                $panjang = pow($bobot / $koefisien_a, 1 / 3);
                $genetik = max(0.7, min(1.3, 1 + 0.1 * $this->gauss()));
                $preferensi = max(0.0, min(1.0, 0.6 + 0.2 * $this->gauss()));
                $zon = mt_rand(0, 1) === 1 ? $zona['atas'] : $zona['bawah'];

                $baris[] = [
                    'kolam_id' => $kolam->id,
                    'kode' => sprintf('%s-F%04d', $data['kode'], $i),
                    'faktor_genetik' => round($genetik, 3),
                    'preferensi_zona' => round($preferensi, 3),
                    'bobot_awal_g' => round($bobot, 3),
                    'panjang_awal_cm' => round($panjang, 3),
                    'bobot_g' => round($bobot, 3),
                    'panjang_cm' => round($panjang, 3),
                    'zona_id' => $zon->id,
                ];

                if (count($baris) === 500 || $i === $total) {
                    Ikan::insert($baris);
                    $baris = [];
                }
            }

            // 7. Nilai turunan

            $kolam->refresh(); // krn vol air m3 adl generated column

            $kolam->update([
                'biomassa_awal_g' => $kolam->ikans()->sum('bobot_awal_g'),
                'padat_tebar_per_m3' => round($kolam->jumlah_ikan_awal / $kolam->volume_air_m3, 2),
            ]);

            return $kolam;
        });

        return response()->json($kolam->only([
            'id',
            'kode',
            'jumlah_ikan_awal',
            'volume_air_m3',
            'biomassa_awal_g',
            'padat_tebar_per_m3',
        ]), 201);
    }

    public function show(int $id): JsonResponse
    {
        $kolam = Kolam::query()
            ->withCount(['ikans as jumlah_ikan_hidup' => fn (Builder $query) => $query->where('status', 'hidup')])
            ->find($id);

        if (! $kolam) {
            return response()->json(['message' => 'Kolam tidak ditemukan'], 404);
        }

        $zona = $kolam->zonas()->orderBy('id')->get();

        $perangkat = $kolam->perangkats()->join('jenis_perangkats', 'jenis_perangkats.id', '=', 'perangkats.jenis_perangkat_id')->select(['perangkats.*', 'jenis_perangkats.nama as jenis_perangkat', 'jenis_perangkats.kode as kode_jenis'])->orderBy('perangkats.id')->get();

        $jadwalPakan = $kolam->jadwalPakans()->leftJoin('jenis_pakans', 'jenis_pakans.id', '=', 'jadwal_pakans.jenis_pakan_id')->select(['jadwal_pakans.*', 'jenis_pakans.merek as nama_pakan'])->orderBy('jadwal_pakans.jam')->get();

        return response()->json([
            'kolam' => $kolam,
            'zona' => $zona,
            'perangkat' => $perangkat,
            'jadwal_pakan' => $jadwalPakan,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $kolam = Kolam::query()->find($id);

        if (! $kolam) {
            return response()->json(['message' => 'Kolam tidak ditemukan'], 404);
        }

        $data = $request->validate([
            'nama' => ['sometimes', 'required', 'string', 'max:100'],
            'panjang_m' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'lebar_m' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'tinggi_air_m' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'kecepatan_waktu' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'bobot_target_panen_g' => ['sometimes', 'required', 'numeric', 'gt:0'],
        ]);

        $kolam->update($data);
        $kolam->refresh();

        if ($kolam->volume_air_m3 > 0) {
            $kolam->update([
                'padat_tebar_per_m3' => round($kolam->jumlah_ikan_awal / $kolam->volume_air_m3, 2),
            ]);
        }

        return response()->json([
            'message' => 'Data kolam berhasil diperbarui.',
            'kolam' => $kolam->fresh(),
        ]);
    }

    public function reset(int $id): JsonResponse
    {
        $kolam = Kolam::query()->find($id);

        if (! $kolam) {
            return response()->json(['message' => 'Kolam tidak ditemukan'], 404);
        }

        DB::transaction(function () use ($kolam): void {
            $ikanIds = Ikan::query()->where('kolam_id', $kolam->id)->pluck('id');
            IkanLogHarian::query()->whereIn('ikan_id', $ikanIds)->delete();
            Ikan::query()->where('kolam_id', $kolam->id)->delete();
            PembacaanSensor::query()->where('kolam_id', $kolam->id)->delete();

            foreach ($kolam->zonas()->get() as $zona) {
                $profil = ProfilLingkungan::query()
                    ->where('kolam_id', $kolam->id)
                    ->where('zona_id', $zona->id)
                    ->get()
                    ->keyBy('parameter');

                KeadaanZona::query()->updateOrCreate(
                    ['zona_id' => $zona->id],
                    [
                        'kolam_id' => $kolam->id,
                        'suhu_asli' => (float) ($profil['suhu']->nilai_dasar ?? 0) + (float) ($profil['suhu']->offset_zona ?? 0),
                        'ph_asli' => (float) ($profil['ph']->nilai_dasar ?? 7) + (float) ($profil['ph']->offset_zona ?? 0),
                        'do_asli' => (float) ($profil['do']->nilai_dasar ?? 0) + (float) ($profil['do']->offset_zona ?? 0),
                        'amonia_asli' => (float) ($profil['amonia']->nilai_dasar ?? 0) + (float) ($profil['amonia']->offset_zona ?? 0),
                        'sisa_pakan_g' => 0,
                        'diperbarui_pada' => $kolam->waktu_mulai_sandbox,
                    ],
                );
            }

            $jenis = JenisPerangkat::query()->get()->keyBy('kode');
            $zona = $kolam->zonas()->get()->keyBy('nama');
            $total = (int) $kolam->jumlah_ikan_awal;
            $minBobot = 0.3 * (float) $kolam->bobot_awal_rata_g;
            $baris = [];

            for ($i = 1; $i <= $total; $i++) {
                $bobot = max($minBobot, (float) $kolam->bobot_awal_rata_g + $this->gauss() * (float) $kolam->bobot_awal_sd_g);
                $panjang = pow($bobot / max(0.000001, (float) $kolam->koef_a), 1 / 3);
                $zonaIkan = mt_rand(0, 1) === 1 ? $zona['atas'] : $zona['bawah'];

                $baris[] = [
                    'kolam_id' => $kolam->id,
                    'kode' => sprintf('%s-F%04d', $kolam->kode, $i),
                    'faktor_genetik' => round(max(0.7, min(1.3, 1 + 0.1 * $this->gauss())), 3),
                    'preferensi_zona' => round(max(0.0, min(1.0, 0.6 + 0.2 * $this->gauss())), 3),
                    'bobot_awal_g' => round($bobot, 3),
                    'panjang_awal_cm' => round($panjang, 3),
                    'bobot_g' => round($bobot, 3),
                    'panjang_cm' => round($panjang, 3),
                    'zona_id' => $zonaIkan->id,
                ];

                if (count($baris) === 500 || $i === $total) {
                    Ikan::query()->insert($baris);
                    $baris = [];
                }
            }

            $kolam->update(['waktu_sandbox_sekarang' => $kolam->waktu_mulai_sandbox, 'status' => 'aktif']);
            $kolam->perangkats()->update([
                'terakhir_baca' => null,
                'baterai_persen' => 100,
                'status' => 'aktif',
            ]);
        });

        return response()->json([
            'message' => 'Kolam berhasil diperbarui dari awal.',
            'kolam' => $kolam->fresh(),
        ]);
    }

    public function uploadFoto(Request $request, int $id): JsonResponse
    {
        $kolam = Kolam::query()->find($id);

        if (! $kolam) {
            return response()->json(['message' => 'Kolam tidak ditemukan'], 404);
        }

        $data = $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $data['foto']->store('kolam', 'public');

        if ($kolam->foto_path !== null) {
            Storage::disk('public')->delete($kolam->foto_path);
        }

        $kolam->update(['foto_path' => $path]);

        return response()->json([
            'message' => 'Foto kolam berhasil disimpan.',
            'foto_url' => $kolam->fresh()->foto_url,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $kolam = Kolam::query()->find($id);

        if (! $kolam) {
            return response()->json(['message' => 'Kolam tidak ditemukan'], 404);
        }

        try {
            DB::transaction(fn () => $kolam->delete());
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Kolam belum dapat dihapus. Periksa foreign key dan relasi data turunannya.',
            ], 409);
        }

        if ($kolam->foto_path !== null) {
            Storage::disk('public')->delete($kolam->foto_path);
        }

        return response()->json(['message' => 'Kolam berhasil dihapus']);
    }
}
