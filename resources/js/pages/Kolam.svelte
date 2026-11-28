<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { say } from '@/lib/aqua-ui.svelte';

    type Pond = {
        id: number;
        kode: string;
        nama: string;
        panjang_m?: number;
        lebar_m?: number;
        tinggi_air_m?: number;
        jumlah_ikan_hidup?: number;
        jumlah_zona?: number;
        volume_air_m3?: number;
        waktu_mulai_sandbox?: string;
        waktu_sandbox_sekarang?: string;
        foto_url?: string | null;
    };

    type CatatForm = {
        tanggal: string;
        jumlah_sampel: number;
        bobot_rata_g: number;
        panjang_rata_cm: number;
    };

    type CatatWeek = {
        number: number;
        date: string;
        status: 'done' | 'missed' | 'available' | 'future';
    };

    type PondForm = {
        nama: string;
        kode: string;
        panjang_m: number;
        lebar_m: number;
        tinggi_air_m: number;
        jumlah_ikan_awal: number;
        bobot_awal_rata_g: number;
        panjang_awal_rata_cm: number;
        bobot_target_panen_g: number;
        kecepatan_waktu: number;
    };

    let ponds = $state<Pond[]>([]);
    let loading = $state(true);
    let error = $state('');
    let uploadingId = $state<number | null>(null);
    let createOpen = $state(false);
    let createBusy = $state(false);
    let createError = $state('');
    let deleteTarget = $state<Pond | null>(null);
    let deleteBusy = $state(false);
    let deleteError = $state('');
    let catatTarget = $state<Pond | null>(null);
    let catatForm = $state<CatatForm>({ tanggal: '', jumlah_sampel: 25, bobot_rata_g: 0, panjang_rata_cm: 0 });
    let catatBusy = $state(false);
    let catatLoading = $state(false);
    let catatError = $state('');
    let catatMinDate = $state('');
    let catatMaxDate = $state('');
    let catatWeeks = $state<CatatWeek[]>([]);

    const catatTotalWeight = $derived(
        Math.max(0, (Number(catatForm.jumlah_sampel) || 0) * (Number(catatForm.bobot_rata_g) || 0)),
    );
    const catatDateValid = $derived(
        catatForm.tanggal !== ''
        && (catatMinDate === '' || catatForm.tanggal >= catatMinDate)
        && (catatMaxDate === '' || catatForm.tanggal <= catatMaxDate),
    );

    const emptyForm = (): PondForm => ({
        nama: '',
        kode: '',
        panjang_m: 3,
        lebar_m: 2,
        tinggi_air_m: 1.2,
        jumlah_ikan_awal: 100,
        bobot_awal_rata_g: 1.2,
        panjang_awal_rata_cm: 3.5,
        bobot_target_panen_g: 100,
        kecepatan_waktu: 60,
    });

    let createForm = $state<PondForm>(emptyForm());

    const createVolume = $derived(
        Math.max(0, createForm.panjang_m * createForm.lebar_m * createForm.tinggi_air_m),
    );

    function openCreate(): void {
        createForm = emptyForm();
        createError = '';
        createOpen = true;
    }

    function generatedCode(): string {
        return `C${Date.now().toString(36).toUpperCase().slice(-6)}`;
    }

    function askDelete(pond: Pond): void {
        deleteError = '';
        deleteTarget = pond;
    }

    function dateOnly(value: string | null | undefined): string {
        if (!value) return '';

        const candidate = value.slice(0, 10);

        return /^\d{4}-\d{2}-\d{2}$/.test(candidate) ? candidate : '';
    }

    function addDays(date: string, days: number): string {
        const [year, month, day] = date.split('-').map(Number);
        const value = new Date(Date.UTC(year, month - 1, day));

        value.setUTCDate(value.getUTCDate() + days);

        return value.toISOString().slice(0, 10);
    }

    function formatDateOnly(date: string): string {
        if (!date) return '—';

        return new Date(`${date}T00:00:00Z`).toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            timeZone: 'UTC',
        });
    }

    function buildCatatWeeks(
        tebarDate: string,
        maxDate: string,
        minDate: string,
        historyDates: string[],
    ): CatatWeek[] {
        if (!tebarDate || !maxDate) return [];

        const weeks: CatatWeek[] = [];
        let cursor = addDays(tebarDate, 7);
        let number = 1;

        while (cursor <= addDays(maxDate, 7) && number <= 60) {
            const end = addDays(cursor, 6);
            const done = historyDates.some((date) => date >= cursor && date <= end);

            let status: CatatWeek['status'] = 'future';

            if (done) {
                status = 'done';
            } else if (cursor > maxDate) {
                status = 'future';
            } else if (end < maxDate) {
                status = 'missed';
            } else if (end >= minDate && cursor <= maxDate) {
                status = 'available';
            } else {
                status = 'missed';
            }

            weeks.push({ number, date: cursor, status });
            cursor = addDays(cursor, 7);
            number++;
        }

        return weeks.slice(-8);
    }

    async function openCatat(pond: Pond): Promise<void> {
        catatTarget = pond;
        catatError = '';
        catatLoading = true;

        const tebarDate = dateOnly(pond.waktu_mulai_sandbox);
        const maxDate = dateOnly(pond.waktu_sandbox_sekarang) || new Date().toISOString().slice(0, 10);
        const fallbackMin = tebarDate ? addDays(tebarDate, 7) : maxDate;

        catatMinDate = fallbackMin;
        catatMaxDate = maxDate;
        catatWeeks = buildCatatWeeks(tebarDate, maxDate, fallbackMin, []);
        catatForm = {
            tanggal: fallbackMin <= maxDate ? fallbackMin : '',
            jumlah_sampel: Math.max(1, Math.min(pond.jumlah_ikan_hidup ?? 25, 25)),
            bobot_rata_g: 0,
            panjang_rata_cm: 0,
        };

        try {
            const [sampleResponse, historyResponse] = await Promise.all([
                fetch(`/api/kolam/${pond.id}/sampel?n=25`, { headers: { Accept: 'application/json' } }),
                fetch(`/api/kolam/${pond.id}/sampling`, { headers: { Accept: 'application/json' } }),
            ]);
            const sample = sampleResponse.ok
                ? (await sampleResponse.json()) as {
                    jumlah_sampel?: number;
                    bobot_total_sampel_g?: number;
                    panjang_rata_cm?: number;
                }
                : null;
            const history = historyResponse.ok
                ? (await historyResponse.json()) as Array<{ tanggal?: string }>
                : [];
            const historyDates = history
                .map((row) => dateOnly(row.tanggal))
                .filter((date): date is string => date !== '')
                .sort();
            const lastDate = historyDates.at(-1) ?? '';
            const minDate = lastDate ? addDays(lastDate, 7) : fallbackMin;
            const count = Math.max(1, Math.round(Number(sample?.jumlah_sampel ?? catatForm.jumlah_sampel)));
            const total = Math.max(0, Number(sample?.bobot_total_sampel_g ?? 0));

            if (catatTarget?.id !== pond.id) return;

            catatMinDate = minDate;
            catatWeeks = buildCatatWeeks(tebarDate, maxDate, minDate, historyDates);
            catatForm = {
                tanggal: minDate <= maxDate ? minDate : '',
                jumlah_sampel: count,
                bobot_rata_g: total > 0 ? Number((total / count).toFixed(2)) : 0,
                panjang_rata_cm: Number(sample?.panjang_rata_cm ?? 0),
            };
        } catch {
            // Form tetap bisa diisi manual jika sampel otomatis gagal.
        } finally {
            catatLoading = false;
        }
    }

    function closeCatat(): void {
        catatTarget = null;
        catatError = '';
        catatLoading = false;
        catatMinDate = '';
        catatMaxDate = '';
        catatWeeks = [];
    }

    async function saveCatat(): Promise<void> {
        if (!catatTarget) return;

        const count = Math.max(1, Math.round(Number(catatForm.jumlah_sampel)));
        const average = Number(catatForm.bobot_rata_g);
        const length = Number(catatForm.panjang_rata_cm);
        const tanggal = dateOnly(catatForm.tanggal);

        if (!tanggal) {
            catatError = 'Tanggal timbang wajib diisi.';
            return;
        }

        if (catatMinDate && tanggal < catatMinDate) {
            catatError = `Tanggal paling cepat ${formatDateOnly(catatMinDate)}.`;
            return;
        }

        if (catatMaxDate && tanggal > catatMaxDate) {
            catatError = `Tanggal tidak boleh melewati ${formatDateOnly(catatMaxDate)}.`;
            return;
        }

        if (!Number.isFinite(count) || count < 1) {
            catatError = 'Jumlah ikan ditimbang harus minimal 1 ekor.';
            return;
        }

        if (!Number.isFinite(average) || average <= 0) {
            catatError = 'Berat rata-rata harus lebih dari 0 gram.';
            return;
        }

        if (!Number.isFinite(length) || length <= 0) {
            catatError = 'Panjang rata-rata harus lebih dari 0 cm.';
            return;
        }

        if (catatTarget.jumlah_ikan_hidup !== undefined && count > catatTarget.jumlah_ikan_hidup) {
            catatError = `Jumlah sampel melebihi ikan hidup (${catatTarget.jumlah_ikan_hidup}).`;
            return;
        }

        catatBusy = true;
        catatError = '';
        const targetName = catatTarget.nama;

        try {
            const response = await fetch(`/api/kolam/${catatTarget.id}/sampling`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tanggal,
                    jumlah_sampel: count,
                    jumlah_mati: 0,
                    bobot_total_sampel_g: Number((count * average).toFixed(2)),
                    panjang_rata_cm: Number(length.toFixed(2)),
                }),
            });
            const payload = (await response.json().catch(() => null)) as
                | { message?: string; error?: string; errors?: Record<string, string[]> }
                | null;

            if (!response.ok) {
                const validation = payload?.errors
                    ? Object.values(payload.errors).flat().join(' ')
                    : null;

                throw new Error(validation ?? payload?.error ?? payload?.message ?? 'Data timbangan tidak dapat disimpan.');
            }

            closeCatat();
            await loadPonds();
            say(`${count} ekor dari ${targetName} berhasil dicatat`);
        } catch (reason) {
            catatError = reason instanceof Error ? reason.message : 'Data timbangan tidak dapat disimpan.';
        } finally {
            catatBusy = false;
        }
    }

    async function createPond(): Promise<void> {
        createBusy = true;
        createError = '';

        try {
            const response = await fetch('/api/kolam', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ...createForm,
                    kode: createForm.kode.trim() || generatedCode(),
                    bobot_awal_sd_g: 0.15,
                    dosis_harian_persen: 5,
                }),
            });

            const payload = (await response.json().catch(() => null)) as
                | { message?: string; errors?: Record<string, string[]> }
                | null;

            if (!response.ok) {
                const messages = payload?.errors
                    ? Object.values(payload.errors).flat().join(' ')
                    : payload?.message;

                throw new Error(messages ?? 'Kolam tidak dapat dibuat.');
            }

            createOpen = false;
            await loadPonds();
            say('Kolam berhasil dibuat');
        } catch (reason) {
            createError = reason instanceof Error ? reason.message : 'Kolam tidak dapat dibuat.';
        } finally {
            createBusy = false;
        }
    }

    async function deletePond(): Promise<void> {
        if (!deleteTarget) return;

        deleteBusy = true;
        deleteError = '';

        try {
            const response = await fetch(`/api/kolam/${deleteTarget.id}`, {
                method: 'DELETE',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                const payload = (await response.json().catch(() => null)) as { message?: string } | null;
                throw new Error(payload?.message ?? 'Kolam tidak dapat dihapus.');
            }

            deleteTarget = null;
            await loadPonds();
            say('Kolam berhasil dihapus');
        } catch (reason) {
            deleteError = reason instanceof Error ? reason.message : 'Kolam tidak dapat dihapus.';
        } finally {
            deleteBusy = false;
        }
    }

    async function loadPonds(): Promise<void> {
        loading = true;
        error = '';

        try {
            const response = await fetch('/api/kolam', {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Daftar kolam tidak dapat dimuat.');
            }

            ponds = (await response.json()) as Pond[];
        } catch (reason) {
            error =
                reason instanceof Error
                    ? reason.message
                    : 'Daftar kolam tidak dapat dimuat.';
        } finally {
            loading = false;
        }
    }

    async function uploadPhoto(pondId: number, event: Event): Promise<void> {
        const input = event.currentTarget as HTMLInputElement;
        const file = input.files?.[0];

        if (!file) return;

        uploadingId = pondId;
        error = '';

        try {
            const form = new FormData();
            form.append('foto', file);

            const response = await fetch(`/api/kolam/${pondId}/foto`, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: form,
            });

            if (!response.ok) {
                const payload = (await response.json().catch(() => null)) as { message?: string } | null;
                throw new Error(payload?.message ?? 'Foto kolam tidak dapat disimpan.');
            }

            await loadPonds();
            say('Foto kolam berhasil diperbarui');
        } catch (reason) {
            error = reason instanceof Error ? reason.message : 'Foto kolam tidak dapat disimpan.';
        } finally {
            uploadingId = null;
            input.value = '';
        }
    }

    onMount(() => {
        void loadPonds();
    });
</script>

<AppHead title="Daftar kolam" />

<div class="head-row head">
    <div class="grow">
        <h1>Kolam saya</h1>
        <p class="lead">
            Pantau kondisi kolam dan perangkat IoT dari satu tempat.
        </p>
    </div>
    <button class="btn" onclick={openCreate}>
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        Tambah kolam
    </button>
</div>

{#if loading}
    <div class="card loading-card">Menghubungkan ke daftar kolam...</div>
{:else if error}
    <div class="note bad">
        <b>Daftar kolam belum tersedia.</b>
        <span>{error}</span>
        <button class="btn sm" onclick={() => void loadPonds()}>Coba lagi</button>
    </div>
{:else if ponds.length === 0}
    <div class="card empty-card">
        <div class="pond-empty-icon">◌</div>
        <h3>Belum ada kolam</h3>
        <p>Tambahkan kolam untuk mulai memantau perangkat dan kondisi air.</p>
        <button class="btn pri" onclick={openCreate}>
            Tambah kolam pertama
        </button>
    </div>
{:else}
    <div class="pond-gallery">
        {#each ponds as pond (pond.id)}
            <div class="pond-card">
                <div class="pond-photo">
                    {#if pond.foto_url}
                        <img src={pond.foto_url} alt={`Foto ${pond.nama}`} />
                    {:else}
                        <div class="pond-default-photo" aria-label="Belum ada foto kolam">
                            <svg viewBox="0 0 64 64" aria-hidden="true">
                                <path d="M8 42c8-8 15-8 24 0s16 8 24 0" />
                                <path d="M8 50c8-8 15-8 24 0s16 8 24 0" />
                                <circle cx="32" cy="21" r="8" />
                                <path d="M32 29v11M26 40h12" />
                            </svg>
                            <span>Belum ada foto</span>
                        </div>
                    {/if}
                    <span class="pond-photo-shade"></span>
                    <span class="pond-live"><i></i>Online</span>
                    <span class="pond-code">{pond.kode}</span>
                </div>
                <Link class="pond-card-body" href={`/kolam/${pond.id}`}>
                    <div class="pond-card-title">
                        <div>
                            <h2>{pond.nama}</h2>
                            <p>{pond.jumlah_zona ?? 0} zona pemantauan · {pond.panjang_m && pond.lebar_m && pond.tinggi_air_m ? `${pond.panjang_m} × ${pond.lebar_m} × ${pond.tinggi_air_m} m` : 'ukuran belum diisi'}</p>
                        </div>
                        <span class="pond-arrow">↗</span>
                    </div>
                    <div class="pond-card-stats">
                        <span><b>{(pond.jumlah_ikan_hidup ?? 0).toLocaleString('id-ID')}</b><small>ikan hidup</small></span>
                        <span><b>{pond.volume_air_m3 ? `${pond.volume_air_m3.toFixed(1)} m³` : '—'}</b><small>volume air</small></span>
                        <span><b>{pond.tinggi_air_m ? `${pond.tinggi_air_m} m` : '—'}</b><small>tinggi air</small></span>
                    </div>
                    <div class="pond-card-footer">
                        <span><i class="status-pulse"></i> Perangkat siap dipantau</span>
                        <span>Lihat detail</span>
                    </div>
                </Link>
                <label class="pond-upload" class:uploading={uploadingId === pond.id}>
                    <input type="file" accept="image/jpeg,image/png,image/webp" onchange={(event) => void uploadPhoto(pond.id, event)} disabled={uploadingId !== null} />
                    <svg viewBox="0 0 24 24"><path d="M12 16V4M7.5 8.5 12 4l4.5 4.5M5 14.5v3A1.5 1.5 0 0 0 6.5 19h11a1.5 1.5 0 0 0 1.5-1.5v-3" /></svg>
                    {uploadingId === pond.id ? 'Menyimpan...' : pond.foto_url ? 'Ganti foto' : 'Upload foto'}
                </label>
                <button class="pond-catat" type="button" onclick={() => void openCatat(pond)}>
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4.5 7.5h15l-1.6 12a1.5 1.5 0 0 1-1.5 1.3H7.6a1.5 1.5 0 0 1-1.5-1.3l-1.6-12Z" />
                        <path d="M9 7.5V6a3 3 0 0 1 6 0v1.5" />
                        <path d="M12 11v6" />
                    </svg>
                    Catat dan timbang
                </button>
                <div class="pond-card-actions">
                    <Link class="btn sm" href={`/kolam/${pond.id}?edit=1`}>Edit</Link>
                    <Link class="btn sm" href={`/kolam/${pond.id}`}>Detail</Link>
                    <button class="btn sm danger" onclick={() => askDelete(pond)}>
                        Hapus
                    </button>
                </div>
            </div>
        {/each}
    </div>
{/if}

{#if createOpen}
    <div class="pond-modal" role="presentation">
        <button class="pond-modal-backdrop" aria-label="Tutup tambah kolam" onclick={() => (createOpen = false)}></button>
        <dialog open class="pond-modal-box pond-form-box" aria-label="Tambah kolam">
            <button class="pond-modal-close" aria-label="Tutup" onclick={() => (createOpen = false)}>×</button>
            <span class="modal-kicker">KOLAM BARU</span>
            <h2>Tambah kolam</h2>
            <p class="modal-subtitle">Isi profil kolam, target panen, dan tempo simulasi. Pengaturan lain memakai nilai aman secara otomatis.</p>

            <form class="pond-form" onsubmit={(event) => { event.preventDefault(); void createPond(); }}>
                {#if createError}
                    <div class="note bad">{createError}</div>
                {/if}

                <label class="pond-field-wide">
                    Nama kolam
                    <input bind:value={createForm.nama} placeholder="Contoh: Kolam Lele A" required maxlength="100" />
                </label>

                <fieldset class="pond-fieldset">
                    <legend>Target & tempo simulasi</legend>
                    <p class="field-hint">Target panen menentukan kapan kolam selesai. Kecepatan waktu memakai satuan menit virtual per detik nyata.</p>
                    <div class="pond-field-grid">
                        <label>Target panen (g/ekor)<input type="number" min="1" step="1" inputmode="numeric" bind:value={createForm.bobot_target_panen_g} required /></label>
                        <label>
                            Kecepatan waktu (menit virtual/detik nyata)
                            <input type="number" min="1" step="1" inputmode="numeric" bind:value={createForm.kecepatan_waktu} required />
                            <span class="hint">Contoh: 60 berarti 60 menit virtual berjalan setiap 1 detik nyata.</span>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="pond-fieldset">
                    <legend>Ukuran kolam</legend>
                    <p class="field-hint">Masukkan ukuran sebenarnya. Volume air dihitung otomatis dari panjang × lebar × tinggi air.</p>
                    <div class="pond-field-grid">
                        <label>Panjang (m)<input type="number" min="0.1" step="0.1" inputmode="decimal" bind:value={createForm.panjang_m} required /></label>
                        <label>Lebar (m)<input type="number" min="0.1" step="0.1" inputmode="decimal" bind:value={createForm.lebar_m} required /></label>
                        <label>Tinggi air (m)<input type="number" min="0.1" step="0.1" inputmode="decimal" bind:value={createForm.tinggi_air_m} required /></label>
                    </div>
                    <div class="pond-volume-preview">
                        <span>Perkiraan volume air</span>
                        <b>{createVolume.toFixed(2)} m³ <small>({Math.round(createVolume * 1000).toLocaleString('id-ID')} liter)</small></b>
                    </div>
                </fieldset>

                <fieldset class="pond-fieldset">
                    <legend>Populasi awal</legend>
                    <div class="pond-field-grid">
                        <label>Jumlah ikan<input type="number" min="1" max="20000" step="1" inputmode="numeric" bind:value={createForm.jumlah_ikan_awal} required /></label>
                        <label>Bobot rata-rata (g)<input type="number" min="0.1" step="0.1" inputmode="decimal" bind:value={createForm.bobot_awal_rata_g} required /></label>
                        <label>Panjang rata-rata (cm)<input type="number" min="0.1" step="0.1" inputmode="decimal" bind:value={createForm.panjang_awal_rata_cm} required /></label>
                    </div>
                </fieldset>

                <div class="modal-actions">
                    <button type="button" class="btn" onclick={() => (createOpen = false)}>Batal</button>
                    <button type="submit" class="btn pri" disabled={createBusy}>{createBusy ? 'Membuat kolam...' : 'Buat kolam'}</button>
                </div>
            </form>
        </dialog>
    </div>
{/if}

{#if deleteTarget}
    <div class="pond-modal" role="presentation">
        <button class="pond-modal-backdrop" aria-label="Tutup hapus kolam" onclick={() => (deleteTarget = null)}></button>
        <dialog open class="pond-modal-box" aria-label="Hapus kolam">
            <button class="pond-modal-close" aria-label="Tutup" onclick={() => (deleteTarget = null)}>×</button>
            <span class="modal-kicker">HAPUS KOLAM</span>
            <h2>Hapus {deleteTarget.nama}?</h2>
            <p class="modal-subtitle">Seluruh data ikan, sensor, pakan, dan riwayat kolam <b>{deleteTarget.kode}</b> akan ikut terhapus. Tindakan ini tidak dapat dibatalkan.</p>
            {#if deleteError}
                <div class="note bad">{deleteError}</div>
            {/if}
            <div class="modal-actions">
                <button class="btn" onclick={() => (deleteTarget = null)}>Batal</button>
                <button class="btn danger" disabled={deleteBusy} onclick={() => void deletePond()}>{deleteBusy ? 'Menghapus...' : 'Ya, hapus kolam'}</button>
            </div>
        </dialog>
    </div>
{/if}

{#if catatTarget}
    <div class="pond-modal" role="presentation">
        <button class="pond-modal-backdrop" aria-label="Tutup catat timbang" onclick={closeCatat}></button>
        <dialog open class="pond-modal-box pond-form-box" aria-label="Catat dan timbang">
            <button class="pond-modal-close" aria-label="Tutup" onclick={closeCatat}>×</button>
            <span class="modal-kicker">CATAT &amp; TIMBANG</span>
            <h2>Catat hasil timbang {catatTarget.nama}</h2>
            <p class="modal-subtitle">Kolam <b>{catatTarget.kode}</b> · data akan masuk ke riwayat sampling dan memperbarui rekomendasi panen.</p>

            {#if catatLoading}
                <div class="note">Mengambil sampel ikan dari simulasi...</div>
            {/if}

            <form class="pond-form" onsubmit={(event) => { event.preventDefault(); void saveCatat(); }}>
                <label class="pond-field-wide catat-date-field">
                    Tanggal timbang
                    <input
                        type="date"
                        min={catatMinDate}
                        max={catatMaxDate}
                        bind:value={catatForm.tanggal}
                        disabled={catatLoading || catatBusy}
                        required
                    />
                    <span class="hint">
                        {#if catatMinDate > catatMaxDate}
                            Belum ada minggu yang bisa diukur. Tunggu sampai tanggal {formatDateOnly(catatMinDate)}.
                        {:else}
                            Minimal 7 hari setelah timbangan sebelumnya · paling lambat {formatDateOnly(catatMaxDate)}.
                        {/if}
                    </span>
                </label>

                {#if catatWeeks.length > 0}
                    <div class="catat-week-strip" aria-label="Status minggu penimbangan">
                        {#each catatWeeks as week}
                            <span class="catat-week {week.status}" class:selected={catatForm.tanggal >= week.date && catatForm.tanggal <= addDays(week.date, 6)}>
                                <b>Minggu {week.number}</b>
                                <small>{formatDateOnly(week.date)}</small>
                                <em>
                                    {#if week.status === 'done'}sudah
                                    {:else if week.status === 'missed'}terlewat
                                    {:else if week.status === 'available'}bisa diukur
                                    {:else}akan datang{/if}
                                </em>
                            </span>
                        {/each}
                    </div>
                {/if}

                <div class="pond-field-grid">
                    <label>
                        Jumlah ikan ditimbang
                        <input type="number" min="1" step="1" inputmode="numeric" bind:value={catatForm.jumlah_sampel} disabled={catatLoading || catatBusy} required />
                    </label>
                    <label>
                        Berat rata-rata (g)
                        <input type="number" min="0.1" step="any" inputmode="decimal" bind:value={catatForm.bobot_rata_g} disabled={catatLoading || catatBusy} required />
                    </label>
                    <label>
                        Panjang rata-rata (cm)
                        <input type="number" min="0.1" step="any" inputmode="decimal" bind:value={catatForm.panjang_rata_cm} disabled={catatLoading || catatBusy} required />
                    </label>
                </div>

                <div class="pond-volume-preview">
                    <span>Total bobot sampel</span>
                    <b>{catatTotalWeight.toLocaleString('id-ID', { maximumFractionDigits: 2 })} g</b>
                </div>

                {#if catatError}
                    <div class="note bad">{catatError}</div>
                {/if}

                <div class="modal-actions">
                    <button type="button" class="btn" onclick={closeCatat} disabled={catatBusy}>Batal</button>
                    <button type="submit" class="btn pri" disabled={catatBusy || catatLoading || !catatDateValid}>
                        {catatBusy ? 'Menyimpan...' : 'Simpan timbangan'}
                    </button>
                </div>
            </form>
        </dialog>
    </div>
{/if}
