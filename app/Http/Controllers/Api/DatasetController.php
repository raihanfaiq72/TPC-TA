<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ikan;
use App\Models\Kolam;
use App\Models\SamplingPertumbuhan;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatasetController extends Controller
{
    /** @var list<string> */
    private const RINGKAS_COLUMNS = [
        'tanggal',
        'kolam_kode',
        'kolam_nama',
        'target_bobot_g',
        'tanggal_tebar',
        'suhu_atas',
        'suhu_bawah',
        'do_atas',
        'do_bawah',
        'ph_atas',
        'ph_bawah',
        'amonia_atas',
        'amonia_bawah',
        'level_air',
        'jumlah_baca_valid',
        'jumlah_baca_gagal',
        'bobot_rata_g',
        'biomassa_g',
        'stres_rata',
        'pakan_total_g',
        'jumlah_mati',
        'sampling_jumlah',
        'sampling_bobot_rata_g',
        'sampling_panjang_rata_cm',
    ];

    /** @var list<string> */
    private const SENSOR_COLUMNS = [
        'waktu_ukur',
        'perangkat',
        'zona',
        'parameter',
        'nilai',
        'satuan',
        'kualitas_data',
        'baterai_persen',
        'sinyal_rssi',
    ];

    public function ringkas(Request $request, int $kolamId): JsonResponse|StreamedResponse
    {
        $kolam = Kolam::query()->find($kolamId);

        if (! $kolam instanceof Kolam) {
            return response()->json(['message' => 'Kolam tidak ditemukan.'], 404);
        }

        $format = $this->format($request);

        if ($format === null) {
            return response()->json([
                'message' => 'Format export tidak valid. Gunakan csv atau json.',
            ], 422);
        }

        $rows = $this->ringkasRows($kolam);

        if ($format === 'json') {
            return response()->json($rows);
        }

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, self::RINGKAS_COLUMNS);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    fn (string $column): mixed => $row[$column] ?? null,
                    self::RINGKAS_COLUMNS,
                ));
            }

            fclose($handle);
        }, $this->filename($kolam, 'ringkas', 'csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function lengkap(Request $request, int $kolamId): JsonResponse|StreamedResponse
    {
        $kolam = Kolam::query()->find($kolamId);

        if (! $kolam instanceof Kolam) {
            return response()->json(['message' => 'Kolam tidak ditemukan.'], 404);
        }

        $format = $this->format($request);

        if ($format === null) {
            return response()->json([
                'message' => 'Format export tidak valid. Gunakan csv atau json.',
            ], 422);
        }

        if ($format === 'csv') {
            return $this->streamSensorCsv($kolam);
        }

        return $this->streamCompleteJson($kolam);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ringkasRows(Kolam $kolam): array
    {
        $sensorRows = DB::table('pembacaan_sensors as ps')
            ->leftJoin('zona_kolams as z', 'z.id', '=', 'ps.zona_id')
            ->where('ps.kolam_id', $kolam->id)
            ->whereIn('ps.kualitas_data', ['normal', 'outlier'])
            ->whereNotNull('ps.nilai')
            ->selectRaw('DATE(ps.waktu_ukur) as tanggal, ps.parameter, z.nama as zona, AVG(ps.nilai) as nilai, COUNT(*) as jumlah')
            ->groupByRaw('DATE(ps.waktu_ukur), ps.parameter, z.nama')
            ->get();

        $failedRows = DB::table('pembacaan_sensors as ps')
            ->where('ps.kolam_id', $kolam->id)
            ->where('ps.kualitas_data', 'gagal')
            ->selectRaw('DATE(ps.waktu_ukur) as tanggal, COUNT(*) as jumlah')
            ->groupByRaw('DATE(ps.waktu_ukur)')
            ->pluck('jumlah', 'tanggal')
            ->all();

        $growthRows = DB::table('ikan_log_harians as il')
            ->join('ikans as i', 'i.id', '=', 'il.ikan_id')
            ->where('i.kolam_id', $kolam->id)
            ->selectRaw('il.tanggal_sandbox as tanggal, AVG(il.bobot_g) as bobot_rata_g, SUM(il.bobot_g) as biomassa_g, AVG(il.stres) as stres_rata, SUM(il.pakan_dimakan_g) as pakan_total_g')
            ->groupBy('il.tanggal_sandbox')
            ->get()
            ->keyBy('tanggal');

        $deathRows = Ikan::query()
            ->where('kolam_id', $kolam->id)
            ->where('status', 'mati')
            ->whereNotNull('tanggal_mati')
            ->selectRaw('DATE(tanggal_mati) as tanggal, COUNT(*) as jumlah')
            ->groupByRaw('DATE(tanggal_mati)')
            ->pluck('jumlah', 'tanggal')
            ->all();

        $samplingRows = SamplingPertumbuhan::query()
            ->where('kolam_id', $kolam->id)
            ->get()
            ->keyBy(fn (SamplingPertumbuhan $row): string => $row->tanggal->toDateString());

        $sensorByDate = [];
        $validByDate = [];

        foreach ($sensorRows as $sensor) {
            $tanggal = (string) $sensor->tanggal;
            $sensorByDate[$tanggal][] = $sensor;
            $validByDate[$tanggal] = ($validByDate[$tanggal] ?? 0) + (int) $sensor->jumlah;
        }

        $dates = array_values(array_unique([
            ...array_keys($sensorByDate),
            ...array_keys($failedRows),
            ...$growthRows->keys()->all(),
            ...array_keys($deathRows),
            ...$samplingRows->keys()->all(),
        ]));
        sort($dates);

        $target = (float) ($kolam->bobot_target_panen_g ?? 0);
        $tanggalTebar = $kolam->waktu_mulai_sandbox?->toDateString();
        $rows = [];

        foreach ($dates as $date) {
            $row = array_fill_keys(self::RINGKAS_COLUMNS, null);
            $row['tanggal'] = $date;
            $row['kolam_kode'] = $kolam->kode;
            $row['kolam_nama'] = $kolam->nama;
            $row['target_bobot_g'] = $target;
            $row['tanggal_tebar'] = $tanggalTebar;
            $row['jumlah_baca_valid'] = $validByDate[$date] ?? 0;
            $row['jumlah_baca_gagal'] = (int) ($failedRows[$date] ?? 0);

            foreach ($sensorByDate[$date] ?? [] as $sensor) {
                $column = $this->sensorColumn((string) $sensor->parameter, $sensor->zona);

                if ($column !== null) {
                    $row[$column] = round((float) $sensor->nilai, 3);
                }
            }

            $growth = $growthRows->get($date);

            if ($growth !== null) {
                $row['bobot_rata_g'] = round((float) $growth->bobot_rata_g, 3);
                $row['biomassa_g'] = round((float) $growth->biomassa_g, 3);
                $row['stres_rata'] = round((float) $growth->stres_rata, 3);
                $row['pakan_total_g'] = round((float) $growth->pakan_total_g, 3);
            }

            $row['jumlah_mati'] = (int) ($deathRows[$date] ?? 0);

            $sampling = $samplingRows->get($date);

            if ($sampling instanceof SamplingPertumbuhan) {
                $row['sampling_jumlah'] = (int) $sampling->jumlah_sampel;
                $row['sampling_bobot_rata_g'] = round((float) $sampling->bobot_rata_g, 3);
                $row['sampling_panjang_rata_cm'] = round((float) $sampling->panjang_rata_cm, 3);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function sensorColumn(string $parameter, ?string $zona): ?string
    {
        if ($parameter === 'level') {
            return 'level_air';
        }

        $prefix = match ($parameter) {
            'suhu' => 'suhu',
            'do' => 'do',
            'ph' => 'ph',
            'amonia' => 'amonia',
            default => null,
        };

        if ($prefix === null) {
            return null;
        }

        $zona = strtolower((string) $zona);

        return in_array($zona, ['atas', 'bawah'], true)
            ? "{$prefix}_{$zona}"
            : null;
    }

    private function streamSensorCsv(Kolam $kolam): StreamedResponse
    {
        $query = $this->sensorQuery($kolam);

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, self::SENSOR_COLUMNS);

            foreach ($query->cursor() as $row) {
                fputcsv($handle, array_values($this->normalSensorRow($row)));
            }

            fclose($handle);
        }, $this->filename($kolam, 'lengkap-sensor', 'csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function streamCompleteJson(Kolam $kolam): StreamedResponse
    {
        $sensorQuery = $this->sensorQuery($kolam);
        $populationQuery = $this->populationQuery($kolam);
        $sampling = SamplingPertumbuhan::query()
            ->where('kolam_id', $kolam->id)
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get()
            ->map(fn (SamplingPertumbuhan $row): array => [
                'tanggal' => $row->tanggal->toDateString(),
                'jumlah_sampel' => (int) $row->jumlah_sampel,
                'jumlah_mati' => (int) $row->jumlah_mati,
                'bobot_total_sampel_g' => (float) $row->bobot_total_sampel_g,
                'bobot_rata_g' => (float) $row->bobot_rata_g,
                'panjang_rata_cm' => (float) $row->panjang_rata_cm,
            ])
            ->values()
            ->all();

        return response()->streamDownload(function () use ($kolam, $sensorQuery, $populationQuery, $sampling): void {
            $meta = [
                'versi' => 'lengkap',
                'dibuat_pada' => Carbon::now()->toIso8601String(),
                'kolam' => [
                    'id' => $kolam->id,
                    'kode' => $kolam->kode,
                    'nama' => $kolam->nama,
                    'target_bobot_g' => (float) ($kolam->bobot_target_panen_g ?? 0),
                    'tanggal_tebar' => $kolam->waktu_mulai_sandbox?->toDateString(),
                ],
                'timezone' => 'UTC',
            ];

            echo '{"meta":'.json_encode($meta, JSON_THROW_ON_ERROR).',"sensor":[';

            $first = true;

            foreach ($sensorQuery->cursor() as $row) {
                if (! $first) {
                    echo ',';
                }

                echo json_encode($this->normalSensorRow($row), JSON_THROW_ON_ERROR);
                $first = false;
            }

            echo '],"populasi":[';

            $first = true;

            foreach ($populationQuery->cursor() as $row) {
                if (! $first) {
                    echo ',';
                }

                echo json_encode($this->normalPopulationRow($row), JSON_THROW_ON_ERROR);
                $first = false;
            }

            echo '],"sampling":'.json_encode($sampling, JSON_THROW_ON_ERROR).'}';
        }, $this->filename($kolam, 'lengkap', 'json'), [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    private function sensorQuery(Kolam $kolam): Builder
    {
        return DB::table('pembacaan_sensors as ps')
            ->join('perangkats as p', 'p.id', '=', 'ps.perangkat_id')
            ->leftJoin('zona_kolams as z', 'z.id', '=', 'ps.zona_id')
            ->where('ps.kolam_id', $kolam->id)
            ->selectRaw('ps.waktu_ukur, p.kode as perangkat, z.nama as zona, ps.parameter, ps.nilai, ps.satuan, ps.kualitas_data, ps.baterai_persen, ps.sinyal_rssi')
            ->orderBy('ps.waktu_ukur')
            ->orderBy('ps.id');
    }

    private function populationQuery(Kolam $kolam): Builder
    {
        return DB::table('ikan_log_harians as il')
            ->join('ikans as i', 'i.id', '=', 'il.ikan_id')
            ->where('i.kolam_id', $kolam->id)
            ->selectRaw('il.tanggal_sandbox as tanggal, i.kode as ikan, il.bobot_g, il.panjang_cm, il.sgr_hari_persen, il.jam_di_atas, il.jam_di_bawah, il.suhu_terpapar, il.ph_terpapar, il.do_terpapar, il.amonia_terpapar, il.pakan_dimakan_g, il.faktor_ransum, il.stres')
            ->orderBy('il.tanggal_sandbox')
            ->orderBy('i.kode');
    }

    /**
     * @return array<string, mixed>
     */
    private function normalSensorRow(object $row): array
    {
        return [
            'waktu_ukur' => (string) $row->waktu_ukur,
            'perangkat' => (string) $row->perangkat,
            'zona' => $row->zona,
            'parameter' => (string) $row->parameter,
            'nilai' => $row->nilai === null ? null : (float) $row->nilai,
            'satuan' => (string) $row->satuan,
            'kualitas_data' => (string) $row->kualitas_data,
            'baterai_persen' => $row->baterai_persen === null ? null : (int) $row->baterai_persen,
            'sinyal_rssi' => $row->sinyal_rssi === null ? null : (int) $row->sinyal_rssi,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalPopulationRow(object $row): array
    {
        return [
            'tanggal' => (string) $row->tanggal,
            'ikan' => (string) $row->ikan,
            'bobot_g' => (float) $row->bobot_g,
            'panjang_cm' => (float) $row->panjang_cm,
            'sgr_hari_persen' => (float) $row->sgr_hari_persen,
            'jam_di_atas' => (float) $row->jam_di_atas,
            'jam_di_bawah' => (float) $row->jam_di_bawah,
            'suhu_terpapar' => (float) $row->suhu_terpapar,
            'ph_terpapar' => (float) $row->ph_terpapar,
            'do_terpapar' => (float) $row->do_terpapar,
            'amonia_terpapar' => (float) $row->amonia_terpapar,
            'pakan_dimakan_g' => (float) $row->pakan_dimakan_g,
            'faktor_ransum' => (float) $row->faktor_ransum,
            'stres' => (float) $row->stres,
        ];
    }

    private function format(Request $request): ?string
    {
        $format = strtolower((string) $request->query('format', 'csv'));

        return in_array($format, ['csv', 'json'], true) ? $format : null;
    }

    private function filename(Kolam $kolam, string $version, string $extension): string
    {
        return sprintf(
            'smartaqua-%s-%s-%s.%s',
            $kolam->kode,
            $version,
            Carbon::now()->format('Ymd-His'),
            $extension,
        );
    }
}
