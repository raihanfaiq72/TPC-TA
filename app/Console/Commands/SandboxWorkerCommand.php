<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\SamplingController;
use App\Http\Controllers\Api\SiklusController;
use App\Http\Controllers\Api\SimulasiController;
use App\Models\Ikan;
use App\Models\Kolam;
use App\Models\SiklusKolam;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class SandboxWorkerCommand extends Command
{
    protected $signature = 'aqua:simulate:worker
        {--pond= : ID kolam worker}
        {--sample-hour=08:00 : Jam timbang otomatis}
        {--sample-count=25 : Jumlah ikan yang ditimbang}
        {--speed=10 : Percepatan proses}
        {--status= : Path file status JSON}';

    protected $description = 'Worker headless untuk satu kolam simulasi SmartAqua';

    private string $statusPath = '';

    public function handle(): int
    {
        set_time_limit(0);

        $pondId = (int) $this->option('pond');
        $this->statusPath = (string) $this->option('status');
        $kolam = Kolam::query()->find($pondId);

        if (! $kolam instanceof Kolam) {
            $this->writeErrorStatus('Kolam worker tidak ditemukan.');

            return self::FAILURE;
        }

        try {
            $this->runWorker(
                $kolam,
                (string) $this->option('sample-hour'),
                (int) $this->option('sample-count'),
                (int) $this->option('speed'),
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->writeStatus($kolam, 'error', $exception->getMessage());
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function runWorker(Kolam $kolam, string $sampleHour, int $sampleCount, int $speed): void
    {
        $tickMenit = max(1, (int) config('sandbox.tick_menit', 10));
        $chunkMenit = min(7200, max(1, $tickMenit * $speed));
        $current = Carbon::parse($kolam->waktu_sandbox_sekarang);
        $nextSample = $this->nextSampleTime($current, $sampleHour);
        $iteration = 0;
        $lastStatusAt = 0.0;

        $this->writeStatus($kolam, 'running');

        while (true) {
            if (++$iteration > 1000000) {
                throw new \RuntimeException('Batas iterasi worker tercapai.');
            }

            $kolam->refresh();

            if ($kolam->status === 'selesai' && ! $kolam->auto_pilot_aktif) {
                break;
            }

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

            $response = app(SimulasiController::class)->tick(
                Request::create('/api/kolam/'.$kolam->id.'/simulasi/tick', 'POST', ['menit' => $minutes]),
                $kolam->id,
            );
            $payload = json_decode((string) $response->getContent(), true);

            if ($response->getStatusCode() >= 400 || ! is_array($payload)) {
                throw new \RuntimeException($this->responseError($payload, 'Tick worker gagal.'));
            }

            if ((int) ($payload['menit_dijalankan'] ?? 0) <= 0) {
                throw new \RuntimeException('Worker berhenti sebelum target tanpa kemajuan.');
            }

            if ($payload['target_tercapai'] ?? false) {
                $cycleResponse = app(SiklusController::class)->next($kolam->id);
                $cyclePayload = json_decode((string) $cycleResponse->getContent(), true);

                if ($cycleResponse->getStatusCode() >= 400 || ! is_array($cyclePayload)) {
                    throw new \RuntimeException($this->responseError($cyclePayload, 'Siklus berikutnya gagal disiapkan.'));
                }

                if ($cyclePayload['selesai'] ?? false) {
                    break;
                }

                $kolam->refresh();
                $current = Carbon::parse($kolam->waktu_sandbox_sekarang);
                $nextSample = $this->nextSampleTime($current, $sampleHour);
            }

            $now = microtime(true);

            if ($now - $lastStatusAt >= 0.5) {
                $this->writeStatus($kolam->refresh(), 'running');
                $lastStatusAt = $now;
            }
        }

        $this->writeStatus($kolam->refresh(), 'finished');
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

    private function nextSampleTime(Carbon $current, string $hour): Carbon
    {
        return $current->copy()
            ->addDays(7)
            ->setTimeFromTimeString($hour);
    }

    private function writeStatus(Kolam $kolam, string $state, ?string $error = null): void
    {
        if ($this->statusPath === '') {
            return;
        }

        try {
            $cycles = SiklusKolam::query()
                ->where('kolam_id', $kolam->id)
                ->orderBy('urutan')
                ->get();
            $active = $cycles->firstWhere('status', 'aktif');
            $average = Ikan::query()
                ->where('kolam_id', $kolam->id)
                ->where('status', 'hidup')
                ->avg('bobot_g');
            $status = [
                'pond_id' => $kolam->id,
                'pond_code' => $kolam->kode,
                'state' => $state,
                'completed' => $cycles->where('status', 'selesai')->count(),
                'total' => $cycles->count(),
                'active_cycle' => $active?->urutan,
                'average_g' => $average === null ? null : round((float) $average, 2),
                'target_g' => $active instanceof SiklusKolam
                    ? (float) $active->target_bobot_g
                    : (float) $kolam->bobot_target_panen_g,
                'virtual_time' => $kolam->waktu_sandbox_sekarang,
                'error' => $error,
                'updated_at' => now()->toIso8601String(),
            ];
        } catch (Throwable $exception) {
            $status = [
                'pond_id' => $kolam->id,
                'state' => 'error',
                'error' => $exception->getMessage(),
                'updated_at' => now()->toIso8601String(),
            ];
        }

        $directory = dirname($this->statusPath);

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        $temporary = $this->statusPath.'.tmp';
        file_put_contents($temporary, json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        @rename($temporary, $this->statusPath);
    }

    private function writeErrorStatus(string $message): void
    {
        if ($this->statusPath === '') {
            return;
        }

        file_put_contents($this->statusPath, json_encode([
            'state' => 'error',
            'error' => $message,
            'updated_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
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
}
