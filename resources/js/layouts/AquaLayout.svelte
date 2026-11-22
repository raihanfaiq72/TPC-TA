<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import { Toaster } from '@/components/ui/sonner';
    import {
        aquaUi,
        closeHelp,
        openHelp,
        say,
    } from '@/lib/aqua-ui.svelte';
    import { getInitials } from '@/lib/initials';
    import { cn } from '@/lib/utils';

    let { children }: { children?: Snippet } = $props();

    const auth = $derived(page.props.auth);
    const initials = $derived(getInitials(auth?.user?.name) || 'SA');

    const PAGES: Record<string, [string, string]> = {
        '/dashboard': ['Beranda', 'Semua kolam kamu hari ini'],
        '/catat': ['Catat timbang', 'Isi 3 kolom, sisanya otomatis'],
        '/air': ['Kondisi air', 'Kolam C07 · diperbarui 2 menit lalu'],
        '/hasil': ['Hasil panen', 'Perkembangan ikan kolam C07'],
        '/panen': ['Jadwal panen', 'Kolam C07 · perkiraan sistem'],
        '/kolam': ['Daftar kolam', '3 kolam aktif'],
        '/pengaturan': ['Pengaturan', 'Target panen dan batas aman air'],
    };

    const path = $derived.by(() => {
        try {
            const pathname = new URL(page.url, 'http://localhost').pathname;

            return pathname.replace(/\/+$/, '') || '/';
        } catch {
            return page.url;
        }
    });

    const info = $derived(PAGES[path] ?? ['SmartAqua', 'Bantuan panen lele']);

    function isActive(href: string): boolean {
        return path === href;
    }

    function logout(): void {
        router.post('/logout');
    }

    function handleKeydown(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            closeHelp();
        }
    }
</script>

<svelte:window onkeydown={handleKeydown} />

<div class="aqua-app" class:wire={aquaUi.wire}>
    <div class="app">
        <!-- ============ SIDEBAR ============ -->
        <aside class="side">
            <div class="brand">
                <div class="brand-logo">
                    <svg viewBox="0 0 24 24"
                        ><path
                            d="M12 3.2c3.1 3.6 5.6 6.5 5.6 9.6A5.6 5.6 0 0 1 12 18.4a5.6 5.6 0 0 1-5.6-5.6C6.4 9.7 8.9 6.8 12 3.2Z"
                        /><path d="M9.6 13.2c0 1.5 1.1 2.6 2.6 2.6" /></svg
                    >
                </div>
                <div>
                    <b>SmartAqua</b>
                    <span>Bantuan panen lele</span>
                </div>
            </div>

            <Link
                href="/dashboard"
                class={cn('nav', isActive('/dashboard') && 'on')}
            >
                <svg viewBox="0 0 24 24"
                    ><path
                        d="M3.5 10.5 12 4l8.5 6.5V20a1 1 0 0 1-1 1h-4.6v-5.6H9.1V21H4.5a1 1 0 0 1-1-1v-9.5Z"
                    /></svg
                >
                Beranda
            </Link>
            <Link href="/catat" class={cn('nav', isActive('/catat') && 'on')}>
                <svg viewBox="0 0 24 24"
                    ><path
                        d="M4.5 19.5V6a1.5 1.5 0 0 1 1.5-1.5h4.6l2 2.5h5A1.5 1.5 0 0 1 19.5 8.5v11"
                    /><path d="M8.5 13.5h7M12 10v7" /></svg
                >
                Catat timbang
            </Link>
            <Link href="/air" class={cn('nav', isActive('/air') && 'on')}>
                <svg viewBox="0 0 24 24"
                    ><path
                        d="M12 3.5s5.5 5.9 5.5 9.8a5.5 5.5 0 0 1-11 0C6.5 9.4 12 3.5 12 3.5Z"
                    /></svg
                >
                Kondisi air
                <span class="pill">1</span>
            </Link>
            <Link href="/hasil" class={cn('nav', isActive('/hasil') && 'on')}>
                <svg viewBox="0 0 24 24"
                    ><path d="M4 19V5M4 19h16" /><path
                        d="m7.5 15 4-4 3 2.6L19 8"
                    /></svg
                >
                Hasil panen
            </Link>
            <Link href="/panen" class={cn('nav', isActive('/panen') && 'on')}>
                <svg viewBox="0 0 24 24"
                    ><rect x="3.5" y="5.5" width="17" height="14" rx="2.5" /><path
                        d="M3.5 10h17M8 3.5v4M16 3.5v4"
                    /><path d="m9 14.5 2 2 3.8-3.8" /></svg
                >
                Jadwal panen
            </Link>

            <div class="nav-t hide-m">Lainnya</div>
            <Link
                href="/kolam"
                class={cn('nav hide-m', isActive('/kolam') && 'on')}
            >
                <svg viewBox="0 0 24 24"
                    ><path
                        d="M2.5 8.2c1.7 0 1.7 1.4 3.4 1.4s1.7-1.4 3.4-1.4 1.7 1.4 3.4 1.4 1.7-1.4 3.4-1.4 1.7 1.4 3.4 1.4"
                    /><path
                        d="M2.5 13.2c1.7 0 1.7 1.4 3.4 1.4s1.7-1.4 3.4-1.4 1.7 1.4 3.4 1.4 1.7-1.4 3.4-1.4 1.7 1.4 3.4 1.4"
                    /></svg
                >
                Daftar kolam
            </Link>
            <Link
                href="/pengaturan"
                class={cn('nav hide-m', isActive('/pengaturan') && 'on')}
            >
                <svg viewBox="0 0 24 24"
                    ><circle cx="12" cy="12" r="3" /><path
                        d="M19.4 14.5a7.6 7.6 0 0 0 .1-2.5 7.6 7.6 0 0 0-.1-1l1.6-1.3-1.9-3.2-2 .7a7.4 7.4 0 0 0-2.1-1.2L14.6 4h-3.8l-.4 2a7.4 7.4 0 0 0-2.1 1.2l-2-.7L4.4 9.7 6 11a7.6 7.6 0 0 0 0 2L4.4 14.3l1.9 3.2 2-.7a7.4 7.4 0 0 0 2.1 1.2l.4 2h3.8l.4-2a7.4 7.4 0 0 0 2.1-1.2l2 .7 1.9-3.2-1.6-1.3Z"
                    /></svg
                >
                Pengaturan
            </Link>

            <div class="grow"></div>

            <div class="side-note">
                <b>Kolam C07</b>
                <p>Panen sekitar 5 hari lagi. Tetap beri pakan seperti biasa.</p>
            </div>

            <Link class="user" href="/pengaturan">
                <span class="av">{initials}</span>
                <span class="t"
                    ><b>{auth?.user?.name ?? 'Pengguna'}</b><span
                        >Pengelola kolam</span
                    ></span
                >
            </Link>
        </aside>

        <!-- ============ MAIN ============ -->
        <div class="main">
            <header class="topbar">
                <div class="grow">
                    <div class="top-title">{info[0]}</div>
                    <div class="top-sub">{info[1]}</div>
                </div>
                <button class="help-btn" onclick={openHelp}>
                    <svg viewBox="0 0 24 24"
                        ><circle cx="12" cy="12" r="9" /><path
                            d="M9.6 9.2a2.5 2.5 0 1 1 3.6 2.3c-.8.4-1.2 1-1.2 1.9"
                        /><path d="M12 17h.01" /></svg
                    >
                    Bantuan
                </button>
                <button
                    class="icon-btn"
                    aria-label="Notifikasi"
                    onclick={() => say('Tidak ada pesan baru')}
                >
                    <svg viewBox="0 0 24 24"
                        ><path
                            d="M18 15.5V11a6 6 0 1 0-12 0v4.5L4.5 18h15L18 15.5Z"
                        /><path d="M10 20.5a2 2 0 0 0 4 0" /></svg
                    >
                    <span class="dot"></span>
                </button>
                <button class="icon-btn" aria-label="Keluar" onclick={logout}>
                    <svg viewBox="0 0 24 24"
                        ><path d="M14.5 12H4.5" /><path d="m8.5 8-4 4 4 4" /><path
                            d="M11 4.5h6A1.5 1.5 0 0 1 18.5 6v12a1.5 1.5 0 0 1-1.5 1.5h-6"
                        /></svg
                    >
                </button>
                <Link
                    class="av"
                    href="/pengaturan"
                    aria-label="Buka pengaturan"
                    style="cursor:pointer"
                >
                    {initials}
                </Link>
            </header>

            <main class="content">
                {@render children?.()}
            </main>
        </div>
    </div>

    <!-- ============ BANTUAN (MODAL) ============ -->
    {#if aquaUi.helpOpen}
        <div class="modal on">
            <button
                class="modal-backdrop"
                aria-label="Tutup bantuan"
                onclick={closeHelp}
            ></button>
            <div
                class="modal-box"
                role="dialog"
                aria-modal="true"
                aria-labelledby="help-title"
            >
                <div class="modal-head">
                    <div class="grow">
                        <h2 id="help-title">Cara pakai SmartAqua</h2>
                        <p class="small muted">Cukup ingat 3 langkah ini.</p>
                    </div>
                    <button class="close" aria-label="Tutup" onclick={closeHelp}>
                        <svg viewBox="0 0 24 24"
                            ><path d="M6 6l12 12M18 6 6 18" /></svg
                        >
                    </button>
                </div>

                <div class="how">
                    <div class="how-i">
                        <span class="n">1</span>
                        <div>
                            <b>Timbang ikan, lalu catat</b>
                            <p>
                                Buka menu "Catat timbang". Isi jumlah ikan dan
                                berat rata-ratanya, lalu simpan.
                            </p>
                        </div>
                    </div>
                    <div class="how-i">
                        <span class="n">2</span>
                        <div>
                            <b>Lihat hasilnya</b>
                            <p>
                                Menu "Hasil panen" menunjukkan berat ikan dan
                                perkembangannya. Kamu tidak perlu menghitung.
                            </p>
                        </div>
                    </div>
                    <div class="how-i">
                        <span class="n">3</span>
                        <div>
                            <b>Ikuti tanggal panen</b>
                            <p>
                                Menu "Jadwal panen" memberi tanggal perkiraan.
                                Kamu yang memutuskan, sistem hanya membantu.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="divider"></div>
                <h3>Arti istilah yang muncul</h3>
                <dl class="gloss">
                    <dt>Berat rata-rata</dt>
                    <dd>
                        Rata-rata berat satu ekor dari ikan yang kamu timbang.
                        Ini patokan utama menuju panen.
                    </dd>
                    <dt>Pakan jadi daging (FCR)</dt>
                    <dd>
                        Berapa kilo pakan yang habis untuk menghasilkan 1 kilo
                        ikan. Makin kecil, makin untung.
                    </dd>
                    <dt>Oksigen (DO)</dt>
                    <dd>
                        Jumlah oksigen di dalam air. Kalau di bawah 3 mg/L, ikan
                        bisa lemas dan berhenti makan.
                    </dd>
                    <dt>Keasaman (pH)</dt>
                    <dd>
                        Tingkat asam-basa air. Untuk lele, angka 6,5 sampai 8,5
                        masih aman.
                    </dd>
                </dl>

                <div class="note acc" style="margin-top:16px">
                    <svg viewBox="0 0 24 24"
                        ><circle cx="12" cy="12" r="9" /><path
                            d="M12 11v5.5M12 8h.01"
                        /></svg
                    >
                    <div>
                        Semua angka di aplikasi ini masih data contoh untuk
                        pengujian.
                    </div>
                </div>

                <button
                    class="btn pri block"
                    style="margin-top:16px"
                    onclick={closeHelp}
                >
                    Mengerti
                </button>
            </div>
        </div>
    {/if}

    <div
        class="toast"
        class:show={aquaUi.toastVisible}
        role="status"
        aria-live="polite"
    >
        <svg viewBox="0 0 24 24"
            ><path d="M4.5 12.5 9 17l10.5-10.5" /></svg
        >
        <span>{aquaUi.toast}</span>
    </div>

    <Toaster />
</div>

