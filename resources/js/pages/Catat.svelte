<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { say } from '@/lib/aqua-ui.svelte';

    type RowStatus = 'ok' | 'err' | 'warn';
    type Row = { no: number; weight: string; status: RowStatus };

    let pond = $state('C07 — umur 70 hari');
    let weighDate = $state('08/11/2026');
    let fishCount = $state('25');
    let averageWeight = $state('88,4');

    let rows = $state<Row[]>([
        { no: 1, weight: '86,5 g', status: 'ok' },
        { no: 2, weight: '91,2 g', status: 'ok' },
        { no: 3, weight: '-3,0 g', status: 'err' },
        { no: 4, weight: '880 g', status: 'warn' },
        { no: 5, weight: '89,7 g', status: 'ok' },
    ]);

    function removeRow(no: number): void {
        rows = rows.filter((row) => row.no !== no);
        say(`Baris ${no} dibuang`);
    }

    function fixRow(no: number): void {
        rows = rows.map((row) =>
            row.no === no ? { ...row, weight: '88,0 g', status: 'ok' } : row,
        );
        say(`Baris ${no} diubah jadi 88,0 g`);
    }

    function saveDraft(): void {
        say('Disimpan sebagai draft, bisa dilanjutkan nanti');
    }

    function saveAll(): void {
        say(
            `${fishCount} data timbangan tersimpan. Perkiraan panen diperbarui.`,
        );
    }
</script>

<AppHead title="Catat timbang" />

<div class="head">
    <h1>Catat hasil timbang</h1>
    <p class="lead">
        Cukup isi 3 kolom. Sistem menghitung sendiri rata-rata, total berat
        ikan, dan sisa hari menuju panen.
    </p>
</div>

<div class="steps">
    <span class="step on"><span class="n">1</span> Isi data</span>
    <span class="step-line"></span>
    <span class="step"><span class="n">2</span> Periksa</span>
    <span class="step-line"></span>
    <span class="step"><span class="n">3</span> Simpan</span>
</div>

<div class="card">
    <div class="card-head">
        <div class="grow">
            <h3>Data timbangan hari ini</h3>
            <p>Kolom umur dan total ikan terisi otomatis.</p>
        </div>
    </div>

    <div class="form">
        <div class="field">
            <label for="f-kolam">Ikan dari kolam</label>
            <select id="f-kolam" bind:value={pond}>
                <option>C07 — umur 70 hari</option>
                <option>C06 — umur 42 hari</option>
                <option>C05 — umur 21 hari</option>
            </select>
            <span class="hint">Pilih kolam yang ikan-nya kamu timbang.</span>
        </div>

        <div class="field">
            <label for="f-tgl">Tanggal timbang</label>
            <input id="f-tgl" bind:value={weighDate} />
            <span class="hint">Biasanya hari ini.</span>
        </div>

        <div class="field">
            <label for="f-jml">Jumlah ikan ditimbang</label>
            <div class="unit">
                <input
                    id="f-jml"
                    class="big"
                    inputmode="numeric"
                    bind:value={fishCount}
                />
                <em>ekor</em>
            </div>
            <span class="hint"
                >Makin banyak, makin akurat. Minimal 10 ekor.</span
            >
        </div>

        <div class="field">
            <label for="f-berat">Berat rata-rata seekor</label>
            <div class="unit">
                <input
                    id="f-berat"
                    class="big"
                    inputmode="decimal"
                    bind:value={averageWeight}
                />
                <em>gram</em>
            </div>
            <span class="hint">Contoh: 88,4 — pakai koma untuk desimal.</span>
        </div>
    </div>

    <div class="divider"></div>

    <div class="stack">
        <div class="note acc">
            <svg viewBox="0 0 24 24"><path d="M4.5 12.5 9 17l10.5-10.5" /></svg>
            <div>
                <b>23 dari 25 angka sudah oke.</b> Ada 2 yang perlu kamu lihat
                dulu.
            </div>
        </div>

        <div class="tbl">
            <table>
                <thead>
                    <tr>
                        <th>Ikan ke-</th>
                        <th class="num">Berat</th>
                        <th>Apa yang salah</th>
                    </tr>
                </thead>
                <tbody>
                    {#each rows as row (row.no)}
                        <tr
                            class:err={row.status === 'err'}
                            class:warn={row.status === 'warn'}
                        >
                            <td>{row.no}</td>
                            <td class="num">{row.weight}</td>
                            <td>
                                {#if row.status === 'err'}
                                    <span
                                        class="inline-msg"
                                        style="color:var(--bad)"
                                    >
                                        Berat tidak boleh minus.
                                        <button
                                            class="btn sm"
                                            onclick={() => removeRow(row.no)}
                                        >
                                            Hapus baris
                                        </button>
                                    </span>
                                {:else if row.status === 'warn'}
                                    <span
                                        class="inline-msg"
                                        style="color:var(--warn)"
                                    >
                                        Kelebihan satu nol. Mungkin 88,0?
                                        <button
                                            class="btn sm"
                                            onclick={() => fixRow(row.no)}
                                        >
                                            Ya, betulkan
                                        </button>
                                    </span>
                                {:else}
                                    <span class="inline-msg muted">Aman</span>
                                {/if}
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
        <p class="tiny muted">
            5 baris pertama ditampilkan. 20 baris lainnya sudah aman semua.
        </p>
    </div>
</div>

<div class="stickybar">
    <div class="t">
        <b>Sudah selesai? Tekan simpan.</b>
        <span
            >Data ini akan dipakai untuk memperbarui perkiraan tanggal
            panen.</span
        >
    </div>
    <button class="btn" onclick={saveDraft}>Simpan nanti</button>
    <button class="btn pri" onclick={saveAll}>
        <svg viewBox="0 0 24 24"><path d="M4.5 12.5 9 17l10.5-10.5" /></svg>
        Simpan {fishCount} data
    </button>
</div>

