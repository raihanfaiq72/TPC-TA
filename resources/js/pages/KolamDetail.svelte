<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FishIcon from '@/components/FishIcon.svelte';
    import HarvestPlanMockup from '@/components/HarvestPlanMockup.svelte';
    import SensorIcon from '@/components/SensorIcon.svelte';
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
    type SensorHistoryRow = {
        waktu: string;
        zona: string | null;
        parameter: string;
        nilai: number;
    };
    type ChartRange = '1m' | '1h' | '1d' | '1w' | '1mo' | '1y';
    const chartRanges: Array<{ value: ChartRange; label: string }> = [
        { value: '1m', label: '1 menit' },
        { value: '1h', label: '1 jam' },
        { value: '1d', label: '1 hari' },
        { value: '1w', label: '1 minggu' },
        { value: '1mo', label: '1 bulan' },
        { value: '1y', label: '1 tahun' },
    ];
    type ChartPoint = { date: string; x: number; y: number; value: number };
    type ChartSeries = {
        key: string;
        label: string;
        color: string;
        dashed: boolean;
        unit: string;
        latest: number | null;
        points: ChartPoint[];
    };
    type ChartModel = {
        dates: string[];
        series: ChartSeries[];
        xLabels: Array<{ x: number; label: string }>;
        xPositions: number[];
        sampleCount: number;
    };
    type SamplingRow = {
        id: number;
        tanggal: string;
        jumlah_sampel: number;
        jumlah_mati: number;
        bobot_total_sampel_g: number;
        bobot_rata_g: number;
        panjang_rata_cm: number;
    };
    type SamplingChartPoint = {
        tanggal: string;
        x: number;
        y: number;
        value: number;
    };
    type SamplingChartModel = {
        points: SamplingChartPoint[];
        xLabels: Array<{ x: number; label: string }>;
        yLabels: Array<{ y: number; label: string }>;
        path: string;
        targetY: number | null;
    };
    type DeathCause = { penyebab: string; jumlah: number };
    type SimulationStatus = {
        kolam: {
            waktu_sandbox_sekarang: string;
            waktu_sandbox_sinkron?: string;
            kecepatan_waktu: number;
        };
        ikan: { jumlah_awal: number; jumlah_hidup: number; jumlah_mati: number; biomassa_hidup_g: number };
    };
    type Recommendation = {
        status: string;
        skor_kesiapan: number;
        alasan: string;
        estimasi_hari_ke_target?: number | null;
    };
    type TickResponse = {
        proses_harian?: boolean;
        pakan_kejadian?: number;
        pakan_g?: number;
        pakan_events?: FeedEvent[];
        alert_baru?: number;
        selesai?: boolean;
        menit_dijalankan?: number;
        waktu_sekarang?: string;
        waktu_sinkron?: string;
    };
    type FeedEvent = { waktu: string; gram: number };
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
    let sensorHistory = $state<SensorHistoryRow[]>([]);
    let samplingRows = $state<SamplingRow[]>([]);
    let historyRequest = 0;
    let deathCauses = $state<DeathCause[]>([]);
    let hiddenChartSeries = $state<string[]>([]);
    let chartHoverSeriesKey = $state<string | null>(null);
    let chartHoverPointDate = $state<string | null>(null);
    let chartRange = $state<ChartRange>('1d');
    const chartRangeLabel = $derived(
        chartRanges.find((range) => range.value === chartRange)?.label ?? '1 hari',
    );
    let simulation = $state<SimulationStatus | null>(null);
    let clockNow = $state(Date.now());
    let recommendation = $state<Recommendation | null>(null);
    let playing = $state(false);
    let simulationSpeed = $state(1);
    let feedActive = $state(false);
    let feedLabel = $state('PAKAN DIBERIKAN');
    let feedQueue = $state<FeedEvent[]>([]);
    let feedTimer: ReturnType<typeof setTimeout> | undefined;
    let simBusy = $state(false);
    let simStepTarget = $state(0);
    let simStepDone = $state(0);
    let deviceToggleBusy = $state<number | null>(null);
    let editOpen = $state(false);
    let resetOpen = $state(false);
    let editBusy = $state(false);
    let editError = $state('');
    let editForm = $state({ nama: '', panjang_m: 0, lebar_m: 0, tinggi_air_m: 0, kecepatan_waktu: 60, bobot_target_panen_g: 100 });
    const editVolume = $derived(
        Math.max(0, editForm.panjang_m * editForm.lebar_m * editForm.tinggi_air_m),
    );
    let playTimer: ReturnType<typeof setInterval> | undefined;
    let clockTimer: ReturnType<typeof setInterval> | undefined;
    let fishCanvas = $state<HTMLCanvasElement>();
    let fishAnimationFrame: number | undefined;
    let fishAnimationStarted = false;
    let fishRecords = $state<FishRecord[]>([]);
    let selectedFish = $state<FishRecord | null>(null);
    let fishPositions: Array<{ x: number; y: number; fish: FishRecord }> = [];
    let error = $state('');
    let lastUpdated = $state('');

    const labels: Record<string, string> = {
        suhu: 'Suhu',
        ph: 'pH',
        do: 'Oksigen terlarut',
        amonia: 'Amonia',
        level: 'Level air',
        feeder: 'Feeder',
    };

    const shortLabels: Record<string, string> = {
        suhu: 'Suhu',
        ph: 'pH',
        do: 'DO',
        amonia: 'NH₃',
        level: 'Level',
        feeder: 'Feeder',
    };

    function deviceLabel(device: Device): string {
        const code = device.kode_jenis.toLowerCase();

        return shortLabels[code] ?? device.jenis_perangkat;
    }

    function formatValue(value: number | null, unit = ''): string {
        return value === null ? '—' : `${value.toLocaleString('id-ID', { maximumFractionDigits: 2 })}${unit ? ` ${unit}` : ''}`;
    }

    function readingFor(device: Device): Reading | undefined {
        return readings.find((reading) => reading.perangkat === device.kode);
    }

    function statusFor(reading: Reading | undefined): 'ok' | 'warn' | 'bad' {
        if (!reading || reading.kualitas_data === 'gagal') return 'bad';

        const value = reading.nilai;

        if (value === null) return 'bad';
        if (reading.kualitas_data === 'outlier') return 'warn';

        if (reading.parameter === 'do') {
            if (value < 3) return 'bad';
            if (value < 5) return 'warn';
        }

        if (reading.parameter === 'amonia') {
            if (value > 1.5) return 'bad';
            if (value > 0.5) return 'warn';
        }

        if (reading.parameter === 'ph') {
            if (value < 5 || value > 9) return 'bad';
            if (value < 6.5 || value > 8.5) return 'warn';
        }

        if (reading.parameter === 'suhu') {
            if (value < 22 || value > 34) return 'bad';
            if (value < 26 || value > 31) return 'warn';
        }

        return 'ok';
    }

    function statusLabel(status: 'ok' | 'warn' | 'bad'): string {
        return status === 'ok' ? 'Normal' : status === 'warn' ? 'Perlu perhatian' : 'Belum terbaca';
    }

    function devicePosition(device: Device): { left: number; top: number } {
        const zone = detail?.zona.find((item) => item.id === device.zona_id);
        const zoneName = zone?.nama ?? '';
        const code = device.kode_jenis.toLowerCase();

        if (code === 'feeder') return { left: 86, top: 14 };
        if (code === 'level') return { left: 14, top: 86 };

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

    async function loadRecommendation(): Promise<void> {
        try {
            const response = await fetch(`/api/kolam/${id}/rekomendasi`, { headers: { Accept: 'application/json' } });

            if (!response.ok) return;

            recommendation = (await response.json()) as Recommendation | null;
        } catch {
            recommendation = null;
        }
    }

    function runPreset(days: number): void {
        if (simBusy) return;

        void advanceSimulation(days * 1440);
    }

    function formatMinuteSpan(total: number): string {
        const days = Math.floor(total / 1440);
        const hours = Math.floor((total % 1440) / 60);
        const minutes = Math.round(total % 60);

        return [
            days > 0 ? `${days} hari` : '',
            hours > 0 ? `${hours} jam` : '',
            minutes > 0 ? `${minutes} menit` : '',
        ].filter(Boolean).join(' ') || '0 menit';
    }

    async function seekVirtualDay(event: MouseEvent): Promise<void> {
        const now = parseVirtualDate(virtualTimeValue);

        if (!now) return;

        const target = event.currentTarget as HTMLElement;
        const rect = target.getBoundingClientRect();
        const targetMinutes = Math.round((((event.clientX - rect.left) / rect.width) * 1440) / 10) * 10;
        const currentMinutes = now.getHours() * 60 + now.getMinutes();
        const delta = targetMinutes - currentMinutes;

        if (delta <= 0) {
            say('Pilih waktu setelah waktu virtual saat ini');
            return;
        }

        await advanceSimulation(delta);
    }

    function enqueueFeedEvents(events: FeedEvent[]): void {
        if (events.length === 0) return;

        feedQueue.push(...events);

        if (!feedActive) {
            drainFeedQueue();
        }
    }

    function drainFeedQueue(): void {
        if (feedActive) return;

        const next = feedQueue.shift();

        if (!next) {
            feedActive = false;
            return;
        }

        feedActive = true;
        const feedTime = next.waktu ? parseVirtualDate(next.waktu) : null;
        const jamPakan = feedTime
            ? feedTime.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
            : '';
        feedLabel = `PAKAN ${jamPakan}${jamPakan ? ' · ' : ''}${next.gram.toLocaleString('id-ID')} g`;

        if (feedTimer) {
            clearTimeout(feedTimer);
        }

        feedTimer = setTimeout(() => {
            feedActive = false;
            feedTimer = setTimeout(drainFeedQueue, 180);
        }, 1000);
    }

    async function loadHistory(): Promise<void> {
        const requestId = ++historyRequest;
        const [historyResponse, sensorResponse] = await Promise.all([
            fetch(`/api/kolam/${id}/ikan/riwayat`, { headers: { Accept: 'application/json' } }),
            fetch(`/api/kolam/${id}/sensor/grafik?range=${chartRange}`, { headers: { Accept: 'application/json' } }),
        ]);

        if (!historyResponse.ok) throw new Error('Riwayat pertumbuhan belum bisa dibaca.');

        const payload = (await historyResponse.json()) as { riwayat: HistoryRow[]; kematian: DeathCause[] };
        const sensorRows = sensorResponse.ok
            ? (await sensorResponse.json()) as SensorHistoryRow[]
            : [];

        if (requestId !== historyRequest) return;

        history = payload.riwayat;
        deathCauses = payload.kematian;
        sensorHistory = sensorRows;
    }

    async function loadSampling(): Promise<void> {
        try {
            const response = await fetch(`/api/kolam/${id}/sampling`, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                samplingRows = [];
                return;
            }

            samplingRows = (await response.json()) as SamplingRow[];
        } catch {
            samplingRows = [];
        }
    }

    function changeChartRange(range: ChartRange): void {
        if (chartRange === range) return;

        chartRange = range;
        clearChartHover();
        void loadHistory().catch((reason) => {
            error = reason instanceof Error ? reason.message : 'Riwayat grafik belum bisa dibaca.';
        });
    }

    async function loadFish(): Promise<void> {
        const response = await fetch(`/api/kolam/${id}/ikan`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Data populasi ikan belum bisa dibaca.');
        const payload = (await response.json()) as { ikan: FishRecord[] };
        fishRecords = payload.ikan.filter((fish) => fish.status === 'hidup');
    }

    const SIM_CHUNK_MENIT = 600;
    const MAX_SIM_PASSES = 1000;

    async function advanceSimulation(minutes?: number): Promise<void> {
        if (simBusy) return;
        simBusy = true;
        error = '';
        const target = minutes ?? 10;
        let remaining = target;
        simStepTarget = target;
        simStepDone = 0;

        try {
            let alertCount = 0;
            let dailyProcess = false;
            let passes = 0;

            // Kirim per batch supaya satu request tidak menabrak timeout server.
            // Backend juga membatasi tick dan waktu eksekusi per request.
            while (remaining > 0 && passes < MAX_SIM_PASSES) {
                // Percepatan memperkecil batch agar jam virtual bergerak
                // lebih sering dan terasa mengikuti kecepatan yang dipilih.
                const batchMenit = Math.min(
                    remaining,
                    Math.max(120, Math.round(SIM_CHUNK_MENIT / Math.max(1, simulationSpeed))),
                );
                const response = await fetch(`/api/kolam/${id}/simulasi/tick`, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ menit: batchMenit }),
                });
                if (!response.ok) throw new Error('Simulasi tidak dapat dilanjutkan.');

                const payload = (await response.json()) as TickResponse;
                const advanced = Math.max(0, payload.menit_dijalankan ?? 0);

                if (advanced <= 0) break;

                remaining = Math.max(0, remaining - advanced);
                simStepDone = Math.min(target, target - remaining);
                alertCount += payload.alert_baru ?? 0;
                dailyProcess = dailyProcess || Boolean(payload.proses_harian);
                passes++;

                if (payload.pakan_events?.length) {
                    enqueueFeedEvents(payload.pakan_events);
                } else if ((payload.pakan_kejadian ?? 0) > 0) {
                    enqueueFeedEvents([{
                        waktu: payload.waktu_sekarang ?? virtualTimeValue ?? '',
                        gram: payload.pakan_g ?? 0,
                    }]);
                }

                // Perbarui jam virtual langsung dari response batch supaya UI
                // bergerak selama simulasi panjang, tanpa menunggu semua batch.
                if (payload.waktu_sekarang && simulation) {
                    simulation = {
                        ...simulation,
                        kolam: {
                            ...simulation.kolam,
                            waktu_sandbox_sekarang: payload.waktu_sekarang,
                            waktu_sandbox_sinkron: payload.waktu_sinkron
                                ?? toVirtualDateTimeString(new Date()),
                        },
                    };
                }

                // Sensor ikut diperbarui di tengah langkah besar agar kartu
                // pembacaan dan animasi sensor tidak terlihat beku.
                void loadReadings().catch(() => undefined);

                if (payload.selesai !== false && remaining <= 0) break;
            }

            await Promise.all([loadSimulation(), loadReadings(), loadFish()]);
            await loadHistory();

            if (dailyProcess) {
                await loadRecommendation();
            }

            if (alertCount > 0) {
                window.dispatchEvent(new Event('aqua:refresh-alerts'));
            }

            if (remaining > 0) {
                say(`Simulasi berhenti pada batas langkah; sisa ${formatMinuteSpan(remaining)} virtual`);
            }
        } catch (reason) {
            error = reason instanceof Error ? reason.message : 'Simulasi tidak dapat dilanjutkan.';
            playing = false;
        } finally {
            simBusy = false;
            simStepTarget = 0;
            simStepDone = 0;
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
        editError = '';
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
        editError = '';
        try {
            const response = await fetch(`/api/kolam/${id}`, {
                method: 'PUT',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(editForm),
            });

            const payload = (await response.json().catch(() => null)) as
                | { message?: string; errors?: Record<string, string[]> }
                | null;

            if (!response.ok) {
                const messages = payload?.errors
                    ? Object.values(payload.errors).flat().join(' ')
                    : payload?.message;

                throw new Error(messages ?? 'Perubahan kolam tidak dapat disimpan.');
            }

            editOpen = false;
            await Promise.all([loadDetail(), loadSimulation()]);
            say('Data kolam berhasil diperbarui');
        } catch (reason) {
            editError = reason instanceof Error ? reason.message : 'Perubahan kolam tidak dapat disimpan.';
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
            await Promise.all([loadDetail(), loadSimulation(), loadReadings(), loadHistory(), loadFish(), loadSampling()]);
            say('Kolam kembali ke kondisi awal');
        } catch (reason) {
            error = reason instanceof Error ? reason.message : 'Kolam tidak dapat direset.';
        } finally {
            editBusy = false;
        }
    }

    function parseVirtualDate(value: string | undefined): Date | null {
        if (!value) return null;

        const normalized = value.includes('T') ? value : value.replace(' ', 'T');
        const hasTimezone = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(normalized);
        const candidate = /^\d{4}-\d{2}-\d{2}$/.test(normalized)
            ? `${normalized}T00:00:00Z`
            : hasTimezone
                ? normalized
                : `${normalized}Z`;
        const parsed = new Date(candidate);

        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function toVirtualDateTimeString(value: Date): string {
        const pad = (part: number): string => String(part).padStart(2, '0');

        return [
            `${value.getUTCFullYear()}-${pad(value.getUTCMonth() + 1)}-${pad(value.getUTCDate())}`,
            `${pad(value.getUTCHours())}:${pad(value.getUTCMinutes())}:${pad(value.getUTCSeconds())}`,
        ].join(' ');
    }

    function chartPath(points: ChartPoint[]): string {
        return points
            .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`)
            .join(' ');
    }

    function formatChartValue(value: number | null, unit: string): string {
        if (value === null) return '—';

        return `${value.toLocaleString('id-ID', { maximumFractionDigits: 2 })}${unit ? ` ${unit}` : ''}`;
    }

    function chartDateLabel(value: string, range: ChartRange): string {
        const parsed = parseVirtualDate(value);

        if (!parsed) return value;

        if (range === '1m' || range === '1h') {
            return parsed.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            });
        }

        if (range === '1d') {
            return parsed.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            });
        }

        if (range === '1w') {
            return parsed.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
            });
        }

        return parsed.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: range === '1y' ? '2-digit' : undefined,
        });
    }

    function chartTooltipDate(value: string): string {
        const parsed = parseVirtualDate(value);

        if (!parsed) return value;

        return parsed.toLocaleString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function formatVirtualTime(value: string | undefined): string {
        const parsed = parseVirtualDate(value);

        if (!parsed) return '—';

        return parsed.toLocaleString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    }

    function formatVirtualDate(value: string | undefined): string {
        const parsed = parseVirtualDate(value);

        if (!parsed) return '—';

        return parsed.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    }

    async function refreshNow(): Promise<void> {
        refreshing = true;
        error = '';
        try {
            const response = await fetch(`/api/kolam/${id}/sensor/baca`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    waktu: virtualTimeValue ?? toVirtualDateTimeString(new Date()),
                }),
            });
            if (!response.ok) throw new Error('Perangkat belum merespons.');
            await loadReadings();
            await loadHistory();
            say('Data perangkat berhasil diperbarui');
        } catch (reason) {
            error = reason instanceof Error ? reason.message : 'Perangkat belum merespons.';
        } finally {
            refreshing = false;
        }
    }

    async function toggleDevice(device: Device): Promise<void> {
        if (deviceToggleBusy !== null) return;

        deviceToggleBusy = device.id;
        const nextStatus = device.status === 'aktif' ? 'mati' : 'aktif';

        try {
            const response = await fetch(`/api/kolam/${id}/sensor/${device.id}`, {
                method: 'PATCH',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: nextStatus }),
            });
            if (!response.ok) throw new Error('Status sensor tidak dapat diubah.');

            await Promise.all([loadDetail(), loadReadings()]);
            say(nextStatus === 'aktif' ? 'Sensor diaktifkan' : 'Sensor dimatikan');
        } catch (reason) {
            error = reason instanceof Error ? reason.message : 'Status sensor tidak dapat diubah.';
        } finally {
            deviceToggleBusy = null;
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
            const cy = rect.height * 0.5;
            const rx = rect.width * 0.44;
            const ry = rect.height * 0.4;

            context.save();
            context.beginPath();
            context.rect(0, 0, rect.width, rect.height);
            context.clip();

            fishPositions = [];

            for (let index = 0; index < fishRecords.length; index += 1) {
                const fish = fishRecords[index];
                const phase = random(fish.id, 1) * Math.PI * 2;
                const swimSpeed = playing
                    ? 0.0011 + simulationSpeed * 0.00035
                    : 0.00035;

                // Sebar setiap ikan secara acak di seluruh area air.
                // Jangan memakai zona_id sebagai posisi Y karena itu membuat
                // ikan terlihat terpisah menjadi dua gerombolan atas/bawah.
                const angle = random(fish.id, 2) * Math.PI * 2;
                const radial = Math.sqrt(random(fish.id, 6)) * 0.9;
                const baseX = cx + Math.cos(angle) * rx * radial;
                const baseY = cy + Math.sin(angle) * ry * radial;

                const swimX = Math.sin(time * swimSpeed + phase);
                const swimY = Math.cos(time * swimSpeed * 0.73 + phase * 1.7);
                const driftX = rx * (0.07 + random(fish.id, 7) * 0.08);
                const driftY = ry * (0.05 + random(fish.id, 8) * 0.08);
                const x = baseX + swimX * driftX;
                const y = baseY + swimY * driftY;
                const initialWeight = Math.max(0.0001, detail?.kolam.bobot_awal_rata_g ?? 1);
                const growthRatio = Math.min(2.4, Math.max(0.55, Math.sqrt(fish.bobot_g / initialWeight)));
                const baseSize = fishRecords.length > 300
                    ? 2.2
                    : 4.2;
                const size = baseSize * growthRatio;
                const direction = Math.cos(time * swimSpeed + phase) >= 0 ? 1 : -1;
                const tail = Math.sin(time * 0.012 + phase) * size * 0.2;
                const fill = '#9bc9f5';
                const outline = '#0b1b2d';

                context.save();
                context.translate(x, y);
                context.scale(direction, 1);
                context.fillStyle = fill;
                context.strokeStyle = outline;
                context.lineWidth = Math.max(0.9, size * 0.16);

                // Ekor
                context.beginPath();
                context.moveTo(-size * 1.2, 0);
                context.lineTo(-size * 1.95, -size * 0.7 + tail);
                context.lineTo(-size * 1.95, size * 0.7 - tail);
                context.closePath();
                context.fill();
                context.stroke();

                // Badan
                context.beginPath();
                context.ellipse(0, 0, size * 1.35, size * 0.58, 0, 0, Math.PI * 2);
                context.fill();
                context.stroke();

                // Sirip punggung
                context.beginPath();
                context.moveTo(-size * 0.2, -size * 0.5);
                context.quadraticCurveTo(size * 0.3, -size * 1.16, size * 0.9, -size * 0.45);
                context.closePath();
                context.fill();
                context.stroke();

                // Sirip bawah
                context.beginPath();
                context.moveTo(-size * 0.15, size * 0.55);
                context.quadraticCurveTo(size * 0.2, size * 1.02, size * 0.58, size * 0.48);
                context.closePath();
                context.fill();
                context.stroke();

                // Insang
                context.beginPath();
                context.moveTo(size * 0.45, -size * 0.32);
                context.quadraticCurveTo(size * 0.7, 0, size * 0.45, size * 0.32);
                context.stroke();

                // Mata
                context.fillStyle = outline;
                context.beginPath();
                context.arc(size * 0.78, -size * 0.18, Math.max(0.65, size * 0.09), 0, Math.PI * 2);
                context.fill();

                context.restore();

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
        clockTimer = setInterval(() => {
            clockNow = Date.now();
        }, 1000);

        void (async () => {
            try {
                await loadDetail();

                if (new URLSearchParams(window.location.search).has('edit')) {
                    openEdit();
                }

                await Promise.all([loadReadings(), loadSimulation(), loadHistory(), loadRecommendation(), loadFish(), loadSampling()]);
            } catch (reason) {
                error = reason instanceof Error ? reason.message : 'Data realtime belum tersedia.';
            } finally {
                loading = false;
                setTimeout(startFishCanvas, 80);
            }
        })();

        timer = setInterval(() => {
            if (autoRefresh && !refreshing && !simBusy) {
                void loadReadings().catch(() => undefined);
                void loadSimulation().catch(() => undefined);
                void loadHistory().catch(() => undefined);
                void loadSampling().catch(() => undefined);
            }
        }, 15000);

        return () => {
            if (timer) clearInterval(timer);
            if (clockTimer) clearInterval(clockTimer);
            if (playTimer) clearInterval(playTimer);
            if (feedTimer) clearTimeout(feedTimer);
            if (fishAnimationFrame) cancelAnimationFrame(fishAnimationFrame);
            feedQueue = [];
            feedActive = false;
        };
    });

    const selectedDevice = $derived(detail?.perangkat.find((device) => device.kode === selectedCode));
    const selectedReading = $derived(selectedDevice ? readingFor(selectedDevice) : undefined);
    const latestReadingTime = $derived.by(() => {
        let latest: { waktu: string; timestamp: number } | null = null;

        for (const reading of readings) {
            const parsed = parseVirtualDate(reading.waktu_ukur);

            if (parsed !== null && (latest === null || parsed.getTime() > latest.timestamp)) {
                latest = { waktu: reading.waktu_ukur, timestamp: parsed.getTime() };
            }
        }

        return latest?.waktu ?? null;
    });
    const onlineCount = $derived(detail?.perangkat.filter((device) => device.status === 'aktif').length ?? 0);
    const offlineCount = $derived(Math.max(0, (detail?.perangkat.length ?? 0) - onlineCount));
    const fishCount = $derived(simulation?.ikan.jumlah_hidup ?? fishRecords.length ?? detail?.kolam.jumlah_ikan_awal ?? 0);
    const doValue = $derived(readings.find((reading) => reading.parameter === 'do')?.nilai ?? null);
    const suhuValue = $derived(readings.find((reading) => reading.parameter === 'suhu')?.nilai ?? null);
    const phValue = $derived(readings.find((reading) => reading.parameter === 'ph')?.nilai ?? null);
    const amoniaValue = $derived(readings.find((reading) => reading.parameter === 'amonia')?.nilai ?? null);
    const harvestStatus = $derived(recommendation?.status ?? 'belum siap');
    const harvestTone = $derived(
        harvestStatus === 'siap panen' ? 'ready' : harvestStatus === 'hampir siap' ? 'near' : 'wait',
    );
    const virtualTimeValue = $derived.by((): string | undefined => {
        const baseValue = simulation?.kolam.waktu_sandbox_sekarang
            ?? detail?.kolam.waktu_sandbox_sekarang;
        const base = parseVirtualDate(baseValue);

        if (!base) return baseValue;

        const sinkron = parseVirtualDate(simulation?.kolam.waktu_sandbox_sinkron);

        if (!sinkron) return baseValue;

        const elapsed = Math.max(0, clockNow - sinkron.getTime());

        return toVirtualDateTimeString(new Date(base.getTime() + elapsed));
    });
    const virtualDateValue = $derived(virtualTimeValue?.slice(0, 10));
    const virtualClock = $derived.by(() => {
        const parsed = parseVirtualDate(virtualTimeValue);

        if (!parsed) {
            return { date: '—', hour: '--', minute: '--', second: '--' };
        }

        return {
            date: formatVirtualDate(virtualTimeValue),
            hour: String(parsed.getHours()).padStart(2, '0'),
            minute: String(parsed.getMinutes()).padStart(2, '0'),
            second: String(parsed.getSeconds()).padStart(2, '0'),
        };
    });
    const simStepVisible = $derived(simBusy && simStepTarget >= 1440);
    const simStepPercent = $derived(
        simStepTarget > 0 ? Math.min(100, Math.round((simStepDone / simStepTarget) * 100)) : 0,
    );
    const virtualDayProgress = $derived.by(() => {
        const now = parseVirtualDate(virtualTimeValue);

        if (!now) return 0;

        const seconds = now.getHours() * 3600
            + now.getMinutes() * 60
            + now.getSeconds();

        return Math.min(100, Math.max(0, (seconds / 86400) * 100));
    });

    const CHART_WIDTH = 960;
    const CHART_HEIGHT = 300;
    const CHART_PAD = { left: 34, right: 18, top: 16, bottom: 32 };

    const chartModel = $derived.by((): ChartModel => {
        const liveTimestamp = virtualTimeValue;
        const rangeSeconds: Record<ChartRange, number> = {
            '1m': 60,
            '1h': 3600,
            '1d': 86400,
            '1w': 604800,
            '1mo': 2592000,
            '1y': 31536000,
        };
        const bucketSeconds: Record<ChartRange, number> = {
            '1m': 10,
            '1h': 300,
            '1d': 3600,
            '1w': 86400,
            '1mo': 86400,
            '1y': 2592000,
        };
        const now = parseVirtualDate(liveTimestamp) ?? new Date();
        const rangeStart = now.getTime() - rangeSeconds[chartRange] * 1000;
        const sensorRangeStart = rangeStart - bucketSeconds[chartRange] * 1000;

        const historyEntries = history
            .map((row) => ({
                key: `${row.tanggal.slice(0, 10)} 00:00:00`,
                row,
            }))
            .filter(({ key }) => {
                const parsed = parseVirtualDate(key);

                return parsed !== null
                    && parsed.getTime() >= rangeStart
                    && parsed.getTime() <= now.getTime();
            });

        const historyDates = historyEntries.map((entry) => entry.key);
        const sensorRows = sensorHistory.filter((row) => {
            const parsed = parseVirtualDate(row.waktu);

            return parsed !== null
                && parsed.getTime() >= sensorRangeStart
                && parsed.getTime() <= now.getTime();
        });
        const sensorTimes = sensorRows.map((row) => row.waktu);
        const dates = Array.from(new Set([
            ...historyDates,
            ...sensorTimes,
            ...(liveTimestamp ? [liveTimestamp] : []),
        ])).sort();

        if (dates.length === 0) {
            return { dates: [], series: [], xLabels: [], xPositions: [], sampleCount: 0 };
        }

        const chartLeft = CHART_PAD.left;
        const chartRight = CHART_WIDTH - CHART_PAD.right;
        const chartTop = CHART_PAD.top;
        const chartBottom = CHART_HEIGHT - CHART_PAD.bottom;
        const chartWidth = chartRight - chartLeft;
        const chartHeight = chartBottom - chartTop;

        const xFor = (index: number): number => dates.length === 1
            ? chartLeft + chartWidth / 2
            : chartLeft + (index / (dates.length - 1)) * chartWidth;

        const sensorValues = (
            parameter: string,
            zona: string | null,
        ): Map<string, number> => {
            const groups = new Map<string, { sum: number; count: number }>();

            for (const row of sensorRows) {
                if (row.parameter !== parameter) {
                    continue;
                }

                if (zona !== null && row.zona !== zona) {
                    continue;
                }

                const date = row.waktu;
                const current = groups.get(date) ?? { sum: 0, count: 0 };

                groups.set(date, {
                    sum: current.sum + row.nilai,
                    count: current.count + 1,
                });
            }

            const result = new Map(
                Array.from(groups.entries()).map(([date, value]) => [
                    date,
                    value.sum / value.count,
                ]),
            );

            if (liveTimestamp !== undefined) {
                const live = readings.find((reading) => reading.parameter === parameter
                    && reading.nilai !== null
                    && (zona === null || reading.zona === zona));

                if (live?.nilai !== null && live?.nilai !== undefined) {
                    result.set(liveTimestamp, live.nilai);
                }
            }

            return result;
        };

        const historyValues = (field: 'bobot_rata_g' | 'jumlah_mati'): Map<string, number> => new Map(
            historyEntries.map(({ key, row }) => [key, row[field]]),
        );

        const growthValues = historyValues('bobot_rata_g');

        if (liveTimestamp !== undefined && fishRecords.length > 0) {
            const avgWeight = fishRecords.reduce((total, fish) => total + fish.bobot_g, 0) / fishRecords.length;
            growthValues.set(liveTimestamp, avgWeight);
        }

        const configs: Array<{
            key: string;
            label: string;
            color: string;
            unit: string;
            dashed?: boolean;
            values: Map<string, number>;
        }> = [
            { key: 'suhu-atas', label: 'Suhu atas', color: '#f59e0b', unit: '°C', values: sensorValues('suhu', 'atas') },
            { key: 'suhu-bawah', label: 'Suhu bawah', color: '#f97316', unit: '°C', dashed: true, values: sensorValues('suhu', 'bawah') },
            { key: 'do-atas', label: 'Oksigen atas', color: '#06b6d4', unit: 'mg/L', values: sensorValues('do', 'atas') },
            { key: 'do-bawah', label: 'Oksigen bawah', color: '#2563eb', unit: 'mg/L', dashed: true, values: sensorValues('do', 'bawah') },
            { key: 'ph-atas', label: 'pH atas', color: '#8b5cf6', unit: 'pH', values: sensorValues('ph', 'atas') },
            { key: 'ph-bawah', label: 'pH bawah', color: '#c026d3', unit: 'pH', dashed: true, values: sensorValues('ph', 'bawah') },
            { key: 'amonia-atas', label: 'Amonia atas', color: '#ef4444', unit: 'ppm', values: sensorValues('amonia', 'atas') },
            { key: 'amonia-bawah', label: 'Amonia bawah', color: '#be123c', unit: 'ppm', dashed: true, values: sensorValues('amonia', 'bawah') },
            { key: 'level', label: 'Level air', color: '#0f766e', unit: 'cm', values: sensorValues('level', null) },
            { key: 'growth', label: 'Bobot rata-rata', color: '#16a34a', unit: 'g', values: growthValues },
            { key: 'deaths', label: 'Ikan mati', color: '#475569', unit: 'ikan', values: historyValues('jumlah_mati') },
        ];

        const series: ChartSeries[] = [];

        for (const config of configs) {
            const rawPoints = dates
                .map((date, index) => {
                    const value = config.values.get(date);

                    return value === undefined
                        ? null
                        : { date, index, value };
                })
                .filter((point): point is { date: string; index: number; value: number } => point !== null);

            if (rawPoints.length === 0) continue;

            const valuesOnly = rawPoints.map((point) => point.value);
            const min = Math.min(...valuesOnly);
            const max = Math.max(...valuesOnly);

            const points: ChartPoint[] = rawPoints.map((point) => ({
                date: point.date,
                x: xFor(point.index),
                y: max === min
                    ? chartTop + chartHeight / 2
                    : chartBottom - ((point.value - min) / (max - min)) * chartHeight,
                value: point.value,
            }));

            series.push({
                key: config.key,
                label: config.label,
                color: config.color,
                dashed: config.dashed ?? false,
                unit: config.unit,
                latest: valuesOnly[valuesOnly.length - 1],
                points,
            });
        }

        const labelIndexes = Array.from(new Set([
            0,
            Math.floor((dates.length - 1) / 2),
            dates.length - 1,
        ]));

        return {
            dates,
            series,
            xLabels: labelIndexes.map((index) => ({
                x: xFor(index),
                label: chartDateLabel(dates[index], chartRange),
            })),
            xPositions: dates.map((_, index) => xFor(index)),
            sampleCount: new Set(sensorTimes).size,
        };
    });

    function formatSamplingDate(value: string): string {
        const [year, month, day] = value.slice(0, 10).split('-').map(Number);

        if (!year || !month || !day) return value;

        return new Date(Date.UTC(year, month - 1, day)).toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            timeZone: 'UTC',
        });
    }

    function samplingPath(points: SamplingChartPoint[], originX: number, originY: number): string {
        if (points.length === 0) return '';

        return [
            `M ${originX.toFixed(2)} ${originY.toFixed(2)}`,
            ...points.map((point) => `L ${point.x.toFixed(2)} ${point.y.toFixed(2)}`),
        ].join(' ');
    }

    const SAMPLING_CHART_WIDTH = 960;
    const SAMPLING_CHART_HEIGHT = 260;
    const SAMPLING_CHART_PAD = { left: 48, right: 22, top: 18, bottom: 34 };

    const samplingChartModel = $derived.by((): SamplingChartModel => {
        const rows = [...samplingRows]
            .filter((row) => row.tanggal)
            .sort((left, right) => left.tanggal.localeCompare(right.tanggal));

        if (rows.length === 0) {
            return { points: [], xLabels: [], yLabels: [], path: '', targetY: null };
        }

        const chartLeft = SAMPLING_CHART_PAD.left;
        const chartRight = SAMPLING_CHART_WIDTH - SAMPLING_CHART_PAD.right;
        const chartTop = SAMPLING_CHART_PAD.top;
        const chartBottom = SAMPLING_CHART_HEIGHT - SAMPLING_CHART_PAD.bottom;
        const chartWidth = chartRight - chartLeft;
        const chartHeight = chartBottom - chartTop;
        const target = Number(detail?.kolam.bobot_target_panen_g ?? 0);
        const values = rows.map((row) => Number(row.bobot_rata_g) || 0);
        const maxValue = Math.max(1, target || 0, ...values);
        const scaleMax = maxValue * 1.08;
        const xFor = (index: number): number => rows.length === 1
            ? chartLeft + chartWidth / 2
            : chartLeft + (index / (rows.length - 1)) * chartWidth;
        const yFor = (value: number): number => chartBottom - ((value - 0) / scaleMax) * chartHeight;
        const points = rows.map((row, index) => ({
            tanggal: row.tanggal,
            x: xFor(index),
            y: yFor(Number(row.bobot_rata_g) || 0),
            value: Number(row.bobot_rata_g) || 0,
        }));
        const labelIndexes = Array.from(new Set([
            0,
            Math.floor((rows.length - 1) / 2),
            rows.length - 1,
        ]));
        const yValues = [0, scaleMax / 2, scaleMax];

        return {
            points,
            path: samplingPath(points, chartLeft, chartBottom),
            targetY: target > 0 ? yFor(target) : null,
            xLabels: labelIndexes.map((index) => ({
                x: xFor(index),
                label: formatSamplingDate(rows[index].tanggal),
            })),
            yLabels: yValues.map((value) => ({
                y: yFor(value),
                label: `${Math.round(value).toLocaleString('id-ID')} g`,
            })),
        };
    });

    const latestSampling = $derived(
        samplingChartModel.points.length > 0
            ? samplingChartModel.points[samplingChartModel.points.length - 1]
            : null,
    );
    const visibleChartSeries = $derived(
        chartModel.series.filter((item) => !hiddenChartSeries.includes(item.key)),
    );
    const chartHoverSeries = $derived(
        chartHoverSeriesKey === null
            ? null
            : visibleChartSeries.find((item) => item.key === chartHoverSeriesKey) ?? null,
    );
    const chartHoverPoint = $derived(
        chartHoverSeries === null || chartHoverPointDate === null
            ? null
            : chartHoverSeries.points.find((point) => point.date === chartHoverPointDate) ?? null,
    );
    const chartHoverX = $derived(chartHoverPoint?.x ?? null);

    function toggleChartSeries(key: string): void {
        hiddenChartSeries = hiddenChartSeries.includes(key)
            ? hiddenChartSeries.filter((item) => item !== key)
            : [...hiddenChartSeries, key];

        if (hiddenChartSeries.includes(key) && chartHoverSeriesKey === key) {
            clearChartHover();
        }
    }

    function clearChartHover(): void {
        chartHoverSeriesKey = null;
        chartHoverPointDate = null;
    }

    function distanceToChartSegment(
        px: number,
        py: number,
        start: ChartPoint,
        end: ChartPoint,
    ): { distance: number; position: number } {
        const dx = end.x - start.x;
        const dy = end.y - start.y;
        const lengthSquared = dx * dx + dy * dy;

        if (lengthSquared === 0) {
            return { distance: Math.hypot(px - start.x, py - start.y), position: 0 };
        }

        const position = Math.max(
            0,
            Math.min(1, ((px - start.x) * dx + (py - start.y) * dy) / lengthSquared),
        );
        const nearestX = start.x + position * dx;
        const nearestY = start.y + position * dy;

        return {
            distance: Math.hypot(px - nearestX, py - nearestY),
            position,
        };
    }

    function nearestChartPoint(px: number, py: number): { series: ChartSeries; point: ChartPoint; distance: number } | null {
        let nearest: { series: ChartSeries; point: ChartPoint; distance: number } | null = null;

        for (const series of visibleChartSeries) {
            const points = series.points;

            if (points.length === 0) continue;

            if (points.length === 1) {
                const point = points[0];
                const distance = Math.hypot(px - point.x, py - point.y);

                if (nearest === null || distance < nearest.distance) {
                    nearest = { series, point, distance };
                }

                continue;
            }

            for (let index = 0; index < points.length - 1; index += 1) {
                const start = points[index];
                const end = points[index + 1];
                const match = distanceToChartSegment(px, py, start, end);
                const point = match.position < 0.5 ? start : end;

                if (nearest === null || match.distance < nearest.distance) {
                    nearest = { series, point, distance: match.distance };
                }
            }
        }

        return nearest;
    }

    function handleChartHover(event: MouseEvent): void {
        const svg = event.currentTarget as SVGSVGElement;
        const rect = svg.getBoundingClientRect();
        const chartX = ((event.clientX - rect.left) / rect.width) * CHART_WIDTH;
        const chartY = ((event.clientY - rect.top) / rect.height) * CHART_HEIGHT;
        const left = CHART_PAD.left;
        const right = CHART_WIDTH - CHART_PAD.right;
        const top = CHART_PAD.top;
        const bottom = CHART_HEIGHT - CHART_PAD.bottom;

        if (
            chartX < left
            || chartX > right
            || chartY < top
            || chartY > bottom
            || visibleChartSeries.length === 0
        ) {
            clearChartHover();
            return;
        }

        const nearest = nearestChartPoint(chartX, chartY);

        if (nearest === null || nearest.distance > 30) {
            clearChartHover();
            return;
        }

        chartHoverSeriesKey = nearest.series.key;
        chartHoverPointDate = nearest.point.date;
    }
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

    <section class="pond-detail-hero corporate-hero">
        <div class="hero-content">
            <div class="hero-topline">
                <span class="eyebrow"><i class="status-pulse"></i> OPERASIONAL KOLAM</span>
                <span class="hero-code">KODE {detail.kolam.kode}</span>
                <span class="hero-status" class:offline={onlineCount === 0} class:warning={onlineCount > 0 && offlineCount > 0}><i></i> {onlineCount === 0 ? 'Perangkat offline' : offlineCount > 0 ? `${offlineCount} perangkat perlu perhatian` : 'Monitoring aktif'}</span>
            </div>

            <div class="hero-title-block">
                <h1>{detail.kolam.nama}</h1>
                <p class="lead">Ringkasan kondisi kolam, kualitas air, dan perangkat IoT dalam satu tampilan operasional.</p>
            </div>

            <div class="hero-metrics">
                <div class="hero-metric">
                    <span class="hero-metric-label">Perangkat aktif</span>
                    <b>{onlineCount}<em>/{detail.perangkat.length}</em></b>
                    <small>unit terhubung</small>
                </div>
                <div class="hero-metric">
                    <span class="hero-metric-label">Zona pemantauan</span>
                    <b>{detail.zona.length}</b>
                    <small>zona air</small>
                </div>
                <div class="hero-metric">
                    <span class="hero-metric-label">Kapasitas air</span>
                    <b>{detail.kolam.volume_air_m3.toFixed(1)} <em>m³</em></b>
                    <small>volume efektif</small>
                </div>
                <div class="hero-metric">
                    <span class="hero-metric-label">Populasi aktif</span>
                    <b>{fishCount.toLocaleString('id-ID')}</b>
                    <small>ikan hidup</small>
                </div>
            </div>

            <div class="virtual-clock" aria-label="Waktu virtual kolam">
                <div class="virtual-clock-label"><i class="clock-dot"></i> Waktu virtual</div>
                <div class="virtual-clock-value">
                    <b>{virtualClock.date}</b>
                    <strong class="virtual-clock-time">{virtualClock.hour}<i>:</i>{virtualClock.minute}<i>:</i>{virtualClock.second}</strong>
                </div>
            </div>
        </div>

        <aside class="hero-actions">
            <span class="hero-actions-label">Tindakan cepat</span>
            <button class="btn pri hero-primary-action" disabled={refreshing} onclick={() => void refreshNow()}>
                <span class:spin={refreshing}>↻</span>
                {refreshing ? 'Menyinkronkan...' : 'Sinkronkan perangkat'}
            </button>
            <button class="btn hero-secondary-action" onclick={openEdit}>Pengaturan kolam</button>
            <span class="live-note"><i class="status-pulse"></i> Sinkronisasi terakhir {lastUpdated || '—'} · otomatis 15 detik</span>
        </aside>
    </section>

    {#if error}
        <div class="note warn detail-alert"><b>Perhatian:</b> {error}</div>
    {/if}

    <section class="simulator-deck">
        <div class="sim-deck-header">
            <div>
                <span class="sim-label">KONTROL SIMULASI</span>
                <div class="sim-deck-heading">
                    <h2>Sandbox operasional</h2>
                    <span class="sim-run-status" class:active={playing} class:busy={simBusy}>
                        <i></i>
                        {simBusy ? 'Memproses' : playing ? 'Berjalan' : 'Dijeda'}
                    </span>
                </div>
                <p>Atur mode simulasi dan kecepatan waktu untuk menguji respons kualitas air, sensor, pakan, dan pertumbuhan ikan.</p>
            </div>
            <span class="sim-harvest {harvestTone}">
                <FishIcon size={14} />
                <span>Status panen</span>
                <b>{harvestStatus}</b>
            </span>
        </div>

        <div class="sim-deck-grid">
            <div class="sim-deck-clock">
                <div class="sim-clock-readout">
                    <span>Waktu virtual</span>
                    <b>{virtualClock.date}</b>
                    <strong>{virtualClock.hour}<i>:</i>{virtualClock.minute}<i>:</i>{virtualClock.second}</strong>
                </div>
            </div>

            <div class="player-progress sim-deck-progress">
                <div class="player-progress-head">
                    <span>Progres hari</span>
                    <b>{formatVirtualTime(virtualTimeValue)}</b>
                </div>
                <button
                    type="button"
                    class="player-progress-rail"
                    class:active={playing}
                    aria-label={`Progres hari virtual ${Math.round(virtualDayProgress)} persen, klik untuk memajukan simulasi`}
                    title={`${Math.round(virtualDayProgress)}% hari virtual · klik untuk seek`}
                    disabled={simBusy}
                    onclick={(event) => void seekVirtualDay(event)}
                >
                    <i style={`width:${virtualDayProgress}%`}></i>
                    <span class="player-progress-marker" style={`left:clamp(5px, ${virtualDayProgress}%, calc(100% - 5px))`}></span>
                </button>
                <div class="player-progress-scale">
                    <span>00:00</span>
                    <span>12:00</span>
                    <span>24:00</span>
                </div>
                {#if simStepVisible}
                    <div class="sim-step-progress" role="status" aria-live="polite">
                        <div class="sim-step-progress-head">
                            <span>Memproses {formatMinuteSpan(simStepDone)} / {formatMinuteSpan(simStepTarget)}</span>
                            <b>{simStepPercent}%</b>
                        </div>
                        <div class="sim-step-progress-rail">
                            <i style={`width:${simStepPercent}%`}></i>
                        </div>
                    </div>
                {/if}
            </div>

            <div class="sim-deck-controls">
                <span class="sim-preset-label">Mode simulasi</span>
                <div class="sim-preset-row">
                    <div class="sim-preset-speeds" role="group" aria-label="Kecepatan simulasi">
                        {#each [1, 2, 5, 10] as speed}
                            <button
                                type="button"
                                class="sim-preset-chip"
                                class:selected={simulationSpeed === speed}
                                aria-pressed={simulationSpeed === speed}
                                onclick={() => changeSpeed(speed)}
                            >
                                {speed}×
                            </button>
                        {/each}
                    </div>
                    <span class="sim-preset-divider" aria-hidden="true"></span>
                    <div class="sim-preset-durations" role="group" aria-label="Durasi simulasi cepat">
                        <button type="button" class="sim-preset-chip duration" disabled={simBusy} onclick={() => runPreset(1)}>1 Hari</button>
                        <button type="button" class="sim-preset-chip duration" disabled={simBusy} onclick={() => runPreset(7)}>7 Hari</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="sim-deck-footer">
            <div class="sim-deck-footer-actions">
                <button class="sim-bottom-action run" class:playing onclick={togglePlayback}>
                    <span class="sim-action-icon">{playing ? 'Ⅱ' : '▶'}</span>
                    <span class="sim-action-copy">
                        <b>{playing ? 'Jeda simulasi' : 'Jalankan simulasi'}</b>
                        <small>{playing ? 'Hentikan sementara laju waktu' : 'Mulai percepatan waktu sandbox'}</small>
                    </span>
                </button>
                <button class="sim-bottom-action reset" onclick={() => (resetOpen = true)}>
                    <span class="sim-action-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20 12a8 8 0 1 1-2.34-5.66M20 4v6h-6" />
                        </svg>
                    </span>
                    <span class="sim-action-copy">
                        <b>Perbarui dari awal</b>
                        <small>Reset data dan mulai siklus baru</small>
                    </span>
                </button>
            </div>
            <div class="sim-deck-footer-meta">
                <span class="sim-deck-hint">
                    {simBusy ? 'Simulasi sedang diproses. Waktu virtual dan data sensor diperbarui secara bertahap.' : 'Pilih kecepatan atau durasi cepat untuk memperbarui data operasional.'}
                </span>
                <button class="sim-deck-edit" onclick={openEdit}>Edit kolam</button>
            </div>
        </div>
    </section>

    <div class="monitor-grid single-monitor">
        <section class="card pool-map-card">
            <div class="card-head">
                <div class="grow">
                    <h3>Topologi kolam & sensor</h3>
                    <p>Pemantauan kolam seperti topology map: status perangkat, zona air, dan populasi ikan dalam satu tampilan.</p>
                </div>
                <span class="badge ok"><span class="d"></span>Kolam aktif</span>
            </div>
            <div
                class="pond-stage"
                class:playing
                class:simulating={simBusy || playing}
                class:oxygen-rich={doValue !== null && doValue > 6}
                class:temperature-high={suhuValue !== null && suhuValue > 31}
                class:ph-unstable={phValue !== null && (phValue < 6.5 || phValue > 8.5)}
                class:ammonia-high={amoniaValue !== null && amoniaValue > 1.5}
                class:feed-active={feedActive}
            >
                <svg class="pond-illustration" viewBox="0 0 1000 600" preserveAspectRatio="none" aria-hidden="true">
                    <defs>
                        <linearGradient id="pond-bank-gradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#d8c39c" />
                            <stop offset="100%" stop-color="#b89a6b" />
                        </linearGradient>
                        <linearGradient id="pond-water-gradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#a9dff3" />
                            <stop offset="48%" stop-color="#62bddc" />
                            <stop offset="52%" stop-color="#3f9fc5" />
                            <stop offset="100%" stop-color="#24769c" />
                        </linearGradient>
                        <radialGradient id="pond-water-glow" cx="45%" cy="28%" r="72%">
                            <stop offset="0%" stop-color="#e0f7ff" stop-opacity=".7" />
                            <stop offset="100%" stop-color="#b4ecff" stop-opacity="0" />
                        </radialGradient>
                        <filter id="pond-shadow" x="-20%" y="-20%" width="140%" height="150%">
                            <feDropShadow dx="0" dy="16" stdDeviation="18" flood-color="#173c66" flood-opacity=".24" />
                        </filter>
                    </defs>

                    <ellipse cx="500" cy="350" rx="462" ry="243" fill="#e8f1fb" />
                    <path class="pond-bank" d="M100 185C260 83 740 83 900 185l30 235C735 548 265 548 70 420Z" fill="url(#pond-bank-gradient)" filter="url(#pond-shadow)" />
                    <path class="pond-bank-lip" d="M138 192C288 110 712 110 862 192l23 218C704 508 296 508 115 410Z" fill="#cbb185" />
                    <path class="pond-water-body" d="M165 205C306 132 694 132 835 205l36 196C702 492 298 492 129 401Z" fill="url(#pond-water-gradient)" />
                    <path class="pond-water-glow" d="M210 220C345 166 655 166 790 220l21 145C660 440 340 440 171 365Z" fill="url(#pond-water-glow)" />
                    <path class="pond-ripple" d="M225 250C375 203 625 203 775 250" />
                    <path class="pond-ripple pond-ripple-two" d="M190 300C355 252 645 252 810 300" />
                    <path class="pond-ripple pond-ripple-three" d="M245 400C390 446 610 446 755 400" />
                    <circle class="pond-bubble" cx="310" cy="250" r="5" />
                    <circle class="pond-bubble" cx="335" cy="238" r="3" />
                    <circle class="pond-bubble" cx="675" cy="360" r="4" />
                    <circle class="pond-bubble" cx="700" cy="348" r="2.5" />
                    <path class="pond-grass" d="M116 205c-12-20-9-35 2-48 2 17 8 29 14 42M135 191c-5-17 0-29 10-38 0 14 4 25 9 35M865 190c13-19 11-35 2-49-3 17-9 29-15 42M883 202c5-14 12-24 24-30-4 12-9 22-15 32" />
                    <path class="pond-depth-mark" d="M70 315h35M87 295v40" />
                    <g class="pond-links">
                        <line x1="190" y1="156" x2="245" y2="238" />
                        <line x1="290" y1="126" x2="335" y2="225" />
                        <line x1="710" y1="126" x2="665" y2="225" />
                        <line x1="810" y1="162" x2="755" y2="240" />
                        <line x1="190" y1="432" x2="245" y2="370" />
                        <line x1="290" y1="468" x2="335" y2="390" />
                        <line x1="710" y1="468" x2="665" y2="390" />
                        <line x1="810" y1="432" x2="755" y2="370" />
                        <line x1="860" y1="84" x2="810" y2="220" />
                        <line x1="140" y1="516" x2="190" y2="370" />
                    </g>
                </svg>
                <div class="pond-effects" aria-hidden="true">
                    <span class="effect-bubble e1"></span>
                    <span class="effect-bubble e2"></span>
                    <span class="effect-bubble e3"></span>
                    <span class="effect-bubble e4"></span>
                    <span class="effect-ripple er1"></span>
                    <span class="effect-ripple er2"></span>
                </div>
                {#if simBusy || playing}
                    <div class="sensor-scan" aria-hidden="true"></div>
                {/if}
                {#if feedActive}
                    <div class="feed-animation" aria-live="polite">
                        <span class="feed-pellet fp1"></span>
                        <span class="feed-pellet fp2"></span>
                        <span class="feed-pellet fp3"></span>
                        <span class="feed-pellet fp4"></span>
                        <span class="feed-pellet fp5"></span>
                        <b>{feedLabel}</b>
                    </div>
                {/if}
                <div class="pond-water">
                    <canvas bind:this={fishCanvas} class="pond-fish-canvas" aria-label="Visual ikan bergerak acak di seluruh area kolam" onclick={selectFishFromCanvas}></canvas>
                    <div class="zone-divider" aria-hidden="true"><span>ZONA SENSOR: ATAS / BAWAH</span></div>
                    <button
                        class="pond-population"
                        aria-label="Buka detail populasi ikan"
                        onclick={() => { selectedFish = null; fishModalOpen = true; }}
                    >
                        <span class="pond-population-main"><FishIcon size={14} /><b>{fishCount.toLocaleString('id-ID')}</b> ikan hidup</span>
                        <small>klik detail</small>
                    </button>
                </div>
                <span class="zone-label zone-top">ZONA ATAS</span>
                <span class="zone-label zone-bottom">ZONA BAWAH</span>
                <span class="pond-dimensions">{detail.kolam.panjang_m.toFixed(1)} × {detail.kolam.lebar_m.toFixed(1)} × {detail.kolam.tinggi_air_m.toFixed(1)} m</span>
                <div class="pond-legend">
                    <span><i class="active"></i>Sensor aktif</span>
                    <span><i class="offline"></i>Sensor mati</span>
                    <span><i class="ok"></i>Normal</span>
                    <span><i class="warn"></i>Waspada</span>
                    <span><i class="bad"></i>Kritis</span>
                </div>
                {#each detail.perangkat as device (device.id)}
                    {@const reading = readingFor(device)}
                    {@const status = statusFor(reading)}
                    {@const nodePosition = devicePosition(device)}
                    <button
                        class="device-node {status} {device.kode_jenis}"
                        class:selected={selectedCode === device.kode}
                        class:offline={device.status !== 'aktif'}
                        style={`left:${nodePosition.left}%;top:${nodePosition.top}%`}
                        aria-label={`Pilih ${device.jenis_perangkat}`}
                        onclick={() => { selectedCode = device.kode; deviceModalOpen = true; }}
                    >
                        <SensorIcon name={device.kode_jenis} size={19} />
                        <span>{deviceLabel(device)}</span>
                    </button>
                {/each}
                <div class="pond-compass">N</div>
            </div>
        </section>
    </div>

    <section class="sensor-chart-section">
        <div class="section-title-row">
            <div>
                <h2>Riwayat kualitas air & populasi</h2>
                <p>Satu tampilan untuk melihat dinamika sensor atas/bawah, pertumbuhan ikan, dan kematian harian.</p>
            </div>
            <div class="chart-section-meta">
                <span>{chartModel.dates.length} titik · {chartRangeLabel}</span>
                <span>Sinkron otomatis · {lastUpdated || '—'}</span>
            </div>
        </div>

        <div class="card sensor-chart-card">
            <div class="chart-head">
                <div>
                    <h3>Indikator operasional kolam</h3>
                    <p>Centang legenda untuk menampilkan atau menyembunyikan garis. Solid = zona atas, putus-putus = zona bawah. Arahkan kursor ke salah satu garis untuk melihat nilai titik yang dipilih.</p>
                </div>
                <div class="chart-head-side">
                    <div class="chart-range-tabs" role="group" aria-label="Rentang waktu chart">
                        {#each chartRanges as range}
                            <button
                                type="button"
                                class:active={chartRange === range.value}
                                aria-pressed={chartRange === range.value}
                                onclick={() => changeChartRange(range.value)}
                            >{range.label}</button>
                        {/each}
                    </div>
                    <span class="chart-live"><i></i> Data historis</span>
                </div>
            </div>

            {#if chartModel.dates.length === 0}
                <div class="chart-empty">Riwayat akan muncul setelah simulasi melewati satu hari virtual.</div>
            {:else}
                <div class="chart-legend">
                    {#each chartModel.series as item (item.key)}
                        <label class="chart-legend-item" class:muted={hiddenChartSeries.includes(item.key)}>
                            <input
                                type="checkbox"
                                checked={!hiddenChartSeries.includes(item.key)}
                                onchange={() => toggleChartSeries(item.key)}
                            />
                            <i class:dashed={item.dashed} style={`--line-color:${item.color}`}></i>
                            <span>{item.label}</span>
                            <b>{formatChartValue(item.latest, item.unit)}</b>
                        </label>
                    {/each}
                </div>

                {#if chartModel.sampleCount < 2}
                    <div class="chart-range-note">
                        {#if chartModel.sampleCount === 0}
                            Belum ada sampel sensor pada rentang ini.
                        {:else}
                            Baru ada 1 sampel sensor pada rentang ini.
                        {/if}
                        {#if latestReadingTime}
                            Pembacaan terakhir {formatVirtualTime(latestReadingTime)}.
                        {/if}
                        Titik live tetap memakai nilai terakhir; interval pembacaan perangkat sekitar 10 menit.
                    </div>
                {/if}

                <div class="chart-hover-readout" class:active={chartHoverSeries !== null && chartHoverPoint !== null} aria-live="polite">
                    {#if chartHoverSeries !== null && chartHoverPoint !== null}
                        <i style={`--line-color:${chartHoverSeries.color}`}></i>
                        <span>{chartHoverSeries.label}</span>
                        <strong>{formatChartValue(chartHoverPoint.value, chartHoverSeries.unit)}</strong>
                        <small>{chartTooltipDate(chartHoverPoint.date)}</small>
                    {:else}
                        <span class="chart-hover-hint">Arahkan kursor ke salah satu garis untuk melihat nilainya.</span>
                    {/if}
                </div>

                <div class="chart-plot">
                    <div class="chart-scroll">
                    <svg
                        viewBox={`0 0 ${CHART_WIDTH} ${CHART_HEIGHT}`}
                        role="img"
                        aria-label="Grafik riwayat suhu, oksigen, pH, amonia, level air, pertumbuhan ikan, dan ikan mati"
                        onmousemove={handleChartHover}
                        onmouseleave={clearChartHover}
                    >
                        {#each [0, 1, 2, 3, 4] as gridIndex}
                            {@const gridY = CHART_PAD.top + (gridIndex / 4) * (CHART_HEIGHT - CHART_PAD.top - CHART_PAD.bottom)}
                            <line
                                class="chart-grid-line"
                                x1={CHART_PAD.left}
                                x2={CHART_WIDTH - CHART_PAD.right}
                                y1={gridY}
                                y2={gridY}
                            />
                            <text
                                class="chart-y-label"
                                x={CHART_PAD.left - 8}
                                y={gridY + 3}
                                text-anchor="end"
                            >{100 - gridIndex * 25}%</text>
                        {/each}

                        <line
                            class="chart-axis-line"
                            x1={CHART_PAD.left}
                            x2={CHART_WIDTH - CHART_PAD.right}
                            y1={CHART_HEIGHT - CHART_PAD.bottom}
                            y2={CHART_HEIGHT - CHART_PAD.bottom}
                        />
                        <line
                            class="chart-axis-line"
                            x1={CHART_PAD.left}
                            x2={CHART_PAD.left}
                            y1={CHART_PAD.top}
                            y2={CHART_HEIGHT - CHART_PAD.bottom}
                        />
                        <text
                            class="chart-axis-title"
                            x={(CHART_PAD.left + CHART_WIDTH - CHART_PAD.right) / 2}
                            y={CHART_HEIGHT - 4}
                            text-anchor="middle"
                        >{chartRange === '1mo' || chartRange === '1y' ? 'Tanggal' : 'Waktu'}</text>
                        <text
                            class="chart-axis-title"
                            transform={`translate(10 ${(CHART_PAD.top + CHART_HEIGHT - CHART_PAD.bottom) / 2}) rotate(-90)`}
                            text-anchor="middle"
                        >Indeks relatif</text>

                        {#each chartModel.xLabels as label}
                            <text class="chart-axis-label" x={label.x} y={CHART_HEIGHT - 18} text-anchor="middle">{label.label}</text>
                        {/each}

                        {#each visibleChartSeries as item (item.key)}
                            <path
                                class="chart-series"
                                class:dashed={item.dashed}
                                class:hovered={item.key === chartHoverSeriesKey}
                                d={chartPath(item.points)}
                                fill="none"
                                stroke={item.color}
                            />
                            {#if item.points.length > 0}
                                {@const lastPoint = item.points[item.points.length - 1]}
                                <circle cx={lastPoint.x} cy={lastPoint.y} r="3.5" fill={item.color} />
                            {/if}
                        {/each}

                        {#if chartHoverSeries !== null && chartHoverPoint !== null && chartHoverX !== null}
                            <line
                                class="chart-crosshair"
                                x1={chartHoverX}
                                x2={chartHoverX}
                                y1={CHART_PAD.top}
                                y2={CHART_HEIGHT - CHART_PAD.bottom}
                            />
                            <circle
                                cx={chartHoverPoint.x}
                                cy={chartHoverPoint.y}
                                r="4.5"
                                fill="#ffffff"
                                stroke={chartHoverSeries.color}
                                stroke-width="2.5"
                            />
                        {/if}
                    </svg>
                    </div>
                </div>

                <div class="chart-footnote">
                    <span><i class="chart-legend-line solid"></i> Zona atas</span>
                    <span><i class="chart-legend-line dashed"></i> Zona bawah</span>
                    <span>Skala vertikal dinormalisasi per indikator agar seluruh metrik dapat dibandingkan dalam satu chart.</span>
                </div>
            {/if}
        </div>
    </section>

    <section class="sampling-chart-section">
        <div class="section-title-row">
            <div>
                <h2>Grafik timbang ikan</h2>
                <p>Bobot rata-rata per sesi penimbangan. Grafik ini terpisah dari sensor kualitas air.</p>
            </div>
            <div class="chart-section-meta">
                <span>{samplingRows.length} sesi timbang</span>
                <span>Target {detail?.kolam.bobot_target_panen_g ?? '—'} g</span>
            </div>
        </div>

        <div class="card sampling-chart-card">
            {#if samplingChartModel.points.length === 0}
                <div class="sampling-chart-empty">
                    <b>Belum ada data timbang ikan.</b>
                    <span>Catat dan timbang dari kartu kolam di daftar kolam.</span>
                    <Link class="btn sm" href="/kolam">Buka daftar kolam</Link>
                </div>
            {:else}
                <div class="sampling-chart-summary">
                    <span>Bobot terakhir <b>{latestSampling ? `${latestSampling.value.toLocaleString('id-ID', { maximumFractionDigits: 2 })} g` : '—'}</b></span>
                    <span>Target panen <b>{detail?.kolam.bobot_target_panen_g ?? '—'} g</b></span>
                </div>

                <div class="sampling-chart-scroll">
                    <svg
                        viewBox={`0 0 ${SAMPLING_CHART_WIDTH} ${SAMPLING_CHART_HEIGHT}`}
                        role="img"
                        aria-label="Grafik bobot rata-rata ikan dari hasil timbang"
                    >
                        {#each samplingChartModel.yLabels as label}
                            <line
                                class="sampling-chart-grid"
                                x1={SAMPLING_CHART_PAD.left}
                                x2={SAMPLING_CHART_WIDTH - SAMPLING_CHART_PAD.right}
                                y1={label.y}
                                y2={label.y}
                            />
                            <text
                                class="sampling-chart-y-label"
                                x={SAMPLING_CHART_PAD.left - 8}
                                y={label.y + 3}
                                text-anchor="end"
                            >{label.label}</text>
                        {/each}

                        {#if samplingChartModel.targetY !== null}
                            <line
                                class="sampling-chart-target"
                                x1={SAMPLING_CHART_PAD.left}
                                x2={SAMPLING_CHART_WIDTH - SAMPLING_CHART_PAD.right}
                                y1={samplingChartModel.targetY}
                                y2={samplingChartModel.targetY}
                            />
                            <text
                                class="sampling-chart-target-label"
                                x={SAMPLING_CHART_WIDTH - SAMPLING_CHART_PAD.right}
                                y={samplingChartModel.targetY - 5}
                                text-anchor="end"
                            >Target panen</text>
                        {/if}

                        <line
                            class="sampling-chart-axis"
                            x1={SAMPLING_CHART_PAD.left}
                            x2={SAMPLING_CHART_WIDTH - SAMPLING_CHART_PAD.right}
                            y1={SAMPLING_CHART_HEIGHT - SAMPLING_CHART_PAD.bottom}
                            y2={SAMPLING_CHART_HEIGHT - SAMPLING_CHART_PAD.bottom}
                        />

                        <path class="sampling-chart-line" d={samplingChartModel.path} fill="none" />
                        <circle
                            class="sampling-chart-origin"
                            cx={SAMPLING_CHART_PAD.left}
                            cy={SAMPLING_CHART_HEIGHT - SAMPLING_CHART_PAD.bottom}
                            r="3.5"
                        />
                        {#each samplingChartModel.points as point}
                            <circle class="sampling-chart-point" cx={point.x} cy={point.y} r="4" />
                        {/each}

                        {#each samplingChartModel.xLabels as label}
                            <text
                                class="sampling-chart-x-label"
                                x={label.x}
                                y={SAMPLING_CHART_HEIGHT - 14}
                                text-anchor="middle"
                            >{label.label}</text>
                        {/each}
                        <text
                            class="sampling-chart-axis-title"
                            x={(SAMPLING_CHART_PAD.left + SAMPLING_CHART_WIDTH - SAMPLING_CHART_PAD.right) / 2}
                            y={SAMPLING_CHART_HEIGHT - 2}
                            text-anchor="middle"
                        >Tanggal timbang</text>
                        <text
                            class="sampling-chart-axis-title"
                            transform={`translate(13 ${(SAMPLING_CHART_PAD.top + SAMPLING_CHART_HEIGHT - SAMPLING_CHART_PAD.bottom) / 2}) rotate(-90)`}
                            text-anchor="middle"
                        >Bobot rata-rata (g)</text>
                    </svg>
                </div>

                <div class="chart-footnote">
                    <span><i class="chart-legend-line solid"></i> Bobot rata-rata hasil timbang</span>
                    <span><i class="chart-legend-line target"></i> Target panen</span>
                </div>
            {/if}
        </div>
    </section>

    <HarvestPlanMockup />
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
                <span><small>Status</small><b>{selectedDevice.status === 'aktif' ? 'Aktif & terhubung' : 'Dimatikan'}</b></span>
                <span><small>Baterai</small><b>{selectedDevice.baterai_persen}%</b></span>
                <span><small>Interval</small><b>{selectedDevice.interval_menit} menit</b></span>
                <span><small>Kualitas</small><b>{deviceReading?.kualitas_data ?? 'menunggu'}</b></span>
            </div>
            <div class="device-power-row">
                <div>
                    <b>{selectedDevice.status === 'aktif' ? 'Sensor sedang berjalan' : 'Sensor sedang dimatikan'}</b>
                    <small>{selectedDevice.status === 'aktif' ? 'Perangkat akan membaca sesuai interval.' : 'Perangkat tidak akan menghasilkan pembacaan baru.'}</small>
                </div>
                <button
                    class="toggle"
                    class:on={selectedDevice.status === 'aktif'}
                    disabled={deviceToggleBusy === selectedDevice.id}
                    aria-label={selectedDevice.status === 'aktif' ? 'Matikan sensor' : 'Aktifkan sensor'}
                    onclick={() => void toggleDevice(selectedDevice)}
                ></button>
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
            <span class="modal-kicker"><span class="fish-modal-icon"><FishIcon size={16} /></span> {selectedFish ? selectedFish.kode : 'POPULASI KOLAM'}</span>
            <h2>{selectedFish ? 'Detail ikan terpantau' : 'Ikan lele di kolam'}</h2>
            <p class="modal-subtitle">{selectedFish ? 'Posisi dan kondisi ikan berasal dari hasil pergerakan sandbox.' : 'Visual kolam menampilkan populasi hidup hasil algoritma pergerakan.'}</p>
            {#if selectedFish}
                <div class="fish-count-panel">
                    <span class="fish-size-icon"><FishIcon size={24} /></span>
                    <div>
                        <strong>{formatValue(selectedFish.bobot_g, 'g')}</strong>
                        <span>Zona {selectedFish.zona?.nama ?? '—'} · stres {selectedFish.stres}% · panjang {selectedFish.panjang_cm.toFixed(1)} cm</span>
                    </div>
                </div>
            {:else}
                <div class="fish-count-panel">
                    <span class="fish-size-icon"><FishIcon size={24} /></span>
                    <div>
                        <strong>{fishCount.toLocaleString('id-ID')}</strong>
                        <span>ikan hidup · klik ikan untuk melihat ukuran dan kondisinya</span>
                    </div>
                </div>
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
            <p class="modal-subtitle">Ubah ukuran sesuai kolam sebenarnya. Volume air dan kepadatan tebar dihitung ulang otomatis.</p>
            {#if editError}
                <div class="note bad">{editError}</div>
            {/if}
            <div class="edit-form">
                <label class="edit-field-wide">Nama kolam<input bind:value={editForm.nama} required maxlength="100" /></label>
                <label>Panjang (m)<input type="number" step="0.1" min="0.1" inputmode="decimal" bind:value={editForm.panjang_m} required /></label>
                <label>Lebar (m)<input type="number" step="0.1" min="0.1" inputmode="decimal" bind:value={editForm.lebar_m} required /></label>
                <label>Tinggi air (m)<input type="number" step="0.1" min="0.1" inputmode="decimal" bind:value={editForm.tinggi_air_m} required /></label>
                <label>Target panen (g)<input type="number" min="1" step="1" inputmode="numeric" bind:value={editForm.bobot_target_panen_g} /></label>
                <label>Kecepatan waktu<input type="number" min="1" step="1" inputmode="numeric" bind:value={editForm.kecepatan_waktu} /></label>
            </div>
            <div class="pond-volume-preview">
                <span>Volume air setelah disimpan</span>
                <b>{editVolume.toFixed(2)} m³ <small>({Math.round(editVolume * 1000).toLocaleString('id-ID')} liter)</small></b>
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
