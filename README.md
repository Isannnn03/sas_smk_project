# SMART ATTENDANCE SYSTEM (SAS) - SMK
## Monorepo Project Structure

```
sas_smk_project/
├── backend/           ✅ SELESAI - PHP Native REST API
│   ├── .htaccess
│   ├── database.sql
│   ├── config/
│   │   ├── database.php   (Koneksi PDO)
│   │   └── helpers.php    (CORS, Response, Token Auth)
│   └── api/
│       ├── auth.php       (Login, Logout, Profile)
│       ├── students.php   (CRUD Siswa + QR Code)
│       ├── schedules.php  (CRUD Jadwal)
│       └── attendance.php (Core Absensi)
├── dashboard/         🔜 TAHAP 2 - Vue.js Dashboard
└── mobile/            🔜 TAHAP 3 - Flutter Mobile App
```

---

## Tahap 1: Backend PHP Native ✅

### Persyaratan Server
- PHP >= 8.1
- MySQL >= 8.0
- Apache (dengan `mod_rewrite` aktif) atau Nginx
- Ekstensi PHP: `pdo`, `pdo_mysql`, `mbstring`

### Setup & Instalasi

#### 1. Buat Database
```bash
mysql -u root -p < backend/database.sql
```

#### 2. Konfigurasi Koneksi Database
Edit file `backend/config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // ← Ganti
define('DB_PASS', 'password');  // ← Ganti
define('DB_NAME', 'sas_db');
```

#### 3. Ganti Secret Key Token
Edit file `backend/config/helpers.php`:
```php
define('TOKEN_SECRET', 'GANTI_DENGAN_STRING_ACAK_PANJANG_DI_PRODUCTION');
```

#### 4. Jalankan di Localhost
Letakkan folder `sas_smk_project/` di dalam `htdocs/` (XAMPP) atau `www/` (WAMP).

---

## API Endpoints

### 🔐 Auth (`/backend/api/auth.php`)
| Method | Action | Deskripsi |
|--------|--------|-----------|
| POST | `?action=login` | Login, mendapatkan token |
| POST | `?action=logout` | Logout |
| GET | `?action=profile` | Info profil user aktif |

**Contoh Login:**
```json
POST /backend/api/auth.php?action=login
Body: { "username": "admin_tu", "password": "password123" }
```

### 👨‍🎓 Students (`/backend/api/students.php`)
| Method | Action | Role | Deskripsi |
|--------|--------|------|-----------|
| GET | `?action=list` | Semua | Daftar siswa (filter: `?class_id=`) |
| GET | `?action=detail&id=1` | Semua | Detail siswa |
| POST | `?action=create` | tata_usaha | Tambah siswa + auto-generate QR |
| PUT | `?action=update&id=1` | tata_usaha | Update data siswa |
| DELETE | `?action=delete&id=1` | tata_usaha | Hapus siswa |
| POST | `?action=regenerate_qr&id=1` | tata_usaha | Generate ulang QR Code |

### 📅 Schedules (`/backend/api/schedules.php`)
| Method | Action | Role | Deskripsi |
|--------|--------|------|-----------|
| GET | `?action=list` | Semua | Semua jadwal (filter: `?day=`, `?class_id=`) |
| GET | `?action=detail&id=1` | Semua | Detail jadwal |
| POST | `?action=create` | tata_usaha | Tambah jadwal baru |
| PUT | `?action=update&id=1` | tata_usaha | Update jadwal |
| DELETE | `?action=delete&id=1` | tata_usaha | Hapus jadwal |
| GET | `?action=by_class&class_id=1` | Semua | Jadwal per kelas |
| GET | `?action=by_teacher&teacher_id=1` | Semua | Jadwal per guru |

### 📋 Attendance (`/backend/api/attendance.php`) — CORE ENGINE
| Method | Action | Deskripsi |
|--------|--------|-----------|
| POST | `?action=start_session` | Buka sesi absensi baru |
| POST | `?action=scan_qr` | Proses scan QR Code siswa |
| POST | `?action=close_session` | Tutup sesi + **Auto-Alpha Engine** |
| GET | `?action=session_status&session_id=1` | Status real-time sesi |
| GET | `?action=session_report&session_id=1` | Laporan sesi (setelah ditutup) |
| PUT | `?action=update_status` | Update status manual (izin/sakit) |
| GET | `?action=report_by_class&class_id=1&start_date=2024-07-01&end_date=2024-07-31` | Rekap per kelas |

---

## Autentikasi

Semua endpoint (kecuali login) membutuhkan token di header:
```
Authorization: Bearer <token_dari_login>
```

Token berlaku selama **8 jam** dan berbasis HMAC-SHA256.

---

## Akun Default (untuk testing)
| Username | Password | Role |
|----------|----------|------|
| `admin_tu` | `password123` | tata_usaha |
| `guru_budi` | `password123` | guru |

> ⚠️ **GANTI PASSWORD DEFAULT SEBELUM DEPLOY KE PRODUCTION!**
