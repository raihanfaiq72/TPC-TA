<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { say } from '@/lib/aqua-ui.svelte';

    type Pond = {
        id: number;
        kode: string;
        nama: string;
        panjang_m: number;
        lebar_m: number;
        tinggi_air_m: number;
        volume_air_m3: number;
        jumlah_ikan_awal: number;
        jumlah_ikan_hidup?: number;
        bobot_awal_rata_g?: number;
        bobot_target_panen_g?: number;
        status?: string;
        kecepatan_waktu?: number;
        waktu_mulai_sandbox?: string;
        waktu_sandbox_sekarang?: string;
    };

    type Zone = { id: number; nama: string; kedalaman_cm: number; porsi_volume: number };
    type Device = {
        id: number;
        kode: string;
        zona_id: number | null;
        jenis_perangkat: string;
        kode_jenis: string;
        status: string;
        interval_menit: number;
        baterai_persen: number;
        terakhir_baca: string | null;
    };
    type Reading = {
        perangkat: string;
        zona: string | null;
        parameter: string;
        nilai: number | null;
        satuan: string;
        waktu_ukur: string;
        kualitas_data: 'normal' | 'outlier' | 'gagal';
    };
    type HistoryRow = {
        tanggal: string;
        bobot_rata_g: number;
        biomassa_g: number;
        stres_rata: number;
        jumlah_mati: number;
    };
    type DeathCause = { penyebab: string; jumlah: number };
    type SimulationStatus = {
        kolam: { waktu_sandbox_sekarang: string; kecepatan_waktu: number };
        ikan: { jumlah_awal: number; jumlah_hidup: number; jumlah_mati: number; biomassa_hidup_g: number };
    };
    type DetailResponse = { kolam: Pond; zona: Zone[]; perangkat: Device[] };
    type FishRecord = {
        id: number;
        kode: string;
        bobot_g: number;
        panjang_cm: number;
        isi_lambung_persen: number;
        stres: number;
        status: string;
        tanggal_mati: string | null;
        penyebab_mati: string | null;
        zona: { id: number; nama: string } | null;
    };

    const id = Number(location.pathname.split('/').filter(Boolean).at(-1));
    let detail = $state<DetailResponse | null>(null);
    let readings = $state<Reading[]>([]);
    let selectedCode = $state('');
    let loading = $state(true);
    let refreshing = $state(false);
    let autoRefresh = $state(true);
    let deviceModalOpen = $state(false);
    let fishModalOpen = $state(false);
    let history = $state<HistoryRow[]>([]);
    let deathCauses = $state<DeathCause[]>([]);
    let simulation = $state<SimulationStatus | null>(null);
    let playing = $state(false);
    let simulationSpeed = $state(1);
    let simBusy = $state(false);
    let editOpen = $state(false);
    let resetOpen = $state(false);
    let editBusy = $state(false);
    let editForm = $state({ nama: '', panjang_m: 0, lebar_m: 0, tinggi_air_m: 0, kecepatan_waktu: 60, bobot_target_panen_g: 100 });
    let playTimer: ReturnType<typeof setInterval> | undefined;
    let fishCanvas = $state<HTMLCanvasElement>();
    let fishAnimationFrame: number | undefined;
    let fishAnimationStarted = false;
    let fishRecords = $state<FishRecord[]>([]);
    let selectedFish = $state<FishRecord | null>(null);
    let fishPositions: Array<{ x: number; y: number; fish: FishRecord }> = [];
    let error = $state('');
    let lastUpdated = $state('');

    const positions = [
        { left: 17, top: 34 },
        { left: 83, top: 34 },
        { left: 20, top: 72 },
        { left: 80, top: 72 },
        { left: 50, top: 18 },
        { left: 50, top: 84 },
    ];
    const labels: Record<string, string> = {
        suhu: 'Suhu',
        ph: 'pH',
        do: 'Oksigen terlarut',
        amonia: 'Amonia',
        level: 'Level air',
    };

    function formatValue(value: number | null, unit = ''): string {
        return value === null ? '—' : `${value.toLocaleString('id-ID', { maximumFractionDigits: 2 })}${unit ? ` ${unit}` : ''}`;
    }

    function readingFor(device: Device): Reading | undefined {
        return readings.find((reading) => reading.perangkat === device.kode);
    }

    function statusFor(reading: Reading | undefined): 'ok' | 'warn' | 'bad' {
        if (!reading || reading.kualitas_data === 'gagal') return 'bad';
        if (reading.kualitas_data === 'outlier' || (reading.parameter === 'do' && (reading.nilai ?? 9) < 3)) return 'warn';
        return 'ok';
    }

    function statusLabel(status: 'ok' | 'warn' | 'bad'): string {
        return status === 'ok' ? 'Normal' : status === 'warn' ? 'Perlu perhatian' : 'Belum terbaca';
    }

    function devicePosition(device: Device): { left: number; top: number } {
        const zone = detail?.zona.find((item) => item.id === device.zona_id);
        const zoneName = zone?.nama ?? '';
        const code = device.kode_jenis.toLowerCase();

        if (code === 'feeder') return { left: 84, top: 50 };
        if (code === 'level') return { left: 16, top: 50 };

        const atas = zoneName === 'atas';
        const parameterPositions: Record<string, { left: number; top: number }> = {
            suhu: atas ? { left: 19, top: 26 } : { left: 19, top: 72 },
            ph: atas ? { left: 29, top: 21 } : { left: 29, top: 78 },
            do: atas ? { left: 71, top: 21 } : { left: 71, top: 78 },
            amonia: atas ? { left: 81, top: 27 } : { left: 81, top: 72 },
        };

        return parameterPositions[code] ?? (atas
            ? { left: 50, top: 19 }
            : { left: 50, top: 82 });
    }

    async function loadDetail(): Promise<void> {
        try {
            const response = await fetch(`/api/kolam/${id}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Detail kolam tidak dapat dimuat.');
            detail = (await response.json()) as DetailResponse;
            selectedCode = detail.perangkat[0]?.kode ?? '';
        } catch (reason) {
            error = reason instanceof Error ? reason.message : 'Detail kolam tidak dapat dimuat.';
        }
    }

    async function loadReadings(): Promise<void> {
        const response = await fetch(`/api/kolam/${id}/sensor/terbaru`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Data sensor belum bisa dibaca.');
        readings = (await response.json()) as Reading[];
        lastUpdated = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    async function loadSimulation(): Promise<void> {
        const response = await fetch(`/api/kolam/${id}/simulasi`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Status sandbox belum bisa dibaca.');
        simulation = (await response.json()) as SimulationStatus;
    }

    async function loadHistory(): Promise<void> {
        const response = await fetch(`/api/kolam/${id}/ikan/riwayat`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Riwayat pertumbuhan belum bisa dibaca.');
        const payload = (await response.json()) as { riwayat: HistoryRow[]; kematian: DeathCause[] };
        history = payload.riwayat;
        deathCauses = payload.kematian;
    }

    async function loadFish(): Promise<void> {
        const response = await fetch(`/api/kolam/${id}/ikan`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Data populasi ikan belum bisa dibaca.');
        const payload = (await response.json()) as { ikan: FishRecord[] };
        fishRecords = payload.ikan.filter((fish) => fish.status === 'hidup');
    }

    async function advanceSimulation(): Promise<void> {
        if (simBusy) return;
        simBusy = true;
        try {
            const response = await fetch(`/api/kolam/${id}/simulasi/tick`, {
                method: 'POST',
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Simulasi tidak dapat dilanjutkan.');
            await Promise.all([loadSimulation(), loadDetail(), loadReadings(), loadHistory(), loadFish()]);
        } catch (reason) {
            error = reason instanceof Error ? reason.message : 'Simulasi tidak dapat dilanjutkan.';
            playing = false;
        } finally {
            simBusy = false;
        }
    }

    function togglePlayback(): void {
        playing = !playing;
        if (playing) {
            void advanceSimulation();
            playTimer = setInterval(() => void advanceSimulation(), Math.max(700, 3200 / simulationSpeed));
        } else if (playTimer) {
            clearInterval(playTimer);
            playTimer = undefined;
        }
    }

    function changeSpeed(speed: number): void {
        simulationSpeed = speed;
        if (playing) {
            if (playTimer) clearInterval(playTimer);
            playTimer = setInterval(() => void advanceSimulation(), Math.max(700, 3200 / simulationSpeed));
        }
    }

    function openEdit(): void {
        if (!detail) return;
        editForm = {
            nama: detail.kolam.nama,
            panjang_m: detail.kolam.panjang_m,
            lebar_m: detail.kolam.lebar_m,
            tinggi_air_m: detail.kolam.tinggi_air_m,
            kecepatan_waktu: detail.kolam.kecepatan_waktu ?? 60,
            bobot_target_panen_g: detail.kolam.bobot_target_panen_g ?? 100,
        };
        editOpen = true;
    }

    async function saveEdit(): Promise<void> {
        editBusy = true;
        try {
            const response = await fetch(`/api/kolam/${id}`, {
                method: 'PUT',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(editForm),
            });
            if (!response.ok) throw new Error('Perubahan kolam tidak dapat disimpan.');
            editOpen = false;
            await Promise.all([loadDetail(), loadSimulation()]);
            say('Data kolam berhasil diperbarui');
        } catch (reason) {
            error = reason instanceof Error ? reason.message : 'Perubahan kolam tidak dapat disimpan.';
        } finally {
            editBusy = false;
        }
    }

    async function resetPond(): Promise<void> {
        editBusy = true;
        try {
            const response = await fetch(`/api/kolam/${id}/reset`, {
                method: 'POST',
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Kolam tidak dapat direset.');
            resetOpen = false;
            playing = false;
            if (playTimer) clearInterval(playTimer);
            await Promise.all([loadDetail(), loadSimulation(), loadReadings(), loadHistory(), loadFish()]);
            say('Kolam kembali ke kondisi awal');
        } catch (reason) {
            error = reason instanceof Error ? reason.message : 'Kolam tidak dapat direset.';
        } finally {
            editBusy = false;
        }
    }

    function formatVirtualTime(value: string | undefined): string {
        if (!value) return '—';
        return new Date(value.replace(' ', 'T')).toLocaleString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    async function refreshNow(): Promise<void> {
        refreshing = true;
        error = '';
        try {
            const response = await fetch(`/api/kolam/${id}/sensor/baca`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ waktu: new Date().toISOString().slice(0, 19).replace('T', ' ') }),
            });
            if (!response.ok) throw new Error('Perangkat belum merespons.');
            await loadReadings();
            say('Data perangkat berhasil diperbarui');
        } catch (reason) {
            error = reason instanceof Error ? reason.message : 'Perangkat belum merespons.';
        } finally {
            refreshing = false;
        }
    }

    function startFishCanvas(): void {
        if (fishAnimationStarted) return;
        const canvas = fishCanvas;
        if (!canvas) return;

        fishAnimationStarted = true;
        const context = canvas.getContext('2d');
        if (!context) return;

        const random = (index: number, salt: number): number => {
            const value = Math.sin((index + 1) * 12.9898 + salt * 78.233) * 43758.5453;
            return value - Math.floor(value);
        };

        let lastDraw = 0;
        const draw = (time: number): void => {
            if (time - lastDraw < 80) {
                fishAnimationFrame = requestAnimationFrame(draw);
                return;
            }
            lastDraw = time;

            const rect = canvas.getBoundingClientRect();
            const ratio = Math.min(window.devicePixelRatio || 1, 2);
            if (canvas.width !== Math.round(rect.width * ratio) || canvas.height !== Math.round(rect.height * ratio)) {
                canvas.width = Math.round(rect.width * ratio);
                canvas.height = Math.round(rect.height * ratio);
            }

            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            context.clearRect(0, 0, rect.width, rect.height);

            const cx = rect.width * 0.5;
            const cy = rect.height * 0.52;
            const rx = rect.width * 0.43;
            const ry = rect.height * 0.34;

            context.save();
            context.beginPath();
            context.ellipse(cx, cy, rx, ry, -0.04, 0, Math.PI * 2);
            context.clip();

            fishPositions = [];
            for (let index = 0; index < fishRecords.length; index += 1) {
                const fish = fishRecords[index];
                const zoneName = fish.zona?.nama ?? (index % 2 === 0 ? 'atas' : 'bawah');
                const angle = random(fish.id, 1) * Math.PI * 2;
                const radius = Math.sqrt(random(fish.id, 2));
                const zoneY = zoneName === 'atas' ? cy - ry * 0.28 : cy + ry * 0.18;
                const baseX = cx + Math.cos(angle) * rx * 0.82 * radius;
                const baseY = zoneY + Math.sin(angle) * ry * 0.42 * radius;
                const x = baseX + Math.sin(time * 0.0007 + fish.id * 0.19) * 2.2;
                const y = baseY + Math.cos(time * 0.0009 + fish.id * 0.13) * 1.4;
                const size = fishRecords.length > 300
                    ? 1.7 + random(fish.id, 3) * 1.2
                    : 3.1 + random(fish.id, 3) * 2.8;
                const direction = random(fish.id, 4) > 0.5 ? 1 : -1;

                context.fillStyle = random(fish.id, 5) > 0.5 ? 'rgba(255, 191, 90, 0.92)' : 'rgba(239, 126, 68, 0.92)';
                context.beginPath();
                context.ellipse(x, y, size, size * 0.48, direction > 0 ? 0 : Math.PI, 0, Math.PI * 2);
                context.fill();

                context.beginPath();
                context.moveTo(x - direction * size * 0.85, y);
                context.lineTo(x - direction * size * 1.55, y - size * 0.75);
                context.lineTo(x - direction * size * 1.55, y + size * 0.75);
                context.closePath();
                context.fill();
                fishPositions.push({ x, y, fish });
            }

            context.restore();
            fishAnimationFrame = requestAnimationFrame(draw);
        };

        fishAnimationFrame = requestAnimationFrame(draw);
    }

    function selectFishFromCanvas(event: MouseEvent): void {
        if (!fishCanvas) {
            selectedFish = null;
            fishModalOpen = true;
            return;
        }

        const rect = fishCanvas.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;
        let nearest: FishRecord | null = null;
        let nearestDistance = 15;

        for (const position of fishPositions) {
            const distance = Math.hypot(position.x - x, position.y - y);
            if (distance < nearestDistance) {
                nearestDistance = distance;
                nearest = position.fish;
            }
        }

        selectedFish = nearest;
        fishModalOpen = true;
    }

    onMount(() => {
        let timer: ReturnType<typeof setInterval> | undefined;
        void (async () => {
            try {
                await loadDetail();
                await Promise.all([loadReadings(), loadSimulation(), loadHistory(), loadFish()]);
            } catch (reason) {
                error = reason instanceof Error ? reason.message : 'Data realtime belum tersedia.';
            } finally {
                loading = false;
                setTimeout(startFishCanvas, 80);
            }
        })();

        timer = setInterval(() => {
            if (autoRefresh && !refreshing) void loadReadings().catch(() => undefined);
        }, 15000);

        return () => {
            if (timer) clearInterval(timer);
            if (playTimer) clearInterval(playTimer);
            if (fishAnimationFrame) cancelAnimationFrame(fishAnimationFrame);
        };
    });

    const selectedDevice = $derived(detail?.perangkat.find((device) => device.kode === selectedCode));
    const selectedReading = $derived(selectedDevice ? readingFor(selectedDevice) : undefined);
    const onlineCount = $derived(detail?.perangkat.filter((device) => device.status === 'aktif').length ?? 0);
    const fishCount = $derived(detail?.kolam.jumlah_ikan_awal ?? fishRecords.length ?? simulation?.ikan.jumlah_hidup ?? 0);
</script>

<AppHead title={detail ? `Pantauan ${detail.kolam.kode}` : 'Pantauan kolam'} />

{#if loading}
    <div class="card loading-card">Menyiapkan peta perangkat kolam...</div>
{:else if !detail}
    <div class="note bad">
        <b>Kolam tidak ditemukan.</b>
        <span>{error || 'Periksa kembali alamat kolam yang dibuka.'}</span>
        <Link class="btn sm" href="/kolam">Kembali ke daftar kolam</Link>
    </div>
{:else}
    <div class="detail-breadcrumb">
        <Link href="/kolam">← Semua kolam</Link>
        <span>/</span>
        <b>{detail.kolam.kode}</b>
    </div>

    <section class="pond-detail-hero">
        <div>
            <span class="eyebrow"><i class="status-pulse"></i> LIVE MONITORING</span>
            <h1>{detail.kolam.nama}</h1>
            <p class="lead">Peta kondisi air dan perangkat IoT yang sedang bekerja di kolam ini.</p>
            <div class="hero-meta">
                <span><b>{onlineCount}</b> perangkat aktif</span>
                <span><b>{detail.zona.length}</b> zona air</span>
                <span><b>{detail.kolam.volume_air_m3.toFixed(1)} m³</b> volume</span>
            </div>
        </div>
        <div class="hero-actions">
            <button class="btn pri" disabled={refreshing} onclick={() => void refreshNow()}>
                <span class:spin={refreshing}>↻</span>
                {refreshing ? 'Membaca...' : 'Baca perangkat sekarang'}
            </button>
            <span class="live-note"><i class="status-pulse"></i> Diperbarui {lastUpdated || '—'} · otomatis 15 detik</span>
        </div>
    </section>

    {#if error}
        <div class="note warn detail-alert"><b>Perhatian:</b> {error}</div>
    {/if}

    <section class="sim-console">
        <div class="sim-console-main">
            <div class="sim-play">
                <button class="play-button" class:playing onclick={togglePlayback} aria-label={playing ? 'Jeda simulasi' : 'Putar simulasi'}>
                    {playing ? 'Ⅱ' : '▶'}
                </button>
                <div>
                    <span class="sim-label">SANDBOX SIMULATOR</span>
                    <b>{playing ? 'Simulasi sedang berjalan' : 'Simulasi dijeda'}</b>
                    <small>Waktu virtual · {formatVirtualTime(simulation?.kolam.waktu_sandbox_sekarang ?? detail.kolam.waktu_sandbox_sekarang)}</small>
                </div>
            </div>
            <div class="sim-controls">
                <button class="btn sm" disabled={simBusy} onclick={() => void advanceSimulation()}>+ 10 menit</button>
                <label>Kecepatan
                    <select value={simulationSpeed} onchange={(event) => changeSpeed(Number((event.currentTarget as HTMLSelectElement).value))}>
                        <option value="1">1×</option>
                        <option value="2">2×</option>
                        <option value="5">5×</option>
                        <option value="10">10×</option>
                    </select>
                </label>
            </div>
        </div>
        <div class="sim-console-actions">
            <button class="btn sm" onclick={openEdit}>Edit kolam</button>
            <button class="btn sm soft" onclick={() => (resetOpen = true)}>Perbarui dari awal</button>
        </div>
    </section>

    <div class="monitor-grid single-monitor">
        <section class="card pool-map-card">
            <div class="card-head">
                <div class="grow">
                    <h3>Peta perangkat kolam</h3>
                    <p>Kolam bioflok · ketuk modul atau populasi ikan untuk melihat informasi.</p>
                </div>
                <span class="badge ok"><span class="d"></span>Kolam aktif</span>
            </div>
            <div class="pond-stage">
                <img class="pond-reference-image" src="/images/kolam-sensor-reference.png" alt="Diagram instalasi kolam bioflok" />
                <div class="pond-water">
                    <canvas bind:this={fishCanvas} class="pond-fish-canvas" aria-label="Visual ikan bergerak menurut zona" onclick={selectFishFromCanvas}></canvas>
                    <span class="pond-population"><b>{fishCount.toLocaleString('id-ID')}</b> ikan ditebar</span>
                </div>
                <span class="zone-label zone-top">ZONA ATAS</span>
                <span class="zone-label zone-bottom">ZONA BAWAH</span>
                {#each detail.perangkat as device (device.id)}
                    {@const reading = readingFor(device)}
                    {@const status = statusFor(reading)}
                    {@const nodePosition = devicePosition(device)}
                    <button
                        class="device-node {status}"
                        class:selected={selectedCode === device.kode}
                        style={`left:${nodePosition.left}%;top:${nodePosition.top}%`}
                        aria-label={`Pilih ${device.jenis_perangkat}`}
                        onclick={() => { selectedCode = device.kode; deviceModalOpen = true; }}
                    >
                        <i></i>
                        <span>{device.kode_jenis}</span>
                    </button>
                {/each}
                <div class="pond-compass">N</div>
            </div>
        </section>
    </div>

    <div class="section-title-row">
        <div><h2>Pembacaan terakhir</h2><p>Angka dari modul sensor di kolam ini.</p></div>
        <span class="tiny muted">Sinkron otomatis · {lastUpdated || '—'}</span>
    </div>
    <div class="metric-grid">
        {#each ['suhu', 'do', 'ph', 'amonia', 'level'] as parameter}
            {@const reading = readings.find((item) => item.parameter === parameter)}
            {@const status = statusFor(reading)}
            <div class="metric-card {status}">
                <div class="metric-card-top"><span>{labels[parameter]}</span><i class="status-pulse"></i></div>
                <strong>{formatValue(reading?.nilai ?? null)}</strong>
                <small>{reading?.satuan ?? 'belum ada data'} · {statusLabel(status)}</small>
            </div>
        {/each}
    </div>

    <section class="history-section">
        <div class="section-title-row">
            <div><h2>Riwayat pertumbuhan kolam</h2><p>Perubahan bobot rata-rata dan biomassa dari waktu virtual.</p></div>
            <span class="tiny muted">{history.length} hari terekam</span>
        </div>
        {#if history.length === 0}
            <div class="card history-empty">Riwayat akan muncul setelah simulasi melewati satu hari virtual.</div>
        {:else}
            <div class="history-layout">
                <div class="card history-chart">
                    <div class="history-chart-head"><b>Bobot rata-rata ikan</b><span>gram</span></div>
                    <div class="history-bars">
                        {#each history.slice(-14) as row}
                            <div class="history-bar-wrap" title={`${row.tanggal}: ${row.bobot_rata_g} g`}>
                                <span class="history-bar" style={`height:${Math.max(8, Math.min(100, row.bobot_rata_g))}%`}></span>
                                <small>{row.tanggal.slice(5)}</small>
                            </div>
                        {/each}
                    </div>
                </div>
                <div class="card death-card">
                    <div class="card-head"><div class="grow"><h3>Ikan mati</h3><p>Terpantau dari proses harian.</p></div><strong class="death-total">{simulation?.ikan.jumlah_mati ?? 0}</strong></div>
                    {#if deathCauses.length === 0}
                        <div class="death-clear">Belum ada kematian tercatat.</div>
                    {:else}
                        {#each deathCauses as cause}
                            <div class="death-row"><span><i></i>{cause.penyebab.replaceAll('_', ' ')}</span><b>{cause.jumlah}</b></div>
                        {/each}
                    {/if}
                </div>
            </div>
        {/if}
    </section>
{/if}

{#if deviceModalOpen && selectedDevice}
    {@const deviceReading = selectedReading}
    {@const deviceStatus = statusFor(deviceReading)}
    <div class="pond-modal" role="presentation">
        <button class="pond-modal-backdrop" aria-label="Tutup informasi perangkat" onclick={() => (deviceModalOpen = false)}></button>
        <dialog open class="pond-modal-box" aria-label={`Informasi ${selectedDevice.jenis_perangkat}`}>
            <button class="pond-modal-close" aria-label="Tutup" onclick={() => (deviceModalOpen = false)}>×</button>
            <span class="modal-kicker"><i class="device-led {deviceStatus}"><i></i></i> MODUL PERANGKAT</span>
            <h2>{selectedDevice.jenis_perangkat}</h2>
            <p class="modal-subtitle">{selectedDevice.kode} · {selectedDevice.zona_id ? 'Terpasang di zona air' : 'Terpasang di area kolam'}</p>
            <div class="modal-reading">
                <span>Pembacaan terakhir</span>
                <strong>{formatValue(deviceReading?.nilai ?? null, deviceReading?.satuan)}</strong>
                <small>{deviceReading ? `${deviceReading.waktu_ukur} · ${statusLabel(deviceStatus)}` : 'Belum ada data dari perangkat ini'}</small>
            </div>
            <div class="modal-info-grid">
                <span><small>Status</small><b>{selectedDevice.status === 'aktif' ? 'Aktif & terhubung' : selectedDevice.status}</b></span>
                <span><small>Baterai</small><b>{selectedDevice.baterai_persen}%</b></span>
                <span><small>Interval</small><b>{selectedDevice.interval_menit} menit</b></span>
                <span><small>Kualitas</small><b>{deviceReading?.kualitas_data ?? 'menunggu'}</b></span>
            </div>
            <button class="btn pri block" onclick={() => { deviceModalOpen = false; void refreshNow(); }}>Baca perangkat ini sekarang</button>
        </dialog>
    </div>
{/if}

{#if fishModalOpen}
    <div class="pond-modal" role="presentation">
        <button class="pond-modal-backdrop" aria-label="Tutup informasi ikan" onclick={() => (fishModalOpen = false)}></button>
        <dialog open class="pond-modal-box fish-modal-box" aria-label="Informasi populasi ikan">
            <button class="pond-modal-close" aria-label="Tutup" onclick={() => (fishModalOpen = false)}>×</button>
            <span class="modal-kicker"><i class="fish-modal-icon">›</i> {selectedFish ? selectedFish.kode : 'POPULASI KOLAM'}</span>
            <h2>{selectedFish ? 'Detail ikan terpantau' : 'Ikan lele di kolam'}</h2>
            <p class="modal-subtitle">{selectedFish ? 'Posisi dan kondisi ikan berasal dari hasil pergerakan sandbox.' : 'Visual kolam menampilkan populasi hidup hasil algoritma pergerakan.'}</p>
            {#if selectedFish}
                <div class="fish-count-panel"><strong>{formatValue(selectedFish.bobot_g, 'g')}</strong><span>Zona {selectedFish.zona?.nama ?? '—'} · stres {selectedFish.stres}%</span></div>
            {:else}
                <div class="fish-count-panel"><strong>{fishCount.toLocaleString('id-ID')}</strong><span>ikan ditebar</span></div>
            {/if}
            <div class="modal-info-grid">
                <span><small>Zona aktif</small><b>{detail?.zona.length ?? 0} zona</b></span>
                <span><small>Bobot awal rata-rata</small><b>{detail?.kolam.bobot_awal_rata_g ?? 0} g</b></span>
                <span><small>Air kolam</small><b>{detail?.kolam.volume_air_m3.toFixed(1)} m³</b></span>
                {#if selectedFish}
                    <span><small>Lambung / panjang</small><b>{selectedFish.isi_lambung_persen.toFixed(1)}% · {selectedFish.panjang_cm.toFixed(1)} cm</b></span>
                {:else}
                    <span><small>Status</small><b>{fishRecords.length.toLocaleString('id-ID')} ikan hidup</b></span>
                {/if}
            </div>
            <button class="btn block" onclick={() => (fishModalOpen = false)}>Kembali ke peta kolam</button>
        </dialog>
    </div>
{/if}

{#if editOpen}
    <div class="pond-modal" role="presentation">
        <button class="pond-modal-backdrop" aria-label="Tutup edit kolam" onclick={() => (editOpen = false)}></button>
        <dialog open class="pond-modal-box" aria-label="Edit kolam">
            <button class="pond-modal-close" aria-label="Tutup" onclick={() => (editOpen = false)}>×</button>
            <span class="modal-kicker">PENGATURAN KOLAM</span>
            <h2>Edit kolam</h2>
            <p class="modal-subtitle">Perubahan ukuran memengaruhi volume dan perhitungan lingkungan.</p>
            <div class="edit-form">
                <label>Nama kolam<input bind:value={editForm.nama} /></label>
                <label>Panjang (m)<input type="number" step="0.1" min="0.1" bind:value={editForm.panjang_m} /></label>
                <label>Lebar (m)<input type="number" step="0.1" min="0.1" bind:value={editForm.lebar_m} /></label>
                <label>Tinggi air (m)<input type="number" step="0.1" min="0.1" bind:value={editForm.tinggi_air_m} /></label>
                <label>Target panen (g)<input type="number" min="1" bind:value={editForm.bobot_target_panen_g} /></label>
                <label>Kecepatan waktu<input type="number" min="1" bind:value={editForm.kecepatan_waktu} /></label>
            </div>
            <button class="btn pri block" disabled={editBusy} onclick={() => void saveEdit()}>{editBusy ? 'Menyimpan...' : 'Simpan perubahan'}</button>
        </dialog>
    </div>
{/if}

{#if resetOpen}
    <div class="pond-modal" role="presentation">
        <button class="pond-modal-backdrop" aria-label="Tutup reset kolam" onclick={() => (resetOpen = false)}></button>
        <dialog open class="pond-modal-box" aria-label="Konfirmasi perbarui kolam">
            <button class="pond-modal-close" aria-label="Tutup" onclick={() => (resetOpen = false)}>×</button>
            <span class="modal-kicker">MULAI SIKLUS BARU</span>
            <h2>Perbarui kolam dari awal?</h2>
            <p class="modal-subtitle">Semua ikan, riwayat sensor, pertumbuhan, dan waktu sandbox akan dikembalikan ke kondisi awal. Tindakan ini tidak dapat dibatalkan.</p>
            <div class="reset-warning">Data lama akan dihapus, lalu ikan ditebar ulang sesuai jumlah awal.</div>
            <div class="modal-actions"><button class="btn" onclick={() => (resetOpen = false)}>Batal</button><button class="btn pri" disabled={editBusy} onclick={() => void resetPond()}>{editBusy ? 'Memulai...' : 'Ya, mulai ulang'}</button></div>
        </dialog>
    </div>
{/if}
