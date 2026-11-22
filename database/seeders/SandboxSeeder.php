<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\AturanAmbang;
use App\Models\Ikan;
use App\Models\IkanLogHarian;
use App\Models\JadwalPakan;
use App\Models\JenisPakan;
use App\Models\JenisPerangkat;
use App\Models\KeadaanZona;
use App\Models\Kolam;
use App\Models\LogPakan;
use App\Models\PembacaanSensor;
use App\Models\Perangkat;
use App\Models\ProfilLingkungan;
use App\Models\RekomendasiPanen;
use App\Models\SamplingPertumbuhan;
use App\Models\SkenarioSimulasi;
use App\Models\ZonaKolam;
use Illuminate\Database\Seeder;

/**
 * Contoh data lengkap satu kolam sandbox (C07) untuk semua tabel.
 *
 * Seeder ini idempoten: kolam contoh C07 dihapus lebih dahulu, lalu
 * dibuat ulang beserta seluruh turunannya (cascade).
 */
class SandboxSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            JenisPerangkatSeeder::class,
            JenisPakanSeeder::class,
            AturanAmbangSeeder::class,
        ]);

        Kolam::query()->where('kode', 'C07')->delete();

        $kolam = $this->buatKolam();
        [$atas, $bawah] = $this->buatZona($kolam);

        $this->buatProfilLingkungan($kolam, $atas, $bawah);
        $this->buatKeadaanZona($kolam, $atas, $bawah);
        $perangkat = $this->buatPerangkat($kolam, $atas, $bawah);
        $this->buatPembacaanSensor($kolam, $perangkat, $atas, $bawah);
        $this->buatPakan($kolam, $perangkat['feeder']);

        $ikans = $this->buatIkan($kolam, $atas, $bawah);
        $this->buatLogHarian($ikans);

        $this->buatSkenario($kolam);
        $this->buatSampling($kolam);
        $this->buatAlert($kolam, $atas, $bawah);
        $this->buatRekomendasi($kolam);
    }

    private function buatKolam(): Kolam
    {
        return Kolam::create([
            'kode' => 'C07',
            'nama' => 'Kolam C07 — Lele Sangkuriang',
            'jenis' => 'terpal',
            'panjang_m' => 3,
            'lebar_m' => 2,
            'tinggi_air_m' => 1.5,
            'jumlah_ikan_awal' => 1300,
            'bobot_awal_rata_g' => 1.2,
            'bobot_awal_sd_g' => 0.15,
            'panjang_awal_rata_cm' => 3.5,
            'koef_a' => 0.0152,
            'biomassa_awal_g' => 1560,
            'padat_tebar_per_m3' => 144.44,
            'dosis_harian_persen' => 5,
            'bobot_target_panen_g' => 100,
            'waktu_mulai_sandbox' => '2026-09-01 06:00:00',
            'waktu_sandbox_sekarang' => '2026-11-08 14:00:00',
            'kecepatan_waktu' => 60,
            'status' => 'aktif',
        ]);
    }

    /**
     * @return array{0: ZonaKolam, 1: ZonaKolam}
     */
    private function buatZona(Kolam $kolam): array
    {
        $atas = $kolam->zonas()->create([
            'nama' => 'atas',
            'kedalaman_cm' => 50,
            'porsi_volume' => 0.40,
        ]);

        $bawah = $kolam->zonas()->create([
            'nama' => 'bawah',
            'kedalaman_cm' => 100,
            'porsi_volume' => 0.60,
        ]);

        return [$atas, $bawah];
    }

    private function buatProfilLingkungan(
        Kolam $kolam,
        ZonaKolam $atas,
        ZonaKolam $bawah,
    ): void {
        $profil = [
            [$atas, ['suhu' => [28.0, 2.0, 0.4], 'ph' => [7.20, 0, 0.10], 'do' => [5.0, 0, 0.30], 'amonia' => [0.20, 0, 0.05]]],
            [$bawah, ['suhu' => [28.0, 2.0, -0.4], 'ph' => [7.20, 0, -0.10], 'do' => [5.0, 0, -0.30], 'amonia' => [0.20, 0, -0.05]]],
        ];

        foreach ($profil as [$zona, $parameter]) {
            foreach ($parameter as $nama => [$dasar, $amplitudo, $offset]) {
                ProfilLingkungan::create([
                    'kolam_id' => $kolam->id,
                    'zona_id' => $zona->id,
                    'parameter' => $nama,
                    'nilai_dasar' => $dasar,
                    'amplitudo_harian' => $amplitudo,
                    'offset_zona' => $offset,
                ]);
            }
        }
    }

    private function buatKeadaanZona(
        Kolam $kolam,
        ZonaKolam $atas,
        ZonaKolam $bawah,
    ): void {
        KeadaanZona::create([
            'zona_id' => $atas->id,
            'kolam_id' => $kolam->id,
            'suhu_asli' => 28.6,
            'ph_asli' => 7.25,
            'do_asli' => 2.6,
            'amonia_asli' => 0.42,
            'sisa_pakan_g' => 12.5,
            'diperbarui_pada' => '2026-11-08 14:00:00',
        ]);

        KeadaanZona::create([
            'zona_id' => $bawah->id,
            'kolam_id' => $kolam->id,
            'suhu_asli' => 28.2,
            'ph_asli' => 7.30,
            'do_asli' => 2.4,
            'amonia_asli' => 0.55,
            'sisa_pakan_g' => 18.2,
            'diperbarui_pada' => '2026-11-08 14:00:00',
        ]);
    }

    /**
     * @return array{zona: array<string, array<string, Perangkat>>, feeder: Perangkat, level: Perangkat}
     */
    private function buatPerangkat(
        Kolam $kolam,
        ZonaKolam $atas,
        ZonaKolam $bawah,
    ): array {
        $jenis = JenisPerangkat::query()->get()->keyBy('kode');
        $hasil = ['zona' => []];

        foreach (['atas' => $atas, 'bawah' => $bawah] as $namaZona => $zona) {
            foreach (['suhu', 'ph', 'do', 'amonia'] as $parameter) {
                $hasil['zona'][$namaZona][$parameter] = Perangkat::create([
                    'kode' => 'C07-'.strtoupper($namaZona).'-'.strtoupper($parameter),
                    'kolam_id' => $kolam->id,
                    'zona_id' => $zona->id,
                    'jenis_perangkat_id' => $jenis[$parameter]->id,
                    'status' => 'aktif',
                    'interval_menit' => $jenis[$parameter]->interval_default_menit,
                    'terakhir_baca' => '2026-11-08 14:00:00',
                    'baterai_persen' => match ($parameter) {
                        'suhu' => 92,
                        'ph' => 88,
                        'do' => 81,
                        default => 76,
                    },
                    'tanggal_kalibrasi' => '2026-10-01',
                ]);
            }
        }

        $hasil['feeder'] = Perangkat::create([
            'kode' => 'C07-FEEDER-01',
            'kolam_id' => $kolam->id,
            'zona_id' => null,
            'jenis_perangkat_id' => $jenis['feeder']->id,
            'status' => 'aktif',
            'interval_menit' => 0,
            'terakhir_baca' => '2026-11-08 14:20:00',
            'baterai_persen' => 100,
            'tanggal_kalibrasi' => '2026-09-15',
        ]);

        $hasil['level'] = Perangkat::create([
            'kode' => 'C07-LEVEL-01',
            'kolam_id' => $kolam->id,
            'zona_id' => null,
            'jenis_perangkat_id' => $jenis['level']->id,
            'status' => 'aktif',
            'interval_menit' => 30,
            'terakhir_baca' => '2026-11-08 14:00:00',
            'baterai_persen' => 78,
            'tanggal_kalibrasi' => '2026-10-05',
        ]);

        return $hasil;
    }

    /**
     * @param  array{zona: array<string, array<string, Perangkat>>, feeder: Perangkat, level: Perangkat}  $perangkat
     */
    private function buatPembacaanSensor(
        Kolam $kolam,
        array $perangkat,
        ZonaKolam $atas,
        ZonaKolam $bawah,
    ): void {
        $jenis = JenisPerangkat::query()->get()->keyBy('kode');
        $zona = ['atas' => $atas, 'bawah' => $bawah];

        $sampel = [
            '06:00' => [
                'atas' => ['suhu' => 27.8, 'ph' => 7.20, 'do' => 4.8, 'amonia' => 0.18],
                'bawah' => ['suhu' => 27.4, 'ph' => 7.30, 'do' => 4.2, 'amonia' => 0.22],
            ],
            '10:00' => [
                'atas' => ['suhu' => 28.2, 'ph' => 7.15, 'do' => 3.6, 'amonia' => 0.29],
                'bawah' => ['suhu' => 27.9, 'ph' => 7.25, 'do' => 3.1, 'amonia' => 0.36],
            ],
            '14:00' => [
                'atas' => ['suhu' => 28.6, 'ph' => 7.25, 'do' => 2.6, 'amonia' => 0.42],
                'bawah' => ['suhu' => 28.2, 'ph' => null, 'do' => 2.4, 'amonia' => 0.55],
            ],
        ];

        foreach ($sampel as $jam => $baris) {
            foreach ($zona as $namaZona => $zonaModel) {
                foreach ($baris[$namaZona] as $parameter => $nilai) {
                    $device = $perangkat['zona'][$namaZona][$parameter];

                    PembacaanSensor::create([
                        'perangkat_id' => $device->id,
                        'kolam_id' => $kolam->id,
                        'zona_id' => $zonaModel->id,
                        'parameter' => $parameter,
                        'nilai' => $nilai,
                        'satuan' => $jenis[$parameter]->satuan,
                        'waktu_ukur' => "2026-11-08 {$jam}:00",
                        'waktu_diterima' => "2026-11-08 {$jam}:02",
                        'kualitas_data' => $nilai === null ? 'gagal' : 'normal',
                        'baterai_persen' => (int) $device->baterai_persen,
                        'sinyal_rssi' => -62,
                    ]);
                }
            }
        }

        // Contoh pembacaan outlier (sensor DO melonjak tidak wajar).
        PembacaanSensor::create([
            'perangkat_id' => $perangkat['zona']['atas']['do']->id,
            'kolam_id' => $kolam->id,
            'zona_id' => $atas->id,
            'parameter' => 'do',
            'nilai' => 18.9,
            'satuan' => $jenis['do']->satuan,
            'waktu_ukur' => '2026-11-08 12:30:00',
            'waktu_diterima' => '2026-11-08 12:31:00',
            'kualitas_data' => 'outlier',
            'baterai_persen' => 81,
            'sinyal_rssi' => -66,
        ]);

        // Contoh pembacaan sensor level air (perangkat tingkat kolam).
        foreach ([['09:00', 96.2], ['14:00', 95.8]] as [$jam, $nilai]) {
            PembacaanSensor::create([
                'perangkat_id' => $perangkat['level']->id,
                'kolam_id' => $kolam->id,
                'zona_id' => null,
                'parameter' => 'level',
                'nilai' => $nilai,
                'satuan' => $jenis['level']->satuan,
                'waktu_ukur' => "2026-11-08 {$jam}:00",
                'waktu_diterima' => "2026-11-08 {$jam}:02",
                'kualitas_data' => 'normal',
                'baterai_persen' => 78,
                'sinyal_rssi' => -58,
            ]);
        }
    }

    private function buatPakan(Kolam $kolam, Perangkat $feeder): void
    {
        $pakan = JenisPakan::query()
            ->where('merek', 'Provit 781-3')
            ->firstOrFail();

        $jadwal = collect([
            ['jam' => '07:00:00', 'porsi' => 30],
            ['jam' => '12:00:00', 'porsi' => 30],
            ['jam' => '16:00:00', 'porsi' => 40],
        ])->map(fn (array $baris) => JadwalPakan::create([
            'kolam_id' => $kolam->id,
            'jenis_pakan_id' => $pakan->id,
            'jam' => $baris['jam'],
            'porsi_dosis_persen' => $baris['porsi'],
            'aktif' => true,
        ]));

        $log = [
            ['waktu' => '2026-11-07 07:00:00', 'jumlah' => 1600, 'jadwal' => 0, 'pemicu' => 'jadwal'],
            ['waktu' => '2026-11-07 12:00:00', 'jumlah' => 1600, 'jadwal' => 1, 'pemicu' => 'jadwal'],
            ['waktu' => '2026-11-07 16:05:00', 'jumlah' => 2200, 'jadwal' => 2, 'pemicu' => 'jadwal'],
            ['waktu' => '2026-11-08 07:00:00', 'jumlah' => 1600, 'jadwal' => 0, 'pemicu' => 'jadwal'],
            ['waktu' => '2026-11-08 12:00:00', 'jumlah' => 1600, 'jadwal' => 1, 'pemicu' => 'jadwal'],
            ['waktu' => '2026-11-08 14:20:00', 'jumlah' => 600, 'jadwal' => null, 'pemicu' => 'manual'],
        ];

        foreach ($log as $baris) {
            LogPakan::create([
                'kolam_id' => $kolam->id,
                'perangkat_id' => $feeder->id,
                'jadwal_id' => $baris['jadwal'] === null
                    ? null
                    : $jadwal[$baris['jadwal']]->id,
                'jenis_pakan_id' => $pakan->id,
                'waktu' => $baris['waktu'],
                'jumlah_g' => $baris['jumlah'],
                'pemicu' => $baris['pemicu'],
            ]);
        }
    }

    /**
     * @return array<int, Ikan>
     */
    private function buatIkan(
        Kolam $kolam,
        ZonaKolam $atas,
        ZonaKolam $bawah,
    ): array {
        $contoh = [
            ['kode' => 'C07-001', 'zona' => 'atas', 'genetik' => 1.05, 'pref' => 0.72, 'bobot' => 91.4, 'panjang' => 18.1, 'isi' => 22, 'stres' => 6, 'mati' => null],
            ['kode' => 'C07-002', 'zona' => 'atas', 'genetik' => 0.98, 'pref' => 0.45, 'bobot' => 87.2, 'panjang' => 17.9, 'isi' => 10, 'stres' => 9, 'mati' => null],
            ['kode' => 'C07-003', 'zona' => 'atas', 'genetik' => 1.10, 'pref' => 0.80, 'bobot' => 94.1, 'panjang' => 18.3, 'isi' => 30, 'stres' => 5, 'mati' => null],
            ['kode' => 'C07-004', 'zona' => 'atas', 'genetik' => 1.02, 'pref' => 0.60, 'bobot' => 89.8, 'panjang' => 18.0, 'isi' => 0, 'stres' => 8, 'mati' => null],
            ['kode' => 'C07-005', 'zona' => 'atas', 'genetik' => 0.95, 'pref' => 0.30, 'bobot' => 86.5, 'panjang' => 17.8, 'isi' => 15, 'stres' => 10, 'mati' => null],
            ['kode' => 'C07-006', 'zona' => 'atas', 'genetik' => 1.07, 'pref' => 0.70, 'bobot' => 90.3, 'panjang' => 18.1, 'isi' => 25, 'stres' => 7, 'mati' => null],
            ['kode' => 'C07-007', 'zona' => 'bawah', 'genetik' => 1.01, 'pref' => 0.55, 'bobot' => 85.9, 'panjang' => 17.7, 'isi' => 18, 'stres' => 12, 'mati' => null],
            ['kode' => 'C07-008', 'zona' => 'bawah', 'genetik' => 1.04, 'pref' => 0.40, 'bobot' => 88.4, 'panjang' => 18.0, 'isi' => 8, 'stres' => 9, 'mati' => null],
            ['kode' => 'C07-009', 'zona' => 'bawah', 'genetik' => 0.93, 'pref' => 0.25, 'bobot' => 83.2, 'panjang' => 17.6, 'isi' => 0, 'stres' => 14, 'mati' => null],
            ['kode' => 'C07-010', 'zona' => 'bawah', 'genetik' => 1.08, 'pref' => 0.65, 'bobot' => 92.0, 'panjang' => 18.2, 'isi' => 35, 'stres' => 6, 'mati' => null],
            ['kode' => 'C07-011', 'zona' => 'bawah', 'genetik' => 0.99, 'pref' => 0.35, 'bobot' => 87.6, 'panjang' => 17.9, 'isi' => 12, 'stres' => 11, 'mati' => null],
            ['kode' => 'C07-012', 'zona' => 'bawah', 'genetik' => 0.90, 'pref' => 0.20, 'bobot' => 84.8, 'panjang' => 17.6, 'isi' => 0, 'stres' => 18, 'mati' => '2026-11-06'],
        ];

        $hasil = [];

        foreach ($contoh as $index => $baris) {
            $jamAtas = (int) round($baris['pref'] * 24);

            $hasil[] = Ikan::create([
                'kolam_id' => $kolam->id,
                'kode' => $baris['kode'],
                'faktor_genetik' => $baris['genetik'],
                'preferensi_zona' => $baris['pref'],
                'bobot_awal_g' => 1.20 + ($index % 4) * 0.03,
                'panjang_awal_cm' => 3.50 + ($index % 3) * 0.03,
                'bobot_g' => $baris['bobot'],
                'panjang_cm' => $baris['panjang'],
                'zona_id' => $baris['zona'] === 'atas' ? $atas->id : $bawah->id,
                'isi_lambung_persen' => $baris['isi'],
                'stres' => $baris['stres'],
                'status' => $baris['mati'] === null ? 'hidup' : 'mati',
                'tanggal_mati' => $baris['mati'],
                'penyebab_mati' => $baris['mati'] === null ? null : 'stres_suhu',
                'hari_tick_atas' => $jamAtas,
                'hari_tick_bawah' => 24 - $jamAtas,
                'hari_sum_suhu' => 28.0 * 24,
                'hari_sum_ph' => 7.22 * 24,
                'hari_sum_do' => 3.6 * 24,
                'hari_sum_amonia' => 0.35 * 24,
                'hari_pakan_g' => $baris['mati'] === null ? 78.5 : 61.0,
            ]);
        }

        return $hasil;
    }

    /**
     * @param  array<int, Ikan>  $ikans
     */
    private function buatLogHarian(array $ikans): void
    {
        $tanggal = [
            '2026-11-06' => 2,
            '2026-11-07' => 1,
            '2026-11-08' => 0,
        ];

        foreach ($ikans as $ikan) {
            if ($ikan->status === 'mati') {
                continue;
            }

            foreach ($tanggal as $tgl => $mundur) {
                $maju = 2 - $mundur;

                IkanLogHarian::create([
                    'ikan_id' => $ikan->id,
                    'tanggal_sandbox' => $tgl,
                    'bobot_g' => round($ikan->bobot_g - $mundur * 1.1, 3),
                    'panjang_cm' => round($ikan->panjang_cm - $mundur * 0.06, 3),
                    'sgr_hari_persen' => 2.10 + $maju * 0.05,
                    'jam_di_atas' => round($ikan->preferensi_zona * 24, 2),
                    'jam_di_bawah' => round((1 - $ikan->preferensi_zona) * 24, 2),
                    'suhu_terpapar' => 27.8 + $maju * 0.2,
                    'ph_terpapar' => 7.20 + $maju * 0.02,
                    'do_terpapar' => 4.6 - $maju * 0.9,
                    'amonia_terpapar' => 0.20 + $maju * 0.12,
                    'pakan_dimakan_g' => 4.2 + $maju * 0.4,
                    'faktor_ransum' => 1.02 + $maju * 0.015,
                    'stres' => min(100, $ikan->stres + $maju * 2),
                ]);
            }
        }
    }

    private function buatSkenario(Kolam $kolam): void
    {
        SkenarioSimulasi::create([
            'kolam_id' => $kolam->id,
            'jenis_kejadian' => 'hujan_malam',
            'besar_dampak' => -3.0,
            'mulai' => '2026-11-05 22:00:00',
            'selesai' => '2026-11-06 05:00:00',
            'sudah_diterapkan' => true,
        ]);

        SkenarioSimulasi::create([
            'kolam_id' => $kolam->id,
            'jenis_kejadian' => 'aerator_mati',
            'besar_dampak' => 0.8,
            'mulai' => '2026-11-08 12:00:00',
            'selesai' => '2026-11-08 15:00:00',
            'sudah_diterapkan' => false,
        ]);

        SkenarioSimulasi::create([
            'kolam_id' => $kolam->id,
            'jenis_kejadian' => 'ganti_air',
            'besar_dampak' => 0.25,
            'mulai' => '2026-11-07 08:00:00',
            'selesai' => '2026-11-07 08:30:00',
            'sudah_diterapkan' => true,
        ]);
    }

    private function buatSampling(Kolam $kolam): void
    {
        $sampling = [
            ['tanggal' => '2026-10-15', 'jumlah' => 20, 'total' => 700.00, 'panjang' => 13.2],
            ['tanggal' => '2026-10-25', 'jumlah' => 20, 'total' => 1060.00, 'panjang' => 15.0],
            ['tanggal' => '2026-11-01', 'jumlah' => 25, 'total' => 1750.00, 'panjang' => 16.6],
            ['tanggal' => '2026-11-06', 'jumlah' => 25, 'total' => 2210.00, 'panjang' => 18.0],
        ];

        foreach ($sampling as $baris) {
            SamplingPertumbuhan::create([
                'kolam_id' => $kolam->id,
                'tanggal' => $baris['tanggal'],
                'jumlah_sampel' => $baris['jumlah'],
                'bobot_total_sampel_g' => $baris['total'],
                'panjang_rata_cm' => $baris['panjang'],
            ]);
        }
    }

    private function buatAlert(
        Kolam $kolam,
        ZonaKolam $atas,
        ZonaKolam $bawah,
    ): void {
        $doKritis = AturanAmbang::query()
            ->where('parameter', 'do')
            ->where('tingkat', 'kritis')
            ->firstOrFail();
        $amoniaWaspada = AturanAmbang::query()
            ->where('parameter', 'amonia')
            ->where('tingkat', 'waspada')
            ->firstOrFail();
        $suhuAman = AturanAmbang::query()
            ->where('parameter', 'suhu')
            ->where('tingkat', 'aman')
            ->firstOrFail();

        Alert::create([
            'kolam_id' => $kolam->id,
            'zona_id' => $atas->id,
            'aturan_id' => $doKritis->id,
            'parameter' => 'do',
            'nilai' => 2.6,
            'tingkat' => 'kritis',
            'waktu' => '2026-11-08 14:00:00',
            'status' => 'baru',
        ]);

        Alert::create([
            'kolam_id' => $kolam->id,
            'zona_id' => $bawah->id,
            'aturan_id' => $amoniaWaspada->id,
            'parameter' => 'amonia',
            'nilai' => 0.55,
            'tingkat' => 'waspada',
            'waktu' => '2026-11-08 14:00:00',
            'status' => 'baru',
        ]);

        Alert::create([
            'kolam_id' => $kolam->id,
            'zona_id' => $atas->id,
            'aturan_id' => $suhuAman->id,
            'parameter' => 'suhu',
            'nilai' => 28.6,
            'tingkat' => 'aman',
            'waktu' => '2026-11-08 14:00:00',
            'status' => 'ditangani',
        ]);
    }

    private function buatRekomendasi(Kolam $kolam): void
    {
        RekomendasiPanen::create([
            'kolam_id' => $kolam->id,
            'tanggal_evaluasi' => '2026-10-25 06:00:00',
            'bobot_rata_g' => 53.0,
            'skor_bobot' => 42.0,
            'skor_air' => 70.0,
            'skor_kesiapan' => 50.0,
            'estimasi_hari_ke_target' => 20,
            'status' => 'belum siap',
            'alasan' => 'Bobot rata-rata masih 53 gram dari target 100 gram. Pertumbuhan baik, lanjutkan pakan dan pantau air.',
        ]);

        RekomendasiPanen::create([
            'kolam_id' => $kolam->id,
            'tanggal_evaluasi' => '2026-11-06 06:00:00',
            'bobot_rata_g' => 85.1,
            'skor_bobot' => 64.0,
            'skor_air' => 55.0,
            'skor_kesiapan' => 59.0,
            'estimasi_hari_ke_target' => 7,
            'status' => 'hampir siap',
            'alasan' => 'Bobot mendekati target, tetapi oksigen air mulai rendah. Perbaiki aerasi sebelum panen.',
        ]);

        RekomendasiPanen::create([
            'kolam_id' => $kolam->id,
            'tanggal_evaluasi' => '2026-11-08 14:00:00',
            'bobot_rata_g' => 88.4,
            'skor_bobot' => 70.0,
            'skor_air' => 50.0,
            'skor_kesiapan' => 63.0,
            'estimasi_hari_ke_target' => 5,
            'status' => 'hampir siap',
            'alasan' => 'Tinggal 12 gram menuju target. Oksigen 2,6 mg/L perlu dinaikkan; perkiraan panen 5 hari lagi.',
        ]);
    }
}
