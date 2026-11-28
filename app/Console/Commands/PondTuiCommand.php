<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\KolamController;
use App\Models\Kolam;
use App\Models\SiklusKolam;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class PondTuiCommand extends Command
{
    protected $signature = 'aqua:tui';

    protected $description = 'TUI manajemen kolam SmartAqua: lihat, tambah, detail, edit, dan hapus';

    public function handle(): int
    {
        try {
            $this->mainMenu();

            return self::SUCCESS;
        } catch (Throwable $exception) {
            error('TUI berhenti: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function mainMenu(): void
    {
        while (true) {
            $ponds = $this->ponds();
            $this->renderHeader($ponds);

            $choice = select(
                'Pilih menu',
                [
                    'refresh' => 'Muat ulang daftar',
                    'create' => 'Tambah kolam',
                    'detail' => 'Detail kolam',
                    'edit' => 'Edit kolam',
                    'delete' => 'Hapus kolam',
                    'exit' => 'Keluar',
                ],
                default: 'refresh',
                scroll: 8,
            );

            if ($choice === 'exit') {
                info('Keluar dari SmartAqua TUI.');

                return;
            }

            if ($choice === 'refresh') {
                continue;
            }

            if ($choice === 'create') {
                $this->createPond();

                continue;
            }

            $pond = $this->choosePond($ponds);

            if (! $pond instanceof Kolam) {
                continue;
            }

            match ($choice) {
                'detail' => $this->showPond($pond),
                'edit' => $this->editPond($pond),
                'delete' => $this->deletePond($pond),
                default => null,
            };
        }
    }

    /**
     * @return Collection<int, Kolam>
     */
    private function ponds()
    {
        return Kolam::query()
            ->withCount([
                'ikans as jumlah_ikan_hidup' => fn ($query) => $query->where('status', 'hidup'),
                'zonas as jumlah_zona',
            ])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, Kolam>  $ponds
     */
    private function renderHeader($ponds): void
    {
        $this->clearScreen();
        info('SmartAqua — Manajemen Kolam');
        $this->line('  CRUD kolam tanpa browser. Pilih menu dengan panah atas/bawah.');
        $this->line('');

        if ($ponds->isEmpty()) {
            warning('Belum ada kolam. Pilih "Tambah kolam".');
            $this->line('');

            return;
        }

        table(
            ['ID', 'Kode', 'Nama', 'Ikan hidup', 'Zona', 'Target g', 'Status'],
            $ponds->map(fn (Kolam $pond): array => [
                $pond->id,
                $pond->kode,
                $pond->nama,
                $pond->jumlah_ikan_hidup,
                $pond->jumlah_zona,
                number_format((float) $pond->bobot_target_panen_g, 1),
                $pond->status,
            ])->all(),
        );
        $this->line('');
    }

    /**
     * @param  Collection<int, Kolam>  $ponds
     */
    private function choosePond($ponds): ?Kolam
    {
        if ($ponds->isEmpty()) {
            warning('Belum ada kolam untuk dipilih.');

            return null;
        }

        $options = [];
        foreach ($ponds as $pond) {
            $options[(string) $pond->id] = "#{$pond->id} · {$pond->kode} · {$pond->nama}";
        }

        $id = select('Pilih kolam', $options, scroll: 10);

        return $ponds->firstWhere('id', (int) $id);
    }

    private function createPond(): void
    {
        $name = text('Nama kolam', default: 'Kolam TUI', required: true, hint: 'Contoh: Kolam 1');
        $length = (float) text('Panjang (m)', default: '3', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $width = (float) text('Lebar (m)', default: '2', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $depth = (float) text('Tinggi air (m)', default: '1.2', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $population = (int) number('Jumlah ikan', default: 100, min: 1, max: 20000, required: true);
        $weight = (float) text('Bobot awal rata-rata (g)', default: '1.2', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $fishLength = (float) text('Panjang awal rata-rata (cm)', default: '3.5', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $target = (float) text('Target panen (g/ekor)', default: '100', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $speed = (float) text('Kecepatan waktu (menit virtual/detik nyata)', default: '60', required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));

        $payload = [
            'kode' => 'TUI-'.strtoupper(Str::random(6)),
            'nama' => $name,
            'panjang_m' => $length,
            'lebar_m' => $width,
            'tinggi_air_m' => $depth,
            'jumlah_ikan_awal' => $population,
            'bobot_awal_rata_g' => $weight,
            'bobot_awal_sd_g' => 0.15,
            'panjang_awal_rata_cm' => $fishLength,
            'bobot_target_panen_g' => $target,
            'kecepatan_waktu' => $speed,
            'dosis_harian_persen' => 5,
        ];

        $response = app(KolamController::class)->store(Request::create('/api/kolam', 'POST', $payload));
        $body = json_decode((string) $response->getContent(), true);

        if ($response->getStatusCode() >= 400) {
            error($this->responseError($body, 'Kolam gagal dibuat.'));

            return;
        }

        $pond = Kolam::query()->find($body['id'] ?? null);

        if (! $pond instanceof Kolam) {
            error('Kolam dibuat, tetapi data tidak dapat dibaca kembali.');

            return;
        }

        info("Kolam #{$pond->id} {$pond->nama} ({$pond->kode}) berhasil dibuat.");
    }

    private function showPond(Kolam $pond): void
    {
        $pond->loadCount([
            'ikans as jumlah_hidup' => fn ($query) => $query->where('status', 'hidup'),
            'zonas as jumlah_zona',
        ]);

        $cycles = SiklusKolam::query()
            ->where('kolam_id', $pond->id)
            ->orderBy('urutan')
            ->get();

        $this->clearScreen();
        info('Detail kolam');
        table(
            ['Field', 'Nilai'],
            [
                ['ID', $pond->id],
                ['Kode', $pond->kode],
                ['Nama', $pond->nama],
                ['Ukuran', $pond->panjang_m.' × '.$pond->lebar_m.' × '.$pond->tinggi_air_m.' m'],
                ['Volume air', number_format((float) $pond->volume_air_m3, 2).' m³'],
                ['Ikan hidup', $pond->jumlah_hidup],
                ['Ikan awal', $pond->jumlah_ikan_awal],
                ['Zona', $pond->jumlah_zona],
                ['Target panen', number_format((float) $pond->bobot_target_panen_g, 2).' g/ekor'],
                ['Kecepatan waktu', $pond->kecepatan_waktu.' menit virtual/detik nyata'],
                ['Status', $pond->status],
                ['Auto pilot', $pond->auto_pilot_aktif ? 'aktif' : 'tidak aktif'],
            ],
        );
        $this->line('');

        if ($cycles->isNotEmpty()) {
            table(
                ['Siklus', 'Tebar', 'Target g', 'Status', 'Selesai'],
                $cycles->map(fn (SiklusKolam $cycle): array => [
                    $cycle->urutan,
                    $cycle->jumlah_tebar,
                    number_format((float) $cycle->target_bobot_g, 1),
                    $cycle->status,
                    $cycle->tanggal_selesai?->format('Y-m-d') ?? '—',
                ])->all(),
            );
        }
    }

    private function editPond(Kolam $pond): void
    {
        $name = text('Nama kolam', default: $pond->nama, required: true);
        $length = (float) text('Panjang (m)', default: (string) $pond->panjang_m, required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $width = (float) text('Lebar (m)', default: (string) $pond->lebar_m, required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $depth = (float) text('Tinggi air (m)', default: (string) $pond->tinggi_air_m, required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $target = (float) text('Target panen (g/ekor)', default: (string) $pond->bobot_target_panen_g, required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));
        $speed = (float) text('Kecepatan waktu (menit virtual/detik nyata)', default: (string) $pond->kecepatan_waktu, required: true, validate: fn (string $value): ?string => $this->positiveNumber($value));

        $response = app(KolamController::class)->update(
            Request::create('/api/kolam/'.$pond->id, 'PUT', [
                'nama' => $name,
                'panjang_m' => $length,
                'lebar_m' => $width,
                'tinggi_air_m' => $depth,
                'bobot_target_panen_g' => $target,
                'kecepatan_waktu' => $speed,
            ]),
            $pond->id,
        );
        $body = json_decode((string) $response->getContent(), true);

        if ($response->getStatusCode() >= 400) {
            error($this->responseError($body, 'Kolam gagal diperbarui.'));

            return;
        }

        info('Kolam berhasil diperbarui.');
    }

    private function deletePond(Kolam $pond): void
    {
        $confirmed = confirm(
            "Hapus kolam {$pond->kode} — {$pond->nama}?",
            default: false,
            hint: 'Semua ikan, sensor, pakan, dan riwayat kolam ikut terhapus.',
        );

        if (! $confirmed) {
            return;
        }

        $response = app(KolamController::class)->destroy($pond->id);
        $body = json_decode((string) $response->getContent(), true);

        if ($response->getStatusCode() >= 400) {
            error($this->responseError($body, 'Kolam gagal dihapus.'));

            return;
        }

        info('Kolam berhasil dihapus.');
    }

    private function positiveNumber(string $value): ?string
    {
        return is_numeric($value) && (float) $value > 0
            ? null
            : 'Isi dengan angka lebih dari 0.';
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
}
