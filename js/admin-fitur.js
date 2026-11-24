/* MAMPIR KOPI — Admin: fitur ERP tambahan (stok, pengiriman, pengembalian, keuangan,
   analitik, blog, testimoni, pesan masuk, pengaturan, CRM). Memakai helper admin.js. */

/* ---------- MANAJEMEN STOK ---------- */
async function loadStok() {
  const rows = await api("stok");
  $("stokTable").innerHTML = rows.map(m => {
    const keadaan = m.stok <= 0 ? '<span class="status cancel">Habis</span>' : (m.stok <= 5 ? '<span class="status menunggu">Menipis</span>' : '<span class="status active">Aman</span>');
    return `<tr><td><b>${esc(m.nama)}</b>${+m.aktif ? "" : ' <span class="status off">Nonaktif</span>'}</td><td>${esc(m.kategori || "-")}</td><td class="price-cell">${money(m.harga)}</td>
    <td><input class="stok-input" type="number" min="0" id="stok-${m.id}" value="${m.stok}"></td><td>${keadaan}</td>
    <td><button class="table-btn" onclick="simpanStok(${m.id})">Simpan</button></td></tr>`;
  }).join("") || '<tr><td colspan="6">Belum ada menu.</td></tr>';
}
async function simpanStok(id) {
  try { await api("stok_save", { id, stok: +$("stok-" + id).value }); toast("Stok diperbarui."); loadStok(); }
  catch (e) { toast(e.message, true); }
}

/* ---------- KELOLA PENGIRIMAN ---------- */
async function loadKirim() {
  const rows = await api("pengiriman");
  const opsi = s => ["Menunggu", "Diproses", "Selesai", "Dibatalkan"].map(x => `<option value="${x}" ${x === s ? "selected" : ""}>${x}</option>`).join("");
  $("kirimTable").innerHTML = rows.map(o => `<tr><td><b>${esc(o.kode)}</b></td><td>${tgl(o.tanggal)}</td><td>${esc(o.pelanggan)}</td><td>${esc(o.item || "-")}</td>
    <td>${esc(o.alamat || "-")}<br><small>Ongkir ${money(o.ongkir)}</small></td><td class="price-cell">${money(o.total)}</td>
    <td>${esc(o.metode || "-")}<br><span class="status ${statusClass(o.status_bayar)}">${esc(o.status_bayar || "-")}</span></td>
    <td><select onchange="kirimStatus(${o.id}, this.value)">${opsi(o.status)}</select></td></tr>`).join("")
    || '<tr><td colspan="8">Belum ada pesanan yang diantar.</td></tr>';
  /* Rubrik #22: ongkir diantar bisa diubah dari sini. */
  try {
    const s = await api("pengaturan_get");
    const inp = $("ongkirInput");
    if (inp && document.activeElement !== inp) inp.value = s.ongkir ?? 5000;
  } catch (e) { /* biarkan nilai lama; simpan tetap bisa dicoba */ }
}
async function kirimStatus(id, status) {
  try { await api("pesanan_status", { id, status }); toast("Status pengiriman diperbarui."); }
  catch (e) { toast(e.message, true); loadKirim(); }
}
async function simpanOngkir() {
  try {
    await api("pengaturan_save", { ongkir: +$("ongkirInput").value });
    toast("Ongkir diperbarui menjadi " + money(+$("ongkirInput").value) + ".");
  } catch (e) { toast(e.message, true); }
}

/* ---------- PENGEMBALIAN & REFUND ---------- */
// REFUND 22 Nov: cache baris untuk dialog konfirmasi (butuh jenis + tujuan).
let kembaliCache = [];
async function loadKembali() {
  const rows = await api("pengembalian_list");
  kembaliCache = rows;
  const chip = s => ({ Menunggu: "menunggu", Disetujui: "verified", Ditolak: "cancel" }[s] || "off");
  $("kembaliTable").innerHTML = rows.map(g => `<tr><td>${tgl(g.dibuat)}</td><td><b>${esc(g.kode)}</b></td><td>${esc(g.pelanggan)}</td>
    <td>${esc(g.jenis || "Pengembalian")}</td><td>${esc(g.alasan)}</td>
    <td>${g.jenis === "Refund" ? esc(g.metode_refund || "-") + "<br>" + esc(g.tujuan_refund || "-") : "-"}</td>
    <td class="price-cell">${money(g.jumlah_refund ?? g.total)}</td><td><span class="status ${chip(g.status)}">${esc(g.status)}</span></td>
    <td>${g.status === "Menunggu" ? `<button class="table-btn" onclick="putusKembali(${g.id}, 'Disetujui')">Setujui</button> <button class="table-btn delete" onclick="putusKembali(${g.id}, 'Ditolak')">Tolak</button>` : "-"}</td></tr>`).join("")
    || '<tr><td colspan="9">Belum ada pengajuan pengembalian.</td></tr>';
}
async function putusKembali(id, putusan) {
  if (putusan === "Disetujui") {
    // REFUND 22 Nov: transfer dana dilakukan admin secara manual di luar
    // aplikasi — ingatkan tujuannya sebelum menyetujui.
    const g = kembaliCache.find(x => +x.id === +id);
    const pesan = g && g.jenis === "Refund"
      ? `Setujui refund ini? Transfer ${money(g.jumlah_refund ?? g.total)} secara manual ke ${g.metode_refund || "-"} ${g.tujuan_refund || "-"}, lalu pesanan dibatalkan dan stok dipulihkan.`
      : "Setujui pengembalian ini? Dana dikembalikan, stok dipulihkan, dan pesanan dibatalkan.";
    if (!confirm(pesan)) return;
  }
  try { await api("pengembalian_putus", { id, putusan }); toast("Pengajuan " + putusan.toLowerCase() + "."); loadKembali(); }
  catch (e) { toast(e.message, true); }
}

/* ---------- LAPORAN KEUANGAN ---------- */
let keuanganCache = [];
async function loadKeuangan() {
  const dari = $("keuDari").value, sampai = $("keuSampai").value;
  const d = await api("keuangan", null, `&dari=${dari}&sampai=${sampai}`);
  keuanganCache = d.pengeluaran;
  $("keuCards").innerHTML = `
    <div class="admin-stat"><span class="stat-icon"></span><p>Pemasukan (${d.trx} transaksi)</p><strong>${money(d.pemasukan)}</strong></div>
    <div class="admin-stat"><span class="stat-icon"></span><p>Total Pengeluaran</p><strong>${money(d.total_keluar)}</strong></div>
    <div class="admin-stat"><span class="stat-icon"></span><p>Saldo Bersih</p><strong>${money(d.saldo)}</strong></div>`;
  /* L-m8: jangan sisipkan teks bebas ke onclick (stored XSS) — tombol hanya
     membawa id lewat data-*, nilai dibaca dari cache lewat addEventListener. */
  $("keluarTable").innerHTML = d.pengeluaran.map(x => `<tr><td>${tglOnly(x.tanggal)}</td><td>${esc(x.keterangan)}</td><td class="price-cell">${money(x.jumlah)}</td>
    <td><button class="table-btn" data-edit-keluar="${x.id}">Edit</button> <button class="table-btn delete" onclick="hapusKeluar(${x.id})">Hapus</button></td></tr>`).join("")
    || '<tr><td colspan="4">Belum ada pengeluaran pada rentang ini.</td></tr>';
  $("keluarTable").querySelectorAll("[data-edit-keluar]").forEach(b => b.addEventListener("click", () => {
    const x = keuanganCache.find(y => y.id == b.dataset.editKeluar);
    if (x) editKeluar(x.id, x.tanggal, x.keterangan, x.jumlah);
  }));
}
function formKeluar(x = {}) {
  return `<div class="form-group"><label>TANGGAL</label><input id="pgTanggal" type="date" value="${x.tanggal || new Date().toISOString().slice(0, 10)}"></div>
  <div class="form-group"><label>KETERANGAN</label><input id="pgKet" value="${esc(x.keterangan || "")}" placeholder="Contoh: Beli biji kopi 5 kg"></div>
  <div class="form-group"><label>JUMLAH (Rp)</label><input id="pgJumlah" type="number" min="0" value="${x.jumlah ?? ""}" placeholder="150000"></div>`;
}
function tambahKeluar() { showModal("Tambah Pengeluaran", formKeluar(), async () => { await api("pengeluaran_save", { tanggal: $("pgTanggal").value, keterangan: $("pgKet").value, jumlah: +$("pgJumlah").value }); toast("Pengeluaran dicatat."); loadKeuangan(); }); }
function editKeluar(id, tanggal, keterangan, jumlah) { showModal("Edit Pengeluaran", formKeluar({ tanggal, keterangan, jumlah }), async () => { await api("pengeluaran_save", { id, tanggal: $("pgTanggal").value, keterangan: $("pgKet").value, jumlah: +$("pgJumlah").value }); toast("Pengeluaran diperbarui."); loadKeuangan(); }); }
async function hapusKeluar(id) { if (!confirm("Hapus catatan pengeluaran ini?")) return; try { await api("pengeluaran_delete", { id }); loadKeuangan(); } catch (e) { toast(e.message, true); } }

/* ---------- ANALITIK ---------- */
function barChart(el, data, label) {
  if (!el) return;
  const max = Math.max(1, ...data.map(x => +x.total || 0));
  el.innerHTML = data.map((x, i) => `<div class="bar-col${i === data.length - 1 ? " today" : ""}" title="${tglOnly(x.tanggal)}: ${label(x)}"><span style="height:${Math.max(3, Math.round((+x.total || 0) / max * 100))}%"></span><small>${tglOnly(x.tanggal).split(" ")[0]}</small></div>`).join("");
}
async function loadAnalitik() {
  const d = await api("analitik");
  $("anaCards").innerHTML = `
    <div class="admin-stat"><p>Kunjungan Hari Ini</p><strong>${d.kunjungan_hari_ini}</strong></div>
    <div class="admin-stat"><p>Pesanan Hari Ini</p><strong>${d.pesanan_hari_ini}</strong></div>
    <div class="admin-stat"><p>Pendapatan Hari Ini</p><strong>${money(d.pendapatan_hari_ini)}</strong></div>`;
  barChart($("anaJual"), d.penjualan_7.map(x => ({ tanggal: x.tanggal, total: x.total })), x => money(x.total));
  barChart($("anaKunjung"), d.kunjungan_7.map(x => ({ tanggal: x.tanggal, total: x.jumlah })), x => x.total + " kunjungan");
  $("anaLaris").innerHTML = d.terlaris.length ? `<table class="admin-table"><thead><tr><th>Menu</th><th>Terjual</th></tr></thead><tbody>${d.terlaris.map(x => `<tr><td><b>${esc(x.nama)}</b></td><td>${x.terjual}</td></tr>`).join("")}</tbody></table>` : '<p class="f-note">Belum ada penjualan selesai.</p>';
}

/* ---------- BLOG ---------- */
let artikelCache = [];
async function loadBlog() {
  artikelCache = await api("artikel_list");
  $("blogTable").innerHTML = artikelCache.map(a => `<tr><td><b>${esc(a.judul)}</b><br><small>blog: artikel.html?slug=${esc(a.slug)}</small></td><td>${tgl(a.dibuat)}</td>
    <td><button class="table-btn" onclick="editArtikel(${a.id})">Edit</button> <button class="table-btn delete" onclick="hapusArtikel(${a.id})">Hapus</button></td></tr>`).join("")
    || '<tr><td colspan="3">Belum ada artikel.</td></tr>';
}
function formArtikel(a = {}) {
  return `<div class="form-group"><label>JUDUL</label><input id="arJudul" value="${esc(a.judul || "")}"></div>
  <div class="form-group"><label>RINGKASAN</label><input id="arRingkasan" value="${esc(a.ringkasan || "")}" placeholder="Satu-dua kalimat pembuka"></div>
  <div class="form-group"><label>GAMBAR (path, opsional)</label><input id="arGambar" value="${esc(a.gambar || "")}" placeholder="img/promo-ngopi.jpg"></div>
  <div class="form-group"><label>ISI ARTIKEL</label><textarea id="arIsi" rows="9" placeholder="Pisahkan paragraf dengan baris kosong">${esc(a.isi || "")}</textarea></div>`;
}
function bacaArtikel(id) { return { id, judul: $("arJudul").value, ringkasan: $("arRingkasan").value, gambar: $("arGambar").value, isi: $("arIsi").value }; }
function tambahArtikel() { showModal("Tulis Artikel", formArtikel(), async () => { await api("artikel_save", bacaArtikel(0)); toast("Artikel diterbitkan."); loadBlog(); }); }
function editArtikel(id) { const a = artikelCache.find(x => x.id == id); showModal("Edit Artikel", formArtikel(a), async () => { await api("artikel_save", bacaArtikel(id)); toast("Artikel diperbarui."); loadBlog(); }); }
async function hapusArtikel(id) { if (!confirm("Hapus artikel ini?")) return; try { await api("artikel_delete", { id }); loadBlog(); } catch (e) { toast(e.message, true); } }

/* ---------- TESTIMONI ---------- */
let testiCache = [];
async function loadTesti() {
  testiCache = await api("testimoni_list");
  $("testiTable").innerHTML = testiCache.map(x => `<tr><td><b>${esc(x.nama)}</b></td><td>${esc(x.isi)}</td><td>${"★".repeat(x.rating)}</td>
    <td><span class="status ${+x.aktif ? "active" : "menunggu"}">${+x.aktif ? "Aktif" : "Menunggu"}</span></td>
    <td><button class="table-btn" onclick="toggleTesti(${x.id})">${+x.aktif ? "Sembunyikan" : "Setujui"}</button> <button class="table-btn" onclick="editTesti(${x.id})">Edit</button> <button class="table-btn delete" onclick="hapusTesti(${x.id})">Hapus</button></td></tr>`).join("")
    || '<tr><td colspan="5">Belum ada testimoni.</td></tr>';
}
async function toggleTesti(id) { try { await api("testimoni_toggle", { id }); loadTesti(); } catch (e) { toast(e.message, true); } }
function formTesti(x = {}) {
  return `<div class="form-group"><label>NAMA</label><input id="tsNama" value="${esc(x.nama || "")}"></div>
  <div class="form-group"><label>RATING</label><select id="tsRating">${[5, 4, 3, 2, 1].map(n => `<option value="${n}" ${+x.rating === n ? "selected" : ""}>${"★".repeat(n)}</option>`).join("")}</select></div>
  <div class="form-group"><label>ISI TESTIMONI</label><textarea id="tsIsi" rows="4">${esc(x.isi || "")}</textarea></div>`;
}
function tambahTesti() { showModal("Tambah Testimoni", formTesti(), async () => { await api("testimoni_save", { nama: $("tsNama").value, isi: $("tsIsi").value, rating: +$("tsRating").value }); toast("Testimoni ditambahkan (langsung aktif)."); loadTesti(); }); }
function editTesti(id) { const x = testiCache.find(y => y.id == id); showModal("Edit Testimoni", formTesti(x), async () => { await api("testimoni_save", { id, nama: $("tsNama").value, isi: $("tsIsi").value, rating: +$("tsRating").value }); toast("Testimoni diperbarui."); loadTesti(); }); }
async function hapusTesti(id) { if (!confirm("Hapus testimoni ini?")) return; try { await api("testimoni_delete", { id }); loadTesti(); } catch (e) { toast(e.message, true); } }

/* ---------- PESAN MASUK ---------- */
async function loadKontak() {
  const rows = await api("kontak_list");
  $("kontakTable").innerHTML = rows.map(x => `<tr><td>${tgl(x.dibuat)}</td><td><b>${esc(x.nama)}</b></td><td>${esc(x.email)}</td><td>${esc(x.pesan)}</td>
    <td><span class="status ${+x.dibaca ? "off" : "menunggu"}">${+x.dibaca ? "Dibaca" : "Baru"}</span></td>
    <td>${+x.dibaca ? "" : `<button class="table-btn" onclick="bacaKontak(${x.id})">Tandai Dibaca</button> `}<button class="table-btn delete" onclick="hapusKontak(${x.id})">Hapus</button></td></tr>`).join("")
    || '<tr><td colspan="6">Belum ada pesan masuk.</td></tr>';
}
async function bacaKontak(id) { try { await api("kontak_baca", { id }); loadKontak(); } catch (e) { toast(e.message, true); } }
async function hapusKontak(id) { if (!confirm("Hapus pesan ini?")) return; try { await api("kontak_delete", { id }); loadKontak(); } catch (e) { toast(e.message, true); } }

/* ---------- PENGATURAN WEBSITE ---------- */
async function loadAturan() {
  const s = await api("pengaturan_get");
  $("setNama").value = s.nama_kedai || ""; $("setAlamat").value = s.alamat || ""; $("setTelepon").value = s.telepon || "";
  $("setEmail").value = s.email || ""; $("setJam").value = s.jam_buka || ""; $("setIG").value = s.instagram || "";
  /* Rubrik #30: preview logo saat ini (cache-buster supaya logo baru langsung terlihat). */
  const pv = $("logoPreview");
  if (pv) pv.src = "img/logo-mampir-kopi.svg?t=" + Date.now();
}
async function unggahLogo() {
  const f = $("logoFile").files[0];
  if (!f) return toast("Pilih berkas logo (SVG) dulu.", true);
  try {
    const fd = new FormData();
    fd.append("logo", f);
    await api("logo_upload", fd);
    $("logoFile").value = "";
    toast("Logo diperbarui (favicon ikut berubah — keduanya memakai berkas yang sama).");
    loadAturan();
  } catch (e) { toast(e.message, true); }
}

/* ---------- CRM: DETAIL PELANGGAN ---------- */
async function detailPelanggan(id) {
  try {
    const d = await api("pelanggan_detail", null, "&id=" + id);
    const body = `
      <div class="form-group"><label>PROFIL</label><p><b>${esc(d.nama)}</b> (@${esc(d.username)})<br>${esc(d.email || "-")} · daftar ${tgl(d.dibuat_pada)}</p></div>
      <div class="form-group"><label>ANALISIS PELANGGAN</label><p>Total belanja <b>${money(d.total_belanja)}</b> dari <b>${d.jumlah_pesanan}</b> pesanan.</p></div>
      <div class="form-group"><label>RIWAYAT PESANAN TERAKHIR</label>${d.pesanan.length ? `<table class="admin-table"><tbody>${d.pesanan.map(o => `<tr><td>${esc(o.kode)}</td><td>${tgl(o.tanggal)}</td><td>${money(o.total)}</td><td><span class="status ${statusClass(o.status)}">${esc(o.status)}</span></td></tr>`).join("")}</tbody></table>` : "<p>Belum ada pesanan.</p>"}</div>
      <div class="form-group"><label>RIWAYAT INTERAKSI</label>${d.interaksi.length ? d.interaksi.map(i => `<p><small>${tgl(i.dibuat)}</small><br>${esc(i.catatan)}</p>`).join("") : "<p>Belum ada catatan interaksi.</p>"}</div>
      <div class="form-group"><label>TAMBAH CATATAN INTERAKSI</label><textarea id="ikCatatan" rows="3" placeholder="Contoh: Minta info promo untuk acara kantor"></textarea></div>`;
    showModal("Detail Pelanggan (CRM)", body, async () => {
      const c = $("ikCatatan").value.trim();
      if (c) { await api("interaksi_add", { id_pelanggan: id, catatan: c }); toast("Catatan interaksi disimpan."); }
    });
  } catch (e) { toast(e.message, true); }
}

/* ---------- CRM: DIGABUNG KE DASHBOARD (22 Nov, Amar) ----------
   Seksi CRM terpisah dihapus; kontennya pindah ke seksi Dashboard di
   admin.html dan dimuat dari loadDashboard() di admin.js. */
async function loadCrm() {
  const d = await api("crm");
  const r = d.ringkasan;
  $("crmCards").innerHTML = `
    <div class="admin-stat"><span class="stat-icon"></span><div><small>Total Pelanggan</small><strong>${r.pelanggan}</strong><em>${r.pelanggan_baru_30hari} baru 30 hari terakhir</em></div></div>
    <div class="admin-stat"><span class="stat-icon"></span><div><small>Leads (Pesan Masuk)</small><strong>${r.leads}</strong><em>${r.leads_baru} belum dibaca</em></div></div>
    <div class="admin-stat"><span class="stat-icon">Rp</span><div><small>Total Belanja Pelanggan</small><strong>${money(r.total_belanja)}</strong><em>${r.pesanan} pesanan (tanpa yang dibatalkan)</em></div></div>
    <div class="admin-stat"><span class="stat-icon">Rp</span><div><small>Rata-rata Belanja / Pelanggan</small><strong>${money(r.rata_belanja)}</strong><em>Total belanja dibagi jumlah pelanggan</em></div></div>`;
  $("crmLeadsTable").innerHTML = d.leads.map(l => `<tr><td>${tgl(l.dibuat)}</td><td><b>${esc(l.nama)}</b></td><td>${esc(l.email)}</td><td>${esc(l.pesan)}</td>
    <td><span class="status ${+l.sudah_pelanggan ? "active" : "menunggu"}">${+l.sudah_pelanggan ? "Sudah pelanggan" : "Prospek baru"}</span>${+l.dibaca ? "" : ' <span class="status menunggu">Baru</span>'}</td></tr>`).join("")
    || '<tr><td colspan="5">Belum ada pesan masuk.</td></tr>';
  $("crmTopTable").innerHTML = d.top.map(t => `<tr><td><b>${esc(t.nama)}</b><br><small>${esc(t.email || "-")}</small></td><td>${t.pesanan}</td><td class="price-cell">${money(t.belanja)}</td><td>${t.terakhir ? tgl(t.terakhir) : "-"}</td></tr>`).join("")
    || '<tr><td colspan="4">Belum ada pelanggan dengan pesanan.</td></tr>';
  $("crmInteraksi").innerHTML = d.interaksi.length ? d.interaksi.map(i => `<p><b>${esc(i.pelanggan)}</b> <small>${tgl(i.dibuat)}</small><br>${esc(i.catatan)}</p>`).join("") : '<p class="f-note">Belum ada catatan interaksi. Tambah lewat tombol Detail di Kelola Pelanggan.</p>';
}

/* ---------- KONTEN WEBSITE (CMS, 22 Nov 2026) ---------- */
/* Semua tulisan, FAQ, dan foto galeri website bisa diubah Super Admin
   dari seksi ini; perubahan langsung tampil di halaman publik. Tidak ada
   fungsi di sini yang menyentuh password atau kredensial. */
let kontenCache = null;

async function loadKonten() {
  const d = await api("konten_get");
  kontenCache = d;
  document.querySelectorAll("[data-konten-key]").forEach(el => { el.value = d.teks[el.dataset.kontenKey] ?? ""; });
  let ids = [];
  try { ids = JSON.parse(d.teks.andalan_ids || "[]"); } catch (e) { ids = []; }
  document.querySelectorAll("[data-andalan]").forEach(sel => {
    const i = +sel.dataset.andalan;
    sel.innerHTML = '<option value="">(kosong)</option>' + d.menu.map(m => `<option value="${m.id}" ${ids[i] == m.id ? "selected" : ""}>${esc(m.nama)}</option>`).join("");
  });
  renderFaqTable(d.faq);
  renderGaleriGrid(d.galeri);
}

async function simpanKonten(keys) {
  const teks = {};
  keys.forEach(k => {
    const el = document.querySelector('[data-konten-key="' + k + '"]');
    if (el) teks[k] = el.value;
  });
  await api("konten_save", { teks });
  toast("Konten disimpan. Perubahan langsung tampil di halaman.");
}

async function simpanAndalan() {
  const ids = [...document.querySelectorAll("[data-andalan]")].map(s => +s.value).filter(v => v > 0);
  await api("konten_save", { teks: { andalan_ids: JSON.stringify(ids) } });
  toast("Menu andalan disimpan.");
}

function renderFaqTable(rows) {
  $("faqTable").innerHTML = rows.map(f => `<tr><td>${f.urutan}</td><td><b>${esc(f.pertanyaan)}</b><br><small>${esc(f.jawaban.length > 120 ? f.jawaban.slice(0, 120) + "..." : f.jawaban)}</small></td>
    <td><button class="promo-status-toggle status ${+f.aktif ? "active" : "off"}" onclick="toggleFaq(${f.id})">${+f.aktif ? "Aktif" : "Nonaktif"}</button></td>
    <td><div class="action-group"><button class="table-btn" onclick="editFaq(${f.id})">Ubah</button><button class="table-btn" onclick="hapusFaq(${f.id})">Hapus</button></div></td></tr>`).join("")
    || '<tr><td colspan="4">Belum ada FAQ.</td></tr>';
}
function formFaq(f = {}) {
  const next = kontenCache && kontenCache.faq.length ? Math.max(...kontenCache.faq.map(x => +x.urutan)) + 1 : 1;
  return `<div class="form-group"><label>PERTANYAAN</label><input id="fqTanya" value="${esc(f.pertanyaan || "")}"></div>
  <div class="form-group"><label>JAWABAN</label><textarea id="fqJawab" rows="4">${esc(f.jawaban || "")}</textarea></div>
  <div class="form-row"><div class="form-group"><label>URUTAN</label><input id="fqUrutan" type="number" min="0" value="${f.urutan ?? next}"></div>
  <div class="form-group check"><label style="margin-top:22px"><input id="fqAktif" type="checkbox" ${f.aktif == 0 ? "" : "checked"}> Tampilkan di halaman FAQ</label></div></div>`;
}
function tambahFaq() { showModal("Tambah FAQ", formFaq(), async () => { await api("faq_save", { pertanyaan: $("fqTanya").value, jawaban: $("fqJawab").value, urutan: +$("fqUrutan").value, aktif: $("fqAktif").checked ? 1 : 0 }); await muatFaq(); toast("FAQ ditambahkan."); }); }
function editFaq(id) { const f = kontenCache.faq.find(x => x.id == id); showModal("Ubah FAQ", formFaq(f), async () => { await api("faq_save", { id, pertanyaan: $("fqTanya").value, jawaban: $("fqJawab").value, urutan: +$("fqUrutan").value, aktif: $("fqAktif").checked ? 1 : 0 }); await muatFaq(); toast("FAQ diperbarui."); }); }
async function hapusFaq(id) { if (!confirm("Hapus FAQ ini?")) return; try { await api("faq_delete", { id }); await muatFaq(); toast("FAQ dihapus."); } catch (e) { toast(e.message, true); } }
async function toggleFaq(id) { const f = kontenCache.faq.find(x => x.id == id); try { await api("faq_save", { id, pertanyaan: f.pertanyaan, jawaban: f.jawaban, urutan: f.urutan, aktif: +f.aktif ? 0 : 1 }); await muatFaq(); } catch (e) { toast(e.message, true); } }
async function muatFaq() { kontenCache.faq = await api("faq_list"); renderFaqTable(kontenCache.faq); }

function renderGaleriGrid(rows) {
  $("galeriGrid").innerHTML = rows.map(g => `<div class="konten-galeri-item">
    <img src="${esc(g.gambar)}" alt="" loading="lazy">
    <div class="konten-galeri-info"><b>${esc(g.judul)}</b><small>Urutan ${g.urutan}${+g.lebar ? " · Slot lebar" : ""}</small>
    <span><button class="promo-status-toggle status ${+g.aktif ? "active" : "off"}" onclick="toggleGaleri(${g.id})">${+g.aktif ? "Aktif" : "Nonaktif"}</button></span></div>
    <div class="action-group"><button class="table-btn" onclick="editGaleri(${g.id})">Ubah</button><button class="table-btn" onclick="hapusGaleri(${g.id})">Hapus</button></div>
  </div>`).join("") || '<p class="f-note">Belum ada foto galeri.</p>';
}
function formGaleri(g = {}) {
  const next = kontenCache && kontenCache.galeri.length ? Math.max(...kontenCache.galeri.map(x => +x.urutan)) + 1 : 1;
  return `<div class="form-group"><label>FOTO</label><img class="preview" id="glPreview" src="${esc(g.gambar || "img/menu/default.svg")}" alt=""><input id="glGambar" type="file" accept="image/jpeg,image/png,image/webp"><div class="hint">JPG / PNG / WEBP, maks. 2 MB. Kosongkan jika tidak ingin mengganti foto.</div></div>
  <div class="form-group"><label>CAPTION</label><input id="glJudul" value="${esc(g.judul || "")}"></div>
  <div class="form-row"><div class="form-group"><label>URUTAN</label><input id="glUrutan" type="number" min="0" value="${g.urutan ?? next}"></div>
  <div class="form-group check"><label style="margin-top:22px"><input id="glLebar" type="checkbox" ${+g.lebar ? "checked" : ""}> Slot lebar (foto landscape)</label></div></div>
  <div class="form-group check"><label><input id="glAktif" type="checkbox" ${g.aktif == 0 ? "" : "checked"}> Tampilkan di galeri</label></div>`;
}
function bindGaleriPreview() { const inp = $("glGambar"); if (inp) inp.onchange = e => { const f = e.target.files[0]; if (f) $("glPreview").src = URL.createObjectURL(f); }; }
function galeriFormData(id) {
  const fd = new FormData();
  if (id) fd.append("id", id);
  fd.append("judul", $("glJudul").value); fd.append("urutan", $("glUrutan").value || 0);
  fd.append("aktif", $("glAktif").checked ? 1 : 0); fd.append("lebar", $("glLebar").checked ? 1 : 0);
  if ($("glGambar").files[0]) fd.append("gambar", $("glGambar").files[0]);
  return fd;
}
function tambahGaleri() { showModal("Tambah Foto Galeri", formGaleri(), async () => { await api("galeri_save", galeriFormData(0)); await muatGaleri(); toast("Foto ditambahkan ke galeri."); }); bindGaleriPreview(); }
function editGaleri(id) { const g = kontenCache.galeri.find(x => x.id == id); showModal("Ubah Foto Galeri", formGaleri(g), async () => { await api("galeri_save", galeriFormData(id)); await muatGaleri(); toast("Foto galeri diperbarui."); }); bindGaleriPreview(); }
async function hapusGaleri(id) { if (!confirm("Hapus foto ini dari galeri?")) return; try { await api("galeri_delete", { id }); await muatGaleri(); toast("Foto dihapus dari galeri."); } catch (e) { toast(e.message, true); } }
async function toggleGaleri(id) { const g = kontenCache.galeri.find(x => x.id == id); try { await api("galeri_save", { id, judul: g.judul, urutan: g.urutan, aktif: +g.aktif ? 0 : 1, lebar: g.lebar }); await muatGaleri(); } catch (e) { toast(e.message, true); } }
async function muatGaleri() { kontenCache.galeri = await api("galeri_list"); renderGaleriGrid(kontenCache.galeri); }

/* ---------- DAFTARKAN LOADER & TOMBOL ---------- */
Object.assign(loaders, {
  stok: loadStok, kirim: loadKirim, kembali: loadKembali, keuangan: loadKeuangan,
  analitik: loadAnalitik, blog: loadBlog, testi: loadTesti, kontak: loadKontak, aturan: loadAturan,
  konten: loadKonten,
});
document.addEventListener("DOMContentLoaded", () => {
  const kd = $("keuDari"), ks = $("keuSampai");
  if (kd && ks) { const now = new Date(); kd.value = now.toISOString().slice(0, 8) + "01"; ks.value = now.toISOString().slice(0, 10); }
  $("keuTerapkan")?.addEventListener("click", () => safe(loadKeuangan)());
  $("addKeluarBtn")?.addEventListener("click", tambahKeluar);
  $("addArtikelBtn")?.addEventListener("click", tambahArtikel);
  $("addTestiBtn")?.addEventListener("click", tambahTesti);
  $("ongkirSimpan")?.addEventListener("click", simpanOngkir);
  $("logoUnggah")?.addEventListener("click", unggahLogo);
  document.querySelectorAll("[data-konten-save]").forEach(b => b.addEventListener("click", async () => { try { await simpanKonten(b.dataset.kontenSave.split(",")); } catch (e) { toast(e.message, true); } }));
  $("andalanSimpan")?.addEventListener("click", async () => { try { await simpanAndalan(); } catch (e) { toast(e.message, true); } });
  $("addFaqBtn")?.addEventListener("click", tambahFaq);
  $("addGaleriBtn")?.addEventListener("click", tambahGaleri);
  $("setSimpan")?.addEventListener("click", async () => {
    try {
      await api("pengaturan_save", { nama_kedai: $("setNama").value, alamat: $("setAlamat").value, telepon: $("setTelepon").value, email: $("setEmail").value, jam_buka: $("setJam").value, instagram: $("setIG").value });
      toast("Pengaturan website disimpan.");
    } catch (e) { toast(e.message, true); }
  });
});
