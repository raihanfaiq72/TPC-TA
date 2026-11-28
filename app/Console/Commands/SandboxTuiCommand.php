<?php

namespace App\Console\Commands;

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
use Illuminate\Support\Str;
use Throwable;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\number;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class SandboxTuiCommand extends Command
{
    protected $signature = 'aqua:tui';

    protected $description = 'TUI SmartAqua: buat kolam, siklus, pakan, sampling, percepatan, dan estimasi selesai';

    private float $startedAt = 0.0;

    public function handle(): int
    {
        set_time_limit(0);
        $this->startedAt = microtime(true);

        $this->renderWelcome();

        try {
            $config = $this->askConfiguration();
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
        $population = (int) number('Populasi awal (ekor)', default: 100, min: 1, max: 20000, required: true);
        $initialWeight = (float) text('Bobot awal rata-rata (g)', default: '1.2', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $initialLength = (float) text('Panjang awal rata-rata (cm)', default: '3.5', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $target = (float) text('Target panen siklus 1 (g/ekor)', default: '10', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $kecepatanWaktu = (int) number('Kecepatan waktu (menit virtual/detik nyata)', default: 60, min: 1, max: 10000, required: true);
        $speed = (int) number('Percepatan proses (x)', default: 10, min: 1, max: 10000, required: true, hint: 'Semakin besar, semakin banyak menit virtual per request');
        $cycleCount = (int) number('Jumlah siklus', default: 2, min: 1, max: 20, required: true);
        $sampleHour = text('Jam timbang otomatis (HH:MM)', default: '08:00', required: true, validate: fn (string $value): ?string => $this->timeValidation($value));
        $sampleCount = (int) number('Sampel per timbang (ekor)', default: 25, min: 1, max: 200, required: true);
        $feedInput = text('Jam pakan otomatis (pisahkan koma)', default: '07:00,12:00,19:00', required: true);
        $feedTimes = $this->parseFeedTimes($feedInput);

        if ($feedTimes === []) {
            throw new \RuntimeException('Minimal satu jam pakan valid diperlukan.');
        }

        $cycles = [];
        $cycles[] = ['jumlah_tebar' => $population, 'target_bobot_g' => $target];

        for ($index = 2; $index <= $cycleCount; $index++) {
            $cyclePopulation = (int) number("Siklus {$index} — jumlah tebar", default: $population, min: 1, max: 20000, required: true);
            $cycleTarget = (float) text("Siklus {$index} — target bobot (g/ekor)", default: (string) ($target + ($index - 1) * 5), required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));

            $cycles[] = [
                'jumlah_tebar' => $cyclePopulation,
                'target_bobot_g' => $cycleTarget,
            ];
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

            if ($nextSample !== null && $current->greaterThanOrEqualTo($nextSample)) {
                $this->takeAutomaticSample($kolam, $nextSample, $sampleCount);
                $nextSample = $nextSample->copy()->addDays(7);
            }

            $minutes = $chunkMenit;

            if ($nextSample !== null && $nextSample->greaterThan($current)) {
                $minutesToSample = (int) $current->diffInMinutes($nextSample);

                if ($minutesToSample > 0 && $minutesToSample < $minutes) {
                    $minutes = $minutesToSample;
                }
            }

            $response = app(SimulasiController::class)->tick(
                Request::create('/api/kolam/'.$kolam->id.'/simulasi/tick', 'POST', ['menit' => $minutes]),
                $kolam->id,
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

            if ($now - $lastRender >= 0.12 || ($payload['target_tercapai'] ?? false)) {
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
                    info('Semua siklus selesai.');
                    break;
                }

                $kolam->refresh();
                $current = Carbon::parse($kolam->waktu_sandbox_sekarang);
                $cycleStartAverage = null;
                $cycleStartedAt = microtime(true);
                $nextSample = $this->nextSampleTime($current, $sampleHour);
                info('Siklus berikutnya dimulai: '.$kolam->jumlah_ikan_awal.' ekor · target '.$kolam->bobot_target_panen_g.' g');

                continue;
            }

            if ($nextSample !== null && $current->greaterThanOrEqualTo($nextSample)) {
                $this->takeAutomaticSample($kolam, $nextSample, $sampleCount);
                $nextSample = $nextSample->copy()->addDays(7);
            }

            // Beri terminal waktu untuk merender indikator TUI.
            usleep(20000);
        }

        $this->renderSummary($kolam);
    }

    private function renderWelcome(): void
    {
        $this->clearScreen();
        info('SmartAqua Sandbox TUI');
        $this->line('  Buat kolam → siklus → pakan → timbang → percepatan → panen');
        $this->line('  Semua proses berjalan langsung di database, tanpa browser.');
        $this->line();
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

        $this->line('  Timbang otomatis '.$waktu->toDateString().' · '.($sample['jumlah_sampel'] ?? $count).' ekor');
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
        $barWidth = 28;
        $filled = (int) round($overall / 100 * $barWidth);
        $bar = str_repeat('█', $filled).str_repeat('░', $barWidth - $filled);

        $this->clearScreen();
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║              SMARTAQUA — SANDBOX AUTO PILOT TUI               ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('  Kolam       : '.$kolam->nama.' ('.$kolam->kode.')');
        $this->line('  Siklus      : '.($completed + ($active ? 1 : 0)).' / '.$totalCycles.' · '.($active ? 'Siklus '.$active->urutan : 'menunggu'));
        $this->line('  Target      : '.number_format($target, 2).' g/ekor');
        $this->line('  Rata-rata   : '.($average === null ? '—' : number_format($average, 2).' g'));
        $this->line('  Virtual     : '.$current->format('Y-m-d H:i').' · '.Ikan::query()->where('kolam_id', $kolam->id)->where('status', 'hidup')->count().' ikan hidup');
        $this->line('  Progress    : ['.$bar.'] '.round($overall).'% total');
        $this->line('  Estimasi    : siklus ini '.($cycleEstimate === null ? '—' : $this->formatReal($cycleEstimate)).' · total '.($totalEstimate === null ? '—' : $this->formatReal($totalEstimate)));
        $this->line('  Pakan       : '.implode(', ', $config['feed_times']));
        $this->line('  Timbang     : setiap 7 hari pukul '.$config['sample_hour'].' · '.$config['sample_count'].' ekor');
        $this->line('  Sample next : '.($nextSample?->format('Y-m-d H:i') ?? '—'));
        $this->line('───────────────────────────────────────────────────────────────');
        $this->line('  Siklus: '.$cycles->map(fn (SiklusKolam $cycle): string => '#'.$cycle->urutan.' '.$cycle->status.' ('.$cycle->jumlah_tebar.' ekor → '.number_format((float) $cycle->target_bobot_g, 1).' g)')->implode(' · '));
        $this->line();
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
        if (stream_isatty(STDOUT)) {
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
            $cycle->urutan,
            $cycle->jumlah_tebar,
            number_format((float) $cycle->target_bobot_g, 2),
            $cycle->status,
            $cycle->tanggal_mulai?->format('Y-m-d H:i') ?? '—',
            $cycle->tanggal_selesai?->format('Y-m-d H:i') ?? '—',
            $cycle->bobot_akhir_g === null ? '—' : number_format((float) $cycle->bobot_akhir_g, 2),
            $cycle->biomassa_akhir_g === null ? '—' : number_format((float) $cycle->biomassa_akhir_g, 1),
            $cycle->jumlah_mati,
        ])->all();

        $this->clearScreen();
        info('SmartAqua TUI — ringkasan siklus');
        $this->line('  Kolam: '.$kolam->nama.' ('.$kolam->kode.')');
        $this->line('  Total waktu nyata proses: '.$this->formatReal(microtime(true) - $this->startedAt));
        $this->line();
        table(
            ['Siklus', 'Tebar', 'Target g', 'Status', 'Mulai', 'Selesai', 'Bobot akhir', 'Biomassa', 'Mati'],
            $rows,
        );
        $this->line();
        info('Selesai. Semua siklus tercatat di riwayat siklus kolam.');
    }
}
