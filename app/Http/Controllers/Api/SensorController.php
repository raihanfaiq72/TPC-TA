<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisPerangkat;
use App\Models\KeadaanZona;
use App\Models\Kolam;
use App\Models\PembacaanSensor;
use App\Models\Perangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SensorController extends Controller
{
    private function gauss(): float
    {
        $u1 = (mt_rand() + 1) / (mt_getrandmax() + 1);
        $u2 = mt_rand() / mt_getrandmax();

        return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }

    private function acak(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    public function bacaSemua(int $kolamId, string $waktu): int
    {
        $waktuCarbon = Carbon::parse($waktu);

        $keadaan = KeadaanZona::query()
            ->where('kolam_id', $kolamId)
            ->get()
            ->keyBy('zona_id');

        $perangkat = Perangkat::query()
            ->with([
                'jenisPerangkat',
                'kolam:id,tinggi_air_m',
                'zona:id,nama',
            ])
            ->where('kolam_id', $kolamId)
            ->where('status', 'aktif')
            ->whereHas('jenisPerangkat', fn ($query) => $query->where('kode', '!=', 'feeder'))
            ->get()
            ->filter(function (Perangkat $item) use ($waktuCarbon): bool {
                if ($item->terakhir_baca === null) {
                    return true;
                }

                $selisihMenit = intdiv(
                    (int) $waktuCarbon->timestamp - (int) $item->terakhir_baca->timestamp,
                    60,
                );

                return $selisihMenit >= $item->interval_menit;
            })
            ->values();

        if ($perangkat->isEmpty()) {
            return 0;
        }

        $turunBaterai = (float) config('sandbox.sensor.baterai_turun_per_baca', 0);

        foreach ($perangkat as $alat) {
            $jenis = $alat->jenisPerangkat;

            if (! $jenis instanceof JenisPerangkat) {
                continue;
            }

            $zona = $alat->zona_id !== null
                ? $keadaan->get($alat->zona_id)
                : null;

            $asli = match ($jenis->kode) {
                'suhu' => $zona instanceof KeadaanZona ? (float) $zona->suhu_asli : 0.0,
                'ph' => $zona instanceof KeadaanZona ? (float) $zona->ph_asli : 0.0,
                'do' => $zona instanceof KeadaanZona ? (float) $zona->do_asli : 0.0,
                'amonia' => $zona instanceof KeadaanZona ? (float) $zona->amonia_asli : 0.0,
                'level' => $alat->kolam instanceof Kolam ? (float) $alat->kolam->tinggi_air_m * 100 : 0.0,
                default => 0.0,
            };

            $nilai = $asli + $this->gauss() * (float) $jenis->noise_sd;
            $kualitas = 'normal';

            if ($this->acak() < (float) $jenis->peluang_error) {
                if ($this->acak() < 0.7) {
                    $arah = mt_rand(0, 1) ? 1 : -1;
                    $nilai = $asli * (1 + $arah * (0.3 + 0.3 * $this->acak()));
                    $kualitas = 'outlier';
                } else {
                    $nilai = null;
                    $kualitas = 'gagal';
                }
            }

            $minimum = (float) ($jenis->nilai_min_fisik ?? 0);
            $maksimum = (float) ($jenis->nilai_maks_fisik ?? 0);

            if ($nilai !== null && ($nilai < $minimum || $nilai > $maksimum)) {
                $kualitas = 'outlier';
                $nilai = max($minimum, min($maksimum, $nilai));
            }

            $baterai = (int) round((float) $alat->baterai_persen);

            PembacaanSensor::query()->create([
                'perangkat_id' => $alat->id,
                'kolam_id' => $kolamId,
                'zona_id' => $alat->zona_id,
                'parameter' => $jenis->parameter,
                'nilai' => $nilai === null ? null : round($nilai, 3),
                'satuan' => $jenis->satuan,
                'waktu_ukur' => $waktuCarbon,
                'waktu_diterima' => $waktuCarbon,
                'kualitas_data' => $kualitas,
                'baterai_persen' => $baterai,
                'sinyal_rssi' => -55 - mt_rand(0, 20),
            ]);

            $alat->update([
                'terakhir_baca' => $waktuCarbon,
                'baterai_persen' => max(0.0, (float) $alat->baterai_persen - $turunBaterai),
            ]);
        }

        return $perangkat->count();
    }

    public function bacaSemuaSensor(Request $request, int $kolamId): JsonResponse
    {
        $data = $request->validate([
            'waktu' => ['required', 'date'],
        ]);

        if (! Kolam::query()->whereKey($kolamId)->exists()) {
            return response()->json([
                'message' => 'Kolam tidak ditemukan.',
            ], 404);
        }

        $waktu = Carbon::parse((string) $data['waktu'])->format('Y-m-d H:i:s');
        $jumlah = $this->bacaSemua($kolamId, $waktu);

        return response()->json([
            'kolam_id' => $kolamId,
            'waktu' => $waktu,
            'jumlah_dibaca' => $jumlah,
        ]);
    }

    public function updateStatus(Request $request, int $kolamId, int $perangkatId): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:aktif,mati'],
        ]);

        $perangkat = Perangkat::query()
            ->where('kolam_id', $kolamId)
            ->whereKey($perangkatId)
            ->first();

        if (! $perangkat instanceof Perangkat) {
            return response()->json([
                'message' => 'Perangkat tidak ditemukan.',
            ], 404);
        }

        $perangkat->update(['status' => $data['status']]);

        return response()->json([
            'message' => $data['status'] === 'aktif'
                ? 'Sensor berhasil diaktifkan.'
                : 'Sensor berhasil dimatikan.',
            'perangkat' => $perangkat->fresh(),
        ]);
    }

    public function terbaru(int $id): JsonResponse
    {
        $rows = Perangkat::query()
            ->with(['pembacaanTerbaru', 'zona:id,nama'])
            ->where('kolam_id', $id)
            ->orderBy('kode')
            ->get()
            ->map(function (Perangkat $alat): ?array {
                $baca = $alat->pembacaanTerbaru;

                if (! $baca instanceof PembacaanSensor) {
                    return null;
                }

                return [
                    'perangkat' => $alat->kode,
                    'zona' => $alat->zona?->nama,
                    'parameter' => $baca->parameter,
                    'nilai' => $baca->nilai === null ? null : (float) $baca->nilai,
                    'satuan' => $baca->satuan,
                    'waktu_ukur' => $baca->waktu_ukur->toDateTimeString(),
                    'kualitas_data' => $baca->kualitas_data,
                ];
            })
            ->filter()
            ->values();

        return response()->json($rows);
    }

    public function grafik(Request $request, int $id): JsonResponse
    {
        $kolam = Kolam::query()->find($id);

        if (! $kolam instanceof Kolam) {
            return response()->json([
                'message' => 'Kolam tidak ditemukan.',
            ], 404);
        }

        $range = (string) $request->query('range', '1d');
        $ranges = [
            '1m' => ['seconds' => 60, 'bucket' => 10],
            '1h' => ['seconds' => 3600, 'bucket' => 300],
            '1d' => ['seconds' => 86400, 'bucket' => 3600],
            '1w' => ['seconds' => 604800, 'bucket' => 86400],
            '1mo' => ['seconds' => 2592000, 'bucket' => 86400],
            '1y' => ['seconds' => 31536000, 'bucket' => 2592000],
        ];

        $config = $ranges[$range] ?? $ranges['1d'];
        $bucketSeconds = $config['bucket'];
        $end = $this->waktuVirtual($kolam);
        $start = $end->copy()->subSeconds($config['seconds']);

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $bucketEpochExpr = "CAST(CAST(strftime('%s', pembacaan_sensors.waktu_ukur) AS INTEGER) / {$bucketSeconds} AS INTEGER) * {$bucketSeconds}";
        } else {
            // UNIX_TIMESTAMP() memakai zona waktu sesi database, sedangkan
            // kolom waktu disimpan sebagai UTC. Koreksi offset agar bucket
            // tetap UTC dan label waktu tidak bergeser di browser.
            $offset = (int) DB::selectOne(
                'SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS tz_offset'
            )->tz_offset;
            $bucketEpochExpr = "(FLOOR((UNIX_TIMESTAMP(pembacaan_sensors.waktu_ukur) + {$offset}) / {$bucketSeconds}) * {$bucketSeconds})";
        }

        $rows = PembacaanSensor::query()
            ->leftJoin('zona_kolams', 'zona_kolams.id', '=', 'pembacaan_sensors.zona_id')
            ->where('pembacaan_sensors.kolam_id', $id)
            ->whereNotNull('pembacaan_sensors.nilai')
            ->whereIn('pembacaan_sensors.kualitas_data', ['normal', 'outlier'])
            ->where('pembacaan_sensors.waktu_ukur', '>=', $start)
            ->where('pembacaan_sensors.waktu_ukur', '<=', $end)
            ->selectRaw("{$bucketEpochExpr} as bucket_epoch, pembacaan_sensors.parameter, zona_kolams.nama as zona, AVG(pembacaan_sensors.nilai) as nilai")
            ->groupByRaw("{$bucketEpochExpr}, pembacaan_sensors.parameter, zona_kolams.nama")
            ->orderBy('bucket_epoch')
            ->get()
            ->map(fn ($row): array => [
                'waktu' => Carbon::createFromTimestampUTC((int) $row->bucket_epoch)->format('Y-m-d H:i:s'),
                'zona' => $row->zona,
                'parameter' => (string) $row->parameter,
                'nilai' => (float) $row->nilai,
            ])
            ->values();

        return response()->json($rows);
    }

    private function waktuVirtual(Kolam $kolam): Carbon
    {
        $virtual = Carbon::parse($kolam->waktu_sandbox_sekarang);

        if ($kolam->waktu_sandbox_sinkron === null) {
            return $virtual;
        }

        $elapsed = max(
            0,
            Carbon::now()->getTimestamp()
                - Carbon::parse($kolam->waktu_sandbox_sinkron)->getTimestamp(),
        );

        return $virtual->copy()->addSeconds($elapsed);
    }

    public function riwayat(Request $request, int $id): JsonResponse
    {
        $parameter = (string) $request->query('parameter', 'suhu');
        $zona = $request->query('zona');
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');

        $query = PembacaanSensor::query()
            ->with('zona:id,nama')
            ->where('kolam_id', $id)
            ->where('parameter', $parameter);

        if ($zona !== null) {
            $namaZona = (string) $zona;
            $query->whereHas('zona', fn ($subQuery) => $subQuery->where('nama', $namaZona));
        }

        if (is_string($dari) && $dari !== '') {
            $query->where('waktu_ukur', '>=', Carbon::parse($dari));
        }

        if (is_string($sampai) && $sampai !== '') {
            $query->where('waktu_ukur', '<', Carbon::parse($sampai));
        }

        $rows = $query
            ->orderBy('waktu_ukur')
            ->limit(5000)
            ->get()
            ->map(fn (PembacaanSensor $baca): array => [
                'waktu_ukur' => $baca->waktu_ukur->toDateTimeString(),
                'zona' => $baca->zona?->nama,
                'parameter' => $baca->parameter,
                'nilai' => $baca->nilai === null ? null : (float) $baca->nilai,
                'kualitas_data' => $baca->kualitas_data,
            ])
            ->values();

        return response()->json($rows);
    }
}
