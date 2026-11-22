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
        foto_url?: string | null;
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
            <p class="modal-subtitle">Cukup isi ukuran kolam dan populasi awal. Pengaturan lain sudah memakai nilai yang aman.</p>

            <form class="pond-form" onsubmit={(event) => { event.preventDefault(); void createPond(); }}>
                {#if createError}
                    <div class="note bad">{createError}</div>
                {/if}

                <label class="pond-field-wide">
                    Nama kolam
                    <input bind:value={createForm.nama} placeholder="Contoh: Kolam Lele A" required maxlength="100" />
                </label>

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

                <details class="pond-advanced">
                    <summary>Pengaturan lanjutan (opsional)</summary>
                    <div class="pond-field-grid">
                        <label>Kode kolam<input bind:value={createForm.kode} placeholder="Otomatis jika kosong" maxlength="20" /></label>
                        <label>Target panen (g)<input type="number" min="1" step="1" inputmode="numeric" bind:value={createForm.bobot_target_panen_g} /></label>
                        <label>Kecepatan waktu<input type="number" min="1" step="1" inputmode="numeric" bind:value={createForm.kecepatan_waktu} /></label>
                    </div>
                </details>

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
