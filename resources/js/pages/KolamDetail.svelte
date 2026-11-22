<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FishIcon from '@/components/FishIcon.svelte';
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
    type DeathCause = { penyebab: string; jumlah: number };
    type SimulationStatus = {
        kolam: { waktu_sandbox_sekarang: string; kecepatan_waktu: number };
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
        alert_baru?: number;
        selesai?: boolean;
        menit_dijalankan?: number;
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
    let recommendation = $state<Recommendation | null>(null);
    let playing = $state(false);
    let simulationSpeed = $state(1);
    let stepAmount = $state(10);
    let stepUnit = $state<'menit' | 'jam' | 'hari' | 'bulan'>('menit');
    let feedActive = $state(false);
    let feedTimer: ReturnType<typeof setTimeout> | undefined;
    let simBusy = $state(false);
    let deviceToggleBusy = $state<number | null>(null);
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

    function stepToMinutes(): number {
        const factor = { menit: 1, jam: 60, hari: 1440, bulan: 43200 }[stepUnit];

        return Math.max(1, Math.round(stepAmount)) * factor;
    }

    function stepLabel(): string {
        return `${Math.max(1, Math.round(stepAmount))} ${stepUnit}`;
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

    function triggerFeed(grams: number): void {
        feedActive = true;

        if (feedTimer) {
            clearTimeout(feedTimer);
        }

        feedTimer = setTimeout(() => {
            feedActive = false;
        }, Math.max(1800, Math.min(4200, grams > 2000 ? 4200 : 2600)));
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

    async function advanceSimulation(minutes?: number): Promise<void> {
        if (simBusy) return;
        simBusy = true;
        error = '';
        try {
            let remaining = minutes ?? stepToMinutes();
            let pakanEvents = 0;
            let pakanGrams = 0;
            let alertCount = 0;
            let dailyProcess = false;
            let passes = 0;

            // Backend membatasi tick per request. Untuk langkah besar seperti
            // satu bulan, lanjutkan request berikutnya sampai target terpenuhi.
            while (remaining > 0 && passes < 12) {
                const response = await fetch(`/api/kolam/${id}/simulasi/tick`, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ menit: remaining }),
                });
                if (!response.ok) throw new Error('Simulasi tidak dapat dilanjutkan.');

                const payload = (await response.json()) as TickResponse;
                const advanced = Math.max(0, payload.menit_dijalankan ?? 0);

                if (advanced <= 0) break;

                remaining -= advanced;
                pakanEvents += payload.pakan_kejadian ?? 0;
                pakanGrams += payload.pakan_g ?? 0;
                alertCount += payload.alert_baru ?? 0;
                dailyProcess = dailyProcess || Boolean(payload.proses_harian);
                passes++;

                if (payload.selesai !== false) break;
            }

            if (pakanEvents > 0) {
                triggerFeed(pakanGrams);
            }

            await Promise.all([loadSimulation(), loadReadings(), loadFish()]);

            if (dailyProcess) {
                await Promise.all([loadHistory(), loadRecommendation()]);
            }

            if (alertCount > 0) {
                window.dispatchEvent(new Event('aqua:refresh-alerts'));
            }

            if (remaining > 0) {
                say(`Simulasi berhenti pada batas langkah; sisa ${remaining} menit virtual`);
            }
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

    function parseVirtualDate(value: string | undefined): Date | null {
        if (!value) return null;

        const parsed = new Date(value.includes('T') ? value : value.replace(' ', 'T'));

        return Number.isNaN(parsed.getTime()) ? null : parsed;
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
                const zoneName = fish.zona?.nama ?? (index % 2 === 0 ? 'atas' : 'bawah');
                const zoneY = zoneName === 'atas' ? cy - ry * 0.46 : cy + ry * 0.42;
                const phase = random(fish.id, 1) * Math.PI * 2;
                const spread = random(fish.id, 2);
                const baseX = cx + (spread - 0.5) * rx * 1.45;
                const baseY = zoneY + (random(fish.id, 6) - 0.5) * ry * 0.3;
                const swimSpeed = playing
                    ? 0.0011 + simulationSpeed * 0.00035
                    : 0.00035;
                const swimX = Math.sin(time * swimSpeed + phase);
                const swimY = Math.cos(time * swimSpeed * 0.75 + phase * 1.3);
                const x = baseX + swimX * rx * 0.18;
                const y = baseY + swimY * ry * 0.08;
                const targetWeight = Math.max(1, detail?.kolam.bobot_target_panen_g ?? 100);
                const growthRatio = Math.min(1.65, Math.max(0.55, fish.bobot_g / targetWeight));
                const baseSize = fishRecords.length > 300
                    ? 1.7 + random(fish.id, 3) * 1.2
                    : 3.4 + random(fish.id, 3) * 2.6;
                const size = baseSize * (0.75 + growthRatio * 0.75);
                const direction = swimX >= 0 ? 1 : -1;
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
        void (async () => {
            try {
                await loadDetail();
                await Promise.all([loadReadings(), loadSimulation(), loadHistory(), loadRecommendation(), loadFish()]);
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
            if (feedTimer) clearTimeout(feedTimer);
            if (fishAnimationFrame) cancelAnimationFrame(fishAnimationFrame);
        };
    });

    const selectedDevice = $derived(detail?.perangkat.find((device) => device.kode === selectedCode));
    const selectedReading = $derived(selectedDevice ? readingFor(selectedDevice) : undefined);
    const onlineCount = $derived(detail?.perangkat.filter((device) => device.status === 'aktif').length ?? 0);
    const fishCount = $derived(simulation?.ikan.jumlah_hidup ?? fishRecords.length ?? detail?.kolam.jumlah_ikan_awal ?? 0);
    const doValue = $derived(readings.find((reading) => reading.parameter === 'do')?.nilai ?? null);
    const suhuValue = $derived(readings.find((reading) => reading.parameter === 'suhu')?.nilai ?? null);
    const phValue = $derived(readings.find((reading) => reading.parameter === 'ph')?.nilai ?? null);
    const amoniaValue = $derived(readings.find((reading) => reading.parameter === 'amonia')?.nilai ?? null);
    const harvestStatus = $derived(recommendation?.status ?? 'belum siap');
    const harvestTone = $derived(
        harvestStatus === 'siap panen' ? 'ready' : harvestStatus === 'hampir siap' ? 'near' : 'wait',
    );
    const virtualTimeValue = $derived(
        simulation?.kolam.waktu_sandbox_sekarang ?? detail?.kolam.waktu_sandbox_sekarang,
    );
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
    const virtualDayProgress = $derived.by(() => {
        const now = parseVirtualDate(virtualTimeValue);

        if (!now) return 0;

        const minutes = now.getHours() * 60 + now.getMinutes();

        return Math.min(100, Math.max(0, (minutes / 1440) * 100));
    });
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
            <div class="virtual-clock" aria-label="Waktu virtual kolam">
                <span><small>Tanggal</small><b>{virtualClock.date}</b></span>
                <span><small>Jam</small><b>{virtualClock.hour}</b></span>
                <span><small>Menit</small><b>{virtualClock.minute}</b></span>
                <span><small>Detik</small><b>{virtualClock.second}</b></span>
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

    <section class="sim-console sim-player">
        <div class="player-main">
            <button class="play-button" class:playing onclick={togglePlayback} aria-label={playing ? 'Jeda simulasi' : 'Putar simulasi'}>
                {playing ? 'Ⅱ' : '▶'}
            </button>
            <div class="player-track">
                <span class="sim-label">SANDBOX SIMULATOR</span>
                <b>{playing ? 'Simulasi sedang berjalan' : 'Simulasi dijeda'}</b>
                <small>Tick {stepLabel()} · kecepatan {simulationSpeed}×</small>
            </div>
        </div>

        <div class="player-progress">
            <div class="player-progress-head">
                <span>Waktu virtual · progres hari</span>
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
                <span class="player-progress-marker" style={`left:${virtualDayProgress}%`}></span>
            </button>
            <div class="player-progress-scale">
                <span>00:00</span>
                <span>12:00</span>
                <span>24:00</span>
            </div>
        </div>

        <div class="player-controls">
            <label class="player-field">Langkah
                <span class="step-input">
                    <input type="number" min="1" max="999" bind:value={stepAmount} />
                    <select bind:value={stepUnit} aria-label="Satuan langkah simulasi">
                        <option value="menit">menit</option>
                        <option value="jam">jam</option>
                        <option value="hari">hari</option>
                        <option value="bulan">bulan</option>
                    </select>
                </span>
            </label>
            <button class="player-advance" disabled={simBusy} onclick={() => void advanceSimulation()} aria-label={`Majukan simulasi ${stepLabel()}`}>
                <span>+</span>
                <b>{stepLabel()}</b>
            </button>
            <label class="player-field">Kecepatan
                <select class="player-speed-select" value={simulationSpeed} onchange={(event) => changeSpeed(Number((event.currentTarget as HTMLSelectElement).value))}>
                    <option value="1">1×</option>
                    <option value="2">2×</option>
                    <option value="5">5×</option>
                    <option value="10">10×</option>
                </select>
            </label>
            <span class="sim-harvest {harvestTone}">
                <FishIcon size={13} />
                {harvestStatus}
            </span>
        </div>

        <div class="player-actions">
            <button class="btn sm" onclick={openEdit}>Edit kolam</button>
            <button class="btn sm soft" onclick={() => (resetOpen = true)}>Perbarui dari awal</button>
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
                {#if feedActive}
                    <div class="feed-animation" aria-live="polite">
                        <span class="feed-pellet fp1"></span>
                        <span class="feed-pellet fp2"></span>
                        <span class="feed-pellet fp3"></span>
                        <span class="feed-pellet fp4"></span>
                        <span class="feed-pellet fp5"></span>
                        <b>PAKAN DIBERIKAN</b>
                    </div>
                {/if}
                <div class="pond-water">
                    <canvas bind:this={fishCanvas} class="pond-fish-canvas" aria-label="Visual ikan bergerak menurut zona" onclick={selectFishFromCanvas}></canvas>
                    <div class="zone-divider" aria-hidden="true"><span>PEMISAH ZONA ATAS / BAWAH</span></div>
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

    <div class="section-title-row">
        <div><h2>Pembacaan terakhir</h2><p>Angka dari modul sensor di kolam ini.</p></div>
        <span class="tiny muted">Sinkron otomatis · {lastUpdated || '—'}</span>
    </div>
    <div class="metric-grid">
        {#each ['suhu', 'do', 'ph', 'amonia', 'level'] as parameter}
            {@const reading = readings.find((item) => item.parameter === parameter)}
            {@const status = statusFor(reading)}
            <div class="metric-card {status}">
                <div class="metric-card-top">
                    <span class="metric-card-label"><span class="metric-icon"><SensorIcon name={parameter} size={16} /></span>{labels[parameter]}</span>
                    <i class="status-pulse"></i>
                </div>
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
