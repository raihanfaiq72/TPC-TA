<?php

return [

    // 1 tick = 10 menit waktu virtual
    'tick_menit' => 10,
    'maks_tick_per_eksekusi' => 720,
    // Batas waktu per request; frontend melanjutkan batch berikutnya.
    'maks_detik_per_eksekusi' => 15,

    'lingkungan' => [
        'puncak_suhu_jam' => 15,    // suhu tertinggi ±15:00, terendah ±03:00 (P2: pagi 29, sore 32)
        'suhu_relaksasi_per_tick' => 0.20,  // kelembaman termal air
        'do_jenuh_ref' => 9.1,   // DO jenuh pada 20°C
        'do_jenuh_per_derajat' => 0.14,  // DO jenuh = ref - per_derajat*(T-20)
        'rea_atas_per_hari' => 8.0,   // laju reaerasi permukaan
        'rea_bawah_per_hari' => 3.0,
        'resp_per_kg_m3_jam' => 0.03,  // mg/L per jam per (kg biomassa / m3) — sudah efektif utk lele (napas udara)
        'resp_koef_suhu' => 0.05,  // +5% per °C di atas 28
        'busuk_do_per_g_m3_jam' => 0.002, // DO terpakai oleh sisa pakan membusuk
        'sisa_pakan_peluruhan_tick' => 0.01,
        'sisa_pakan_mengendap_tick' => 0.05,  // sisa pakan apung turun ke zona bawah
        'amonia_dari_pakan_dimakan' => 0.0012, // g TAN efektif per g pakan dimakan (P1: <0,15 -> 1,5 ppm dalam 6 minggu)
        'amonia_dari_sisa_busuk' => 0.005,
        'amonia_dari_bangkai' => 0.01,   // g per g bobot ikan mati
        'amonia_hilang_per_hari' => 0.40,   // nitrifikasi/penguapan/ganti air parsial (efektif)
        'ph_relaksasi_per_tick' => 0.05,
        'ph_turun_per_ppm_amonia' => 0.33,
        'campur_antar_zona' => 0.02,   // pencampuran DO & amonia atas<->bawah per tick
    ],

    'ikan' => [
        'kapasitas_lambung_bobot' => 0.035,  // lambung penuh = 3,5% bobot ikan
        'jam_lambung_kosong' => 8,      // lambung penuh kosong dalam ± 8 jam
        'peluang_pindah_tick' => 0.30,   // peluang tiap ikan "mempertimbangkan" pindah zona
        'bonus_naik_dekat_pakan' => 0.35,   // ±30 menit dari jam pakan
        'bonus_naik_lapar' => 0.20,
        'bonus_naik_do_bawah_rendah' => 0.50,   // DO bawah < ambang
        'ambang_do_bawah' => 3.0,
        'penalti_naik_suhu_atas' => 0.20,   // suhu atas > ambang
        'ambang_suhu_atas' => 31.0,
        'kebutuhan_pakan_harian' => 0.05,   // 5% bobot/hari (P1 & P3) = ransum ideal
        // SGR_maks(W) = sgr_ref * (W / bobot_ref)^(-eksponen)  [% per hari]
        'sgr_ref' => 7.0,
        'bobot_ref_g' => 8.0,
        'sgr_eksponen' => 0.30,
        'pemulihan_stres_per_hari' => 5.0,
        'peluang_mati_dasar' => 0.0003, // per ikan per hari
        'peluang_mati_stres_maks' => 0.02,   // tambahan saat stres 100
        'ambang_stres_mati' => 70,
        'peluang_kanibal' => 0.003,
        'rasio_ukuran_kanibal' => 0.6,    // ikan < 60% ikan terbesar dan lapar = rentan dimangsa (P1)
    ],

    'sensor' => [
        'baterai_turun_per_baca' => 0.002,
    ],

    'pakan' => [
        // 'sampling' = dosis dihitung dari hasil sampling mingguan (yang kamu isi di form)
        // 'sandbox'  = dosis dihitung dari biomassa sebenarnya (tanpa input manusia)
        'dosis_berdasar' => 'sampling',
    ],

    'rekomendasi' => [
        'bobot_skor' => 0.7,   // skor_kesiapan = 0.7*skor_bobot + 0.3*skor_air
        'air_skor' => 0.3,
        'hampir_siap' => 0.85,  // >= 85% bobot target = "hampir siap"
        'jendela_air_hari' => 7,
    ],
];
