<?php
/* MAMPIR KOPI — konfigurasi & fungsi bantu.  Sesuaikan 4 baris DB_* sesuai server Anda. */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'db_kedai_kopi';
const DB_USER = 'root';
const DB_PASS = '';           // XAMPP default: kosong

date_default_timezone_set('Asia/Jakarta');

class ApiError extends Exception {}

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+07:00'");
    }
    return $pdo;
}

function json_start(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    set_exception_handler(function (Throwable $e) {
        $isApi = $e instanceof ApiError;
        http_response_code($isApi ? 400 : 500);
        if (!$isApi) error_log('[MampirKopi] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => $isApi ? $e->getMessage()
            : 'Kesalahan server. Pastikan database sudah diimpor dan server aktif, lalu coba lagi.']);
        exit;
    });
}
function ok($data = null): void { echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE); exit; }
function fail(string $msg): void { throw new ApiError($msg); }
function body(): array {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw ?: '', true);
    return is_array($j) ? $j : $_POST;
}
function slugify(string $s): string {
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'item';
}

/* Batas waktu pembayaran (menit): pesanan yang pembayarannya belum terverifikasi
   dalam batas ini dibatalkan otomatis. Stok pesanan yang belum terverifikasi belum
   pernah terpotong, jadi pembatalan tidak memerlukan pengembalian stok. Pesanan
   yang pembayarannya sudah terverifikasi tidak pernah disentuh fungsi ini. */
const BATAS_BAYAR_MENIT = 5;

function sapu_pesanan_kadaluarsa(PDO $pdo): void {
    $pdo->exec("UPDATE pesanan SET status = 'Dibatalkan'
                WHERE status = 'Menunggu'
                  AND tanggal_pesanan < NOW() - INTERVAL " . BATAS_BAYAR_MENIT . " MINUTE
                  AND NOT EXISTS (SELECT 1 FROM pembayaran b WHERE b.id_pesanan = pesanan.id_pesanan AND b.status = 'Terverifikasi')
                  AND NOT EXISTS (SELECT 1 FROM pembayaran b2 WHERE b2.id_pesanan = pesanan.id_pesanan AND b2.metode = 'COD')");
    // L2-M4: pesanan Diproses tanpa pembayaran terverifikasi yang sudah
    // melewati batas waktu ikut dibatalkan. Sejak perbaikan L2-M4, pesanan
    // baru tidak bisa lagi mencapai Diproses tanpa verifikasi (kecuali COD);
    // klausa ini membersihkan pesanan warisan dari celah tersebut. COD dan
    // pesanan terverifikasi tetap dikecualikan.
    $pdo->exec("UPDATE pesanan SET status = 'Dibatalkan'
                WHERE status = 'Diproses'
                  AND tanggal_pesanan < NOW() - INTERVAL " . BATAS_BAYAR_MENIT . " MINUTE
                  AND NOT EXISTS (SELECT 1 FROM pembayaran b WHERE b.id_pesanan = pesanan.id_pesanan AND b.status = 'Terverifikasi')
                  AND NOT EXISTS (SELECT 1 FROM pembayaran b2 WHERE b2.id_pesanan = pesanan.id_pesanan AND b2.metode = 'COD')");
    // L-m3: pembayaran pesanan yang dibatalkan sweeper jangan dibiarkan
    // 'Menunggu Verifikasi' selamanya — tandai 'Kadaluarsa' supaya antrean
    // verifikasi admin tidak tercemar baris mati (tidak bisa diverifikasi lagi).
    $pdo->exec("UPDATE pembayaran b JOIN pesanan p ON p.id_pesanan = b.id_pesanan
                SET b.status = 'Kadaluarsa'
                WHERE b.status = 'Menunggu Verifikasi' AND p.status = 'Dibatalkan'
                  AND p.tanggal_pesanan < NOW() - INTERVAL " . BATAS_BAYAR_MENIT . " MINUTE
                  AND NOT EXISTS (SELECT 1 FROM pembayaran b3 WHERE b3.id_pesanan = p.id_pesanan AND b3.status = 'Terverifikasi')
                  AND NOT EXISTS (SELECT 1 FROM pembayaran b2 WHERE b2.id_pesanan = p.id_pesanan AND b2.metode = 'COD')");
}

/* Pastikan kolom foto profil pelanggan tersedia (dibuat otomatis bila belum ada,
   supaya database lama tetap bisa dipakai tanpa impor ulang). Foto profil disimpan
   sebagai BLOB di database, bukan sebagai file. */
function pastikan_kolom_foto(PDO $pdo): void {
    static $sudah = false;
    if ($sudah) return;
    $sudah = true;
    $ada = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pelanggan' AND COLUMN_NAME = 'foto'")->fetchColumn();
    if (!$ada) {
        $pdo->exec("ALTER TABLE pelanggan ADD COLUMN foto MEDIUMBLOB NULL, ADD COLUMN foto_mime VARCHAR(50) NULL, ADD COLUMN foto_diubah DATETIME NULL");
    }
}

/* PROFIL ADMIN 22 Nov: kolom foto yang sama untuk tabel admin — cermin
   pastikan_kolom_foto() di atas, supaya admin bisa pasang foto profil
   seperti pelanggan. */
function pastikan_kolom_foto_admin(PDO $pdo): void {
    static $sudah = false;
    if ($sudah) return;
    $sudah = true;
    $ada = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin' AND COLUMN_NAME = 'foto'")->fetchColumn();
    if (!$ada) {
        $pdo->exec("ALTER TABLE admin ADD COLUMN foto MEDIUMBLOB NULL, ADD COLUMN foto_mime VARCHAR(50) NULL, ADD COLUMN foto_diubah DATETIME NULL");
    }
}

/* MODE GELAP (22 Nov 2026): preferensi tema pelanggan ('terang'/'gelap').
   Pola yang sama seperti pastikan_kolom_foto() — dipanggil dari
   pastikan_tabel_fitur() (semua jalur API) dan dari endpoint `me`. */
function pastikan_kolom_tema(PDO $pdo): void {
    static $sudah = false;
    if ($sudah) return;
    $sudah = true;
    $ada = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pelanggan' AND COLUMN_NAME = 'pref_tema'")->fetchColumn();
    if (!$ada) $pdo->exec("ALTER TABLE pelanggan ADD COLUMN pref_tema VARCHAR(10) NOT NULL DEFAULT 'terang'");
}

/* L-m6: nomor versi sesi pelanggan. Disimpan di sesi saat login dan dicocokkan
   tiap request oleh pelanggan_id(); dinaikkan saat ganti/reset password dan
   saat status akun diubah, sehingga sesi lama (mis. di perangkat lain) mati
   seketika. Sesi yang dibuat sebelum kolom ini ada tidak membawa versi dan
   tetap lolos cek versi (hanya cek keberadaan akun yang berlaku). */
function pastikan_sesi_versi(PDO $pdo): void {
    static $sudah = false;
    if ($sudah) return;
    $sudah = true;
    $ada = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pelanggan' AND COLUMN_NAME = 'sesi_versi'")->fetchColumn();
    if (!$ada) $pdo->exec("ALTER TABLE pelanggan ADD COLUMN sesi_versi INT NOT NULL DEFAULT 0");
}

/* L2-m9: nomor versi sesi admin — pola yang sama seperti pelanggan
   (lihat pastikan_sesi_versi()). Dinaikkan saat password admin diganti
   lewat admin_save; dicocokkan tiap request oleh wajib_admin(). */
function pastikan_sesi_versi_admin(PDO $pdo): void {
    static $sudah = false;
    if ($sudah) return;
    $sudah = true;
    $ada = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin' AND COLUMN_NAME = 'sesi_versi'")->fetchColumn();
    if (!$ada) $pdo->exec("ALTER TABLE admin ADD COLUMN sesi_versi INT NOT NULL DEFAULT 0");
}

const ONGKIR_FLAT = 5000;

/* Ongkir diantar saat ini: nilai dari pengaturan situs (bisa diubah admin di
   Kelola Pengiriman), dengan ONGKIR_FLAT sebagai cadangan bila belum ada. */
function ongkir_flat(PDO $pdo): int {
    $s = ambil_pengaturan($pdo);
    $v = (int)($s['ongkir'] ?? ONGKIR_FLAT);
    return $v >= 0 ? $v : ONGKIR_FLAT;
}

/* Fondasi fitur tambahan (wishlist, testimoni, blog, kontak, pengaturan situs,
   pengeluaran, pengembalian, CRM, statistik kunjungan, pengantaran). Semua tabel
   dan kolom dibuat otomatis bila belum ada, supaya database lama tetap bisa
   dipakai tanpa impor ulang — pola yang sama seperti pastikan_kolom_foto(). */
function pastikan_tabel_fitur(PDO $pdo): void {
    static $sudah = false;
    if ($sudah) return;
    $sudah = true;
    pastikan_kolom_tema($pdo); /* MODE GELAP: kolom pref_tema pelanggan */
    $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
        id_wishlist INT AUTO_INCREMENT PRIMARY KEY,
        id_pelanggan INT NOT NULL, id_menu INT NOT NULL, dibuat DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_wish (id_pelanggan, id_menu)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS testimoni (
        id_testimoni INT AUTO_INCREMENT PRIMARY KEY,
        id_pelanggan INT NULL, nama VARCHAR(100) NOT NULL, isi TEXT NOT NULL, rating TINYINT DEFAULT 5,
        aktif TINYINT DEFAULT 0, dibuat DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS artikel (
        id_artikel INT AUTO_INCREMENT PRIMARY KEY,
        judul VARCHAR(200) NOT NULL, slug VARCHAR(220) NOT NULL UNIQUE, ringkasan VARCHAR(300) DEFAULT '',
        isi TEXT NOT NULL, gambar VARCHAR(255) DEFAULT '', dibuat DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pesan_kontak (
        id_pesan INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL, pesan TEXT NOT NULL,
        dibaca TINYINT DEFAULT 0, dibuat DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pengaturan (
        kunci VARCHAR(60) PRIMARY KEY, nilai TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pengeluaran (
        id_pengeluaran INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL, keterangan VARCHAR(200) NOT NULL, jumlah INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pengembalian (
        id_pengembalian INT AUTO_INCREMENT PRIMARY KEY,
        id_pesanan INT NOT NULL, id_pelanggan INT NOT NULL, alasan TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'Menunggu', dibuat DATETIME DEFAULT CURRENT_TIMESTAMP,
        jenis VARCHAR(20) DEFAULT 'Pengembalian', metode_refund VARCHAR(30) NULL,
        tujuan_refund VARCHAR(100) NULL, jumlah_refund INT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS interaksi (
        id_interaksi INT AUTO_INCREMENT PRIMARY KEY,
        id_pelanggan INT NOT NULL, catatan TEXT NOT NULL, dibuat DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS kunjungan (
        tanggal DATE PRIMARY KEY, jumlah INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Kolom pengantaran di pesanan + preferensi notifikasi di pelanggan.
    $kol = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pesanan'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('cara_ambil', $kol, true)) $pdo->exec("ALTER TABLE pesanan ADD COLUMN cara_ambil VARCHAR(20) DEFAULT 'Ambil di Kasir', ADD COLUMN alamat_antar TEXT NULL, ADD COLUMN ongkir INT DEFAULT 0");
    $kol2 = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pelanggan' AND COLUMN_NAME = 'pref_notifikasi'")->fetchColumn();
    if (!$kol2) $pdo->exec("ALTER TABLE pelanggan ADD COLUMN pref_notifikasi TINYINT DEFAULT 1");
    // Testimoni: tautkan ke pengirim (nullable — testimoni lama & yang ditambah
    // admin manual tidak punya pengirim) agar foto profilnya bisa tampil.
    $adaTestiPlg = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'testimoni' AND COLUMN_NAME = 'id_pelanggan'")->fetchColumn();
    if (!$adaTestiPlg) $pdo->exec("ALTER TABLE testimoni ADD COLUMN id_pelanggan INT NULL");
    // Status pembayaran: perluas ENUM bawaan bila ada nilai yang belum ada
    // (L-M6: 'Ditolak' untuk penolakan admin; L-m3: 'Kadaluarsa' untuk
    // pembayaran pesanan yang dibatalkan sweeper). Idempotent dan aman diulang:
    // nilai yang sudah ada dipertahankan urutannya, yang kurang ditambahkan.
    $tipeStatus = $pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembayaran' AND COLUMN_NAME = 'status'")->fetchColumn();
    if (is_string($tipeStatus) && stripos($tipeStatus, 'enum(') === 0) {
        preg_match_all("/'([^']*)'/", $tipeStatus, $mm);
        $vals = $mm[1];
        $kurang = array_diff(['Menunggu Verifikasi', 'Terverifikasi', 'Refund', 'Ditolak', 'Kadaluarsa'], $vals);
        if ($kurang) {
            foreach (['Menunggu Verifikasi', 'Terverifikasi', 'Refund', 'Ditolak', 'Kadaluarsa'] as $v) if (!in_array($v, $vals, true)) $vals[] = $v;
            $pdo->exec("ALTER TABLE pembayaran MODIFY status ENUM('" . implode("','", array_map(fn($v) => str_replace("'", "''", $v), $vals)) . "') DEFAULT 'Menunggu Verifikasi'");
        }
    }
    // L-M6: kolom alasan penolakan pembayaran (nullable; diisi endpoint pembayaran_tolak).
    $adaAlasan = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembayaran' AND COLUMN_NAME = 'alasan'")->fetchColumn();
    if (!$adaAlasan) $pdo->exec("ALTER TABLE pembayaran ADD COLUMN alasan TEXT NULL");
    // L-m7: kunci idempotensi order — retry dengan kunci sama mengembalikan
    // pesanan yang sudah ada, bukan membuat pesanan kedua (unik per pelanggan).
    $adaIdem = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pesanan' AND COLUMN_NAME = 'idempotency_key'")->fetchColumn();
    if (!$adaIdem) $pdo->exec("ALTER TABLE pesanan ADD COLUMN idempotency_key VARCHAR(64) NULL");
    $adaIdxIdem = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pesanan' AND INDEX_NAME = 'uq_idem'")->fetchColumn();
    if (!$adaIdxIdem) $pdo->exec("ALTER TABLE pesanan ADD UNIQUE KEY uq_idem (id_pelanggan, idempotency_key)");
    // L-m6: nomor versi sesi pelanggan (lihat pastikan_sesi_versi()).
    pastikan_sesi_versi($pdo);
    // L2-m9: nomor versi sesi admin (lihat pastikan_sesi_versi_admin()).
    pastikan_sesi_versi_admin($pdo);
    // L2-M2/L3-M1: jejak "pernah Selesai" untuk aritmetika stok yang benar.
    // Batal dari Selesai tidak mengembalikan stok (barang sudah dikonsumsi),
    // jadi buka-ulang pesanan yang pernah Selesai tidak boleh memotong stok
    // lagi (L3-M1); dan batal dari Menunggu/Diproses sesudah pernah Selesai
    // juga tidak boleh mengembalikan stok (L2-M2). Satu kolom menutup
    // keduanya. Backfill: pesanan yang saat ini Selesai ditandai pernah
    // Selesai. (Pesanan yang sudah Dibatalkan sebelum kolom ini ada tidak
    // bisa dilacak riwayatnya — keterbatasan yang dicatat di FIX-REPORT.)
    $adaPernah = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pesanan' AND COLUMN_NAME = 'pernah_selesai'")->fetchColumn();
    if (!$adaPernah) {
        $pdo->exec("ALTER TABLE pesanan ADD COLUMN pernah_selesai TINYINT NOT NULL DEFAULT 0");
        $pdo->exec("UPDATE pesanan SET pernah_selesai = 1 WHERE status = 'Selesai'");
    }
    // Rubrik #25 (22 Nov 2026): gambar kategori — kolom baru, diisi lewat
    // Kelola Kategori di panel admin. NULL = kategori memakai ikon bawaan.
    $adaGambarKat = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kategori' AND COLUMN_NAME = 'gambar'")->fetchColumn();
    if (!$adaGambarKat) $pdo->exec("ALTER TABLE kategori ADD COLUMN gambar VARCHAR(255) NULL");
    // REFUND 22 Nov: pengajuan refund dana perlu mencatat JENIS pengajuan,
    // TUJUAN dana dikembalikan, dan JUMLAHNYA. Kolom baru, nullable supaya
    // pengajuan lama tetap valid (jenis default 'Pengembalian' = perilaku lama).
    $kolKembali = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengembalian'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('jenis', $kolKembali, true)) $pdo->exec("ALTER TABLE pengembalian ADD COLUMN jenis VARCHAR(20) DEFAULT 'Pengembalian', ADD COLUMN metode_refund VARCHAR(30) NULL, ADD COLUMN tujuan_refund VARCHAR(100) NULL, ADD COLUMN jumlah_refund INT NULL");
    // Nilai awal pengaturan situs (bisa diubah admin di Pengaturan Website).
    $def = [
        'nama_kedai' => 'Mampir Kopi',
        'alamat' => 'Jl. Ff No. 24, RT.7/RW.5, Sukabumi Selatan, Kebon Jeruk, Jakarta Barat, DKI Jakarta 11560',
        'telepon' => '+62 896-2264-2662', 'email' => '', 'jam_buka' => 'Setiap hari · 08.00–22.00 WIB',
        'instagram' => '@mampir.kopi_',
        // Rubrik #22 (22 Nov 2026): ongkir diantar sebagai nilai pengaturan
        // (sebelumnya konstanta ONGKIR_FLAT saja) — bisa diubah admin.
        'ongkir' => '5000',
    ];
    $ins = $pdo->prepare('INSERT IGNORE INTO pengaturan (kunci, nilai) VALUES (?, ?)');
    foreach ($def as $k => $v) $ins->execute([$k, $v]);
    // Nomor WA kedai (Amar, 22 Nov 2026): isi telepon yang masih kosong
    // (DB lama sudah punya baris telepon='' dari seed lama, jadi INSERT
    // IGNORE di atas tidak mengubahnya). Tidak menimpa nomor yang sudah
    // diubah admin.
    $pdo->exec("UPDATE pengaturan SET nilai = '+62 896-2264-2662' WHERE kunci = 'telepon' AND (nilai IS NULL OR nilai = '')");
    // Harga poster menu (Amar, 22 Nov 2026): harga poster adalah yang
    // benar. Hanya ubah harga yang masih sama persis dengan harga lama
    // (bila admin sudah mengubah manual, tidak ditimpa). Lemon Tea dan
    // Yakult Rosella SENGAJA tidak ada di sini: poster hanya mencantumkan
    // satu harga untuk keduanya (Lemon Tea "Reguler: 8K", Yakult "Small:
    // 12K") dan Amar memutuskan (13:23 WIB) keduanya tetap mengikuti
    // harga web (12K/14K), jangan diganti.
    $hargaPoster = [
        // slug, Small lama, Regular lama, Small baru, Regular baru
        ['kopi-susu-mampir', 13000, 15000, 13000, 17000],
        ['kopi-susu-singgah', 13000, 15000, 13000, 17000],
        ['kopi-susu-butterscotch', 13000, 15000, 13000, 17000],
        ['chocolate', 10000, 12000, 13000, 17000],
        ['green-tea-latte', 10000, 12000, 13000, 17000],
        ['red-velvet-latte', 12000, 14000, 13000, 17000],
        ['butterfly-lemon', 10000, 12000, 10000, 14000],
        ['americano', 10000, 12000, 10000, 13000],
        ['mocha-latte', 13000, 15000, 13000, 17000],
        ['sea-salt-latte', 15000, 17000, 18000, 22000],
    ];
    $stHarga = $pdo->prepare('UPDATE menu SET harga = ?, harga_regular = ? WHERE slug = ? AND harga = ? AND harga_regular = ?');
    foreach ($hargaPoster as [$slug, $lama, $lamaReg, $baru, $baruReg]) {
        $stHarga->execute([$baru, $baruReg, $slug, $lama, $lamaReg]);
    }
    // Contoh testimoni & artikel awal (bisa diedit/dihapus admin).
    if (!$pdo->query('SELECT COUNT(*) FROM testimoni')->fetchColumn()) {
        $st = $pdo->prepare('INSERT INTO testimoni (nama, isi, rating, aktif) VALUES (?, ?, ?, 1)');
        $st->execute(['Rani', 'Kopi Susu Mampir-nya konsisten enak, harganya bersahabat buat kantong mahasiswa. Tempatnya juga nyaman buat nugas.', 5]);
        $st->execute(['Bagas', 'Pesan dari web, sampai kedai tinggal ambil. Manual brew-nya wajib coba buat yang suka kopi tanpa gula.', 5]);
        $st->execute(['Sinta', 'Wedang jahe-nya juara pas lagi hujan. Pelayanannya cepat dan ramah.', 4]);
    }
    if (!$pdo->query('SELECT COUNT(*) FROM artikel')->fetchColumn()) {
        $st = $pdo->prepare('INSERT INTO artikel (judul, slug, ringkasan, isi, gambar) VALUES (?, ?, ?, ?, ?)');
        $st->execute(['Cara Menikmati Kopi Tubruk yang Benar', 'cara-menikmati-kopi-tubruk', 'Kopi tubruk itu gampang, tapi ada triknya supaya ampas tidak mengganggu dan rasanya keluar semua.', "Kopi tubruk itu cara seduh paling merakyat di Indonesia. Bubuk kopi langsung bertemu air panas, tanpa alat khusus. Sederhana, tapi ada caranya supaya hasilnya enak dan ampas tidak mengganggu.\n\nPakai air yang baru mendidih, lalu diamkan sekitar 30 detik. Tuang perlahan ke gelas berisi 1,5 sendok makan bubuk kopi, aduk sebentar, lalu tutup gelasnya selama 3 sampai 4 menit. Waktu tunggu ini yang membiarkan ampas turun sendiri ke dasar gelas.\n\nSesudah itu jangan diaduk lagi. Seruput perlahan dari permukaan, di situlah rasa paling bersih berada. Di Mampir Kopi, Kopi Tubruk kami diseduh per pesanan, jadi selalu segar dan hangat.", 'img/promo-ngopi.jpg']);
        $st->execute(['Beda Small dan Regular di Mampir Kopi', 'beda-small-dan-regular', 'Small 200 ml, Regular 300 ml. Bedanya cuma volume, rasanya sama.', "Sering bingung pilih Small atau Regular? Gampang. Small itu 200 ml, pas untuk ngobrol singkat atau kalau kamu cuma butuh dorongan kafein ringan. Regular 300 ml, buat yang duduk lebih lama, nugas, atau memang lagi ingin menikmati pelan-pelan.\n\nRasio kopi dan susunya sama di kedua ukuran, jadi rasanya tidak berubah. Yang berubah cuma volumenya. Harganya pun selisih sedikit, jadi pilih sesuai waktumu hari ini.", 'img/promo-segar.jpg']);
    }
    // Artikel ke-3 (2026-11-15, ronde 4): sisipkan bila belum ada. Blok COUNT
    // di atas tidak jalan lagi di DB yang sudah punya 2 artikel.
    $st = $pdo->prepare("INSERT INTO artikel (judul, slug, ringkasan, isi, gambar) SELECT ?, ?, ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM artikel WHERE slug = ?)");
    $st->execute(['Dari Teras Kecil di Sukabumi Selatan', 'dari-teras-kecil-sukabumi-selatan', 'Awalnya cuma kebiasaan menyeduh kopi untuk tetangga. Teras kecil itu sekarang jadi kedai.', "Mampir Kopi berawal dari teras kecil di Sukabumi Selatan, Kebon Jeruk. Awalnya bukan kedai, hanya kebiasaan menyeduh kopi untuk tetangga dan teman yang mampir. Yang datang makin banyak, yang minta dibuatkan kopi makin sering, sampai akhirnya teras itu berubah jadi kedai kecil.\n\nMenu kami tumbuh dari situ juga. Kopi Susu Mampir, yang sekarang jadi andalan, adalah racikan pertama yang kami buat untuk tetangga. Kopi tubruk dan wedang jahe ada karena pelanggan yang datang pagi-pagi minta yang hangat. Tidak ada yang kami buat asal-asalan. Semua berawal dari yang diminta orang yang mampir.\n\nSampai sekarang, prinsipnya sama: kopi yang baik tidak harus mahal atau ribet. Cukup diseduh dengan benar, disajikan hangat, dan dinikmati tanpa buru-buru. Kalau kamu mampir ke Kebon Jeruk, teras kami masih terbuka. Sekarang dengan meja yang lebih banyak.", 'img/promo-mampir.jpg', 'dari-teras-kecil-sukabumi-selatan']);
    // Artikel ke-4 (ronde 5): grid blog 2 kolom menyisakan 1 kartu yatim
    // dengan 3 artikel — artikel ke-4 melengkapi baris kedua.
    $st->execute(['Biji Kopi Indonesia, dari Kebun ke Cangkir', 'biji-kopi-indonesia-kebun-ke-cangkir', 'Semua kopi kami memakai biji Indonesia, dari pemasok lokal yang kami kenal namanya.', "Semua kopi di Mampir Kopi memakai biji dari Indonesia. Bukan karena tren. Kopi yang baik dimulai dari kebun yang dirawat dengan benar, dan petani Indonesia sudah melakukannya jauh sebelum kedai kami ada.\n\nKami membeli dari pemasok lokal yang kami kenal namanya, bukan dari gudang entah di mana. Bijinya datang segar, kami simpan dengan benar, dan kami giling sedikit demi sedikit supaya aromanya tidak hilang. Cara ini juga yang membuat harga kami tetap bersahabat. Tanpa rantai yang panjang, tanpa biaya yang tidak perlu.\n\nKalau mau merasakan karakter bijinya paling jujur, coba V60 kami. Tanpa susu, tanpa gula. Hanya air panas dan biji yang diseduh pelan. Di situ terasa bedanya biji yang dirawat dengan yang asal-asalan.", 'uploads/menu/menu-1791018171-11387a.jpg', 'biji-kopi-indonesia-kebun-ke-cangkir']);

    // Ronde 6: tiga menu tanpa deskripsi (Americano Peach, Wedang Jahe,
    // Kopi Tubruk Jahe Geprek) — isi hanya yang masih kosong, supaya kartu
    // menu tidak tampil telanjang nama+harga saja.
    $desk = [
        ['americano-peach', 'Americano dingin dengan sirup peach. Pahit kopinya tetap ada, manis buahnya mengikuti di belakang.'],
        ['wedang-jahe', 'Jahe dan gula aren, diseduh hangat. Ini yang paling laku kalau hujan turun.'],
        ['kopi-tubruk-jahe-geprek', 'Kopi tubruk pekat dicampur jahe geprek. Pahit dan hangatnya jalan bareng.'],
    ];
    $st = $pdo->prepare("UPDATE menu SET deskripsi = ? WHERE slug = ? AND (deskripsi IS NULL OR TRIM(deskripsi) = '')");
    foreach ($desk as $d) $st->execute([$d[1], $d[0]]);

    // Tulis ulang copy 22 Nov 2026: deskripsi menu, artikel, dan promo yang
    // lama terdengar seperti tulisan AI. UPDATE hanya menimpa teks lama yang
    // masih persis sama (WHERE deskripsi/ringkasan lama) — teks yang sudah
    // diubah admin lewat panel tidak tersentuh. Idempotent, aman diulang.
    $copyBaru = [ // [slug, deskripsi lama, deskripsi baru]
        ['kopi-susu-mampir', 'Perpaduan kopi pilihan dengan susu full cream yang lembut dan pas di setiap tegukan.', 'Espresso dan susu full cream, manisnya pas. Racikan pertama kami, dan sampai sekarang yang paling sering dipesan.'],
        ['kopi-susu-singgah', 'Kopi susu dengan gula aren yang manis legit dan aroma yang menenangkan.', 'Kopi susu gula aren, manisnya legit dan dalam. Buat yang suka manis tapi tidak mau yang bikin enek.'],
        ['kopi-susu-butterscotch', 'Kopi susu dengan sirup butterscotch karamel yang gurih manis.', 'Kopi susu dengan sirup butterscotch. Manis karamelnya terasa, tapi pahit kopinya tetap ikut.'],
        ['lemon-tea', 'Teh segar dengan perasan lemon, cocok diminum dingin.', 'Teh dingin dengan perasan lemon asli, asamnya nyegerin. Paling pas diminum siang bolong.'],
        ['chocolate', 'Cokelat pekat yang creamy dengan susu segar.', 'Cokelat pekat yang diaduk dengan susu sampai creamy. Tanpa kopi, jadi aman buat yang tidak bisa kafein.'],
        ['green-tea-latte', 'Matcha pilihan berpadu susu yang lembut dan sedikit pahit.', 'Matcha yang dicampur susu. Pahit khas matcha tetap terasa di ujung, susunya yang bikin lembut.'],
        ['red-velvet-latte', 'Red velvet manis dengan susu, tampil cantik dan creamy.', 'Susu red velvet, manis dan creamy. Warnanya merah muda, jadi sering dipesan buat difoto dulu sebelum diminum.'],
        ['butterfly-lemon', 'Minuman bunga telang dengan lemon, warnanya berubah saat diaduk.', 'Bunga telang dan lemon. Aduk pelan, warnanya berubah dari biru ke ungu. Rasanya ringan, asamnya segar.'],
        ['yakult-rosella', 'Perpaduan Yakult dan rosella yang segar asam manis.', 'Yakult dicampur rosella. Asam manisnya khas, dan warnanya merah cantik.'],
        ['americano', 'Espresso dengan air dingin, bersih dan menyegarkan.', 'Espresso dan air dingin, tanpa gula. Buat yang mau rasa kopinya apa adanya.'],
        ['mocha-latte', 'Espresso, cokelat, dan susu dalam satu gelas dingin.', 'Espresso, cokelat, dan susu dingin. Buat yang suka kopi tapi masih mau manis cokelatnya.'],
        ['sea-salt-latte', 'Latte dengan sea salt cream yang gurih dan manis.', 'Latte dengan sea salt cream di atasnya. Gurih asinnya bikin manis susunya terasa lebih hidup.'],
        ['v60', 'Seduhan manual V60 dengan biji kopi pilihan barista.', 'Biji kopi Indonesia diseduh pelan pakai V60. Tanpa susu dan gula, jadi karakternya terasa semua.'],
        ['kopi-tubruk', 'Kopi tubruk tradisional yang pekat dan harum.', 'Kopi hitam pekat ala warung, diseduh langsung di gelas. Pahitnya jujur, harganya cuma lima ribu.'],
        ['susu-jahe-geprek', 'Susu hangat dengan jahe geprek, menghangatkan badan.', 'Susu hangat dengan jahe geprek segar. Diminum malam hari, badan langsung hangat.'],
        ['wedang-jahe', 'Wedang jahe hangat dengan gula aren, penenang di hari hujan.', 'Jahe dan gula aren, diseduh hangat. Ini yang paling laku kalau hujan turun.'],
        ['americano-peach', 'Americano dingin dengan sentuhan peach yang manis segar.', 'Americano dingin dengan sirup peach. Pahit kopinya tetap ada, manis buahnya mengikuti di belakang.'],
        ['kopi-tubruk-jahe-geprek', 'Kopi tubruk pekat dengan jahe geprek yang menghangatkan.', 'Kopi tubruk pekat dicampur jahe geprek. Pahit dan hangatnya jalan bareng.'],
    ];
    $st = $pdo->prepare('UPDATE menu SET deskripsi = ? WHERE slug = ? AND deskripsi = ?');
    foreach ($copyBaru as $c) $st->execute([$c[2], $c[0], $c[1]]);
    $artBaru = [ // [slug, ringkasan lama, ringkasan baru, isi baru]
        ['cara-menikmati-kopi-tubruk', 'Kopi tubruk itu sederhana, tapi ada caranya supaya ampas tidak mengganggu dan rasa keluar maksimal.', 'Kopi tubruk itu gampang, tapi ada triknya supaya ampas tidak mengganggu dan rasanya keluar semua.', "Kopi tubruk itu cara seduh paling merakyat di Indonesia. Bubuk kopi langsung bertemu air panas, tanpa alat khusus. Sederhana, tapi ada caranya supaya hasilnya enak dan ampas tidak mengganggu.\n\nPakai air yang baru mendidih, lalu diamkan sekitar 30 detik. Tuang perlahan ke gelas berisi 1,5 sendok makan bubuk kopi, aduk sebentar, lalu tutup gelasnya selama 3 sampai 4 menit. Waktu tunggu ini yang membiarkan ampas turun sendiri ke dasar gelas.\n\nSesudah itu jangan diaduk lagi. Seruput perlahan dari permukaan, di situlah rasa paling bersih berada. Di Mampir Kopi, Kopi Tubruk kami diseduh per pesanan, jadi selalu segar dan hangat."],
        ['beda-small-dan-regular', 'Dua ukuran, dua kebutuhan. Kenali bedanya supaya pesananmu selalu pas.', 'Small 200 ml, Regular 300 ml. Bedanya cuma volume, rasanya sama.', "Sering bingung pilih Small atau Regular? Gampang. Small itu 200 ml, pas untuk ngobrol singkat atau kalau kamu cuma butuh dorongan kafein ringan. Regular 300 ml, buat yang duduk lebih lama, nugas, atau memang lagi ingin menikmati pelan-pelan.\n\nRasio kopi dan susunya sama di kedua ukuran, jadi rasanya tidak berubah. Yang berubah cuma volumenya. Harganya pun selisih sedikit, jadi pilih sesuai waktumu hari ini."],
        ['dari-teras-kecil-sukabumi-selatan', 'Mampir Kopi berawal dari kebiasaan sederhana: menyeduh kopi untuk tetangga yang mampir.', 'Awalnya cuma kebiasaan menyeduh kopi untuk tetangga. Teras kecil itu sekarang jadi kedai.', "Mampir Kopi berawal dari teras kecil di Sukabumi Selatan, Kebon Jeruk. Awalnya bukan kedai, hanya kebiasaan menyeduh kopi untuk tetangga dan teman yang mampir. Yang datang makin banyak, yang minta dibuatkan kopi makin sering, sampai akhirnya teras itu berubah jadi kedai kecil.\n\nMenu kami tumbuh dari situ juga. Kopi Susu Mampir, yang sekarang jadi andalan, adalah racikan pertama yang kami buat untuk tetangga. Kopi tubruk dan wedang jahe ada karena pelanggan yang datang pagi-pagi minta yang hangat. Tidak ada yang kami buat asal-asalan. Semua berawal dari yang diminta orang yang mampir.\n\nSampai sekarang, prinsipnya sama: kopi yang baik tidak harus mahal atau ribet. Cukup diseduh dengan benar, disajikan hangat, dan dinikmati tanpa buru-buru. Kalau kamu mampir ke Kebon Jeruk, teras kami masih terbuka. Sekarang dengan meja yang lebih banyak."],
        ['biji-kopi-indonesia-kebun-ke-cangkir', 'Kami memakai biji kopi Indonesia — dari petani dan pemasok lokal yang kami kenal.', 'Semua kopi kami memakai biji Indonesia, dari pemasok lokal yang kami kenal namanya.', "Semua kopi di Mampir Kopi memakai biji dari Indonesia. Bukan karena tren. Kopi yang baik dimulai dari kebun yang dirawat dengan benar, dan petani Indonesia sudah melakukannya jauh sebelum kedai kami ada.\n\nKami membeli dari pemasok lokal yang kami kenal namanya, bukan dari gudang entah di mana. Bijinya datang segar, kami simpan dengan benar, dan kami giling sedikit demi sedikit supaya aromanya tidak hilang. Cara ini juga yang membuat harga kami tetap bersahabat. Tanpa rantai yang panjang, tanpa biaya yang tidak perlu.\n\nKalau mau merasakan karakter bijinya paling jujur, coba V60 kami. Tanpa susu, tanpa gula. Hanya air panas dan biji yang diseduh pelan. Di situ terasa bedanya biji yang dirawat dengan yang asal-asalan."],
    ];
    $st = $pdo->prepare('UPDATE artikel SET ringkasan = ?, isi = ? WHERE slug = ? AND ringkasan = ?');
    foreach ($artBaru as $a) $st->execute([$a[2], $a[3], $a[0], $a[1]]);
    $promoBaru = [ // [kode, deskripsi lama, deskripsi baru]
        ['NGOPI10', 'Potongan 10% untuk semua pesanan.', 'Potongan 10% untuk semua pesanan, tanpa minimum belanja.'],
        ['MAMPIR20', 'Min. transaksi Rp100.000.', 'Potongan Rp20.000 untuk belanja minimal Rp100.000.'],
        ['HEMAT5', 'Min. transaksi Rp30.000.', 'Potongan Rp5.000 untuk belanja minimal Rp30.000.'],
    ];
    $st = $pdo->prepare('UPDATE promo SET deskripsi = ? WHERE kode = ? AND deskripsi = ?');
    foreach ($promoBaru as $p) $st->execute([$p[2], $p[0], $p[1]]);

    // L2-m3: sapu pesanan kadaluarsa di setiap request API (fungsi ini
    // dipanggil di awal api/shop.php dan api/admin.php). Sebelumnya sweeper
    // hanya dipanggil di sebagian endpoint, sehingga detail, pengiriman,
    // laporan, keuangan, dan analitik menampilkan status basi. Migrasi di
    // atas sudah jalan dulu, jadi nilai enum Kadaluarsa dijamin ada.
    pastikan_konten($pdo);
    sapu_pesanan_kadaluarsa($pdo);
}

/* ---------- CMS KONTEN (22 Nov 2026) ---------- */
/* Daftar kunci teks konten (tabel pengaturan) + batas panjangnya. Satu
   sumber kebenaran untuk konten_get/konten_save (admin) dan route publik
   konten (shop). Kunci andalan_ids menyimpan JSON array id menu (maks 4). */
function konten_kunci(): array {
    return [
        'hero_tamu_kicker' => 200, 'hero_tamu_judul' => 200, 'hero_tamu_sub' => 300,
        'hero_dash_kicker' => 200, 'hero_dash_judul' => 200, 'hero_dash_sub' => 300,
        'login_kicker' => 200, 'login_judul' => 200,
        'intro_kicker' => 200, 'intro_judul' => 200, 'intro_teks' => 2000,
        'andalan_kicker' => 200, 'andalan_judul' => 200, 'andalan_ids' => 100,
        'galeri_kicker' => 200, 'galeri_judul' => 200, 'galeri_catatan' => 200,
        'tentang_judul' => 200, 'tentang_lead' => 500, 'tentang_visi' => 2000,
        'tentang_misi' => 1000, 'tentang_cerita' => 3000,
        'faq_judul' => 200, 'faq_lead' => 300,
        'syarat_judul' => 200, 'syarat_lead' => 300, 'syarat_isi' => 20000,
        'privasi_judul' => 200, 'privasi_lead' => 300, 'privasi_isi' => 20000,
    ];
}

/* Tabel FAQ + galeri dan seed teks konten. Semua seed VERBATIM dari file
   HTML saat build ini dibuat (22 Nov 2026) — tidak ada kata yang diubah.
   Idempotent: kunci pengaturan memakai INSERT IGNORE (tidak menimpa edit
   admin); baris FAQ/galeri hanya di-seed sekali, ditandai kunci
   konten_seed_v1, supaya penghapusan total oleh admin tidak di-seed ulang. */
function pastikan_konten(PDO $pdo): void {
    static $sudah = false;
    if ($sudah) return;
    $sudah = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS faq (
        id_faq INT AUTO_INCREMENT PRIMARY KEY,
        pertanyaan VARCHAR(200) NOT NULL, jawaban TEXT NOT NULL,
        urutan INT NOT NULL DEFAULT 0, aktif TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS galeri (
        id_galeri INT AUTO_INCREMENT PRIMARY KEY,
        gambar VARCHAR(255) NOT NULL, judul VARCHAR(100) NOT NULL,
        urutan INT NOT NULL DEFAULT 0, aktif TINYINT NOT NULL DEFAULT 1,
        lebar TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $seed = [
        'hero_tamu_kicker' => 'Mampir Kopi · Ruang untuk Menikmati',
        'hero_tamu_judul' => 'Temukan kopi untuk menemani harimu.',
        'hero_tamu_sub' => 'Jelajahi pilihan kopi dan minuman kami. Masuk atau daftar untuk mulai memesan.',
        'hero_dash_kicker' => 'Mampir Kopi · Ruang untuk Menikmati',
        'hero_dash_judul' => 'Halo, {nama}!',
        'hero_dash_sub' => 'Luangkan waktu sejenak dan temukan kopi yang pas untuk harimu.',
        'login_kicker' => 'Kopi yang dibuat dengan hati',
        'login_judul' => 'Tempat singgah untuk menikmati jeda.',
        'intro_kicker' => 'Tentang Mampir Kopi',
        'intro_judul' => 'Tempat singgah di Kebon Jeruk',
        'intro_teks' => "Awalnya bukan kedai. Cuma kebiasaan menyeduh kopi untuk tetangga dan teman yang mampir ke teras kecil kami di Sukabumi Selatan. Yang datang makin banyak, sampai akhirnya teras itu jadi kedai.\n\nMenunya tetap sederhana: kopi susu andalan, kopi tubruk, manual brew, dan wedang jahe buat hari hujan. Semua diseduh per pesanan, harganya tetap bersahabat.",
        'andalan_kicker' => 'Yang paling sering dipesan',
        'andalan_judul' => 'Andalan kami',
        'andalan_ids' => '[1,2,3,13]',
        'galeri_kicker' => 'Yang kami seduh',
        'galeri_judul' => 'Galeri',
        'galeri_catatan' => 'Klik foto untuk memperbesar',
        'tentang_judul' => 'Kedai kecil, rasa yang serius.',
        'tentang_lead' => 'Mampir Kopi adalah kedai kopi rumahan di Kebon Jeruk, Jakarta Barat. Buat kami, kopi yang baik tidak harus mahal atau ribet. Cukup diseduh dengan benar, disajikan hangat, dan dinikmati tanpa buru-buru.',
        'tentang_visi' => 'Kami mau jadi tempat singgah yang pertama diingat warga Kebon Jeruk kalau lagi pengin kopi. Bukan cuma karena rasanya, tapi karena betah: bisa kerja, bisa belajar, bisa ngobrol, dan besoknya pengin balik lagi.',
        'tentang_misi' => "Kopi dan minuman yang rasanya sama enaknya setiap hari, dari bahan yang kami pilih sendiri.\nPelayanan yang ramah dan jujur, dari kamu pesan di web sampai pesananmu siap diambil.\nHarga yang tetap terjangkau, tanpa mengorbankan rasa.\nBiji kopi Indonesia dari pemasok lokal yang kami kenal, bukan dari gudang entah di mana.",
        'tentang_cerita' => "Awalnya bukan kedai. Cuma kebiasaan menyeduh kopi untuk tetangga dan teman yang mampir ke teras kecil kami di Sukabumi Selatan. Yang datang makin banyak, yang minta dibuatkan kopi makin sering, sampai akhirnya teras itu jadi kedai. Menunya tetap sederhana: kopi susu andalan, kopi tubruk, manual brew, dan wedang jahe buat hari hujan.\n\nLewat website ini kamu bisa lihat menu, pesan dulu, lalu tinggal ambil di kasir. Atau minta diantar ke alamatmu. Kode pesanannya bisa dilacak kapan saja dari halaman Lacak Pesanan.",
        'faq_judul' => 'Pertanyaan yang sering diajukan.',
        'faq_lead' => 'Jawaban cepat untuk hal-hal yang paling sering ditanyakan pelanggan Mampir Kopi.',
        'syarat_judul' => 'Aturan main di Mampir Kopi.',
        'syarat_lead' => 'Dengan memakai website dan layanan Mampir Kopi, kamu menyetujui ketentuan berikut.',
        'syarat_isi' => "# 1. Umum\n\nWebsite ini adalah layanan pemesanan resmi Mampir Kopi, Kebon Jeruk, Jakarta Barat. Harga, menu, dan ketersediaan stok dapat berubah sewaktu-waktu mengikuti kondisi kedai.\n\n# 2. Akun\n\nKamu bertanggung jawab atas akun dan password-mu sendiri. Satu akun dipakai untuk satu orang; pesanan yang dibuat lewat akunmu dianggap dibuat olehmu.\n\n# 3. Pemesanan\n\nPesanan dianggap masuk setelah kamu menekan Bayar Sekarang dan menerima kode pesanan. Harga yang berlaku adalah harga dari database kami saat pesanan dibuat, bukan harga pada tangkapan layar atau salinan lama.\n\n# 4. Pembayaran\n\nPembayaran QRIS/e-wallet wajib diselesaikan dalam 5 menit; pesanan yang belum terverifikasi lewat dari batas itu dibatalkan otomatis. Pembayaran dinyatakan sah setelah diverifikasi admin. COD dibayar tunai penuh saat pesanan diterima.\n\n# 5. Pengantaran\n\nLayanan antar dikenakan ongkir tetap Rp5.000 untuk wilayah jangkauan kedai. Pastikan alamat yang ditulis lengkap dan benar; keterlambatan karena alamat tidak jelas di luar tanggung jawab kami.\n\n# 6. Pengembalian\n\nPengembalian hanya untuk pesanan berstatus Selesai dan diajukan lewat menu Riwayat dengan alasan yang jelas. Bila disetujui admin, dana dikembalikan dan pesanan dibatalkan.\n\n# 7. Promo\n\nKode promo hanya berlaku selama masa aktifnya dan sesuai syarat minimum belanja. Satu pesanan hanya bisa memakai satu kode promo.",
        'privasi_judul' => 'Datamu, kami jaga.',
        'privasi_lead' => 'Penjelasan sederhana tentang data apa yang kami simpan dan untuk apa.',
        'privasi_isi' => "# Data yang kami simpan\n\nSaat mendaftar kami menyimpan nama, username, email, dan password yang terenkripsi (hash) — kami tidak pernah menyimpan password dalam bentuk teks biasa. Bila kamu mengisi profil, foto profilmu tersimpan di database kami. Riwayat pesanan dan pembayaranmu tersimpan sebagai catatan transaksi kedai.\n\n# Untuk apa data itu dipakai\n\nHanya untuk menjalankan layanan: memproses pesanan, memverifikasi pembayaran, menampilkan riwayat belanjamu, dan menghubungi kamu bila ada hal penting soal pesananmu. Kami tidak menjual data pelanggan ke pihak mana pun.\n\n# Testimoni dan pesan\n\nTestimoni yang kamu kirim tampil dengan nama depanmu setelah disetujui admin. Pesan dari formulir kontak hanya dibaca oleh admin kedai.\n\n# Hakmu\n\nKamu bisa mengubah nama, username, email, password, dan foto kapan saja lewat Edit Profil, termasuk menghapus foto profil. Untuk penghapusan akun dan seluruh datanya, hubungi admin lewat halaman Kontak Kami.\n\n# Keamanan\n\nSitus ini berjalan dengan sesi login yang aman dan pemeriksaan CSRF pada setiap pengiriman data. Meski begitu, jagalah password-mu dan jangan membagikannya ke siapa pun — admin Mampir Kopi tidak pernah meminta password pelanggan.",
    ];
    $ins = $pdo->prepare('INSERT IGNORE INTO pengaturan (kunci, nilai) VALUES (?, ?)');
    foreach ($seed as $k => $v) $ins->execute([$k, $v]);
    $sudahSeed = $pdo->query("SELECT COUNT(*) FROM pengaturan WHERE kunci = 'konten_seed_v1'")->fetchColumn();
    if (!$sudahSeed) {
        $faq = [
            ['Bagaimana cara memesan di Mampir Kopi?', 'Masuk atau daftar dulu, lalu pilih menu dan atur varian (ukuran, gula, es) sebelum masuk keranjang. Di halaman Pembayaran, pilih mau ambil di kasir atau diantar, pilih metode pembayaran, lalu klik Bayar Sekarang. Pesananmu langsung muncul di Riwayat.'],
            ['Metode pembayaran apa saja yang diterima?', 'QRIS, GoPay, OVO, DANA, dan ShopeePay. QRIS bisa dipindai dari aplikasi pembayaran mana saja. Kalau pesananmu diantar, bisa juga bayar tunai di tempat (COD).'],
            ['Apa beda Ambil di Kasir dan Diantar?', 'Ambil di Kasir gratis, kamu ambil sendiri pesananmu di kedai. Diantar dikenakan ongkir tetap Rp5.000, dan pesanan kami antar ke alamat yang kamu tulis saat pembayaran.'],
            ['Berapa lama saya harus membayar?', 'Untuk QRIS dan e-wallet, bayar dalam 5 menit sejak pesanan dibuat. Lewat dari itu pesanan batal otomatis, supaya stok tidak tertahan. Pesanan COD tidak kena batas ini.'],
            ['Bagaimana cara memakai voucher promo?', 'Di halaman Pembayaran ada kolom kode promo. Masukkan kode dari halaman Promo, diskonnya langsung memotong total belanja, asal syarat minimum belanjanya terpenuhi.'],
            ['Bisakah pesanan dibatalkan atau dikembalikan?', 'Pesanan yang belum dibayar cukup dibiarkan saja, nanti batal otomatis. Kalau pesananmu sudah Selesai tapi ada masalah, ajukan Pengembalian dari detail pesanan di Riwayat. Admin akan meninjau, dan kalau disetujui, dana dan stok dikembalikan.'],
            ['Bagaimana cara melacak pesanan saya?', 'Buka halaman Lacak Pesanan, masukkan kode pesanan (contoh: MK-0001) dan email yang kamu pakai saat mendaftar. Statusnya langsung tampil, tanpa perlu masuk akun.'],
        ];
        $st = $pdo->prepare('INSERT INTO faq (pertanyaan, jawaban, urutan, aktif) VALUES (?, ?, ?, 1)');
        foreach ($faq as $i => $f) $st->execute([$f[0], $f[1], $i + 1]);
        // Galeri: urutan dan caption sama persis seperti tamu.html. Item 1
        // (foto landscape) memakai slot lebar. Baris seed menunjuk berkas
        // img/galeri/ saja — foto menu TIDAK dipakai di galeri (Amar,
        // 22 Nov 14:49 WIB); upload baru masuk uploads/galeri/. Foto 01
        // tidak di-seed: itu foto intro, bukan anggota galeri.
        $gal = [
            ['img/galeri/02-minuman-dua-gelas-oranye.png', 'Kopi Susu Mampir', 1],
            ['img/galeri/03-minuman-butterfly-ungu.jpg', 'Butterfly Lemon', 0],
            ['img/galeri/04-eksterior-malam-seberang.jpg', 'Kedai di malam hari', 0],
            ['img/galeri/05-minuman-lapis-kuning-ungu.jpg', 'Minuman berlapis', 0],
            ['img/galeri/06-minuman-biru-gradasi.jpg', 'Minuman biru', 0],
            ['img/galeri/07-suasana-tanaman-malam.jpg', 'Tanaman di teras', 0],
            ['img/galeri/11-minuman-kantong-takeaway.jpg', 'Pesanan dibungkus', 0],
            ['img/galeri/09-minuman-kopi-susu-meja.jpg', 'Kopi susu', 0],
            ['img/galeri/12-minuman-dua-gelas-kounter.jpg', 'Disiapkan di konter', 0],
            ['img/galeri/13-minuman-gelas-slogan.jpg', 'Life happens', 0],
        ];
        $st = $pdo->prepare('INSERT INTO galeri (gambar, judul, urutan, aktif, lebar) VALUES (?, ?, ?, 1, ?)');
        foreach ($gal as $i => $g) $st->execute([$g[0], $g[1], $i + 1, $g[2]]);
        $pdo->exec("INSERT IGNORE INTO pengaturan (kunci, nilai) VALUES ('konten_seed_v1', '1')");
    }
    // Galeri 100% foto Google Maps (Amar, 22 Nov 14:49 WIB — "jangan foto
    // aset dari menu web"): ganti 3 foto menu yang sempat jadi anggota
    // galeri dengan foto Maps 11-13. Hanya baris yang gambarnya masih
    // persis foto menu lama yang diubah (edit admin tidak tertimpa);
    // idempotent — di DB baru (seed sudah benar) tidak mengubah apa pun.
    $gantiGaleri = [
        ['uploads/menu/menu-1791018092-8bdca4.jpg', 'img/galeri/11-minuman-kantong-takeaway.jpg', 'Pesanan dibungkus'],
        ['uploads/menu/menu-1791018158-15274b.jpg', 'img/galeri/12-minuman-dua-gelas-kounter.jpg', 'Disiapkan di konter'],
        ['uploads/menu/menu-1791018397-442085.jpg', 'img/galeri/13-minuman-gelas-slogan.jpg', 'Life happens'],
    ];
    $stG = $pdo->prepare('UPDATE galeri SET gambar = ?, judul = ? WHERE gambar = ?');
    foreach ($gantiGaleri as $g) $stG->execute([$g[1], $g[2], $g[0]]);
}

function ambil_pengaturan(PDO $pdo): array {
    pastikan_tabel_fitur($pdo);
    $rows = $pdo->query('SELECT kunci, nilai FROM pengaturan')->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[$r['kunci']] = $r['nilai'];
    return $out;
}

/* Hitung diskon promo. Mengembalikan [diskon, barisPromo]. Melempar ApiError jika tidak valid. */
function hitung_promo(PDO $pdo, string $kode, int $subtotal): array {
    $st = $pdo->prepare('SELECT * FROM promo WHERE kode = ?');
    $st->execute([strtoupper(trim($kode))]);
    $p = $st->fetch();
    if (!$p) fail('Kode voucher tidak ditemukan.');
    if (!$p['aktif'] || ($p['berlaku_sampai'] && $p['berlaku_sampai'] < date('Y-m-d'))) fail('Voucher sudah kadaluarsa.');
    if ($subtotal < (int)$p['min_transaksi']) fail('Minimal transaksi Rp ' . number_format($p['min_transaksi'], 0, ',', '.') . ' untuk voucher ini.');
    $d = $p['tipe'] === 'persen' ? (int) floor($subtotal * $p['nilai'] / 100) : (int)$p['nilai'];
    return [min($d, $subtotal), $p];
}

/* Data laporan transaksi (dipakai tampilan admin & ekspor Excel) */
function laporan_rows(PDO $pdo, string $dari, string $sampai, string $status): array {
    $w = ['DATE(p.tanggal_pesanan) BETWEEN ? AND ?']; $a = [$dari, $sampai];
    if ($status !== '' && $status !== 'Semua') { $w[] = 'p.status = ?'; $a[] = $status; }
    $sql = "SELECT p.id_pesanan, p.kode_pesanan, p.tanggal_pesanan, p.subtotal, p.diskon, p.total_harga, p.status,
                   pl.nama_pelanggan,
                   (SELECT GROUP_CONCAT(CONCAT(d.jumlah,'x ',d.nama_menu) SEPARATOR ', ') FROM detail_pesanan d WHERE d.id_pesanan = p.id_pesanan) AS item,
                   (SELECT b.metode FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan ORDER BY b.id_pembayaran LIMIT 1) AS metode,
                   (SELECT b.kode_transaksi FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan ORDER BY b.id_pembayaran LIMIT 1) AS kode_transaksi,
                   EXISTS (SELECT 1 FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan AND b.status = 'Terverifikasi') AS bayar_terverifikasi
            FROM pesanan p JOIN pelanggan pl ON pl.id_pelanggan = p.id_pelanggan
            WHERE " . implode(' AND ', $w) . ' ORDER BY p.tanggal_pesanan DESC';
    $st = $pdo->prepare($sql); $st->execute($a);
    return $st->fetchAll();
}

/* ---------- Sesi login ---------- */
session_set_cookie_params([
    'lifetime' => 0,
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

/* Token CSRF: wajib dikirim lewat header X-CSRF-Token pada request POST yang mengubah data. */
function csrf_token(): string { return (string)($_SESSION['csrf'] ?? ''); }
function check_csrf(): void {
    $sent = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) fail('Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.');
}

function need_login(string $msg): void {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}
function pelanggan_id(): int {
    if (($_SESSION['role'] ?? '') !== 'user') need_login('Silakan login dulu.');
    $id = (int)$_SESSION['id'];
    // L-m6: sesi tidak lagi berlaku bila akun sudah dihapus admin, atau bila
    // nomor versi sesi tidak cocok (password direset/diganti, status diubah).
    // Bila DB sedang tidak bisa dijangkau, biarkan lolos — pemanggilan DB
    // berikutnya akan gagal dengan pesan yang semestinya.
    try {
        $pdo = db();
        pastikan_sesi_versi($pdo);
        $st = $pdo->prepare('SELECT sesi_versi FROM pelanggan WHERE id_pelanggan = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            $_SESSION = []; session_destroy();
            need_login('Akun tidak ditemukan. Silakan login lagi.');
        }
        if (isset($_SESSION['sesi_versi']) && (int)$_SESSION['sesi_versi'] !== (int)$row['sesi_versi']) {
            $_SESSION = []; session_destroy();
            need_login('Sesi sudah tidak berlaku. Silakan login lagi.');
        }
    } catch (PDOException $e) { /* DB down: ditangani pemanggil berikutnya */ }
    return $id;
}
function wajib_admin(): void {
    if (($_SESSION['role'] ?? '') !== 'admin') need_login('Khusus admin. Silakan login dulu.');
    // L-m6: sesi admin mati bila akunnya sudah dihapus/dinonaktifkan.
    // L2-m9: sesi admin juga mati bila nomor versi sesi tidak cocok
    // (password diganti lewat admin_save) — pola yang sama seperti
    // pelanggan_id(). Sesi yang dibuat sebelum kolom ini ada tidak membawa
    // versi dan tetap lolos cek versi.
    try {
        $pdo = db();
        pastikan_sesi_versi_admin($pdo);
        $st = $pdo->prepare("SELECT sesi_versi FROM admin WHERE id_admin = ? AND status = 'Aktif'");
        $st->execute([(int)$_SESSION['id']]);
        $row = $st->fetch();
        if (!$row) {
            $_SESSION = []; session_destroy();
            need_login('Akun admin tidak aktif. Silakan login lagi.');
        }
        if (isset($_SESSION['sesi_versi']) && (int)$_SESSION['sesi_versi'] !== (int)$row['sesi_versi']) {
            $_SESSION = []; session_destroy();
            need_login('Sesi sudah tidak berlaku. Silakan login lagi.');
        }
    } catch (PDOException $e) { /* DB down: ditangani pemanggil berikutnya */ }
}

