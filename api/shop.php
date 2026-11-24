<?php
/* API sisi pelanggan: menu, voucher, pesanan, riwayat, statistik. */
require __DIR__ . '/config.php';
json_start();
$pdo = db();
pastikan_tabel_fitur($pdo);
$r = $_GET['r'] ?? '';

switch ($r) {

case 'menu': // seluruh katalog dikelompokkan per kategori
    $kat = $pdo->query('SELECT id_kategori AS id, nama_kategori AS nama, slug, ikon FROM kategori ORDER BY id_kategori')->fetchAll();
    $menu = $pdo->query('SELECT id_menu AS id, id_kategori, nama_menu AS nama, slug, deskripsi, harga, harga_regular, tipe, gambar, stok
                         FROM menu WHERE aktif = 1 ORDER BY id_menu')->fetchAll();
    foreach ($kat as &$k) {
        $k['menu'] = array_values(array_filter($menu, fn($m) => $m['id_kategori'] == $k['id']));
    }
    ok(array_values(array_filter($kat, fn($k) => count($k['menu']) > 0)));

case 'item':
    $st = $pdo->prepare('SELECT id_menu AS id, nama_menu AS nama, slug, deskripsi, harga, harga_regular, tipe, gambar, stok
                         FROM menu WHERE slug = ? AND aktif = 1');
    $st->execute([$_GET['slug'] ?? '']);
    $m = $st->fetch();
    if (!$m) fail('Menu tidak ditemukan.');
    ok($m);

case 'banner': // daftar promo aktif untuk banner bergulir (publik, tanpa login)
    $st = $pdo->prepare('SELECT kode, judul, tipe, nilai, min_transaksi, berlaku_sampai FROM promo WHERE aktif = 1 AND (berlaku_sampai IS NULL OR berlaku_sampai >= ?) ORDER BY id_promo DESC');
    $st->execute([date('Y-m-d')]);
    ok($st->fetchAll());

case 'konten': // seluruh konten CMS untuk halaman publik (tanpa login).
    // Teks dari pengaturan, FAQ + galeri aktif, dan strip andalan yang
    // di-resolve dari tabel menu supaya nama/foto/harga selalu live.
    $s = ambil_pengaturan($pdo);
    $teks = [];
    foreach (konten_kunci() as $k => $maks) $teks[$k] = $s[$k] ?? '';
    $faq = $pdo->query('SELECT id_faq AS id, pertanyaan, jawaban FROM faq WHERE aktif = 1 ORDER BY urutan, id_faq')->fetchAll();
    $galeri = $pdo->query('SELECT id_galeri AS id, gambar, judul, lebar FROM galeri WHERE aktif = 1 ORDER BY urutan, id_galeri')->fetchAll();
    $ids = json_decode($s['andalan_ids'] ?? '[]', true);
    if (!is_array($ids)) $ids = [];
    $andalan = [];
    $st = $pdo->prepare('SELECT id_menu AS id, nama_menu AS nama, gambar, harga FROM menu WHERE id_menu = ? AND aktif = 1');
    foreach (array_slice(array_values(array_unique(array_map('intval', $ids))), 0, 4) as $id) {
        $st->execute([$id]);
        $m = $st->fetch();
        if (!$m) continue;
        // Nama tampil: item "Manual Brew" ditampilkan sebagai V60 (pola
        // yang sama seperti namaTampil() di shop.js/guest.js).
        if ($m['nama'] === 'Manual Brew') $m['nama'] = 'V60';
        $m['harga'] = (int)$m['harga'];
        $andalan[] = $m;
    }
    ok(['teks' => $teks, 'faq' => $faq, 'galeri' => $galeri, 'andalan' => $andalan]);

case 'promo': // cek voucher untuk subtotal tertentu
    [$diskon, $p] = hitung_promo($pdo, $_GET['kode'] ?? '', (int)($_GET['subtotal'] ?? 0));
    ok(['kode' => $p['kode'], 'judul' => $p['judul'], 'diskon' => $diskon]);

case 'order': // buat pesanan + pembayaran
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Metode tidak valid.');
    check_csrf();
    sapu_pesanan_kadaluarsa($pdo);
    $in = body();
    $items = $in['items'] ?? [];
    if (!is_array($items) || !$items || count($items) > 50) fail('Keranjang kosong.');
    $metode = $in['metode'] ?? 'QRIS';
    if (!in_array($metode, ['QRIS', 'GoPay', 'OVO', 'DANA', 'ShopeePay', 'COD'], true)) fail('Metode pembayaran tidak valid.');
    $caraAmbil = (($in['cara_ambil'] ?? 'Ambil di Kasir') === 'Diantar') ? 'Diantar' : 'Ambil di Kasir';
    $alamatAntar = null; $ongkir = 0;
    if ($caraAmbil === 'Diantar') {
        $alamatAntar = mb_substr(trim((string)($in['alamat_antar'] ?? '')), 0, 500);
        if (mb_strlen($alamatAntar) < 10) fail('Alamat pengantaran belum lengkap (minimal 10 karakter).');
        $ongkir = ongkir_flat($pdo); // Rubrik #22: dari pengaturan situs, bukan konstanta
    }
    if ($metode === 'COD' && $caraAmbil !== 'Diantar') fail('COD hanya untuk pesanan yang diantar.');

    // L-m7: idempotensi order — kunci dari klien (opsional); bila kosong,
    // server membuat satu. Retry dengan kunci sama mengembalikan pesanan lama.
    $idemKey = mb_substr(trim((string)($in['idempotency_key'] ?? '')), 0, 64);
    if ($idemKey === '') $idemKey = bin2hex(random_bytes(16));
    $pid = pelanggan_id();
    $cariIdem = function () use ($pdo, $pid, $idemKey) {
        $st = $pdo->prepare('SELECT p.id_pesanan AS id, p.kode_pesanan AS kode, p.subtotal, p.diskon, p.ongkir, p.cara_ambil, p.total_harga AS total,
            (SELECT b.kode_transaksi FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan ORDER BY b.id_pembayaran LIMIT 1) AS kode_transaksi
            FROM pesanan p WHERE p.id_pelanggan = ? AND p.idempotency_key = ?');
        $st->execute([$pid, $idemKey]);
        return $st->fetch();
    };
    $jawabIdem = function (array $lama) use ($idemKey): void {
        ok(['id' => (int)$lama['id'], 'kode' => $lama['kode'], 'kode_transaksi' => $lama['kode_transaksi'],
            'subtotal' => (int)$lama['subtotal'], 'diskon' => (int)$lama['diskon'], 'ongkir' => (int)$lama['ongkir'],
            'cara_ambil' => $lama['cara_ambil'], 'total' => (int)$lama['total'], 'idempotency_key' => $idemKey]);
    };
    if ($lama = $cariIdem()) $jawabIdem($lama);

    $pdo->beginTransaction();
    try {
        $baris = []; $subtotal = 0;
        $get = $pdo->prepare('SELECT * FROM menu WHERE id_menu = ? AND aktif = 1 FOR UPDATE');
        $pakai = []; // total qty per menu (untuk cek stok)
        foreach ($items as $it) {
            $get->execute([(int)($it['id'] ?? 0)]);
            $m = $get->fetch();
            if (!$m) {
                // L2-m8: sebut nama itemnya supaya pengguna tahu menu mana
                // yang bermasalah (bukan pesan generik tanpa nama).
                $cariNama = $pdo->prepare('SELECT nama_menu FROM menu WHERE id_menu = ?');
                $cariNama->execute([(int)($it['id'] ?? 0)]);
                $nm = $cariNama->fetchColumn();
                fail($nm ? 'Menu "' . $nm . '" sudah tidak tersedia. Silakan hapus lalu pesan ulang.' : 'Ada menu di keranjang yang sudah tidak tersedia. Silakan hapus lalu pesan ulang.');
            }
            $qty = max(1, min(99, (int)($it['jumlah'] ?? 1)));
            $pakai[$m['id_menu']] = ($pakai[$m['id_menu']] ?? 0) + $qty;
            if ($pakai[$m['id_menu']] > $m['stok']) fail('Stok ' . $m['nama_menu'] . ' tidak mencukupi (sisa ' . $m['stok'] . ').');

            $ukuran = ($m['harga_regular'] > 0) ? (($it['ukuran'] ?? '') === 'Regular' ? 'Regular' : 'Small') : null;
            $gula = null; $es = null;
            if ($m['tipe'] !== 'makanan') {
                $gula = in_array($it['gula'] ?? '', ['Less Sugar', 'Normal', 'More Sugar'], true) ? $it['gula'] : 'Normal';
            }
            if ($m['tipe'] === 'es') {
                $es = in_array($it['es'] ?? '', ['Less Ice', 'Normal', 'More Ice'], true) ? $it['es'] : 'Normal';
            }
            $harga = ($ukuran === 'Regular') ? (int)$m['harga_regular'] : (int)$m['harga'];   // harga selalu dari database
            $catatan = mb_substr(trim((string)($it['catatan'] ?? '')), 0, 100) ?: null;
            $sub = $harga * $qty; $subtotal += $sub;
            $baris[] = [$m['id_menu'], $m['nama_menu'], $ukuran, $gula, $es, $catatan, $harga, $qty, $sub];
        }
        $diskon = 0; $kodePromo = null;
        if (!empty($in['kode_promo'])) { [$diskon, $p] = hitung_promo($pdo, $in['kode_promo'], $subtotal); $kodePromo = $p['kode']; }
        $total = $subtotal - $diskon + $ongkir;

        $pdo->prepare('INSERT INTO pesanan (kode_pesanan,id_pelanggan,tanggal_pesanan,subtotal,diskon,total_harga,kode_promo,status,cara_ambil,alamat_antar,ongkir,idempotency_key) VALUES (?,?,NOW(),?,?,?,?,"Menunggu",?,?,?,?)')
            ->execute([uniqid('T'), $pid, $subtotal, $diskon, $total, $kodePromo, $caraAmbil, $alamatAntar, $ongkir, $idemKey]);
        $id = (int)$pdo->lastInsertId();
        $kode = 'MK-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE pesanan SET kode_pesanan = ? WHERE id_pesanan = ?')->execute([$kode, $id]);

        $ins = $pdo->prepare('INSERT INTO detail_pesanan (id_pesanan,id_menu,nama_menu,ukuran,gula,es,catatan,harga_satuan,jumlah,subtotal) VALUES (?,?,?,?,?,?,?,?,?,?)');
        foreach ($baris as $b) $ins->execute(array_merge([$id], $b));
        // Stok TIDAK dipotong di sini. Stok baru terpotong saat pembayaran diverifikasi
        // (oleh admin lewat pembayaran_verify, atau nanti oleh webhook payment gateway).

        $trx = 'TRX-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
        $pdo->prepare('INSERT INTO pembayaran (kode_transaksi,id_pesanan,metode,jumlah,status,tanggal) VALUES (?,?,?,?,"Menunggu Verifikasi",NOW())')
            ->execute([$trx, $id, $metode, $total]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        // L-m7 (race): request lain dengan kunci sama menang duluan (UNIQUE
        // uq_idem) — kembalikan pesanan yang sudah ada, jangan error.
        if ($e instanceof PDOException && $e->getCode() === '23000' && stripos($e->getMessage(), 'uq_idem') !== false) {
            if ($lama = $cariIdem()) $jawabIdem($lama);
        }
        throw $e;
    }
    ok(['id' => $id, 'kode' => $kode, 'kode_transaksi' => $trx, 'subtotal' => $subtotal, 'diskon' => $diskon, 'ongkir' => $ongkir, 'cara_ambil' => $caraAmbil, 'total' => $total, 'idempotency_key' => $idemKey]);

case 'riwayat':
    sapu_pesanan_kadaluarsa($pdo);
    // L-M1: pesanan yang dikecualikan sweeper (COD / sudah Terverifikasi) tidak
    // punya batas bayar — sisa_bayar NULL (bukan 0), supaya frontend tidak
    // me-render countdown dan tidak me-reload halaman tanpa henti.
    // L-M6: sertakan status + metode pembayaran untuk tampilan riwayat.
    $st = $pdo->prepare("SELECT p.id_pesanan AS id, p.kode_pesanan AS kode, p.tanggal_pesanan AS tanggal, p.total_harga AS total, p.status,
            CASE WHEN EXISTS (SELECT 1 FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan AND (b.status = 'Terverifikasi' OR b.metode = 'COD')) THEN NULL
                 ELSE GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), p.tanggal_pesanan + INTERVAL " . BATAS_BAYAR_MENIT . " MINUTE)) END AS sisa_bayar,
            (SELECT b.status FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan ORDER BY b.id_pembayaran LIMIT 1) AS status_bayar,
            (SELECT b.metode FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan ORDER BY b.id_pembayaran LIMIT 1) AS metode,
            (SELECT GROUP_CONCAT(d.nama_menu SEPARATOR ' + ') FROM detail_pesanan d WHERE d.id_pesanan = p.id_pesanan) AS menu,
            (SELECT COALESCE(SUM(d.jumlah),0) FROM detail_pesanan d WHERE d.id_pesanan = p.id_pesanan) AS jumlah
            FROM pesanan p WHERE p.id_pelanggan = ? ORDER BY p.tanggal_pesanan DESC, p.id_pesanan DESC");
    $st->execute([pelanggan_id()]);
    ok($st->fetchAll());

case 'detail':
    $st = $pdo->prepare('SELECT id_pesanan AS id, kode_pesanan AS kode, tanggal_pesanan AS tanggal, subtotal, diskon, total_harga AS total, kode_promo, status, cara_ambil, alamat_antar, ongkir
                         FROM pesanan WHERE id_pesanan = ? AND id_pelanggan = ?');
    $st->execute([(int)($_GET['id'] ?? 0), pelanggan_id()]);
    $p = $st->fetch();
    if (!$p) fail('Pesanan tidak ditemukan.');
    // PESANAN TERAKHIR 22 Nov: id_menu disertakan supaya dashboard bisa
    // memesan ulang item yang sama (tanpa ini frontend hanya punya nama).
    $st = $pdo->prepare('SELECT d.id_menu AS id_menu, d.nama_menu AS nama, d.ukuran, d.gula, d.es, d.catatan, d.harga_satuan AS harga, d.jumlah, d.subtotal, m.gambar
                         FROM detail_pesanan d LEFT JOIN menu m ON m.id_menu = d.id_menu WHERE d.id_pesanan = ?');
    $st->execute([$p['id']]);
    $p['items'] = $st->fetchAll();
    $st = $pdo->prepare('SELECT nama_pelanggan FROM pelanggan WHERE id_pelanggan = ?');
    $st->execute([pelanggan_id()]);
    $p['pelanggan'] = $st->fetchColumn() ?: '';
    $st = $pdo->prepare('SELECT kode_transaksi, metode, status FROM pembayaran WHERE id_pesanan = ? ORDER BY id_pembayaran LIMIT 1');
    $st->execute([$p['id']]);
    $p['pembayaran'] = $st->fetch() ?: null;
    $st = $pdo->prepare('SELECT status FROM pengembalian WHERE id_pesanan = ? ORDER BY id_pengembalian DESC LIMIT 1');
    $st->execute([$p['id']]);
    $p['pengembalian'] = $st->fetchColumn() ?: null;
    // REFUND 22 Nov: detail pengajuan terbaru (jenis + jumlah) untuk modal Riwayat.
    $st = $pdo->prepare('SELECT jenis, metode_refund, tujuan_refund, jumlah_refund FROM pengembalian WHERE id_pesanan = ? ORDER BY id_pengembalian DESC LIMIT 1');
    $st->execute([$p['id']]);
    $p['pengembalian_info'] = $st->fetch() ?: null;
    ok($p);

case 'stats':
    sapu_pesanan_kadaluarsa($pdo);
    // L2-m4: jumlah/total hanya dari pesanan dengan pembayaran Terverifikasi
    // (selaras dengan definisi pendapatan L-M3) — pesanan belum bayar bukan
    // belanja. 'aktif' tetap menghitung pesanan berjalan apa pun status bayarnya.
    $adaBayar = "EXISTS (SELECT 1 FROM pembayaran b WHERE b.id_pesanan = pesanan.id_pesanan AND b.status = 'Terverifikasi')";
    $st = $pdo->prepare("SELECT COUNT(CASE WHEN $adaBayar THEN 1 END) AS jumlah, COALESCE(SUM(CASE WHEN $adaBayar THEN total_harga ELSE 0 END),0) AS total,
                                COALESCE(SUM(status IN ('Menunggu','Diproses')),0) AS aktif
                         FROM pesanan WHERE id_pelanggan = ? AND status <> 'Dibatalkan'");
    $st->execute([pelanggan_id()]);
    $s = $st->fetch();
    $n = $pdo->prepare('SELECT nama_pelanggan, username FROM pelanggan WHERE id_pelanggan = ?'); $n->execute([pelanggan_id()]);
    $ident = $n->fetch(PDO::FETCH_ASSOC) ?: [];
    $s['nama'] = $ident['nama_pelanggan'] ?? 'Pelanggan';
    $s['username'] = $ident['username'] ?? null;
    ok($s);


case 'info': // pengaturan publik untuk halaman Tentang/Kontak (tanpa login)
    $s = ambil_pengaturan($pdo);
    ok(['nama_kedai' => $s['nama_kedai'] ?? 'Mampir Kopi', 'alamat' => $s['alamat'] ?? '', 'telepon' => $s['telepon'] ?? '',
        'email' => $s['email'] ?? '', 'jam_buka' => $s['jam_buka'] ?? '', 'instagram' => $s['instagram'] ?? '',
        'ongkir' => ongkir_flat($pdo)]);

case 'kontak': // form Kontak Kami -> tersimpan sebagai pesan masuk admin
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Metode tidak valid.');
    check_csrf();
    $in = body();
    $nama = mb_substr(trim((string)($in['nama'] ?? '')), 0, 100);
    $email = mb_substr(trim((string)($in['email'] ?? '')), 0, 150);
    $pesan = mb_substr(trim((string)($in['pesan'] ?? '')), 0, 2000);
    if (mb_strlen($nama) < 2) fail('Nama belum diisi.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Format email tidak valid.');
    if (mb_strlen($pesan) < 5) fail('Pesan terlalu singkat.');
    // L-m22: maks 1 kiriman per 60 detik per sesi (anti-spam).
    if (time() - (int)($_SESSION['kontak_terakhir'] ?? 0) < 60) fail('Terlalu cepat. Tunggu sebentar sebelum mengirim pesan lagi.');
    $pdo->prepare('INSERT INTO pesan_kontak (nama, email, pesan) VALUES (?, ?, ?)')->execute([$nama, $email, $pesan]);
    $_SESSION['kontak_terakhir'] = time();
    ok(['terkirim' => true]);

case 'testimoni': // daftar testimoni aktif (publik) + foto profil pengirim
    ok($pdo->query('SELECT t.nama, t.isi, t.rating, t.dibuat, t.id_pelanggan, (p.foto IS NOT NULL) AS foto_ada, UNIX_TIMESTAMP(p.foto_diubah) AS foto_ver FROM testimoni t LEFT JOIN pelanggan p ON p.id_pelanggan = t.id_pelanggan WHERE t.aktif = 1 ORDER BY t.id_testimoni DESC LIMIT 30')->fetchAll());

case 'testimoni_kirim': // pelanggan login mengirim testimoni (langsung tampil)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Metode tidak valid.');
    check_csrf();
    $in = body();
    $isi = mb_substr(trim((string)($in['isi'] ?? '')), 0, 600);
    $rating = max(1, min(5, (int)($in['rating'] ?? 5)));
    if (mb_strlen($isi) < 10) fail('Testimoni terlalu singkat (minimal 10 karakter).');
    // L-m22: maks 1 kiriman per 60 detik per sesi (anti-spam).
    if (time() - (int)($_SESSION['testimoni_terakhir'] ?? 0) < 60) fail('Terlalu cepat. Tunggu sebentar sebelum mengirim testimoni lagi.');
    $st = $pdo->prepare('SELECT nama_pelanggan FROM pelanggan WHERE id_pelanggan = ?');
    $st->execute([pelanggan_id()]);
    $pdo->prepare('INSERT INTO testimoni (id_pelanggan, nama, isi, rating, aktif) VALUES (?, ?, ?, ?, 1)')->execute([pelanggan_id(), $st->fetchColumn() ?: 'Pelanggan', $isi, $rating]);
    $_SESSION['testimoni_terakhir'] = time();
    ok(['terkirim' => true]);

case 'blog': // daftar artikel (publik)
    ok($pdo->query('SELECT judul, slug, ringkasan, gambar, dibuat FROM artikel ORDER BY id_artikel DESC LIMIT 30')->fetchAll());

case 'artikel': // isi satu artikel (publik)
    $st = $pdo->prepare('SELECT judul, slug, ringkasan, isi, gambar, dibuat FROM artikel WHERE slug = ?');
    $st->execute([$_GET['slug'] ?? '']);
    $a = $st->fetch();
    if (!$a) fail('Artikel tidak ditemukan.');
    ok($a);

case 'lacak': // lacak pesanan publik: cocokkan kode pesanan + email pemilik
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Metode tidak valid.');
    check_csrf();
    sapu_pesanan_kadaluarsa($pdo); // L-m2: jangan tampilkan status basi
    // L-m24: maks 5 percobaan per 10 menit per sesi (kode pesanan sekuensial,
    // jadi percobaan menebak harus dibatasi).
    $skrg = time();
    $coba = array_values(array_filter((array)($_SESSION['lacak_percobaan'] ?? []), fn($t) => $t > $skrg - 600));
    if (count($coba) >= 5) fail('Terlalu banyak percobaan lacak. Coba lagi dalam 10 menit.');
    $coba[] = $skrg;
    $_SESSION['lacak_percobaan'] = $coba;
    $in = body();
    $st = $pdo->prepare('SELECT p.id_pesanan AS id, p.kode_pesanan AS kode, p.tanggal_pesanan AS tanggal, p.status, p.cara_ambil, p.total_harga AS total,
            (SELECT GROUP_CONCAT(CONCAT(d.jumlah, "x ", d.nama_menu) SEPARATOR ", ") FROM detail_pesanan d WHERE d.id_pesanan = p.id_pesanan) AS item,
            (SELECT b.status FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan ORDER BY b.id_pembayaran LIMIT 1) AS status_bayar
            FROM pesanan p JOIN pelanggan pl ON pl.id_pelanggan = p.id_pelanggan
            WHERE p.kode_pesanan = ? AND pl.email = ?');
    $st->execute([strtoupper(trim((string)($in['kode'] ?? ''))), trim((string)($in['email'] ?? ''))]);
    $row = $st->fetch();
    if (!$row) fail('Pesanan tidak ditemukan. Periksa lagi kode pesanan dan email yang dipakai saat mendaftar.');
    // KARTU ITEM LACAK (22 Nov 16:53, Amar): kembalikan rincian item
    // (dengan foto) supaya hasil lacak menampilkan kartu seperti di
    // keranjang, bukan hanya teks nama menu.
    $st = $pdo->prepare('SELECT d.id_menu AS id_menu, d.nama_menu AS nama, d.ukuran, d.gula, d.es, d.catatan, d.harga_satuan AS harga, d.jumlah, d.subtotal, m.gambar
                         FROM detail_pesanan d LEFT JOIN menu m ON m.id_menu = d.id_menu WHERE d.id_pesanan = ?');
    $st->execute([$row['id']]);
    $row['items'] = $st->fetchAll();
    unset($row['id']); // id internal tidak perlu terekspos ke publik
    ok($row);

case 'kunjung': // penghitung kunjungan harian untuk Analitik admin (sekali per sesi per hari)
    $hari = date('Y-m-d');
    if (($_SESSION['kunjung'] ?? '') !== $hari) {
        $_SESSION['kunjung'] = $hari;
        $pdo->prepare('INSERT INTO kunjungan (tanggal, jumlah) VALUES (?, 1) ON DUPLICATE KEY UPDATE jumlah = jumlah + 1')->execute([$hari]);
    }
    ok(['ok' => true]);

case 'wishlist': // daftar produk simpanan pelanggan
    // deskripsi + tipe + harga_regular: kartu wishlist sama persis dengan
    // kartu menu (22 Nov 16:53) — deskripsi ditampilkan, tipe/harga_regular
    // dipakai tombol + (tambah ke keranjang dengan pilihan standar).
    $st = $pdo->prepare('SELECT m.id_menu AS id, m.nama_menu AS nama, m.slug, m.harga, m.harga_regular, m.tipe, m.deskripsi, m.gambar, m.stok FROM wishlist w JOIN menu m ON m.id_menu = w.id_menu WHERE w.id_pelanggan = ? AND m.aktif = 1 ORDER BY w.id_wishlist DESC');
    $st->execute([pelanggan_id()]);
    ok($st->fetchAll());

case 'wishlist_toggle': // simpan / hapus dari wishlist
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Metode tidak valid.');
    check_csrf();
    $in = body();
    $idMenu = (int)($in['id_menu'] ?? 0);
    $pid = pelanggan_id();
    $st = $pdo->prepare('SELECT id_wishlist FROM wishlist WHERE id_pelanggan = ? AND id_menu = ?');
    $st->execute([$pid, $idMenu]);
    if ($st->fetch()) { $pdo->prepare('DELETE FROM wishlist WHERE id_pelanggan = ? AND id_menu = ?')->execute([$pid, $idMenu]); $ada = false; }
    else {
        $cek = $pdo->prepare('SELECT COUNT(*) FROM menu WHERE id_menu = ? AND aktif = 1'); $cek->execute([$idMenu]);
        if (!$cek->fetchColumn()) fail('Menu tidak ditemukan.');
        $pdo->prepare('INSERT INTO wishlist (id_pelanggan, id_menu) VALUES (?, ?)')->execute([$pid, $idMenu]); $ada = true;
    }
    $st = $pdo->prepare('SELECT id_menu FROM wishlist WHERE id_pelanggan = ?'); $st->execute([$pid]);
    ok(['ada' => $ada, 'ids' => array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN))]);

case 'pengembalian': // REFUND 22 Nov: ajukan refund dana atau pengembalian barang
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Metode tidak valid.');
    check_csrf();
    $in = body();
    $idP = (int)($in['id_pesanan'] ?? 0);
    // Backward-compatible: panggilan lama tanpa jenis = 'Pengembalian'.
    $jenis = ($in['jenis'] ?? 'Pengembalian') === 'Refund' ? 'Refund' : 'Pengembalian';
    $alasan = mb_substr(trim((string)($in['alasan'] ?? '')), 0, 500);
    if (mb_strlen($alasan) < 5) fail('Alasan pengembalian belum diisi.');
    $pid = pelanggan_id();
    $st = $pdo->prepare('SELECT status, total_harga FROM pesanan WHERE id_pesanan = ? AND id_pelanggan = ?');
    $st->execute([$idP, $pid]);
    $pes = $st->fetch();
    if (!$pes) fail('Pesanan tidak ditemukan.');
    $metodeRefund = null; $tujuanRefund = null; $jumlahRefund = null;
    if ($jenis === 'Refund') {
        // Refund = uang kembali: hanya bila ada dana yang memang sudah dibayar
        // (pembayaran Terverifikasi). COD yang belum dibayar tidak punya dana
        // untuk dikembalikan; pesanan Dibatalkan tidak bisa diajukan lagi.
        $st = $pdo->prepare('SELECT status, metode FROM pembayaran WHERE id_pesanan = ? ORDER BY id_pembayaran LIMIT 1');
        $st->execute([$idP]);
        $bayar = $st->fetch();
        if (!$bayar || $bayar['status'] !== 'Terverifikasi')
            fail($bayar && $bayar['metode'] === 'COD' ? 'Pesanan COD ini belum dibayar, jadi tidak ada dana yang bisa dikembalikan.' : 'Refund hanya untuk pesanan yang sudah dibayar dan terverifikasi.');
        if (!in_array($pes['status'], ['Menunggu', 'Diproses', 'Selesai'], true)) fail('Pesanan ini tidak bisa diajukan refund.');
        $metodeRefund = trim((string)($in['metode_refund'] ?? ''));
        if (!in_array($metodeRefund, ['Transfer Bank', 'GoPay', 'OVO', 'DANA', 'ShopeePay'], true)) fail('Pilih metode refund yang valid.');
        $tujuanRefund = mb_substr(trim((string)($in['tujuan_refund'] ?? '')), 0, 100);
        if (mb_strlen($tujuanRefund) < 5) fail('Isi nomor rekening atau nomor e-wallet tujuan refund.');
        $jumlahRefund = (int)$pes['total_harga']; // dihitung server, bukan input user
    } else {
        if ($pes['status'] !== 'Selesai') fail('Pengembalian hanya untuk pesanan berstatus Selesai.');
    }
    $st = $pdo->prepare("SELECT COUNT(*) FROM pengembalian WHERE id_pesanan = ? AND status IN ('Menunggu','Disetujui')");
    $st->execute([$idP]);
    if ($st->fetchColumn()) fail('Pengembalian untuk pesanan ini sudah pernah diajukan.');
    $pdo->prepare('INSERT INTO pengembalian (id_pesanan, id_pelanggan, alasan, jenis, metode_refund, tujuan_refund, jumlah_refund) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$idP, $pid, $alasan, $jenis, $metodeRefund, $tujuanRefund, $jumlahRefund]);
    ok(['terkirim' => true]);

default: fail('Endpoint tidak dikenal.');
}
