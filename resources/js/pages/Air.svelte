<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { say } from '@/lib/aqua-ui.svelte';

    let autoSend = $state(true);

    function toggleAutoSend(): void {
        autoSend = !autoSend;
        say(autoSend ? 'Kirim otomatis diaktifkan' : 'Kirim otomatis dimatikan');
    }
</script>

<AppHead title="Kondisi air" />

<div class="head">
    <h1>Kondisi air kolam C07</h1>
    <p class="lead">
        Dibaca otomatis oleh alat di kolam. Terakhir diperbarui 2 menit lalu.
    </p>
</div>

<div class="note warn" style="margin-bottom:16px">
    <svg viewBox="0 0 24 24"
        ><path d="M12 4.5 21 20H3L12 4.5Z" /><path
            d="M12 10v4M12 17h.01"
        /></svg
    >
    <div>
        <b>Ada 1 hal yang perlu tindakan:</b> oksigen air terlalu rendah.
        Nyalakan aerator tambahan atau minta petugas mengecek.
    </div>
</div>

<div class="grid g3">
    <div class="card sensor">
        <div class="sh">
            <span class="sic"
                ><svg viewBox="0 0 24 24"
                    ><path
                        d="M14 14.8V5.5a2 2 0 1 0-4 0v9.3a4 4 0 1 0 4 0Z"
                    /><path d="M12 9v6" /></svg
                ></span
            >
            <b>Suhu air</b>
            <span style="flex:1"></span>
            <span class="badge ok"><span class="d"></span>Aman</span>
        </div>
        <div class="v">28,4 <small>°C</small></div>
        <div class="say">
            Masih nyaman untuk ikan. Batas wajar 26–30 °C.
        </div>
        <button
            class="btn sm"
            style="margin-top:4px"
            onclick={() => say('Riwayat suhu 7 hari dibuka')}
        >
            Lihat riwayat suhu
        </button>
    </div>

    <div class="card sensor">
        <div class="sh">
            <span class="sic" style="background:var(--warn-bg);color:var(--warn)"
                ><svg viewBox="0 0 24 24"
                    ><circle cx="12" cy="12" r="9" /><path
                        d="M12 8v4.5M12 16h.01"
                    /></svg
                ></span
            >
            <b>Oksigen</b>
            <span style="flex:1"></span>
            <span class="badge warn"><span class="d"></span>Rendah</span>
        </div>
        <div class="v">2,6 <small>mg/L</small></div>
        <div class="say">
            Terlalu rendah. Ikan bisa lemas dan berhenti makan.
        </div>
        <button
            class="btn sm soft"
            style="margin-top:4px"
            onclick={() => say('Permintaan cek aerator dikirim ke petugas')}
        >
            Minta cek aerator
        </button>
    </div>

    <div class="card sensor">
        <div class="sh">
            <span class="sic" style="background:var(--bad-bg);color:var(--bad)"
                ><svg viewBox="0 0 24 24"
                    ><path d="M9 3.5h6M12 3.5v11" /><rect
                        x="7"
                        y="8"
                        width="10"
                        height="12.5"
                        rx="2"
                    /></svg
                ></span
            >
            <b>Keasaman</b>
            <span style="flex:1"></span>
            <span class="badge bad"><span class="d"></span>Belum ada</span>
        </div>
        <div class="v">—</div>
        <div class="say">
            Angka pH air tidak dikirim jam 14:00. Terakhir 7,3 kemarin.
        </div>
        <button
            class="btn sm"
            style="margin-top:4px"
            onclick={() => say('Permintaan ukur ulang pH dikirim')}
        >
            Minta ukur ulang
        </button>
    </div>
</div>

<div class="grid gsplit" style="margin-top:16px">
    <div class="card">
        <div class="card-head">
            <div class="grow">
                <h3>Oksigen turun sejak pagi</h3>
                <p>24 jam terakhir · garis putus-putus = batas aman 3 mg/L</p>
            </div>
        </div>
        <svg
            class="chart"
            viewBox="0 0 560 170"
            role="img"
            aria-label="Grafik oksigen air 24 jam"
        >
            <line class="gl" x1="46" y1="36" x2="540" y2="36" />
            <line class="gl" x1="46" y1="96" x2="540" y2="96" />
            <line class="gl" x1="46" y1="140" x2="540" y2="140" />
            <text x="8" y="39">6</text>
            <text x="8" y="99">3</text>
            <text x="30" y="143">0</text>
            <path
                class="ar"
                d="M60 36 L102 42 L144 50 L186 56 L228 60 L270 68 L312 74 L354 80 L396 84 L438 90 L480 94 L522 104 L522 140 L60 140 Z"
            />
            <path
                class="ln"
                d="M60 36 L102 42 L144 50 L186 56 L228 60 L270 68 L312 74 L354 80 L396 84 L438 90 L480 94 L522 104"
            />
            <circle class="pt" cx="522" cy="104" r="4.4" />
            <text x="60" y="158" text-anchor="middle">pagi</text>
            <text x="228" y="158" text-anchor="middle">siang</text>
            <text x="396" y="158" text-anchor="middle">sore</text>
            <text x="522" y="158" text-anchor="middle">sekarang</text>
        </svg>
        <div class="legend">
            <span><i></i>Oksigen terukur</span>
            <span><i class="dash"></i>Batas aman 3 mg/L</span>
        </div>
        <p class="tiny muted" style="margin-top:10px">
            Semakin sore oksigen makin turun karena tanaman air berhenti
            menghasilkan oksigen. Aerator membantu menaikkannya.
        </p>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="grow">
                <h3>Alat di kolam</h3>
                <p>Sensor-01</p>
            </div>
        </div>
        <div class="rows">
            <div class="row-i">
                <div class="t">
                    <b>Status alat</b><span>Baterai 82% · sinyal kuat</span>
                </div>
                <span class="badge ok"><span class="d"></span>Nyala</span>
            </div>
            <div class="row-i">
                <div class="t">
                    <b>Kirim otomatis</b><span>Setiap 60 menit</span>
                </div>
                <button
                    class="toggle"
                    class:on={autoSend}
                    role="switch"
                    aria-checked={autoSend}
                    aria-label="Kirim otomatis"
                    onclick={toggleAutoSend}
                ></button>
            </div>
            <div class="row-i">
                <div class="t">
                    <b>Data terakhir</b
                    ><span>14:00 · suhu 28,4 · oksigen 2,6</span>
                </div>
            </div>
        </div>
        <button
            class="btn block"
            style="margin-top:16px"
            onclick={() => say('Data baru dikirim, hasilnya muncul di atas')}
        >
            Kirim data sekarang
        </button>
        <p class="tiny muted" style="margin-top:10px">
            Alat ini masih simulasi untuk pengujian, belum dipasang di kolam
            sungguhan.
        </p>
    </div>
</div>

