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

    type AlertRow = {
        waktu: string;
        zona: string | null;
        parameter: string;
        nilai: number;
        tingkat: string;
        status: string;
    };

    let alerts = $state<AlertRow[]>([]);
    let alertOpen = $state(false);
    let alertLoading = $state(false);

    const PAGES: Record<string, [string, string]> = {
        '/dashboard': ['Beranda', 'Semua kolam kamu hari ini'],
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

    const info = $derived(
        path.startsWith('/kolam/')
            ? ['Detail kolam', 'Pantauan perangkat dan kondisi air']
            : (PAGES[path] ?? ['SmartAqua', 'Bantuan panen lele']),
    );

    const pondId = $derived.by(() => {
        const match = path.match(/^\/kolam\/(\d+)/);

        return match ? Number(match[1]) : null;
    });

    const unreadAlerts = $derived(
        alerts.filter((alert) => alert.status === 'baru').length,
    );

    async function loadAlerts(id: number): Promise<void> {
        alertLoading = true;

        try {
            const response = await fetch(`/api/kolam/${id}/alert`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                alerts = [];
                return;
            }

            const rows = (await response.json()) as AlertRow[];
            alerts = rows.filter((alert) => alert.tingkat !== 'aman').slice(0, 20);
        } catch {
            alerts = [];
        } finally {
            alertLoading = false;
        }
    }

    $effect(() => {
        if (pondId === null) {
            alerts = [];
            return;
        }

        void loadAlerts(pondId);
        const timer = setInterval(() => void loadAlerts(pondId), 30000);
        const refresh = (): void => void loadAlerts(pondId);
        window.addEventListener('aqua:refresh-alerts', refresh);

        return () => {
            clearInterval(timer);
            window.removeEventListener('aqua:refresh-alerts', refresh);
        };
    });

    function isActive(href: string): boolean {
        return path === href;
    }

    function logout(): void {
        router.post('/logout');
    }

    function handleKeydown(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            alertOpen = false;
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
                    <span>IPB University</span>
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
            <Link
                href="/kolam"
                class={cn('nav', (isActive('/kolam') || path.startsWith('/kolam/')) && 'on')}
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
                <div class="alert-menu-wrap">
                    <button
                        class="icon-btn"
                        aria-label={`Notifikasi${unreadAlerts > 0 ? ` (${unreadAlerts} baru)` : ''}`}
                        aria-expanded={alertOpen}
                        onclick={() => {
                            alertOpen = !alertOpen;
                            if (alertOpen && pondId !== null) void loadAlerts(pondId);
                        }}
                    >
                        <svg viewBox="0 0 24 24"
                            ><path
                                d="M18 15.5V11a6 6 0 1 0-12 0v4.5L4.5 18h15L18 15.5Z"
                            /><path d="M10 20.5a2 2 0 0 0 4 0" /></svg
                        >
                        {#if unreadAlerts > 0}<span class="dot"></span>{/if}
                    </button>
                    {#if alertOpen}
                        <div class="alert-menu">
                            <div class="alert-menu-head">
                                <div>
                                    <b>Peringatan kolam</b>
                                    <small>{unreadAlerts} belum ditangani</small>
                                </div>
                                <span class="alert-menu-count">{alerts.length}</span>
                            </div>
                            {#if alertLoading}
                                <div class="alert-empty">Memuat alert...</div>
                            {:else if alerts.length === 0}
                                <div class="alert-empty">Tidak ada peringatan aktif.</div>
                            {:else}
                                {#each alerts.slice(0, 8) as alert}
                                    <button class="alert-row {alert.tingkat}" onclick={() => say(`Alert ${alert.parameter} · ${alert.tingkat}`)}>
                                        <span class="alert-status"></span>
                                        <span class="alert-copy">
                                            <b>{alert.parameter.toUpperCase()} · {alert.zona ?? 'kolam'}</b>
                                            <small>{alert.waktu} · nilai {alert.nilai}</small>
                                        </span>
                                        <em>{alert.tingkat}</em>
                                    </button>
                                {/each}
                            {/if}
                            <Link class="alert-menu-foot" href={pondId ? `/kolam/${pondId}` : '/kolam'}>
                                Buka detail kolam →
                            </Link>
                        </div>
                    {/if}
                </div>
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

            <main class="content" class:dashboard-content={path === '/dashboard'}>
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
                                Buka menu "Daftar kolam", tekan "Catat dan
                                timbang" pada kartu kolam, isi jumlah ikan dan
                                berat rata-ratanya, lalu simpan.
                            </p>
                        </div>
                    </div>
                    <div class="how-i">
                        <span class="n">2</span>
                        <div>
                            <b>Lihat hasilnya</b>
                            <p>
                                Grafik dan ringkasan pertumbuhan tersedia di
                                detail kolam. Kamu tidak perlu menghitung.
                            </p>
                        </div>
                    </div>
                    <div class="how-i">
                        <span class="n">3</span>
                        <div>
                            <b>Ikuti rekomendasi panen</b>
                            <p>
                                Rekomendasi panen muncul di detail kolam.
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

