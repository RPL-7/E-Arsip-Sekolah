# Sistem Login Multi-User Sekolah

Sistem login dengan 3 tipe user: **Siswa**, **Guru**, dan **Admin**

## 📋 Fitur

- Login untuk Siswa menggunakan **NIS**
- Login untuk Guru menggunakan **ID Guru**
- Login untuk Admin menggunakan **Username**
- Password terenkripsi menggunakan password_hash PHP
- Session management yang aman
- Validasi status user (aktif/nonaktif)
- Dashboard berbeda untuk setiap tipe user

## 🚀 Cara Instalasi

### 1. Persiapan Database

```sql
-- Buat database
CREATE DATABASE nama_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nama_database;

-- Import semua tabel yang sudah Anda miliki
-- (admin, user_guru, user_siswa, kelas, pelajaran, arsip)
```

### 2. Upload File ke Server

Upload semua file PHP ke folder web server Anda (htdocs untuk XAMPP):

```
/htdocs/sistem-sekolah/
├── login.php (file HTML login)
├── login_process.php
├── config.php
├── logout.php
├── create_user_sample.php
├── dashboard_siswa.php
├── dashboard_guru.php
└── dashboard_admin.php
```

### 3. Konfigurasi Database

Edit file **config.php**, ubah bagian berikut sesuai dengan konfigurasi database Anda:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'nama_database'); // Ganti dengan nama database Anda
define('DB_USER', 'root'); // Ganti dengan username database
define('DB_PASS', ''); // Ganti dengan password database
```

### 4. Buat User Sample (Untuk Testing)

Jalankan file `create_user_sample.php` di browser:

```
http://localhost/sistem-sekolah/create_user_sample.php
```

File ini akan membuat:
- 1 Admin dengan username: **admin**
- 1 Guru dengan ID Guru yang ditampilkan
- 1 Siswa dengan NIS: **2024001**
- 1 Kelas sample

**Password default untuk semua user: `password123`**

⚠️ **PENTING**: Setelah selesai testing, hapus atau amankan file `create_user_sample.php`

### 5. Testing Login

Akses halaman login:
```
http://localhost/sistem-sekolah/login.php
```

Coba login dengan kredensial berikut:

#### Login sebagai Admin:
- Pilih: **Admin**
- Username: `admin`
- Password: `password123`

#### Login sebagai Guru:
- Pilih: **Guru**
- ID Guru: `(lihat hasil dari create_user_sample.php)`
- Password: `password123`

#### Login sebagai Siswa:
- Pilih: **Siswa**
- NIS: `2024001`
- Password: `password123`

## 🔐 Cara Membuat User Baru Secara Manual

### Membuat Password Hash

Gunakan script PHP berikut untuk membuat password hash:

```php
<?php
$password = "password_anda";
$hash = password_hash($password, PASSWORD_DEFAULT);
echo $hash;
?>
```

### Insert User Baru ke Database

#### Tambah Siswa Baru:
```sql
INSERT INTO user_siswa (nis, nama_siswa, password_login, jenis_kelamin, tanggal_lahir, alamat, no_hp, id_kelas, status)
VALUES ('2024002', 'Nama Siswa', '$2y$10$...hash...', 'L', '2008-01-01', 'Alamat', '08123456789', 1, 'aktif');
```

#### Tambah Guru Baru:
```sql
INSERT INTO user_guru (nip, nama_guru, email, password_login, jenis_kelamin, tanggal_lahir, alamat, no_hp, status)
VALUES ('9876543210', 'Nama Guru', 'guru@sekolah.com', '$2y$10$...hash...', 'P', '1985-01-01', 'Alamat', '08123456789', 'aktif');
```

#### Tambah Admin Baru:
```sql
INSERT INTO admin (username, nama_admin, email, password)
VALUES ('admin2', 'Admin Dua', 'admin2@sekolah.com', '$2y$10$...hash...');
```

## 📁 Struktur File

| File | Deskripsi |
|------|-----------|
| `login.php` | Halaman login dengan pilihan user type |
| `login_process.php` | Proses autentikasi dan validasi login |
| `config.php` | Konfigurasi database dan fungsi helper |
| `logout.php` | Proses logout dan destroy session |
| `dashboard_siswa.php` | Dashboard untuk siswa |
| `dashboard_guru.php` | Dashboard untuk guru |
| `dashboard_admin.php` | Dashboard untuk admin |
| `create_user_sample.php` | Script untuk membuat user testing |

## 🔒 Keamanan

1. **Password Encryption**: Menggunakan `password_hash()` dengan algoritma bcrypt
2. **SQL Injection Protection**: Menggunakan Prepared Statements (PDO)
3. **Session Management**: Validasi session di setiap halaman
4. **XSS Protection**: Menggunakan `htmlspecialchars()` untuk output
5. **Status Check**: Validasi status user (aktif/nonaktif)

## 🎨 Customization

### Mengubah Warna Dashboard

Edit bagian CSS di setiap file dashboard:

```css
/* Dashboard Siswa - Ungu */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* Dashboard Guru - Hijau */
background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);

/* Dashboard Admin - Pink */
background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
```

### Mengubah Redirect Setelah Login

Edit file `login_process.php` pada bagian:

```php
$redirect_url = 'dashboard_siswa.php'; // Ubah ke halaman yang diinginkan
```

## ⚠️ Troubleshooting

### Error: "Koneksi database gagal"
- Pastikan MySQL/MariaDB sudah running
- Cek konfigurasi di `config.php`
- Pastikan database sudah dibuat

### Error: "Username atau password salah"
- Pastikan password di database sudah dalam format hash
- Cek apakah user tersebut ada di database

### Session tidak tersimpan
- Pastikan `session_start()` dipanggil di awal setiap halaman
- Cek permission folder session di server

### Redirect tidak berfungsi
- Pastikan tidak ada output sebelum `header()` dipanggil
- Hapus spasi atau karakter sebelum tag `<?php`

## 📞 Support

Jika ada pertanyaan atau masalah, silakan dokumentasikan error yang muncul beserta langkah-langkah yang sudah dilakukan.

## 📝 Catatan Penting

1. Jangan gunakan password sederhana seperti "password123" di production
2. Selalu backup database sebelum melakukan perubahan
3. Hapus atau amankan `create_user_sample.php` setelah testing
4. Gunakan HTTPS di production untuk keamanan
5. Update password secara berkala
