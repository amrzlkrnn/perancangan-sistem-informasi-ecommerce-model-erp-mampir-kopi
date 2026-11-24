<?php
/* API panel admin. Hanya bisa diakses admin yang sudah login (lihat api/auth.php). */
require __DIR__ . '/config.php';
wajib_admin();
json_start();
$pdo = db();
pastikan_tabel_fitur($pdo);
$r = $_GET['r'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') check_csrf();
$in = ($_SERVER['REQUEST_METHOD'] === 'POST') ? body() : [];

function q(PDO $pdo, string $sql, array $a = []): PDOStatement { $s = $pdo->prepare($sql); $s->execute($a); return $s; }
/* L-m14: tanggal harus benar-benar kalender yang valid dalam format Y-m-d
   (createFromFormat saja meloloskan 2026-02-31 dengan menggesernya ke Maret). */
function tanggal_ymd(string $t): bool {
    $d = DateTime::createFromFormat('Y-m-d', $t);
    return $d !== false && $d->format('Y-m-d') === $t;
}
function req(array $in, string $k, string $label): string {
    $v = trim((string)($in[$k] ?? ''));
    if ($v === '') fail("$label wajib diisi.");
    return $v;
}

/* L3-m2: validasi panjang maksimum & rentang INT sebelum INSERT/UPDATE.
   Tanpa ini, input yang melebihi kolom meledak jadi HTTP 500 generik yang
   menuduh database belum diimpor — padahal masalahnya hanya inputnya. */
function panjang_maks(string $v, int $maks, string $label): void {
    if (mb_strlen($v) > $maks) fail("$label maksimal $maks karakter.");
}
function batas_int(int $v, string $label): void {
    if ($v > 2147483647) fail("$label terlalu besar (maksimal 2.147.483.647).");
}

/* Simpan upload gambar (JPG/PNG/WEBP maks 2 MB). $subdir = folder di bawah
   uploads/ ('menu' atau 'kategori'); $prefix = awalan nama berkas. Bila tidak
   ada berkas baru, kembalikan path lama. Berkas lama di uploads/ dihapus. */
function simpan_gambar(?string $lama, string $subdir = 'menu', string $prefix = 'menu-'): ?string {
    if (empty($_FILES['gambar']['name']) || $_FILES['gambar']['error'] === UPLOAD_ERR_NO_FILE) return $lama;
    $f = $_FILES['gambar'];
    if ($f['error'] !== UPLOAD_ERR_OK) fail('Upload foto gagal (kode ' . $f['error'] . '). Ukuran maksimal 2 MB.');
    if ($f['size'] > 2 * 1024 * 1024) fail('Ukuran foto maksimal 2 MB.');
    $info = @getimagesize($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
    if (!$ext) fail('Format foto harus JPG, PNG, atau WEBP.');
    $dir = dirname(__DIR__) . '/uploads/' . $subdir;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $nama = $prefix . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$nama")) fail('Foto tidak bisa disimpan. Pastikan folder uploads/ dapat ditulis.');
    if ($lama && str_starts_with($lama, 'uploads/') && is_file(dirname(__DIR__) . '/' . $lama)) @unlink(dirname(__DIR__) . '/' . $lama);
    return 'uploads/' . $subdir . '/' . $nama;
}

switch ($r) {

/* ---------- DASHBOARD ---------- */
case 'dashboard':
    sapu_pesanan_kadaluarsa($pdo);
    $s = q($pdo, "SELECT COUNT(*) FROM pesanan")->fetchColumn();
    $hari = q($pdo, "SELECT COUNT(*) FROM pesanan WHERE DATE(tanggal_pesanan)=CURDATE()")->fetchColumn();
    // L-M3: "pendapatan" dihitung HANYA dari pesanan dengan pembayaran
    // Terverifikasi (selaras dengan Laporan Keuangan) — bukan sekadar
    // status='Selesai', yang bisa diset tanpa pembayaran.
    $adaBayar = "EXISTS (SELECT 1 FROM pembayaran b WHERE b.id_pesanan = pesanan.id_pesanan AND b.status = 'Terverifikasi')";
    $bulan = q($pdo, "SELECT COALESCE(SUM(total_harga),0) FROM pesanan WHERE status='Selesai' AND $adaBayar AND tanggal_pesanan >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
    $rpHari = q($pdo, "SELECT COALESCE(SUM(total_harga),0) FROM pesanan WHERE status='Selesai' AND $adaBayar AND DATE(tanggal_pesanan)=CURDATE()")->fetchColumn();
    $rpKemarin = q($pdo, "SELECT COALESCE(SUM(total_harga),0) FROM pesanan WHERE status='Selesai' AND $adaBayar AND DATE(tanggal_pesanan)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
    $tunggu = q($pdo, "SELECT COUNT(*) FROM pesanan WHERE status='Menunggu'")->fetchColumn();
    $tungguBayar = q($pdo, "SELECT COUNT(*) FROM pembayaran WHERE status='Menunggu Verifikasi'")->fetchColumn();
    $plg = q($pdo, "SELECT COUNT(*) FROM pelanggan")->fetchColumn();
    $menu = q($pdo, "SELECT COUNT(*) FROM menu")->fetchColumn();
    $kat = q($pdo, "SELECT COUNT(*) FROM kategori")->fetchColumn();
    $terbaru = q($pdo, "SELECT p.id_pesanan AS pid, p.kode_pesanan AS id, pl.nama_pelanggan AS customer, p.status, p.total_harga AS total, p.tanggal_pesanan AS waktu,
        (SELECT GROUP_CONCAT(CONCAT(d.jumlah,'x ',d.nama_menu) SEPARATOR ' + ') FROM detail_pesanan d WHERE d.id_pesanan=p.id_pesanan) AS items,
        (SELECT b.status FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan ORDER BY b.id_pembayaran LIMIT 1) AS status_bayar,
        (SELECT b.metode FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan ORDER BY b.id_pembayaran LIMIT 1) AS metode
        FROM pesanan p JOIN pelanggan pl ON pl.id_pelanggan=p.id_pelanggan ORDER BY p.tanggal_pesanan DESC, p.id_pesanan DESC LIMIT 5")->fetchAll();
    $per = q($pdo, "SELECT DATE(tanggal_pesanan) d, SUM(total_harga) t FROM pesanan WHERE status='Selesai' AND $adaBayar AND tanggal_pesanan >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(tanggal_pesanan)")->fetchAll(PDO::FETCH_KEY_PAIR);
    $hariNama = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']; $minggu = []; $grafikBulan = [];
    for ($i = 29; $i >= 0; $i--) {
        $t = strtotime("-$i day"); $tgl = date('Y-m-d', $t); $tot = (int)($per[$tgl] ?? 0);
        $grafikBulan[] = ['label' => date('j', $t), 'tanggal' => $tgl, 'total' => $tot];
        if ($i <= 6) $minggu[] = ['label' => $hariNama[date('w', $t)], 'tanggal' => $tgl, 'total' => $tot];
    }
    $terlaris = q($pdo, "SELECT m.nama_menu AS nama, SUM(d.jumlah) AS terjual FROM detail_pesanan d
        JOIN pesanan p ON p.id_pesanan=d.id_pesanan JOIN menu m ON m.id_menu=d.id_menu
        WHERE p.status='Selesai' AND EXISTS (SELECT 1 FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan AND b.status = 'Terverifikasi')
        GROUP BY m.id_menu, m.nama_menu ORDER BY terjual DESC, m.nama_menu LIMIT 5")->fetchAll();
    $stokMenipis = q($pdo, "SELECT nama_menu AS nama, stok FROM menu WHERE aktif=1 AND stok<=5 ORDER BY stok ASC, nama_menu LIMIT 6")->fetchAll();
    ok(['pesanan' => (int)$s, 'pesanan_hari_ini' => (int)$hari, 'pendapatan_30hari' => (int)$bulan,
        'pendapatan_hari_ini' => (int)$rpHari, 'pendapatan_kemarin' => (int)$rpKemarin,
        'menunggu_pesanan' => (int)$tunggu, 'menunggu_bayar' => (int)$tungguBayar, 'pelanggan' => (int)$plg,
        'menu' => (int)$menu, 'kategori' => (int)$kat, 'terbaru' => $terbaru, 'minggu' => $minggu, 'bulan' => $grafikBulan, 'terlaris' => $terlaris, 'stok_menipis' => $stokMenipis]);

/* ---------- MENU ---------- */
case 'menu':
    ok(q($pdo, "SELECT m.id_menu AS id, m.id_kategori, k.nama_kategori AS kategori, m.nama_menu AS nama, m.deskripsi, m.harga, m.harga_regular, m.tipe, m.gambar, m.stok, m.aktif
                FROM menu m JOIN kategori k ON k.id_kategori=m.id_kategori ORDER BY m.id_menu DESC")->fetchAll());

case 'menu_save':
    $id = (int)($in['id'] ?? 0);
    $nama = req($in, 'nama', 'Nama menu');
    panjang_maks($nama, 80, 'Nama menu'); // L3-m2: menu.nama_menu varchar(80)
    $kat = (int)($in['id_kategori'] ?? 0);
    if (!q($pdo, 'SELECT 1 FROM kategori WHERE id_kategori=?', [$kat])->fetch()) fail('Pilih kategori yang valid.');
    $harga = (int)($in['harga'] ?? 0); if ($harga <= 0) fail('Harga harus lebih dari 0.');
    batas_int($harga, 'Harga'); // L3-m2: kolom INT
    $reg = max(0, (int)($in['harga_regular'] ?? 0));
    batas_int($reg, 'Harga Regular'); // L3-m2
    $tipe = in_array($in['tipe'] ?? '', ['es', 'panas', 'makanan'], true) ? $in['tipe'] : 'es';
    if ($tipe === 'makanan') $reg = 0;
    // L3-m4: harga Regular tidak boleh lebih murah dari Small — salah ketik
    // admin (tertukar) langsung merugikan kedai di setiap pesanan Regular.
    if ($reg > 0 && $reg < $harga) fail('Harga Regular tidak boleh lebih murah dari harga Small.');
    $stok = max(0, (int)($in['stok'] ?? 0));
    batas_int($stok, 'Stok'); // L3-m2: kolom INT
    $aktif = !empty($in['aktif']) && $in['aktif'] !== '0' ? 1 : 0;
    $desk = trim((string)($in['deskripsi'] ?? ''));
    if ($id) {
        $lama = q($pdo, 'SELECT gambar FROM menu WHERE id_menu=?', [$id])->fetchColumn();
        if ($lama === false) fail('Menu tidak ditemukan.');
        $g = simpan_gambar($lama);
        q($pdo, 'UPDATE menu SET id_kategori=?,nama_menu=?,deskripsi=?,harga=?,harga_regular=?,tipe=?,gambar=?,stok=?,aktif=? WHERE id_menu=?',
          [$kat, $nama, $desk, $harga, $reg, $tipe, $g, $stok, $aktif, $id]);
    } else {
        $slug = slugify($nama); $base = $slug; $n = 2;
        while (q($pdo, 'SELECT 1 FROM menu WHERE slug=?', [$slug])->fetch()) $slug = $base . '-' . $n++;
        $g = simpan_gambar(null) ?: 'img/menu/default.svg';
        q($pdo, 'INSERT INTO menu (id_kategori,nama_menu,slug,deskripsi,harga,harga_regular,tipe,gambar,stok,aktif) VALUES (?,?,?,?,?,?,?,?,?,?)',
          [$kat, $nama, $slug, $desk, $harga, $reg, $tipe, $g, $stok, $aktif]);
    }
    ok();

case 'menu_delete':
    $id = (int)($in['id'] ?? 0);
    // L-m18: jangan hapus menu yang masih ada di pesanan berjalan — detailnya
    // jadi id_menu NULL dan verifikasi stok melewatkan item itu (total tetap).
    if (q($pdo, "SELECT 1 FROM detail_pesanan d JOIN pesanan p ON p.id_pesanan = d.id_pesanan WHERE d.id_menu = ? AND p.status IN ('Menunggu','Diproses') LIMIT 1", [$id])->fetch())
        fail('Menu ini masih ada di pesanan yang sedang berjalan (Menunggu/Diproses). Selesaikan atau batalkan pesanan itu dulu.');
    $g = q($pdo, 'SELECT gambar FROM menu WHERE id_menu=?', [$id])->fetchColumn();
    q($pdo, 'DELETE FROM menu WHERE id_menu=?', [$id]);
    if ($g && str_starts_with($g, 'uploads/') && is_file(dirname(__DIR__) . '/' . $g)) @unlink(dirname(__DIR__) . '/' . $g);
    ok();

/* ---------- KATEGORI ---------- */
case 'kategori':
    ok(q($pdo, "SELECT k.id_kategori AS id, k.nama_kategori AS nama, k.slug, k.gambar, (SELECT COUNT(*) FROM menu m WHERE m.id_kategori=k.id_kategori) AS jumlah
                FROM kategori k ORDER BY k.id_kategori")->fetchAll());

case 'kategori_save':
    $id = (int)($in['id'] ?? 0);
    $nama = req($in, 'nama', 'Nama kategori');
    panjang_maks($nama, 50, 'Nama kategori'); // L3-m2: kategori.nama_kategori varchar(50)
    $slug = slugify($nama);
    if (q($pdo, 'SELECT 1 FROM kategori WHERE slug=? AND id_kategori<>?', [$slug, $id])->fetch()) fail('Kategori dengan nama tersebut sudah ada.');
    if ($id) {
        $lama = q($pdo, 'SELECT gambar FROM kategori WHERE id_kategori=?', [$id])->fetchColumn();
        if ($lama === false) fail('Kategori tidak ditemukan.');
        $g = simpan_gambar($lama ?: null, 'kategori', 'kat-');
        q($pdo, 'UPDATE kategori SET nama_kategori=?,slug=?,gambar=? WHERE id_kategori=?', [$nama, $slug, $g, $id]);
    } else {
        $g = simpan_gambar(null, 'kategori', 'kat-');
        q($pdo, 'INSERT INTO kategori (nama_kategori,slug,gambar) VALUES (?,?,?)', [$nama, $slug, $g]);
    }
    ok();

case 'kategori_delete':
    $id = (int)($in['id'] ?? 0);
    if (q($pdo, 'SELECT COUNT(*) FROM menu WHERE id_kategori=?', [$id])->fetchColumn() > 0) fail('Kategori masih dipakai oleh menu. Pindahkan atau hapus menunya dulu.');
    $g = q($pdo, 'SELECT gambar FROM kategori WHERE id_kategori=?', [$id])->fetchColumn();
    q($pdo, 'DELETE FROM kategori WHERE id_kategori=?', [$id]);
    if ($g && str_starts_with($g, 'uploads/') && is_file(dirname(__DIR__) . '/' . $g)) @unlink(dirname(__DIR__) . '/' . $g);
    ok();

/* ---------- PESANAN ---------- */
case 'pesanan':
    sapu_pesanan_kadaluarsa($pdo);
    ok(q($pdo, "SELECT p.id_pesanan AS pid, p.kode_pesanan AS id, pl.nama_pelanggan AS customer, p.total_harga AS total, p.tanggal_pesanan AS waktu, p.status,
        (SELECT GROUP_CONCAT(CONCAT(d.jumlah,'x ',d.nama_menu) SEPARATOR ' + ') FROM detail_pesanan d WHERE d.id_pesanan=p.id_pesanan) AS items
        FROM pesanan p JOIN pelanggan pl ON pl.id_pelanggan=p.id_pelanggan ORDER BY p.tanggal_pesanan DESC, p.id_pesanan DESC")->fetchAll());

/* Rubrik #26: detail pesanan lengkap khusus admin (isi, pelanggan, alamat/
   metode antar, pembayaran, status). */
case 'pesanan_detail':
    $id = (int)($_GET['id'] ?? ($in['id'] ?? 0));
    $p = q($pdo, "SELECT p.id_pesanan AS id, p.kode_pesanan AS kode, p.tanggal_pesanan AS tanggal,
        p.subtotal, p.diskon, p.ongkir, p.total_harga AS total, p.kode_promo, p.status,
        p.cara_ambil, p.alamat_antar, pl.nama_pelanggan AS pelanggan, pl.username, pl.email
        FROM pesanan p JOIN pelanggan pl ON pl.id_pelanggan = p.id_pelanggan WHERE p.id_pesanan = ?", [$id])->fetch();
    if (!$p) fail('Pesanan tidak ditemukan.');
    $p['items'] = q($pdo, 'SELECT nama_menu AS nama, ukuran, gula, es, catatan, harga_satuan AS harga, jumlah, subtotal FROM detail_pesanan WHERE id_pesanan = ? ORDER BY id_detail', [$id])->fetchAll();
    $p['pembayaran'] = q($pdo, 'SELECT kode_transaksi, metode, jumlah, status, alasan, tanggal FROM pembayaran WHERE id_pesanan = ? ORDER BY id_pembayaran', [$id])->fetchAll();
    $p['subtotal'] = (int)$p['subtotal']; $p['diskon'] = (int)$p['diskon']; $p['ongkir'] = (int)$p['ongkir']; $p['total'] = (int)$p['total'];
    ok($p);

case 'pesanan_status':
    // Stok mengikuti status PEMBAYARAN: pesanan yang belum terverifikasi tidak pernah
    // memotong stok, jadi membatalkan/membukanya tidak mengubah stok sama sekali.
    $id = (int)($in['id'] ?? 0); $st = $in['status'] ?? '';
    if (!in_array($st, ['Menunggu', 'Diproses', 'Selesai', 'Dibatalkan'], true)) fail('Status tidak valid.');
    sapu_pesanan_kadaluarsa($pdo); // L-M4: pesanan kadaluarsa harus sudah Dibatalkan sebelum diproses
    $pdo->beginTransaction();
    try {
        $row = q($pdo, 'SELECT status, pernah_selesai FROM pesanan WHERE id_pesanan=? FOR UPDATE', [$id])->fetch();
        if ($row === false) fail('Pesanan tidak ditemukan.');
        $lama = $row['status'];
        // L2-M2/L3-M1: pesanan yang pernah Selesai tidak pernah mengembalikan
        // stok saat dibatalkan (barang sudah dikonsumsi) — jadi buka-ulangnya
        // tidak memotong stok lagi, dan batal dari Menunggu/Diproses sesudah
        // pernah Selesai juga tidak mengembalikan stok.
        $pernahSelesai = (int)($row['pernah_selesai'] ?? 0) === 1;
        $bayar = q($pdo, 'SELECT status, metode FROM pembayaran WHERE id_pesanan=? ORDER BY id_pembayaran LIMIT 1', [$id])->fetch();
        $statusBayar = $bayar['status'] ?? null;
        $metode = $bayar['metode'] ?? null;
        // L2-M2: pesanan Selesai tidak bisa diubah lagi statusnya — barangnya
        // sudah dikonsumsi. Sebelumnya Selesai -> Menunggu -> Dibatalkan
        // mengembalikan stok phantom (bypass L-M7). Pengembalian barang tetap
        // lewat alur pengembalian (pengembalian_putus), bukan dari sini.
        if ($lama === 'Selesai' && $st !== 'Selesai') fail('Pesanan yang sudah Selesai tidak bisa diubah statusnya. Gunakan alur Pengembalian bila pelanggan mengembalikan pesanan.');
        $terverifikasi = (bool) q($pdo, "SELECT 1 FROM pembayaran WHERE id_pesanan=? AND status='Terverifikasi' LIMIT 1", [$id])->fetchColumn();
        // L-M3: pesanan belum bayar tidak boleh dinyatakan Selesai (dan laporan
        // tidak lagi menghitungnya sebagai pendapatan).
        if ($st === 'Selesai' && !$terverifikasi) fail('Pesanan belum diverifikasi pembayarannya.');
        // L2-M4: Diproses juga mensyaratkan pembayaran terverifikasi — kalau
        // tidak, pesanan lolos dari sweeper 5 menit selamanya. COD dikecualikan
        // (bayar saat barang diantar) selama pembayarannya masih Menunggu
        // Verifikasi; pembayaran terminal (Ditolak/Kadaluarsa/Refund) tetap
        // tidak boleh Diproses supaya tidak buntu seperti L2-M3.
        if ($st === 'Diproses' && !$terverifikasi && !($metode === 'COD' && $statusBayar === 'Menunggu Verifikasi')) fail('Pesanan belum diverifikasi pembayarannya.');
        // L-M7: batalkan pesanan berbayar -> kembalikan stok, tapi HANYA dari
        // status Menunggu/Diproses (barang pesanan Selesai sudah dikonsumsi).
        // L2-M2: pengecualian yang sama berlaku bila pesanan PERNAH Selesai —
        // Selesai -> Menunggu -> Dibatalkan tidak boleh mengembalikan stok.
        if ($terverifikasi && $st === 'Dibatalkan' && in_array($lama, ['Menunggu', 'Diproses'], true) && !$pernahSelesai)
            q($pdo, 'UPDATE menu m JOIN detail_pesanan d ON d.id_menu=m.id_menu SET m.stok=m.stok+d.jumlah WHERE d.id_pesanan=?', [$id]);
        // L3-M1: buka-ulang hanya memotong ulang stok bila pembatalan
        // sebelumnya benar-benar mengembalikan stok — yaitu bila pesanan
        // belum pernah Selesai. Pesanan yang pernah Selesai dibatalkan tanpa
        // mengembalikan stok, jadi buka-ulangnya tidak memotong apa-apa
        // (dan tidak ditolak karena stok menipis).
        if ($terverifikasi && $lama === 'Dibatalkan' && $st !== 'Dibatalkan' && !$pernahSelesai) { // dibuka lagi -> potong ulang, cek stok dulu
            // L-m13: kunci tiap baris menu sebelum cek (pola yang sama dengan
            // pembayaran_verify) supaya dua admin tidak sama-sama lolos cek.
            $items = q($pdo, 'SELECT id_menu, SUM(jumlah) AS qty FROM detail_pesanan WHERE id_pesanan=? AND id_menu IS NOT NULL GROUP BY id_menu', [$id])->fetchAll();
            foreach ($items as $it) {
                $m = q($pdo, 'SELECT nama_menu, stok FROM menu WHERE id_menu=? FOR UPDATE', [$it['id_menu']])->fetch();
                if ($m && (int)$m['stok'] < (int)$it['qty'])
                    fail('Stok ' . $m['nama_menu'] . ' tidak mencukupi untuk membuka lagi pesanan ini.');
            }
            q($pdo, 'UPDATE menu m JOIN detail_pesanan d ON d.id_menu=m.id_menu SET m.stok=m.stok-d.jumlah WHERE d.id_pesanan=?', [$id]);
        }
        // L2-M3: buka-ulang pesanan batal yang belum bayar ke Menunggu memberi
        // kesempatan kedua — jam 5 menit dihitung ulang dari sekarang, dan
        // pembayaran terminal (Kadaluarsa/Ditolak) kembali Menunggu Verifikasi
        // supaya bisa dibayar/diverifikasi. Tanpa ini, pesanan langsung
        // dibatalkan lagi oleh sweeper (atau buntu bila dibuka ke Diproses).
        // Pembayaran Terverifikasi/Refund tidak disentuh.
        if ($lama === 'Dibatalkan' && $st === 'Menunggu' && !$terverifikasi) {
            q($pdo, 'UPDATE pesanan SET tanggal_pesanan = NOW() WHERE id_pesanan=?', [$id]);
            q($pdo, "UPDATE pembayaran SET status='Menunggu Verifikasi', alasan=NULL WHERE id_pesanan=? AND status IN ('Kadaluarsa','Ditolak')", [$id]);
        }
        // L2-m1: pesanan yang dibatalkan jangan meninggalkan pembayaran
        // 'Menunggu Verifikasi' yang menggantung di antrean admin (termasuk
        // COD) — tandai Kadaluarsa. Pembayaran Terverifikasi tidak disentuh
        // di sini (lihat blok REFUND di bawah).
        if ($st === 'Dibatalkan')
            q($pdo, "UPDATE pembayaran SET status='Kadaluarsa' WHERE id_pesanan=? AND status='Menunggu Verifikasi'", [$id]);
        // REFUND 22 Nov: toko yang membatalkan pesanan berbayar = dana kembali.
        // Pembayaran Terverifikasi diubah ke Refund di transaksi yang sama,
        // supaya pembukuan benar dan dana pelanggan tidak menggantung tanpa
        // jalur refund. (Sebelumnya pembayaran tetap Terverifikasi — hanya
        // alur pengembalian yang mengubahnya.) Selesai tidak sampai sini
        // karena Selesai-lock di atas.
        if ($st === 'Dibatalkan')
            q($pdo, "UPDATE pembayaran SET status='Refund' WHERE id_pesanan=? AND status='Terverifikasi'", [$id]);
        // L2-M2/L3-M1: tandai jejak "pernah Selesai" (lihat pastikan_tabel_fitur).
        if ($st === 'Selesai') q($pdo, 'UPDATE pesanan SET status=?, pernah_selesai=1 WHERE id_pesanan=?', [$st, $id]);
        else q($pdo, 'UPDATE pesanan SET status=? WHERE id_pesanan=?', [$st, $id]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    ok();

case 'pesanan_delete':
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) fail('Pesanan tidak valid.');
    $pdo->beginTransaction();
    try {
        $pesanan = q($pdo, 'SELECT status, pernah_selesai FROM pesanan WHERE id_pesanan=? FOR UPDATE', [$id])->fetch();
        if (!$pesanan) fail('Pesanan tidak ditemukan.');
        $terverifikasi = (bool) q($pdo, "SELECT 1 FROM pembayaran WHERE id_pesanan=? AND status='Terverifikasi' LIMIT 1", [$id])->fetchColumn();
        // L2-M2: jangan kembalikan stok bila pesanan PERNAH Selesai.
        if ($terverifikasi && in_array($pesanan['status'], ['Menunggu', 'Diproses'], true) && !(int)$pesanan['pernah_selesai']) {
            q($pdo, 'UPDATE menu m JOIN detail_pesanan d ON d.id_menu=m.id_menu SET m.stok=m.stok+d.jumlah WHERE d.id_pesanan=?', [$id]);
        }
        q($pdo, 'DELETE FROM pengembalian WHERE id_pesanan=?', [$id]); // L-m17: jangan sisakan baris yatim
        q($pdo, 'DELETE FROM pesanan WHERE id_pesanan=?', [$id]);
        $pdo->commit();
        // L-m4: AUTO_INCREMENT TIDAK di-reset — kode pesanan/transaksi yang sudah
        // pernah dipakai tidak boleh dipakai ulang oleh pesanan baru.
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    ok();

/* ---------- PEMBAYARAN ---------- */
case 'pembayaran':
    sapu_pesanan_kadaluarsa($pdo);
    ok(q($pdo, "SELECT b.id_pembayaran AS pid, b.kode_transaksi AS id, pl.nama_pelanggan AS customer, b.metode AS method, b.jumlah AS total, b.status, b.alasan
                FROM pembayaran b JOIN pesanan p ON p.id_pesanan=b.id_pesanan JOIN pelanggan pl ON pl.id_pelanggan=p.id_pelanggan
                ORDER BY b.tanggal DESC, b.id_pembayaran DESC")->fetchAll());

case 'pembayaran_verify':
    // Titik pemotongan stok: stok berkurang HANYA di sini, saat pembayaran dinyatakan sah.
    // Aman dari klik ganda (baris pembayaran dikunci; status Terverifikasi tidak diproses ulang)
    // dan stok dicek ulang di dalam transaksi sebelum dipotong.
    $id = (int)($in['id'] ?? 0);
    sapu_pesanan_kadaluarsa($pdo); // L-M4: pesanan kadaluarsa harus sudah Dibatalkan sebelum diproses
    $pdo->beginTransaction();
    try {
        $b = q($pdo, 'SELECT id_pesanan, status FROM pembayaran WHERE id_pembayaran=? FOR UPDATE', [$id])->fetch();
        if (!$b) fail('Pembayaran tidak ditemukan.');
        // Hanya pembayaran yang masih menunggu verifikasi yang bisa diverifikasi;
        // status terminal (Ditolak/Kadaluarsa/Refund) tidak bisa dihidupkan lagi.
        if ($b['status'] !== 'Terverifikasi' && $b['status'] !== 'Menunggu Verifikasi')
            fail('Pembayaran berstatus ' . $b['status'] . ' tidak bisa diverifikasi.');
        if ($b['status'] !== 'Terverifikasi') {
            $idp = (int)$b['id_pesanan'];
            $statusPesanan = q($pdo, 'SELECT status FROM pesanan WHERE id_pesanan=? FOR UPDATE', [$idp])->fetchColumn();
            if ($statusPesanan === 'Dibatalkan') fail('Pesanan ini sudah dibatalkan, tidak bisa diverifikasi.');
            $items = q($pdo, 'SELECT id_menu, SUM(jumlah) AS qty FROM detail_pesanan WHERE id_pesanan=? AND id_menu IS NOT NULL GROUP BY id_menu', [$idp])->fetchAll();
            foreach ($items as $it) {
                $m = q($pdo, 'SELECT nama_menu, stok FROM menu WHERE id_menu=? FOR UPDATE', [$it['id_menu']])->fetch();
                if ($m && (int)$m['stok'] < (int)$it['qty'])
                    fail('Stok ' . $m['nama_menu'] . ' tidak mencukupi (sisa ' . $m['stok'] . ', dibutuhkan ' . $it['qty'] . '). Verifikasi dibatalkan.');
            }
            q($pdo, 'UPDATE menu m JOIN detail_pesanan d ON d.id_menu=m.id_menu SET m.stok=m.stok-d.jumlah WHERE d.id_pesanan=?', [$idp]);
            q($pdo, "UPDATE pembayaran SET status='Terverifikasi' WHERE id_pembayaran=?", [$id]);
            q($pdo, "UPDATE pesanan SET status='Diproses' WHERE id_pesanan=? AND status='Menunggu'", [$idp]);
        }
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    ok();

case 'pembayaran_tolak': // L-M6: tolak pembayaran (mis. stok habis saat verifikasi) — tanpa ubah stok
    $id = (int)($in['id'] ?? 0);
    $alasan = mb_substr(trim((string)($in['alasan'] ?? '')), 0, 500) ?: null;
    if ($id <= 0) fail('Pembayaran tidak valid.');
    $pdo->beginTransaction();
    try {
        $b = q($pdo, 'SELECT id_pesanan, status FROM pembayaran WHERE id_pembayaran=? FOR UPDATE', [$id])->fetch();
        if (!$b) fail('Pembayaran tidak ditemukan.');
        if ($b['status'] !== 'Menunggu Verifikasi') fail('Hanya pembayaran yang menunggu verifikasi yang bisa ditolak.');
        q($pdo, "UPDATE pembayaran SET status='Ditolak', alasan=? WHERE id_pembayaran=?", [$alasan, $id]);
        // L2-M5: pembayaran yang ditolak berarti pesanan tidak jadi —
        // batalkan pesanannya juga. Tanpa ini, pesanan COD jadi buntu
        // 'Menunggu + Ditolak' selamanya: sweeper mengecualikan COD,
        // verifikasi menolak Ditolak, Selesai mensyaratkan Terverifikasi.
        // Stok tidak disentuh (pembayaran belum terverifikasi tidak pernah
        // memotong stok).
        q($pdo, "UPDATE pesanan SET status='Dibatalkan' WHERE id_pesanan=? AND status IN ('Menunggu','Diproses')", [(int)$b['id_pesanan']]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    ok();

case 'pembayaran_delete':
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) fail('Pembayaran tidak valid.');
    $pdo->beginTransaction();
    try {
        $b = q($pdo, 'SELECT b.status, p.status AS status_pesanan FROM pembayaran b LEFT JOIN pesanan p ON p.id_pesanan = b.id_pesanan WHERE b.id_pembayaran=? FOR UPDATE', [$id])->fetch();
        if (!$b) fail('Pembayaran tidak ditemukan.');
        // L-M5: pembayaran Terverifikasi tidak boleh dihapus — jejak auditnya
        // harus tetap ada. Pengembalian dana lewat alur pengembalian (status jadi Refund).
        if ($b['status'] === 'Terverifikasi') fail('Pembayaran yang sudah terverifikasi tidak bisa dihapus. Gunakan alur pengembalian untuk mengembalikan dana pelanggan.');
        // L3-m5: pembayaran Refund dilindungi sama — baris itu satu-satunya
        // catatan di sisi pembayaran bahwa dana pernah dikembalikan.
        if ($b['status'] === 'Refund') fail('Pembayaran yang sudah di-refund tidak bisa dihapus. Jejak pengembalian dana harus tetap ada.');
        // L2-m2: menghapus pembayaran pesanan yang masih berjalan membuat
        // pesanan yatim — tidak bisa dibayar, diverifikasi, atau diselesaikan
        // lagi (tidak ada endpoint membuat pembayaran baru). Hapus hanya
        // diizinkan untuk pesanan yang sudah Dibatalkan (bersih-bersih).
        if (in_array($b['status_pesanan'], ['Menunggu', 'Diproses'], true))
            fail('Pembayaran pesanan yang masih berjalan tidak bisa dihapus. Batalkan pesanannya dulu bila perlu.');
        // Pembayaran yang belum terverifikasi tidak pernah memotong stok, jadi
        // penghapusannya tidak mengembalikan stok apa pun (tanpa stok phantom).
        q($pdo, 'DELETE FROM pembayaran WHERE id_pembayaran=?', [$id]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    ok();

/* ---------- PELANGGAN ---------- */
case 'pelanggan':
    // L2-m4: pesanan/belanja hanya dari pesanan dengan pembayaran
    // Terverifikasi (selaras dengan definisi pendapatan L-M3).
    $adaBayarPlg = "EXISTS (SELECT 1 FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan AND b.status = 'Terverifikasi')";
    ok(q($pdo, "SELECT pl.id_pelanggan AS id, pl.nama_pelanggan AS nama, pl.username, pl.email, pl.status,
        (SELECT COUNT(*) FROM pesanan p WHERE p.id_pelanggan=pl.id_pelanggan AND p.status<>'Dibatalkan' AND $adaBayarPlg) AS pesanan,
        (SELECT COALESCE(SUM(p.total_harga),0) FROM pesanan p WHERE p.id_pelanggan=pl.id_pelanggan AND p.status<>'Dibatalkan' AND $adaBayarPlg) AS belanja
        FROM pelanggan pl ORDER BY pl.id_pelanggan")->fetchAll());

/* Rubrik #21: admin menambah/mengubah pelanggan manual. Validasi mengikuti
   registrasi (api/auth.php daftar): username 3-30 karakter [a-z0-9_], email
   valid, password minimal 6 karakter. Password wajib untuk pelanggan baru;
   pada edit, kosongkan bila tidak diubah (bila diisi, sesi lama ikut mati
   lewat sesi_versi). Pesan error eksplisit — admin sudah melihat daftar
   pelanggan, jadi anti-enumerasi registrasi tidak berlaku di sini. */
case 'pelanggan_save':
    $id = (int)($in['id'] ?? 0);
    $nama = mb_substr(req($in, 'nama', 'Nama pelanggan'), 0, 100);
    $username = strtolower(trim((string)($in['username'] ?? '')));
    $email = mb_substr(trim((string)($in['email'] ?? '')), 0, 100);
    if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) fail('Username 3-30 karakter: huruf kecil, angka, atau _.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Format email tidak valid.');
    // L2-M1: username/email yang dipakai akun ADMIN tidak boleh dipakai
    // pelanggan — login mencocokkan kedua tabel dan tabrakan mengunci salah
    // satu pihak keluar. Dicek ke tabel admin (sama seperti daftar).
    if (q($pdo, 'SELECT 1 FROM admin WHERE username=? OR email=?', [$username, $email])->fetch()) fail('Username/email sudah dipakai akun admin.');
    if (q($pdo, 'SELECT 1 FROM pelanggan WHERE username=? AND id_pelanggan<>?', [$username, $id])->fetch()) fail('Username sudah dipakai pelanggan lain.');
    if (q($pdo, 'SELECT 1 FROM pelanggan WHERE email=? AND id_pelanggan<>?', [$email, $id])->fetch()) fail('Email sudah dipakai pelanggan lain.');
    $pw = (string)($in['password'] ?? '');
    if ($id) {
        if (!q($pdo, 'SELECT 1 FROM pelanggan WHERE id_pelanggan=?', [$id])->fetch()) fail('Pelanggan tidak ditemukan.');
        q($pdo, 'UPDATE pelanggan SET nama_pelanggan=?, username=?, email=? WHERE id_pelanggan=?', [$nama, $username, $email, $id]);
        if ($pw !== '') {
            if (strlen($pw) < 6) fail('Password minimal 6 karakter.');
            q($pdo, 'UPDATE pelanggan SET password=?, sesi_versi = sesi_versi + 1 WHERE id_pelanggan=?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
        }
    } else {
        if (strlen($pw) < 6) fail('Password awal minimal 6 karakter.');
        q($pdo, 'INSERT INTO pelanggan (nama_pelanggan, username, email, password) VALUES (?,?,?,?)',
          [$nama, $username, $email, password_hash($pw, PASSWORD_DEFAULT)]);
    }
    ok();

case 'pelanggan_delete':
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) fail('Pelanggan tidak valid.');
    $pdo->beginTransaction();
    try {
        if (!q($pdo, 'SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan=? FOR UPDATE', [$id])->fetch()) {
            fail('Pelanggan tidak ditemukan.');
        }
        q($pdo, "UPDATE menu m
                 JOIN detail_pesanan d ON d.id_menu=m.id_menu
                 JOIN pesanan p ON p.id_pesanan=d.id_pesanan
                 SET m.stok=m.stok+d.jumlah
                 WHERE p.id_pelanggan=? AND p.status NOT IN ('Dibatalkan','Selesai') AND p.pernah_selesai = 0
                 AND EXISTS (SELECT 1 FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan AND b.status = 'Terverifikasi')", [$id]);
        // L-M2: stok dikembalikan HANYA untuk pesanan yang pembayarannya
        // Terverifikasi (pesanan belum bayar tidak pernah memotong stok).
        // L2-M2: dan yang tidak pernah Selesai (barangnya sudah dikonsumsi).
        // L-m17: hapus eksplisit baris milik pelanggan di tabel tanpa FK.
        // L3-m3: testimoni ikut dihapus — testimoni terlewat di L-m17 karena
        // kolom id_pelanggan di testimoni baru ditambahkan belakangan.
        q($pdo, 'DELETE FROM wishlist WHERE id_pelanggan=?', [$id]);
        q($pdo, 'DELETE FROM interaksi WHERE id_pelanggan=?', [$id]);
        q($pdo, 'DELETE FROM pengembalian WHERE id_pelanggan=?', [$id]);
        q($pdo, 'DELETE FROM testimoni WHERE id_pelanggan=?', [$id]);
        q($pdo, 'DELETE FROM pesanan WHERE id_pelanggan=?', [$id]);
        q($pdo, 'DELETE FROM pelanggan WHERE id_pelanggan=?', [$id]);
        $pdo->commit();
        // L-m4: AUTO_INCREMENT TIDAK di-reset (lihat pesanan_delete).
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    ok();

case 'pelanggan_status': // L-m20: nonaktifkan/aktifkan akun pelanggan
    $id = (int)($in['id'] ?? 0);
    $st = $in['status'] ?? '';
    if (!in_array($st, ['Aktif', 'Nonaktif'], true)) fail('Status tidak valid.');
    if (!q($pdo, 'SELECT 1 FROM pelanggan WHERE id_pelanggan=?', [$id])->fetch()) fail('Pelanggan tidak ditemukan.');
    // sesi_versi dinaikkan supaya sesi pelanggan yang sedang login ikut mati.
    q($pdo, 'UPDATE pelanggan SET status=?, sesi_versi = sesi_versi + 1 WHERE id_pelanggan=?', [$st, $id]);
    ok();

case 'pelanggan_reset_password':
    $id = (int)($in['id'] ?? 0);
    $pw = (string)($in['password'] ?? '');
    if ($id <= 0) fail('Pelanggan tidak valid.');
    if (strlen($pw) < 6) fail('Password baru minimal 6 karakter.');
    if (!q($pdo, 'SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan=?', [$id])->fetch()) fail('Pelanggan tidak ditemukan.');
    // L-m6: naikkan sesi_versi supaya sesi lama pemilik akun ikut tidak berlaku.
    q($pdo, 'UPDATE pelanggan SET password=?, sesi_versi = sesi_versi + 1 WHERE id_pelanggan=?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
    ok();

/* ---------- LAPORAN ---------- */
case 'laporan':
    $dari = $_GET['dari'] ?? date('Y-m-01'); $sampai = $_GET['sampai'] ?? date('Y-m-d'); $status = $_GET['status'] ?? 'Selesai';
    $rows = laporan_rows($pdo, $dari, $sampai, $status);
    // L-M3: angka pendapatan hanya dari pesanan dengan pembayaran Terverifikasi.
    $adaBayar = "EXISTS (SELECT 1 FROM pembayaran b WHERE b.id_pesanan = pesanan.id_pesanan AND b.status = 'Terverifikasi')";
    $hari = q($pdo, "SELECT COALESCE(SUM(total_harga),0) FROM pesanan WHERE status='Selesai' AND $adaBayar AND DATE(tanggal_pesanan)=CURDATE()")->fetchColumn();
    $bulan = q($pdo, "SELECT COALESCE(SUM(total_harga),0) FROM pesanan WHERE status='Selesai' AND $adaBayar AND YEAR(tanggal_pesanan)=YEAR(CURDATE()) AND MONTH(tanggal_pesanan)=MONTH(CURDATE())")->fetchColumn();
    $selesai = q($pdo, "SELECT COUNT(*) FROM pesanan WHERE status='Selesai'")->fetchColumn();
    // L3-m1: total_periode memakai definisi yang SAMA dengan hari_ini/bulan_ini
    // dan dashboard — Selesai DAN terbayar, basis tanggal pesanan. Sebelumnya
    // total_periode menghitung pesanan Menunggu/Diproses yang terverifikasi,
    // sehingga satu layar laporan menampilkan dua angka "hari ini" yang beda.
    ok(['hari_ini' => (int)$hari, 'bulan_ini' => (int)$bulan, 'selesai' => (int)$selesai, 'rows' => $rows,
        'total_periode' => array_sum(array_map(fn($x) => ($x['status'] === 'Selesai' && !empty($x['bayar_terverifikasi'])) ? (int)$x['total_harga'] : 0, $rows))]);

/* ---------- PROMO ---------- */
case 'promo':
    ok(q($pdo, "SELECT id_promo AS id, kode, judul, deskripsi, tipe, nilai, min_transaksi, berlaku_sampai, aktif FROM promo ORDER BY id_promo DESC")->fetchAll());

case 'promo_toggle':
    $id = (int)($in['id'] ?? 0);
    $aktif = filter_var($in['aktif'] ?? null, FILTER_VALIDATE_INT);
    if ($id <= 0 || !in_array($aktif, [0, 1], true)) fail('Status promo tidak valid.');
    if (!q($pdo, 'SELECT 1 FROM promo WHERE id_promo=?', [$id])->fetch()) fail('Promo tidak ditemukan.');
    q($pdo, 'UPDATE promo SET aktif=? WHERE id_promo=?', [$aktif, $id]);
    ok();

case 'promo_save':
    $id = (int)($in['id'] ?? 0);
    $kode = strtoupper(preg_replace('/\s+/', '', req($in, 'kode', 'Kode voucher')));
    panjang_maks($kode, 30, 'Kode voucher'); // L3-m2: promo.kode varchar(30)
    $judul = req($in, 'judul', 'Nama promo');
    panjang_maks($judul, 80, 'Nama promo'); // L3-m2: promo.judul varchar(80)
    $desk = trim((string)($in['deskripsi'] ?? ''));
    panjang_maks($desk, 200, 'Deskripsi'); // L3-m2: promo.deskripsi varchar(200)
    $tipe = ($in['tipe'] ?? '') === 'nominal' ? 'nominal' : 'persen';
    $nilai = (int)($in['nilai'] ?? 0);
    if ($nilai <= 0 || ($tipe === 'persen' && $nilai > 100)) fail('Nilai potongan tidak valid.');
    batas_int($nilai, 'Nilai potongan'); // L3-m2: kolom INT
    $min = max(0, (int)($in['min_transaksi'] ?? 0));
    batas_int($min, 'Minimal transaksi'); // L3-m2: kolom INT
    $sd = trim((string)($in['berlaku_sampai'] ?? '')) ?: null;
    if ($sd !== null && !tanggal_ymd($sd)) fail('Format tanggal tidak valid.'); // L-m14
    $aktif = !empty($in['aktif']) ? 1 : 0;
    if (q($pdo, 'SELECT 1 FROM promo WHERE kode=? AND id_promo<>?', [$kode, $id])->fetch()) fail('Kode voucher sudah dipakai.');
    $a = [$kode, $judul, $desk, $tipe, $nilai, $min, $sd, $aktif];
    if ($id) q($pdo, 'UPDATE promo SET kode=?,judul=?,deskripsi=?,tipe=?,nilai=?,min_transaksi=?,berlaku_sampai=?,aktif=? WHERE id_promo=?', [...$a, $id]);
    else q($pdo, 'INSERT INTO promo (kode,judul,deskripsi,tipe,nilai,min_transaksi,berlaku_sampai,aktif) VALUES (?,?,?,?,?,?,?,?)', $a);
    ok();

case 'promo_delete':
    q($pdo, 'DELETE FROM promo WHERE id_promo=?', [(int)($in['id'] ?? 0)]);
    ok();

/* ---------- ADMIN ---------- */
case 'admins':
    ok(q($pdo, "SELECT id_admin AS id, nama, email, role, status FROM admin ORDER BY id_admin")->fetchAll());

case 'admin_save':
    $id = (int)($in['id'] ?? 0);
    $nama = req($in, 'nama', 'Nama'); $email = req($in, 'email', 'Email');
    panjang_maks($nama, 80, 'Nama'); // L3-m2: admin.nama varchar(80)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Format email tidak valid.');
    panjang_maks($email, 100, 'Email'); // L3-m2: admin.email varchar(100)
    $role = 'Super Admin';
    if (q($pdo, 'SELECT 1 FROM admin WHERE email=? AND id_admin<>?', [$email, $id])->fetch()) fail('Email sudah terdaftar.');
    // L2-M1: email admin tidak boleh sama dengan email pelanggan (login
    // mencocokkan kedua tabel — tabrakan mengunci salah satu pihak keluar).
    if (q($pdo, 'SELECT 1 FROM pelanggan WHERE email=?', [$email])->fetch()) fail('Email sudah dipakai akun pelanggan.');
    if ($id) {
        if (isset($in['password']) && $in['password'] !== '') {
            $password = (string)$in['password'];
            if (strlen($password) < 6) fail('Password minimal 6 karakter.');
        }
        q($pdo, 'UPDATE admin SET nama=?,email=?,role=? WHERE id_admin=?', [$nama, $email, $role, $id]);
        if (isset($password)) {
            // L2-m9: ganti password menaikkan sesi_versi supaya sesi admin
            // lain (mis. di perangkat lain) langsung tidak berlaku; sesi
            // sendiri diperbarui ke versi baru supaya tidak ikut mati.
            q($pdo, 'UPDATE admin SET password=?, sesi_versi = sesi_versi + 1 WHERE id_admin=?', [password_hash($password, PASSWORD_DEFAULT), $id]);
            if ($id === (int)($_SESSION['id'] ?? 0)) {
                $_SESSION['sesi_versi'] = (int) q($pdo, 'SELECT sesi_versi FROM admin WHERE id_admin=?', [$id])->fetchColumn();
            }
        }
    }
    else {
        $password = req($in, 'password', 'Password');
        if (strlen($password) < 6) fail('Password minimal 6 karakter.');
        // L-m15: username diturunkan dari local-part email — cek keunikannya
        // sebelum insert supaya tidak gagal 500 karena UNIQUE bertabrakan.
        $usernameBaru = strtolower(strstr($email, '@', true));
        if (q($pdo, 'SELECT 1 FROM admin WHERE username=?', [$usernameBaru])->fetch())
            fail('Username turunan dari email ini sudah dipakai admin lain. Gunakan email dengan nama depan yang berbeda.');
        // L2-M1: username turunan juga tidak boleh sama dengan username pelanggan.
        if (q($pdo, 'SELECT 1 FROM pelanggan WHERE username=?', [$usernameBaru])->fetch())
            fail('Username turunan dari email ini sudah dipakai akun pelanggan. Gunakan email dengan nama depan yang berbeda.');
        q($pdo, 'INSERT INTO admin (nama,username,email,password,role) VALUES (?,?,?,?,?)',
            [$nama, $usernameBaru, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    }
    ok();

case 'admin_delete':
    if (q($pdo, 'SELECT COUNT(*) FROM admin')->fetchColumn() <= 1) fail('Minimal harus ada satu akun admin.');
    q($pdo, 'DELETE FROM admin WHERE id_admin=?', [(int)($in['id'] ?? 0)]);
    ok();

/* ---------- PROFIL ADMIN SENDIRI (22 Nov, Amar: "admin juga bisa edit
   profile dan password seperti user") — cermin profil_update pelanggan di
   api/auth.php, dengan aturan admin_save: username TIDAK diubah (diturunkan
   dari email saat akun dibuat dan tidak pernah berubah sesudahnya). */
case 'profil_admin':
    pastikan_kolom_foto_admin($pdo);
    $row = q($pdo, "SELECT nama, username, email, role, (foto IS NOT NULL) AS foto_ada, UNIX_TIMESTAMP(foto_diubah) AS foto_ver FROM admin WHERE id_admin = ?", [(int)$_SESSION['id']])->fetch();
    if (!$row) fail('Akun tidak ditemukan.');
    $row['foto_ada'] = (bool)$row['foto_ada'];
    $row['foto_ver'] = $row['foto_ver'] ? (int)$row['foto_ver'] : null;
    ok($row);

case 'profil_admin_update':
    pastikan_kolom_foto_admin($pdo);
    $aid = (int)$_SESSION['id'];
    $cur = q($pdo, 'SELECT username, email, password FROM admin WHERE id_admin = ?', [$aid])->fetch(PDO::FETCH_ASSOC);
    if (!$cur) fail('Akun tidak ditemukan.');
    $nama = req($in, 'nama', 'Nama');
    if (mb_strlen($nama) < 2) fail('Nama minimal 2 karakter.');
    panjang_maks($nama, 80, 'Nama'); // admin.nama varchar(80)
    $email = req($in, 'email', 'Email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Format email tidak valid.');
    panjang_maks($email, 100, 'Email'); // L3-m2: admin.email varchar(100)
    if (q($pdo, 'SELECT 1 FROM admin WHERE email=? AND id_admin<>?', [$email, $aid])->fetch()) fail('Email sudah terdaftar.');
    // L2-M1: email admin tidak boleh sama dengan email pelanggan.
    if (q($pdo, 'SELECT 1 FROM pelanggan WHERE email=?', [$email])->fetch()) fail('Email sudah dipakai akun pelanggan.');
    $pwLama = (string)($in['password_lama'] ?? '');
    $pwBaru = (string)($in['password_baru'] ?? '');
    $pwUlang = (string)($in['password_ulang'] ?? '');
    $emailBerubah = ($email !== $cur['email']);
    $kolomPwDiisi = ($pwLama !== '' || $pwBaru !== '' || $pwUlang !== '');
    // Mengganti email (identitas login) atau password wajib membuktikan password saat ini.
    if ($emailBerubah || $kolomPwDiisi) {
        if ($pwLama === '') fail('Masukkan password saat ini untuk mengganti email atau password.');
        if (!password_verify($pwLama, (string)$cur['password'])) fail('Password saat ini salah.');
    }
    $set = ['nama = ?', 'email = ?'];
    $args = [$nama, $email];
    if ($pwBaru !== '' || $pwUlang !== '') {
        if ($pwBaru === '' || $pwUlang === '') fail('Untuk ganti password, isi password baru dan ulangannya.');
        if (strlen($pwBaru) < 6) fail('Password baru minimal 6 karakter.');
        if ($pwBaru !== $pwUlang) fail('Ulangi password baru tidak sama.');
        $set[] = 'password = ?';
        $args[] = password_hash($pwBaru, PASSWORD_DEFAULT);
        // L2-m9: ganti password mematikan sesi admin lain (mis. di perangkat lain).
        $set[] = 'sesi_versi = sesi_versi + 1';
        $gantiPassword = true;
    }
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) fail('Unggah foto gagal. Coba lagi.');
        if ($_FILES['foto']['size'] > 2 * 1024 * 1024) fail('Ukuran foto maksimal 2MB.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['foto']['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) fail('Format foto harus JPG, PNG, atau WebP.');
        $data = (string)file_get_contents($_FILES['foto']['tmp_name']);
        // Kecilkan foto besar bila GD tersedia, agar database tetap ringan (maks 512px).
        if (function_exists('imagecreatefromstring') && ($im = @imagecreatefromstring($data)) !== false) {
            $w = imagesx($im); $h = imagesy($im);
            $mini = min($w, $h);
            if ($w !== $h && function_exists('imagecrop')) {
                $kotak = imagecrop($im, ['x' => (int)(($w - $mini) / 2), 'y' => (int)(($h - $mini) / 2), 'width' => $mini, 'height' => $mini]);
                if ($kotak !== false) { imagedestroy($im); $im = $kotak; $w = $h = $mini; }
            }
            if (max($w, $h) > 512) {
                $skala = 512 / max($w, $h);
                $nw = max(1, (int)round($w * $skala)); $nh = max(1, (int)round($h * $skala));
                $baru = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($baru, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
                ob_start();
                if ($mime === 'image/png') imagepng($baru);
                elseif ($mime === 'image/webp' && function_exists('imagewebp')) imagewebp($baru, null, 85);
                else { imagejpeg($baru, null, 85); $mime = 'image/jpeg'; }
                $data = (string)ob_get_clean();
                imagedestroy($baru);
            }
            imagedestroy($im);
        }
        $set[] = 'foto = ?'; $args[] = $data;
        $set[] = 'foto_mime = ?'; $args[] = $mime;
        $set[] = 'foto_diubah = NOW()';
    }
    $args[] = $aid;
    q($pdo, 'UPDATE admin SET ' . implode(', ', $set) . ' WHERE id_admin = ?', $args);
    $_SESSION['nama'] = $nama;
    if (!empty($gantiPassword)) {
        // Sesi sendiri ikut diperbarui ke versi baru supaya tidak ikut mati.
        $_SESSION['sesi_versi'] = (int) q($pdo, 'SELECT sesi_versi FROM admin WHERE id_admin = ?', [$aid])->fetchColumn();
    }
    ok(['nama' => $nama, 'email' => $email]);

case 'profil_admin_hapus_foto':
    pastikan_kolom_foto_admin($pdo);
    q($pdo, 'UPDATE admin SET foto = NULL, foto_mime = NULL, foto_diubah = NULL WHERE id_admin = ?', [(int)$_SESSION['id']]);
    ok();


/* ---------- FITUR TAMBAHAN (ERP) ---------- */
case 'stok':
    ok(q($pdo, 'SELECT m.id_menu AS id, m.nama_menu AS nama, k.nama_kategori AS kategori, m.harga, m.stok, m.aktif FROM menu m LEFT JOIN kategori k ON k.id_kategori = m.id_kategori ORDER BY m.stok ASC, m.id_menu')->fetchAll());

case 'stok_save':
    $id = (int)($in['id'] ?? 0); $stok = (int)($in['stok'] ?? -1);
    if ($id <= 0 || $stok < 0) fail('Stok tidak valid.');
    batas_int($stok, 'Stok'); // L3-m2: kolom INT
    q($pdo, 'UPDATE menu SET stok = ? WHERE id_menu = ?', [$stok, $id]);
    ok();

case 'pengiriman':
    ok(q($pdo, "SELECT p.id_pesanan AS id, p.kode_pesanan AS kode, p.tanggal_pesanan AS tanggal, pl.nama_pelanggan AS pelanggan,
                       p.alamat_antar AS alamat, p.ongkir, p.total_harga AS total, p.status,
                       (SELECT b.metode FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan ORDER BY b.id_pembayaran LIMIT 1) AS metode,
                       (SELECT b.status FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan ORDER BY b.id_pembayaran LIMIT 1) AS status_bayar,
                       (SELECT GROUP_CONCAT(CONCAT(d.jumlah,'x ',d.nama_menu) SEPARATOR ', ') FROM detail_pesanan d WHERE d.id_pesanan = p.id_pesanan) AS item
                FROM pesanan p JOIN pelanggan pl ON pl.id_pelanggan = p.id_pelanggan
                WHERE p.cara_ambil = 'Diantar' ORDER BY p.tanggal_pesanan DESC LIMIT 100")->fetchAll());

case 'keuangan':
    $dari = $_GET['dari'] ?? date('Y-m-01'); $sampai = $_GET['sampai'] ?? date('Y-m-d');
    // L3-m1: pemasukan memakai definisi pendapatan yang sama dengan
    // dashboard/laporan — pesanan Selesai DAN terbayar, basis tanggal
    // pesanan. Sebelumnya keuangan menghitung pembayaran Terverifikasi
    // basis tanggal pembayaran (termasuk pesanan yang belum Selesai dan
    // yang dibatalkan), sehingga angkanya berbeda dari modul lain.
    $adaBayar = "EXISTS (SELECT 1 FROM pembayaran b WHERE b.id_pesanan = pesanan.id_pesanan AND b.status = 'Terverifikasi')";
    $masuk = q($pdo, "SELECT COALESCE(SUM(total_harga),0) FROM pesanan WHERE status='Selesai' AND $adaBayar AND DATE(tanggal_pesanan) BETWEEN ? AND ?", [$dari, $sampai])->fetchColumn();
    $jmlTrx = q($pdo, "SELECT COUNT(*) FROM pesanan WHERE status='Selesai' AND $adaBayar AND DATE(tanggal_pesanan) BETWEEN ? AND ?", [$dari, $sampai])->fetchColumn();
    $keluarRows = q($pdo, 'SELECT id_pengeluaran AS id, tanggal, keterangan, jumlah FROM pengeluaran WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal DESC, id_pengeluaran DESC', [$dari, $sampai])->fetchAll();
    $keluar = array_sum(array_map(fn($x) => (int)$x['jumlah'], $keluarRows));
    ok(['dari' => $dari, 'sampai' => $sampai, 'pemasukan' => (int)$masuk, 'trx' => (int)$jmlTrx, 'pengeluaran' => $keluarRows, 'total_keluar' => $keluar, 'saldo' => (int)$masuk - $keluar]);

case 'pengeluaran_save':
    $id = (int)($in['id'] ?? 0);
    $tanggal = trim((string)($in['tanggal'] ?? date('Y-m-d'))) ?: date('Y-m-d');
    if (!tanggal_ymd($tanggal)) fail('Format tanggal tidak valid.'); // L-m14
    $ket = req($in, 'keterangan', 'Keterangan');
    panjang_maks($ket, 200, 'Keterangan'); // L3-m2: pengeluaran.keterangan varchar(200)
    $jumlah = (int)($in['jumlah'] ?? 0);
    if ($jumlah <= 0) fail('Jumlah pengeluaran harus lebih dari 0.');
    batas_int($jumlah, 'Jumlah pengeluaran'); // L3-m2: kolom INT
    if ($id > 0) q($pdo, 'UPDATE pengeluaran SET tanggal=?, keterangan=?, jumlah=? WHERE id_pengeluaran=?', [$tanggal, $ket, $jumlah, $id]);
    else q($pdo, 'INSERT INTO pengeluaran (tanggal, keterangan, jumlah) VALUES (?, ?, ?)', [$tanggal, $ket, $jumlah]);
    ok();

case 'pengeluaran_delete':
    q($pdo, 'DELETE FROM pengeluaran WHERE id_pengeluaran=?', [(int)($in['id'] ?? 0)]);
    ok();

case 'analitik':
    $kHariIni = (int) q($pdo, 'SELECT jumlah FROM kunjungan WHERE tanggal = CURDATE()')->fetchColumn();
    $k7 = q($pdo, "SELECT tanggal, jumlah FROM kunjungan WHERE tanggal >= CURDATE() - INTERVAL 6 DAY ORDER BY tanggal")->fetchAll();
    // L3-m1: penjualan memakai definisi yang sama dengan dashboard —
    // Selesai DAN terbayar, basis tanggal pesanan (bukan tanggal
    // pembayaran). pesanan_hari_ini juga disamakan dengan dashboard:
    // semua pesanan hari ini, termasuk yang dibatalkan — labelnya
    // "pesanan hari ini", bukan "pesanan aktif hari ini".
    $adaBayar = "EXISTS (SELECT 1 FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan AND b.status = 'Terverifikasi')";
    $jual7 = q($pdo, "SELECT DATE(p.tanggal_pesanan) AS tanggal, COALESCE(SUM(p.total_harga),0) AS total FROM pesanan p WHERE p.status='Selesai' AND $adaBayar AND DATE(p.tanggal_pesanan) >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(p.tanggal_pesanan) ORDER BY tanggal")->fetchAll();
    $laris = q($pdo, "SELECT d.nama_menu AS nama, SUM(d.jumlah) AS terjual FROM detail_pesanan d JOIN pesanan p ON p.id_pesanan = d.id_pesanan WHERE p.status = 'Selesai' AND EXISTS (SELECT 1 FROM pembayaran b WHERE b.id_pesanan = p.id_pesanan AND b.status = 'Terverifikasi') GROUP BY d.nama_menu ORDER BY terjual DESC LIMIT 5")->fetchAll();
    $pesananHariIni = (int) q($pdo, "SELECT COUNT(*) FROM pesanan WHERE DATE(tanggal_pesanan) = CURDATE()")->fetchColumn();
    $pendapatanHariIni = (int) q($pdo, "SELECT COALESCE(SUM(p.total_harga),0) FROM pesanan p WHERE p.status='Selesai' AND $adaBayar AND DATE(p.tanggal_pesanan) = CURDATE()")->fetchColumn();
    ok(['kunjungan_hari_ini' => $kHariIni, 'kunjungan_7' => $k7, 'penjualan_7' => $jual7, 'terlaris' => $laris,
        'pesanan_hari_ini' => $pesananHariIni, 'pendapatan_hari_ini' => $pendapatanHariIni]);

case 'pengaturan_get':
    ok(ambil_pengaturan($pdo));

case 'pengaturan_save':
    $boleh = ['nama_kedai', 'alamat', 'telepon', 'email', 'jam_buka', 'instagram'];
    $up = $pdo->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)');
    foreach ($boleh as $k) if (array_key_exists($k, $in)) $up->execute([$k, mb_substr(trim((string)$in[$k]), 0, 500)]);
    // Rubrik #22: ongkir diantar — angka bulat, 0 sampai 1 juta.
    if (array_key_exists('ongkir', $in)) {
        $ongkir = filter_var($in['ongkir'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
        if ($ongkir === false) fail('Ongkir harus berupa angka 0 sampai 1.000.000.');
        $up->execute(['ongkir', (string)$ongkir]);
    }
    ok();

/* Rubrik #30: ganti logo kedai (juga dipakai sebagai favicon — situs memakai
   satu berkas yang sama, img/logo-mampir-kopi.svg, untuk keduanya). Hanya SVG
   yang diterima, karena berkas yang diganti adalah SVG. SVG disaring dari
   skrip dan referensi eksternal supaya tidak bisa mengeksekusi JavaScript. */
case 'logo_upload':
    if (empty($_FILES['logo']['name']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) fail('Pilih berkas logo (SVG) dulu.');
    $f = $_FILES['logo'];
    if ($f['error'] !== UPLOAD_ERR_OK) fail('Upload logo gagal (kode ' . $f['error'] . ').');
    if ($f['size'] > 500 * 1024) fail('Ukuran logo maksimal 500 KB.');
    if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'svg') fail('Logo harus berupa berkas SVG.');
    $isi = file_get_contents($f['tmp_name']);
    if ($isi === false || stripos(ltrim($isi), '<svg') !== 0) fail('Berkas bukan SVG yang valid.');
    // Saring konten berbahaya. Deklarasi namespace SVG/XLink yang standar
    // (http://www.w3.org/2000/svg) dihapus dulu dari pemeriksaan — itu
    // bukan tautan luar, dan logo asli situs sendiri memakainya.
    $periksa = str_ireplace(['http://www.w3.org/2000/svg', 'http://www.w3.org/1999/xlink'], '', $isi);
    $terlarang = ['<script', 'javascript:', '<foreignobject', '<image', '<use', 'http://', 'https://', '//', 'data:'];
    foreach ($terlarang as $t) if (stripos($periksa, $t) !== false) fail('SVG mengandung konten yang tidak diizinkan (skrip/tautan luar).');
    if (preg_match('/<a[\s>]/i', $periksa) || preg_match('/\son\w+\s*=/i', $periksa)) fail('SVG mengandung konten yang tidak diizinkan (skrip/tautan luar).');
    $target = dirname(__DIR__) . '/img/logo-mampir-kopi.svg';
    if (file_put_contents($target, $isi) === false) fail('Logo tidak bisa disimpan. Pastikan folder img/ dapat ditulis.');
    ok(['logo' => 'img/logo-mampir-kopi.svg']);

/* ---------- CMS KONTEN (22 Nov 2026) ---------- */
/* Semua teks konten website (hero, tentang, syarat/privasi, dll) disimpan
   di tabel pengaturan; FAQ dan galeri punya tabel sendiri. Route ini hanya
   dibaca/ditulis Super Admin (wajib_admin di atas + CSRF untuk POST).
   Tidak ada route di sini yang menyentuh password atau kredensial. */
case 'konten_get':
    $s = ambil_pengaturan($pdo);
    $teks = [];
    foreach (konten_kunci() as $k => $maks) $teks[$k] = $s[$k] ?? '';
    ok([
        'teks' => $teks,
        'faq' => q($pdo, 'SELECT id_faq AS id, pertanyaan, jawaban, urutan, aktif FROM faq ORDER BY urutan, id_faq')->fetchAll(),
        'galeri' => q($pdo, 'SELECT id_galeri AS id, gambar, judul, urutan, aktif, lebar FROM galeri ORDER BY urutan, id_galeri')->fetchAll(),
        'menu' => q($pdo, 'SELECT id_menu AS id, nama_menu AS nama FROM menu ORDER BY nama_menu')->fetchAll(),
    ]);

case 'konten_save':
    $teks = $in['teks'] ?? null;
    if (!is_array($teks)) fail('Data konten tidak valid.');
    $boleh = konten_kunci();
    $up = $pdo->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)');
    foreach ($teks as $k => $v) {
        if (!array_key_exists($k, $boleh)) fail("Kunci konten tidak dikenal: $k.");
        $v = trim((string)$v);
        if ($k === 'andalan_ids') {
            // JSON array id menu (maks 4, unik, harus ada di tabel menu).
            $ids = json_decode($v === '' ? '[]' : $v, true);
            if (!is_array($ids)) fail('Daftar menu andalan tidak valid.');
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if (count($ids) > 4) fail('Menu andalan maksimal 4.');
            foreach ($ids as $id) {
                if ($id <= 0 || !q($pdo, 'SELECT 1 FROM menu WHERE id_menu = ?', [$id])->fetch()) fail("Menu andalan tidak ditemukan (id $id).");
            }
            $v = json_encode($ids);
        } else {
            panjang_maks($v, $boleh[$k], "Konten $k");
        }
        $up->execute([$k, $v]);
    }
    ok();

case 'faq_list':
    ok(q($pdo, 'SELECT id_faq AS id, pertanyaan, jawaban, urutan, aktif FROM faq ORDER BY urutan, id_faq')->fetchAll());

case 'faq_save':
    $id = (int)($in['id'] ?? 0);
    $tanya = req($in, 'pertanyaan', 'Pertanyaan');
    $jawab = req($in, 'jawaban', 'Jawaban');
    panjang_maks($tanya, 200, 'Pertanyaan'); // faq.pertanyaan varchar(200)
    $urutan = max(0, (int)($in['urutan'] ?? 0));
    batas_int($urutan, 'Urutan');
    $aktif = !empty($in['aktif']) && $in['aktif'] !== '0' ? 1 : 0;
    if ($id > 0) q($pdo, 'UPDATE faq SET pertanyaan=?, jawaban=?, urutan=?, aktif=? WHERE id_faq=?', [$tanya, $jawab, $urutan, $aktif, $id]);
    else q($pdo, 'INSERT INTO faq (pertanyaan, jawaban, urutan, aktif) VALUES (?, ?, ?, ?)', [$tanya, $jawab, $urutan, $aktif]);
    ok();

case 'faq_delete':
    q($pdo, 'DELETE FROM faq WHERE id_faq = ?', [(int)($in['id'] ?? 0)]);
    ok();

case 'galeri_list':
    ok(q($pdo, 'SELECT id_galeri AS id, gambar, judul, urutan, aktif, lebar FROM galeri ORDER BY urutan, id_galeri')->fetchAll());

case 'galeri_save':
    $id = (int)($in['id'] ?? 0);
    $judul = req($in, 'judul', 'Caption foto');
    panjang_maks($judul, 100, 'Caption foto'); // galeri.judul varchar(100)
    $urutan = max(0, (int)($in['urutan'] ?? 0));
    batas_int($urutan, 'Urutan');
    $aktif = !empty($in['aktif']) && $in['aktif'] !== '0' ? 1 : 0;
    $lebar = !empty($in['lebar']) && $in['lebar'] !== '0' ? 1 : 0;
    if ($id > 0) {
        $lama = q($pdo, 'SELECT gambar FROM galeri WHERE id_galeri = ?', [$id])->fetchColumn();
        if ($lama === false) fail('Foto galeri tidak ditemukan.');
        // simpan_gambar() menghapus berkas lama di uploads/ bila ada foto
        // baru. Foto seed di img/galeri/ dan foto menu di uploads/menu/
        // TIDAK BOLEH terhapus (dipakai halaman lain), jadi hanya teruskan
        // path lama bila ia berkas upload galeri (uploads/galeri/).
        $lamaUpload = ($lama && str_starts_with($lama, 'uploads/galeri/')) ? $lama : null;
        $g = simpan_gambar($lamaUpload, 'galeri', 'gal-');
        if (!$g) $g = $lama; // tidak ada foto baru: pakai foto lama
        q($pdo, 'UPDATE galeri SET gambar=?, judul=?, urutan=?, aktif=?, lebar=? WHERE id_galeri=?', [$g, $judul, $urutan, $aktif, $lebar, $id]);
    } else {
        $g = simpan_gambar(null, 'galeri', 'gal-');
        if (!$g) fail('Pilih foto untuk galeri.');
        q($pdo, 'INSERT INTO galeri (gambar, judul, urutan, aktif, lebar) VALUES (?, ?, ?, ?, ?)', [$g, $judul, $urutan, $aktif, $lebar]);
    }
    ok();

case 'galeri_delete':
    $g = q($pdo, 'SELECT gambar FROM galeri WHERE id_galeri = ?', [(int)($in['id'] ?? 0)])->fetchColumn();
    q($pdo, 'DELETE FROM galeri WHERE id_galeri = ?', [(int)($in['id'] ?? 0)]);
    // Hanya hapus berkas upload (uploads/); foto seed di img/galeri/ dan
    // foto menu di uploads/menu/ dibiarkan (dipakai halaman lain / arsip).
    if ($g && str_starts_with($g, 'uploads/galeri/') && is_file(dirname(__DIR__) . '/' . $g)) @unlink(dirname(__DIR__) . '/' . $g);
    ok();

/* Rubrik #32: data halaman CRM — ringkasan, leads (pesan kontak dari orang
   yang belum tentu pelanggan), pelanggan teratas, interaksi terbaru. */
case 'crm':
    $totalPlg = (int) q($pdo, 'SELECT COUNT(*) FROM pelanggan')->fetchColumn();
    $baru30 = (int) q($pdo, 'SELECT COUNT(*) FROM pelanggan WHERE tanggal_daftar >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)')->fetchColumn();
    $jmlPesanan = (int) q($pdo, "SELECT COUNT(*) FROM pesanan WHERE status <> 'Dibatalkan'")->fetchColumn();
    $totalBelanja = (int) q($pdo, "SELECT COALESCE(SUM(total_harga),0) FROM pesanan WHERE status <> 'Dibatalkan'")->fetchColumn();
    $jmlLeads = (int) q($pdo, 'SELECT COUNT(*) FROM pesan_kontak')->fetchColumn();
    $leadsBaru = (int) q($pdo, 'SELECT COUNT(*) FROM pesan_kontak WHERE dibaca = 0')->fetchColumn();
    $leads = q($pdo, "SELECT k.id_pesan AS id, k.nama, k.email, k.pesan, k.dibaca, k.dibuat,
        (SELECT COUNT(*) FROM pelanggan pl WHERE pl.email = k.email) AS sudah_pelanggan
        FROM pesan_kontak k ORDER BY k.id_pesan DESC LIMIT 50")->fetchAll();
    $top = q($pdo, "SELECT pl.id_pelanggan AS id, pl.nama_pelanggan AS nama, pl.email,
        (SELECT COUNT(*) FROM pesanan p WHERE p.id_pelanggan = pl.id_pelanggan AND p.status <> 'Dibatalkan') AS pesanan,
        (SELECT COALESCE(SUM(p.total_harga),0) FROM pesanan p WHERE p.id_pelanggan = pl.id_pelanggan AND p.status <> 'Dibatalkan') AS belanja,
        (SELECT MAX(p.tanggal_pesanan) FROM pesanan p WHERE p.id_pelanggan = pl.id_pelanggan) AS terakhir
        FROM pelanggan pl ORDER BY belanja DESC, pesanan DESC LIMIT 10")->fetchAll();
    $interaksi = q($pdo, 'SELECT i.catatan, i.dibuat, pl.nama_pelanggan AS pelanggan
        FROM interaksi i JOIN pelanggan pl ON pl.id_pelanggan = i.id_pelanggan
        ORDER BY i.id_interaksi DESC LIMIT 10')->fetchAll();
    ok(['ringkasan' => ['pelanggan' => $totalPlg, 'pelanggan_baru_30hari' => $baru30, 'pesanan' => $jmlPesanan,
        'total_belanja' => $totalBelanja, 'rata_belanja' => $totalPlg > 0 ? (int) round($totalBelanja / $totalPlg) : 0,
        'leads' => $jmlLeads, 'leads_baru' => $leadsBaru],
        'leads' => $leads, 'top' => $top, 'interaksi' => $interaksi]);

case 'kontak_list':
    ok(q($pdo, 'SELECT id_pesan AS id, nama, email, pesan, dibaca, dibuat FROM pesan_kontak ORDER BY id_pesan DESC LIMIT 100')->fetchAll());

case 'kontak_baca':
    q($pdo, 'UPDATE pesan_kontak SET dibaca = 1 WHERE id_pesan = ?', [(int)($in['id'] ?? 0)]);
    ok();

case 'kontak_delete':
    q($pdo, 'DELETE FROM pesan_kontak WHERE id_pesan = ?', [(int)($in['id'] ?? 0)]);
    ok();

case 'testimoni_list':
    ok(q($pdo, 'SELECT id_testimoni AS id, nama, isi, rating, aktif, dibuat FROM testimoni ORDER BY id_testimoni DESC')->fetchAll());

case 'testimoni_toggle':
    q($pdo, 'UPDATE testimoni SET aktif = 1 - aktif WHERE id_testimoni = ?', [(int)($in['id'] ?? 0)]);
    ok();

case 'testimoni_save':
    $id = (int)($in['id'] ?? 0);
    $nama = req($in, 'nama', 'Nama'); $isi = req($in, 'isi', 'Isi testimoni');
    panjang_maks($nama, 100, 'Nama'); // L3-m2: testimoni.nama varchar(100)
    $rating = max(1, min(5, (int)($in['rating'] ?? 5)));
    if ($id > 0) q($pdo, 'UPDATE testimoni SET nama=?, isi=?, rating=? WHERE id_testimoni=?', [$nama, $isi, $rating, $id]);
    else q($pdo, 'INSERT INTO testimoni (nama, isi, rating, aktif) VALUES (?, ?, ?, 1)', [$nama, $isi, $rating]);
    ok();

case 'testimoni_delete':
    q($pdo, 'DELETE FROM testimoni WHERE id_testimoni = ?', [(int)($in['id'] ?? 0)]);
    ok();

case 'artikel_list':
    ok(q($pdo, 'SELECT id_artikel AS id, judul, slug, ringkasan, isi, gambar, dibuat FROM artikel ORDER BY id_artikel DESC')->fetchAll());

case 'artikel_save':
    $id = (int)($in['id'] ?? 0);
    $judul = req($in, 'judul', 'Judul'); $isi = req($in, 'isi', 'Isi artikel');
    panjang_maks($judul, 200, 'Judul'); // L3-m2: artikel.judul varchar(200)
    $ringkasan = mb_substr(trim((string)($in['ringkasan'] ?? '')), 0, 300);
    $gambar = trim((string)($in['gambar'] ?? ''));
    panjang_maks($gambar, 255, 'Gambar'); // L3-m2: artikel.gambar varchar(255) — kelas yang sama
    if ($id > 0) { q($pdo, 'UPDATE artikel SET judul=?, ringkasan=?, isi=?, gambar=? WHERE id_artikel=?', [$judul, $ringkasan, $isi, $gambar, $id]); }
    else {
        $slug = slugify($judul); $dasar = $slug; $n = 2;
        while (q($pdo, 'SELECT COUNT(*) FROM artikel WHERE slug = ?', [$slug])->fetchColumn()) { $slug = $dasar . '-' . $n++; }
        q($pdo, 'INSERT INTO artikel (judul, slug, ringkasan, isi, gambar) VALUES (?, ?, ?, ?, ?)', [$judul, $slug, $ringkasan, $isi, $gambar]);
    }
    ok();

case 'artikel_delete':
    q($pdo, 'DELETE FROM artikel WHERE id_artikel = ?', [(int)($in['id'] ?? 0)]);
    ok();

case 'pengembalian_list':
    // REFUND 22 Nov: sertakan jenis + tujuan + jumlah refund agar admin tahu
    // harus transfer ke mana dan berapa.
    ok(q($pdo, "SELECT g.id_pengembalian AS id, g.alasan, g.status, g.dibuat, g.jenis, g.metode_refund, g.tujuan_refund, g.jumlah_refund, p.kode_pesanan AS kode, p.total_harga AS total, pl.nama_pelanggan AS pelanggan
                FROM pengembalian g JOIN pesanan p ON p.id_pesanan = g.id_pesanan JOIN pelanggan pl ON pl.id_pelanggan = g.id_pelanggan
                ORDER BY g.id_pengembalian DESC LIMIT 100")->fetchAll());

case 'pengembalian_putus':
    $id = (int)($in['id'] ?? 0); $putusan = $in['putusan'] ?? '';
    if (!in_array($putusan, ['Disetujui', 'Ditolak'], true)) fail('Putusan tidak valid.');
    $pdo->beginTransaction();
    try {
        $g = q($pdo, 'SELECT id_pesanan, status FROM pengembalian WHERE id_pengembalian=? FOR UPDATE', [$id])->fetch();
        if (!$g) fail('Pengajuan tidak ditemukan.');
        if ($g['status'] !== 'Menunggu') fail('Pengajuan ini sudah diputuskan.');
        if ($putusan === 'Disetujui') {
            $idp = (int)$g['id_pesanan'];
            $terverifikasi = (bool) q($pdo, "SELECT 1 FROM pembayaran WHERE id_pesanan=? AND status='Terverifikasi' LIMIT 1", [$idp])->fetchColumn();
            $statusP = q($pdo, 'SELECT status FROM pesanan WHERE id_pesanan=? FOR UPDATE', [$idp])->fetchColumn();
            if ($terverifikasi && $statusP !== 'Dibatalkan')
                q($pdo, 'UPDATE menu m JOIN detail_pesanan d ON d.id_menu=m.id_menu SET m.stok=m.stok+d.jumlah WHERE d.id_pesanan=?', [$idp]);
            q($pdo, "UPDATE pembayaran SET status='Refund' WHERE id_pesanan=? AND status='Terverifikasi'", [$idp]);
            q($pdo, "UPDATE pesanan SET status='Dibatalkan' WHERE id_pesanan=?", [$idp]);
        }
        q($pdo, 'UPDATE pengembalian SET status=? WHERE id_pengembalian=?', [$putusan, $id]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    ok();

case 'pelanggan_detail':
    $id = (int)($_GET['id'] ?? ($in['id'] ?? 0));
    $pl = q($pdo, 'SELECT id_pelanggan AS id, nama_pelanggan AS nama, username, email, tanggal_daftar AS dibuat_pada FROM pelanggan WHERE id_pelanggan=?', [$id])->fetch();
    if (!$pl) fail('Pelanggan tidak ditemukan.');
    // L2-m4: hanya pesanan dengan pembayaran Terverifikasi (lihat pelanggan).
    $adaBayarDetail = "EXISTS (SELECT 1 FROM pembayaran b WHERE b.id_pesanan = pesanan.id_pesanan AND b.status = 'Terverifikasi')";
    $pl['total_belanja'] = (int) q($pdo, "SELECT COALESCE(SUM(total_harga),0) FROM pesanan WHERE id_pelanggan=? AND status <> 'Dibatalkan' AND $adaBayarDetail", [$id])->fetchColumn();
    $pl['jumlah_pesanan'] = (int) q($pdo, "SELECT COUNT(*) FROM pesanan WHERE id_pelanggan=? AND status <> 'Dibatalkan' AND $adaBayarDetail", [$id])->fetchColumn();
    $pl['pesanan'] = q($pdo, 'SELECT kode_pesanan AS kode, tanggal_pesanan AS tanggal, total_harga AS total, status FROM pesanan WHERE id_pelanggan=? ORDER BY id_pesanan DESC LIMIT 10', [$id])->fetchAll();
    $pl['interaksi'] = q($pdo, 'SELECT catatan, dibuat FROM interaksi WHERE id_pelanggan=? ORDER BY id_interaksi DESC LIMIT 20', [$id])->fetchAll();
    ok($pl);

case 'interaksi_add':
    $idp = (int)($in['id_pelanggan'] ?? 0);
    $catatan = mb_substr(trim((string)($in['catatan'] ?? '')), 0, 500);
    if ($idp <= 0 || mb_strlen($catatan) < 2) fail('Catatan interaksi belum diisi.');
    // L2-m10: jangan sisakan baris yatim untuk pelanggan yang tidak ada.
    if (!q($pdo, 'SELECT 1 FROM pelanggan WHERE id_pelanggan=?', [$idp])->fetch()) fail('Pelanggan tidak ditemukan.');
    q($pdo, 'INSERT INTO interaksi (id_pelanggan, catatan) VALUES (?, ?)', [$idp, $catatan]);
    ok();

default: fail('Endpoint tidak dikenal.');
}
