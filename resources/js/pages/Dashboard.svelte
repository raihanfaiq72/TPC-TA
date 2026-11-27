<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';

    type Pond = {
        id: number;
        kode: string;
        nama: string;
        volume_air_m3?: number;
        padat_tebar_per_m3?: number;
        jumlah_ikan_awal?: number;
        jumlah_ikan_hidup?: number;
        jumlah_zona?: number;
        bobot_target_panen_g?: number;
        status?: string;
        waktu_mulai_sandbox?: string;
        waktu_sandbox_sekarang?: string;
        foto_url?: string | null;
    };
    type Reading = {
        perangkat: string;
        zona: string | null;
        parameter: string;
        nilai: number | null;
        satuan: string;
        waktu_ukur: string;
        kualitas_data: string;
    };
    type Recommendation = {
        status: string;
        skor_kesiapan: number;
        alasan: string;
        estimasi_hari_ke_target?: number | null;
    };
    type SamplingRow = {
        id: number;
        tanggal: string;
        jumlah_sampel: number;
        bobot_rata_g: number;
        panjang_rata_cm?: number;
    };
    type AlertRow = {
        waktu: string;
        zona: string | null;
        parameter: string;
        nilai: number;
        tingkat: string;
        status: string;
    };
    type PondSummary = Pond & {
        readings: Reading[];
        recommendation: Recommendation | null;
        latestSample: SamplingRow | null;
        alerts: AlertRow[];
        lastReadingAt: string | null;
    };

    let summaries = $state<PondSummary[]>([]);
    let loading = $state(true);
    let refreshing = $state(false);
    let error = $state('');
    let lastRefresh = $state('');

    const todayLabel = new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date());

    const totalPonds = $derived(summaries.length);
    const totalFish = $derived(summaries.reduce((total, pond) => total + (pond.jumlah_ikan_hidup ?? 0), 0));
    const totalInitial = $derived(summaries.reduce((total, pond) => total + (pond.jumlah_ikan_awal ?? 0), 0));
    const totalVolume = $derived(summaries.reduce((total, pond) => total + (pond.volume_air_m3 ?? 0), 0));
    const averageDensity = $derived(
        summaries.length > 0
            ? summaries.reduce((total, pond) => total + (pond.padat_tebar_per_m3 ?? 0), 0) / summaries.length
            : null,
    );
    const alertRows = $derived(
        summaries
            .flatMap((pond) => pond.alerts
                .filter((alert) => alert.status === 'baru')
                .map((alert) => ({ ...alert, pond })))
            .sort((left, right) => right.waktu.localeCompare(left.waktu)),
    );
    const alertPondCount = $derived(new Set(alertRows.map((alert) => alert.pond.id)).size);
    const readyCount = $derived(summaries.filter((pond) => pond.recommendation?.status === 'siap panen').length);
    const nearCount = $derived(summaries.filter((pond) => pond.recommendation?.status === 'hampir siap').length);
    const progressValues = $derived(
        summaries
            .map((pond) => pondProgress(pond))
            .filter((value): value is number => value !== null),
    );
    const averageProgress = $derived(
        progressValues.length > 0
            ? Math.round(progressValues.reduce((total, value) => total + value, 0) / progressValues.length)
            : null,
    );
    const recentAlerts = $derived(alertRows.slice(0, 5));

    async function fetchJson<T>(url: string): Promise<T | null> {
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (!response.ok) return null;

            return (await response.json()) as T;
        } catch {
            return null;
        }
    }

    async function loadDashboard(initial = false): Promise<void> {
        if (initial) {
            loading = true;
        } else {
            refreshing = true;
        }

        error = '';

        try {
            const response = await fetch('/api/kolam', { headers: { Accept: 'application/json' } });

            if (!response.ok) throw new Error('Daftar kolam tidak dapat dimuat.');

            const ponds = (await response.json()) as Pond[];
            summaries = await Promise.all(ponds.map(async (pond) => {
                const [readings, recommendation, sampling, alerts] = await Promise.all([
                    fetchJson<Reading[]>(`/api/kolam/${pond.id}/sensor/terbaru`),
                    fetchJson<Recommendation | null>(`/api/kolam/${pond.id}/rekomendasi`),
                    fetchJson<SamplingRow[]>(`/api/kolam/${pond.id}/sampling`),
                    fetchJson<AlertRow[]>(`/api/kolam/${pond.id}/alert`),
                ]);
                const latestSample = (sampling ?? [])[0] ?? null;
                const lastReadingAt = (readings ?? [])
                    .map((reading) => reading.waktu_ukur)
                    .filter(Boolean)
                    .sort()
                    .at(-1) ?? null;

                return {
                    ...pond,
                    readings: readings ?? [],
                    recommendation: recommendation ?? null,
                    latestSample,
                    alerts: alerts ?? [],
                    lastReadingAt,
                };
            }));
            lastRefresh = new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch (reason) {
            error = reason instanceof Error ? reason.message : 'Dashboard tidak dapat dimuat.';
        } finally {
            loading = false;
            refreshing = false;
        }
    }

    function pondProgress(pond: PondSummary): number | null {
        const weight = pond.latestSample?.bobot_rata_g;

        if (weight === undefined || weight === null) return null;

        const target = pond.bobot_target_panen_g ?? 100;

        return Math.min(100, Math.max(0, Math.round((weight / target) * 100)));
    }

    function readingValue(pond: PondSummary, parameter: string): number | null {
        const values = pond.readings
            .filter((reading) => reading.parameter === parameter && reading.nilai !== null)
            .map((reading) => Number(reading.nilai));

        return values.length > 0
            ? values.reduce((total, value) => total + value, 0) / values.length
            : null;
    }

    function sensorTone(parameter: string, value: number | null): 'ok' | 'warn' | 'bad' {
        if (value === null) return 'bad';

        if (parameter === 'do') return value < 3 ? 'bad' : value < 5 ? 'warn' : 'ok';
        if (parameter === 'ph') return value < 6.5 || value > 8.5 ? 'warn' : 'ok';
        if (parameter === 'suhu') return value > 31 || value < 25 ? 'warn' : 'ok';
        if (parameter === 'amonia') return value > 0.5 ? 'warn' : 'ok';

        return 'ok';
    }

    function recommendationTone(status?: string): 'ready' | 'near' | 'wait' {
        if (status === 'siap panen') return 'ready';
        if (status === 'hampir siap') return 'near';
        return 'wait';
    }

    function formatNumber(value: number | null | undefined, digits = 0): string {
        if (value === null || value === undefined || Number.isNaN(value)) return '—';

        return value.toLocaleString('id-ID', {
            maximumFractionDigits: digits,
            minimumFractionDigits: digits,
        });
    }

    function formatDate(value?: string | null): string {
        if (!value) return '—';

        const parsed = new Date(value.includes('T') ? value : `${value.replace(' ', 'T')}Z`);

        if (Number.isNaN(parsed.getTime())) return value;

        return parsed.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
        });
    }

    function formatDateTime(value?: string | null): string {
        if (!value) return '—';

        const parsed = new Date(value.includes('T') ? value : `${value.replace(' ', 'T')}Z`);

        if (Number.isNaN(parsed.getTime())) return value;

        return parsed.toLocaleString('id-ID', {
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function formatVolume(value: number | null | undefined): string {
        if (value === null || value === undefined) return '—';

        return `${value.toLocaleString('id-ID', { maximumFractionDigits: 1 })} m³`;
    }

    onMount(() => {
        void loadDashboard(true);
    });
</script>


<AppHead title="Dashboard" />

<div class="dashboard-page">
    <header class="dash-command">
        <div class="dash-command-copy">
            <span class="dash-eyebrow">CONTROL ROOM <i></i> {todayLabel}</span>
            <h1>Operasional kolam</h1>
            <p>Pantau performa budidaya, kualitas air, dan kesiapan panen dari seluruh kolam dalam satu tampilan.</p>
        </div>
        <div class="dash-command-side">
            <span class="dash-sync">
                <i class:busy={refreshing}></i>
                {refreshing ? 'Menyegarkan data...' : lastRefresh ? `Diperbarui ${lastRefresh}` : 'Menyiapkan data...'}
            </span>
            <div class="dash-command-actions">
                <button class="dash-button" type="button" onclick={() => void loadDashboard()} disabled={refreshing || loading}>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.34-5.66M20 4v6h-6" /></svg>
                    Segarkan
                </button>
                <Link class="dash-button primary" href="/kolam">
                    Kelola kolam
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                </Link>
            </div>
        </div>
    </header>

    {#if loading}
        <div class="dash-loading">
            <div class="dash-loading-bar"></div>
            <span>Menyiapkan ringkasan operasional...</span>
        </div>
    {:else if error}
        <div class="dash-error">
            <div>
                <b>Dashboard belum dapat dimuat.</b>
                <span>{error}</span>
            </div>
            <button class="dash-button" type="button" onclick={() => void loadDashboard()}>Coba lagi</button>
        </div>
    {:else if summaries.length === 0}
        <div class="dash-empty">
            <span class="dash-empty-mark">—</span>
            <h2>Belum ada kolam untuk dipantau</h2>
            <p>Tambahkan kolam pertama untuk mulai melihat populasi, kualitas air, dan kesiapan panen di sini.</p>
            <Link class="dash-button primary" href="/kolam">Buka daftar kolam</Link>
        </div>
    {:else}
        <section class="dash-kpis" aria-label="Ringkasan operasional">
            <article class="dash-kpi">
                <span class="dash-kpi-head"><i class="dash-kpi-icon">01</i>Kolam aktif</span>
                <strong>{totalPonds}</strong>
                <small>{summaries.reduce((total, pond) => total + (pond.jumlah_zona ?? 0), 0)} zona terpantau</small>
            </article>
            <article class="dash-kpi">
                <span class="dash-kpi-head"><i class="dash-kpi-icon">02</i>Populasi hidup</span>
                <strong>{formatNumber(totalFish)}</strong>
                <small>dari {formatNumber(totalInitial)} ekor ditebar</small>
            </article>
            <article class="dash-kpi">
                <span class="dash-kpi-head"><i class="dash-kpi-icon">03</i>Volume terpantau</span>
                <strong>{formatVolume(totalVolume)}</strong>
                <small>rata-rata {formatNumber(averageDensity, 1)} ekor/m³</small>
            </article>
            <article class="dash-kpi" class:alert={alertRows.length > 0}>
                <span class="dash-kpi-head"><i class="dash-kpi-icon">04</i>Peringatan baru</span>
                <strong>{alertRows.length}</strong>
                <small>{alertPondCount > 0 ? `${alertPondCount} kolam perlu ditinjau` : 'Tidak ada peringatan baru'}</small>
            </article>
            <article class="dash-kpi">
                <span class="dash-kpi-head"><i class="dash-kpi-icon">05</i>Capaian sampling</span>
                <strong>{averageProgress === null ? '—' : `${averageProgress}%`}</strong>
                <small>{readyCount > 0 ? `${readyCount} siap panen · ${nearCount} hampir siap` : `${nearCount} kolam hampir siap`}</small>
            </article>
        </section>

        <div class="dash-main-grid">
            <section class="dash-panel dash-portfolio">
                <div class="dash-panel-head">
                    <div>
                        <span class="dash-section-label">PORTOFOLIO</span>
                        <h2>Kinerja kolam</h2>
                    </div>
                    <Link class="dash-text-link" href="/kolam">
                        Lihat semua
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                    </Link>
                </div>

                <div class="dash-table-wrap">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Kolam</th>
                                <th>Status panen</th>
                                <th>Populasi</th>
                                <th>Volume / padat tebar</th>
                                <th>Sampling terakhir</th>
                                <th>Capaian</th>
                                <th>Sensor terakhir</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each summaries as pond (pond.id)}
                                {@const progress = pondProgress(pond)}
                                <tr>
                                    <td>
                                        <div class="dash-pond-cell">
                                            <div class="dash-pond-avatar">
                                                {#if pond.foto_url}
                                                    <img src={pond.foto_url} alt="" />
                                                {:else}
                                                    <span>{pond.kode.slice(0, 2)}</span>
                                                {/if}
                                            </div>
                                            <div>
                                                <b>{pond.nama}</b>
                                                <span>{pond.kode} · {pond.jumlah_zona ?? 0} zona</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="dash-rec-badge {recommendationTone(pond.recommendation?.status)}">
                                            {pond.recommendation?.status ?? 'Belum ada rekomendasi'}
                                        </span>
                                        {#if pond.recommendation?.estimasi_hari_ke_target}
                                            <small class="dash-cell-note">± {pond.recommendation.estimasi_hari_ke_target} hari</small>
                                        {/if}
                                    </td>
                                    <td>
                                        <b>{formatNumber(pond.jumlah_ikan_hidup)}</b>
                                        <small class="dash-cell-note">ekor hidup</small>
                                    </td>
                                    <td>
                                        <b>{formatVolume(pond.volume_air_m3)}</b>
                                        <small class="dash-cell-note">{formatNumber(pond.padat_tebar_per_m3, 1)} ekor/m³</small>
                                    </td>
                                    <td>
                                        <b>{pond.latestSample ? `${formatNumber(pond.latestSample.bobot_rata_g, 1)} g` : '—'}</b>
                                        <small class="dash-cell-note">{pond.latestSample ? formatDate(pond.latestSample.tanggal) : 'Belum ada sampling'}</small>
                                    </td>
                                    <td>
                                        <div class="dash-progress-cell">
                                            <div class="dash-progress-track">
                                                <i style={`width:${progress ?? 0}%`}></i>
                                            </div>
                                            <small>{progress === null ? '—' : `${progress}%`}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="dash-sensor-time">{formatDateTime(pond.lastReadingAt)}</span>
                                    </td>
                                    <td>
                                        <Link class="dash-row-action" href={`/kolam/${pond.id}`}>Detail</Link>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="dash-side">
                <section class="dash-panel dash-attention">
                    <div class="dash-panel-head">
                        <div>
                            <span class="dash-section-label">TINDAK LANJUT</span>
                            <h2>Perlu perhatian</h2>
                        </div>
                        <span class="dash-count">{alertRows.length}</span>
                    </div>

                    {#if recentAlerts.length > 0}
                        <div class="dash-alert-list">
                            {#each recentAlerts as alert}
                                <Link class="dash-alert" href={`/kolam/${alert.pond.id}`}>
                                    <i class="dash-alert-mark {alert.tingkat}"></i>
                                    <span class="dash-alert-copy">
                                        <b>{alert.pond.kode} · {alert.parameter}</b>
                                        <span>{alert.zona ?? 'kolam'} · {formatNumber(alert.nilai, 2)}</span>
                                    </span>
                                    <small>{formatDateTime(alert.waktu)}</small>
                                </Link>
                            {/each}
                        </div>
                    {:else}
                        <div class="dash-panel-empty">
                            <span class="dash-empty-line"></span>
                            <b>Tidak ada peringatan baru</b>
                            <p>Semua sensor terakhir berada di bawah ambang peringatan.</p>
                        </div>
                    {/if}
                </section>

                <section class="dash-panel dash-recs">
                    <div class="dash-panel-head">
                        <div>
                            <span class="dash-section-label">SINYAL PANEN</span>
                            <h2>Rekomendasi</h2>
                        </div>
                        <span class="dash-count">{readyCount + nearCount}</span>
                    </div>

                    {#if summaries.some((pond) => pond.recommendation)}
                        <div class="dash-rec-list">
                            {#each summaries.filter((pond) => pond.recommendation) as pond (pond.id)}
                                <Link class="dash-rec" href={`/kolam/${pond.id}`}>
                                    <span class="dash-rec-marker {recommendationTone(pond.recommendation?.status)}"></span>
                                    <span class="dash-rec-copy">
                                        <b>{pond.kode} · {pond.recommendation?.status}</b>
                                        <span>{pond.recommendation?.alasan?.split('.')[0] ?? 'Menunggu evaluasi berikutnya.'}</span>
                                    </span>
                                    <span class="dash-rec-score">{formatNumber(pond.recommendation?.skor_kesiapan, 0)}</span>
                                </Link>
                            {/each}
                        </div>
                    {:else}
                        <div class="dash-panel-empty">
                            <span class="dash-empty-line"></span>
                            <b>Belum ada rekomendasi</b>
                            <p>Selesaikan satu sesi sampling untuk memulai evaluasi panen.</p>
                        </div>
                    {/if}
                </section>
            </aside>
        </div>

        <div class="dash-bottom-grid">
            <section class="dash-panel dash-sampling">
                <div class="dash-panel-head">
                    <div>
                        <span class="dash-section-label">PERTUMBUHAN</span>
                        <h2>Capaian sampling</h2>
                    </div>
                    <span class="dash-meta">{progressValues.length} kolam terukur</span>
                </div>
                <div class="dash-sample-list">
                    {#each summaries as pond (pond.id)}
                        {@const progress = pondProgress(pond)}
                        {@const target = pond.bobot_target_panen_g ?? 100}
                        <div class="dash-sample-row">
                            <div class="dash-sample-meta">
                                <b>{pond.kode}</b>
                                <span>{pond.nama}</span>
                                <em>{pond.latestSample ? `${formatNumber(pond.latestSample.bobot_rata_g, 1)} / ${formatNumber(target)} g` : 'Belum ada sampling'}</em>
                            </div>
                            <div class="dash-sample-track">
                                <i style={`width:${progress ?? 0}%`}></i>
                                <span class="dash-sample-target"></span>
                            </div>
                            <strong>{progress === null ? '—' : `${progress}%`}</strong>
                        </div>
                    {/each}
                </div>
                <p class="dash-panel-note">Garis penuh menunjukkan target bobot panen tiap kolam. Bar menunjukkan capaian sampling terakhir.</p>
            </section>

            <section class="dash-panel dash-water">
                <div class="dash-panel-head">
                    <div>
                        <span class="dash-section-label">KUALITAS AIR</span>
                        <h2>Kondisi terakhir</h2>
                    </div>
                    <span class="dash-meta">Rata-rata zona</span>
                </div>
                <div class="dash-water-wrap">
                    <div class="dash-water-table">
                        <div class="dash-water-row dash-water-head">
                            <span>Kolam</span>
                            <span>Suhu</span>
                            <span>DO</span>
                            <span>pH</span>
                            <span>NH₃</span>
                        </div>
                        {#each summaries as pond (pond.id)}
                            {@const suhu = readingValue(pond, 'suhu')}
                            {@const oksigen = readingValue(pond, 'do')}
                            {@const ph = readingValue(pond, 'ph')}
                            {@const amonia = readingValue(pond, 'amonia')}
                            <Link class="dash-water-row" href={`/kolam/${pond.id}`}>
                                <span class="dash-water-pond">
                                    <b>{pond.kode}</b>
                                    <small>{pond.lastReadingAt ? formatDateTime(pond.lastReadingAt) : 'Belum ada data'}</small>
                                </span>
                                <span class="dash-water-value {sensorTone('suhu', suhu)}">{suhu === null ? '—' : `${formatNumber(suhu, 1)}°`}</span>
                                <span class="dash-water-value {sensorTone('do', oksigen)}">{oksigen === null ? '—' : formatNumber(oksigen, 1)}</span>
                                <span class="dash-water-value {sensorTone('ph', ph)}">{ph === null ? '—' : formatNumber(ph, 1)}</span>
                                <span class="dash-water-value {sensorTone('amonia', amonia)}">{amonia === null ? '—' : formatNumber(amonia, 2)}</span>
                            </Link>
                        {/each}
                    </div>
                </div>
                <p class="dash-panel-note">Nilai merupakan rata-rata pembacaan terakhir dari sensor zona atas dan bawah.</p>
            </section>
        </div>
    {/if}
</div>

<style>
    .dashboard-page {
        display: grid;
        gap: 14px;
        min-width: 0;
        max-width: 100%;
    }
    .dash-command {
        position: relative;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 24px;
        overflow: hidden;
        padding: 24px 26px;
        border: 1px solid #244866;
        border-radius: 18px;
        color: #ffffff;
        background: #102a46;
    }
    .dash-command::before {
        content: '';
        position: absolute;
        top: 0;
        left: 26px;
        width: 84px;
        height: 3px;
        background: #6ee7b7;
    }
    .dash-command-copy { min-width: 0; }
    .dash-eyebrow {
        display: flex;
        align-items: center;
        gap: 7px;
        color: #a9c8dc;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }
    .dash-eyebrow i {
        display: inline-block;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #6ee7b7;
    }
    .dash-command h1 {
        margin: 13px 0 0;
        color: #ffffff;
        font-size: 1.9rem;
        font-weight: 800;
        letter-spacing: -0.035em;
        line-height: 1.1;
    }
    .dash-command p {
        max-width: 700px;
        margin: 8px 0 0;
        color: #c4d7e5;
        font-size: 12px;
        line-height: 1.6;
    }
    .dash-command-side {
        display: flex;
        align-items: flex-end;
        flex: 0 0 auto;
        flex-direction: column;
        gap: 13px;
        min-width: 0;
    }
    .dash-sync {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #a9c8dc;
        font-size: 9px;
        font-weight: 700;
        white-space: nowrap;
    }
    .dash-sync i {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #6ee7b7;
    }
    .dash-sync i.busy { animation: dash-pulse 1s infinite; }
    .dash-command-actions { display: flex; align-items: center; gap: 7px; }
    .dash-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 34px;
        padding: 0 11px;
        border: 1px solid rgba(190, 222, 239, 0.3);
        border-radius: 8px;
        color: #dcecf6;
        background: rgba(255, 255, 255, 0.07);
        font-size: 10px;
        font-weight: 800;
        white-space: nowrap;
    }
    .dash-button:hover { background: rgba(255, 255, 255, 0.13); }
    .dash-button:disabled { cursor: not-allowed; opacity: 0.55; }
    .dash-button svg {
        width: 14px;
        height: 14px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    .dash-button.primary { color: #102a46; border-color: #6ee7b7; background: #6ee7b7; }
    .dash-button.primary:hover { background: #8ef0c6; }
    .dash-kpis {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
    }
    .dash-kpi {
        min-width: 0;
        padding: 13px 14px;
        border: 1px solid #dfe7f2;
        border-radius: 13px;
        background: #ffffff;
    }
    .dash-kpi.alert { border-color: #f0d9ae; background: #fffdf7; }
    .dash-kpi-head {
        display: flex;
        align-items: center;
        gap: 7px;
        color: #64748b;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }
    .dash-kpi-icon {
        display: grid;
        place-items: center;
        width: 19px;
        height: 19px;
        border-radius: 5px;
        color: #263c92;
        background: #eef3fd;
        font-size: 8px;
        font-style: normal;
        font-weight: 900;
    }
    .dash-kpi.alert .dash-kpi-icon { color: #9a6500; background: #fff1cf; }
    .dash-kpi strong {
        display: block;
        margin-top: 10px;
        color: #14235c;
        font-size: 1.55rem;
        font-weight: 900;
        letter-spacing: -0.045em;
        line-height: 1;
    }
    .dash-kpi small {
        display: block;
        overflow: hidden;
        margin-top: 6px;
        color: #7b8aa6;
        font-size: 9px;
        line-height: 1.35;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dash-main-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.75fr) minmax(280px, 0.75fr);
        gap: 14px;
        min-width: 0;
    }
    .dash-panel {
        min-width: 0;
        padding: 16px;
        border: 1px solid #dfe7f2;
        border-radius: 14px;
        background: #ffffff;
    }
    .dash-panel-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }
    .dash-panel-head h2 {
        margin: 2px 0 0;
        color: #14235c;
        font-size: 14px;
        font-weight: 900;
        letter-spacing: -0.02em;
    }
    .dash-section-label {
        color: #8a98ac;
        font-size: 8px;
        font-weight: 900;
        letter-spacing: 0.1em;
    }
    .dash-text-link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        flex: 0 0 auto;
        color: #263c92;
        font-size: 9px;
        font-weight: 800;
    }
    .dash-text-link:hover { color: #14235c; }
    :global(.dash-text-link svg) {
        width: 12px;
        height: 12px;
        fill: none;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    .dash-table-wrap { min-width: 0; max-width: 100%; overflow-x: auto; }
    .dash-table { width: 100%; min-width: 930px; border-collapse: collapse; }
    .dash-table th {
        padding: 0 8px 9px;
        border-bottom: 1px solid #e4ebf4;
        color: #8a98ac;
        font-size: 8px;
        font-weight: 900;
        letter-spacing: 0.04em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .dash-table th:first-child, .dash-table td:first-child { padding-left: 0; }
    .dash-table th:last-child, .dash-table td:last-child { padding-right: 0; }
    .dash-table td {
        padding: 11px 8px;
        border-bottom: 1px solid #eef2f8;
        color: #52647e;
        font-size: 10px;
        vertical-align: middle;
    }
    .dash-table tbody tr:last-child td { border-bottom: 0; }
    .dash-table tbody tr:hover td { background: #fbfcff; }
    .dash-table td > b { color: #14235c; font-size: 11px; font-weight: 900; }
    .dash-pond-cell { display: flex; align-items: center; gap: 8px; min-width: 150px; }
    .dash-pond-avatar {
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        width: 29px;
        height: 29px;
        overflow: hidden;
        border: 1px solid #dbe7f5;
        border-radius: 7px;
        color: #263c92;
        background: #eef3fd;
        font-size: 8px;
        font-weight: 900;
    }
    .dash-pond-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .dash-pond-cell b { display: block; max-width: 150px; overflow: hidden; color: #14235c; font-size: 11px; font-weight: 900; text-overflow: ellipsis; white-space: nowrap; }
    .dash-pond-cell span { display: block; margin-top: 2px; color: #8a98ac; font-size: 8px; }
    .dash-rec-badge {
        display: inline-flex;
        align-items: center;
        max-width: 130px;
        overflow: hidden;
        padding: 4px 6px;
        border-radius: 5px;
        color: #64748b;
        background: #f1f4f8;
        font-size: 8px;
        font-weight: 900;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .dash-rec-badge.ready { color: #11633a; background: #e7f6ed; }
    .dash-rec-badge.near { color: #263c92; background: #e8efff; }
    .dash-rec-badge.wait { color: #9a6500; background: #fff1cf; }
    .dash-cell-note { display: block; margin-top: 3px; color: #8a98ac; font-size: 8px; }
    .dash-progress-cell { display: flex; align-items: center; gap: 6px; min-width: 80px; }
    .dash-progress-track { flex: 1 1 auto; height: 5px; overflow: hidden; border-radius: 99px; background: #e9eff8; }
    .dash-progress-track i { display: block; height: 100%; border-radius: inherit; background: #263c92; }
    .dash-progress-cell small { flex: 0 0 auto; color: #52647e; font-size: 8px; font-weight: 800; }
    .dash-sensor-time { color: #52647e; font-size: 9px; white-space: nowrap; }
    .dash-row-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 25px;
        padding: 0 8px;
        border: 1px solid #d7e2f2;
        border-radius: 5px;
        color: #263c92;
        background: #f8fbff;
        font-size: 8px;
        font-weight: 900;
        white-space: nowrap;
    }
    .dash-row-action:hover { border-color: #9fb6e7; background: #eef3fd; }

    .dash-side { display: grid; align-content: start; gap: 14px; min-width: 0; }
    .dash-count {
        display: inline-grid;
        place-items: center;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        border-radius: 5px;
        color: #263c92;
        background: #eef3fd;
        font-size: 9px;
        font-weight: 900;
    }
    .dash-alert-list, .dash-rec-list { display: grid; gap: 2px; }
    .dash-alert {
        display: flex;
        align-items: flex-start;
        gap: 7px;
        min-width: 0;
        padding: 8px 5px;
        border-bottom: 1px solid #eef2f8;
    }
    .dash-alert:last-child { border-bottom: 0; }
    .dash-alert:hover { background: #fbfcff; }
    .dash-alert-mark {
        flex: 0 0 auto;
        width: 5px;
        height: 5px;
        margin-top: 5px;
        border-radius: 50%;
        background: #d9a62e;
    }
    .dash-alert-mark.kritis { background: #b3271f; }
    .dash-alert-mark.waspada { background: #d9a62e; }
    .dash-alert-copy { display: grid; flex: 1 1 auto; min-width: 0; gap: 2px; }
    .dash-alert-copy b { overflow: hidden; color: #14235c; font-size: 9px; font-weight: 900; text-overflow: ellipsis; white-space: nowrap; }
    .dash-alert-copy span { color: #8a98ac; font-size: 8px; }
    :global(.dash-alert > small) { flex: 0 0 auto; color: #8a98ac; font-size: 8px; white-space: nowrap; }
    .dash-rec {
        display: flex;
        align-items: flex-start;
        gap: 7px;
        min-width: 0;
        padding: 8px 5px;
        border-bottom: 1px solid #eef2f8;
    }
    .dash-rec:last-child { border-bottom: 0; }
    .dash-rec:hover { background: #fbfcff; }
    .dash-rec-marker {
        flex: 0 0 auto;
        width: 5px;
        height: 5px;
        margin-top: 5px;
        border-radius: 50%;
        background: #8a98ac;
    }
    .dash-rec-marker.ready { background: #16794a; }
    .dash-rec-marker.near { background: #263c92; }
    .dash-rec-copy { display: grid; flex: 1 1 auto; min-width: 0; gap: 2px; }
    .dash-rec-copy b { color: #14235c; font-size: 9px; font-weight: 900; }
    .dash-rec-copy span { display: -webkit-box; overflow: hidden; color: #8a98ac; font-size: 8px; line-height: 1.35; -webkit-box-orient: vertical; -webkit-line-clamp: 2; line-clamp: 2; }
    .dash-rec-score { flex: 0 0 auto; color: #263c92; font-size: 9px; font-weight: 900; }
    .dash-panel-empty {
        display: grid;
        gap: 4px;
        padding: 14px 4px;
        color: #8a98ac;
        font-size: 9px;
    }
    .dash-panel-empty b { color: #52647e; font-size: 10px; }
    .dash-panel-empty p { margin: 0; line-height: 1.45; }
    .dash-empty-line { display: block; width: 22px; height: 2px; margin-bottom: 3px; background: #dbe7f5; }

    .dash-bottom-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.08fr) minmax(0, 0.92fr);
        gap: 14px;
        min-width: 0;
    }
    .dash-meta { color: #8a98ac; font-size: 8px; font-weight: 700; white-space: nowrap; }
    .dash-sample-list { display: grid; gap: 9px; }
    .dash-sample-row { display: grid; grid-template-columns: minmax(145px, 0.8fr) minmax(100px, 1fr) 36px; align-items: center; gap: 10px; min-width: 0; }
    .dash-sample-meta { display: grid; min-width: 0; gap: 1px; }
    .dash-sample-meta b { color: #14235c; font-size: 9px; font-weight: 900; }
    .dash-sample-meta span { overflow: hidden; color: #52647e; font-size: 8px; text-overflow: ellipsis; white-space: nowrap; }
    .dash-sample-meta em { color: #8a98ac; font-size: 8px; font-style: normal; }
    .dash-sample-track { position: relative; height: 7px; overflow: hidden; border-radius: 99px; background: #e9eff8; }
    .dash-sample-track i { display: block; height: 100%; border-radius: inherit; background: #263c92; }
    .dash-sample-target { position: absolute; top: 0; right: 0; bottom: 0; width: 1px; background: #a9b9dc; }
    .dash-sample-row > strong { color: #263c92; font-size: 9px; font-weight: 900; text-align: right; }
    .dash-panel-note { margin: 11px 0 0; color: #8a98ac; font-size: 8px; line-height: 1.45; }
    .dash-water-wrap { min-width: 0; max-width: 100%; overflow-x: auto; }
    .dash-water-table { min-width: 550px; }
    .dash-water-row {
        display: grid;
        grid-template-columns: minmax(145px, 1.3fr) repeat(4, minmax(55px, 0.55fr));
        align-items: center;
        gap: 8px;
        min-width: 0;
        padding: 8px 4px;
        border-bottom: 1px solid #eef2f8;
        color: #52647e;
        font-size: 9px;
    }
    .dash-water-row:last-child { border-bottom: 0; }
    .dash-water-head { padding-top: 0; color: #8a98ac; font-size: 8px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.04em; }
    .dash-water-row:not(.dash-water-head):hover { background: #fbfcff; }
    .dash-water-pond { display: grid; min-width: 0; gap: 2px; }
    .dash-water-pond b { color: #14235c; font-size: 9px; font-weight: 900; }
    .dash-water-pond small { overflow: hidden; color: #8a98ac; font-size: 8px; text-overflow: ellipsis; white-space: nowrap; }
    .dash-water-value { font-weight: 800; white-space: nowrap; }
    .dash-water-value.ok { color: #16794a; }
    .dash-water-value.warn { color: #9a6500; }
    .dash-water-value.bad { color: #b3271f; }
    .dash-loading {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 30px 16px;
        border: 1px solid #dfe7f2;
        border-radius: 14px;
        color: #7b8aa6;
        background: #ffffff;
        font-size: 10px;
    }
    .dash-loading-bar {
        width: 18px;
        height: 18px;
        border: 2px solid #dbe7f5;
        border-top-color: #263c92;
        border-radius: 50%;
        animation: dash-spin 0.8s linear infinite;
    }
    .dash-error {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 13px 15px;
        border: 1px solid #f2c4c0;
        border-radius: 11px;
        color: #b42318;
        background: #fff6f5;
        font-size: 10px;
    }
    .dash-error div { display: grid; gap: 3px; }
    .dash-error b { font-size: 11px; }
    .dash-error .dash-button { color: #b42318; border-color: #f2c4c0; background: #ffffff; }
    .dash-empty {
        display: grid;
        justify-items: center;
        gap: 6px;
        padding: 54px 20px;
        border: 1px solid #dfe7f2;
        border-radius: 14px;
        background: #ffffff;
        text-align: center;
    }
    .dash-empty-mark { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; color: #263c92; background: #eef3fd; font-size: 20px; }
    .dash-empty h2 { margin: 2px 0 0; color: #14235c; font-size: 15px; }
    .dash-empty p { max-width: 380px; margin: 0; color: #7b8aa6; font-size: 10px; line-height: 1.5; }
    :global(.dash-empty .dash-button) { margin-top: 7px; color: #ffffff; border-color: #263c92; background: #263c92; }
    @keyframes dash-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
    @keyframes dash-spin { to { transform: rotate(360deg); } }

    @media (max-width: 1240px) {
        .dash-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .dash-kpi:last-child { grid-column: span 2; }
    }
    @media (max-width: 1080px) {
        .dash-main-grid { grid-template-columns: minmax(0, 1fr); }
        .dash-side { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 900px) {
        .dash-bottom-grid { grid-template-columns: minmax(0, 1fr); }
        .dash-side { grid-template-columns: minmax(0, 1fr); }
        .dash-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .dash-kpi:last-child { grid-column: auto; }
    }
    @media (max-width: 620px) {
        .dash-command { flex-direction: column; align-items: flex-start; padding: 20px 18px; }
        .dash-command-side { align-items: flex-start; width: 100%; }
        .dash-command-actions { width: 100%; }
        .dash-command-actions .dash-button { flex: 1; }
        .dash-command h1 { font-size: 1.65rem; }
        .dash-kpis { grid-template-columns: minmax(0, 1fr); }
        .dash-panel { padding: 13px; }
        .dash-sample-row { grid-template-columns: minmax(110px, 0.9fr) minmax(80px, 1fr) 32px; gap: 7px; }
    }
</style>

