<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { aquaUi, say, toggleWire } from '@/lib/aqua-ui.svelte';

    type Preferences = {
        target: string;
        minOxygen: string;
        phMin: string;
        phMax: string;
        remind: boolean;
        whatsapp: boolean;
        phone: string;
    };

    const STORAGE_KEY = 'aqua-pengaturan';

    const defaults: Preferences = {
        target: '100',
        minOxygen: '3',
        phMin: '6,5',
        phMax: '8,5',
        remind: true,
        whatsapp: false,
        phone: '',
    };

    function readNumber(value: string): number {
        const clean = value.trim().replace(',', '.');

        return clean === '' ? NaN : Number(clean);
    }

    function loadPreferences(): Preferences {
        if (typeof window === 'undefined') {
            return { ...defaults };
        }

        try {
            const raw = window.localStorage.getItem(STORAGE_KEY);

            if (!raw) {
                return { ...defaults };
            }

            const data = JSON.parse(raw) as Partial<Preferences>;

            return {
                target:
                    typeof data.target === 'string'
                        ? data.target
                        : defaults.target,
                minOxygen:
                    typeof data.minOxygen === 'string'
                        ? data.minOxygen
                        : defaults.minOxygen,
                phMin:
                    typeof data.phMin === 'string'
                        ? data.phMin
                        : defaults.phMin,
                phMax:
                    typeof data.phMax === 'string'
                        ? data.phMax
                        : defaults.phMax,
                remind:
                    typeof data.remind === 'boolean'
                        ? data.remind
                        : defaults.remind,
                whatsapp:
                    typeof data.whatsapp === 'boolean'
                        ? data.whatsapp
                        : defaults.whatsapp,
                phone:
                    typeof data.phone === 'string'
                        ? data.phone
                        : defaults.phone,
            };
        } catch {
            return { ...defaults };
        }
    }

    // Data disimpan di perangkat ini karena aplikasi contoh belum punya API preferensi.
    const initial = loadPreferences();
    let form = $state<Preferences>({ ...initial });
    let saved = $state<Preferences>({ ...initial });

    const dirty = $derived(JSON.stringify(form) !== JSON.stringify(saved));

    const error = $derived.by(() => {
        const target = readNumber(form.target);
        const oxygen = readNumber(form.minOxygen);
        const phMin = readNumber(form.phMin);
        const phMax = readNumber(form.phMax);
        const phone = form.phone.replace(/[\s-]/g, '');

        if (!Number.isFinite(target) || target < 20 || target > 1000) {
            return 'Berat target diisi antara 20 sampai 1000 gram.';
        }
        if (!Number.isFinite(oxygen) || oxygen < 0 || oxygen > 14) {
            return 'Oksigen minimal diisi antara 0 sampai 14 mg/L.';
        }
        if (
            !Number.isFinite(phMin) ||
            !Number.isFinite(phMax) ||
            phMin < 4 ||
            phMax > 10 ||
            phMin >= phMax
        ) {
            return 'Rentang pH harus 4–10, dengan batas bawah lebih kecil dari batas atas.';
        }
        if (form.whatsapp && !/^0\d{8,13}$/.test(phone)) {
            return 'Nomor WhatsApp diawali 0 dan berisi 9–14 angka.';
        }

        return '';
    });

    function selectWire(): void {
        toggleWire();
        say(aquaUi.wire ? 'Mode hitam-putih aktif' : 'Mode berwarna aktif');
    }

    function saveSettings(): void {
        if (error || !dirty) {
            return;
        }

        const next: Preferences = {
            target: String(readNumber(form.target)).replace('.', ','),
            minOxygen: String(readNumber(form.minOxygen)).replace('.', ','),
            phMin: form.phMin.trim().replace('.', ','),
            phMax: form.phMax.trim().replace('.', ','),
            remind: form.remind,
            whatsapp: form.whatsapp,
            phone: form.phone.trim(),
        };

        form = next;
        saved = { ...next };

        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
        } catch {
            // localStorage bisa diblokir; pengaturan tetap dipakai selama halaman dibuka.
        }

        say('Pengaturan tersimpan');
    }

    function resetChanges(): void {
        form = { ...saved };
        say('Perubahan dibatalkan');
    }

    function logout(): void {
        router.post('/logout');
    }
</script>

<AppHead title="Pengaturan" />

<div class="head">
    <h1>Pengaturan</h1>
    <p class="lead">
        Atur target panen, batas aman air, dan tampilan laporan. Perubahan
        disimpan di perangkat ini.
    </p>
</div>

<div class="grid g2">
    <div class="card">
        <div class="card-head">
            <div class="grow">
                <h3>Target panen</h3>
                <p>Dipakai untuk menghitung perkiraan tanggal panen.</p>
            </div>
            <span class="badge acc">{saved.target} gram</span>
        </div>

        <div class="field">
            <label for="s-target">Berat ikan yang kamu mau</label>
            <div class="unit">
                <input
                    id="s-target"
                    class="big"
                    inputmode="numeric"
                    bind:value={form.target}
                />
                <em>gram</em>
            </div>
            <span class="hint"
                >Umumnya 100–150 gram untuk lele siap panen.</span
            >
        </div>

        <div class="divider"></div>

        <div class="set">
            <div class="t">
                <b>Ingatkan saya</b>
                <span>Notifikasi 1 hari sebelum tanggal panen.</span>
            </div>
            <button
                class="toggle"
                class:on={form.remind}
                role="switch"
                aria-checked={form.remind}
                aria-label="Ingatkan saya"
                onclick={() => (form.remind = !form.remind)}
            ></button>
        </div>
        <div class="set">
            <div class="t">
                <b>Pesan lewat WhatsApp</b>
                <span>Pengingat dikirim ke nomor yang kamu isi.</span>
            </div>
            <button
                class="toggle"
                class:on={form.whatsapp}
                role="switch"
                aria-checked={form.whatsapp}
                aria-label="Pesan lewat WhatsApp"
                onclick={() => (form.whatsapp = !form.whatsapp)}
            ></button>
        </div>
        {#if form.whatsapp}
            <div class="field spaced">
                <label for="s-phone">Nomor WhatsApp</label>
                <input
                    id="s-phone"
                    type="tel"
                    inputmode="tel"
                    placeholder="0812-xxxx-xxxx"
                    autocomplete="tel"
                    bind:value={form.phone}
                />
                <span class="hint">Mulai dengan 0, tanpa kode negara.</span>
            </div>
        {/if}
    </div>

    <div class="card">
        <div class="card-head">
            <div class="grow">
                <h3>Batas aman air</h3>
                <p>Sistem memberi peringatan saat angka di luar batas.</p>
            </div>
        </div>

        <div class="form">
            <div class="field">
                <label for="s-do">Oksigen minimal</label>
                <div class="unit">
                    <input
                        id="s-do"
                        inputmode="decimal"
                        bind:value={form.minOxygen}
                    />
                    <em>mg/L</em>
                </div>
                <span class="hint">Di bawah ini ikan bisa lemas.</span>
            </div>
            <div class="field">
                <label for="s-ph-min">pH terendah</label>
                <input
                    id="s-ph-min"
                    inputmode="decimal"
                    bind:value={form.phMin}
                />
                <span class="hint">Batas bawah yang masih dianggap aman.</span>
            </div>
            <div class="field">
                <label for="s-ph-max">pH tertinggi</label>
                <input
                    id="s-ph-max"
                    inputmode="decimal"
                    bind:value={form.phMax}
                />
                <span class="hint">Batas atas yang masih dianggap aman.</span>
            </div>
        </div>

        <div class="note acc" style="margin-top:16px">
            <svg viewBox="0 0 24 24"
                ><circle cx="12" cy="12" r="9" /><path
                    d="M12 11v5.5M12 8h.01"
                /></svg
            >
            <div>
                Kalau ragu, biarkan nilai bawaan. Angka ini sudah sesuai
                standar budidaya lele.
            </div>
        </div>

        <div class="divider"></div>
        <h3 style="margin-bottom:6px">Tampilan untuk laporan</h3>
        <div class="set">
            <div class="t">
                <b>Mode hitam-putih</b>
                <span>Untuk dicetak di laporan atau skripsi.</span>
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

{#if error}
    <div class="note bad" style="margin-top:16px">
        <svg viewBox="0 0 24 24"
            ><path d="M12 8v5.5M12 16.5h.01" /><circle
                cx="12"
                cy="12"
                r="9"
            /></svg
        >
        <div>{error}</div>
    </div>
{/if}

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
            <Link class="btn sm" href="/settings/profile" aria-label="Buka pengaturan profil"
                >Buka</Link
            >
            <svg class="chev" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6" /></svg>
        </div>
        <div class="row-i">
            <div class="t">
                <b>Keamanan</b><span>Kata sandi dan verifikasi dua langkah</span>
            </div>
            <Link class="btn sm" href="/settings/security" aria-label="Buka pengaturan keamanan"
                >Buka</Link
            >
            <svg class="chev" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6" /></svg>
        </div>
        <div class="row-i">
            <div class="t">
                <b>Tampilan</b><span>Mode terang atau gelap</span>
            </div>
            <Link class="btn sm" href="/settings/appearance" aria-label="Buka pengaturan tampilan"
                >Buka</Link
            >
            <svg class="chev" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6" /></svg>
        </div>
        <div class="row-i">
            <div class="t"><b>Keluar</b><span>Akhiri sesi di perangkat ini</span></div>
            <button class="btn sm" onclick={logout}>Keluar</button>
        </div>
    </div>
</div>

<div class="stickybar">
    <div class="t">
        <b
            >{dirty
                ? 'Ada perubahan yang belum disimpan'
                : error
                  ? 'Periksa lagi isian kamu'
                  : 'Semua pengaturan tersimpan'}</b
        >
        <span
            >{dirty
                ? 'Tekan simpan agar dipakai saat membuka aplikasi lagi.'
                : error
                  ? error
                  : 'Pengaturan tersimpan di browser perangkat ini.'}</span
        >
    </div>
    {#if dirty}
        <button class="btn" onclick={resetChanges}>Batalkan</button>
    {/if}
    <button
        class="btn pri"
        disabled={!dirty || Boolean(error)}
        onclick={saveSettings}
    >
        <svg viewBox="0 0 24 24"><path d="M4.5 12.5 9 17l10.5-10.5" /></svg>
        Simpan pengaturan
    </button>
</div>
