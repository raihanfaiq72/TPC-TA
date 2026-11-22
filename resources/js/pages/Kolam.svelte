<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { say } from '@/lib/aqua-ui.svelte';
</script>

<AppHead title="Daftar kolam" />

<div class="head-row head">
    <div class="grow">
        <h1>Kolam saya</h1>
        <p class="lead">
            3 kolam aktif. Ketuk salah satu untuk melihat detailnya.
        </p>
    </div>
    <button class="btn" onclick={() => say('Form tambah kolam dibuka')}>
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        Tambah kolam
    </button>
</div>

<div class="grid g3">
    <div class="card pond">
        <span class="top">
            <span class="av" style="background:var(--green);color:#fff">07</span>
            <span class="t"
                ><b>Kolam C07</b><span>1.300 ekor · umur 70 hari</span></span
            >
        </span>
        <span class="badge ok"
            ><span class="d"></span>Siap panen 5 hari lagi</span
        >
        <span class="mini3">
            <span><em>Berat</em><strong>88,4 g</strong></span>
            <span><em>Total</em><strong>104 kg</strong></span>
            <span><em>Target</em><strong>88%</strong></span>
        </span>
        <div class="progress flat"><i style="width:88%"></i></div>
        <Link class="btn block" href="/panen">Buka kolam ini</Link>
    </div>

    <div class="card pond">
        <span class="top">
            <span class="av">06</span>
            <span class="t"
                ><b>Kolam C06</b><span>1.500 ekor · umur 42 hari</span></span
            >
        </span>
        <span class="badge"><span class="d"></span>Belum waktunya panen</span>
        <span class="mini3">
            <span><em>Berat</em><strong>41,2 g</strong></span>
            <span><em>Total</em><strong>55,6 kg</strong></span>
            <span><em>Target</em><strong>41%</strong></span>
        </span>
        <div class="progress flat"><i style="width:41%"></i></div>
        <button class="btn block" onclick={() => say('Detail kolam C06 dibuka')}>
            Buka kolam ini
        </button>
    </div>

    <div class="card pond">
        <span class="top">
            <span class="av">05</span>
            <span class="t"
                ><b>Kolam C05</b><span>1.600 ekor · umur 21 hari</span></span
            >
        </span>
        <span class="badge"><span class="d"></span>Baru ditebar</span>
        <span class="mini3">
            <span><em>Berat</em><strong>12,8 g</strong></span>
            <span><em>Total</em><strong>19,8 kg</strong></span>
            <span><em>Target</em><strong>13%</strong></span>
        </span>
        <div class="progress flat"><i style="width:13%"></i></div>
        <button class="btn block" onclick={() => say('Detail kolam C05 dibuka')}>
            Buka kolam ini
        </button>
    </div>
</div>
