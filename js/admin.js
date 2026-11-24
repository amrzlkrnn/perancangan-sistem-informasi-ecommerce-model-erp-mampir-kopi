/* MAMPIR KOPI — Admin Panel (terhubung ke database lewat api/admin.php) */
let menus = [], categories = [], orders = [], payments = [], customers = [], promos = [], admins = [];

const $ = id => document.getElementById(id);
const money = n => "Rp " + Number(n || 0).toLocaleString("id-ID");
const esc = s => String(s ?? "").replace(/[&<>"']/g, m => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[m]));
const statusClass = s => ({Selesai:"selesai", Menunggu:"menunggu", Diproses:"proses", Dibatalkan:"cancel", Terverifikasi:"verified", "Menunggu Verifikasi":"pending", Ditolak:"cancel", Kadaluarsa:"cancel", Refund:"off", Aktif:"active", Nonaktif:"off"}[s] || "off");
const img = g => g || "img/menu/default.svg";
/* Server selalu WIB (L-m25): datetime tanpa zona diurai sebagai +07:00 dan
   diformat di zona Asia/Jakarta, bukan zona browser. */
const tgl = s => { const iso0 = String(s).replace(" ", "T"), iso = /^\d{4}-\d{2}-\d{2}$/.test(iso0) ? iso0 + "T00:00:00" : iso0, d = new Date(/[Zz]|[+-]\d{2}:?\d{2}$/.test(iso) ? iso : iso + "+07:00"), b = ["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agu","Sep","Okt","Nov","Des"];
  if (isNaN(d)) return String(s);
  const p = {};
  new Intl.DateTimeFormat("en-US", {timeZone: "Asia/Jakarta", day: "numeric", month: "numeric", year: "numeric", hour: "2-digit", minute: "2-digit", hourCycle: "h23"}).formatToParts(d).forEach(x => { p[x.type] = x.value; });
  return p.day + " " + b[+p.month - 1] + " " + p.year + ", " + p.hour + ":" + p.minute; };
const tglOnly = s => s ? tgl(s + " 00:00:00").split(",")[0] : "";

function toast(msg, err) {
  const t = document.createElement("div"); t.className = "admin-toast" + (err ? " err" : ""); t.textContent = msg;
  document.body.appendChild(t); setTimeout(() => t.remove(), 2600);
}
let _csrfToken = null;
async function csrfToken() {
  if (!_csrfToken) {
    const r = await fetch("api/auth.php?r=csrf");
    const j = await r.json();
    _csrfToken = j.data.token;
  }
  return _csrfToken;
}
async function api(r, body, params = "") {
  let res, j;
  try {
    const opt = {};
    if (body instanceof FormData) { opt.method = "POST"; opt.headers = {"X-CSRF-Token": await csrfToken()}; opt.body = body; }
    else if (body) { opt.method = "POST"; opt.headers = {"Content-Type": "application/json", "X-CSRF-Token": await csrfToken()}; opt.body = JSON.stringify(body); }
    res = await fetch("api/admin.php?r=" + r + params, opt); j = await res.json();
  } catch (e) { throw new Error("Tidak bisa terhubung ke server. Buka lewat http://localhost/mampirkopi/admin.html (Apache & MySQL aktif)."); }
  if (res.status === 401) location.href = "login.html";
  if (!j.ok) throw new Error(j.error || "Terjadi kesalahan.");
  return j.data;
}
const safe = fn => async (...a) => { try { return await fn(...a); } catch (e) { toast(e.message, true); } };

/* ---------------- DASHBOARD ---------------- */
let dash = null, chartMode = "minggu";
const jamPesanan = w => { const s = tgl(w); const iso0 = String(w).replace(" ", "T"); const iso = /^\d{4}-\d{2}-\d{2}$/.test(iso0) ? iso0 + "T00:00:00" : iso0; const d = new Date(/[Zz]|[+-]\d{2}:?\d{2}$/.test(iso) ? iso : iso + "+07:00");
  if (isNaN(d)) return s;
  const fmtHari = new Intl.DateTimeFormat("en-CA", {timeZone: "Asia/Jakarta"});
  return fmtHari.format(d) === fmtHari.format(new Date()) ? s.split(", ")[1] : s.split(", ")[0].replace(/ \d{4}$/, ""); };
const NEXT_STATUS = {Menunggu: ["Proses", "Diproses"], Diproses: ["Selesai", "Selesai"]};

function renderChart() {
  if (!dash) return;
  const bulan = chartMode === "bulan", data = bulan ? dash.bulan : dash.minggu;
  const max = Math.max(1, ...data.map(x => x.total));
  const totalSemua = data.reduce((a, x) => a + x.total, 0);
  $("weekChart").className = "bar-chart" + (bulan ? " month" : "");
  if (totalSemua <= 0) {
    /* D-m6: empty state — jangan tampilkan batang-batang nol yang tampak rusak. */
    $("weekChart").innerHTML = `<p class="chart-empty">Belum ada penjualan ${data.length} hari terakhir</p>`;
  } else {
    /* D-m6: label sumbu Y (nilai maksimum) di pojok atas grafik. */
    const rpSingkat = n => n >= 1000000 ? "Rp " + (n / 1000000).toLocaleString("id-ID", {maximumFractionDigits: 1}) + " jt" : n >= 1000 ? "Rp " + Math.round(n / 1000) + " rb" : money(n);
    $("weekChart").innerHTML = `<span class="chart-ymax">${rpSingkat(max)}</span>` + data.map((x, i) => {
      const last = i === data.length - 1, lbl = !bulan || last || i % 5 === 0 ? x.label : "";
      return `<div class="bar-col${last ? " today" : ""}" title="${tglOnly(x.tanggal)}: ${money(x.total)}"><span style="height:${Math.max(3, Math.round(x.total / max * 100))}%"></span><small>${lbl}</small></div>`;
    }).join("");
  }
  $("weekTotal").textContent = money(data.reduce((a, x) => a + x.total, 0));
  $("weekTotalLabel").textContent = "Total " + data.length + " hari terakhir";
  $("chartSub").textContent = data.length + " hari terakhir, hari ini ditandai hijau";
  document.querySelectorAll("[data-period]").forEach(b => b.classList.toggle("active", b.dataset.period === chartMode));
}
document.addEventListener("click", e => { const b = e.target.closest("[data-period]"); if (b) { chartMode = b.dataset.period; renderChart(); } });

async function loadDashboard() {
  const d = await api("dashboard"); dash = d;
  $("todayDate").textContent = new Date().toLocaleDateString("id-ID", {weekday: "long", day: "numeric", month: "long", year: "numeric"});
  $("statOrders").textContent = d.pesanan; $("statOrdersEm").textContent = d.pesanan_hari_ini + " pesanan hari ini";
  $("statRevenue").textContent = money(d.pendapatan_hari_ini);
  const sel = d.pendapatan_hari_ini - d.pendapatan_kemarin, em = $("statRevenueEm");
  em.className = sel > 0 ? "up" : sel < 0 ? "down" : "";
  em.textContent = sel > 0 ? "Naik " + money(sel) + " dari kemarin" : sel < 0 ? "Turun " + money(-sel) + " dari kemarin" : d.pendapatan_hari_ini > 0 ? "Sama dengan kemarin" : "Belum ada pesanan selesai hari ini";
  $("statCustomers").textContent = d.pelanggan;
  [["actOrders", d.menunggu_pesanan], ["actPay", d.menunggu_bayar]].forEach(([id, n]) => { $(id).textContent = n; $(id).classList.toggle("zero", n === 0); });
  $("dashboardOrders").innerHTML = d.terbaru.map(o => { const n = NEXT_STATUS[o.status];
    // L2-M4: tombol cepat hanya bila pembayaran sudah terverifikasi — kecuali
    // "Proses" untuk COD (bayar saat diantar, verifikasi setelah pengiriman).
    const boleh = n && (n[1] === "Diproses" ? (o.status_bayar === "Terverifikasi" || o.metode === "COD") : o.status_bayar === "Terverifikasi");
    return `<div class="mini-order"><span class="order-id">${esc(o.id)}</span><div class="mini-main"><p>${esc(o.customer)}</p><small>${esc(o.items)}</small><small class="mini-kode">${esc(o.id)} · ${money(o.total)}</small></div><div class="mini-meta"><b>${money(o.total)}</b><small>${jamPesanan(o.waktu)}</small></div><span class="status ${statusClass(o.status)}">${o.status}</span>${boleh ? `<button class="quick-btn" onclick="changeOrder(${o.pid},'${n[1]}')">${n[0]}</button>` : `<span></span>`}</div>`; }).join("") || "<p style='font-size:12px;color:#77776d'>Belum ada pesanan.</p>";
  const topMax = Math.max(1, ...d.terlaris.map(x => +x.terjual));
  $("topMenus").innerHTML = d.terlaris.map((x, i) => `<div class="top-item"><span class="top-rank">${i + 1}</span><div class="top-main"><p>${esc(x.nama)}</p><div class="top-track"><i style="width:${Math.round(x.terjual / topMax * 100)}%"></i></div></div><b>${x.terjual} terjual</b></div>`).join("") || "<p class='empty-note'>Belum ada pesanan selesai.</p>";
  $("lowStock").innerHTML = d.stok_menipis.map(x => `<div class="stock-item"><p>${esc(x.nama)}</p><span class="status ${+x.stok === 0 ? "cancel" : "pending"}">${+x.stok === 0 ? "Habis" : "Sisa " + x.stok}</span></div>`).join("") || "<p class='empty-note'>Semua stok menu masih aman.</p>";
  renderChart();
  // CRM digabung ke Dashboard (22 Nov, Amar): muat ringkasan pelanggan,
  // leads, dan interaksi terbaru di halaman yang sama. loadCrm tinggal di
  // admin-fitur.js; guard typeof supaya dashboard tetap jalan bila berkas
  // itu gagal dimuat.
  if (typeof loadCrm === "function") safe(loadCrm)();
}

/* ---------------- MENU ---------------- */
function renderMenu() {
  const q = ($("menuSearch")?.value || "").toLowerCase(), cat = $("menuCategoryFilter")?.value || "Semua";
  const rows = menus.filter(m => m.nama.toLowerCase().includes(q) && (cat === "Semua" || m.kategori === cat)).map(m => `
    <tr><td><div class="menu-name"><span class="menu-thumb"><img src="${esc(img(m.gambar))}" alt=""></span><b>${esc(m.nama)}</b></div></td><td>${esc(m.kategori)}</td>
    <td class="price-cell">${money(m.harga)}${m.harga_regular > 0 ? `<br><small style="color:#999;font-weight:400">Regular ${money(m.harga_regular)}</small>` : ""}</td>
    <td>${m.stok}</td>
    <td><span class="status ${m.aktif == 1 ? "active" : "off"}">${m.aktif == 1 ? "Tersedia" : "Nonaktif"}</span></td>
    <td><div class="action-group"><button class="table-btn" onclick="editMenu(${m.id})">Edit</button><button class="table-btn delete" onclick="deleteMenu(${m.id})">Hapus</button></div></td></tr>`).join("");
  $("menuTable").innerHTML = rows || `<tr><td colspan="6">Menu tidak ditemukan.</td></tr>`;
}
async function loadMenu() { [menus, categories] = await Promise.all([api("menu"), api("kategori")]); fillCategoryFilter(); renderMenu(); renderCategories(); }
function fillCategoryFilter() {
  const sel = $("menuCategoryFilter"), cur = sel.value;
  sel.innerHTML = `<option value="Semua">Semua kategori</option>` + categories.map(c => `<option value="${esc(c.nama)}">${esc(c.nama)}</option>`).join("");
  sel.value = [...sel.options].some(o => o.value === cur) ? cur : "Semua";
}
function menuForm(m = {}) {
  const tipeOpt = (v, t) => `<option value="${v}" ${m.tipe === v ? "selected" : ""}>${t}</option>`;
  return `
  <div class="form-group"><label>FOTO MENU</label><img class="preview" id="fPreview" src="${esc(img(m.gambar))}" alt=""><input id="fGambar" type="file" accept="image/jpeg,image/png,image/webp"><div class="hint">JPG / PNG / WEBP, maks. 2 MB. Kosongkan jika tidak ingin mengganti.</div></div>
  <div class="form-group"><label>NAMA MENU</label><input id="fName" value="${esc(m.nama || "")}" placeholder="Contoh: Hazelnut Latte"></div>
  <div class="form-row"><div class="form-group"><label>KATEGORI</label><select id="fCat">${categories.map(c => `<option value="${c.id}" ${m.id_kategori == c.id ? "selected" : ""}>${esc(c.nama)}</option>`).join("")}</select></div>
  <div class="form-group"><label>JENIS</label><select id="fTipe">${tipeOpt("es", "Minuman dingin (ada pilihan es)")}${tipeOpt("panas", "Minuman panas")}${tipeOpt("makanan", "Makanan")}</select></div></div>
  <div class="form-row"><div class="form-group"><label>HARGA (Small / standar)</label><input id="fPrice" type="number" min="0" value="${m.harga ?? ""}" placeholder="15000"></div>
  <div class="form-group"><label>HARGA REGULAR</label><input id="fReg" type="number" min="0" value="${m.harga_regular ?? 0}"><div class="hint">0 = tanpa pilihan ukuran</div></div></div>
  <div class="form-row"><div class="form-group"><label>STOK</label><input id="fStok" type="number" min="0" value="${m.stok ?? 100}"></div>
  <div class="form-group check"><label style="margin-top:22px"><input id="fAktif" type="checkbox" ${m.aktif == 0 ? "" : "checked"}> Tampilkan di katalog</label></div></div>
  <div class="form-group"><label>DESKRIPSI</label><textarea id="fDesc" rows="3">${esc(m.deskripsi || "")}</textarea></div>`;
}
function bindPreview() { $("fGambar").onchange = e => { const f = e.target.files[0]; if (f) $("fPreview").src = URL.createObjectURL(f); }; }
function menuFormData(id) {
  const fd = new FormData();
  if (id) fd.append("id", id);
  fd.append("nama", $("fName").value); fd.append("id_kategori", $("fCat").value); fd.append("tipe", $("fTipe").value);
  fd.append("harga", $("fPrice").value || 0); fd.append("harga_regular", $("fReg").value || 0); fd.append("stok", $("fStok").value || 0);
  fd.append("aktif", $("fAktif").checked ? 1 : 0); fd.append("deskripsi", $("fDesc").value);
  if ($("fGambar").files[0]) fd.append("gambar", $("fGambar").files[0]);
  return fd;
}
function addMenu() {
  if (!categories.length) return toast("Buat kategori terlebih dahulu.", true);
  showModal("Tambah Menu", menuForm(), async () => { await api("menu_save", menuFormData(0)); await loadMenu(); toast("Menu ditambahkan."); }); bindPreview();
}
function editMenu(id) {
  const m = menus.find(x => x.id == id);
  showModal("Edit Menu", menuForm(m), async () => { await api("menu_save", menuFormData(id)); await loadMenu(); toast("Menu diperbarui."); }); bindPreview();
}
const deleteMenu = safe(async id => { if (!confirm("Hapus menu ini?")) return; await api("menu_delete", {id}); await loadMenu(); toast("Menu dihapus."); });

/* ---------------- KATEGORI ---------------- */
const categoryIcons = {
  signature: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9h13v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V9Z"/><path d="M17 10h1a3 3 0 0 1 0 6h-1M8 5c-1-1 1-2 0-3M12 5c-1-1 1-2 0-3"/></svg>',
  "non-coffee": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8h11l-1 12H7L6 8Z"/><path d="M5 8h13M9 5V3M13 5V3M17 11h2a2 2 0 0 1 0 4h-2"/></svg>',
  "iced-coffee": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8h12l-1.5 13h-9L6 8Z"/><path d="M4 8h16M10 4l4 4M15 3l-3 5"/></svg>',
  "manual-brew": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 8h12l-2 9H7L5 8Z"/><path d="M3 8h16M8 5h6M10 3h2M11 17v3M7 21h8"/></svg>',
  hot: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14l-1 7H6l-1-7Z"/><path d="M8 9c-1-1 1-2 0-3M12 9c-1-1 1-2 0-3M16 9c-1-1 1-2 0-3"/></svg>',
};
const fallbackCategoryIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>';
function renderCategories() {
  $("categoryGrid").innerHTML = categories.map(c => `<div class="category-card"><div class="category-icon">${c.gambar ? `<img src="${esc(c.gambar)}" alt="">` : (categoryIcons[c.slug] || fallbackCategoryIcon)}</div><h3>${esc(c.nama)}</h3><p>${c.jumlah} menu</p><div class="card-actions"><span></span><div class="action-group"><button class="table-btn" onclick="editCategory(${c.id})">Edit</button><button class="table-btn delete" onclick="deleteCategory(${c.id})">Hapus</button></div></div></div>`).join("");
}
const catForm = c => `
  <div class="form-group"><label>GAMBAR KATEGORI</label><img class="preview" id="fPreview" src="${esc(c.gambar || "img/menu/default.svg")}" alt=""><input id="fGambar" type="file" accept="image/jpeg,image/png,image/webp"><div class="hint">JPG / PNG / WEBP, maks. 2 MB. Kosongkan jika tidak ingin mengganti; tanpa gambar, kategori memakai ikon bawaan.</div></div>
  <div class="form-group"><label>NAMA KATEGORI</label><input id="fName" value="${esc(c.nama || "")}" placeholder="Contoh: Dessert"></div>`;
function catFormData(id) {
  const fd = new FormData();
  if (id) fd.append("id", id);
  fd.append("nama", $("fName").value);
  if ($("fGambar").files[0]) fd.append("gambar", $("fGambar").files[0]);
  return fd;
}
function editCategory(id) { const c = categories.find(x => x.id == id); showModal("Edit Kategori", catForm(c), async () => { await api("kategori_save", catFormData(id)); await loadMenu(); toast("Kategori diperbarui."); }); bindPreview(); }
function addCategory() { showModal("Tambah Kategori", catForm({}), async () => { await api("kategori_save", catFormData(0)); await loadMenu(); toast("Kategori ditambahkan."); }); bindPreview(); }
const deleteCategory = safe(async id => { if (!confirm("Hapus kategori ini?")) return; await api("kategori_delete", {id}); await loadMenu(); toast("Kategori dihapus."); });

/* ---------------- PESANAN ---------------- */
const STATUS = ["Menunggu", "Diproses", "Selesai", "Dibatalkan"];
function renderOrders(filter = document.querySelector("#orderTabs .active")?.dataset.filter || "Semua") {
  $("orderTable").innerHTML = orders.filter(o => filter === "Semua" || o.status === filter).map(o => `<tr><td><b>${esc(o.id)}</b></td><td>${esc(o.customer)}</td><td>${esc(o.items)}</td><td class="price-cell">${money(o.total)}</td><td>${tgl(o.waktu)}</td><td><span class="status ${statusClass(o.status)}">${o.status}</span></td>
    <td><div class="action-group"><button class="table-btn" onclick="detailPesanan(${o.pid})">Detail</button><select class="admin-select" onchange="changeOrder(${o.pid},this.value)">${STATUS.map(s => `<option ${s === o.status ? "selected" : ""}>${s}</option>`).join("")}</select><button class="table-btn delete" onclick="deleteOrder(${o.pid})">Hapus</button></div></td></tr>`).join("") || `<tr><td colspan="7">Tidak ada pesanan.</td></tr>`;
}
async function loadOrders() { orders = await api("pesanan"); renderOrders(); }
/* Rubrik #26: detail pesanan lengkap di modal (isi, pelanggan, alamat/metode
   antar, pembayaran, status). */
async function detailPesanan(id) {
  try {
    const d = await api("pesanan_detail", null, "&id=" + id);
    const varian = i => [i.ukuran, i.gula, i.es].filter(Boolean).join(" · ");
    const body = `
      <div class="form-group"><label>PESANAN</label><p><b>${esc(d.kode)}</b> · ${tgl(d.tanggal)}<br><span class="status ${statusClass(d.status)}">${esc(d.status)}</span></p></div>
      <div class="form-group"><label>PELANGGAN</label><p><b>${esc(d.pelanggan)}</b> (@${esc(d.username)})<br>${esc(d.email || "-")}</p></div>
      <div class="form-group"><label>ISI PESANAN</label><table class="admin-table"><tbody>${d.items.map(i => `<tr><td>${i.jumlah}x ${esc(i.nama)}${varian(i) ? `<br><small>${esc(varian(i))}</small>` : ""}${i.catatan ? `<br><small>Catatan: ${esc(i.catatan)}</small>` : ""}</td><td class="price-cell">${money(i.subtotal)}</td></tr>`).join("")}</tbody></table></div>
      <div class="form-group"><label>RINCIAN BIAYA</label><p>Subtotal ${money(d.subtotal)}${d.diskon ? `<br>Diskon${d.kode_promo ? " (" + esc(d.kode_promo) + ")" : ""} − ${money(d.diskon)}` : ""}${d.ongkir ? `<br>Ongkir + ${money(d.ongkir)}` : ""}<br><b>Total ${money(d.total)}</b></p></div>
      <div class="form-group"><label>PENGAMBILAN</label><p>${esc(d.cara_ambil)}${d.alamat_antar ? `<br>${esc(d.alamat_antar)}` : ""}</p></div>
      <div class="form-group"><label>PEMBAYARAN</label>${d.pembayaran.length ? d.pembayaran.map(b => `<p><b>${esc(b.kode_transaksi)}</b> · ${esc(b.metode)} · ${money(b.jumlah)}<br><span class="status ${statusClass(b.status)}">${esc(b.status)}</span> <small>${tgl(b.tanggal)}</small>${b.alasan ? `<br><small>Alasan: ${esc(b.alasan)}</small>` : ""}</p>`).join("") : "<p>Belum ada pembayaran.</p>"}</div>`;
    showModal("Detail Pesanan — " + d.kode, body, async () => {});
    /* Modal detail hanya untuk dibaca: sembunyikan tombol Simpan. */
    const sb = $("saveModal"); if (sb) sb.style.display = "none";
  } catch (e) { toast(e.message, true); }
}
const changeOrder = safe(async (id, status) => { await api("pesanan_status", {id, status}); await Promise.all([loadOrders(), loadDashboard()]); toast("Status pesanan diubah ke " + status + "."); });
const deleteOrder = safe(async id => {
  const order = orders.find(o => o.pid == id);
  if (!order) throw new Error("Pesanan tidak ditemukan.");
  if (!confirm(`Hapus pesanan ${order.id}? Detail pesanan dan pembayaran terkait juga akan dihapus.`)) return;
  await api("pesanan_delete", {id});
  await Promise.all([loadOrders(), loadPayments(), loadDashboard()]);
  toast("Pesanan dan data terkait dihapus.");
});

/* ---------------- PEMBAYARAN ---------------- */
function renderPayments() {
  $("paymentTable").innerHTML = payments.map(p => `<tr><td><b>${esc(p.id)}</b></td><td>${esc(p.customer)}</td><td>${esc(p.method)}</td><td class="price-cell">${money(p.total)}</td><td><span class="status ${statusClass(p.status)}">${p.status}</span></td>
    <td><div class="action-group">${p.status === "Menunggu Verifikasi" ? `<button class="table-btn" onclick="verifyPayment(${p.pid})">Verifikasi</button><button class="table-btn delete" onclick="tolakPayment(${p.pid})">Tolak</button>` : p.status === "Terverifikasi" ? `<span style="font-size:11px;color:#888">Sudah diverifikasi</span>` : `<span style="font-size:11px;color:#888">—</span>`}<button class="table-btn delete" onclick="deletePayment(${p.pid})">Hapus</button></div></td></tr>`).join("") || `<tr><td colspan="6">Belum ada pembayaran.</td></tr>`;
}
async function loadPayments() { payments = await api("pembayaran"); renderPayments(); }
const verifyPayment = safe(async id => { await api("pembayaran_verify", {id}); await Promise.all([loadPayments(), loadOrders(), loadDashboard()]); toast("Pembayaran berhasil diverifikasi."); });
/* L-M6: tolak pembayaran 'Menunggu Verifikasi' (mis. stok habis saat verifikasi).
   Alasan opsional; penolakan tidak memotong stok. L2-M5: pesanannya ikut dibatalkan. */
function tolakPayment(id) {
  const payment = payments.find(p => p.pid == id);
  showModal("Tolak Pembayaran" + (payment ? " — " + payment.id : ""), `
    <div class="form-group"><label>ALASAN PENOLAKAN (OPSIONAL)</label><textarea id="fAlasan" rows="3" placeholder="Contoh: stok menu habis, dana akan dikembalikan"></textarea></div>
    <div class="hint">Pembayaran ditolak dan pesanannya ikut dibatalkan, tanpa memotong stok. Beri tahu pelanggan bila dana perlu dikembalikan.</div>`,
  async () => {
    await api("pembayaran_tolak", {id, alasan: $("fAlasan").value.trim()});
    await Promise.all([loadPayments(), loadOrders(), loadDashboard()]);
    toast("Pembayaran ditolak.");
  });
}
const deletePayment = safe(async id => {
  const payment = payments.find(p => p.pid == id);
  if (!payment) throw new Error("Pembayaran tidak ditemukan.");
  if (!confirm(`Hapus pembayaran ${payment.id}? Pesanan terkait tetap disimpan.`)) return;
  await api("pembayaran_delete", {id});
  await Promise.all([loadPayments(), loadDashboard()]);
  toast("Pembayaran dihapus.");
});

/* ---------------- PELANGGAN ---------------- */
async function loadCustomers() {
  customers = await api("pelanggan");
  $("customerTable").innerHTML = customers.map(c => `<tr><td><b>${esc(c.nama)}</b></td><td>${esc(c.email || "-")}</td><td>${c.pesanan}</td><td class="price-cell">${money(c.belanja)}</td><td><span class="status ${statusClass(c.status)}">${esc(c.status)}</span></td><td><div class="action-group"><button class="table-btn" onclick="detailPelanggan(${c.id})">Detail</button><button class="table-btn" onclick="editPelanggan(${c.id})">Edit</button><button class="table-btn" onclick="toggleCustomerStatus(${c.id})">${c.status === "Nonaktif" ? "Aktifkan" : "Nonaktifkan"}</button><button class="table-btn" onclick="resetCustomerPassword(${c.id})">Reset Password</button><button class="table-btn delete" onclick="deleteCustomer(${c.id})">Hapus</button></div></td></tr>`).join("") || `<tr><td colspan="6">Belum ada pelanggan.</td></tr>`;
}
/* Rubrik #21: tambah/edit pelanggan manual. Validasi mengikuti registrasi:
   username 3-30 karakter [a-z0-9_], email valid, password minimal 6 karakter. */
const pelangganForm = c => `
  <div class="form-group"><label>NAMA PELANGGAN</label><input id="fNama" value="${esc(c.nama || "")}" placeholder="Nama lengkap"></div>
  <div class="form-group"><label>USERNAME</label><input id="fUsername" value="${esc(c.username || "")}" placeholder="huruf kecil, angka, atau _ (3-30 karakter)"></div>
  <div class="form-group"><label>EMAIL</label><input id="fEmail" type="email" value="${esc(c.email || "")}" placeholder="nama@email.com"></div>
  <div class="form-group"><label>PASSWORD ${c.id ? "(KOSONGKAN BILA TIDAK DIUBAH)" : "AWAL"}</label><input id="fPassword" type="password" minlength="6" autocomplete="new-password" placeholder="Minimal 6 karakter"></div>
  ${c.id ? "" : '<div class="hint">Beri tahu pelanggan password awalnya agar bisa masuk, lalu sarankan ia menggantinya lewat Edit Profil.</div>'}`;
const pelangganData = id => ({id, nama: $("fNama").value, username: $("fUsername").value, email: $("fEmail").value, password: $("fPassword").value});
function addPelanggan() { showModal("Tambah Pelanggan", pelangganForm({}), async () => { await api("pelanggan_save", pelangganData(0)); await loadCustomers(); toast("Pelanggan ditambahkan."); }); }
function editPelanggan(id) { const c = customers.find(x => x.id == id); showModal("Edit Pelanggan", pelangganForm(c), async () => { await api("pelanggan_save", pelangganData(id)); await loadCustomers(); toast("Pelanggan diperbarui."); }); }
/* L-m20: nonaktifkan/aktifkan pelanggan (login menolak akun Nonaktif). */
const toggleCustomerStatus = safe(async id => {
  const customer = customers.find(c => c.id == id);
  if (!customer) throw new Error("Pelanggan tidak ditemukan.");
  const status = customer.status === "Nonaktif" ? "Aktif" : "Nonaktif";
  if (!confirm(status === "Nonaktif" ? `Nonaktifkan akun ${customer.nama}? Pelanggan tidak bisa masuk sampai diaktifkan lagi.` : `Aktifkan kembali akun ${customer.nama}?`)) return;
  await api("pelanggan_status", {id, status});
  await loadCustomers();
  toast("Pelanggan " + (status === "Nonaktif" ? "dinonaktifkan." : "diaktifkan."));
});
const deleteCustomer = safe(async id => {
  const customer = customers.find(c => c.id == id);
  if (!customer) throw new Error("Pelanggan tidak ditemukan.");
  if (!confirm(`Hapus pelanggan ${customer.nama}? Semua pesanan dan pembayaran pelanggan ini beserta riwayat transaksinya juga akan dihapus.`)) return;
  await api("pelanggan_delete", {id});
  await Promise.all([loadCustomers(), loadOrders(), loadPayments(), loadDashboard()]);
  toast("Pelanggan dan seluruh data transaksinya dihapus.");
});

/* ---------------- LAPORAN ---------------- */
const repParams = () => `&dari=${$("repDari").value}&sampai=${$("repSampai").value}&status=${encodeURIComponent($("repStatus").value)}`;
async function loadReport() {
  const d = await api("laporan", null, repParams());
  $("repHari").textContent = money(d.hari_ini); $("repBulan").textContent = money(d.bulan_ini); $("repSelesai").textContent = d.selesai;
  $("repPeriode").textContent = money(d.total_periode);
  $("reportTable").innerHTML = d.rows.map(o => `<tr><td>${tgl(o.tanggal_pesanan)}</td><td><b>${esc(o.kode_pesanan)}</b></td><td>${esc(o.nama_pelanggan)}</td><td>${esc(o.item)}</td><td>${esc(o.metode || "-")}</td><td class="price-cell">${money(o.total_harga)}</td><td><span class="status ${statusClass(o.status)}">${o.status}</span></td></tr>`).join("") || `<tr><td colspan="7">Tidak ada transaksi pada periode ini.</td></tr>`;
}

/* ---------------- PROMO ---------------- */
function renderPromos() {
  $("promoGrid").innerHTML = promos.map(p => `<div class="promo-card"><div class="promo-code">${esc(p.kode)}</div><h3>${esc(p.judul)}</h3><p>${esc(p.deskripsi)}</p>
    <div class="promo-value">${p.tipe === "persen" ? p.nilai + "%" : money(p.nilai)}${p.min_transaksi > 0 ? " • min. " + money(p.min_transaksi) : ""}</div>
    <p style="margin-top:4px">${p.berlaku_sampai ? "Berlaku sampai " + tglOnly(p.berlaku_sampai) : "Tanpa batas waktu"}</p>
    <div class="card-actions"><button class="status ${p.aktif == 1 ? "active" : "off"} promo-status-toggle" type="button" onclick="togglePromo(${p.id})" aria-label="${p.aktif == 1 ? "Nonaktifkan" : "Aktifkan"} promo ${esc(p.kode)}" title="Klik untuk ${p.aktif == 1 ? "menonaktifkan" : "mengaktifkan"}">${p.aktif == 1 ? "Aktif" : "Nonaktif"}</button><div class="action-group"><button class="table-btn" onclick="editPromo(${p.id})">Edit</button><button class="table-btn delete" onclick="deletePromo(${p.id})">Hapus</button></div></div></div>`).join("") || "<p style='font-size:12px;color:#999'>Belum ada promo.</p>";
}
async function loadPromos() { promos = await api("promo"); renderPromos(); }
const togglePromo = safe(async id => {
  const promo = promos.find(p => p.id == id);
  if (!promo) throw new Error("Promo tidak ditemukan.");
  const aktif = promo.aktif == 1 ? 0 : 1;
  await api("promo_toggle", {id, aktif});
  await loadPromos();
  toast(aktif ? "Promo diaktifkan." : "Promo dinonaktifkan.");
});
const promoForm = p => `
  <div class="form-row"><div class="form-group"><label>KODE VOUCHER</label><input id="fCode" value="${esc(p.kode || "")}" placeholder="NGOPI15"></div><div class="form-group"><label>NAMA PROMO</label><input id="fName" value="${esc(p.judul || "")}" placeholder="Diskon 15%"></div></div>
  <div class="form-group"><label>DESKRIPSI</label><input id="fDesc" value="${esc(p.deskripsi || "")}" placeholder="Syarat dan ketentuan promo"></div>
  <div class="form-row"><div class="form-group"><label>JENIS POTONGAN</label><select id="fTipe"><option value="persen" ${p.tipe !== "nominal" ? "selected" : ""}>Persen (%)</option><option value="nominal" ${p.tipe === "nominal" ? "selected" : ""}>Nominal (Rp)</option></select></div><div class="form-group"><label>NILAI</label><input id="fNilai" type="number" min="1" value="${p.nilai ?? ""}"></div></div>
  <div class="form-row"><div class="form-group"><label>MIN. TRANSAKSI (Rp)</label><input id="fMin" type="number" min="0" value="${p.min_transaksi ?? 0}"></div><div class="form-group"><label>BERLAKU SAMPAI</label><input id="fUntil" type="date" value="${p.berlaku_sampai || ""}"></div></div>
  <div class="form-group check"><label><input id="fAktif" type="checkbox" ${p.aktif == 0 ? "" : "checked"}> Promo aktif</label></div>`;
const promoData = id => ({id, kode: $("fCode").value, judul: $("fName").value, deskripsi: $("fDesc").value, tipe: $("fTipe").value, nilai: $("fNilai").value, min_transaksi: $("fMin").value, berlaku_sampai: $("fUntil").value, aktif: $("fAktif").checked ? 1 : 0});
function resetCustomerPassword(id) {
  const c = customers.find(x => x.id == id);
  showModal("Reset Password — " + (c ? c.nama : "Pelanggan"), `
    <div class="form-group"><label>PASSWORD BARU</label><input type="password" id="fPw1" placeholder="Minimal 6 karakter"></div>
    <div class="form-group"><label>ULANGI PASSWORD BARU</label><input type="password" id="fPw2" placeholder="Ketik ulang password baru"></div>
    <div class="hint">Password lama pelanggan langsung diganti. Beri tahu pelanggan password barunya agar bisa masuk, lalu sarankan ia menggantinya lewat Edit Profil.</div>`,
  async () => {
    const p1 = $("fPw1").value, p2 = $("fPw2").value;
    if (p1.length < 6) throw new Error("Password baru minimal 6 karakter.");
    if (p1 !== p2) throw new Error("Ulangi password baru tidak sama.");
    await api("pelanggan_reset_password", {id, password: p1});
    toast("Password " + (c ? c.nama : "pelanggan") + " sudah direset.");
  });
}
function editPromo(id) { const p = promos.find(x => x.id == id); showModal("Edit Promo", promoForm(p), async () => { await api("promo_save", promoData(id)); await loadPromos(); toast("Promo diperbarui."); }); }
function addPromo() { showModal("Buat Promo", promoForm({}), async () => { await api("promo_save", promoData(0)); await loadPromos(); toast("Promo dibuat."); }); }
const deletePromo = safe(async id => { if (!confirm("Hapus promo ini?")) return; await api("promo_delete", {id}); await loadPromos(); toast("Promo dihapus."); });

/* ---------------- ADMIN ---------------- */
function renderAdmins() {
  $("adminTable").innerHTML = admins.map(a => `<tr><td><b>${esc(a.nama)}</b></td><td>${esc(a.email)}</td><td>${esc(a.role)}</td><td><span class="status active">${esc(a.status)}</span></td><td><div class="action-group"><button class="table-btn" onclick="editAdmin(${a.id})">Edit</button><button class="table-btn delete" onclick="deleteAdmin(${a.id})">Hapus</button></div></td></tr>`).join("");
}
async function loadAdmins() { admins = await api("admins"); renderAdmins(); }
/* Rubrik #29: keputusan pemilik (22 Nov 2026) — kedai ini punya sendiri,
   hanya ada Super Admin, tanpa peran lain. Form menampilkan peran sebagai
   teks statis (bukan pemilih) supaya tidak ada yang mengira peran bisa dipilih. */
const adminForm = (a, includePassword = false) => `<div class="form-group"><label>NAMA</label><input id="fName" value="${esc(a.nama || "")}" placeholder="Nama admin"></div><div class="form-group"><label>EMAIL</label><input id="fEmail" type="email" value="${esc(a.email || "")}" placeholder="email@mampirkopi.id"></div>
  <div class="form-group"><label>ROLE</label><p><b>Super Admin</b> <small style="color:#888">(satu-satunya peran — semua admin memiliki akses penuh)</small></p></div>
  ${includePassword ? `<div class="form-group"><label>PASSWORD${a.id ? " BARU (OPSIONAL)" : ""}</label><input id="fPassword" type="password" minlength="6" autocomplete="new-password" placeholder="${a.id ? "Kosongkan jika tidak diubah" : "Minimal 6 karakter"}" ${a.id ? "" : "required"}></div>` : ""}`;
const adminData = id => ({id, nama: $("fName").value, email: $("fEmail").value, ...(($("fPassword")?.value || !id) ? {password: $("fPassword").value} : {})});
function editAdmin(id) { const a = admins.find(x => x.id == id); showModal("Edit Admin", adminForm(a, true), async () => { await api("admin_save", adminData(id)); await loadAdmins(); toast("Admin diperbarui."); }); }
function addAdmin() { showModal("Tambah Admin", adminForm({}, true), async () => { await api("admin_save", adminData(0)); await loadAdmins(); toast("Admin ditambahkan."); }); }
const deleteAdmin = safe(async id => { if (!confirm("Hapus akun admin ini?")) return; await api("admin_delete", {id}); await loadAdmins(); toast("Admin dihapus."); });

/* ---------------- MODAL & NAVIGASI ---------------- */
/* ---------------- PROFIL SAYA (22 Nov, Amar: admin bisa edit profil +
   password seperti user) — cermin initProfil() di js/shop.js. ---------- */
let admBerkas = null;
async function loadProfilAdmin() {
  let p;
  try { p = await api("profil_admin"); } catch (e) { toast(e.message, true); return; }
  const av = $("admAvatar");
  if (p.foto_ada) av.innerHTML = '<img src="api/auth.php?r=foto&v=' + (p.foto_ver || 1) + '" alt="Foto profil">';
  else av.textContent = (p.nama || '?').trim().charAt(0).toUpperCase();
  $("admNamaTampil").textContent = p.nama;
  $("admSub").textContent = '@' + p.username + (p.email ? ' • ' + p.email : '');
  $("admNama").value = p.nama;
  $("admUsername").value = p.username;
  $("admEmail").value = p.email || '';
  $("btnAdmHapusFoto").hidden = !p.foto_ada;
}
function bindProfilAdmin() {
  document.querySelectorAll("#section-profil .pw-eye").forEach(btn => {
    btn.addEventListener("click", () => {
      const inp = document.getElementById(btn.dataset.pw);
      if (!inp) return;
      const tampil = inp.type === "password";
      inp.type = tampil ? "text" : "password";
      btn.classList.toggle("on", tampil);
      btn.setAttribute("aria-label", tampil ? "Sembunyikan password" : "Tampilkan password");
    });
  });
  $("admNama").addEventListener("input", e => { $("admNamaTampil").textContent = e.target.value || "—"; });
  $("admFile").addEventListener("change", e => {
    admBerkas = e.target.files[0] || null;
    if (!admBerkas) return;
    if (admBerkas.size > 2 * 1024 * 1024) { toast("Ukuran foto maksimal 2MB", true); e.target.value = ""; admBerkas = null; return; }
    $("admAvatar").innerHTML = '<img src="' + URL.createObjectURL(admBerkas) + '" alt="Pratinjau foto">';
  });
  $("btnAdmHapusFoto").addEventListener("click", async () => {
    if (!confirm("Hapus foto profil? Avatar kembali ke huruf inisial nama.")) return;
    try {
      await api("profil_admin_hapus_foto", {});
      admBerkas = null; $("admFile").value = "";
      await loadProfilAdmin();
      await segarkanTopbarAdmin();
      toast("Foto profil dihapus");
    } catch (e) { toast(e.message, true); }
  });
  $("btnAdmSimpan").addEventListener("click", async () => {
    const b = $("btnAdmSimpan"); b.disabled = true; b.textContent = "Menyimpan...";
    try {
      const pwB = $("admPwBaru").value, pwU = $("admPwUlang").value;
      if (pwB !== pwU) { toast("Ulangi password baru tidak sama", true); b.disabled = false; b.textContent = "Simpan Perubahan"; return; }
      const fd = new FormData();
      fd.append("nama", $("admNama").value.trim());
      fd.append("email", $("admEmail").value.trim());
      fd.append("password_lama", $("admPwLama").value);
      fd.append("password_baru", pwB);
      fd.append("password_ulang", pwU);
      if (admBerkas) fd.append("foto", admBerkas);
      await api("profil_admin_update", fd);
      toast("Profil tersimpan");
      admBerkas = null; $("admFile").value = "";
      $("admPwLama").value = ""; $("admPwBaru").value = ""; $("admPwUlang").value = "";
      await loadProfilAdmin();
      await segarkanTopbarAdmin();
      b.disabled = false; b.textContent = "Simpan Perubahan";
    } catch (e) { toast(e.message, true); b.disabled = false; b.textContent = "Simpan Perubahan"; }
  });
}
/* Menu akun topbar: foto + nama + dropdown (Edit Profil / Keluar),
   cermin .nav-user pelanggan. */
function isiTopbarAdmin(me) {
  const box = $("adminProfile");
  if (!box || !me) return;
  const av = box.querySelector(".avatar");
  if (me.foto_ada) av.innerHTML = '<img src="api/auth.php?r=foto&v=' + (me.foto_ver || 1) + '" alt="">';
  else av.textContent = (me.nama || '?').trim().charAt(0).toUpperCase();
  box.querySelector("b").textContent = me.nama;
  box.querySelector("small").textContent = me.role === "admin" ? "Super Admin" : me.role;
}
async function segarkanTopbarAdmin() {
  try { const r = await fetch("api/auth.php?r=me"); const j = await r.json(); if (j.ok) isiTopbarAdmin(j.data); } catch (e) { /* topbar tetap apa adanya */ }
}
async function keluarAdmin() {
  if (!confirm("Keluar dari panel admin?")) return;
  try { await fetch("api/auth.php?r=logout", {method: "POST", headers: {"Content-Type": "application/json", "X-CSRF-Token": await csrfToken()}, body: "{}"}); } catch (e) { /* sesi sudah berakhir di server */ }
  if (window.hapusTemaLokal) window.hapusTemaLokal(); /* cache tema ikut dihapus */
  location.href = "tamu.html";
}
async function initAdminProfile() {
  const box = $("adminProfile");
  if (!box) return;
  let me = null;
  try { const r = await fetch("api/auth.php?r=me"); const j = await r.json(); me = j.ok ? j.data : null; } catch (e) { return; }
  if (!me) return;
  isiTopbarAdmin(me);
  const menu = document.createElement("div");
  menu.className = "user-menu"; menu.hidden = true;
  menu.innerHTML = '<a href="#" data-profil-link>Edit Profil</a><button type="button" class="user-logout">Keluar</button>';
  box.appendChild(menu);
  box.addEventListener("click", e => {
    if (e.target.closest(".user-menu a") || e.target.closest(".user-logout")) return;
    menu.hidden = !menu.hidden;
  });
  menu.querySelector("[data-profil-link]").addEventListener("click", e => { e.preventDefault(); menu.hidden = true; goSection("profil"); });
  menu.querySelector(".user-logout").addEventListener("click", keluarAdmin);
  document.addEventListener("click", e => { if (!box.contains(e.target)) menu.hidden = true; });
}

function showModal(title, body, onSave) {
  $("modalTitle").textContent = title;
  $("modalBody").innerHTML = body + `<div class="modal-actions"><button class="btn btn-ghost" onclick="closeModal()">Batal</button><button class="btn btn-primary" id="saveModal">Simpan</button></div>`;
  $("modal").classList.add("show");
  $("saveModal").onclick = async () => {
    const b = $("saveModal"); b.disabled = true; b.textContent = "Menyimpan...";
    try { await onSave(); closeModal(); } catch (e) { toast(e.message, true); b.disabled = false; b.textContent = "Simpan"; }
  };
}
function closeModal() { $("modal").classList.remove("show"); }

const loaders = {dashboard: loadDashboard, profil: loadProfilAdmin, menu: loadMenu, kategori: loadMenu, pesanan: loadOrders, pembayaran: loadPayments, pelanggan: loadCustomers, laporan: loadReport, promo: loadPromos, admin: loadAdmins};
function goSection(section) {
  /* D-M4: setelah pindah modul di HP, lipat lagi navigasi agar konten terlihat. */
  const sb = document.querySelector(".admin-sidebar");
  if (sb) { sb.classList.remove("nav-open"); const nt = $("navToggle"); if (nt) { nt.setAttribute("aria-expanded", "false"); nt.setAttribute("aria-label", "Buka menu navigasi"); } }
  document.querySelectorAll(".admin-nav-item").forEach(b => b.classList.toggle("active", b.dataset.section === section));
  document.querySelectorAll(".admin-section").forEach(s => s.classList.toggle("active", s.id === "section-" + section));
  const btn = document.querySelector(`[data-section="${section}"]`);
  $("pageTitle").textContent = btn?.querySelector("span")?.textContent || "Dashboard";
  window.scrollTo(0, 0);
  safe(loaders[section])();
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".admin-nav-item").forEach(btn => btn.addEventListener("click", () => goSection(btn.dataset.section)));
  document.querySelectorAll("[data-goto]").forEach(b => b.addEventListener("click", () => goSection(b.dataset.goto)));
  $("menuSearch").addEventListener("input", renderMenu);
  $("menuCategoryFilter").addEventListener("change", renderMenu);
  $("addMenuBtn").onclick = addMenu; $("addCategoryBtn").onclick = addCategory; $("addPromoBtn").onclick = addPromo; $("addAdminBtn").onclick = addAdmin;
  $("addPelangganBtn").onclick = addPelanggan;
  $("modalClose").onclick = closeModal; $("modal").addEventListener("click", e => { if (e.target.id === "modal") closeModal(); });
  $("orderTabs").addEventListener("click", e => { if (e.target.tagName === "BUTTON") { document.querySelectorAll("#orderTabs button").forEach(b => b.classList.remove("active")); e.target.classList.add("active"); renderOrders(e.target.dataset.filter); } });
  const today = new Date(), iso = d => d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
  $("repDari").value = iso(new Date(today.getTime() - 30 * 86400000)); $("repSampai").value = iso(today);
  ["repDari", "repSampai", "repStatus"].forEach(id => $(id).addEventListener("change", safe(loadReport)));
  $("printReport").onclick = () => window.print();
  $("exportExcel").onclick = () => { window.location.href = "api/export.php?" + repParams().slice(1); };
  $("logoutBtn").onclick = keluarAdmin;
  bindProfilAdmin();
  initAdminProfile();
  /* D-M4: hamburger — navigasi admin dilipat di HP, dibuka lewat tombol ini. */
  const navToggle = $("navToggle"), sidebar = document.querySelector(".admin-sidebar");
  if (navToggle && sidebar) navToggle.addEventListener("click", () => {
    const buka = sidebar.classList.toggle("nav-open");
    navToggle.setAttribute("aria-expanded", String(buka));
    navToggle.setAttribute("aria-label", buka ? "Tutup menu navigasi" : "Buka menu navigasi");
  });
  safe(loadDashboard)();
});
