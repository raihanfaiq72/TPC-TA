<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { say } from '@/lib/aqua-ui.svelte';

    type Pond = {
        id: number;
        kode: string;
        nama: string;
        jumlah_ikan_hidup?: number;
        jumlah_zona?: number;
        volume_air_m3?: number;
        tinggi_air_m?: number;
        foto_url?: string | null;
    };

    let ponds = $state<Pond[]>([]);
    let loading = $state(true);
    let error = $state('');
    let uploadingId = $state<number | null>(null);

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
    <button class="btn" onclick={() => say('Form tambah kolam dibuka')}>
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
        <button class="btn pri" onclick={() => say('Form tambah kolam dibuka')}>
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
                            <p>{pond.jumlah_zona ?? 0} zona pemantauan · kolam aktif</p>
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
            </div>
        {/each}
    </div>
{/if}
