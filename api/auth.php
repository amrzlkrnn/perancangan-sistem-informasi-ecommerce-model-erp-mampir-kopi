<?php
/* Login, daftar, logout untuk admin dan pelanggan. */
require __DIR__ . '/config.php';
json_start();
$pdo = db();
$r = $_GET['r'] ?? '';
$in = body();

/* Batas percobaan login: maks 8x gagal per IP+akun dalam 10 menit (catatan di file temp server).
   L-m23: baca–ubah–tulis dilakukan dalam satu kunci file (flock) supaya dua
   percobaan bersamaan tidak saling menimpa hitungan. */
function login_file(callable $fn) {
    $f = sys_get_temp_dir() . '/mk_login_attempts.json';
    $fp = @fopen($f, 'c+');
    if ($fp === false) { $data = []; return $fn($data, time()); }
    try {
        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $data = ($raw === '' || $raw === false) ? [] : (json_decode($raw, true) ?: []);
        $now = time();
        foreach ($data as $k => $v) $data[$k] = array_values(array_filter((array)$v, fn($t) => $t > $now - 600));
        $out = $fn($data, $now);
        ftruncate($fp, 0); rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);
        return $out;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
function login_dicek(string $key): void {
    $terkunci = login_file(fn(array $data) => count($data[$key] ?? []) >= 8);
    if ($terkunci) fail('Terlalu banyak percobaan masuk. Tunggu 10 menit lalu coba lagi.');
}
function login_gagal(string $key): void {
    login_file(function (array &$data, int $now) use ($key): void { $data[$key][] = $now; });
}
function login_berhasil(string $key): void {
    login_file(function (array &$data) use ($key): void { unset($data[$key]); });
}

switch ($r) {

case 'csrf':
    ok(['token' => csrf_token()]);

case 'login':
    $identifier = trim((string)($in['identifier'] ?? $in['username'] ?? ''));
    $pass = (string)($in['password'] ?? '');
    if ($identifier === '' || $pass === '') fail('Username/email dan password wajib diisi.');
    check_csrf();
    $rlKey = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '') . '|' . strtolower($identifier));
    login_dicek($rlKey);
    pastikan_sesi_versi($pdo); // L-m6: kolom versi sesi harus ada sebelum dibaca
    pastikan_sesi_versi_admin($pdo); // L2-m9: sama untuk admin
    pastikan_kolom_tema($pdo); // MODE GELAP: kolom pref_tema harus ada sebelum dibaca di bawah

    $adminSt = $pdo->prepare("SELECT id_admin AS id, nama, password, sesi_versi FROM admin WHERE (username = ? OR email = ?) AND status = 'Aktif'");
    $adminSt->execute([$identifier, $identifier]);
    $admin = $adminSt->fetch();

    $customerSt = $pdo->prepare("SELECT id_pelanggan AS id, nama_pelanggan AS nama, password, sesi_versi, pref_tema FROM pelanggan WHERE (username = ? OR email = ?) AND status = 'Aktif'");
    $customerSt->execute([$identifier, $identifier]);
    $customer = $customerSt->fetch();

    $matches = [];
    if ($admin && password_verify($pass, $admin['password'])) $matches[] = ['role' => 'admin', 'user' => $admin];
    if ($customer && password_verify($pass, $customer['password'])) $matches[] = ['role' => 'user', 'user' => $customer];
    if (count($matches) !== 1) { login_gagal($rlKey); fail('Username atau password salah.'); }
    login_berhasil($rlKey);

    $role = $matches[0]['role'];
    $u = $matches[0]['user'];
    $csrfKeep = $_SESSION['csrf'] ?? null;
    session_regenerate_id(true);
    $_SESSION = ['role' => $role, 'id' => (int)$u['id'], 'nama' => $u['nama']];
    if ($role === 'user') $_SESSION['sesi_versi'] = (int)($u['sesi_versi'] ?? 0); // L-m6
    if ($role === 'admin') $_SESSION['sesi_versi'] = (int)($u['sesi_versi'] ?? 0); // L2-m9
    if ($csrfKeep) $_SESSION['csrf'] = $csrfKeep; // token CSRF tetap berlaku setelah login
    $tbl = $role === 'admin' ? 'admin' : 'pelanggan';
    $kol = $role === 'admin' ? 'id_admin' : 'id_pelanggan';
    $pdo->prepare("UPDATE $tbl SET terakhir_login = NOW() WHERE $kol = ?")->execute([$u['id']]);
    $out = ['role' => $role, 'nama' => $u['nama']];
    // MODE GELAP: login ikut mengembalikan pref_tema supaya login.html bisa
    // menulis cache localStorage SEBELUM redirect — dasbor pertama sesudah
    // login langsung gelap tanpa kilatan (22 Nov 16:34).
    if ($role === 'user') $out['pref_tema'] = ($u['pref_tema'] ?? 'terang') === 'gelap' ? 'gelap' : 'terang';
    ok($out);

case 'daftar':
    check_csrf();
    $username = strtolower(trim((string)($in['username'] ?? '')));
    $nama = trim((string)($in['nama'] ?? $username));
    $email = trim((string)($in['email'] ?? ''));
    $pass = (string)($in['password'] ?? '');
    if ($nama === '' || $username === '' || $email === '' || $pass === '') fail('Semua kolom wajib diisi.');
    // L3-m2: nama & email dibatasi panjang kolomnya (seperti profil_update)
    // supaya input yang terlalu panjang ditolak dengan pesan jelas, bukan 500.
    if (mb_strlen($nama) < 2 || mb_strlen($nama) > 100) fail('Nama harus 2-100 karakter.');
    if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) fail('Username 3-30 karakter: huruf kecil, angka, atau _.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Format email tidak valid.');
    if (mb_strlen($email) > 100) fail('Email maksimal 100 karakter.');
    if (strlen($pass) < 6) fail('Password minimal 6 karakter.');
    // L2-m11: maks 3 pendaftaran per jam per sesi (pola yang sama dengan
    // lacak_percobaan di api/shop.php) — satu-satunya endpoint tulis publik
    // tanpa batas. Dihitung setelah validasi, supaya salah ketik tidak
    // menghabiskan jatah.
    $skrg = time();
    $daftarCoba = array_values(array_filter((array)($_SESSION['daftar_percobaan'] ?? []), fn($t) => $t > $skrg - 3600));
    if (count($daftarCoba) >= 3) fail('Terlalu banyak pendaftaran dari sesi ini. Coba lagi dalam satu jam.');
    $daftarCoba[] = $skrg;
    $_SESSION['daftar_percobaan'] = $daftarCoba;
    $cek = $pdo->prepare('SELECT 1 FROM pelanggan WHERE username = ? OR email = ?');
    $cek->execute([$username, $email]);
    // L2-M1: username/email yang dipakai akun ADMIN juga tidak boleh
    // didaftarkan sebagai pelanggan — login mencocokkan kedua tabel dan
    // gagal bila keduanya cocok, jadi satu akun pelanggan 'admin' bisa
    // mengunci admin keluar. Responsnya sama dengan L-m5 (sukses palsu)
    // supaya tidak membocorkan akun admin mana yang ada.
    $cekAdmin = $pdo->prepare('SELECT 1 FROM admin WHERE username = ? OR email = ?');
    $cekAdmin->execute([$username, $email]);
    // L-m5 (anti-enumerasi): bila username/email sudah terdaftar, kembalikan
    // respons sukses PALSU yang identik dengan pendaftaran berhasil — tanpa
    // membuat akun. Penyerang tidak lagi bisa memetakan akun yang terdaftar.
    // Tradeoff: pendaftar yang salah ketik tidak diberi tahu; ia akan gagal
    // login dan harus mencoba lagi atau memakai fitur lupa password/reset admin.
    if ($cek->fetch() || $cekAdmin->fetch()) ok();
    $pdo->prepare('INSERT INTO pelanggan (nama_pelanggan, username, email, password) VALUES (?,?,?,?)')
        ->execute([$nama, $username, $email, password_hash($pass, PASSWORD_DEFAULT)]);
    ok();

case 'reset':
    // LUPA PASSWORD 22 Nov 2026 (Amar, 14:47 WIB): atur ulang password
    // self-service dengan verifikasi PASSWORD LAMA — tanpa kode reset,
    // tanpa email (situs localhost tanpa mail server; toko tidak punya
    // email). Pengguna yang benar-benar lupa password lamanya tetap butuh
    // reset oleh admin (fitur lama, tidak berubah).
    check_csrf();
    pastikan_sesi_versi($pdo); // kolom sesi_versi harus ada sebelum di-update
    $identifier = trim((string)($in['identifier'] ?? ''));
    $pwLama = (string)($in['password_lama'] ?? '');
    $pwBaru = (string)($in['password'] ?? '');
    $pwUlang = (string)($in['konfirmasi'] ?? '');
    if ($identifier === '' || $pwLama === '' || $pwBaru === '' || $pwUlang === '') fail('Semua kolom wajib diisi.');
    // Rate limit: maks 5 percobaan/jam per sesi (pola L2-m11 di daftar).
    // Dihitung setelah validasi kolom, supaya salah ketik tidak
    // menghabiskan jatah. Batas ini per sesi (bukan per akun) sehingga
    // pesannya tidak membocorkan akun mana yang terdaftar.
    $skrg = time();
    $resetCoba = array_values(array_filter((array)($_SESSION['reset_percobaan'] ?? []), fn($t) => $t > $skrg - 3600));
    if (count($resetCoba) >= 5) fail('Terlalu banyak percobaan atur ulang password dari sesi ini. Coba lagi dalam satu jam.');
    $resetCoba[] = $skrg;
    $_SESSION['reset_percobaan'] = $resetCoba;
    // Anti-enumerasi (pola L3-m6): identifier tak dikenal dan password lama
    // salah mengembalikan pesan yang SAMA — "Password lama salah." — supaya
    // penyerang tidak bisa memetakan akun yang terdaftar. Pesan ini
    // ditampilkan apa adanya di panel reset (Amar, 14:47 WIB).
    $st = $pdo->prepare("SELECT id_pelanggan, password FROM pelanggan WHERE (username = ? OR email = ?) AND status = 'Aktif'");
    $st->execute([$identifier, $identifier]);
    $row = $st->fetch();
    if (!$row || !password_verify($pwLama, (string)$row['password'])) fail('Password lama salah.');
    if (strlen($pwBaru) < 6) fail('Password baru minimal 6 karakter.');
    if ($pwBaru !== $pwUlang) fail('Ulangi password baru tidak sama.');
    $pdo->prepare('UPDATE pelanggan SET password = ?, sesi_versi = sesi_versi + 1 WHERE id_pelanggan = ?')
        ->execute([password_hash($pwBaru, PASSWORD_DEFAULT), (int)$row['id_pelanggan']]);
    ok();

case 'logout':
    // L-m12: logout hanya via POST + CSRF (GET membuka celah logout-CSRF).
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Metode tidak valid.');
    check_csrf();
    $_SESSION = [];
    session_destroy();
    ok();

case 'me':
    if (!isset($_SESSION['role'])) ok(null);
    $out = ['role' => $_SESSION['role'], 'nama' => $_SESSION['nama'], 'username' => null, 'foto_ada' => false, 'foto_ver' => null, 'pref_tema' => 'terang'];
    if ($_SESSION['role'] === 'user') {
        pastikan_kolom_foto($pdo);
        pastikan_kolom_tema($pdo); /* MODE GELAP */
        pastikan_sesi_versi($pdo);
        $st = $pdo->prepare('SELECT nama_pelanggan, username, sesi_versi, pref_tema, (foto IS NOT NULL) AS ada, UNIX_TIMESTAMP(foto_diubah) AS ver FROM pelanggan WHERE id_pelanggan = ?');
        $st->execute([$_SESSION['id']]);
        $row = $st->fetch();
        // L2-m12: akun yang sudah dihapus (atau sesi yang versinya sudah
        // tidak berlaku) tidak lagi dilaporkan sebagai login — header situs
        // memakai endpoint ini, jadi data basi membuatnya tampak masih masuk.
        if (!$row || (isset($_SESSION['sesi_versi']) && (int)$_SESSION['sesi_versi'] !== (int)$row['sesi_versi'])) {
            $_SESSION = []; session_destroy();
            ok(null);
        }
        $out['nama'] = $row['nama_pelanggan'];
        $out['username'] = $row['username'];
        $out['foto_ada'] = (bool)$row['ada'];
        $out['foto_ver'] = $row['ver'] ? (int)$row['ver'] : null;
        $out['pref_tema'] = ($row['pref_tema'] ?? 'terang') === 'gelap' ? 'gelap' : 'terang';
    } elseif ($_SESSION['role'] === 'admin') {
        // L2-m12 (sisi admin): validasi yang sama untuk sesi admin.
        pastikan_sesi_versi_admin($pdo);
        // PROFIL ADMIN 22 Nov: nama/username/email/foto dibaca dari DB
        // (bukan hanya sesi) supaya topbar admin menampilkan foto profil.
        pastikan_kolom_foto_admin($pdo);
        $st = $pdo->prepare("SELECT nama, username, email, sesi_versi, (foto IS NOT NULL) AS ada, UNIX_TIMESTAMP(foto_diubah) AS ver FROM admin WHERE id_admin = ? AND status = 'Aktif'");
        $st->execute([$_SESSION['id']]);
        $row = $st->fetch();
        if (!$row || (isset($_SESSION['sesi_versi']) && (int)$_SESSION['sesi_versi'] !== (int)$row['sesi_versi'])) {
            $_SESSION = []; session_destroy();
            ok(null);
        }
        $out['nama'] = $row['nama'];
        $out['username'] = $row['username'];
        $out['email'] = $row['email'];
        $out['foto_ada'] = (bool)$row['ada'];
        $out['foto_ver'] = $row['ver'] ? (int)$row['ver'] : null;
    }
    ok($out);

case 'profil':
    if (($_SESSION['role'] ?? '') !== 'user') fail('Silakan masuk dulu.');
    pastikan_kolom_foto($pdo);
    pastikan_tabel_fitur($pdo);
    $st = $pdo->prepare('SELECT nama_pelanggan AS nama, username, email, pref_notifikasi, pref_tema, (foto IS NOT NULL) AS foto_ada, UNIX_TIMESTAMP(foto_diubah) AS foto_ver FROM pelanggan WHERE id_pelanggan = ?');
    $st->execute([$_SESSION['id']]);
    $row = $st->fetch();
    if (!$row) fail('Akun tidak ditemukan.');
    $row['foto_ada'] = (bool)$row['foto_ada'];
    $row['pref_notifikasi'] = (bool)($row['pref_notifikasi'] ?? true);
    $row['pref_tema'] = ($row['pref_tema'] ?? 'terang') === 'gelap' ? 'gelap' : 'terang';
    $row['foto_ver'] = $row['foto_ver'] ? (int)$row['foto_ver'] : null;
    ok($row);

case 'profil_update':
    check_csrf();
    if (($_SESSION['role'] ?? '') !== 'user') fail('Silakan masuk dulu.');
    pastikan_kolom_foto($pdo);
    pastikan_tabel_fitur($pdo);
    $uid = (int)$_SESSION['id'];
    // Baris akun saat ini: deteksi perubahan identitas login + verifikasi password.
    $stCur = $pdo->prepare('SELECT username, email, password FROM pelanggan WHERE id_pelanggan = ?');
    $stCur->execute([$uid]);
    $cur = $stCur->fetch(PDO::FETCH_ASSOC);
    if (!$cur) fail('Akun tidak ditemukan.');
    $nama = trim((string)($in['nama'] ?? ''));
    if (mb_strlen($nama) < 2 || mb_strlen($nama) > 100) fail('Nama harus 2-100 karakter.');
    $username = strtolower(trim((string)($in['username'] ?? '')));
    $email = trim((string)($in['email'] ?? ''));
    if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) fail('Username 3-30 karakter: huruf kecil, angka, atau _.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Format email tidak valid.');
    $cek = $pdo->prepare('SELECT 1 FROM pelanggan WHERE (username = ? OR email = ?) AND id_pelanggan <> ?');
    $cek->execute([$username, $email, $uid]);
    // L3-m6: satu pesan generik (pola anti-enumerasi yang sama dengan daftar) —
    // pesan yang membedakan username vs email membocorkan akun yang terdaftar.
    if ($cek->fetch()) fail('Username atau email sudah dipakai akun lain.');
    // L2-M1: username/email admin tidak boleh diambil pelanggan (lihat daftar).
    // Pesan generik yang sama (L3-m6) supaya tidak membocorkan akun admin.
    $cekAdm = $pdo->prepare('SELECT 1 FROM admin WHERE username = ? OR email = ?');
    $cekAdm->execute([$username, $email]);
    if ($cekAdm->fetch()) fail('Username atau email sudah dipakai akun lain.');
    $set = ['nama_pelanggan = ?', 'username = ?', 'email = ?'];
    $args = [$nama, $username, $email];
    if (array_key_exists('pref_notifikasi', $in)) { $set[] = 'pref_notifikasi = ?'; $args[] = ((string)$in['pref_notifikasi'] === '1') ? 1 : 0; }
    // MODE GELAP: nilai selain 'terang'/'gelap' diabaikan — jangan gagalkan
    // seluruh update hanya karena satu preferensi tidak valid.
    if (array_key_exists('pref_tema', $in) && in_array($in['pref_tema'], ['terang', 'gelap'], true)) { $set[] = 'pref_tema = ?'; $args[] = $in['pref_tema']; }
    $pwLama = (string)($in['password_lama'] ?? '');
    $pwBaru = (string)($in['password_baru'] ?? '');
    $pwUlang = (string)($in['password_ulang'] ?? '');
    $identitasBerubah = ($username !== $cur['username'] || $email !== $cur['email']);
    $kolomPwDiisi = ($pwLama !== '' || $pwBaru !== '' || $pwUlang !== '');
    // Mengganti username/email (identitas login) atau password wajib membuktikan password saat ini.
    if ($identitasBerubah || $kolomPwDiisi) {
        if ($pwLama === '') fail('Masukkan password saat ini untuk mengganti username, email, atau password.');
        if (!password_verify($pwLama, (string)$cur['password'])) fail('Password saat ini salah.');
    }
    if ($pwBaru !== '' || $pwUlang !== '') {
        if ($pwBaru === '' || $pwUlang === '') fail('Untuk ganti password, isi password baru dan ulangannya.');
        if (strlen($pwBaru) < 6) fail('Password baru minimal 6 karakter.');
        if ($pwBaru !== $pwUlang) fail('Ulangi password baru tidak sama.');
        $set[] = 'password = ?';
        $args[] = password_hash($pwBaru, PASSWORD_DEFAULT);
        // L-m6: ganti password mematikan sesi lain (mis. di perangkat lain).
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
    $args[] = $uid;
    $pdo->prepare('UPDATE pelanggan SET ' . implode(', ', $set) . ' WHERE id_pelanggan = ?')->execute($args);
    $_SESSION['nama'] = $nama;
    if (!empty($gantiPassword)) {
        // Sesi sendiri ikut diperbarui ke versi baru supaya tidak ikut mati.
        $stV = $pdo->prepare('SELECT sesi_versi FROM pelanggan WHERE id_pelanggan = ?');
        $stV->execute([$uid]);
        $_SESSION['sesi_versi'] = (int)$stV->fetchColumn();
    }
    ok(['nama' => $nama, 'username' => $username, 'email' => $email]);

case 'profil_hapus_foto':
    check_csrf();
    if (($_SESSION['role'] ?? '') !== 'user') fail('Silakan masuk dulu.');
    pastikan_kolom_foto($pdo);
    $pdo->prepare('UPDATE pelanggan SET foto = NULL, foto_mime = NULL, foto_diubah = NULL WHERE id_pelanggan = ?')->execute([$_SESSION['id']]);
    ok();

case 'foto':
    // Mengalirkan foto profil (BLOB dari database) milik pengguna yang sedang masuk.
    // PROFIL ADMIN 22 Nov: admin juga punya foto (tabel admin).
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin') {
        pastikan_kolom_foto_admin($pdo);
        $st = $pdo->prepare('SELECT foto, foto_mime FROM admin WHERE id_admin = ?');
        $st->execute([$_SESSION['id']]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['foto'] === null) { http_response_code(404); exit; }
        header('Content-Type: ' . ($row['foto_mime'] ?: 'image/jpeg'));
        header('Content-Length: ' . strlen($row['foto']));
        header('Cache-Control: private, max-age=3600');
        echo $row['foto'];
        exit;
    }
    if ($role !== 'user') { http_response_code(404); exit; }
    pastikan_kolom_foto($pdo);
    $st = $pdo->prepare('SELECT foto, foto_mime FROM pelanggan WHERE id_pelanggan = ?');
    $st->execute([$_SESSION['id']]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['foto'] === null) { http_response_code(404); exit; }
    header('Content-Type: ' . ($row['foto_mime'] ?: 'image/jpeg'));
    header('Content-Length: ' . strlen($row['foto']));
    header('Cache-Control: private, max-age=3600');
    echo $row['foto'];
    exit;

case 'foto_publik':
    // Foto profil pengirim testimoni (publik, untuk avatar di kartu testimoni).
    // Hanya dilayani bila pemiliknya punya minimal satu testimoni aktif —
    // foto pelanggan yang tidak pernah ber-testimoni tidak terekspos.
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { http_response_code(404); exit; }
    pastikan_tabel_fitur($pdo);
    $st = $pdo->prepare("SELECT p.foto, p.foto_mime FROM pelanggan p WHERE p.id_pelanggan = ? AND p.foto IS NOT NULL AND EXISTS (SELECT 1 FROM testimoni t WHERE t.id_pelanggan = p.id_pelanggan AND t.aktif = 1)");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) { http_response_code(404); exit; }
    header('Content-Type: ' . ($row['foto_mime'] ?: 'image/jpeg'));
    header('Content-Length: ' . strlen($row['foto']));
    header('Cache-Control: public, max-age=3600');
    echo $row['foto'];
    exit;

default: fail('Endpoint tidak dikenal.');
}
