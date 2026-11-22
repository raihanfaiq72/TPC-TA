<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { say } from '@/lib/aqua-ui.svelte';

    type Task = { title: string; note: string; done: boolean };

    let tasks = $state<Task[]>([
        { title: 'Tentukan target berat', note: '100 gram per ekor', done: true },
        { title: 'Pengecekan sistem', note: '6 November 2026', done: true },
        {
            title: 'Puasa ikan 1 hari',
            note: '12 November · supaya ikan tidak stres',
            done: false,
        },
        {
            title: 'Siapkan jaring & keranjang',
            note: '12 November',
            done: false,
        },
    ]);

    const doneCount = $derived(tasks.filter((task) => task.done).length);

    let target = $state('100');

    function toggleTask(index: number): void {
        tasks[index].done = !tasks[index].done;
        say(
            tasks[index].done
                ? 'Ditandai selesai. Bagus!'
                : 'Ditandai belum selesai.',
        );
    }

    function recalculate(): void {
        say('Target diubah. Perkiraan tanggal dihitung ulang.');
    }
</script>

<AppHead title="Jadwal panen" />

<div class="head">
    <h1>Jadwal panen kolam C07</h1>
    <p class="lead">
        Perkiraan dari sistem, masih bisa kamu ubah sesuai kondisi di kolam.
    </p>
</div>

<div class="status-card">
    <div class="label">Perkiraan tanggal panen</div>
    <div class="big">Kamis, 13 November 2026</div>
    <div class="say">
        Boleh juga panen antara 11–16 November. Umur ikan nanti 75 hari.
    </div>
    <div class="prog-row" style="margin-bottom:0">
        <span>Perkiraan berat 100 gram/ekor</span>
        <span>Total ± 118 kg</span>
    </div>
    <div class="acts">
        <button
            class="btn pri"
            onclick={() => say('Jadwal panen 13 November tersimpan')}
        >
            <svg viewBox="0 0 24 24"><path d="M4.5 12.5 9 17l10.5-10.5" /></svg>
            Konfirmasi tanggal ini
        </button>
        <button
            class="btn"
            onclick={() => say('Pengingat dikirim H-1 sebelum panen')}
        >
            Ingatkan saya H-1
        </button>
    </div>
</div>

<div class="grid gsplit" style="margin-top:16px">
    <div class="card">
        <div class="card-head">
            <div class="grow">
                <h3>Yang perlu disiapkan</h3>
                <p>Tekan tombolnya kalau sudah dikerjakan.</p>
            </div>
            <span class="badge acc">{doneCount} dari {tasks.length}</span>
        </div>
        <div class="stack" style="gap:10px">
            {#each tasks as task, index (task.title)}
                <div class="task" class:done={task.done}>
                    <span class="tic">
                        {#if task.done}
                            <svg viewBox="0 0 24 24"
                                ><path d="M4.5 12.5 9 17l10.5-10.5" /></svg
                            >
                        {:else}
                            <svg viewBox="0 0 24 24"
                                ><path d="M12 5v14M5 12h14" /></svg
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

    <div class="card">
        <div class="card-head">
            <div class="grow">
                <h3>Ubah target</h3>
                <p>Kalau mau ikan lebih besar dulu.</p>
            </div>
        </div>
        <div class="field" style="margin-bottom:14px">
            <label for="f-target">Target berat per ekor</label>
            <div class="unit">
                <input id="f-target" class="big" bind:value={target} />
                <em>gram</em>
            </div>
        </div>
        <button class="btn block" onclick={recalculate}>
            Hitung ulang tanggal
        </button>
        <div class="divider"></div>
        <div class="rows">
            <div class="row-i">
                <div class="t">
                    <b>Perkiraan nilai jual</b
                    ><span>Kalau harga Rp 20.000/kg</span>
                </div>
                <span class="badge acc">± Rp 2,36 juta</span>
            </div>
        </div>
        <p class="tiny muted" style="margin-top:12px">
            Keputusan panen tetap milik kamu. Sistem hanya membantu menghitung.
        </p>
    </div>
</div>
