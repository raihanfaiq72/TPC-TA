<?php

namespace App\Console\Commands;

use App\Console\Support\CountdownSpinner;
use App\Http\Controllers\Api\KolamController;
use App\Http\Controllers\Api\PakanController;
use App\Http\Controllers\Api\SamplingController;
use App\Http\Controllers\Api\SiklusController;
use App\Http\Controllers\Api\SimulasiController;
use App\Models\Ikan;
use App\Models\Kolam;
use App\Models\SiklusKolam;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Process\Process;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\number;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class SandboxTuiCommand extends Command
{
    protected $signature = 'aqua:simulate';

    protected $description = 'TUI simulasi lengkap SmartAqua: siklus, pakan, sampling, percepatan, worker paralel, dan estimasi selesai';

    private float $startedAt = 0.0;

    private string $etaText = '—';

    private ?float $remainingEstimate = null;

    private ?float $initialEta = null;

    private float $initialEtaAt = 0.0;

    private ?float $singleEta = null;

    private float $singleEtaAt = 0.0;

    private ?float $deadlineAt = null;

    public function handle(): int
    {
        set_time_limit(0);
        $this->startedAt = microtime(true);

        $this->renderWelcome();

        try {
            $config = $this->askConfiguration();

            if ((int) ($config['workers'] ?? 1) > 1) {
                $this->runParallel($config);

                return self::SUCCESS;
            }

            $kolam = $this->createPond($config);
            $this->configureFeed($kolam, $config['feed_times']);
            $this->startAutoPilot($kolam);

            $this->runSimulation($kolam, $config);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            error('TUI berhenti: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function askConfiguration(): array
    {
        $name = text('Nama kolam', default: 'Kolam TUI', required: true, hint: 'Contoh: Kolam 1');
        $length = (float) text('Panjang kolam (m)', default: '3', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $width = (float) text('Lebar kolam (m)', default: '2', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $depth = (float) text('Kedalaman air (m)', default: '1.2', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $population = (int) number('Populasi awal (ekor)', default: '100', min: 1, max: 20000, required: true);
        $initialWeight = (float) text('Bobot awal rata-rata (g)', default: '1.2', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $initialLength = (float) text('Panjang awal rata-rata (cm)', default: '3.5', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $target = (float) text('Target panen siklus 1 (g/ekor)', default: '10', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $kecepatanWaktu = (int) number('Kecepatan waktu (menit virtual/detik nyata)', default: '60', min: 1, max: 10000, required: true);
        $speed = (int) number('Percepatan proses (x)', default: '1000', min: 1, max: 10000, required: true, hint: 'Untuk 100 siklus, gunakan 1000 atau lebih; worker paralel akan membagi siklus');
        $cycleCount = (int) number('Jumlah siklus', default: '2', min: 1, max: 200, required: true);
        $maxWorkers = max(1, min(16, $cycleCount));
        $workerCount = (int) number(
            'Jumlah worker paralel',
            default: (string) ($cycleCount >= 10 ? min(4, $maxWorkers) : 1),
            min: 1,
            max: $maxWorkers,
            required: true,
            hint: '1 = satu kolam berurutan; >1 = siklus dibagi ke beberapa kolam worker dan dijalankan paralel',
        );
        $sampleHour = text('Jam timbang otomatis (HH:MM)', default: '08:00', required: true, validate: fn (string $value): ?string => $this->timeValidation($value));
        $sampleCount = (int) number('Sampel per timbang (ekor)', default: '25', min: 1, max: 200, required: true);
        $feedInput = text('Jam pakan otomatis (pisahkan koma)', default: '07:00,12:00,19:00', required: true);
        $feedTimes = $this->parseFeedTimes($feedInput);

        if ($feedTimes === []) {
            throw new \RuntimeException('Minimal satu jam pakan valid diperlukan.');
        }

        $cycles = [];
        $cycles[] = ['jumlah_tebar' => $population, 'target_bobot_g' => $target];

        $autoCycles = false;

        if ($cycleCount > 1) {
            $autoCycles = confirm(
                'Generate siklus berikutnya otomatis dari rentang?',
                default: true,
                hint: 'Pilih Ya agar tidak memasukkan 100 siklus satu per satu.',
            );
        }

        if ($autoCycles) {
            $minPopulation = (int) number('Jumlah tebar minimum siklus berikutnya', default: (string) max(1, (int) round($population * 0.8)), min: 1, max: 20000, required: true);
            $maxPopulation = (int) number('Jumlah tebar maksimum siklus berikutnya', default: (string) $population, min: 1, max: 20000, required: true);
            $minTarget = (float) text('Target bobot minimum siklus berikutnya (g/ekor)', default: (string) max(0.1, $target + 5), required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
            $maxTarget = (float) text('Target bobot maksimum siklus berikutnya (g/ekor)', default: (string) max($minTarget, $target + 20), required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));

            if ($minPopulation > $maxPopulation) {
                [$minPopulation, $maxPopulation] = [$maxPopulation, $minPopulation];
            }

            if ($minTarget > $maxTarget) {
                [$minTarget, $maxTarget] = [$maxTarget, $minTarget];
            }

            for ($index = 2; $index <= $cycleCount; $index++) {
                $cycles[] = [
                    'jumlah_tebar' => random_int($minPopulation, $maxPopulation),
                    'target_bobot_g' => round($minTarget + (mt_rand() / mt_getrandmax()) * ($maxTarget - $minTarget), 1),
                ];
            }

            info('Siklus berikutnya dibuat otomatis: '.($cycleCount - 1).' siklus.');
        } else {
            for ($index = 2; $index <= $cycleCount; $index++) {
                $cyclePopulation = (int) number("Siklus {$index} — jumlah tebar", default: (string) $population, min: 1, max: 20000, required: true);
                $cycleTarget = (float) text("Siklus {$index} — target bobot (g/ekor)", default: (string) ($target + ($index - 1) * 5), required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));

                $cycles[] = [
                    'jumlah_tebar' => $cyclePopulation,
                    'target_bobot_g' => $cycleTarget,
                ];
            }
        }

        return [
            'name' => $name,
            'length' => $length,
            'width' => $width,
            'depth' => $depth,
            'population' => $population,
            'initial_weight' => $initialWeight,
            'initial_length' => $initialLength,
            'target' => $target,
            'kecepatan_waktu' => $kecepatanWaktu,
            'speed' => $speed,
            'workers' => $workerCount,
            'cycles' => $cycles,
            'sample_hour' => $sampleHour,
            'sample_count' => $sampleCount,
            'feed_times' => $feedTimes,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function createPond(array $config): Kolam
    {
        $payload = [
            'kode' => 'TUI-'.strtoupper(Str::random(6)),
            'nama' => $config['name'],
            'panjang_m' => $config['length'],
            'lebar_m' => $config['width'],
            'tinggi_air_m' => $config['depth'],
            'jumlah_ikan_awal' => $config['population'],
            'bobot_awal_rata_g' => $config['initial_weight'],
            'bobot_awal_sd_g' => 0.15,
            'panjang_awal_rata_cm' => $config['initial_length'],
            'bobot_target_panen_g' => $config['target'],
            'kecepatan_waktu' => $config['kecepatan_waktu'],
            'dosis_harian_persen' => 5,
            'siklus' => $config['cycles'],
        ];

        $response = app(KolamController::class)->store(
            Request::create('/api/kolam', 'POST', $payload),
        );
        $body = json_decode((string) $response->getContent(), true);

        if ($response->getStatusCode() >= 400 || ! is_array($body)) {
            throw new \RuntimeException($this->responseError($body, 'Kolam gagal dibuat.'));
        }

        $kolam = Kolam::query()->find($body['id'] ?? null);

        if (! $kolam instanceof Kolam) {
            throw new \RuntimeException('Kolam dibuat tetapi tidak dapat dibaca kembali.');
        }

        info("Kolam dibuat: {$kolam->nama} ({$kolam->kode}) · ".$kolam->jumlah_ikan_awal.' ekor');

        return $kolam;
    }

    /**
     * @param  array<int, string>  $feedTimes
     */
    private function configureFeed(Kolam $kolam, array $feedTimes): void
    {
        $response = app(PakanController::class)->aturJadwal(
            Request::create('/api/kolam/'.$kolam->id.'/pakan/jadwal', 'PUT', ['jadwal' => $feedTimes]),
            $kolam->id,
        );
        $body = json_decode((string) $response->getContent(), true);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException($this->responseError($body, 'Jadwal pakan gagal disimpan.'));
        }

        info('Jadwal pakan: '.implode(', ', $feedTimes));
    }

    private function startAutoPilot(Kolam $kolam): void
    {
        $response = app(SimulasiController::class)->startAutoPilot(
            Request::create('/api/kolam/'.$kolam->id.'/simulasi/auto-pilot/start', 'POST'),
            $kolam->id,
        );
        $body = json_decode((string) $response->getContent(), true);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException($this->responseError($body, 'Auto pilot gagal dimulai.'));
        }

        info('Auto pilot aktif. Siklus pertama siap dijalankan.');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function runSimulation(Kolam $kolam, array $config): void
    {
        $tickMenit = max(1, (int) config('sandbox.tick_menit', 10));
        $chunkMenit = min(7200, max(1, $tickMenit * (int) $config['speed']));
        $sampleHour = (string) $config['sample_hour'];
        $sampleCount = (int) $config['sample_count'];
        $current = Carbon::parse($kolam->waktu_sandbox_sekarang);
        $nextSample = $this->nextSampleTime($current, $sampleHour);
        $cycleStartedAt = microtime(true);
        $cycleStartAverage = null;
        $lastRender = 0.0;
        $iteration = 0;

        while (true) {
            if (++$iteration > 1000000) {
                throw new \RuntimeException('Batas iterasi TUI tercapai.');
            }

            $kolam->refresh();
            $current = Carbon::parse($kolam->waktu_sandbox_sekarang);

            if ($current->greaterThanOrEqualTo($nextSample)) {
                $this->takeAutomaticSample($kolam, $nextSample, $sampleCount);
                $nextSample = $nextSample->copy()->addDays(7);
            }

            $minutes = $chunkMenit;

            if ($nextSample->greaterThan($current)) {
                $minutesToSample = (int) $current->diffInMinutes($nextSample);

                if ($minutesToSample > 0 && $minutesToSample < $minutes) {
                    $minutes = $minutesToSample;
                }
            }

            $spinner = new CountdownSpinner(
                'Menjalankan simulasi · '.$current->format('Y-m-d H:i').' · '.$minutes.' menit virtual · ETA '.$this->etaText,
                $this->remainingEstimate === null
                    ? null
                    : Carbon::now('Asia/Jakarta')->getTimestamp() + (int) round($this->remainingEstimate),
            );

            $response = $spinner->spin(
                fn () => app(SimulasiController::class)->tick(
                    Request::create('/api/kolam/'.$kolam->id.'/simulasi/tick', 'POST', ['menit' => $minutes]),
                    $kolam->id,
                ),
            );
            $payload = json_decode((string) $response->getContent(), true);

            if ($response->getStatusCode() >= 400 || ! is_array($payload)) {
                throw new \RuntimeException($this->responseError($payload, 'Tick simulasi gagal.'));
            }

            $advanced = (int) ($payload['menit_dijalankan'] ?? 0);

            if ($advanced <= 0) {
                throw new \RuntimeException('Simulasi berhenti sebelum target tanpa kemajuan.');
            }

            $kolam->refresh();
            $current = Carbon::parse($payload['waktu_sekarang'] ?? $kolam->waktu_sandbox_sekarang);
            $average = isset($payload['bobot_rata_hidup_g'])
                ? (float) $payload['bobot_rata_hidup_g']
                : null;
            $target = (float) ($payload['target_bobot_g'] ?? $kolam->bobot_target_panen_g);

            if ($average !== null && $cycleStartAverage === null) {
                $cycleStartAverage = $average;
                $cycleStartedAt = microtime(true);
            }

            $now = microtime(true);

            if ($now - $lastRender >= 0.5 || ($payload['target_tercapai'] ?? false)) {
                $this->renderDashboard(
                    $kolam,
                    $current,
                    $average,
                    $target,
                    $cycleStartedAt,
                    $cycleStartAverage,
                    $nextSample,
                    $config,
                );
                $lastRender = $now;
            }

            if ($payload['target_tercapai'] ?? false) {
                $cycleResponse = app(SiklusController::class)->next($kolam->id);
                $cyclePayload = json_decode((string) $cycleResponse->getContent(), true);

                if ($cycleResponse->getStatusCode() >= 400 || ! is_array($cyclePayload)) {
                    throw new \RuntimeException($this->responseError($cyclePayload, 'Siklus berikutnya gagal disiapkan.'));
                }

                if ($cyclePayload['selesai'] ?? false) {
                    $this->renderDashboard(
                        $kolam,
                        $current,
                        $average,
                        $target,
                        $cycleStartedAt,
                        $cycleStartAverage,
                        $nextSample,
                        $config,
                    );
                    break;
                }

                $kolam->refresh();
                $current = Carbon::parse($kolam->waktu_sandbox_sekarang);
                $cycleStartAverage = null;
                $cycleStartedAt = microtime(true);
                $nextSample = $this->nextSampleTime($current, $sampleHour);

                continue;
            }

            if ($current->greaterThanOrEqualTo($nextSample)) {
                $this->takeAutomaticSample($kolam, $nextSample, $sampleCount);
                $nextSample = $nextSample->copy()->addDays(7);
            }

            // Beri terminal waktu untuk merender indikator TUI.
            usleep(20000);
        }

        $this->renderSummary($kolam);
    }

    /**
     * Mode paralel: setiap worker mendapat satu kolam dan sebagian siklus.
     * Worker dijalankan sebagai proses PHP terpisah agar tick benar-benar paralel.
     *
     * @param  array<string, mixed>  $config
     */
    private function runParallel(array $config): void
    {
        $requestedWorkers = (int) $config['workers'];
        $workerCount = $this->safeWorkerCount($requestedWorkers);

        if ($workerCount < $requestedWorkers) {
            info('Worker dibatasi dari '.$requestedWorkers.' menjadi '.$workerCount.' karena batas koneksi database.');
        }

        $groups = $this->splitCycles(
            $config['cycles'],
            $workerCount,
            (float) $config['initial_weight'],
        );
        $statusDirectory = storage_path('app/sandbox-workers');
        File::ensureDirectoryExists($statusDirectory);

        info('Mode paralel: '.count($groups).' worker. Setiap worker memakai kolam sendiri.');
        info('Penjadwalan LPT berbobot: populasi × estimasi hari menuju target.');

        /** @var array<int, array{pond: Kolam, group: array<int, array{jumlah_tebar: int, target_bobot_g: float}>, label: string, status_path: string}> $prepared */
        $prepared = [];

        foreach ($groups as $index => $group) {
            $groupConfig = $config;
            $groupConfig['name'] = $config['name'].' — Worker '.($index + 1);
            $groupConfig['population'] = (int) $group[0]['jumlah_tebar'];
            $groupConfig['target'] = (float) $group[0]['target_bobot_g'];
            $groupConfig['cycles'] = $group;

            $pond = $this->createPond($groupConfig);
            $this->configureFeed($pond, $config['feed_times']);
            $this->startAutoPilot($pond);

            $prepared[] = [
                'pond' => $pond,
                'group' => $group,
                'label' => 'W'.($index + 1),
                'status_path' => $statusDirectory.'/worker-'.($index + 1).'-'.$pond->id.'.json',
            ];
        }

        /** @var array<int, array{process: Process, command: array<int, string>, pond: Kolam, group: array<int, array{jumlah_tebar: int, target_bobot_g: float}>, label: string, status_path: string, total: int, done: bool, attempts: int, exit_code: int|null, error: string|null, last_error: string|null, retrying: bool}> $workers */
        $workers = [];

        try {
            foreach ($prepared as $item) {
                $worker = [
                    'process' => null,
                    'command' => [
                        PHP_BINARY,
                        base_path('artisan'),
                        'aqua:simulate:worker',
                        '--pond='.$item['pond']->id,
                        '--sample-hour='.$config['sample_hour'],
                        '--sample-count='.$config['sample_count'],
                        '--speed='.$config['speed'],
                        '--status='.$item['status_path'],
                    ],
                    'pond' => $item['pond'],
                    'group' => $item['group'],
                    'label' => $item['label'],
                    'status_path' => $item['status_path'],
                    'total' => count($item['group']),
                    'done' => false,
                    'attempts' => 0,
                    'failures' => 0,
                    'last_heartbeat_at' => microtime(true),
                    'last_status_mtime' => 0,
                    'last_progress_key' => '',
                    'retry_at' => null,
                    'exit_code' => null,
                    'error' => null,
                    'last_error' => null,
                    'retrying' => false,
                ];

                $worker['process'] = $this->startWorkerProcess($worker);
                $workers[] = $worker;
            }

            $this->monitorWorkers($workers);
        } finally {
            foreach ($workers as $worker) {
                if ($worker['process']->isRunning()) {
                    $worker['process']->stop(1);
                }

                if (is_file($worker['status_path'])) {
                    @unlink($worker['status_path']);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $worker
     */
    private function startWorkerProcess(array $worker): Process
    {
        $process = new Process($worker['command'], base_path());
        $process->setTimeout(null);
        $process->start();

        return $process;
    }

    /**
     * Admission control sederhana berdasarkan max_connections MySQL.
     * Menyisakan dua koneksi untuk parent TUI dan aplikasi lain.
     */
    private function safeWorkerCount(int $requested): int
    {
        try {
            $maxRow = DB::selectOne("SHOW VARIABLES LIKE 'max_connections'");
            $usedRow = DB::selectOne("SHOW STATUS LIKE 'Threads_connected'");
            $max = (int) ($maxRow->Value ?? 0);
            $used = (int) ($usedRow->Value ?? 0);

            if ($max > 0) {
                return max(1, min($requested, max(1, $max - $used - 2)));
            }
        } catch (Throwable) {
            // Bukan MySQL atau izin SHOW dibatasi; pakai jumlah yang diminta.
        }

        return max(1, $requested);
    }

    /**
     * LPT (Longest Processing Time first) untuk mesin paralel.
     * Siklus diurutkan dari beban terbesar, lalu dimasukkan ke worker
     * dengan total beban terkecil. Graham (1969): makespan <= (4/3 - 1/(3m)) OPT.
     *
     * @param  array<int, array{jumlah_tebar: int, target_bobot_g: float}>  $cycles
     * @return array<int, array<int, array{jumlah_tebar: int, target_bobot_g: float}>>
     */
    private function splitCycles(array $cycles, int $workers, float $initialWeight): array
    {
        $total = count($cycles);
        $workers = max(1, min($workers, $total));
        $items = [];

        foreach ($cycles as $index => $cycle) {
            $items[] = [
                'index' => $index,
                'cycle' => $cycle,
                'cost' => $this->cycleCost($cycle, $initialWeight),
            ];
        }

        usort($items, fn (array $left, array $right): int => $right['cost'] <=> $left['cost']
            ?: $left['index'] <=> $right['index']);

        $bins = array_fill(0, $workers, ['cost' => 0.0, 'items' => []]);

        foreach ($items as $item) {
            $target = 0;

            for ($index = 1; $index < $workers; $index++) {
                if ($bins[$index]['cost'] < $bins[$target]['cost']) {
                    $target = $index;
                }
            }

            $bins[$target]['cost'] += $item['cost'];
            $bins[$target]['items'][] = $item;
        }

        $groups = [];

        foreach ($bins as $bin) {
            if ($bin['items'] === []) {
                continue;
            }

            usort($bin['items'], fn (array $left, array $right): int => $left['index'] <=> $right['index']);
            $groups[] = array_map(fn (array $item): array => $item['cycle'], $bin['items']);
        }

        return $groups;
    }

    /**
     * Estimasi beban satu siklus: populasi x perkiraan hari menuju target.
     * Laju pertumbuhan memakai aturan ikan yang sudah ada di config/sandbox,
     * bukan model simulasi baru.
     *
     * @param  array{jumlah_tebar: int, target_bobot_g: float}  $cycle
     */
    private function cycleCost(array $cycle, float $initialWeight): float
    {
        $population = max(1, (int) $cycle['jumlah_tebar']);
        $target = max(0.01, (float) $cycle['target_bobot_g']);
        $initialWeight = max(0.01, $initialWeight);
        $dailyGrowth = max(0.001, (float) config('sandbox.ikan.sgr_ref', 7) / 100);
        $days = $target > $initialWeight
            ? log($target / $initialWeight) / log(1 + $dailyGrowth)
            : 0.0;

        return $population * max(1.0, $days);
    }

    /**
     * @param  array<int, array<string, mixed>>  $workers
     */
    private function monitorWorkers(array $workers): void
    {
        $lastRender = 0.0;
        $stallSeconds = 45;

        while (true) {
            $anyRunning = false;

            foreach ($workers as $index => $worker) {
                if ($worker['done']) {
                    continue;
                }

                if ($worker['retry_at'] !== null) {
                    if (microtime(true) < (float) $worker['retry_at']) {
                        $anyRunning = true;

                        continue;
                    }

                    try {
                        if (is_file($worker['status_path'])) {
                            @unlink($worker['status_path']);
                        }

                        $workers[$index]['process'] = $this->startWorkerProcess($worker);
                        $workers[$index]['retry_at'] = null;
                        $workers[$index]['retrying'] = true;
                        $workers[$index]['last_status_mtime'] = 0;
                        $workers[$index]['last_progress_key'] = '0|';
                        $workers[$index]['last_heartbeat_at'] = microtime(true);
                        $anyRunning = true;
                    } catch (Throwable $exception) {
                        $workers[$index]['last_error'] = 'Gagal restart worker: '.$exception->getMessage();
                        $this->restartWorker($workers, $index, 0);
                    }

                    continue;
                }

                $statusMtime = is_file($worker['status_path']) ? (int) filemtime($worker['status_path']) : 0;
                $statusData = $this->readWorkerStatus($worker['status_path']);
                $progressKey = implode('|', [
                    (string) ($statusData['completed'] ?? 0),
                    (string) ($statusData['virtual_time'] ?? ''),
                ]);

                if ($progressKey !== (string) $worker['last_progress_key'] && $worker['process']->isRunning()) {
                    $workers[$index]['last_progress_key'] = $progressKey;
                    $workers[$index]['last_heartbeat_at'] = microtime(true);
                    $workers[$index]['failures'] = 0;
                }

                if ($statusMtime > $worker['last_status_mtime']) {
                    $workers[$index]['last_status_mtime'] = $statusMtime;
                    $workers[$index]['last_heartbeat_at'] = microtime(true);
                }

                if ($worker['process']->isRunning()) {
                    $workers[$index]['retrying'] = false;

                    if (microtime(true) - $worker['last_heartbeat_at'] > $stallSeconds) {
                        $workers[$index]['process']->stop(2);
                        $workers[$index]['last_error'] = 'Worker tidak merespons selama '.$stallSeconds.' detik.';
                        $this->restartWorker($workers, $index, $stallSeconds);
                    } else {
                        $anyRunning = true;
                    }

                    continue;
                }

                $exitCode = $worker['process']->getExitCode();
                $status = $this->readWorkerStatus($worker['status_path']);

                if ($exitCode === 0 || ($status['state'] ?? null) === 'finished') {
                    $workers[$index]['done'] = true;
                    $workers[$index]['exit_code'] = $exitCode;
                    $workers[$index]['retrying'] = false;

                    continue;
                }

                $workers[$index]['last_error'] = $this->workerFailureMessage($worker, $exitCode);
                $this->restartWorker($workers, $index, $exitCode);
            }

            foreach ($workers as $worker) {
                if (! $worker['done'] && $worker['process']->isRunning()) {
                    $anyRunning = true;
                    break;
                }
            }

            $now = microtime(true);

            if ($now - $lastRender >= 0.5) {
                try {
                    $this->renderWorkersDashboard($workers);
                } catch (Throwable $exception) {
                    try {
                        DB::disconnect();
                        DB::reconnect();
                    } catch (Throwable) {
                        // Koneksi parent akan dicoba lagi pada render berikutnya.
                    }

                    usleep(500000);
                }

                $lastRender = $now;
            }

            if (! $anyRunning) {
                $pending = false;

                foreach ($workers as $worker) {
                    if (! $worker['done']) {
                        $pending = true;
                        break;
                    }
                }

                if (! $pending) {
                    break;
                }
            }

            usleep(200000);
        }

        try {
            $this->renderWorkerSummary($workers);
        } catch (Throwable $exception) {
            error('Gagal menampilkan ringkasan worker: '.$exception->getMessage());
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $workers
     */
    private function restartWorker(array &$workers, int $index, int $exitCode): void
    {
        $worker = $workers[$index];

        $workers[$index]['attempts']++;
        $workers[$index]['failures']++;
        $workers[$index]['retrying'] = true;
        $delay = $workers[$index]['failures'] <= 1
            ? 0.0
            : min(0.5 * ($workers[$index]['failures'] - 1), 5.0);

        if ($this->isDatabaseErrorText($workers[$index]['last_error'])) {
            // Jangan menyerbu MySQL serentak saat banyak worker kena SQL error.
            $delay = max(1.0, $delay) + random_int(0, 1000) / 1000;
        }

        $workers[$index]['retry_at'] = microtime(true) + $delay;
        $workers[$index]['last_status_mtime'] = 0;
        $workers[$index]['last_progress_key'] = '0|';
        $workers[$index]['last_heartbeat_at'] = microtime(true);

        if (is_file($worker['status_path'])) {
            @unlink($worker['status_path']);
        }
    }

    private function isDatabaseErrorText(?string $error): bool
    {
        $message = strtolower((string) $error);

        foreach ([
            'sqlstate',
            'database',
            'deadlock',
            'lock wait',
            'too many connections',
            'server has gone away',
            'lost connection',
            'connection refused',
            'connection reset',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $worker
     */
    private function workerFailureMessage(array $worker, ?int $exitCode): string
    {
        $status = $this->readWorkerStatus($worker['status_path']);

        if (is_string($status['error'] ?? null) && $status['error'] !== '') {
            return $status['error'];
        }

        $error = trim($worker['process']->getErrorOutput());

        if ($error !== '') {
            return $error;
        }

        $output = trim($worker['process']->getOutput());

        return $output !== ''
            ? $output
            : 'Worker berhenti dengan kode '.($exitCode ?? '?').'.';
    }

    /**
     * @param  array<string, mixed>  $worker
     * @return array<string, mixed>
     */
    private function workerSnapshot(array $worker): array
    {
        $pond = Kolam::query()->find($worker['pond']->id);

        if (! $pond instanceof Kolam) {
            return [
                'label' => $worker['label'],
                'pond' => $worker['pond'],
                'total' => max(1, (int) $worker['total']),
                'completed' => 0,
                'active' => null,
                'average' => null,
                'target' => 0.0,
                'ratio' => 0.0,
                'progress' => 0.0,
                'virtual' => null,
                'fish' => 0,
                'last_completed' => null,
                'done' => true,
                'attempts' => $worker['attempts'],
                'retrying' => false,
                'error' => 'Kolam worker tidak ditemukan.',
                'last_error' => $worker['last_error'],
            ];
        }

        $cycles = SiklusKolam::query()
            ->where('kolam_id', $pond->id)
            ->orderBy('urutan')
            ->get();
        $completed = $cycles->where('status', 'selesai')->count();
        $active = $cycles->firstWhere('status', 'aktif');
        $target = $active instanceof SiklusKolam
            ? (float) $active->target_bobot_g
            : (float) $pond->bobot_target_panen_g;
        $fishStats = DB::table('ikans')
            ->where('kolam_id', $pond->id)
            ->where('status', 'hidup')
            ->selectRaw('COUNT(*) as fish_total, COALESCE(AVG(bobot_g), 0) as average_weight')
            ->first();
        $average = $fishStats === null ? null : (float) $fishStats->average_weight;
        $ratio = $active instanceof SiklusKolam && $target > 0 && $average !== null
            ? min(1, max(0, $average / $target))
            : 0.0;
        $total = max(1, $cycles->count());

        return [
            'label' => $worker['label'],
            'pond' => $pond,
            'total' => $total,
            'completed' => $completed,
            'active' => $active,
            'average' => $average,
            'target' => $target,
            'ratio' => $ratio,
            'progress' => min(100, (($completed + $ratio) / $total) * 100),
            'virtual' => Carbon::parse($pond->waktu_sandbox_sekarang),
            'fish' => $fishStats === null ? 0 : (int) $fishStats->fish_total,
            'last_completed' => $cycles->where('status', 'selesai')->sortByDesc('urutan')->first(),
            'done' => $worker['done'],
            'attempts' => $worker['attempts'],
            'retrying' => $worker['retrying'],
            'error' => $worker['error'],
            'last_error' => $worker['last_error'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $workers
     */
    /**
     * @param  array<int, array<string, mixed>>  $workers
     */
    private function renderWorkersDashboard(array $workers): void
    {
        $snapshots = array_map(fn (array $worker): array => $this->workerSnapshot($worker), $workers);
        $totalCycles = (int) array_sum(array_column($snapshots, 'total'));
        $completed = (int) array_sum(array_column($snapshots, 'completed'));
        $activeRatio = (float) array_sum(array_column($snapshots, 'ratio'));
        $overall = $totalCycles > 0
            ? min(100, (($completed + $activeRatio) / $totalCycles) * 100)
            : 0;
        $barWidth = 28;
        $filled = (int) round($overall / 100 * $barWidth);
        $bar = str_repeat('█', $filled).str_repeat('░', $barWidth - $filled);
        $realNow = Carbon::now('Asia/Jakarta');
        $elapsed = max(0.1, microtime(true) - $this->startedAt);
        $rawEta = $overall > 0 ? $elapsed * (100 - $overall) / $overall : null;

        if ($this->initialEta === null && $rawEta !== null && $overall >= 0.5) {
            $this->initialEta = $rawEta;
            $this->initialEtaAt = $elapsed;
            $this->deadlineAt = $this->startedAt + $this->initialEtaAt + $this->initialEta;
        }

        $remainingDeadline = $this->deadlineAt === null
            ? null
            : max(0, $this->deadlineAt - microtime(true));

        foreach ($snapshots as $index => $snapshot) {
            $workerRemaining = $snapshot['progress'] > 0
                ? $elapsed * (100 - $snapshot['progress']) / $snapshot['progress']
                : null;

            $snapshots[$index]['deadline_slack'] = $remainingDeadline !== null && $workerRemaining !== null
                ? $remainingDeadline - $workerRemaining
                : null;
        }

        $eta = $this->initialEta !== null
            ? max(0, $this->initialEta - ($elapsed - $this->initialEtaAt))
            : ($overall >= 0.5 ? $rawEta : null);

        if ($overall >= 100) {
            $eta = 0.0;
        }

        $etaText = $eta === null
            ? '—'
            : $realNow->copy()->addSeconds((int) round($eta))->format('H:i:s').' WIB';
        $countdown = $eta === null ? '—' : $this->formatCountdown($eta);
        $initialEtaText = $this->initialEta === null ? null : $this->formatReal($this->initialEta);

        $this->clearScreen();
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║           SMARTAQUA — SANDBOX AUTO PILOT TUI (WORKER)          ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('<fg=cyan;options=bold>  Jam nyata    : '.$realNow->format('H:i:s').' WIB</>');
        $this->line('  Status       : <fg=green;options=bold>'.(count(array_filter($snapshots, fn (array $snapshot): bool => ! $snapshot['done'] && $snapshot['error'] === null)) > 0 ? 'BERJALAN' : 'SELESAI').'</>');
        $this->line('  Mode         : '.count($workers).' worker paralel');
        $this->line('  Penjadwalan  : LPT berbobot + least laxity (deadline)');
        $this->line('  Siklus       : '.$completed.' / '.$totalCycles.' selesai');
        $this->line('  Progress     : ['.$bar.'] '.round($overall).'% total');
        $this->line('<fg=yellow;options=bold>  Dunia nyata  : selesai di jam '.$etaText.'</>');
        $this->line('<fg=yellow;options=bold>  Hitung mundur: '.$countdown.' lagi (estimasi)</>');
        if ($initialEtaText !== null) {
            $this->line('  Estimasi awal: '.$initialEtaText.' (dikunci, tidak naik)');
        }
        $this->line('  Durasi nyata : '.$this->formatReal($elapsed));
        $this->line('───────────────────────────────────────────────────────────────');

        $workerLines = [];

        foreach ($snapshots as $snapshot) {
            $width = 6;
            $fill = (int) round($snapshot['progress'] / 100 * $width);
            $workerBar = str_repeat('█', $fill).str_repeat('░', $width - $fill);
            $status = $snapshot['error'] !== null
                ? '!'
                : ($snapshot['done'] ? '✓' : ($snapshot['retrying'] ? 'R' : 'J'));
            $deadlineRisk = ($snapshot['deadline_slack'] ?? null) !== null
                && (float) $snapshot['deadline_slack'] < 0;
            $active = $snapshot['active'] instanceof SiklusKolam
                ? '#'.$snapshot['active']->urutan
                : '—';
            $average = $snapshot['average'] === null ? '—' : (string) round($snapshot['average']);
            $workerLines[] = str_pad($snapshot['label'], 3)
                .' ['.$workerBar.'] '.str_pad((string) round($snapshot['progress']).'%', 4)
                .' '.$snapshot['completed'].'/'.$snapshot['total']
                .' '.$active.' '.$average.'/'.round((float) $snapshot['target'])
                .' '.$status.($deadlineRisk ? 'D' : '');
        }

        $terminalWidth = max(60, (new Terminal)->getWidth());
        $columns = count($workerLines) > 3 && $terminalWidth >= 72 ? 2 : 1;

        if ($columns === 2) {
            $columnWidth = max(30, (int) floor(($terminalWidth - 4) / 2));

            foreach (array_chunk($workerLines, 2) as $chunk) {
                $left = str_pad(mb_substr($chunk[0], 0, $columnWidth), $columnWidth);
                $right = isset($chunk[1]) ? mb_substr($chunk[1], 0, $columnWidth) : '';
                $this->line('  '.$left.'  '.$right);
            }
        } else {
            foreach ($workerLines as $workerLine) {
                $this->line('  '.$workerLine);
            }
        }

        $this->line('───────────────────────────────────────────────────────────────');
        $this->line('  J=jalan R=restart !=error ✓=selesai D=deadline risk');
    }

    /**
     * @param  array<int, array<string, mixed>>  $workers
     */
    private function renderWorkerSummary(array $workers): void
    {
        $snapshots = array_map(fn (array $worker): array => $this->workerSnapshot($worker), $workers);
        $rows = array_map(fn (array $snapshot): array => [
            $snapshot['label'],
            $snapshot['pond']->kode,
            $snapshot['completed'].' / '.$snapshot['total'],
            $snapshot['average'] === null ? '—' : number_format($snapshot['average'], 2),
            number_format($snapshot['target'], 2),
            $snapshot['error'] ?? ($snapshot['done'] ? 'selesai' : 'berhenti'),
        ], $snapshots);

        $this->clearScreen();
        info('SmartAqua TUI — ringkasan worker paralel');
        $this->line('  Total waktu nyata proses: '.$this->formatReal(microtime(true) - $this->startedAt));
        $this->line('  Setiap worker memiliki kolam dan riwayat siklus sendiri.');
        $this->line('');
        table(['Worker', 'Kode kolam', 'Siklus', 'Bobot akhir', 'Target', 'Status'], $rows);
        $this->line('');
        info('Selesai. Kolam worker dapat dibuka dari menu manajemen kolam.');
    }

    /**
     * @return array<string, mixed>
     */
    private function readWorkerStatus(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function renderWelcome(): void
    {
        $this->clearScreen();
        info('SmartAqua Sandbox TUI');
        $this->line('  Buat kolam → siklus → pakan → timbang → percepatan → panen');
        $this->line('  Worker paralel membagi siklus ke beberapa kolam agar tidak saling mengunci.');
        $this->line('  Semua proses berjalan langsung di database, tanpa browser.');
        $this->line('');
    }

    private function positiveNumber(string $value): ?string
    {
        return is_numeric($value) && (float) $value > 0
            ? null
            : 'Isi dengan angka lebih dari 0.';
    }

    private function timeValidation(string $value): ?string
    {
        return preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $value) === 1
            ? null
            : 'Gunakan format HH:MM, contoh 08:00.';
    }

    /**
     * @return array<int, string>
     */
    private function parseFeedTimes(string $input): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $input)),
            fn (string $value): bool => preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $value) === 1,
        ));
    }

    private function nextSampleTime(Carbon $current, string $hour): Carbon
    {
        return $current->copy()
            ->addDays(7)
            ->setTimeFromTimeString($hour);
    }

    private function takeAutomaticSample(Kolam $kolam, Carbon $waktu, int $count): void
    {
        $sampleResponse = app(SamplingController::class)->ambilSampel(
            Request::create('/api/kolam/'.$kolam->id.'/sampel', 'GET', ['n' => $count]),
            $kolam->id,
        );
        $sample = json_decode((string) $sampleResponse->getContent(), true);

        if ($sampleResponse->getStatusCode() >= 400 || ! is_array($sample)) {
            throw new \RuntimeException($this->responseError($sample, 'Sampel otomatis gagal diambil.'));
        }

        $storeResponse = app(SamplingController::class)->store(
            Request::create('/api/kolam/'.$kolam->id.'/sampling', 'POST', [
                'tanggal' => $waktu->toDateString(),
                'jumlah_sampel' => (int) ($sample['jumlah_sampel'] ?? $count),
                'jumlah_mati' => 0,
                'bobot_total_sampel_g' => (float) ($sample['bobot_total_sampel_g'] ?? 0),
                'panjang_rata_cm' => (float) ($sample['panjang_rata_cm'] ?? 0),
            ]),
            $kolam->id,
        );
        $stored = json_decode((string) $storeResponse->getContent(), true);

        if ($storeResponse->getStatusCode() >= 400) {
            throw new \RuntimeException($this->responseError($stored, 'Timbangan otomatis gagal disimpan.'));
        }

    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function renderDashboard(
        Kolam $kolam,
        Carbon $current,
        ?float $average,
        float $target,
        float $cycleStartedAt,
        ?float $cycleStartAverage,
        ?Carbon $nextSample,
        array $config,
    ): void {
        $cycles = SiklusKolam::query()
            ->where('kolam_id', $kolam->id)
            ->orderBy('urutan')
            ->get();
        $completed = $cycles->where('status', 'selesai')->count();
        $active = $cycles->firstWhere('status', 'aktif');
        $totalCycles = max(1, $cycles->count());
        $ratio = $target > 0 && $average !== null
            ? min(1, max(0, $average / $target))
            : 0;
        $overall = min(100, (($completed + $ratio) / $totalCycles) * 100);
        [$cycleEstimate, $totalEstimate] = $this->estimateRemaining(
            $average,
            $target,
            $cycleStartAverage,
            $cycleStartedAt,
            $cycles,
            (float) $kolam->bobot_awal_rata_g,
        );

        if ($totalEstimate === null) {
            [$cycleEstimate, $totalEstimate] = $this->estimateByVirtualClock(
                $current,
                $average,
                $target,
                $cycleStartedAt,
                $cycles,
                (float) $kolam->bobot_awal_rata_g,
            );
        }

        if ($totalEstimate !== null && $this->singleEta === null) {
            $this->singleEta = $totalEstimate;
            $this->singleEtaAt = microtime(true);
        }

        if ($this->singleEta !== null) {
            $totalEstimate = max(0, $this->singleEta - (microtime(true) - $this->singleEtaAt));
        }
        $barWidth = 28;
        $filled = (int) round($overall / 100 * $barWidth);
        $bar = str_repeat('█', $filled).str_repeat('░', $barWidth - $filled);
        $realNow = Carbon::now('Asia/Jakarta');
        $etaCycle = $cycleEstimate === null
            ? '—'
            : $realNow->copy()->addSeconds((int) round($cycleEstimate))->format('H:i:s').' WIB';
        $etaTotal = $totalEstimate === null
            ? '—'
            : $realNow->copy()->addSeconds((int) round($totalEstimate))->format('H:i:s').' WIB';
        $countdown = $totalEstimate === null
            ? '—'
            : $this->formatCountdown($totalEstimate);
        $singleInitialEtaText = $this->singleEta === null ? null : $this->formatReal($this->singleEta);
        $this->remainingEstimate = $totalEstimate;
        $this->etaText = $etaTotal;

        $this->clearScreen();
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║              SMARTAQUA — SANDBOX AUTO PILOT TUI               ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('<fg=cyan;options=bold>  Jam nyata    : '.$realNow->format('H:i:s').' WIB</>');
        $this->line('  Status       : <fg=green;options=bold>'.(($completed >= $totalCycles && ! $active) ? 'SELESAI' : 'BERJALAN').'</>');
        $this->line('  Kolam        : '.$kolam->nama.' ('.$kolam->kode.')');
        $this->line('  Siklus       : '.($completed + ($active ? 1 : 0)).' / '.$totalCycles.' · '.($active ? 'Siklus '.$active->urutan : 'menunggu'));
        $this->line('  Target       : '.number_format($target, 2).' g/ekor');
        $this->line('  Rata-rata    : '.($average === null ? '—' : number_format($average, 2).' g'));
        $this->line('  Virtual      : '.$current->format('Y-m-d H:i').' · '.Ikan::query()->where('kolam_id', $kolam->id)->where('status', 'hidup')->count().' ikan hidup');
        $this->line('  Progress     : ['.$bar.'] '.round($overall).'% total');
        $this->line('<fg=yellow;options=bold>  Dunia nyata  : selesai di jam '.$etaTotal.'</>');
        $this->line('<fg=yellow;options=bold>  Hitung mundur: '.$countdown.' lagi (estimasi)</>');
        if ($singleInitialEtaText !== null) {
            $this->line('  Estimasi awal: '.$singleInitialEtaText.' (dikunci, tidak naik)');
        }
        $this->line('  Estimasi     : siklus ini '.($cycleEstimate === null ? '—' : $this->formatReal($cycleEstimate)).' · ETA siklus '.$etaCycle);
        $this->line('  Pakan        : '.implode(', ', $config['feed_times']));
        $this->line('  Timbang      : setiap 7 hari pukul '.$config['sample_hour'].' · '.$config['sample_count'].' ekor');
        $this->line('  Sample next  : '.($nextSample?->format('Y-m-d H:i') ?? '—'));
        $this->line('───────────────────────────────────────────────────────────────');
        $this->line('  Siklus        : '.$cycles->map(fn (SiklusKolam $cycle): string => '#'.$cycle->urutan.' '.$cycle->status.' ('.$cycle->jumlah_tebar.' ekor → '.number_format((float) $cycle->target_bobot_g, 1).' g)')->implode(' · '));
    }

    /**
     * @param  Collection<int, SiklusKolam>  $cycles
     * @return array{0: float|null, 1: float|null}
     */
    private function estimateRemaining(
        ?float $average,
        float $target,
        ?float $startAverage,
        float $startedAt,
        $cycles,
        float $initialAverage,
    ): array {
        if ($average === null || $startAverage === null) {
            return [null, null];
        }

        $elapsed = microtime(true) - $startedAt;
        $gain = $average - $startAverage;

        if ($elapsed <= 0 || $gain <= 0) {
            return [null, null];
        }

        $rate = $gain / $elapsed;
        $currentSeconds = max(0, $target - $average) / $rate;
        $futureSeconds = $cycles
            ->where('status', 'pending')
            ->sum(fn (SiklusKolam $cycle): float => max(0, (float) $cycle->target_bobot_g - $initialAverage) / $rate);

        return [$currentSeconds, $currentSeconds + (float) $futureSeconds];
    }

    /**
     * Estimasi cadangan saat pertumbuhan belum terukur (gain masih nol).
     * Menggunakan laju jam virtual nyata dan aturan SGR yang sudah ada.
     *
     * @param  Collection<int, SiklusKolam>  $cycles
     * @return array{0: float|null, 1: float|null}
     */
    private function estimateByVirtualClock(
        Carbon $current,
        ?float $average,
        float $target,
        float $cycleStartedAt,
        $cycles,
        float $initialAverage,
    ): array {
        $active = $cycles->firstWhere('status', 'aktif');

        if (! $active instanceof SiklusKolam || $active->tanggal_mulai === null) {
            return [null, null];
        }

        $virtualStart = Carbon::parse($active->tanggal_mulai);
        $virtualDays = max(0.0, $virtualStart->diffInSeconds($current) / 86400);
        $realDays = max(0.00001, (microtime(true) - $cycleStartedAt) / 86400);

        if ($virtualDays <= 0) {
            return [null, null];
        }

        $virtualDaysPerRealDay = $virtualDays / $realDays;
        $growth = max(0.001, (float) config('sandbox.ikan.sgr_ref', 7) / 100);
        $currentAverage = max(0.01, $average ?? $initialAverage);
        $target = max(0.01, $target);
        $logGrowth = log(1 + $growth);
        $secondsPerVirtualDay = 86400 / $virtualDaysPerRealDay;
        $currentDays = $currentAverage < $target
            ? log($target / $currentAverage) / $logGrowth
            : 0.0;
        $futureDays = $cycles
            ->where('status', 'pending')
            ->sum(function (SiklusKolam $cycle) use ($initialAverage, $logGrowth): float {
                return max(0, (float) $cycle->target_bobot_g - $initialAverage) > 0
                    ? log(max(0.01, (float) $cycle->target_bobot_g) / max(0.01, $initialAverage)) / $logGrowth
                    : 0.0;
            });

        $currentSeconds = $currentDays * $secondsPerVirtualDay;

        return [
            $currentSeconds,
            $currentSeconds + ((float) $futureDays * $secondsPerVirtualDay),
        ];
    }

    private function formatReal(float $seconds): string
    {
        if ($seconds < 60) {
            return max(1, (int) ceil($seconds)).' detik';
        }

        if ($seconds < 3600) {
            return max(1, (int) ceil($seconds / 60)).' menit';
        }

        if ($seconds < 86400) {
            return number_format($seconds / 3600, 1, ',', '.').' jam';
        }

        return number_format($seconds / 86400, 1, ',', '.').' hari';
    }

    private function formatCountdown(float $seconds): string
    {
        $total = max(0, (int) ceil($seconds));
        $hours = intdiv($total, 3600);
        $minutes = intdiv($total % 3600, 60);
        $remainingSeconds = $total % 60;

        return str_pad((string) $hours, 2, '0', STR_PAD_LEFT)
            .':'.str_pad((string) $minutes, 2, '0', STR_PAD_LEFT)
            .':'.str_pad((string) $remainingSeconds, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function responseError(?array $body, string $fallback): string
    {
        if (is_array($body['errors'] ?? null)) {
            return implode(' ', array_map(
                fn (mixed $value): string => is_array($value) ? implode(' ', $value) : (string) $value,
                $body['errors'],
            ));
        }

        return (string) ($body['message'] ?? $body['error'] ?? $fallback);
    }

    private function clearScreen(): void
    {
        // Jangan bergantung hanya pada stream_isatty(); beberapa terminal/IDE
        // melaporkan false padahal mendukung ANSI. Tanpa ini dashboard menumpuk
        // ke bawah dan terminal terus scroll.
        if (stream_isatty(STDOUT) || getenv('TERM') !== false || getenv('WT_SESSION') !== false) {
            $this->output->write("\033[2J\033[H");
        }
    }

    private function renderSummary(Kolam $kolam): void
    {
        $cycles = SiklusKolam::query()
            ->where('kolam_id', $kolam->id)
            ->orderBy('urutan')
            ->get();
        $rows = $cycles->map(fn (SiklusKolam $cycle): array => [
            (string) $cycle->urutan,
            (string) $cycle->jumlah_tebar,
            number_format((float) $cycle->target_bobot_g, 2),
            $cycle->status,
            $cycle->tanggal_mulai?->format('Y-m-d H:i') ?? '—',
            $cycle->tanggal_selesai?->format('Y-m-d H:i') ?? '—',
            $cycle->bobot_akhir_g === null ? '—' : number_format((float) $cycle->bobot_akhir_g, 2),
            $cycle->biomassa_akhir_g === null ? '—' : number_format((float) $cycle->biomassa_akhir_g, 1),
            (string) $cycle->jumlah_mati,
        ])->all();

        $this->clearScreen();
        info('SmartAqua TUI — ringkasan siklus');
        $this->line('  Kolam: '.$kolam->nama.' ('.$kolam->kode.')');
        $this->line('  Total waktu nyata proses: '.$this->formatReal(microtime(true) - $this->startedAt));
        $this->line('');
        table(
            ['Siklus', 'Tebar', 'Target g', 'Status', 'Mulai', 'Selesai', 'Bobot akhir', 'Biomassa', 'Mati'],
            $rows,
        );
        $this->line('');
        info('Selesai. Semua siklus tercatat di riwayat siklus kolam.');
    }
}
