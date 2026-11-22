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
└── Lingkungan/
    ├── folder.yml              # metadata folder
    ├── Lihat Lingkungan.yml    # GET /api/kolam/{id}/lingkungan
    ├── Tick Lingkungan.yml     # POST /api/kolam/{id}/lingkungan/tick
    ├── Tambah Amonia.yml       # POST /api/kolam/{id}/lingkungan/amonia
    └── Tambah Sisa Pakan.yml   # POST /api/kolam/{id}/lingkungan/sisa-pakan
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
- **Hapus Kolam** memakai `{{kolamId}}` dan menampilkan response `200`,
  `404`, atau `409` bila data turunan masih menghalangi penghapusan.

## Generate API Docs di Bruno

1. Buka collection **SmartAqua API**.
2. Buka tab/menu **Docs** pada collection.
3. Bruno akan merangkum deskripsi dari blok `docs:` pada tiap request
   (Ping, Kolam, dan Lingkungan).
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
