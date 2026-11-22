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
└── Kolam/
    ├── folder.yml              # metadata folder
    ├── Index kolam.yml         # GET /api/kolam
    ├── Show Kolam.yml          # GET /api/kolam/{id}
    ├── create-kolam.yml        # POST /api/kolam (valid)
    └── create-kolam-invalid.yml# POST /api/kolam (422)
```

## Cara membuka

1. Buka aplikasi **Bruno**.
2. Pilih **Open Collection**.
3. Arahkan ke folder `Docs/API` (bukan ke file `opencollection.yml`).
4. Pilih environment **Local** di pojok kanan atas.
5. Jalankan `Ping` → `Kolam → Daftar Kolam` → `Detail Kolam`.
6. Jalankan `Buat Kolam`; variable `kolamId` otomatis terisi dari response.

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
| POST | `/api/kolam` | Buat Kolam (validasi gagal) | Contoh response 422 |

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
- **Buat Kolam (validasi gagal)** memastikan API mengembalikan `422`
  beserta `errors` per field.

## Generate API Docs di Bruno

1. Buka collection **SmartAqua API**.
2. Buka tab/menu **Docs** pada collection.
3. Bruno akan merangkum deskripsi dari blok `docs:` pada tiap request
   (Ping, Daftar Kolam, Detail Kolam, Buat Kolam, dan contoh validasi).
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
