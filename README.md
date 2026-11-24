# Perancangan Sistem Informasi E-Commerce Model ERP Mampir Kopi

Sistem informasi e-commerce model ERP untuk kedai kopi Mampir Kopi (Kebon Jeruk, Jakarta Barat): katalog menu, keranjang, checkout (ambil di kasir / diantar / COD), pembayaran QRIS, lacak pesanan, wishlist, testimoni, blog, promo, serta panel admin (stok, pengiriman, pengembalian & refund, laporan keuangan, analitik, CRM, konten website).

Dibangun dengan PHP + MySQL + JavaScript vanilla. Situs live: http://mampirkopi.free.nf/

## Cara Menjalankan

1. Salin folder `mampirkopi` ke `C:\xampp\htdocs\` lalu aktifkan **Apache** dan **MySQL** di XAMPP.
2. Untuk instalasi awal, buka `http://localhost/phpmyadmin`, buat database bernama `db_kedai_kopi`, pilih database tersebut, lalu tab **Import** dan pilih `database/db_kedai_kopi.sql`.
   Jangan impor ulang ke database yang sudah berisi data: file SQL ini berisi data contoh dan bukan mekanisme sinkronisasi atau migrasi.
3. Jika user/password MySQL Anda bukan `root` / kosong, ubah di `api/config.php`.
4. Buka `http://localhost/mampirkopi/` untuk melihat halaman tamu, atau `http://localhost/mampirkopi/login.html` untuk langsung masuk/daftar. Setelah login, pelanggan menuju `dashboard.html` dan admin menuju panel admin.

## Akun demo
- Admin: `admin` — password `admin123` (akun ini ada di file SQL)
- Pelanggan: daftar sendiri lewat `login.html` (file SQL tidak menyertakan akun pelanggan — data pelanggan asli tidak dipublikasikan)
- Ganti password demo sebelum dipasang online.

Penting: buka lewat `http://localhost/...`, jangan klik dua kali file HTML (PHP tidak akan jalan).

## Struktur
- `api/auth.php` — login, daftar, logout (admin & pelanggan)
- `login.html` — halaman masuk / daftar
- `dashboard.html` — beranda pelanggan setelah login
- `tamu.html` — beranda tamu dengan katalog yang dapat dilihat sebelum login
- `js/guest.js` — memuat katalog publik; klik menu meminta pengunjung masuk/daftar
- `api/shop.php` — menu, voucher, buat pesanan, riwayat (sisi pelanggan)
- `api/admin.php` — semua CRUD panel admin
- `api/export.php` — export laporan ke Excel (.xlsx)
- `js/shop.js` — katalog, keranjang, pembayaran, riwayat
- `js/admin.js` — panel admin
- `img/menu/` — gambar bawaan menu; foto yang diunggah admin tersimpan di `uploads/menu/`
- `img/qris.png` — gambar QRIS di halaman pembayaran (ganti dengan QRIS toko Anda)
