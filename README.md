# Sistem Informasi Manajemen Karyawan, Absensi, dan WhatsApp Fonnte

Aplikasi berbasis web presisi tinggi untuk pencatatan absensi masuk dan pulang kerja, deteksi keterlambatan otomatis, permohonan izin/sakit, audit trail koreksi, pelaporan Excel & PDF, serta notifikasi WhatsApp otomatis via Fonnte Gateway.

---

## 🚀 Panduan Menjalankan di Windows (XAMPP)

### 1. Prasyarat Sistem
- **XAMPP** dengan Apache dan MySQL/MariaDB aktif.
- **PHP 8.0 - 8.2+** (PDO extension aktif).
- Lokasi folder proyek: `c:\xampp_baru\htdocs\absenkaryawan\` (atau `c:\xampp\htdocs\absenkaryawan\`).

### 2. URL Akses Aplikasi
Buka peramban (browser) dan akses:
```
http://localhost/absenkaryawan/
```
atau
```
http://127.0.0.1/absenkaryawan/
```

### 3. Akun Pengguna Bawaan (Default Credentials)

| Peran (Role) | Email / NIK | Kata Sandi | Status Akun |
| :--- | :--- | :--- | :--- |
| **👑 Super Admin** | `admin@absenkaryawan.local` / `ADM001` | `admin123` | Disetujui (Aktif) |
| **👤 Karyawan (Disetujui)** | `budi@absenkaryawan.local` / `KRY001` | `karyawan123` | Disetujui (Aktif) |
| **⏳ Karyawan (Menunggu)** | `siti@absenkaryawan.local` / `KRY002` | `karyawan123` | Menunggu Persetujuan Admin |

> *Tips: Pada halaman Login tersedia tombol **💡 Akun Demo Cepat** untuk mengisi kredensial dalam 1 klik.*

---

## ⚙️ Fitur Utama & Panduan Penggunaan

### 1. Portal Karyawan
- **Dashboard & Punch Card:** Jam digital server real-time (WIB) dengan tombol dinamis *Check-In* dan *Check-Out*.
- **Pendeteksi Terlambat:** Otomatis menghitung menit keterlambatan jika melewati batas jam masuk (default `08:00:00`).
- **Pengajuan Izin / Sakit:** Formulir permohonan dengan kalkulator hari kerja efektif (otomatis melewati hari Minggu dan libur nasional) serta upload dokumen bukti opsional (JPG/PNG/PDF maks 2 MB).
- **Riwayat Absensi:** Rekapitulasi bulanan pribadi lengkap dengan audit catatan koreksi.

### 2. Portal Administrator
- **Dashboard Analitik:** Metrik kehadiran hari ini, status belum hadir, daftar karyawan terlambat, dan banner persetujuan yang tertunda.
- **Manajemen Karyawan:** Tambah, edit, verifikasi persetujuan pendaftaran publik, serta fitur pengarsipan (Soft Delete).
- **Monitoring Presensi Harian:** Pantau kehadiran seluruh staf pada tanggal yang dipilih.
- **Koreksi Absensi (Audit Trail):** Mengoreksi jam masuk/pulang karyawan dengan **alasan wajib diisi**. Seluruh data lama dan baru tersimpan di tabel audit serta terkirim ke WhatsApp karyawan.
- **Persetujuan Izin & Sakit:** Menyetujui atau menolak permohonan dengan catatan resmi.
- **Laporan & Rekapitulasi:** Filter rentang tanggal, status, atau karyawan tertentu dengan fitur:
  - **Ekspor Excel (.xls):** Spreadsheet terstruktur rapi.
  - **Cetak / PDF:** Tampilan kop perusahaan resmi, siap dicetak atau disimpan sebagai PDF.
- **Audit Log WhatsApp Fonnte:** Memantau seluruh pesan yang dikirimkan gateway Fonnte beserta tombol **Kirim Ulang (Resend)** jika gagal.
- **Pengaturan Jam Kerja & Kalender Libur:** Atur batas waktu jam masuk, hari kerja reguler (Senin-Sabtu), dan tambah hari libur nasional.

---

## 📱 Konfigurasi WhatsApp Fonnte

1. Daftarkan nomor WhatsApp Anda di [Fonnte.com](https://fonnte.com).
2. Dapatkan API Token dari dashboard Fonnte.
3. Masukkan token tersebut melalui menu **Pengaturan** di panel Admin, atau isi file `.env` di root folder:
   ```env
   FONNTE_TOKEN=TOKEN_FONNTE_ANDA_DISINI
   ```
4. Jika token belum diisi, sistem tetap berjalan normal dan mencatat seluruh notifikasi ke tabel `notification_logs` sebagai mode simulasi.

---

## 🛡️ Standar Keamanan

- **Prepared Statements (PDO):** Kebal dari SQL Injection di semua modul.
- **Hashing Bcrypt:** Password diamankan dengan `password_hash($pass, PASSWORD_BCRYPT)`.
- **CSRF Token:** Semua form POST dilindungi token keamanan anti pemalsuan permintaan antar-situs.
- **Anti Shell Upload:** Direktori `uploads/evidence/` dilindungi berkas `.htaccess` yang memblokir eksekusi script PHP.
- **RBAC Server-Side:** Validasi peran pengguna dilakukan di sisi server (tidak dapat ditembus via direct URL).
