<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
</script>

<AppHead title="Hasil panen" />

<div class="head">
    <h1>Perkembangan ikan C07</h1>
    <p class="lead">
        Dihitung dari 10 kali penimbangan. Kamu tidak perlu menghitung apa pun.
    </p>
</div>

<div class="grid g3" style="margin-bottom:16px">
    <div class="card kpi">
        <div class="kic">
            <svg viewBox="0 0 24 24"
                ><path
                    d="M4.5 7.5h15l-1.6 12a1.5 1.5 0 0 1-1.5 1.3H7.6a1.5 1.5 0 0 1-1.5-1.3l-1.6-12Z"
                /><path d="M9 7.5V6a3 3 0 0 1 6 0v1.5" /></svg
            >
        </div>
        <div class="v">88,4 <small>gram</small></div>
        <div class="n">Berat 1 ekor</div>
        <div class="x">Rata-rata dari 25 ekor yang kamu timbang kemarin.</div>
    </div>
    <div class="card kpi">
        <div class="kic">
            <svg viewBox="0 0 24 24"
                ><path d="M3 16.5 9 10l4 3.6 7-7" /><path
                    d="M3 20.5h18"
                /></svg
            >
        </div>
        <div class="v">104 <small>kg</small></div>
        <div class="n">Total berat semua ikan</div>
        <div class="x">Dari 1.180 ekor yang masih hidup.</div>
    </div>
    <div class="card kpi">
        <div class="kic">
            <svg viewBox="0 0 24 24"
                ><path
                    d="M5 8h14l-1.4 11.2a1.5 1.5 0 0 1-1.5 1.3H7.9a1.5 1.5 0 0 1-1.5-1.3L5 8Z"
                /><path d="M9 8V6.5a3 3 0 0 1 6 0V8" /></svg
            >
        </div>
        <div class="v">1,21 <small>: 1</small></div>
        <div class="n">Pakan jadi daging</div>
        <div class="x">
            1,21 kg pakan menghasilkan 1 kg ikan. Makin kecil makin untung.
        </div>
    </div>
</div>

<div class="grid gsplit">
    <div class="card">
        <div class="card-head">
            <div class="grow">
                <h3>Berat ikan naik terus</h3>
                <p>Umur ikan (hari) → berat 1 ekor (gram)</p>
            </div>
            <span class="badge acc">Naik 2% per hari</span>
        </div>
        <svg
            class="chart"
            viewBox="0 0 560 210"
            role="img"
            aria-label="Grafik berat ikan per umur"
        >
            <line class="gl" x1="46" y1="24" x2="540" y2="24" />
            <line class="gl" x1="46" y1="88" x2="540" y2="88" />
            <line class="gl" x1="46" y1="152" x2="540" y2="152" />
            <text x="4" y="27">100</text>
            <text x="10" y="91">50</text>
            <text x="28" y="155">0</text>
            <path
                class="ar"
                d="M60 134 L125 120 L189 106 L254 90 L318 72 L383 58 L447 47 L512 32 L512 152 L60 152 Z"
            />
            <path
                class="ln"
                d="M60 134 L125 120 L189 106 L254 90 L318 72 L383 58 L447 47 L512 32"
            />
            <path class="lnp" d="M512 32 L548 18" />
            <circle class="pt" cx="512" cy="32" r="4.6" />
            <text x="60" y="170" text-anchor="middle">47</text>
            <text x="189" y="170" text-anchor="middle">54</text>
            <text x="318" y="170" text-anchor="middle">61</text>
            <text x="447" y="170" text-anchor="middle">66</text>
            <text x="512" y="170" text-anchor="middle">70</text>
            <text x="544" y="170" text-anchor="middle">75</text>
            <text x="46" y="196">hari ke-</text>
        </svg>
        <div class="legend">
            <span><i></i>Hasil timbangan kamu</span>
            <span><i class="dash"></i>Perkiraan sistem</span>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="grow">
                <h3>Kenapa 5 hari lagi?</h3>
                <p>Alasan sistem menyarankan tanggal itu.</p>
            </div>
        </div>
        <div class="rows">
            <div class="row-i">
                <div class="t">
                    <b>Berat hampir sampai target</b
                    ><span class="lead2"
                        >88,4 g dari 100 g — tinggal 12 gram lagi.</span
                    >
                </div>
                <span class="badge warn">Hampir</span>
            </div>
            <div class="row-i">
                <div class="t">
                    <b>Data masih baru</b
                    ><span class="lead2"
                        >Ditimbang 2 hari lalu, jadi masih bisa
                        dipercaya.</span
                    >
                </div>
                <span class="badge ok">Baru</span>
            </div>
            <div class="row-i">
                <div class="t">
                    <b>Timbangan sudah 10 kali</b
                    ><span class="lead2"
                        >Cukup untuk melihat pola pertumbuhan.</span
                    >
                </div>
                <span class="badge ok">Cukup</span>
            </div>
            <div class="row-i">
                <div class="t">
                    <b>Air perlu dipantau</b
                    ><span class="lead2"
                        >Oksigen rendah bisa memperlambat pertumbuhan.</span
                    >
                </div>
                <span class="badge warn">Pantau</span>
            </div>
        </div>
        <Link class="btn block" style="margin-top:16px" href="/panen">
            Lihat jadwal panennya
        </Link>
    </div>
</div>

