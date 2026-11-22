# SmartAqua API — Bruno Collection

Collection ini memakai format **OpenCollection YAML** (Bruno 3+).

> Catatan kompatibilitas: field `info.version` sengaja tidak dipakai karena
> memicu bug parser di Bruno VS Code Extension lama
> (`Unable to parse opencollection.yml: bruno.json format is invalid`).
> Field itu opsional di spec OpenCollection.

## Struktur

```text
Docs/API/
├── opencollection.yml          # metadata collection
├── environments/
│   └── Local.yml               # baseUrl + kolamId + token
├── Ping.yml                    # GET /api/ping
├── Kolam/
│   ├── folder.yml              # metadata folder
│   ├── Index kolam.yml         # GET /api/kolam
│   ├── Show Kolam.yml          # GET /api/kolam/{id}
│   ├── create-kolam.yml        # POST /api/kolam
│   └── Hapus Kolam.yml         # DELETE /api/kolam/{id}
├── Lingkungan/
│   ├── folder.yml              # metadata folder
│   ├── Lihat Lingkungan.yml    # GET /api/kolam/{id}/lingkungan
│   ├── Tick Lingkungan.yml     # POST /api/kolam/{id}/lingkungan/tick
│   ├── Tambah Amonia.yml       # POST /api/kolam/{id}/lingkungan/amonia
│   └── Tambah Sisa Pakan.yml   # POST /api/kolam/{id}/lingkungan/sisa-pakan
├── Ikan/
│   ├── folder.yml              # metadata folder + config
│   ├── Lihat Ikan.yml          # GET /api/kolam/{id}/ikan
│   ├── Gerak Ikan.yml          # POST /api/kolam/{id}/ikan/gerak
│   └── Proses Harian Ikan.yml  # POST /api/kolam/{id}/ikan/proses-harian
├── Sampling/
│   ├── folder.yml              # metadata + status implementasi
│   ├── Ambil Sampel.yml        # GET /api/kolam/{id}/sampel
│   ├── Daftar Sampling.yml     # GET /api/kolam/{id}/sampling
│   ├── Simpan Sampling.yml     # POST /api/kolam/{id}/sampling
│   ├── Detail Sampling.yml     # GET /api/kolam/{id}/sampling/{samplingId}
│   ├── Ubah Sampling.yml       # PUT/PATCH /api/kolam/{id}/sampling/{samplingId}
│   └── Hapus Sampling.yml      # DELETE /api/kolam/{id}/sampling/{samplingId}
├── Analisis/
│   ├── folder.yml              # metadata + catatan analisis
│   ├── Daftar Alert.yml        # GET /api/kolam/{id}/alert
│   ├── Rekomendasi Terbaru.yml # GET /api/kolam/{id}/rekomendasi
│   └── Ringkasan Validasi.yml  # GET /api/kolam/{id}/validasi
└── Sensor/
    ├── folder.yml              # metadata + aturan pembacaan
    ├── Baca Sensor.yml         # POST /api/kolam/{id}/sensor/baca
    ├── Pembacaan Terbaru.yml   # GET /api/kolam/{id}/sensor/terbaru
    └── Riwayat Pembacaan.yml   # GET /api/kolam/{id}/sensor/riwayat
```

## Cara membuka

1. Buka aplikasi **Bruno**.
2. Pilih **Open Collection**.
3. Arahkan ke folder `Docs/API` (bukan ke file `opencollection.yml`).
4. Pilih environment **Local** di pojok kanan atas.
5. Jalankan `Ping` → `Kolam → Daftar Kolam` → `Detail Kolam`.
6. Jalankan `Buat Kolam`; variable `kolamId` otomatis terisi dari response.
7. Buka folder `Lingkungan` → `Lihat Lingkungan` → `Tambah Amonia` /
   `Tambah Sisa Pakan` → `Tick Lingkungan`.
8. Buka folder `Ikan` → `Lihat Ikan` → `Gerak Ikan` (beberapa kali) →
   `Proses Harian Ikan`.
9. Buka folder `Sampling` → `Ambil Sampel` → `Simpan Sampling` →
   `Daftar Sampling` → `Detail Sampling`; gunakan `Ubah/Hapus Sampling`
   bila perlu.
10. Buka folder `Analisis` → `Daftar Alert` → `Rekomendasi Terbaru` →
    `Ringkasan Validasi`.
11. Buka folder `Sensor` → `Baca Sensor` → `Pembacaan Terbaru` →
    `Riwayat Pembacaan`.

Kalau `baseUrl` berbeda, ubah di:

```yaml
# Docs/API/environments/Local.yml
name: Local
variables:
  - name: baseUrl
    value: http://localhost:8000
  - name: kolamId
    value: "1"
```

## Endpoint

| Method | Path | Nama request | Keterangan |
|---|---|---|---|
| GET | `/api/ping` | Ping | Cek status API |
| GET | `/api/kolam` | Daftar Kolam | Semua kolam + jumlah ikan hidup & zona |
| GET | `/api/kolam/{id}` | Detail Kolam | Kolam + zona + perangkat + jadwal pakan |
| POST | `/api/kolam` | Buat Kolam | Provisioning kolam + zona + perangkat + ikan |
| DELETE | `/api/kolam/{id}` | Hapus Kolam | Hapus kolam beserta data turunannya |
| GET | `/api/kolam/{id}/lingkungan` | Lihat Lingkungan | Keadaan suhu, pH, DO, amonia, sisa pakan per zona |
| POST | `/api/kolam/{id}/lingkungan/tick` | Tick Lingkungan | Jalankan satu langkah simulasi lingkungan |
| POST | `/api/kolam/{id}/lingkungan/amonia` | Tambah Amonia | Tambah beban amonia ke semua zona |
| POST | `/api/kolam/{id}/lingkungan/sisa-pakan` | Tambah Sisa Pakan | Tambah sisa pakan ke zona atas |
| GET | `/api/kolam/{id}/ikan` | Lihat Ikan | Ringkasan populasi + daftar ikan |
| POST | `/api/kolam/{id}/ikan/gerak` | Gerak Ikan | Pergerakan zona, pengosongan lambung, akumulasi paparan |
| POST | `/api/kolam/{id}/ikan/proses-harian` | Proses Harian Ikan | Pertumbuhan, stres, kematian, log harian, reset |
| GET | `/api/kolam/{id}/sampel` | Ambil Sampel | Ambil n ikan hidup secara acak |
| GET | `/api/kolam/{id}/sampling` | Daftar Sampling | Riwayat sampling per kolam |
| POST | `/api/kolam/{id}/sampling` | Simpan Sampling | Simpan sampling + evaluasi rekomendasi panen |
| GET | `/api/kolam/{id}/sampling/{samplingId}` | Detail Sampling | Detail satu record sampling |
| PUT/PATCH | `/api/kolam/{id}/sampling/{samplingId}` | Ubah Sampling | Update sampling + evaluasi ulang |
| DELETE | `/api/kolam/{id}/sampling/{samplingId}` | Hapus Sampling | Hapus record sampling |
| GET | `/api/kolam/{id}/alert` | Daftar Alert | 200 alert terbaru per kolam |
| GET | `/api/kolam/{id}/rekomendasi` | Rekomendasi Terbaru | Rekomendasi panen terakhir |
| GET | `/api/kolam/{id}/validasi` | Ringkasan Validasi | Validasi kondisi populasi ikan |
| POST | `/api/kolam/{id}/sensor/baca` | Baca Sensor | Baca perangkat yang jatuh tempo |
| GET | `/api/kolam/{id}/sensor/terbaru` | Pembacaan Terbaru | Pembacaan terakhir tiap perangkat |
| GET | `/api/kolam/{id}/sensor/riwayat` | Riwayat Pembacaan | Riwayat sensor terfilter |

> **Route Sampling sudah aktif** dan `AnalisisController` sudah tersedia,
> sehingga `Simpan Sampling` dapat mengembalikan rekomendasi panen.

API otomatis memakai prefix `/api` dari `bootstrap/app.php`. Alias lama
`/api/sandbox/kolam` masih tersedia agar koleksi sebelumnya tetap jalan.

## Fitur request

- **Daftar Kolam** memastikan response berupa array.
- **Detail Kolam** memakai path parameter `{{kolamId}}` dan memeriksa
  `kolam`, `zona`, `perangkat`, serta `jadwal_pakan`.
- **Buat Kolam** membuat kode unik otomatis (`C` + 6 digit timestamp)
  lewat pre-request script, jadi bisa dijalankan berulang.
- Assertion Buat Kolam:
  - status `201`
  - `kode` berupa string
  - `id` dan `volume_air_m3` berupa number
- ID hasil pembuatan kolam disimpan ke variable `kolamId`
  (post-response action) untuk dipakai request selanjutnya.
- **Lihat Lingkungan** memeriksa `kolam`, array `zona`, dan array
  `skenario`, termasuk keadaan serta profil lingkungan tiap zona.
- **Tick Lingkungan** menjalankan satu langkah simulasi pada `waktu`
  yang dikirim. Assertion memastikan status `200` dan
  `diperbarui_pada` terisi.
- **Tambah Amonia** menambahkan `gram_tan` ke semua zona dan memeriksa
  `amonia_asli` berupa number.
- **Tambah Sisa Pakan** menambahkan `gram` ke zona atas dan memeriksa
  `sisa_pakan_g` berupa number.
- **Lihat Ikan** menampilkan `ringkasan` populasi dan array `ikan`
  beserta relasi `zona`.
- **Gerak Ikan** menjalankan satu tick pergerakan dan memeriksa
  `ringkasan.jumlah_hidup` serta array `ikan`.
- **Proses Harian Ikan** memproses pertumbuhan/kematian dan reset
  akumulator; assertion memeriksa `ringkasan.jumlah_total` dan array
  `ikan`.
- **Ambil Sampel** mengambil `n` ikan hidup secara acak dan memeriksa
  `jumlah_sampel` serta array `ikan`.
- **Simpan Sampling** mengirim jumlah/bobot/panjang sampel dan memeriksa
  `tanggal` hasil response. Rekomendasi panen dibuat oleh
  `AnalisisController`.
- **Daftar Sampling** memakai response array dan otomatis menyimpan
  `samplingId` dari baris pertama.
- **Detail/Ubah/Hapus Sampling** memakai `{{samplingId}}`; update
  mendukung `PUT` maupun `PATCH`.
- **Baca Sensor** memicu perangkat yang jatuh tempo dan memeriksa
  `jumlah_dibaca`.
- **Pembacaan Terbaru** memeriksa response berupa array.
- **Riwayat Pembacaan** memakai query `parameter`, `zona`, `dari`, dan
  `sampai`, lalu memeriksa array hasil.
- **Daftar Alert**, **Rekomendasi Terbaru**, dan **Ringkasan Validasi**
  tersedia sebagai endpoint analisis tambahan hasil implementasi
  `AnalisisController`.
- **Daftar Alert** memeriksa response berupa array.
- **Rekomendasi Terbaru** memeriksa `status` rekomendasi terakhir.
- **Ringkasan Validasi** memeriksa `ringkasan.awal` dan array
  `penyebab_mati`.
- **Hapus Kolam** memakai `{{kolamId}}` dan menampilkan response `200`,
  `404`, atau `409` bila data turunan masih menghalangi penghapusan.

## Generate API Docs di Bruno

1. Buka collection **SmartAqua API**.
2. Buka tab/menu **Docs** pada collection.
3. Bruno akan merangkum deskripsi dari blok `docs:` pada tiap request
   (Ping, Kolam, Lingkungan, Ikan, Sampling, Analisis, dan Sensor).
4. Gunakan **Publish / Export Docs** di Bruno untuk membagikan dokumentasi.


## Sebelum mencoba endpoint kolam

Pastikan database sudah dimigrasi dan master data tersedia:

```bash
php artisan migrate --seed
```

Lalu jalankan server:

```bash
php artisan serve
```

**Catatan:** endpoint sandbox saat ini publik dengan throttle 30 request/menit.
Setelah Sanctum dipasang, tambahkan `auth:sanctum` pada group route dan isi
variable `token` di environment `Local`.

**Catatan Lingkungan:** request **Tick Lingkungan** membutuhkan
`config/sandbox.php` dengan key `tick_menit` dan `lingkungan`. Tanpa config
tersebut API mengembalikan `503` dengan pesan
`Konfigurasi sandbox.lingkungan belum tersedia.` Request **Lihat Lingkungan**,
**Tambah Amonia**, dan **Tambah Sisa Pakan** tetap dapat dipakai tanpa config.

**Catatan Ikan:** request **Gerak Ikan** dan **Proses Harian Ikan**
membutuhkan `config/sandbox.php` dengan key `tick_menit` dan `ikan`. Tanpa
config tersebut API mengembalikan `503` dengan pesan
`Konfigurasi sandbox.ikan belum tersedia.` Request **Lihat Ikan** tetap dapat
dipakai tanpa config. Jalankan **Gerak Ikan** minimal sekali sebelum
**Proses Harian Ikan** agar ikan memiliki tick paparan.
