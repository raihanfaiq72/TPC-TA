<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { aquaUi, say, toggleWire } from '@/lib/aqua-ui.svelte';

    let target = $state('100');
    let minOxygen = $state('3');
    let phRange = $state('6,5 – 8,5');
    let remind = $state(true);
    let whatsapp = $state(true);

    function selectWire(): void {
        toggleWire();
        say(aquaUi.wire ? 'Mode hitam-putih aktif' : 'Mode berwarna aktif');
    }

    function saveSettings(): void {
        say('Pengaturan tersimpan');
    }

    function logout(): void {
        router.post('/logout');
    }
</script>

<AppHead title="Pengaturan" />

<div class="head">
    <h1>Pengaturan</h1>
    <p class="lead">Sesuaikan sesuai kebiasaanmu di kolam.</p>
</div>

<div class="grid g2">
    <div class="card">
        <div class="card-head"><div class="grow"><h3>Target panen</h3></div></div>
        <div class="field" style="margin-bottom:16px">
            <label for="s-target">Berat ikan yang kamu mau</label>
            <div class="unit">
                <input id="s-target" class="big" bind:value={target} />
                <em>gram</em>
            </div>
            <span class="hint"
                >Dipakai untuk menghitung tanggal panen. Umumnya 100–150
                gram.</span
            >
        </div>
        <div class="set">
            <div class="t">
                <b>Ingatkan saya</b
                ><span>Notifikasi 1 hari sebelum tanggal panen.</span>
            </div>
            <button
                class="toggle"
                class:on={remind}
                role="switch"
                aria-checked={remind}
                aria-label="Ingatkan saya"
                onclick={() => {
                    remind = !remind;
                    say(remind ? 'Diaktifkan' : 'Dimatikan');
                }}
            ></button>
        </div>
        <div class="set">
            <div class="t">
                <b>Pesan lewat WhatsApp</b
                ><span>Pengingat dikirim ke nomor 0812-xxxx-xxxx.</span>
            </div>
            <button
                class="toggle"
                class:on={whatsapp}
                role="switch"
                aria-checked={whatsapp}
                aria-label="Pesan lewat WhatsApp"
                onclick={() => {
                    whatsapp = !whatsapp;
                    say(whatsapp ? 'Diaktifkan' : 'Dimatikan');
                }}
            ></button>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><div class="grow"><h3>Batas aman air</h3></div></div>
        <div class="form">
            <div class="field">
                <label for="s-do">Oksigen minimal</label>
                <div class="unit">
                    <input id="s-do" bind:value={minOxygen} /><em>mg/L</em>
                </div>
                <span class="hint"
                    >Di bawah ini sistem akan memperingatkan.</span
                >
            </div>
            <div class="field">
                <label for="s-ph">Keasaman (pH) ideal</label>
                <input id="s-ph" bind:value={phRange} />
                <span class="hint">Umumnya 6,5 sampai 8,5.</span>
            </div>
        </div>
        <div class="note acc" style="margin-top:16px">
            <svg viewBox="0 0 24 24"
                ><circle cx="12" cy="12" r="9" /><path
                    d="M12 11v5.5M12 8h.01"
                /></svg
            >
            <div>
                Kalau ragu, biarkan saja. Nilai ini sudah sesuai standar
                budidaya lele.
            </div>
        </div>
        <div class="divider"></div>
        <h3 style="margin-bottom:6px">Tampilan untuk laporan</h3>
        <div class="set">
            <div class="t">
                <b>Mode hitam-putih</b
                ><span>Untuk dicetak di laporan atau skripsi.</span>
            </div>
            <button
                class="toggle"
                class:on={aquaUi.wire}
                role="switch"
                aria-checked={aquaUi.wire}
                aria-label="Mode hitam-putih"
                onclick={selectWire}
            ></button>
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="card-head">
        <div class="grow">
            <h3>Akun</h3>
            <p>Profil, keamanan, dan tampilan akunmu.</p>
        </div>
    </div>
    <div class="rows">
        <div class="row-i">
            <div class="t"><b>Profil</b><span>Nama dan alamat email</span></div>
            <Link class="btn sm" href="/settings/profile">Buka</Link>
        </div>
        <div class="row-i">
            <div class="t">
                <b>Keamanan</b><span>Kata sandi dan verifikasi dua langkah</span>
            </div>
            <Link class="btn sm" href="/settings/security">Buka</Link>
        </div>
        <div class="row-i">
            <div class="t">
                <b>Tampilan</b><span>Mode terang atau gelap</span>
            </div>
            <Link class="btn sm" href="/settings/appearance">Buka</Link>
        </div>
        <div class="row-i">
            <div class="t">
                <b>Keluar</b><span>Akhiri sesi di perangkat ini</span>
            </div>
            <button class="btn sm" onclick={logout}>Keluar</button>
        </div>
    </div>
</div>

<div class="stickybar">
    <div class="t">
        <b>Sudah pas?</b
        ><span>Perubahan langsung dipakai di semua halaman.</span>
    </div>
    <button class="btn pri" onclick={saveSettings}>Simpan pengaturan</button>
</div>

