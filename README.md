# Aquiris Print & Acrylic Studio
### Sistem Informasi Keuangan & Operasional Usaha Percetakan, Fotocopy & Penyewaan Papan Akrilik

Aplikasi berbasis web yang dirancang khusus untuk mencatat dan mengelola seluruh arus keuangan (pemasukan & pengeluaran) pada usaha percetakan/fotocopy sekaligus manajemen penyewaan papan akrilik (welcome board wedding/event).

---

## 🚀 Fitur Utama

### 1. Kasir POS Percetakan & Fotocopy (`kasir.php`)
- **Katalog Lengkap**: Fotocopy (A4/F4 1 sisi, bolak-balik), Print Dokumen (Hitam Putih, Warna, Foto Glossy, Art Paper A3+), Jilid (Spiral Kawat, Lakban, Hardcover Skripsi), Cetak Banner Flexy, X-Banner, Stiker Vinyl/Chromo, Kartu Nama, dsb.
- **Item Custom Fleksibel**: Tambah pesanan khusus di luar katalog secara langsung pada keranjang kasir.
- **Kalkulasi Otomatis**: Subtotal, potongan diskon, nominal bayar, kembalian / sisa tagihan.
- **Status Pembayaran**: Lunas, Uang Muka (DP), Belum Lunas.
- **Metode Pembayaran**: Tunai (Cash), Transfer Bank, QRIS / E-Wallet.
- **Cetak Nota & Integrasi WhatsApp**:
  - Cetak Struk Thermal (58mm / 80mm).
  - Cetak Nota Standar (A4 / A5) lengkap dengan tanda tangan.
  - Tombol kirim rincian nota langsung ke WhatsApp pelanggan.

### 2. Manajemen Penyewaan Papan Akrilik (`rental_akrilik.php`, `rental_tambah.php`, `rental_detail.php`)
- **Inventaris Papan & Standing**: Papan transparan A1/A2, frosted bulat, mirror gold, kubah/arch pastel + easel kayu atau tripod standing besi gold/hitam.
- **Kustomisasi Cetak Stiker Akrilik**: Opsi tambahan jasa cetak tulisan ucapan (nama pengantin, tanggal pernikahan, wisuda, quotes) langsung saat pemesanan sewa.
- **Uang Jaminan (Deposit)**: Pencatatan uang jaminan yang dipegang dan dikembalikan saat unit kembali dalam kondisi utuh.
- **Jadwal & Notifikasi WhatsApp**:
  - Pantau tanggal pengambilan dan batas pengembalian sewa.
  - Peringatan papan jatuh tempo / terlambat (Overdue alert).
  - Satu klik kirim pesan WhatsApp pengingat jadwal pengembalian kepada penyewa.
- **Formulir Pengembalian & Denda**: Hitung otomatis denda keterlambatan per hari dan checklist pengembalian deposit.
- **Cetak Dokumen Kontrak**: Cetak Surat Perjanjian & Tanda Terima Sewa resmi (`print_sewa.php`).

### 3. Pencatatan Pengeluaran Operasional (`pengeluaran.php`)
- Catat beban pengeluaran usaha berdasarkan kategori:
  - Pembelian bahan baku kertas HVS, Art Paper, mika.
  - Pembelian tinta, toner & cartridge printer.
  - Bahan spanduk flexy, stiker vinyl & akrilik mentah.
  - Biaya perawatan & servis mesin fotocopy / printer.
  - Biaya utilitas (listrik, internet, air).
  - Gaji karyawan & konsumsi operasional.
- Filter pengeluaran berdasarkan rentang tanggal dan kategori beban.

### 4. Buku Kas & Arus Kas Lengkap / Cash Flow (`arus_kas.php`)
- Menggabungkan seluruh mutasi keuangan secara kronologis:
  1. Pemasukan Percetakan
  2. Pemasukan Sewa Akrilik (Sewa + Stiker + Denda)
  3. Pengeluaran Beban Operasional
- Menampilkan saldo kas berjalan (*running balance*).
- Filter transaksi, cetak buku kas, dan rekap mutasi kas.

### 5. Laporan Laba Rugi Komprehensif (`laporan.php`)
- Rekapitulasi Pendapatan Kotor vs Total Beban Operasional.
- Laba Bersih (*Net Profit*) dan Margin Keuntungan (%).
- Alokasi pembagian hasil (Profit Sharing) untuk Pemilik Modal, Pekerja & Kas Usaha.
- Ekspor data laporan keuangan ke format **Excel / CSV**.
- Cetak Laporan Keuangan resmi siap tanda tangan 3 pihak (Pemilik Modal, Perwakilan Pekerja, Pembukuan Kasir).

### 6. Sistem Bagi Hasil & Prive Usaha (`bagi_hasil.php`, `print_bagi_hasil.php`)
- **Skema Mudharabah / Profit Sharing UMKM**:
  1. Bagian **Pemilik Modal** (Hak dividen / return of capital modal toko).
  2. Bagian **Pekerja / Karyawan** (Insentif & upah bagi hasil pengelola).
  3. Bagian **Kas Usaha** (Dana cadangan darurat & reinvestasi modal alat).
- **Rasio Fleksibel**: Bebas diatur kapan saja di menu Pengaturan (contoh: 40% Pemilik, 40% Pekerja, 20% Kas Usaha) dengan validasi total 100%.
- **Eksekusi & Pencatatan Resmi**: Catat pencairan bagi hasil periode tertentu langsung ke Buku Arus Kas (`arus_kas.php`).
- **Cetak Voucher Bukti Bagi Hasil**: Format cetak A4 resmi siap tanda tangan bertiga (`print_bagi_hasil.php`).

### 7. Master Data & Pengaturan Toko
- Master Layanan / Produk Cetak (`produk.php`).
- Katalog Inventaris Papan Akrilik (`akrilik_master.php`).
- Data Pelanggan & Riwayat Transaksi (`pelanggan.php`).
- Pengaturan Profil Toko, Rasio Bagi Hasil, WhatsApp Gateway Fonnte & Manajemen Akun (`pengaturan.php`).

---

## 🛠️ Spesifikasi & Teknologi

- **Backend**: PHP 8.2 (Native PDO dengan Prepared Statements & Transaksi aman)
- **Database**: MySQL / MariaDB (UTF-8 MB4)
- **Frontend / Styling**: Tailwind CSS (CDN), Font Awesome 6, Google Fonts (Plus Jakarta Sans)
- **Grafik & Notifikasi**: Chart.js & SweetAlert2
- **Kompatibel Server**: Laragon, XAMPP, Apache, Nginx

---

## 📦 Informasi Akses & Akun Login

Aplikasi dapat langsung diakses melalui browser:
👉 **URL Lokal**: `http://localhost/aquiris_print`

### Akun Bawaan (Default Credentials):
| Peran (Role) | Username | Password | Keterangan |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin123` | Akses penuh seluruh fitur, laporan & user |
| **Kasir** | `kasir` | `kasir123` | Akses transaksi POS, rental & pelanggan |

*(Password dan pengguna baru dapat diubah/ditambah pada menu Pengaturan Toko).*

---

## 🗄️ Database Auto-Installer

Database `db_aquiris_print` telah dikonfigurasi secara otomatis saat aplikasi pertama kali dijalankan melalui `config/setup.php`. Semua tabel, relasi, dan data awal produk, akrilik, serta transaksi demo sudah terisi dan siap digunakan.
