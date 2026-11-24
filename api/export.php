<?php
/* Ekspor Laporan Transaksi ke Excel (.xlsx). Parameter: dari, sampai, status. */
require __DIR__ . '/config.php';
wajib_admin();
json_start(); // L-m11: error (mis. DB mati) jadi JSON ramah, bukan 500 kosong
$pdo = db();
pastikan_tabel_fitur($pdo); // L2-m3: migrasi dulu (enum Kadaluarsa), lalu sapu
sapu_pesanan_kadaluarsa($pdo); // L2-m3: jangan ekspor status basi
$dari = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari'] ?? '') ? $_GET['dari'] : date('Y-m-01');
$sampai = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai'] ?? '') ? $_GET['sampai'] : date('Y-m-d');
$status = $_GET['status'] ?? 'Selesai';
$rows = laporan_rows($pdo, $dari, $sampai, $status);

$head = ['No', 'Tanggal', 'Kode Pesanan', 'Kode Transaksi', 'Pelanggan', 'Item', 'Metode', 'Subtotal', 'Diskon', 'Total', 'Status'];
$data = [];
foreach ($rows as $i => $x) {
    $data[] = [$i + 1, date('d/m/Y H:i', strtotime($x['tanggal_pesanan'])), $x['kode_pesanan'], $x['kode_transaksi'] ?? '-', $x['nama_pelanggan'],
               $x['item'] ?? '-', $x['metode'] ?? '-', (int)$x['subtotal'], (int)$x['diskon'], (int)$x['total_harga'], $x['status']];
}
$judul = 'Laporan Transaksi Mampir Kopi';
$periode = 'Periode: ' . date('d/m/Y', strtotime($dari)) . ' - ' . date('d/m/Y', strtotime($sampai)) . ' | Status: ' . ($status ?: 'Semua');
$fname = 'laporan-transaksi-' . $dari . '_sd_' . $sampai . '.xlsx';
// L-M3/L3-m1: total memakai definisi pendapatan yang sama dengan
// dashboard/laporan — hanya pesanan Selesai DAN terbayar (selaras dengan
// total_periode laporan). Sebelumnya total menghitung pesanan
// Menunggu/Diproses yang terverifikasi.
$totalPendapatan = 0; foreach ($rows as $x) if ($x['status'] === 'Selesai' && !empty($x['bayar_terverifikasi'])) $totalPendapatan += (int)$x['total_harga'];

function kolom(int $i): string { return chr(65 + $i); }
function x(string $s): string { return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }

if (class_exists('ZipArchive')) {
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols>';
    foreach ([5, 17, 14, 15, 18, 44, 11, 13, 11, 13, 13] as $i => $w) $sheet .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
    $sheet .= '</cols><sheetData>';
    $sheet .= '<row r="1"><c r="A1" t="inlineStr" s="3"><is><t>' . x($judul) . '</t></is></c></row>';
    $sheet .= '<row r="2"><c r="A2" t="inlineStr"><is><t>' . x($periode) . '</t></is></c></row>';
    $sheet .= '<row r="4">'; foreach ($head as $i => $h) $sheet .= '<c r="' . kolom($i) . '4" t="inlineStr" s="1"><is><t>' . x($h) . '</t></is></c>'; $sheet .= '</row>';
    $r = 5;
    foreach ($data as $row) {
        $sheet .= '<row r="' . $r . '">';
        foreach ($row as $i => $v) {
            $ref = kolom($i) . $r;
            if (is_int($v) && in_array($i, [7, 8, 9], true)) $sheet .= '<c r="' . $ref . '" s="2"><v>' . $v . '</v></c>';
            elseif (is_int($v)) $sheet .= '<c r="' . $ref . '"><v>' . $v . '</v></c>';
            else $sheet .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . x((string)$v) . '</t></is></c>';
        }
        $sheet .= '</row>'; $r++;
    }
    // Total ditulis sebagai nilai (bukan rumus): hanya pesanan terverifikasi
    // yang dihitung, dan status bayar tidak ada sebagai kolom di sheet ini.
    $sheet .= '<row r="' . ($r + 1) . '"><c r="I' . ($r + 1) . '" t="inlineStr" s="1"><is><t>TOTAL (Selesai &amp; terverifikasi)</t></is></c>'
            . '<c r="J' . ($r + 1) . '" s="2"><v>' . $totalPendapatan . '</v></c></row>';
    $sheet .= '</sheetData></worksheet>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
      . '<numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;Rp&quot;\ #,##0"/></numFmts>'
      . '<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font></fonts>'
      . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF4D3115"/></patternFill></fill></fills>'
      . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
      . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
      . '<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
      . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
      . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
      . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Laporan" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp); unlink($tmp); exit;
}

/* Cadangan bila ekstensi php_zip tidak aktif: tabel HTML yang tetap bisa dibuka Excel (.xls) */
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . str_replace('.xlsx', '.xls', $fname) . '"');
echo "\xEF\xBB\xBF<html><head><meta charset='utf-8'></head><body><h3>" . x($judul) . "</h3><p>" . x($periode) . "</p><table border='1'><tr>";
foreach ($head as $h) echo '<th>' . x($h) . '</th>';
echo '</tr>';
foreach ($data as $row) { echo '<tr>'; foreach ($row as $v) echo '<td>' . x((string)$v) . '</td>'; echo '</tr>'; }
echo '<tr><td colspan="9"><b>TOTAL (Selesai &amp; terverifikasi)</b></td><td><b>' . $totalPendapatan . '</b></td><td></td></tr></table></body></html>';
