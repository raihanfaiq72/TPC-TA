<script lang="ts">
    type StatusKey = 'belum siap' | 'hampir siap' | 'siap panen';
    type StatusTone = 'wait' | 'near' | 'ready';
    type ForecastRow = { label: string; value: number };
    type StatusMock = {
        label: string;
        tone: StatusTone;
        range: string;
        rangeNote: string;
        reasons: string[];
        actual: number[];
        forecast: ForecastRow[];
        currentKg: number;
        targetKg: number;
    };

    const statusOrder: StatusKey[] = ['belum siap', 'hampir siap', 'siap panen'];
    const actualLabels = ['15 Okt', '25 Okt', '1 Nov', '6 Nov'];

    const statusMocks: Record<StatusKey, StatusMock> = {
        'belum siap': {
            label: 'Belum siap',
            tone: 'wait',
            range: '28 Nov – 5 Des 2026',
            rangeNote: 'Perkiraan masih lebar; menunggu pertumbuhan dan stabilitas air.',
            reasons: [
                'Bobot rata-rata baru 66 g, masih jauh dari target 100 g.',
                'DO pagi sempat 2,9 mg/L sehingga perlu dipantau sebelum panen.',
            ],
            actual: [30, 42, 55, 66],
            forecast: [
                { label: '13 Nov', value: 72 },
                { label: '20 Nov', value: 80 },
                { label: '27 Nov', value: 90 },
                { label: '4 Des', value: 98 },
            ],
            currentKg: 78,
            targetKg: 118,
        },
        'hampir siap': {
            label: 'Hampir siap',
            tone: 'near',
            range: '21–27 Nov 2026',
            rangeNote: 'Rentang tanggal, bukan tanggal pasti; bisa bergeser mengikuti pertumbuhan.',
            reasons: [
                'Bobot rata-rata 88,4 g, sudah 88% dari target 100 g.',
                'Kualitas air stabil 6 dari 7 hari terakhir; DO perlu dijaga pagi hari.',
            ],
            actual: [35, 53, 70, 88.4],
            forecast: [
                { label: '13 Nov', value: 92 },
                { label: '20 Nov', value: 97 },
                { label: '27 Nov', value: 100 },
            ],
            currentKg: 104,
            targetKg: 118,
        },
        'siap panen': {
            label: 'Siap panen',
            tone: 'ready',
            range: '18–20 Nov 2026',
            rangeNote: 'Jendela panen pendek; siapkan tenaga dan pembeli lebih awal.',
            reasons: [
                'Bobot rata-rata 101 g, sudah melewati target 100 g.',
                'Suhu dan DO berada di rentang aman untuk pengangkutan.',
            ],
            actual: [35, 55, 78, 101],
            forecast: [
                { label: '13 Nov', value: 103 },
                { label: '20 Nov', value: 104 },
            ],
            currentKg: 118,
            targetKg: 118,
        },
    };

    let activeStatus = $state<StatusKey>('hampir siap');
    const currentStatus = $derived(statusMocks[activeStatus]);
    type ChartPoint = { label: string; x: number; y: number; value: number };
    type ChartModel = {
        actualPoints: ChartPoint[];
        forecastPoints: ChartPoint[];
        actualPath: string;
        forecastPath: string;
        xLabels: Array<{ x: number; label: string }>;
        yLabels: Array<{ y: number; label: string }>;
        targetY: number;
        origin: { x: number; y: number };
    };

    const targetWeight = 100;
    const population = 1180;
    const latestPrice = 24500;
    const latestPriceDate = '6 Nov 2026';
    const latestPriceSource = 'Pasar Induk Rau, Serang';

    const CHART_WIDTH = 960;
    const CHART_HEIGHT = 290;
    const CHART_PAD = { left: 50, right: 24, top: 22, bottom: 38 };

    const chart = $derived.by((): ChartModel => {
        const chartLeft = CHART_PAD.left;
        const chartRight = CHART_WIDTH - CHART_PAD.right;
        const chartTop = CHART_PAD.top;
        const chartBottom = CHART_HEIGHT - CHART_PAD.bottom;
        const chartWidth = chartRight - chartLeft;
        const chartHeight = chartBottom - chartTop;
        const labels = [
            ...actualLabels,
            ...currentStatus.forecast.map((point) => point.label),
        ];
        const values = [
            ...currentStatus.actual,
            ...currentStatus.forecast.map((point) => point.value),
            targetWeight,
        ];
        const scaleMax = Math.max(1, ...values) * 1.08;
        const xFor = (index: number): number => labels.length === 1
            ? chartLeft + chartWidth / 2
            : chartLeft + (index / (labels.length - 1)) * chartWidth;
        const yFor = (value: number): number => chartBottom - (value / scaleMax) * chartHeight;
        const actualPoints: ChartPoint[] = currentStatus.actual.map((value, index) => ({
            label: actualLabels[index],
            x: xFor(index),
            y: yFor(value),
            value,
        }));
        const forecastPoints: ChartPoint[] = currentStatus.forecast.map((point, index) => ({
            label: point.label,
            x: xFor(actualLabels.length + index),
            y: yFor(point.value),
            value: point.value,
        }));
        const origin = { x: chartLeft, y: chartBottom };
        const actualPath = actualPoints.length > 0
            ? [
                `M ${origin.x.toFixed(2)} ${origin.y.toFixed(2)}`,
                ...actualPoints.map((point) => `L ${point.x.toFixed(2)} ${point.y.toFixed(2)}`),
            ].join(' ')
            : '';
        const forecastPath = actualPoints.length > 0 && forecastPoints.length > 0
            ? [
                `M ${actualPoints[actualPoints.length - 1].x.toFixed(2)} ${actualPoints[actualPoints.length - 1].y.toFixed(2)}`,
                ...forecastPoints.map((point) => `L ${point.x.toFixed(2)} ${point.y.toFixed(2)}`),
            ].join(' ')
            : '';
        const labelIndexes = Array.from(new Set([
            0,
            actualPoints.length - 1,
            actualPoints.length,
            labels.length - 1,
        ])).filter((index) => index >= 0 && index < labels.length);

        return {
            actualPoints,
            forecastPoints,
            actualPath,
            forecastPath,
            origin,
            targetY: yFor(targetWeight),
            xLabels: labelIndexes.map((index) => ({
                x: xFor(index),
                label: labels[index],
            })),
            yLabels: [0, scaleMax / 2, scaleMax].map((value) => ({
                y: yFor(value),
                label: `${Math.round(value)} g`,
            })),
        };
    });

    const prepItems = [
        { id: 'feed', label: 'Hentikan pakan 12–24 jam sebelum panen', note: 'Mengurangi sisa pakan dan stres saat sortir.' },
        { id: 'do', label: 'Cek DO dan suhu pagi hari', note: 'Pastikan air tetap aman saat ikan dikumpulkan.' },
        { id: 'aerator', label: 'Siapkan aerator cadangan', note: 'Antisipasi penurunan oksigen saat proses panen.' },
        { id: 'tools', label: 'Siapkan timbangan, keranjang, dan es', note: 'Untuk sortir dan menjaga kualitas hasil.' },
    ];
    let prepDone = $state<Record<string, boolean>>({});

    function togglePrep(id: string): void {
        prepDone = { ...prepDone, [id]: !prepDone[id] };
    }

    function formatRupiah(value: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(value);
    }
</script>

<section class="harvest-plan" aria-labelledby="harvest-plan-title">
    <div class="harvest-plan-head">
        <div>
            <span class="harvest-kicker">MOCKUP · KEPUTUSAN PANEN</span>
            <h2 id="harvest-plan-title">Rencana panen</h2>
            <p>Pratinjau alur keputusan panen. Seluruh angka dan status di bagian ini adalah data contoh, belum terhubung ke backend.</p>
        </div>
        <span class="harvest-mock-badge">Data contoh</span>
    </div>

    <div class="card harvest-status-card {currentStatus.tone}">
        <div class="harvest-status-top">
            <div>
                <span class="harvest-label">STATUS PANEN</span>
                <h3>{currentStatus.label}</h3>
            </div>
            <span class="harvest-status-pill {currentStatus.tone}"><i></i>{currentStatus.label}</span>
        </div>

        <div class="harvest-status-range">
            <span>Perkiraan rentang panen</span>
            <strong>{currentStatus.range}</strong>
            <small>{currentStatus.rangeNote}</small>
        </div>

        <div class="harvest-reasons">
            {#each currentStatus.reasons as reason, index}
                <div class="harvest-reason">
                    <span>{index + 1}</span>
                    <p>{reason}</p>
                </div>
            {/each}
        </div>

        <div class="harvest-status-tabs" role="group" aria-label="Pratinjau status panen">
            <span>Pratinjau status</span>
            {#each statusOrder as status}
                <button
                    type="button"
                    class:active={activeStatus === status}
                    onclick={() => (activeStatus = status)}
                >{statusMocks[status].label}</button>
            {/each}
        </div>
    </div>

    <div class="card harvest-growth-card">
        <div class="harvest-card-head">
            <div>
                <span class="harvest-label">PERTUMBUHAN IKAN</span>
                <h3>Bobot sampling &amp; perkiraan ke depan</h3>
                <p>Titik pengukuran dan garis prediksi dipisahkan agar tidak tertukar dengan target panen.</p>
            </div>
            <span class="harvest-target-pill">Target {targetWeight} g</span>
        </div>

        <div class="harvest-chart-scroll">
            <svg
                viewBox={`0 0 ${CHART_WIDTH} ${CHART_HEIGHT}`}
                role="img"
                aria-label="Grafik bobot hasil sampling dan prediksi pertumbuhan"
            >
                {#each chart.yLabels as label}
                    <line
                        class="harvest-chart-grid"
                        x1={CHART_PAD.left}
                        x2={CHART_WIDTH - CHART_PAD.right}
                        y1={label.y}
                        y2={label.y}
                    />
                    <text class="harvest-chart-y-label" x={CHART_PAD.left - 8} y={label.y + 3} text-anchor="end">{label.label}</text>
                {/each}

                <line
                    class="harvest-chart-target"
                    x1={CHART_PAD.left}
                    x2={CHART_WIDTH - CHART_PAD.right}
                    y1={chart.targetY}
                    y2={chart.targetY}
                />
                <text
                    class="harvest-chart-target-label"
                    x={CHART_WIDTH - CHART_PAD.right}
                    y={chart.targetY - 6}
                    text-anchor="end"
                >Target {targetWeight} g</text>

                <path class="harvest-chart-actual" d={chart.actualPath} fill="none" />
                <path class="harvest-chart-forecast" d={chart.forecastPath} fill="none" />

                {#each chart.actualPoints as point}
                    <circle class="harvest-chart-actual-point" cx={point.x} cy={point.y} r="4.5" />
                {/each}
                {#each chart.forecastPoints as point}
                    <circle class="harvest-chart-forecast-point" cx={point.x} cy={point.y} r="4" />
                {/each}

                <circle class="harvest-chart-origin" cx={chart.origin.x} cy={chart.origin.y} r="3.4" />

                {#each chart.xLabels as label}
                    <text class="harvest-chart-x-label" x={label.x} y={CHART_HEIGHT - 15} text-anchor="middle">{label.label}</text>
                {/each}
                <text
                    class="harvest-chart-axis-title"
                    x={(CHART_PAD.left + CHART_WIDTH - CHART_PAD.right) / 2}
                    y={CHART_HEIGHT - 2}
                    text-anchor="middle"
                >Tanggal sampling / prediksi</text>
            </svg>
        </div>

        <div class="harvest-chart-legend">
            <span><i class="actual"></i> Hasil pengukuran</span>
            <span><i class="forecast"></i> Garis prediksi</span>
            <span><i class="target"></i> Target bobot {targetWeight} g</span>
        </div>
    </div>
    <div class="harvest-lower-grid">
        <div class="card harvest-price-card">
            <div class="harvest-card-head">
                <div>
                    <span class="harvest-label">HARGA &amp; WAKTU PANEN</span>
                    <h3>Harga terbaru dan perbandingan</h3>
                </div>
                <span class="harvest-source-badge">Harga terakhir</span>
            </div>

            <div class="harvest-price-latest">
                <div>
                    <strong>{formatRupiah(latestPrice)}<small>/kg</small></strong>
                    <span>{latestPriceSource} · {latestPriceDate}</span>
                </div>
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 19V5M4 19h16" />
                    <path d="m7.5 15 4-4 3 2.6L19 8" />
                </svg>
            </div>

            <div class="harvest-table-wrap">
                <table class="harvest-price-table">
                    <thead>
                        <tr>
                            <th>Pilihan</th>
                            <th>Perkiraan bobot/hasil</th>
                            <th>Asumsi harga</th>
                            <th>Perkiraan nilai kotor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><b>Panen sekarang</b><small>{population.toLocaleString('id-ID')} ekor</small></td>
                            <td>{currentStatus.currentKg} kg</td>
                            <td>Harga terbaru<br /><small>{formatRupiah(latestPrice)}/kg</small></td>
                            <td><b>{formatRupiah(currentStatus.currentKg * latestPrice)}</b></td>
                        </tr>
                        <tr class="not-comparable">
                            <td><b>Menunggu target</b><small>target {currentStatus.targetKg} kg</small></td>
                            <td>{currentStatus.targetKg} kg</td>
                            <td>Prediksi harga<br /><small>belum tersedia</small></td>
                            <td><span class="harvest-unavailable">—</span><small>belum dapat dibandingkan</small></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="harvest-price-note">
                <b>Catatan forecast harga</b>
                <p>Prediksi harga belum layak dibuat karena data historis masih kurang. Harga terakhir tetap ditampilkan pada tanggal {latestPriceDate}; skenario “menunggu target” belum dapat dibandingkan dengan prediksi harga.</p>
            </div>
            <p class="harvest-table-footnote">Nilai kotor = perkiraan bobot × asumsi harga, belum termasuk biaya panen dan transportasi.</p>
        </div>


        <div class="card harvest-env-card">
            <div class="harvest-card-head">
                <div>
                    <span class="harvest-label">KONDISI LINGKUNGAN</span>
                    <h3>Siapkan panen dengan aman</h3>
                </div>
                <span class="harvest-source-badge">Mockup</span>
            </div>

            <div class="harvest-env-grid">
                <div class="harvest-env-item ok">
                    <span>Suhu</span><strong>28,4 °C</strong><small>Normal</small>
                </div>
                <div class="harvest-env-item ok">
                    <span>DO</span><strong>4,8 mg/L</strong><small>Aman</small>
                </div>
                <div class="harvest-env-item ok">
                    <span>pH</span><strong>7,3</strong><small>Normal</small>
                </div>
                <div class="harvest-env-item warn">
                    <span>Amonia</span><strong>0,28 ppm</strong><small>Pantau</small>
                </div>
            </div>

            <div class="harvest-prep-head">
                <b>Persiapan panen</b>
                <span>{Object.values(prepDone).filter(Boolean).length}/{prepItems.length} selesai</span>
            </div>
            <div class="harvest-prep-list">
                {#each prepItems as item}
                    <label class="harvest-prep-item" class:done={prepDone[item.id]}>
                        <input type="checkbox" checked={prepDone[item.id] ?? false} onchange={() => togglePrep(item.id)} />
                        <span class="harvest-prep-check"></span>
                        <span>
                            <b>{item.label}</b>
                            <small>{item.note}</small>
                        </span>
                    </label>
                {/each}
            </div>
        </div>
    </div>
</section>


<style>
    .harvest-plan { min-width: 0; max-width: 100%; margin-top: 28px; }
    .harvest-plan-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 12px;
    }
    .harvest-kicker {
        display: block;
        margin-bottom: 5px;
        color: #7b8aa6;
        font-size: 9px;
        font-weight: 900;
        letter-spacing: 0.1em;
    }
    .harvest-plan-head h2 {
        margin: 0;
        color: #14235c;
        font-size: 20px;
        font-weight: 900;
        letter-spacing: -0.03em;
    }
    .harvest-plan-head p {
        max-width: 760px;
        margin: 4px 0 0;
        color: #64748b;
        font-size: 11px;
        line-height: 1.55;
    }
    .harvest-mock-badge,
    .harvest-source-badge,
    .harvest-target-pill {
        display: inline-flex;
        align-items: center;
        flex: 0 0 auto;
        padding: 5px 8px;
        border: 1px solid #d7e2f2;
        border-radius: 999px;
        color: #52647e;
        background: #f8fbff;
        font-size: 8px;
        font-weight: 900;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .harvest-mock-badge { color: #805000; border-color: #ecd797; background: #fffaf0; }
    .harvest-label {
        display: block;
        margin-bottom: 4px;
        color: #7b8aa6;
        font-size: 8px;
        font-weight: 900;
        letter-spacing: 0.09em;
    }
    .harvest-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 12px;
    }
    .harvest-card-head h3 {
        margin: 0;
        color: #14235c;
        font-size: 15px;
        font-weight: 900;
        letter-spacing: -0.02em;
    }
    .harvest-card-head p {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 10px;
        line-height: 1.45;
    }
    .harvest-status-card {
        position: relative;
        overflow: hidden;
        padding: 20px 22px;
        border-radius: 18px;
        background: #f8fbff;
    }
    .harvest-status-card::after {
        content: '';
        position: absolute;
        top: -70px;
        right: -40px;
        width: 250px;
        height: 250px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.5);
        pointer-events: none;
    }
    .harvest-status-card.wait { border-color: #ecd797; background: #fffaf0; }
    .harvest-status-card.near { border-color: #d7e2f2; background: #f4f8ff; }
    .harvest-status-card.ready { border-color: #b9dcc9; background: #f1faf5; }
    .harvest-status-top {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
    }
    .harvest-status-top h3 {
        margin: 0;
        color: #14235c;
        font-size: 28px;
        font-weight: 900;
        letter-spacing: -0.04em;
    }
    .harvest-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 9px;
        border-radius: 999px;
        font-size: 9px;
        font-weight: 900;
    }
    .harvest-status-pill i { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .harvest-status-pill.wait { color: #9a6500; background: #fff0c8; }
    .harvest-status-pill.near { color: #263c92; background: #e8efff; }
    .harvest-status-pill.ready { color: #11633a; background: #dff4e8; }

    .harvest-status-range {
        position: relative;
        z-index: 1;
        display: grid;
        gap: 3px;
        margin-top: 18px;
        padding-top: 13px;
        border-top: 1px solid rgba(100, 116, 139, 0.16);
    }
    .harvest-status-range > span {
        color: #64748b;
        font-size: 9px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .harvest-status-range strong {
        color: #14235c;
        font-size: 23px;
        font-weight: 900;
        letter-spacing: -0.03em;
        overflow-wrap: anywhere;
    }
    .harvest-status-range small { color: #64748b; font-size: 10px; }
    .harvest-reasons {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin-top: 14px;
    }
    .harvest-reason {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 9px 10px;
        border: 1px solid rgba(100, 116, 139, 0.14);
        border-radius: 9px;
        color: #52647e;
        background: rgba(255, 255, 255, 0.55);
        font-size: 10px;
        line-height: 1.45;
    }
    .harvest-reason > span {
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        width: 17px;
        height: 17px;
        border-radius: 50%;
        color: #ffffff;
        background: #263c92;
        font-size: 8px;
        font-weight: 900;
    }
    .harvest-reason p { margin: 0; }
    .harvest-status-tabs {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 5px;
        margin-top: 16px;
    }
    .harvest-status-tabs > span { margin-right: 4px; color: #7b8aa6; font-size: 9px; font-weight: 800; }
    .harvest-status-tabs button {
        padding: 6px 9px;
        border: 1px solid #d7e2f2;
        border-radius: 7px;
        color: #64748b;
        background: rgba(255, 255, 255, 0.6);
        font-size: 9px;
        font-weight: 800;
    }
    .harvest-status-tabs button:hover { border-color: #9fb6e7; color: #263c92; }
    .harvest-status-tabs button.active { border-color: #263c92; color: #ffffff; background: #263c92; }

    .harvest-growth-card { margin-top: 14px; padding: 18px 20px 13px; min-width: 0; }
    .harvest-target-pill { color: #11633a; border-color: #b9dcc9; background: #eef8f2; }
    .harvest-chart-scroll { max-width: 100%; min-width: 0; overflow-x: auto; }
    .harvest-chart-scroll svg { display: block; width: 100%; min-width: 560px; height: auto; }
    .harvest-chart-grid { stroke: #e6edf7; stroke-width: 1; stroke-dasharray: 3 5; }
    .harvest-chart-y-label, .harvest-chart-x-label, .harvest-chart-target-label {
        fill: #7b8aa6;
        font-size: 9px;
        font-weight: 700;
    }
    .harvest-chart-target-label { fill: #a06b00; font-size: 8px; }
    .harvest-chart-axis-title { fill: #52647e; font-size: 9px; font-weight: 800; letter-spacing: 0.03em; }
    .harvest-chart-target { stroke: #d9a62e; stroke-width: 1.3; stroke-dasharray: 5 5; }
    .harvest-chart-actual { stroke: #263c92; stroke-width: 2.6; stroke-linecap: round; stroke-linejoin: round; }
    .harvest-chart-forecast { stroke: #8b5cf6; stroke-width: 2.2; stroke-dasharray: 7 5; stroke-linecap: round; }
    .harvest-chart-actual-point { fill: #ffffff; stroke: #263c92; stroke-width: 2.3; }
    .harvest-chart-forecast-point { fill: #ffffff; stroke: #8b5cf6; stroke-width: 2; }
    .harvest-chart-origin { fill: #d9a62e; stroke: #ffffff; stroke-width: 1.5; }
    .harvest-chart-legend { display: flex; align-items: center; flex-wrap: wrap; gap: 7px 15px; margin-top: 4px; color: #7b8aa6; font-size: 9px; }
    .harvest-chart-legend span { display: inline-flex; align-items: center; gap: 5px; }
    .harvest-chart-legend i { display: inline-block; width: 20px; height: 0; border-top: 2px solid #52647e; }
    .harvest-chart-legend i.actual { border-color: #263c92; }
    .harvest-chart-legend i.forecast { border-color: #8b5cf6; border-top-style: dashed; }
    .harvest-chart-legend i.target { border-color: #d9a62e; border-top-style: dashed; }

    .harvest-lower-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.85fr);
        gap: 14px;
        margin-top: 14px;
        min-width: 0;
    }
    .harvest-lower-grid > * { min-width: 0; }
    .harvest-price-card, .harvest-env-card { min-width: 0; padding: 18px 20px; }
    .harvest-price-latest {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 14px;
        padding: 11px 13px;
        border: 1px solid #dbe7f5;
        border-radius: 11px;
        background: #f8fbff;
    }
    .harvest-price-latest > div { min-width: 0; }
    .harvest-price-latest strong { color: #14235c; font-size: 21px; font-weight: 900; letter-spacing: -0.03em; }
    .harvest-price-latest strong small { margin-left: 3px; color: #64748b; font-size: 10px; font-weight: 700; letter-spacing: 0; }
    .harvest-price-latest span { display: block; margin-top: 2px; color: #64748b; font-size: 9px; }
    .harvest-price-latest svg { width: 22px; height: 22px; fill: none; stroke: #16a34a; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .harvest-table-wrap { min-width: 0; max-width: 100%; overflow-x: auto; margin-top: 13px; }
    .harvest-price-table { width: 100%; min-width: 560px; border-collapse: collapse; font-size: 9px; }
    .harvest-price-table th {
        padding: 7px 8px;
        border-bottom: 1px solid #dbe7f5;
        color: #7b8aa6;
        font-size: 8px;
        font-weight: 900;
        letter-spacing: 0.03em;
        text-align: left;
        text-transform: uppercase;
    }
    .harvest-price-table td { padding: 9px 8px; border-bottom: 1px solid #edf2f8; color: #52647e; vertical-align: top; }
    .harvest-price-table tbody tr:last-child td { border-bottom: 0; }
    .harvest-price-table td b { color: #14235c; font-size: 10px; font-weight: 900; }
    .harvest-price-table td small { display: block; margin-top: 2px; color: #7b8aa6; font-size: 8px; line-height: 1.35; }
    .harvest-price-table tbody tr.not-comparable td { color: #94a3b8; background: #f8fafc; }
    .harvest-price-table tbody tr.not-comparable td b { color: #64748b; }
    .harvest-unavailable { color: #9a6500; font-size: 13px; font-weight: 900; }
    .harvest-price-note {
        margin-top: 12px;
        padding: 9px 10px;
        border: 1px solid #f0dfb1;
        border-radius: 8px;
        color: #805000;
        background: #fffaf0;
        font-size: 9px;
        line-height: 1.45;
    }
    .harvest-price-note b { font-size: 9px; font-weight: 900; }
    .harvest-price-note p { margin: 3px 0 0; }
    .harvest-table-footnote { margin: 8px 0 0; color: #94a3b8; font-size: 8px; line-height: 1.4; }

    .harvest-env-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 7px; }
    .harvest-env-item {
        display: grid;
        gap: 2px;
        padding: 9px 10px;
        border: 1px solid #dbe7f5;
        border-radius: 9px;
        background: #f8fbff;
    }
    .harvest-env-item span { color: #7b8aa6; font-size: 8px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
    .harvest-env-item strong { color: #14235c; font-size: 13px; font-weight: 900; }
    .harvest-env-item small { color: #16a34a; font-size: 8px; font-weight: 800; }
    .harvest-env-item.warn { border-color: #f0dfb1; background: #fffaf0; }
    .harvest-env-item.warn small { color: #a06b00; }
    .harvest-prep-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin: 15px 0 7px; }
    .harvest-prep-head b { color: #14235c; font-size: 11px; font-weight: 900; }
    .harvest-prep-head span { color: #7b8aa6; font-size: 8px; font-weight: 800; }
    .harvest-prep-list { display: grid; gap: 5px; }
    .harvest-prep-item {
        display: flex;
        align-items: flex-start;
        gap: 7px;
        padding: 6px 7px;
        border-radius: 7px;
        color: #52647e;
        cursor: pointer;
    }
    .harvest-prep-item:hover { background: #f8fbff; }
    .harvest-prep-item input { position: absolute; opacity: 0; pointer-events: none; }
    .harvest-prep-check {
        flex: 0 0 auto;
        width: 14px;
        height: 14px;
        margin-top: 1px;
        border: 1.5px solid #b8c9e4;
        border-radius: 4px;
        background: #ffffff;
    }
    .harvest-prep-item.done .harvest-prep-check {
        border-color: #263c92;
        background: #263c92;
        box-shadow: inset 0 0 0 3px #ffffff;
    }
    .harvest-prep-item.done b { color: #7b8aa6; text-decoration: line-through; }
    .harvest-prep-item > span:last-child { display: grid; gap: 2px; }
    .harvest-prep-item b { color: #14235c; font-size: 9px; font-weight: 800; line-height: 1.35; }
    .harvest-prep-item small { color: #7b8aa6; font-size: 8px; line-height: 1.35; }
    @media (max-width: 1100px) {
        .harvest-lower-grid { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 620px) {
        .harvest-reasons { grid-template-columns: minmax(0, 1fr); }
        .harvest-plan-head { flex-direction: column; gap: 7px; }
        .harvest-card-head { flex-wrap: wrap; }
        .harvest-status-card { padding: 16px; }
        .harvest-growth-card, .harvest-price-card, .harvest-env-card { padding: 15px 13px 12px; }
        .harvest-status-top h3 { font-size: 24px; }
        .harvest-status-range strong { font-size: 19px; }
    }
</style>

