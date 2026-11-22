<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { say } from '@/lib/aqua-ui.svelte';

    type Task = {
        title: string;
        note: string;
        done: boolean;
        icon: 'feed' | 'weigh' | 'check';
    };

    let tasks = $state<Task[]>([
        {
            title: 'Beri pakan sore',
            note: '3,4 kg · biasanya jam 16:00',
            done: false,
            icon: 'feed',
        },
        {
            title: 'Timbang 25 ekor ikan',
            note: 'Terakhir ditimbang 2 hari lalu',
            done: false,
            icon: 'weigh',
        },
        {
            title: 'Beri pakan pagi',
            note: '1,8 kg · tercatat 06:30',
            done: true,
            icon: 'check',
        },
    ]);

    const doneCount = $derived(tasks.filter((task) => task.done).length);

    function toggleTask(index: number): void {
        tasks[index].done = !tasks[index].done;
        say(
            tasks[index].done
                ? 'Ditandai selesai. Bagus!'
                : 'Ditandai belum selesai.',
        );
    }
</script>

<AppHead title="Beranda" />

<div class="status-card">
    <div class="label">Kolam C07 · hari ini, 8 November</div>
    <div class="big">Panen sekitar 5 hari lagi</div>
    <div class="say">
        Ikan sudah 88% dari target. Belum perlu panen — tetap beri pakan
        seperti biasa.
    </div>
    <div class="prog-row">
        <span>Berat sekarang 88,4 gram</span>
        <span>Target 100 gram</span>
    </div>
    <div class="progress"><i style="width:88%"></i></div>
    <div class="acts">
        <Link class="btn pri" href="/panen">
            <svg viewBox="0 0 24 24"
                ><rect x="3.5" y="5.5" width="17" height="14" rx="2.5" /><path
                    d="M3.5 10h17M8 3.5v4M16 3.5v4"
                /></svg
            >
            Lihat jadwal panen
        </Link>
        <Link class="btn" href="/catat">
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
            Catat hasil timbang
        </Link>
    </div>
</div>

<div class="head" style="margin-top:24px">
    <h2>Mau ngapain sekarang?</h2>
    <p class="lead">
        Pilih salah satu. Tiga hal ini yang paling sering dipakai.
    </p>
</div>

<div class="grid g3">
    <Link class="action" href="/catat">
        <span class="aic"
            ><svg viewBox="0 0 24 24"
                ><path
                    d="M4.5 7.5h15l-1.6 12a1.5 1.5 0 0 1-1.5 1.3H7.6a1.5 1.5 0 0 1-1.5-1.3l-1.6-12Z"
                /><path d="M9 7.5V6a3 3 0 0 1 6 0v1.5" /><path
                    d="M12 11v6"
                /></svg
            ></span
        >
        <span class="t">
            <b>Timbang &amp; catat ikan</b>
            <span>Catat berat ikan hari ini</span>
        </span>
        <svg class="chev" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6" /></svg>
    </Link>

    <Link class="action" href="/air">
        <span class="aic"
            ><svg viewBox="0 0 24 24"
                ><path
                    d="M12 3.5s5.5 5.9 5.5 9.8a5.5 5.5 0 0 1-11 0C6.5 9.4 12 3.5 12 3.5Z"
                /></svg
            ></span
        >
        <span class="t">
            <b>Cek kondisi air</b>
            <span>Suhu &amp; oksigen kolam</span>
        </span>
        <svg class="chev" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6" /></svg>
    </Link>

    <Link class="action" href="/panen">
        <span class="aic"
            ><svg viewBox="0 0 24 24"
                ><rect x="3.5" y="5.5" width="17" height="14" rx="2.5" /><path
                    d="M3.5 10h17M8 3.5v4M16 3.5v4"
                /><path d="m9 14.5 2 2 3.8-3.8" /></svg
            ></span
        >
        <span class="t">
            <b>Lihat kapan panen</b>
            <span>Tanggal perkiraan panen</span>
        </span>
        <svg class="chev" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6" /></svg>
    </Link>
</div>

<div class="grid gsplit" style="margin-top:24px">
    <div class="card">
        <div class="card-head">
            <div class="grow">
                <h3>Tugas hari ini</h3>
                <p>Tekan "Selesai" kalau sudah dikerjakan.</p>
            </div>
            <span class="badge">{doneCount} dari {tasks.length} selesai</span>
        </div>
        <div class="stack" style="gap:10px">
            {#each tasks as task, index (task.title)}
                <div class="task" class:done={task.done}>
                    <span class="tic">
                        {#if task.icon === 'feed'}
                            <svg viewBox="0 0 24 24"
                                ><path
                                    d="M5 8h14l-1.4 11.2a1.5 1.5 0 0 1-1.5 1.3H7.9a1.5 1.5 0 0 1-1.5-1.3L5 8Z"
                                /><path d="M9 8V6.5a3 3 0 0 1 6 0V8" /></svg
                            >
                        {:else if task.icon === 'weigh'}
                            <svg viewBox="0 0 24 24"
                                ><path
                                    d="M4.5 7.5h15l-1.6 12a1.5 1.5 0 0 1-1.5 1.3H7.6a1.5 1.5 0 0 1-1.5-1.3l-1.6-12Z"
                                /><path d="M9 7.5V6a3 3 0 0 1 6 0v1.5" /></svg
                            >
                        {:else}
                            <svg viewBox="0 0 24 24"
                                ><path d="M4.5 12.5 9 17l10.5-10.5" /></svg
                            >
                        {/if}
                    </span>
                    <span class="t"
                        ><b>{task.title}</b><span>{task.note}</span></span
                    >
                    <button
                        class="btn sm"
                        class:soft={task.done}
                        onclick={() => toggleTask(index)}
                    >
                        {task.done ? 'Selesai' : 'Catat'}
                    </button>
                </div>
            {/each}
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-head">
                <div class="grow">
                    <h3>Air kolam sekarang</h3>
                    <p>Diperbarui 2 menit lalu</p>
                </div>
            </div>
            <div class="rows">
                <div class="row-i">
                    <div class="t">
                        <b>Suhu 28,4 °C</b><span>Nyaman untuk ikan</span>
                    </div>
                    <span class="badge ok"><span class="d"></span>Normal</span>
                </div>
                <div class="row-i">
                    <div class="t">
                        <b>Oksigen 2,6 mg/L</b
                        ><span>Terlalu rendah, ikan bisa lemas</span>
                    </div>
                    <span class="badge warn"><span class="d"></span>Rendah</span>
                </div>
                <div class="row-i">
                    <div class="t">
                        <b>Keasaman (pH)</b
                        ><span>Alat belum kirim angka jam 14:00</span>
                    </div>
                    <span class="badge bad"
                        ><span class="d"></span>Belum ada</span
                    >
                </div>
            </div>
            <Link class="btn block" style="margin-top:16px" href="/air">
                Buka kondisi air
            </Link>
        </div>

        <Link class="card pond" href="/kolam">
            <span class="top">
                <span class="av">06</span>
                <span class="t"
                    ><b>Kolam C06</b
                    ><span>Umur 42 hari · belum waktunya panen</span></span
                >
                <svg
                    style="width:16px;height:16px;stroke:var(--muted);fill:none;stroke-width:2.2"
                    viewBox="0 0 24 24"><path d="m9 6 6 6-6 6" /></svg
                >
            </span>
            <span class="mini3">
                <span><em>Berat</em><strong>41,2 g</strong></span>
                <span><em>Total</em><strong>55,6 kg</strong></span>
                <span><em>Target</em><strong>41%</strong></span>
            </span>
        </Link>
    </div>
</div>

