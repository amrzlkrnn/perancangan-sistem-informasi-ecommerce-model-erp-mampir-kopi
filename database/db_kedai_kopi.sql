-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 23 Nov 2026 pada 20.29
-- Versi server: 11.4.14-MariaDB
-- Versi PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_kedai_kopi`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL,
  `nama` varchar(80) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'Kasir',
  `status` varchar(20) NOT NULL DEFAULT 'Aktif',
  `terakhir_login` datetime DEFAULT NULL,
  `sesi_versi` int(11) NOT NULL DEFAULT 0,
  `foto` mediumblob DEFAULT NULL,
  `foto_mime` varchar(50) DEFAULT NULL,
  `foto_diubah` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id_admin`, `nama`, `username`, `email`, `password`, `role`, `status`, `terakhir_login`, `sesi_versi`, `foto`, `foto_mime`, `foto_diubah`) VALUES
(1, 'Admin Mampir', 'admin', 'admin@mampirkopi.id', '$2b$10$atpo77Lwnt3R9LYs8F4U/O.6nhOvYfF0Hl7onVRxjgn3bLc6pvfQe', 'Super Admin', 'Aktif', '2026-11-24 07:28:38', 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `artikel`
--

CREATE TABLE `artikel` (
  `id_artikel` int(11) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `ringkasan` varchar(300) DEFAULT '',
  `isi` text NOT NULL,
  `gambar` varchar(255) DEFAULT '',
  `dibuat` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `artikel`
--

INSERT INTO `artikel` (`id_artikel`, `judul`, `slug`, `ringkasan`, `isi`, `gambar`, `dibuat`) VALUES
(1, 'Cara Menikmati Kopi Tubruk yang Benar', 'cara-menikmati-kopi-tubruk', 'Kopi tubruk itu gampang, tapi ada triknya supaya ampas tidak mengganggu dan rasanya keluar semua.', 'Kopi tubruk itu cara seduh paling merakyat di Indonesia. Bubuk kopi langsung bertemu air panas, tanpa alat khusus. Sederhana, tapi ada caranya supaya hasilnya enak dan ampas tidak mengganggu.\n\nPakai air yang baru mendidih, lalu diamkan sekitar 30 detik. Tuang perlahan ke gelas berisi 1,5 sendok makan bubuk kopi, aduk sebentar, lalu tutup gelasnya selama 3 sampai 4 menit. Waktu tunggu ini yang membiarkan ampas turun sendiri ke dasar gelas.\n\nSesudah itu jangan diaduk lagi. Seruput perlahan dari permukaan, di situlah rasa paling bersih berada. Di Mampir Kopi, Kopi Tubruk kami diseduh per pesanan, jadi selalu segar dan hangat.', 'img/promo-ngopi.jpg', '2026-11-15 20:08:28'),
(2, 'Beda Small dan Regular di Mampir Kopi', 'beda-small-dan-regular', 'Small 200 ml, Regular 300 ml. Bedanya cuma volume, rasanya sama.', 'Sering bingung pilih Small atau Regular? Gampang. Small itu 200 ml, pas untuk ngobrol singkat atau kalau kamu cuma butuh dorongan kafein ringan. Regular 300 ml, buat yang duduk lebih lama, nugas, atau memang lagi ingin menikmati pelan-pelan.\n\nRasio kopi dan susunya sama di kedua ukuran, jadi rasanya tidak berubah. Yang berubah cuma volumenya. Harganya pun selisih sedikit, jadi pilih sesuai waktumu hari ini.', 'img/promo-segar.jpg', '2026-11-15 20:08:28'),
(3, 'Dari Teras Kecil di Sukabumi Selatan', 'dari-teras-kecil-sukabumi-selatan', 'Awalnya cuma kebiasaan menyeduh kopi untuk tetangga. Teras kecil itu sekarang jadi kedai.', 'Mampir Kopi berawal dari teras kecil di Sukabumi Selatan, Kebon Jeruk. Awalnya bukan kedai, hanya kebiasaan menyeduh kopi untuk tetangga dan teman yang mampir. Yang datang makin banyak, yang minta dibuatkan kopi makin sering, sampai akhirnya teras itu berubah jadi kedai kecil.\n\nMenu kami tumbuh dari situ juga. Kopi Susu Mampir, yang sekarang jadi andalan, adalah racikan pertama yang kami buat untuk tetangga. Kopi tubruk dan wedang jahe ada karena pelanggan yang datang pagi-pagi minta yang hangat. Tidak ada yang kami buat asal-asalan. Semua berawal dari yang diminta orang yang mampir.\n\nSampai sekarang, prinsipnya sama: kopi yang baik tidak harus mahal atau ribet. Cukup diseduh dengan benar, disajikan hangat, dan dinikmati tanpa buru-buru. Kalau kamu mampir ke Kebon Jeruk, teras kami masih terbuka. Sekarang dengan meja yang lebih banyak.', 'img/promo-mampir.jpg', '2026-11-16 07:23:23'),
(4, 'Biji Kopi Indonesia, dari Kebun ke Cangkir', 'biji-kopi-indonesia-kebun-ke-cangkir', 'Semua kopi kami memakai biji Indonesia, dari pemasok lokal yang kami kenal namanya.', 'Semua kopi di Mampir Kopi memakai biji dari Indonesia. Bukan karena tren. Kopi yang baik dimulai dari kebun yang dirawat dengan benar, dan petani Indonesia sudah melakukannya jauh sebelum kedai kami ada.\n\nKami membeli dari pemasok lokal yang kami kenal namanya, bukan dari gudang entah di mana. Bijinya datang segar, kami simpan dengan benar, dan kami giling sedikit demi sedikit supaya aromanya tidak hilang. Cara ini juga yang membuat harga kami tetap bersahabat. Tanpa rantai yang panjang, tanpa biaya yang tidak perlu.\n\nKalau mau merasakan karakter bijinya paling jujur, coba V60 kami. Tanpa susu, tanpa gula. Hanya air panas dan biji yang diseduh pelan. Di situ terasa bedanya biji yang dirawat dengan yang asal-asalan.', 'uploads/menu/menu-1791018171-11387a.jpg', '2026-11-16 07:23:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_pesanan`
--

CREATE TABLE `detail_pesanan` (
  `id_detail` int(11) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `id_menu` int(11) DEFAULT NULL,
  `nama_menu` varchar(80) NOT NULL,
  `ukuran` varchar(20) DEFAULT NULL,
  `gula` varchar(20) DEFAULT NULL,
  `es` varchar(20) DEFAULT NULL,
  `catatan` varchar(100) DEFAULT NULL,
  `harga_satuan` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `subtotal` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `detail_pesanan`
--


-- --------------------------------------------------------

--
-- Struktur dari tabel `faq`
--

CREATE TABLE `faq` (
  `id_faq` int(11) NOT NULL,
  `pertanyaan` varchar(200) NOT NULL,
  `jawaban` text NOT NULL,
  `urutan` int(11) NOT NULL DEFAULT 0,
  `aktif` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `faq`
--

INSERT INTO `faq` (`id_faq`, `pertanyaan`, `jawaban`, `urutan`, `aktif`) VALUES
(1, 'Bagaimana cara memesan di Mampir Kopi?', 'Masuk atau daftar dulu, lalu pilih menu dan atur varian (ukuran, gula, es) sebelum masuk keranjang. Di halaman Pembayaran, pilih mau ambil di kasir atau diantar, pilih metode pembayaran, lalu klik Bayar Sekarang. Pesananmu langsung muncul di Riwayat.', 1, 1),
(2, 'Metode pembayaran apa saja yang diterima?', 'QRIS, GoPay, OVO, DANA, dan ShopeePay. QRIS bisa dipindai dari aplikasi pembayaran mana saja. Kalau pesananmu diantar, bisa juga bayar tunai di tempat (COD).', 2, 1),
(3, 'Apa beda Ambil di Kasir dan Diantar?', 'Ambil di Kasir gratis, kamu ambil sendiri pesananmu di kedai. Diantar dikenakan ongkir tetap Rp5.000, dan pesanan kami antar ke alamat yang kamu tulis saat pembayaran.', 3, 1),
(4, 'Berapa lama saya harus membayar?', 'Untuk QRIS dan e-wallet, bayar dalam 5 menit sejak pesanan dibuat. Lewat dari itu pesanan batal otomatis, supaya stok tidak tertahan. Pesanan COD tidak kena batas ini.', 4, 1),
(5, 'Bagaimana cara memakai voucher promo?', 'Di halaman Pembayaran ada kolom kode promo. Masukkan kode dari halaman Promo, diskonnya langsung memotong total belanja, asal syarat minimum belanjanya terpenuhi.', 5, 1),
(6, 'Bisakah pesanan dibatalkan atau dikembalikan?', 'Pesanan yang belum dibayar cukup dibiarkan saja, nanti batal otomatis. Kalau pesananmu sudah Selesai tapi ada masalah, ajukan Pengembalian dari detail pesanan di Riwayat. Admin akan meninjau, dan kalau disetujui, dana dan stok dikembalikan.', 6, 1),
(7, 'Bagaimana cara melacak pesanan saya?', 'Buka halaman Lacak Pesanan, masukkan kode pesanan (contoh: MK-0001) dan email yang kamu pakai saat mendaftar. Statusnya langsung tampil, tanpa perlu masuk akun.', 7, 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `galeri`
--

CREATE TABLE `galeri` (
  `id_galeri` int(11) NOT NULL,
  `gambar` varchar(255) NOT NULL,
  `judul` varchar(100) NOT NULL,
  `urutan` int(11) NOT NULL DEFAULT 0,
  `aktif` tinyint(4) NOT NULL DEFAULT 1,
  `lebar` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `galeri`
--

INSERT INTO `galeri` (`id_galeri`, `gambar`, `judul`, `urutan`, `aktif`, `lebar`) VALUES
(1, 'img/galeri/02-minuman-dua-gelas-oranye.png', 'Kopi Susu Mampir', 1, 1, 1),
(2, 'img/galeri/03-minuman-butterfly-ungu.jpg', 'Butterfly Lemon', 2, 1, 0),
(3, 'img/galeri/04-eksterior-malam-seberang.jpg', 'Kedai di malam hari', 3, 1, 0),
(4, 'img/galeri/05-minuman-lapis-kuning-ungu.jpg', 'Minuman berlapis', 4, 1, 0),
(5, 'img/galeri/06-minuman-biru-gradasi.jpg', 'Minuman biru', 5, 1, 0),
(6, 'img/galeri/07-suasana-tanaman-malam.jpg', 'Tanaman di teras', 6, 1, 0),
(7, 'img/galeri/11-minuman-kantong-takeaway.jpg', 'Pesanan dibungkus', 7, 1, 0),
(8, 'img/galeri/09-minuman-kopi-susu-meja.jpg', 'Kopi susu', 8, 1, 0),
(9, 'img/galeri/12-minuman-dua-gelas-kounter.jpg', 'Disiapkan di konter', 9, 1, 0),
(10, 'img/galeri/13-minuman-gelas-slogan.jpg', 'Life happens', 10, 1, 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `interaksi`
--

CREATE TABLE `interaksi` (
  `id_interaksi` int(11) NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `catatan` text NOT NULL,
  `dibuat` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori`
--

CREATE TABLE `kategori` (
  `id_kategori` int(11) NOT NULL,
  `nama_kategori` varchar(50) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `ikon` varchar(10) NOT NULL DEFAULT '☕',
  `gambar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori`
--

INSERT INTO `kategori` (`id_kategori`, `nama_kategori`, `slug`, `ikon`, `gambar`) VALUES
(1, 'Signature', 'signature', '☕', NULL),
(2, 'Non Coffee', 'non-coffee', '🍵', NULL),
(3, 'Iced Coffee', 'iced-coffee', '🥤', NULL),
(4, 'Manual Brew', 'manual-brew', '🫖', NULL),
(5, 'Hot', 'hot', '♨️', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `kunjungan`
--

CREATE TABLE `kunjungan` (
  `tanggal` date NOT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kunjungan`
--


-- --------------------------------------------------------

--
-- Struktur dari tabel `menu`
--

CREATE TABLE `menu` (
  `id_menu` int(11) NOT NULL,
  `id_kategori` int(11) NOT NULL,
  `nama_menu` varchar(80) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `harga` int(11) NOT NULL,
  `harga_regular` int(11) NOT NULL DEFAULT 0 COMMENT '0 = tidak ada pilihan ukuran',
  `tipe` enum('es','panas','makanan') NOT NULL DEFAULT 'es',
  `gambar` varchar(255) DEFAULT NULL,
  `stok` int(11) NOT NULL DEFAULT 100,
  `aktif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `menu`
--

INSERT INTO `menu` (`id_menu`, `id_kategori`, `nama_menu`, `slug`, `deskripsi`, `harga`, `harga_regular`, `tipe`, `gambar`, `stok`, `aktif`) VALUES
(1, 1, 'Kopi Susu Mampir', 'kopi-susu-mampir', 'Espresso dan susu full cream, manisnya pas. Racikan pertama kami, dan sampai sekarang yang paling sering dipesan.', 13000, 17000, 'es', 'uploads/menu/menu-1791018037-169144.jpg', 98, 1),
(2, 1, 'Kopi Susu Singgah', 'kopi-susu-singgah', 'Kopi susu gula aren, manisnya legit dan dalam. Buat yang suka manis tapi tidak mau yang bikin enek.', 13000, 17000, 'es', 'uploads/menu/menu-1791018047-113d17.jpg', 99, 1),
(3, 1, 'Kopi Susu Butterscotch', 'kopi-susu-butterscotch', 'Kopi susu dengan sirup butterscotch. Manis karamelnya terasa, tapi pahit kopinya tetap ikut.', 13000, 17000, 'es', 'uploads/menu/menu-1791018056-04262d.jpg', 100, 1),
(4, 2, 'Lemon Tea', 'lemon-tea', 'Teh dingin dengan perasan lemon asli, asamnya nyegerin. Paling pas diminum siang bolong.', 12000, 14000, 'es', 'uploads/menu/menu-1791018066-fc24cf.jpg', 100, 1),
(5, 2, 'Chocolate', 'chocolate', 'Cokelat pekat yang diaduk dengan susu sampai creamy. Tanpa kopi, jadi aman buat yang tidak bisa kafein.', 13000, 17000, 'es', 'uploads/menu/menu-1791018075-460e82.jpg', 97, 1),
(6, 2, 'Green Tea Latte', 'green-tea-latte', 'Matcha yang dicampur susu. Pahit khas matcha tetap terasa di ujung, susunya yang bikin lembut.', 13000, 17000, 'es', 'uploads/menu/menu-1791018092-8bdca4.jpg', 98, 1),
(7, 2, 'Red Velvet Latte', 'red-velvet-latte', 'Susu red velvet, manis dan creamy. Warnanya merah muda, jadi sering dipesan buat difoto dulu sebelum diminum.', 13000, 17000, 'es', 'uploads/menu/menu-1791018101-6cef75.jpg', 100, 1),
(8, 2, 'Butterfly Lemon', 'butterfly-lemon', 'Bunga telang dan lemon. Aduk pelan, warnanya berubah dari biru ke ungu. Rasanya ringan, asamnya segar.', 10000, 14000, 'es', 'uploads/menu/menu-1791018110-c35e97.jpg', 100, 1),
(9, 2, 'Yakult Rosella', 'yakult-rosella', 'Yakult dicampur rosella. Asam manisnya khas, dan warnanya merah cantik.', 12000, 14000, 'es', 'uploads/menu/menu-1791018118-53d799.jpg', 100, 1),
(10, 3, 'Americano', 'americano', 'Espresso dan air dingin, tanpa gula. Buat yang mau rasa kopinya apa adanya.', 10000, 13000, 'es', 'uploads/menu/menu-1791018128-7df547.jpg', 100, 1),
(11, 3, 'Mocha Latte', 'mocha-latte', 'Espresso, cokelat, dan susu dingin. Buat yang suka kopi tapi masih mau manis cokelatnya.', 13000, 17000, 'es', 'uploads/menu/menu-1791018142-1deaf5.jpg', 99, 1),
(12, 3, 'Sea Salt Latte', 'sea-salt-latte', 'Latte dengan sea salt cream di atasnya. Gurih asinnya bikin manis susunya terasa lebih hidup.', 18000, 22000, 'es', 'uploads/menu/menu-1791018158-15274b.jpg', 99, 1),
(13, 4, 'Manual Brew', 'v60', 'Biji kopi Indonesia diseduh pelan pakai V60. Tanpa susu dan gula, jadi karakternya terasa semua.', 20000, 0, 'panas', 'uploads/menu/menu-1791018171-11387a.jpg', 100, 1),
(14, 5, 'Kopi Tubruk', 'kopi-tubruk', 'Kopi hitam pekat ala warung, diseduh langsung di gelas. Pahitnya jujur, harganya cuma lima ribu.', 5000, 0, 'panas', 'uploads/menu/menu-1791018199-00aebf.jpg', 100, 1),
(15, 5, 'Susu Jahe Geprek', 'susu-jahe-geprek', 'Susu hangat dengan jahe geprek segar. Diminum malam hari, badan langsung hangat.', 8000, 0, 'panas', 'uploads/menu/menu-1791018210-3e8ce3.jpg', 100, 1),
(17, 5, 'Wedang Jahe', 'wedang-jahe', 'Jahe dan gula aren, diseduh hangat. Ini yang paling laku kalau hujan turun.', 5000, 0, 'panas', 'uploads/menu/menu-1791018342-b14a8d.jpg', 100, 1),
(18, 3, 'Americano Peach', 'americano-peach', 'Americano dingin dengan sirup peach. Pahit kopinya tetap ada, manis buahnya mengikuti di belakang.', 12000, 15000, 'es', 'uploads/menu/menu-1791018397-442085.jpg', 100, 1),
(19, 5, 'Kopi Tubruk Jahe Geprek', 'kopi-tubruk-jahe-geprek', 'Kopi tubruk pekat dicampur jahe geprek. Pahit dan hangatnya jalan bareng.', 7000, 0, 'panas', 'uploads/menu/menu-1791018453-54a8ca.jpg', 100, 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `pelanggan`
--

CREATE TABLE `pelanggan` (
  `id_pelanggan` int(11) NOT NULL,
  `nama_pelanggan` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Aktif',
  `tanggal_daftar` datetime NOT NULL DEFAULT current_timestamp(),
  `terakhir_login` datetime DEFAULT NULL,
  `foto` mediumblob DEFAULT NULL,
  `foto_mime` varchar(50) DEFAULT NULL,
  `foto_diubah` datetime DEFAULT NULL,
  `pref_notifikasi` tinyint(4) DEFAULT 1,
  `sesi_versi` int(11) NOT NULL DEFAULT 0,
  `pref_tema` varchar(10) NOT NULL DEFAULT 'terang'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pelanggan`
--


-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id_pembayaran` int(11) NOT NULL,
  `kode_transaksi` varchar(20) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `metode` varchar(30) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `status` enum('Menunggu Verifikasi','Terverifikasi','Refund','Ditolak','Kadaluarsa') DEFAULT 'Menunggu Verifikasi',
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `alasan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pembayaran`
--


-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan`
--

CREATE TABLE `pengaturan` (
  `kunci` varchar(60) NOT NULL,
  `nilai` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengaturan`
--

INSERT INTO `pengaturan` (`kunci`, `nilai`) VALUES
('alamat', 'Jl. Ff No. 24, RT.7/RW.5, Sukabumi Selatan, Kebon Jeruk, Jakarta Barat, DKI Jakarta 11560'),
('andalan_ids', '[1,2,3,13]'),
('andalan_judul', 'Andalan kami'),
('andalan_kicker', 'Yang paling sering dipesan'),
('email', ''),
('faq_judul', 'Pertanyaan yang sering diajukan.'),
('faq_lead', 'Jawaban cepat untuk hal-hal yang paling sering ditanyakan pelanggan Mampir Kopi.'),
('galeri_catatan', 'Klik foto untuk memperbesar'),
('galeri_judul', 'Galeri'),
('galeri_kicker', 'Yang kami seduh'),
('hero_dash_judul', 'Halo, {nama}!'),
('hero_dash_kicker', 'Mampir Kopi · Ruang untuk Menikmati'),
('hero_dash_sub', 'Luangkan waktu sejenak dan temukan kopi yang pas untuk harimu.'),
('hero_tamu_judul', 'Temukan kopi untuk menemani harimu.'),
('hero_tamu_kicker', 'Mampir Kopi · Ruang untuk Menikmati'),
('hero_tamu_sub', 'Jelajahi pilihan kopi dan minuman kami. Masuk atau daftar untuk mulai memesan.'),
('instagram', '@mampir.kopi_'),
('intro_judul', 'Tempat singgah di Kebon Jeruk'),
('intro_kicker', 'Tentang Mampir Kopi'),
('intro_teks', 'Awalnya bukan kedai. Cuma kebiasaan menyeduh kopi untuk tetangga dan teman yang mampir ke teras kecil kami di Sukabumi Selatan. Yang datang makin banyak, sampai akhirnya teras itu jadi kedai.\n\nMenunya tetap sederhana: kopi susu andalan, kopi tubruk, manual brew, dan wedang jahe buat hari hujan. Semua diseduh per pesanan, harganya tetap bersahabat.'),
('jam_buka', 'Setiap hari · 08.00–22.00 WIB'),
('konten_seed_v1', '1'),
('login_judul', 'Tempat singgah untuk menikmati jeda.'),
('login_kicker', 'Kopi yang dibuat dengan hati'),
('nama_kedai', 'Mampir Kopi'),
('ongkir', '5000'),
('privasi_isi', '# Data yang kami simpan\n\nSaat mendaftar kami menyimpan nama, username, email, dan password yang terenkripsi (hash) — kami tidak pernah menyimpan password dalam bentuk teks biasa. Bila kamu mengisi profil, foto profilmu tersimpan di database kami. Riwayat pesanan dan pembayaranmu tersimpan sebagai catatan transaksi kedai.\n\n# Untuk apa data itu dipakai\n\nHanya untuk menjalankan layanan: memproses pesanan, memverifikasi pembayaran, menampilkan riwayat belanjamu, dan menghubungi kamu bila ada hal penting soal pesananmu. Kami tidak menjual data pelanggan ke pihak mana pun.\n\n# Testimoni dan pesan\n\nTestimoni yang kamu kirim tampil dengan nama depanmu setelah disetujui admin. Pesan dari formulir kontak hanya dibaca oleh admin kedai.\n\n# Hakmu\n\nKamu bisa mengubah nama, username, email, password, dan foto kapan saja lewat Edit Profil, termasuk menghapus foto profil. Untuk penghapusan akun dan seluruh datanya, hubungi admin lewat halaman Kontak Kami.\n\n# Keamanan\n\nSitus ini berjalan dengan sesi login yang aman dan pemeriksaan CSRF pada setiap pengiriman data. Meski begitu, jagalah password-mu dan jangan membagikannya ke siapa pun — admin Mampir Kopi tidak pernah meminta password pelanggan.'),
('privasi_judul', 'Datamu, kami jaga.'),
('privasi_lead', 'Penjelasan sederhana tentang data apa yang kami simpan dan untuk apa.'),
('syarat_isi', '# 1. Umum\n\nWebsite ini adalah layanan pemesanan resmi Mampir Kopi, Kebon Jeruk, Jakarta Barat. Harga, menu, dan ketersediaan stok dapat berubah sewaktu-waktu mengikuti kondisi kedai.\n\n# 2. Akun\n\nKamu bertanggung jawab atas akun dan password-mu sendiri. Satu akun dipakai untuk satu orang; pesanan yang dibuat lewat akunmu dianggap dibuat olehmu.\n\n# 3. Pemesanan\n\nPesanan dianggap masuk setelah kamu menekan Bayar Sekarang dan menerima kode pesanan. Harga yang berlaku adalah harga dari database kami saat pesanan dibuat, bukan harga pada tangkapan layar atau salinan lama.\n\n# 4. Pembayaran\n\nPembayaran QRIS/e-wallet wajib diselesaikan dalam 5 menit; pesanan yang belum terverifikasi lewat dari batas itu dibatalkan otomatis. Pembayaran dinyatakan sah setelah diverifikasi admin. COD dibayar tunai penuh saat pesanan diterima.\n\n# 5. Pengantaran\n\nLayanan antar dikenakan ongkir tetap Rp5.000 untuk wilayah jangkauan kedai. Pastikan alamat yang ditulis lengkap dan benar; keterlambatan karena alamat tidak jelas di luar tanggung jawab kami.\n\n# 6. Pengembalian\n\nPengembalian hanya untuk pesanan berstatus Selesai dan diajukan lewat menu Riwayat dengan alasan yang jelas. Bila disetujui admin, dana dikembalikan dan pesanan dibatalkan.\n\n# 7. Promo\n\nKode promo hanya berlaku selama masa aktifnya dan sesuai syarat minimum belanja. Satu pesanan hanya bisa memakai satu kode promo.'),
('syarat_judul', 'Aturan di Mampir Kopi'),
('syarat_lead', 'Dengan memakai website dan layanan Mampir Kopi, kamu menyetujui ketentuan berikut.'),
('telepon', '+62 896-2264-2662'),
('tentang_cerita', 'Awalnya bukan kedai. Cuma kebiasaan menyeduh kopi untuk tetangga dan teman yang mampir ke teras kecil kami di Sukabumi Selatan. Yang datang makin banyak, yang minta dibuatkan kopi makin sering, sampai akhirnya teras itu jadi kedai. Menunya tetap sederhana: kopi susu andalan, kopi tubruk, manual brew, dan wedang jahe buat hari hujan.\n\nLewat website ini kamu bisa lihat menu, pesan dulu, lalu tinggal ambil di kasir. Atau minta diantar ke alamatmu. Kode pesanannya bisa dilacak kapan saja dari halaman Lacak Pesanan.'),
('tentang_judul', 'Kedai kecil, rasa yang serius.'),
('tentang_lead', 'Mampir Kopi adalah kedai kopi rumahan di Kebon Jeruk, Jakarta Barat. Buat kami, kopi yang baik tidak harus mahal atau ribet. Cukup diseduh dengan benar, disajikan hangat, dan dinikmati tanpa buru-buru.'),
('tentang_misi', 'Kopi dan minuman yang rasanya sama enaknya setiap hari, dari bahan yang kami pilih sendiri.\nPelayanan yang ramah dan jujur, dari kamu pesan di web sampai pesananmu siap diambil.\nHarga yang tetap terjangkau, tanpa mengorbankan rasa.\nBiji kopi Indonesia dari pemasok lokal yang kami kenal, bukan dari gudang entah di mana.'),
('tentang_visi', 'Kami mau jadi tempat singgah yang pertama diingat warga Kebon Jeruk kalau lagi pengin kopi. Bukan cuma karena rasanya, tapi karena betah: bisa kerja, bisa belajar, bisa ngobrol, dan besoknya pengin balik lagi.');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengeluaran`
--

CREATE TABLE `pengeluaran` (
  `id_pengeluaran` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(200) NOT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengembalian`
--

CREATE TABLE `pengembalian` (
  `id_pengembalian` int(11) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `alasan` text NOT NULL,
  `status` varchar(20) DEFAULT 'Menunggu',
  `dibuat` datetime DEFAULT current_timestamp(),
  `jenis` varchar(20) DEFAULT 'Pengembalian',
  `metode_refund` varchar(30) DEFAULT NULL,
  `tujuan_refund` varchar(100) DEFAULT NULL,
  `jumlah_refund` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengembalian`
--


-- --------------------------------------------------------

--
-- Struktur dari tabel `pesanan`
--

CREATE TABLE `pesanan` (
  `id_pesanan` int(11) NOT NULL,
  `kode_pesanan` varchar(20) NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `tanggal_pesanan` datetime NOT NULL DEFAULT current_timestamp(),
  `subtotal` int(11) NOT NULL DEFAULT 0,
  `diskon` int(11) NOT NULL DEFAULT 0,
  `total_harga` int(11) NOT NULL,
  `kode_promo` varchar(30) DEFAULT NULL,
  `status` enum('Menunggu','Diproses','Selesai','Dibatalkan') NOT NULL DEFAULT 'Menunggu',
  `cara_ambil` varchar(20) DEFAULT 'Ambil di Kasir',
  `alamat_antar` text DEFAULT NULL,
  `ongkir` int(11) DEFAULT 0,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `pernah_selesai` tinyint(4) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pesanan`
--


-- --------------------------------------------------------

--
-- Struktur dari tabel `pesan_kontak`
--

CREATE TABLE `pesan_kontak` (
  `id_pesan` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `pesan` text NOT NULL,
  `dibaca` tinyint(4) DEFAULT 0,
  `dibuat` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `promo`
--

CREATE TABLE `promo` (
  `id_promo` int(11) NOT NULL,
  `kode` varchar(30) NOT NULL,
  `judul` varchar(80) NOT NULL,
  `deskripsi` varchar(200) DEFAULT NULL,
  `tipe` enum('persen','nominal') NOT NULL DEFAULT 'persen',
  `nilai` int(11) NOT NULL,
  `min_transaksi` int(11) NOT NULL DEFAULT 0,
  `berlaku_sampai` date DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `promo`
--

INSERT INTO `promo` (`id_promo`, `kode`, `judul`, `deskripsi`, `tipe`, `nilai`, `min_transaksi`, `berlaku_sampai`, `aktif`) VALUES
(1, 'NGOPI10', 'Diskon 10%', 'Potongan 10% untuk semua pesanan, tanpa minimum belanja.', 'persen', 10, 0, '2026-12-31', 1),
(2, 'MAMPIR20', 'Diskon Rp20.000', 'Potongan Rp20.000 untuk belanja minimal Rp100.000.', 'nominal', 20000, 100000, '2026-12-31', 1),
(3, 'HEMAT5', 'Hemat Rp5.000', 'Potongan Rp5.000 untuk belanja minimal Rp30.000.', 'nominal', 5000, 30000, NULL, 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `testimoni`
--

CREATE TABLE `testimoni` (
  `id_testimoni` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `isi` text NOT NULL,
  `rating` tinyint(4) DEFAULT 5,
  `aktif` tinyint(4) DEFAULT 0,
  `dibuat` datetime DEFAULT current_timestamp(),
  `id_pelanggan` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `testimoni`
--


-- --------------------------------------------------------

--
-- Struktur dari tabel `wishlist`
--

CREATE TABLE `wishlist` (
  `id_wishlist` int(11) NOT NULL,
  `id_pelanggan` int(11) NOT NULL,
  `id_menu` int(11) NOT NULL,
  `dibuat` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `wishlist`
--


--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `artikel`
--
ALTER TABLE `artikel`
  ADD PRIMARY KEY (`id_artikel`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indeks untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `id_pesanan` (`id_pesanan`),
  ADD KEY `fk_detail_menu` (`id_menu`);

--
-- Indeks untuk tabel `faq`
--
ALTER TABLE `faq`
  ADD PRIMARY KEY (`id_faq`);

--
-- Indeks untuk tabel `galeri`
--
ALTER TABLE `galeri`
  ADD PRIMARY KEY (`id_galeri`);

--
-- Indeks untuk tabel `interaksi`
--
ALTER TABLE `interaksi`
  ADD PRIMARY KEY (`id_interaksi`);

--
-- Indeks untuk tabel `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id_kategori`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indeks untuk tabel `kunjungan`
--
ALTER TABLE `kunjungan`
  ADD PRIMARY KEY (`tanggal`);

--
-- Indeks untuk tabel `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`id_menu`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `id_kategori` (`id_kategori`);

--
-- Indeks untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`id_pelanggan`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id_pembayaran`),
  ADD UNIQUE KEY `kode_transaksi` (`kode_transaksi`),
  ADD KEY `id_pesanan` (`id_pesanan`);

--
-- Indeks untuk tabel `pengaturan`
--
ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`kunci`);

--
-- Indeks untuk tabel `pengeluaran`
--
ALTER TABLE `pengeluaran`
  ADD PRIMARY KEY (`id_pengeluaran`);

--
-- Indeks untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD PRIMARY KEY (`id_pengembalian`);

--
-- Indeks untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id_pesanan`),
  ADD UNIQUE KEY `kode_pesanan` (`kode_pesanan`),
  ADD UNIQUE KEY `uq_idem` (`id_pelanggan`,`idempotency_key`),
  ADD KEY `id_pelanggan` (`id_pelanggan`);

--
-- Indeks untuk tabel `pesan_kontak`
--
ALTER TABLE `pesan_kontak`
  ADD PRIMARY KEY (`id_pesan`);

--
-- Indeks untuk tabel `promo`
--
ALTER TABLE `promo`
  ADD PRIMARY KEY (`id_promo`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indeks untuk tabel `testimoni`
--
ALTER TABLE `testimoni`
  ADD PRIMARY KEY (`id_testimoni`);

--
-- Indeks untuk tabel `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id_wishlist`),
  ADD UNIQUE KEY `uq_wish` (`id_pelanggan`,`id_menu`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `artikel`
--
ALTER TABLE `artikel`
  MODIFY `id_artikel` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT untuk tabel `faq`
--
ALTER TABLE `faq`
  MODIFY `id_faq` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `galeri`
--
ALTER TABLE `galeri`
  MODIFY `id_galeri` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `interaksi`
--
ALTER TABLE `interaksi`
  MODIFY `id_interaksi` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id_kategori` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `menu`
--
ALTER TABLE `menu`
  MODIFY `id_menu` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT untuk tabel `pelanggan`
--
ALTER TABLE `pelanggan`
  MODIFY `id_pelanggan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id_pembayaran` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `pengeluaran`
--
ALTER TABLE `pengeluaran`
  MODIFY `id_pengeluaran` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  MODIFY `id_pengembalian` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id_pesanan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `pesan_kontak`
--
ALTER TABLE `pesan_kontak`
  MODIFY `id_pesan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `promo`
--
ALTER TABLE `promo`
  MODIFY `id_promo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `testimoni`
--
ALTER TABLE `testimoni`
  MODIFY `id_testimoni` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id_wishlist` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD CONSTRAINT `fk_detail_menu` FOREIGN KEY (`id_menu`) REFERENCES `menu` (`id_menu`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_detail_pesanan` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `menu`
--
ALTER TABLE `menu`
  ADD CONSTRAINT `fk_menu_kategori` FOREIGN KEY (`id_kategori`) REFERENCES `kategori` (`id_kategori`);

--
-- Ketidakleluasaan untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `fk_bayar_pesanan` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `fk_pesanan_pelanggan` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id_pelanggan`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
