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
│   └── Local.yml               # baseUrl + token
├── Ping.yml                    # GET /api/ping
└── Sandbox/
    ├── folder.yml              # metadata folder
    ├── create-kolam.yml        # POST /api/sandbox/kolam (valid)
    └── create-kolam-invalid.yml# POST /api/sandbox/kolam (422)
```

## Cara membuka

1. Buka aplikasi **Bruno**.
2. Pilih **Open Collection**.
3. Arahkan ke folder `Docs/API` (bukan ke file `opencollection.yml`).
4. Pilih environment **Local** di pojok kanan atas.
5. Jalankan `Ping` terlebih dahulu, lalu `Sandbox → Buat Kolam`.

Kalau `baseUrl` berbeda, ubah di:

```yaml
# Docs/API/environments/Local.yml
name: Local
variables:
  - name: baseUrl
    value: http://127.0.0.1:8000
```

## Endpoint

| Method | Path | Nama request | Keterangan |
|---|---|---|---|
| GET | `/api/ping` | Ping | Cek status API |
| POST | `/api/sandbox/kolam` | Buat Kolam | Provisioning kolam + zona + perangkat + ikan |
| POST | `/api/sandbox/kolam` | Buat Kolam (validasi gagal) | Contoh response 422 |

API otomatis memakai prefix `/api` dari `bootstrap/app.php`.

## Fitur request

- **Buat Kolam** membuat kode unik otomatis (`C` + 6 digit timestamp)
  lewat pre-request script, jadi bisa dijalankan berulang.
- Assertion bawaan:
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
   (Ping, Buat Kolam, dan contoh validasi sudah terisi).
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
