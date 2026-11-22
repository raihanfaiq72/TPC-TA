# SmartAqua

Aplikasi pemantauan kolam berbasis Laravel 13, Inertia.js, Svelte 5, dan MariaDB. Aplikasi menyediakan peta kolam, perangkat IoT, sensor realtime, sandbox pertumbuhan ikan, riwayat, foto kolam, edit kolam, dan reset siklus.

## Persyaratan

- PHP 8.3 atau lebih baru
- Composer 2
- Node.js 22 LTS
- npm
- MariaDB 10.6+ atau MySQL 8+
- Ekstensi PHP:
  - `pdo_mysql`
  - `mbstring`
  - `xml`
  - `curl`
  - `zip`
  - `bcmath`
  - `intl`

## 1. Siapkan database MariaDB

Masuk ke MariaDB:

```bash
mysql -u root -p
```

Buat database dan pengguna:

```sql
CREATE DATABASE smartaqua
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'smartaqua'@'localhost'
    IDENTIFIED BY 'password-kuat';

GRANT ALL PRIVILEGES ON smartaqua.*
    TO 'smartaqua'@'localhost';

FLUSH PRIVILEGES;
```

Hentikan MariaDB bila perlu melalui service manager OS, tetapi pastikan MariaDB aktif kembali sebelum menjalankan aplikasi.

## 2. Clone repository

```bash
git clone https://github.com/raihanfaiq72/TPC-TA.git
cd TPC-TA
```

## 3. Buat file environment

Linux dan macOS:

```bash
cp .env.example .env
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Windows Command Prompt:

```bat
copy .env.example .env
```

Ubah bagian database pada `.env`:

```env
APP_NAME=SmartAqua
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smartaqua
DB_USERNAME=smartaqua
DB_PASSWORD=password-kuat
```

## 4. Instalasi per OS

### macOS

Gunakan Homebrew:

```bash
brew install php composer node mariadb
brew services start mariadb
```

Jika PHP tidak memiliki ekstensi MariaDB:

```bash
brew install php
```

Pastikan `php -m` memuat `pdo_mysql`.

### Ubuntu/Debian

```bash
sudo apt update
sudo apt install mariadb-server mariadb-client \
  php8.3 php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl \
  composer nodejs npm

sudo systemctl enable mariadb
sudo systemctl start mariadb
sudo mysql_secure_installation
```

Periksa PHP setelah instalasi:

```bash
php -m | grep -E 'pdo_mysql|mbstring|bcmath|intl'
```

Untuk versi PHP lain, sesuaikan nama paket PHP dengan versi yang tersedia, tetapi tetap gunakan PHP minimal 8.3.

### Fedora/RHEL

```bash
sudo dnf install mariadb-server mariadb \
  php php-cli php-mysqlnd php-mbstring php-xml \
  php-curl php-bcmath php-intl composer nodejs npm

sudo systemctl enable mariadb
sudo systemctl start mariadb
```

### Arch Linux

```bash
sudo pacman -S mariadb php php-composer nodejs npm
sudo mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql
sudo systemctl enable mariadb
sudo systemctl start mariadb
```

### Windows

Cara termudah adalah Laragon:

1. Install Laragon.
2. Aktifkan PHP 8.3 atau lebih baru.
3. Aktifkan MariaDB.
4. Install Node.js 22 LTS.
5. Install Composer.
6. Tambahkan PHP, Composer, Node.js, dan npm ke `PATH`.

Alternatif WAMP atau XAMPP dapat digunakan jika MariaDB, PHP 8.3, Composer, dan Node.js tersedia.

## 5. Install dependency

Jalankan di folder repository:

```bash
composer install
npm install
```

Untuk instalasi dependency frontend yang tepat berdasarkan lock file:

```bash
npm ci
```

## 6. Inisialisasi aplikasi

Generate application key:

```bash
php artisan key:generate
```

Buat struktur tabel:

```bash
php artisan migrate
```

Isi data awal, termasuk kolam C07, ikan, zona, perangkat, jenis pakan, dan aturan ambang:

```bash
php artisan db:seed
```

Untuk instalasi dari nol:

```bash
php artisan migrate:fresh --seed
```

Perintah `migrate:fresh --seed` menghapus semua tabel dan mengisi ulang database. Jangan jalankan pada database produksi.

Buat link untuk upload foto kolam:

```bash
php artisan storage:link
```

Bersihkan cache setelah mengubah `.env`:

```bash
php artisan optimize:clear
```

## 7. Jalankan aplikasi

### Cara pertama

Terminal pertama:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal kedua:

```bash
npm run dev
```

Buka:

```text
http://localhost:8000
```

Halaman daftar kolam:

```text
http://localhost:8000/kolam
```

Contoh detail kolam sandbox:

```text
http://localhost:8000/kolam/9
```

Nomor kolam dapat berbeda pada database baru. Gunakan endpoint berikut untuk melihat daftar kolam:

```text
http://localhost:8000/api/kolam
```

### Cara Laravel development command

```bash
composer run dev
```

Cara ini menjalankan server Laravel, queue, dan Vite secara terintegrasi.

## 8. Akun demo

Seeder membuat akun berikut:

```text
Email: test@example.com
Password: password
```

## 9. Build untuk production

```bash
npm run build
php artisan optimize
php artisan storage:link
```

Konfigurasi web server harus mengarah ke folder:

```text
public/
```

Contoh URL:

```text
https://domain-anda.com
```

## 10. Perintah dasar

Melihat route aplikasi:

```bash
php artisan route:list
```

Menguji koneksi dan migrasi:

```bash
php artisan migrate:status
```

Mengulang seluruh database sandbox:

```bash
php artisan migrate:fresh --seed
```

Menjalankan seeder ulang:

```bash
php artisan db:seed
```

Menjalankan test backend:

```bash
php artisan test
```

Memeriksa tipe Svelte:

```bash
npm run types:check
```

Mengecek format dan lint proyek:

```bash
npm run check
```

Membangun frontend:

```bash
npm run build
```