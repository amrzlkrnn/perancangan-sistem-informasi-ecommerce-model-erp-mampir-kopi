/* MAMPIR KOPI — logika sisi pelanggan: katalog, keranjang, pembayaran, riwayat. Dimuat setelah app.js */
/* Nama tampil menu: satu-satunya item Manual Brew bernama "Manual Brew" di
   DB — sama dengan nama kategorinya. Tampilkan sebagai V60 (minuman itu
   sendiri), tanpa mengubah database. */
const namaTampil = n => n === 'Manual Brew' ? 'V60' : n;
const MK = {
  money: n => 'Rp ' + Number(n || 0).toLocaleString('id-ID'),
  esc: s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])),
  img: g => g || 'img/menu/default.svg',
  _csrf: null,
  async csrf() {
    if (!MK._csrf) {
      const r = await fetch('api/auth.php?r=csrf');
      const j = await r.json();
      MK._csrf = j.data.token;
    }
    return MK._csrf;
  },
  async api(path, body) {
    let res, j;
    try {
      res = await fetch('api/' + path, body ? {method:'POST', headers:{'Content-Type':'application/json', 'X-CSRF-Token': await MK.csrf()}, body: JSON.stringify(body)} : {});
      j = await res.json();
    } catch (e) {
      throw new Error('Tidak bisa terhubung ke server. Buka website lewat http://localhost/mampirkopi/ (Apache & MySQL aktif), bukan dengan klik dua kali file HTML.');
    }
    if (res.status === 401) location.href = 'login.html';
    if (!j.ok) throw new Error(j.error || 'Terjadi kesalahan.');
    return j.data;
  },
  async apiForm(path, fd) {
    let res, j;
    try {
      res = await fetch('api/' + path, {method:'POST', headers:{'X-CSRF-Token': await MK.csrf()}, body: fd});
      j = await res.json();
    } catch (e) {
      throw new Error('Tidak bisa terhubung ke server.');
    }
    if (!j.ok) throw new Error(j.error || 'Terjadi kesalahan.');
    return j.data;
  },
  toast(msg, err) {
    let w = document.querySelector('.mk-toast-wrap');
    if (!w) { w = document.createElement('div'); w.className = 'mk-toast-wrap'; document.body.appendChild(w); }
    const t = document.createElement('div'); t.className = 'mk-toast' + (err ? ' err' : ''); t.textContent = msg; w.appendChild(t);
    setTimeout(() => t.remove(), 2600);
  },
  modal(html) {
    const m = document.createElement('div'); m.className = 'mk-modal';
    m.innerHTML = '<div class="mk-box"><button class="mk-close" aria-label="Tutup">×</button>' + html + '</div>';
    const close = () => { m.remove(); document.removeEventListener('keydown', esc); };
    const esc = e => { if (e.key === 'Escape') close(); };
    m.addEventListener('click', e => { if (e.target === m || e.target.closest('.mk-close')) close(); });
    document.addEventListener('keydown', esc); document.body.appendChild(m);
    m.close = close; return m;
  },
  strukHTML(d) {
    const pay = d.pembayaran || null;
    // L2-m6: tampilkan baris Ongkir bila ada (tanpanya Subtotal tidak nyambung ke TOTAL).
    // L2-m7: tanpa baris pembayaran, jangan klaim 'QRIS' — tampilkan 'Belum ada pembayaran'.
    const baris = (k, v) => '<div class="struk-row"><span>' + k + '</span><span>' + v + '</span></div>';
    return `<div class="struk">
      <p class="struk-brand">MAMPIR KOPI</p>
      <p class="struk-sub">Kedai Kopi · Kebon Jeruk, Jakarta Barat<br>@mampir.kopi_</p>
      <div class="struk-dash"></div>
      ${baris('No. Pesanan', '<b>' + MK.esc(d.kode) + '</b>')}
      ${baris('Tanggal', MK.esc(MK.tgl(d.tanggal)) + ' WIB')}
      ${d.pelanggan ? baris('Pelanggan', MK.esc(d.pelanggan)) : ''}
      ${baris('Status Pesanan', MK.esc(d.status))}
      <div class="struk-dash"></div>
      ${d.items.map(i => `<div class="struk-item"><div class="struk-row"><span>${MK.esc(i.nama)}</span><span>${MK.money(i.subtotal)}</span></div>
        <p class="struk-var">${i.jumlah} × ${MK.money(i.harga)}${MK.varian(i) ? ' · ' + MK.esc(MK.varian(i)) : ''}${i.catatan ? '<br>Catatan: &ldquo;' + MK.esc(i.catatan) + '&rdquo;' : ''}</p></div>`).join('')}
      <div class="struk-dash"></div>
      ${baris('Subtotal', MK.money(d.subtotal))}
      ${+d.diskon > 0 ? baris('Diskon (' + MK.esc(d.kode_promo || 'Promo') + ')', '− ' + MK.money(d.diskon)) : ''}
      ${+d.ongkir > 0 ? baris('Ongkir' + (d.cara_ambil ? ' (' + MK.esc(d.cara_ambil) + ')' : ''), MK.money(d.ongkir)) : ''}
      <div class="struk-row struk-total"><span>TOTAL</span><span>${MK.money(d.total)}</span></div>
      <div class="struk-dash"></div>
      ${pay ? baris('Pembayaran', MK.esc(pay.metode)) + baris('Kode Transaksi', MK.esc(pay.kode_transaksi)) + baris('Status Bayar', MK.esc(pay.status)) : baris('Pembayaran', 'Belum ada pembayaran')}
      <div class="struk-dash"></div>
      <p class="struk-thanks">Terima kasih sudah mampir!<br>Simpan struk ini sebagai bukti pembelian.</p>
    </div>`;
  },
  tampilStruk(d) {
    const m = MK.modal('<h3 style="text-align:center">Struk Pembelian</h3>' + MK.strukHTML(d) +
      '<div class="struk-actions"><button class="btn btn-primary" id="btnCetakStruk">🖨️ Cetak / Simpan PDF</button><button class="btn btn-ghost" id="btnTutupStruk">Tutup</button></div>');
    m.querySelector('#btnTutupStruk').onclick = () => m.close();
    m.querySelector('#btnCetakStruk').onclick = () => {
      let area = document.getElementById('mkPrint');
      if (!area) { area = document.createElement('div'); area.id = 'mkPrint'; document.body.appendChild(area); }
      area.innerHTML = MK.strukHTML(d);
      window.print();
    };
    return m;
  },
  tgl(s) { // "2026-09-22 14:32:00" -> "22 Sep 2026, 14:32" — server selalu WIB (L-m25)
    const b = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    const iso0 = String(s).replace(' ', 'T');
    const iso = /^\d{4}-\d{2}-\d{2}$/.test(iso0) ? iso0 + 'T00:00:00' : iso0;
    const d = new Date(/[Zz]|[+-]\d{2}:?\d{2}$/.test(iso) ? iso : iso + '+07:00');
    if (isNaN(d)) return String(s);
    const p = {};
    new Intl.DateTimeFormat('en-US', {timeZone: 'Asia/Jakarta', day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23'}).formatToParts(d).forEach(x => { p[x.type] = x.value; });
    return p.day + ' ' + b[+p.month - 1] + ' ' + p.year + ', ' + p.hour + ':' + p.minute;
  },
  varian: it => [it.ukuran, it.gula, it.es].filter(Boolean).join(' • '),
  tagClass: s => ({Selesai:'selesai', Diproses:'diproses', Menunggu:'menunggu', Dibatalkan:'dibatalkan', Terverifikasi:'selesai', 'Menunggu Verifikasi':'menunggu', Ditolak:'dibatalkan', Kadaluarsa:'dibatalkan', Refund:'diproses'}[s] || 'menunggu'),

  /* ---------- keranjang (disimpan di localStorage) ---------- */
  cart: {
    get() { try { return JSON.parse(localStorage.getItem('mk_cart') || '[]'); } catch (e) { return []; } },
    set(c) { try { localStorage.setItem('mk_cart', JSON.stringify(c)); } catch (e) {} MK.badge(); },
    key: i => [i.id, i.ukuran, i.gula, i.es, i.catatan].join('|'),
    add(item) {
      const c = this.get(); item.key = this.key(item);
      item.jumlah = Math.max(1, Math.min(99, item.jumlah || 1)); // L-m9: clamp item baru juga
      const ada = c.find(x => x.key === item.key);
      if (ada) ada.jumlah = Math.min(99, ada.jumlah + item.jumlah); else c.push(item);
      this.set(c);
    },
    count() { return this.get().reduce((a, i) => a + i.jumlah, 0); },
    subtotal() { return this.get().reduce((a, i) => a + i.harga * i.jumlah, 0); },
    clear() { this.set([]); try { localStorage.removeItem('mk_promo'); } catch (e) {} },
  },
  badge() {
    const n = this.cart.count();
    const a = document.querySelector('.nav-links a[href="keranjang.html"]');
    if (a) {
      a.querySelector('.cart-badge')?.remove();
      if (n > 0) a.insertAdjacentHTML('beforeend', '<span class="cart-badge">' + n + '</span>');
    }
    const tb = document.querySelector('.tabbar a[href="keranjang.html"] .tab-badge');
    if (tb) { tb.textContent = n > 9 ? '9+' : String(n); tb.hidden = n <= 0; }
  },
  /* hitung diskon voucher yang tersimpan; mengembalikan {kode, diskon, pesan} */
  async promo(subtotal) {
    const kode = localStorage.getItem('mk_promo');
    if (!kode || subtotal <= 0) return {kode: null, diskon: 0};
    try { const p = await this.api('shop.php?r=promo&kode=' + encodeURIComponent(kode) + '&subtotal=' + subtotal); return {kode: p.kode, diskon: p.diskon}; }
    catch (e) { localStorage.removeItem('mk_promo'); return {kode: null, diskon: 0, pesan: e.message}; }
  },
  /* L-m10: muat ulang harga dari katalog terbaru dan hitung ulang keranjang
     (jangan pakai harga lama dari localStorage). Mengembalikan true bila ada
     harga yang berubah. Katalog gagal dimuat = harga tersimpan tetap dipakai. */
  async segarkanHarga() {
    let berubah = false;
    try {
      const kat = await this.api('shop.php?r=menu');
      const peta = {};
      kat.forEach(k => (k.menu || []).forEach(m => { peta[m.id] = m; }));
      let c = this.cart.get();
      // L2-m8: item yang sudah tidak ada di katalog (dinonaktifkan/dihapus
      // admin) langsung dikeluarkan dari keranjang dengan pemberitahuan —
      // jangan didiamkan sampai checkout gagal di langkah terakhir.
      const hilang = c.filter(i => !peta[i.id]);
      if (hilang.length) {
        c = c.filter(i => peta[i.id]);
        this.cart.set(c);
        hilang.forEach(i => this.toast((i.nama || 'Menu') + ' sudah tidak tersedia, dihapus dari keranjang', true));
      }
      c.forEach(i => {
        const m = peta[i.id];
        if (!m) return;
        const baru = (i.ukuran === 'Regular' && +m.harga_regular > 0) ? +m.harga_regular : +m.harga;
        if (+i.harga !== baru) { i.harga = baru; berubah = true; }
      });
      if (berubah) this.cart.set(c);
    } catch (e) { /* katalog gagal dimuat: pakai harga tersimpan */ }
    return berubah;
  },
};

document.addEventListener('DOMContentLoaded', () => {
  MK.badge();
  fetch('api/shop.php?r=kunjung').catch(() => {});
  const $ = id => document.getElementById(id);
  const backToTop = $('backToTop');
  if (backToTop) {
    const updateBackToTop = () => { backToTop.hidden = window.scrollY < 320; };
    window.addEventListener('scroll', updateBackToTop, {passive: true});
    backToTop.addEventListener('click', () => window.scrollTo({top: 0, behavior: 'smooth'}));
    updateBackToTop();
  }
  if ($('menuContent')) initMenu();
  if ($('optUkuran')) initCustomize();
  if ($('cartList')) initCart();
  if ($('payTotal')) initPay();
  if ($('riwayatBody')) initRiwayat();
  if ($('profUsername')) initProfil();
  if ($('wishList')) initWishlist();
  if ($('statJumlah')) initHome();

  /* ================= BERANDA ================= */
  async function initHome() {
    try {
      const s = await MK.api('shop.php?r=stats');
      $('statJumlah').textContent = s.jumlah; $('statTotal').textContent = MK.money(s.total); $('statAktif').textContent = s.aktif;
      const h = document.querySelector('.hero h1'); if (h && !window.__kontenSapa) h.textContent = 'Halo, ' + s.nama.split(' ')[0] + '!'; // CMS konten: bila js/konten.js sudah menerapkan template sapaan admin, jangan timpa
    } catch (e) { MK.toast(e.message, true); }
    try { // gambar kategori di Katalog Menu
      const kat = await MK.api('shop.php?r=menu');
      document.querySelectorAll('.favorite-card').forEach(card => {
        const slug = (card.getAttribute('href') || '').split('#')[1];
        const k = kat.find(x => x.slug === slug), th = card.querySelector('.product-thumb');
        if (k && th) { th.innerHTML = '<img class="product-img" alt="" src="' + MK.esc(MK.img(k.menu[0].gambar)) + '">'; th.style.position = 'relative'; }
      });
      // slider foto hero: semua menu bergantian (foto + nama + harga)
      if (window.initHeroSlider) window.initHeroSlider(kat.flatMap(k => (k.menu || []).map(m => ({ nama: namaTampil(m.nama), gambar: m.gambar, harga: m.harga, kategori: k.nama }))));
      // PESANAN TERAKHIR 22 Nov (Amar: beranda user terlalu sepi): 3 pesanan
      // terbaru + tombol Pesan Lagi (item diambil dari detail pesanan, harga
      // mengikuti harga menu saat ini; item yang sudah tidak ada / habis
      // dilewati dan dilaporkan).
      const boxT = $('pesananTerakhir');
      if (boxT) {
        try {
          const semuaMenu = kat.flatMap(k => k.menu || []);
          const tiga = (await MK.api('shop.php?r=riwayat')).slice(0, 3);
          if (!tiga.length) {
            boxT.innerHTML = '<div class="empty-state"><b>Belum ada pesanan</b><p>Pesan kopi pertamamu, nanti riwayatnya muncul di sini.</p><a class="btn btn-primary" href="menu.html">Mulai Pesan</a></div>';
          } else {
            boxT.innerHTML = tiga.map(o => {
              const ringkas = (o.menu || '').split(' + ').map(n => namaTampil(n.trim())).join(' + ');
              return `<article class="pesanan-card" data-id="${o.id}">
                <div class="pesanan-info">
                  <p class="pesanan-kode">${MK.esc(o.kode)} <span class="tag ${MK.tagClass(o.status)}">${o.status}</span></p>
                  <p class="pesanan-tgl">${MK.tgl(o.tanggal)}</p>
                  <p class="pesanan-menu">${MK.esc(ringkas)} · ${o.jumlah} item</p>
                </div>
                <div class="pesanan-aksi">
                  <p class="pesanan-total">${MK.money(o.total)}</p>
                  <button type="button" class="btn btn-primary btn-sm" data-pesanlagi="${o.id}">Pesan Lagi</button>
                </div>
              </article>`;
            }).join('');
            boxT.addEventListener('click', async e => {
              const b = e.target.closest('[data-pesanlagi]'); if (!b) return;
              b.disabled = true; const teks = b.textContent; b.textContent = 'Menambahkan…';
              try {
                const d = await MK.api('shop.php?r=detail&id=' + b.dataset.pesanlagi);
                let tambah = 0, lewati = 0;
                (d.items || []).forEach(it => {
                  const m = semuaMenu.find(x => +x.id === +it.id_menu);
                  if (!m || +m.stok <= 0) { lewati++; return; }
                  const reguler = it.ukuran === 'Regular' && +m.harga_regular > 0;
                  MK.cart.add({id: m.id, nama: namaTampil(m.nama), slug: m.slug, gambar: m.gambar, tipe: m.tipe,
                    ukuran: it.ukuran || null, gula: it.gula || null, es: it.es || null,
                    catatan: it.catatan || '', harga: reguler ? +m.harga_regular : +m.harga, jumlah: +it.jumlah || 1});
                  tambah++;
                });
                if (!tambah) { MK.toast('Semua item pesanan ini sudah tidak tersedia.', true); b.disabled = false; b.textContent = teks; return; }
                MK.toast(tambah + ' item ditambahkan ke keranjang' + (lewati ? ' (' + lewati + ' item tidak tersedia lagi)' : ''));
                location.href = 'keranjang.html';
              } catch (err) { MK.toast(err.message, true); b.disabled = false; b.textContent = teks; }
            });
          }
        } catch (e) { boxT.innerHTML = '<div class="empty-state"><b>Pesanan belum bisa dimuat</b><p>' + MK.esc(e.message) + '</p></div>'; }
      }
    } catch (e) {}
  }

  /* ================= KATALOG MENU ================= */
  async function initMenu() {
    const box = $('menuContent'), side = document.querySelector('.sidebar'), search = document.querySelector('.search-box input');
    const categoryIcons = {
      signature: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9h13v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V9Z"/><path d="M17 10h1a3 3 0 0 1 0 6h-1M8 5c-1-1 1-2 0-3M12 5c-1-1 1-2 0-3"/></svg>',
      'non-coffee': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8h11l-1 12H7L6 8Z"/><path d="M5 8h13M9 5V3M13 5V3M17 11h2a2 2 0 0 1 0 4h-2"/></svg>',
      'iced-coffee': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8h12l-1.5 13h-9L6 8Z"/><path d="M4 8h16M10 4l4 4M15 3l-3 5M8 11h.01M13 13h.01"/></svg>',
      'manual-brew': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 8h12l-2 9H7L5 8Z"/><path d="M3 8h16M8 5h6M10 3h2M11 17v3M7 21h8"/></svg>',
      hot: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14l-1 7H6l-1-7Z"/><path d="M8 9c-1-1 1-2 0-3M12 9c-1-1 1-2 0-3M16 9c-1-1 1-2 0-3"/></svg>',
    };
    const fallbackCategoryIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>';
    let data = [];
    try { data = await MK.api('shop.php?r=menu'); } catch (e) { box.innerHTML = '<div class="empty-state"><b>Menu belum bisa dimuat</b>' + MK.esc(e.message) + '</div>'; return; }
    data.forEach(k => (k.menu || []).forEach(m => { m.nama = namaTampil(m.nama); }));
    let wishIds = new Set();
    try { const w = await MK.api('shop.php?r=wishlist'); wishIds = new Set(w.map(x => +x.id)); } catch (e) {}

    side.querySelectorAll('a:not(:first-child)').forEach(a => a.remove());
    data.forEach(k => side.insertAdjacentHTML('beforeend', '<a href="#' + k.slug + '"><span class="sb-ico">' + (categoryIcons[k.slug] || fallbackCategoryIcon) + '</span>' + MK.esc(k.nama) + '</a>'));

    const card = (m, feat) => `
      <a class="product-card${m.stok <= 0 ? ' habis' : ''}${feat ? ' menu-featured' : ''}" href="pesanan.html?item=${encodeURIComponent(m.slug)}">
        <div class="product-thumb"><img class="product-img" loading="lazy" alt="${MK.esc(m.nama)}" src="${MK.esc(MK.img(m.gambar))}">${feat ? '<span class="menu-tag">ANDALAN</span>' : ''}<button type="button" class="product-add" data-id="${m.id}" title="Tambah ke keranjang">+</button></div>
        <div class="product-info"><p class="product-name">${MK.esc(m.nama)}</p>${m.deskripsi ? `<p class="product-desc">${MK.esc(m.deskripsi)}</p>` : ''}<div class="product-foot"><p class="product-price">${MK.money(m.harga)}</p><button type="button" class="product-wish${wishIds.has(+m.id) ? ' on' : ''}" data-wish="${m.id}" title="Simpan ke wishlist" aria-label="Simpan ke wishlist"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20.5C7 16.5 3 13.3 3 9.3 3 6.4 5.2 4.5 7.7 4.5c1.7 0 3.4.9 4.3 2.4.9-1.5 2.6-2.4 4.3-2.4 2.5 0 4.7 1.9 4.7 4.8 0 4-4 7.2-9 11.2z"/></svg></button></div></div>
      </a>`;
    const draw = () => {
      const q = (search?.value || '').trim().toLowerCase();
      const unggulan = !q && data.length && data[0].menu.length ? data[0].menu[0] : null;
      const html = data.map((k, ki) => {
        const ms = k.menu.filter(m => !q || m.nama.toLowerCase().includes(q));
        return ms.length ? `<p class="category-label" id="${k.slug}">${MK.esc(k.nama)}</p><div class="grid grid-4">${ms.map(m => card(m, m === unggulan)).join('')}</div>` : '';
      }).join('');
      box.innerHTML = html || '<div class="empty-state"><b>Menu tidak ditemukan</b>Coba kata kunci lain.</div>';
    };
    draw();
    search?.addEventListener('input', draw);

    // tombol + : tambah ke keranjang dengan pilihan standar
    box.addEventListener('click', async e => {
      const wb = e.target.closest('.product-wish');
      if (wb) {
        e.preventDefault(); e.stopPropagation();
        try { const r = await MK.api('shop.php?r=wishlist_toggle', {id_menu: +wb.dataset.wish}); wishIds = new Set(r.ids); wb.classList.toggle('on', r.ada); MK.toast(r.ada ? 'Disimpan ke wishlist' : 'Dihapus dari wishlist'); }
        catch (err) { MK.toast(err.message, true); }
        return;
      }
      const btn = e.target.closest('.product-add'); if (!btn) return;
      e.preventDefault(); e.stopPropagation();
      const m = data.flatMap(k => k.menu).find(x => x.id == btn.dataset.id); if (!m) return;
      MK.cart.add({id: m.id, nama: m.nama, slug: m.slug, gambar: m.gambar, tipe: m.tipe,
        ukuran: m.harga_regular > 0 ? 'Small' : null, gula: m.tipe === 'makanan' ? null : 'Less Sugar', es: m.tipe === 'es' ? 'Less Ice' : null,
        catatan: '', harga: m.harga, jumlah: 1});
      btn.classList.add('added'); btn.textContent = '✓'; setTimeout(() => { btn.classList.remove('added'); btn.textContent = '+'; }, 900);
      MK.toast(m.nama + ' ditambahkan (' + MK.cart.count() + ' item di keranjang)');
    });

    // sidebar: tandai aktif & gulir
    side.addEventListener('click', e => {
      const a = e.target.closest('a'); if (!a) return;
      side.querySelectorAll('a').forEach(x => x.classList.toggle('active', x === a));
      if (a.getAttribute('href') === '#semua') { e.preventDefault(); window.scrollTo({top: 0, behavior: 'smooth'}); }
    });
    if (location.hash.length > 1) {
      const t = document.getElementById(location.hash.slice(1));
      if (t) { t.scrollIntoView(); side.querySelectorAll('a').forEach(x => x.classList.toggle('active', x.getAttribute('href') === location.hash)); }
    }
  }

  /* ================= KUSTOMISASI PESANAN ================= */
  async function initCustomize() {
    const slug = new URLSearchParams(location.search).get('item');
    if (!slug) { location.replace('menu.html'); return; }
    let m;
    try { m = await MK.api('shop.php?r=item&slug=' + encodeURIComponent(slug)); }
    catch (e) { MK.toast(e.message, true); setTimeout(() => location.replace('menu.html'), 1200); return; }
    m.nama = namaTampil(m.nama);

    document.title = 'Mampir Kopi — ' + m.nama;
    $('itemPhoto').innerHTML = '<img alt="' + MK.esc(m.nama) + '" src="' + MK.esc(MK.img(m.gambar)) + '">';
    $('itemName').textContent = m.nama; $('itemPrice').textContent = MK.money(m.harga); $('itemDesc').textContent = m.deskripsi || '';

    const pills = (id, arr) => { $(id).innerHTML = arr.map((p, i) => `<div class="pill${p.sel ? ' selected' : ''}" data-v="${MK.esc(p.v)}">${MK.esc(p.v)}${p.sub ? '<small>' + p.sub + '</small>' : ''}</div>`).join(''); };
    const adaUkuran = m.harga_regular > 0, adaGula = m.tipe !== 'makanan', adaEs = m.tipe === 'es';
    $('grpUkuran').style.display = adaUkuran ? '' : 'none'; $('grpGula').style.display = adaGula ? '' : 'none'; $('grpEs').style.display = adaEs ? '' : 'none';
    pills('optUkuran', [{v:'Small', sub: MK.money(m.harga), sel:true}, {v:'Regular', sub: MK.money(m.harga_regular)}]);
    pills('optGula', [{v:'Less Sugar', sel:true}, {v:'Normal'}, {v:'More Sugar'}]);
    pills('optEs', [{v:'Less Ice', sel:true}, {v:'Normal'}, {v:'More Ice'}]);

    const pick = id => document.querySelector('#' + id + ' .pill.selected')?.dataset.v || null;
    const qty = () => Math.max(1, parseInt(document.querySelector('.qty-value').textContent, 10) || 1);
    const unit = () => (adaUkuran && pick('optUkuran') === 'Regular') ? m.harga_regular : m.harga;
    const btn = $('btnAdd');
    const refresh = () => {
      $('itemPrice').textContent = MK.money(unit());
      $('btnPrice').textContent = MK.money(unit() * qty());
      const n = $('itemNote'); $('noteCount').textContent = n.value.length + '/100';
    };
    refresh();
    // app.js memproses klik pill & tombol +/- lebih dulu; kita hitung ulang sesudahnya
    document.addEventListener('click', e => { if (e.target.closest('.customize-card')) setTimeout(refresh, 0); });
    $('itemNote').addEventListener('input', refresh);

    if (m.stok <= 0) { btn.disabled = true; $('btnLabel').textContent = 'Stok habis'; }
    btn.addEventListener('click', () => {
      if (m.stok < qty()) { MK.toast('Stok tinggal ' + m.stok, true); return; }
      MK.cart.add({id: m.id, nama: m.nama, slug: m.slug, gambar: m.gambar, tipe: m.tipe,
        ukuran: adaUkuran ? pick('optUkuran') : null, gula: adaGula ? pick('optGula') : null, es: adaEs ? pick('optEs') : null,
        catatan: $('itemNote').value.trim(), harga: unit(), jumlah: qty()});
      location.href = 'keranjang.html';
    });
  }

  /* ================= KERANJANG ================= */
  async function initCart() {
    const list = $('cartList');
    const draw = async () => {
      const c = MK.cart.get();
      if (!c.length) {
        list.innerHTML = '<div class="empty-state"><b>Keranjang masih kosong</b>Yuk pilih menu favoritmu dulu.<br><a class="btn btn-primary" href="menu.html">Lihat Menu</a></div>';
      } else {
        list.innerHTML = c.map(i => `
          <div class="cart-item" data-key="${MK.esc(i.key)}">
            <div class="cart-thumb"><img alt="" src="${MK.esc(MK.img(i.gambar))}"></div>
            <div class="cart-info">
              <p class="name">${MK.esc(i.nama)}</p>
              <p class="variant">${MK.esc(MK.varian(i)) || '&nbsp;'}</p>
              ${i.catatan ? '<p class="note">“' + MK.esc(i.catatan) + '”</p>' : ''}
              <p class="price">${MK.money(i.harga * i.jumlah)}${i.jumlah > 1 ? '<small>@ ' + MK.money(i.harga) + '</small>' : ''}</p>
            </div>
            <div class="cart-controls">
              <button class="trash-btn" data-act="del" title="Hapus">✕</button>
              <div class="cart-qty"><button data-act="min">−</button><span>${i.jumlah}</span><button data-act="plus">+</button></div>
            </div>
          </div>`).join('');
      }
      const sub = MK.cart.subtotal(), p = await MK.promo(sub);
      $('sumLabel').textContent = 'Subtotal (' + MK.cart.count() + ' item)';
      $('sumSub').textContent = MK.money(sub);
      $('rowDiskon').style.display = p.diskon ? '' : 'none';
      $('sumDiskonLabel').textContent = 'Diskon (' + (p.kode || '') + ')';
      $('sumDiskon').textContent = '− ' + MK.money(p.diskon);
      $('sumTotal').textContent = MK.money(sub - p.diskon);
      const msg = $('voucherMsg');
      if (p.kode) { msg.className = 'voucher-msg ok'; msg.textContent = 'Voucher ' + p.kode + ' dipakai.'; $('voucherInput').value = p.kode; }
      else if (p.pesan) { msg.className = 'voucher-msg err'; msg.textContent = p.pesan; }
      $('btnLanjut').style.opacity = c.length ? '' : '.5';
    };
    list.addEventListener('click', e => {
      const b = e.target.closest('[data-act]'); if (!b) return;
      const c = MK.cart.get(), it = c.find(x => x.key === b.closest('.cart-item').dataset.key); if (!it) return;
      if (b.dataset.act === 'plus') it.jumlah = Math.min(99, it.jumlah + 1);
      if (b.dataset.act === 'min') it.jumlah = Math.max(1, it.jumlah - 1);
      MK.cart.set(b.dataset.act === 'del' ? c.filter(x => x !== it) : c); draw();
    });
    $('voucherBtn').addEventListener('click', async () => {
      const kode = $('voucherInput').value.trim().toUpperCase(), msg = $('voucherMsg');
      if (!kode) { localStorage.removeItem('mk_promo'); msg.className = 'voucher-msg'; msg.textContent = ''; return draw(); }
      try { await MK.api('shop.php?r=promo&kode=' + encodeURIComponent(kode) + '&subtotal=' + MK.cart.subtotal()); localStorage.setItem('mk_promo', kode); }
      catch (e) { msg.className = 'voucher-msg err'; msg.textContent = e.message; $('voucherInput').value = localStorage.getItem('mk_promo') || ''; return; }
      draw();
    });
    $('btnLanjut').addEventListener('click', e => { if (!MK.cart.count()) { e.preventDefault(); MK.toast('Keranjang masih kosong', true); } });
    if (await MK.segarkanHarga()) MK.toast('Harga diperbarui mengikuti katalog terbaru.');
    draw();
  }

  /* ================= PEMBAYARAN ================= */
  async function initPay() {
    if (await MK.segarkanHarga()) MK.toast('Harga diperbarui mengikuti katalog terbaru.');
    const c = MK.cart.get();
    if (!c.length) {
      document.querySelector('.pay-layout').innerHTML = '<div class="empty-state" style="grid-column:1/-1"><b>Belum ada pesanan untuk dibayar</b>Tambahkan menu ke keranjang dulu.<br><a class="btn btn-primary" href="menu.html">Lihat Menu</a></div>';
      return;
    }
    const sub = MK.cart.subtotal(), p = await MK.promo(sub);
    if (p.pesan) MK.toast(p.pesan, true);
    /* Rubrik #22: ongkir diambil dari pengaturan situs (bisa diubah admin);
       5000 hanya cadangan bila info tidak bisa dimuat. */
    let ONGKIR = 5000;
    try { const info = await (await fetch("api/shop.php?r=info")).json(); if (info?.ok && +info.data.ongkir >= 0) ONGKIR = +info.data.ongkir; } catch (e) {}
    const lblDiantar = document.querySelector('[data-ambil="Diantar"] small');
    if (lblDiantar) lblDiantar.textContent = "+ " + MK.money(ONGKIR) + " ongkir";
    let caraAmbil = 'Ambil di Kasir';
    const ongkirAktif = () => caraAmbil === 'Diantar' ? ONGKIR : 0;
    const totalKini = () => sub - p.diskon + ongkirAktif();
    const segarTotal = () => { $('payTotal').textContent = MK.money(totalKini()); const r = $('rowOngkir'); if (r) r.style.display = ongkirAktif() ? '' : 'none'; const qt = $('qrSummaryTotal'); if (qt) qt.textContent = MK.money(totalKini()); const qr = $('qrRowOngkir'); if (qr) qr.style.display = ongkirAktif() ? '' : 'none'; };
    segarTotal();
    $('paySub').textContent = c.map(i => i.jumlah + 'x ' + i.nama + (i.ukuran ? ' (' + i.ukuran + ')' : '')).join(', ');
    /* D-m9: varian di baris kedua kecil di bawah nama; harga tetap di kanan baris pertama. */
    const barisRingkasan = i => { const v = MK.varian(i); return `<div class="pay-item"><span class="pay-item-main"><span class="pay-item-name">${i.jumlah}x ${MK.esc(i.nama)}</span>${v ? `<small class="pay-item-var">${MK.esc(v)}</small>` : ''}</span><b>${MK.money(i.harga * i.jumlah)}</b></div>`; };
    $('payDetail').innerHTML = c.map(barisRingkasan).join('')
      + (p.diskon ? `<div><span>Diskon (${MK.esc(p.kode)})</span><b>− ${MK.money(p.diskon)}</b></div>` : '')
      + `<div id="rowOngkir" style="display:none"><span>Ongkir (diantar)</span><b>${MK.money(ONGKIR)}</b></div>`;
    // Ronde 6: ringkasan yang sama di kolom kanan (di bawah panel QRIS).
    const qs = $('qrSummaryItems');
    if (qs) qs.innerHTML = c.map(barisRingkasan).join('')
      + (p.diskon ? `<div><span>Diskon (${MK.esc(p.kode)})</span><b>− ${MK.money(p.diskon)}</b></div>` : '')
      + `<div id="qrRowOngkir" style="display:none"><span>Ongkir (diantar)</span><b>${MK.money(ONGKIR)}</b></div>`;
    const qt0 = $('qrSummaryTotal'); if (qt0) qt0.textContent = MK.money(totalKini());
    $('payToggle').addEventListener('click', () => {
      const open = $('payDetail').classList.toggle('open');
      $('payToggle').setAttribute('aria-expanded', String(open));
    });

    const metode = () => document.querySelector('.pay-method-row.selected .pay-name')?.textContent.trim() || 'QRIS';
    const judul = () => { const m = metode(), cod = m === 'COD';
      $('qrTitle').textContent = cod ? 'Bayar di tempat (COD)' : (m === 'QRIS' ? 'Bayar dengan QRIS' : 'Bayar dengan ' + m + ' (scan QRIS)');
      const qc = document.querySelector('.qr-code'), st = document.querySelector('.payment-steps');
      if (qc) qc.style.display = cod ? 'none' : ''; if (st) st.style.display = cod ? 'none' : '';
      const tt = $('payTimer'); if (tt) tt.hidden = cod; };
    document.querySelector('.pay-method-list').addEventListener('click', () => setTimeout(judul, 0)); judul();

    const codRow = document.querySelector('.cod-row'), alamatEl = $('alamatAntar');
    /* kritikus 2.0: pil "6 metode" statis padahal COD hanya muncul untuk
       Diantar — hitung dari baris yang terlihat (5 Ambil di Kasir, 6 Diantar) */
    const segarMetode = () => { const mc = document.querySelector('.payment-method-count'); if (mc) mc.textContent = (caraAmbil === 'Diantar' ? 6 : 5) + ' metode'; };
    segarMetode();
    document.querySelectorAll('.ambil-row').forEach(row => row.addEventListener('click', () => {
      caraAmbil = row.dataset.ambil;
      document.querySelectorAll('.ambil-row').forEach(x => x.classList.toggle('selected', x === row));
      if (alamatEl) alamatEl.hidden = caraAmbil !== 'Diantar';
      if (codRow) codRow.hidden = caraAmbil !== 'Diantar';
      if (caraAmbil !== 'Diantar' && metode() === 'COD') { const q1 = document.querySelector('.pay-method-row'); if (q1) q1.click(); }
      segarTotal();
      segarMetode();
    }));

    /* L-m1: TIDAK ada lagi hitung mundur di halaman ini — pesanan belum ada
       sebelum tombol Bayar diklik, jadi timer 5 menit dari pembukaan halaman
       menyesatkan (dan modal "pesanan dibatalkan"-nya palsu). Info statis
       5 menit ada di HTML (#payTimer); countdown yang benar tampil di Riwayat. */

    $('btnBayar').addEventListener('click', async () => {
      const b = $('btnBayar'); b.disabled = true; b.textContent = 'Memproses...';
      try {
        if (caraAmbil === 'Diantar' && (alamatEl?.value.trim().length || 0) < 10) { MK.toast('Tulis alamat pengantaran yang lengkap dulu (minimal 10 karakter).', true); b.disabled = false; b.textContent = 'Bayar Sekarang'; return; }
        /* L-m7: kunci idempotensi — satu kunci per percobaan checkout; retry
           setelah gagal jaringan memakai kunci yang sama agar pesanan tidak ganda. */
        let kunci = sessionStorage.getItem('mk_order_key');
        if (!kunci) {
          kunci = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : 'mk-' + Date.now() + '-' + Math.random().toString(36).slice(2);
          sessionStorage.setItem('mk_order_key', kunci);
        }
        const r = await MK.api('shop.php?r=order', {metode: metode(), kode_promo: p.kode, cara_ambil: caraAmbil, alamat_antar: alamatEl?.value.trim() || '', idempotency_key: kunci,
          items: MK.cart.get().map(i => ({id: i.id, ukuran: i.ukuran, gula: i.gula, es: i.es, catatan: i.catatan, jumlah: i.jumlah}))});
        MK.cart.clear(); sessionStorage.removeItem('mk_order_key');
        const m = MK.modal(`<div class="mk-success"><div class="ck">✓</div><h3>Pesanan berhasil dibuat</h3>
          <p class="mk-meta">Kode pesanan <b>${MK.esc(r.kode)}</b> • Transaksi ${MK.esc(r.kode_transaksi)}</p>
          <p style="font-size:22px;font-weight:800;margin:6px 0">${MK.money(r.total)}</p>
          ${metode() === 'COD' ? '<p class="mk-meta">Pesanan disiapkan lalu diantar ke alamatmu. Siapkan uang tunai ' + MK.money(r.total) + ' — bayar saat pesanan sampai. Status bisa dilihat di Riwayat.</p>' : (r.cara_ambil === 'Diantar' ? '<p class="mk-meta">Pembayaran ' + MK.esc(metode()) + ' menunggu verifikasi admin (maks. 5 menit), lalu pesanan disiapkan dan diantar ke alamatmu. Status bisa dilihat di Riwayat.</p>' : '<p class="mk-meta">Pembayaran ' + MK.esc(metode()) + ' sedang menunggu verifikasi admin. Bila belum terverifikasi dalam 5 menit, pesanan otomatis dibatalkan. Status bisa dilihat di Riwayat.</p>')}
          <button class="btn btn-primary" id="btnStrukBaru"><svg class="ic-struk" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2h12v20l-2-1.6L14 22l-2-1.6L10 22l-2-1.6L6 22z"/><path d="M9.5 8h5M9.5 12h5"/></svg> Lihat Struk</button><a class="btn btn-ghost" href="riwayat.html">Lihat Riwayat</a><a class="btn btn-ghost" href="dashboard.html">Ke Beranda</a></div>`);
        m.querySelector('#btnStrukBaru').onclick = async () => {
          try { const d = await MK.api('shop.php?r=detail&id=' + r.id); MK.tampilStruk(d); }
          catch (e) { MK.toast(e.message, true); }
        };
        m.querySelector('.mk-close').onclick = () => { location.href = 'riwayat.html'; };
      } catch (e) { MK.toast(e.message, true); b.disabled = false; b.textContent = 'Bayar Sekarang'; }
    });
  }

  /* ================= RIWAYAT ================= */
  async function initRiwayat() {
    const body = $('riwayatBody');
    let rows = [];
    try { rows = await MK.api('shop.php?r=riwayat'); } catch (e) { body.innerHTML = '<p class="memuat">' + MK.esc(e.message) + '</p>'; return; }
    /* KARTU RIWAYAT (Amar 22 Nov 17:26): tabel diganti kartu per pesanan,
       sama seperti kartu menu (awwwards.css §24). Kelas .riwayat-card
       didukung filter tab di js/app.js; data-status + data-id
       dipertahankan supaya tab dan modal detail jalan seperti semula.
       Kode pesanan (MK-XXXX) kini tampil sebagai judul kartu — di tabel
       lama kode tidak pernah ditampilkan. */
    body.innerHTML = rows.length ? rows.map(o => `<article class="riwayat-card" data-status="${o.status}">
      <div class="orc-head"><b class="orc-kode">${MK.esc(o.kode)}</b><span class="tag ${MK.tagClass(o.status)}">${o.status}</span></div>
      <p class="orc-meta">${MK.tgl(o.tanggal)} • ${o.jumlah} item</p>
      <p class="orc-menu">${MK.esc(o.menu)}</p>
      ${o.status === 'Menunggu' && typeof o.sisa_bayar === 'number' ? `<div class="batas-bayar" data-sisa="${o.sisa_bayar}">Batal otomatis: <b>--:--</b></div>` : ''}
      <div class="orc-foot"><span class="orc-total">${MK.money(o.total)}</span><button class="btn btn-ghost btn-sm" data-id="${o.id}">Lihat Detail</button></div></article>`).join('')
      : '<div class="empty-state"><b>Belum ada pesanan</b><a class="btn btn-primary" href="menu.html">Mulai Pesan</a></div>';

    const bts = body.querySelectorAll('.batas-bayar');
    if (bts.length) {
      const fmtB = s => String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
      const gamb = () => { let nol = false; bts.forEach(el => { const s = +el.dataset.sisa; el.querySelector('b').textContent = fmtB(Math.max(0, s)); if (s <= 0) nol = true; el.dataset.sisa = s - 1; }); if (nol) { clearInterval(ivB); setTimeout(() => location.reload(), 1200); } };
      const ivB = setInterval(gamb, 1000); gamb();
    }

    body.addEventListener('click', async e => {
      const b = e.target.closest('button[data-id]'); if (!b) return;
      b.disabled = true;
      try {
        const d = await MK.api('shop.php?r=detail&id=' + b.dataset.id), pay = d.pembayaran;
        // REFUND 22 Nov: tombol pengajuan muncul bila pesanan Selesai (refund
        // atau kembalikan barang), atau Menunggu/Diproses yang SUDAH dibayar
        // terverifikasi (refund saja — barang belum diterima, jadi tidak ada
        // yang dikembalikan). Pesanan belum bayar / COD belum bayar / sudah
        // Dibatalkan tidak dapat tombol ini.
        const sudahBayar = !!(pay && pay.status === 'Terverifikasi');
        const bolehKembali = !d.pengembalian && (d.status === 'Selesai' || ((d.status === 'Menunggu' || d.status === 'Diproses') && sudahBayar));
        const m = MK.modal(`<h3>Detail Pesanan ${MK.esc(d.kode)}</h3>
          <p class="mk-meta">${MK.tgl(d.tanggal)} • <span class="tag ${MK.tagClass(d.status)}">${d.status}</span></p>
          ${d.items.map(i => `<div class="mk-item"><img alt="" src="${MK.esc(MK.img(i.gambar))}"><div><p class="n">${MK.esc(i.nama)} ×${i.jumlah}</p>
            <p class="v">${MK.esc(MK.varian(i)) || '—'}${i.catatan ? ' • “' + MK.esc(i.catatan) + '”' : ''}</p></div><span class="t">${MK.money(i.subtotal)}</span></div>`).join('')}
          <div class="mk-sum"><div><span>Subtotal</span><span>${MK.money(d.subtotal)}</span></div>
            ${d.diskon > 0 ? `<div><span>Diskon (${MK.esc(d.kode_promo || '')})</span><span>− ${MK.money(d.diskon)}</span></div>` : ''}
            <div class="tot"><span>Total Biaya</span><span>${MK.money(d.total)}</span></div></div>
          ${pay ? `<div class="mk-pay"><b>Pembayaran</b><br>Metode: ${MK.esc(pay.metode)}<br>Kode transaksi: ${MK.esc(pay.kode_transaksi)}<br>Status: <span class="tag ${MK.tagClass(pay.status)}">${MK.esc(pay.status)}</span></div>` : ''}
          ${d.cara_ambil ? `<div class="mk-pay"><b>Pengambilan</b><br>${MK.esc(d.cara_ambil)}${+d.ongkir > 0 ? ' · Ongkir ' + MK.money(d.ongkir) : ''}${d.alamat_antar ? '<br>' + MK.esc(d.alamat_antar) : ''}</div>` : ''}
          ${d.pengembalian ? `<div class="mk-pay"><b>Pengembalian</b><br>Status pengajuan: ${MK.esc(d.pengembalian)}${d.pengembalian_info && d.pengembalian_info.jenis === 'Refund' ? ' (Refund dana' + (d.pengembalian_info.jumlah_refund ? ' ' + MK.money(d.pengembalian_info.jumlah_refund) : '') + ')' : ''}</div>` : (bolehKembali ? '<button class="btn btn-ghost" id="btnKembali" style="margin-top:14px">Ajukan Refund / Pengembalian</button>' : '')}
          <button class="btn btn-primary" id="btnStrukRiwayat" style="margin-top:14px"><svg class="ic-struk" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2h12v20l-2-1.6L14 22l-2-1.6L10 22l-2-1.6L6 22z"/><path d="M9.5 8h5M9.5 12h5"/></svg> Lihat Struk</button>`);
        m.querySelector('#btnStrukRiwayat').onclick = () => { m.close(); MK.tampilStruk(d); };
        const bk = m.querySelector('#btnKembali');
        if (bk) bk.onclick = () => { m.close();
          // REFUND 22 Nov: pilih jenis pengajuan. Pesanan Menunggu/Diproses
          // hanya bisa Refund (barang belum diterima); pesanan Selesai bisa
          // pilih Refund atau Kembalikan pesanan. Field tujuan refund hanya
          // tampil bila jenisnya Refund.
          const hanyaRefund = d.status !== 'Selesai';
          const mf = MK.modal(`<h3>Ajukan Refund / Pengembalian</h3><p class="mk-meta">Untuk pesanan ${MK.esc(d.kode)}. Admin akan meninjau pengajuanmu; bila disetujui, dana dikembalikan dan pesanan dibatalkan.</p>
            ${hanyaRefund ? '<p class="mk-meta">Pesanan ini belum selesai, jadi yang bisa diajukan adalah refund dana.</p>' : `<div class="f-field"><label>Jenis pengajuan</label><label style="display:flex;gap:8px;align-items:center;justify-content:flex-start;font-weight:400"><input type="radio" name="jenisKembali" value="Refund" style="width:16px;height:16px;flex:none;margin:0" checked> Refund dana</label><label style="display:flex;gap:8px;align-items:center;justify-content:flex-start;font-weight:400"><input type="radio" name="jenisKembali" value="Pengembalian" style="width:16px;height:16px;flex:none;margin:0"> Kembalikan pesanan</label></div>`}
            <div class="f-field" id="wrapRefund"><label for="metodeRefund">Metode refund</label><select id="metodeRefund"><option value="">Pilih metode</option><option>Transfer Bank</option><option>GoPay</option><option>OVO</option><option>DANA</option><option>ShopeePay</option></select></div>
            <div class="f-field" id="wrapTujuan"><label for="tujuanRefund">Nomor rekening / e-wallet tujuan refund</label><input id="tujuanRefund" type="text" placeholder="Contoh: BCA 1234567890 a.n. Budi" maxlength="100"></div>
            <div class="f-field"><label for="alasanKembali">Alasan</label><textarea id="alasanKembali" placeholder="Contoh: pesanan yang diterima salah atau rusak"></textarea></div>
            <button class="btn btn-primary" id="btnKirimKembali">Kirim Pengajuan</button>`);
          const aturJenis = () => {
            const j = mf.querySelector('input[name="jenisKembali"]:checked');
            const isRefund = !j || j.value === 'Refund';
            mf.querySelector('#wrapRefund').style.display = isRefund ? '' : 'none';
            mf.querySelector('#wrapTujuan').style.display = isRefund ? '' : 'none';
          };
          mf.querySelectorAll('input[name="jenisKembali"]').forEach(r => r.addEventListener('change', aturJenis));
          mf.querySelector('#btnKirimKembali').onclick = async () => {
            try {
              const j = mf.querySelector('input[name="jenisKembali"]:checked');
              const jenis = j ? j.value : 'Refund';
              const data = {id_pesanan: d.id, alasan: mf.querySelector('#alasanKembali').value, jenis};
              if (jenis === 'Refund') { data.metode_refund = mf.querySelector('#metodeRefund').value; data.tujuan_refund = mf.querySelector('#tujuanRefund').value; }
              await MK.api('shop.php?r=pengembalian', data);
              mf.close(); MK.toast('Pengajuan ' + (jenis === 'Refund' ? 'refund' : 'pengembalian') + ' terkirim.'); setTimeout(() => location.reload(), 900);
            } catch (e) { MK.toast(e.message, true); }
          };
        };
      } catch (err) { MK.toast(err.message, true); }
      b.disabled = false;
    });
  }

  /* ================= WISHLIST ================= */
  async function initWishlist() {
    const box = $('wishList');
    let rows = [];
    const draw = async () => {
      try {
        rows = await MK.api('shop.php?r=wishlist');
        rows.forEach(m => { m.nama = namaTampil(m.nama); });
        // KARTU WISHLIST = KARTU MENU (22 Nov 16:53, Amar): foto, nama,
        // deskripsi, harga + hati di kaki kartu, tombol + — markup sama
        // persis seperti kartu di halaman Menu (dulu: tanpa deskripsi,
        // tanpa tombol +, hati menempel di foto).
        box.innerHTML = rows.length ? rows.map(m => `
      <a class="product-card${m.stok <= 0 ? ' habis' : ''}" href="pesanan.html?item=${encodeURIComponent(m.slug)}">
        <div class="product-thumb"><img class="product-img" loading="lazy" alt="${MK.esc(m.nama)}" src="${MK.esc(MK.img(m.gambar))}"><button type="button" class="product-add" data-id="${m.id}" title="Tambah ke keranjang">+</button></div>
        <div class="product-info"><p class="product-name">${MK.esc(m.nama)}</p>${m.deskripsi ? `<p class="product-desc">${MK.esc(m.deskripsi)}</p>` : ''}<div class="product-foot"><p class="product-price">${MK.money(m.harga)}</p><button type="button" class="product-wish on" data-wish="${m.id}" title="Hapus dari wishlist" aria-label="Hapus dari wishlist"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20.5C7 16.5 3 13.3 3 9.3 3 6.4 5.2 4.5 7.7 4.5c1.7 0 3.4.9 4.3 2.4.9-1.5 2.6-2.4 4.3-2.4 2.5 0 4.7 1.9 4.7 4.8 0 4-4 7.2-9 11.2z"/></svg></button></div></div>
      </a>`).join('') : '<div class="wish-empty"><b>Wishlist masih kosong</b><br>Tekan ikon hati pada menu favoritmu di halaman Menu.<br><br><a class="btn btn-primary" href="menu.html">Lihat Menu</a></div>';
      } catch (e) { box.innerHTML = '<div class="empty-state"><b>Wishlist belum bisa dimuat</b>' + MK.esc(e.message) + '</div>'; }
    };
    box.addEventListener('click', async e => {
      const wb = e.target.closest('.product-wish');
      if (wb) {
        e.preventDefault(); e.stopPropagation();
        try { await MK.api('shop.php?r=wishlist_toggle', {id_menu: +wb.dataset.wish}); MK.toast('Dihapus dari wishlist'); draw(); }
        catch (err) { MK.toast(err.message, true); }
        return;
      }
      // tombol + : sama seperti di Menu — tambah ke keranjang dengan
      // pilihan standar (Small bila ada Regular, Less Sugar, Less Ice).
      const btn = e.target.closest('.product-add'); if (!btn) return;
      e.preventDefault(); e.stopPropagation();
      const m = rows.find(x => x.id == btn.dataset.id); if (!m) return;
      MK.cart.add({id: m.id, nama: m.nama, slug: m.slug, gambar: m.gambar, tipe: m.tipe,
        ukuran: m.harga_regular > 0 ? 'Small' : null, gula: m.tipe === 'makanan' ? null : 'Less Sugar', es: m.tipe === 'es' ? 'Less Ice' : null,
        catatan: '', harga: m.harga, jumlah: 1});
      btn.classList.add('added'); btn.textContent = '✓'; setTimeout(() => { btn.classList.remove('added'); btn.textContent = '+'; }, 900);
      MK.toast(m.nama + ' ditambahkan (' + MK.cart.count() + ' item di keranjang)');
    });
    draw();
  }

  /* ================= EDIT PROFIL ================= */
  async function initProfil() {
    document.querySelectorAll('.pw-eye').forEach(btn => {
      btn.addEventListener('click', () => {
        const inp = document.getElementById(btn.dataset.pw);
        if (!inp) return;
        const tampil = inp.type === 'password';
        inp.type = tampil ? 'text' : 'password';
        btn.classList.toggle('on', tampil);
        btn.setAttribute('aria-label', tampil ? 'Sembunyikan password' : 'Tampilkan password');
      });
    });
    let p = null;
    try { p = await MK.api('auth.php?r=profil'); } catch (e) { MK.toast(e.message, true); return; }
    const av = $('profAvatar');
    const tampil = (nama, ver) => {
      if (ver) av.innerHTML = '<img src="api/auth.php?r=foto&v=' + ver + '" alt="Foto profil">';
      else av.textContent = (nama || '?').trim().charAt(0).toUpperCase();
    };
    if ($('profNamaTampil')) $('profNamaTampil').textContent = p.nama;
    if ($('profSub')) $('profSub').textContent = '@' + p.username + (p.email ? ' • ' + p.email : '');
    $('profNama').value = p.nama;
    $('profNama').addEventListener('input', e => { if ($('profNamaTampil')) $('profNamaTampil').textContent = e.target.value || '—'; });
    $('profUsername').value = p.username;
    $('profUsername').addEventListener('input', e => { const v = e.target.value.toLowerCase(); if (e.target.value !== v) e.target.value = v; });
    $('profEmail').value = p.email || '';
    if ($('profNotif')) $('profNotif').checked = !!p.pref_notifikasi;
    // MODE GELAP: radio tema — langsung diterapkan ke halaman begitu dipilih,
    // tersimpan ke server saat tombol Simpan ditekan (pola pref_notifikasi).
    const radioTema = document.querySelectorAll('input[name="tema"]');
    const temaDariServer = v => (v === 'gelap' ? 'gelap' : 'terang');
    radioTema.forEach(r => {
      r.checked = r.value === temaDariServer(p.pref_tema);
      r.addEventListener('change', () => { if (r.checked && window.terapkanTema) window.terapkanTema({ pref_tema: r.value }); });
    });
    tampil(p.nama, p.foto_ada ? (p.foto_ver || 1) : null);
    let berkas = null;
    $('profFile').addEventListener('change', e => {
      berkas = e.target.files[0] || null;
      if (!berkas) return;
      if (berkas.size > 2 * 1024 * 1024) { MK.toast('Ukuran foto maksimal 2MB', true); e.target.value = ''; berkas = null; return; }
      av.innerHTML = '<img src="' + URL.createObjectURL(berkas) + '" alt="Pratinjau foto">';
    });

    // Segarkan tampilan profil + header dari server tanpa memuat ulang halaman.
    const segarkan = async () => {
      try {
        const q = await MK.api('auth.php?r=profil');
        p = q;
        $('profNama').value = q.nama;
        if ($('profNamaTampil')) $('profNamaTampil').textContent = q.nama;
        if ($('profSub')) $('profSub').textContent = '@' + q.username + (q.email ? ' • ' + q.email : '');
        $('profUsername').value = q.username;
        $('profEmail').value = q.email || '';
        if ($('profNotif')) $('profNotif').checked = !!q.pref_notifikasi;
        radioTema.forEach(r => { r.checked = r.value === temaDariServer(q.pref_tema); });
        if (window.terapkanTema) window.terapkanTema({ pref_tema: temaDariServer(q.pref_tema) });
        if (window.simpanTemaLokal) window.simpanTemaLokal(q.pref_tema); /* cache anti-kilatan: q dari server */
        tampil(q.nama, q.foto_ada ? (q.foto_ver || 1) : null);
        if ($('btnHapusFoto')) $('btnHapusFoto').hidden = !q.foto_ada;
        const me = await MK.api('auth.php?r=me');
        const box = document.querySelector('.nav-user');
        if (box && me) {
          const av2 = box.querySelector('.avatar');
          if (av2) {
            if (me.foto_ada) av2.innerHTML = '<img src="api/auth.php?r=foto&v=' + (me.foto_ver || 1) + '" alt="">';
            else av2.textContent = (me.nama || '?').trim().charAt(0).toUpperCase();
            if (av2.nextElementSibling) av2.nextElementSibling.textContent = 'Halo, ' + me.nama.split(' ')[0];
          }
        }
      } catch (e) { /* toast utama sudah memberi tahu */ }
    };
    if ($('btnHapusFoto')) {
      $('btnHapusFoto').hidden = !(p && p.foto_ada);
      $('btnHapusFoto').addEventListener('click', async () => {
        if (!confirm('Hapus foto profil? Avatar kembali ke huruf inisial nama.')) return;
        try {
          await MK.api('auth.php?r=profil_hapus_foto', {});
          berkas = null; $('profFile').value = '';
          await segarkan();
          MK.toast('Foto profil dihapus');
        } catch (e) { MK.toast(e.message, true); }
      });
    }

    $('btnProfilSimpan').addEventListener('click', async () => {
      const b = $('btnProfilSimpan'); b.disabled = true; b.textContent = 'Menyimpan...';
      try {
        const pwB = $('profPwBaru').value, pwU = $('profPwUlang').value;
        if (pwB !== pwU) { MK.toast('Ulangi password baru tidak sama', true); b.disabled = false; b.textContent = 'Simpan Perubahan'; return; }
        const fd = new FormData();
        fd.append('nama', $('profNama').value.trim());
        fd.append('username', $('profUsername').value.trim());
        fd.append('email', $('profEmail').value.trim());
        fd.append('pref_notifikasi', $('profNotif') && $('profNotif').checked ? '1' : '0');
        fd.append('pref_tema', (document.querySelector('input[name="tema"]:checked') || {}).value === 'gelap' ? 'gelap' : 'terang');
        fd.append('password_lama', $('profPwLama').value);
        fd.append('password_baru', pwB);
        fd.append('password_ulang', pwU);
        if (berkas) fd.append('foto', berkas);
        await MK.apiForm('auth.php?r=profil_update', fd);
        MK.toast('Profil tersimpan');
        berkas = null; $('profFile').value = '';
        $('profPwLama').value = ''; $('profPwBaru').value = ''; $('profPwUlang').value = '';
        await segarkan();
        b.disabled = false; b.textContent = 'Simpan Perubahan';
      } catch (e) { MK.toast(e.message, true); b.disabled = false; b.textContent = 'Simpan Perubahan'; }
    });
  }
});

/* ================= AKUN (nama user + tombol keluar) ================= */
document.addEventListener('DOMContentLoaded', async () => {
  const box = document.querySelector('.nav-user');
  if (!box) return;

  let me = null;
  try { me = await MK.api('auth.php?r=me'); } catch (e) { return; }
  if (!me) { location.href = 'login.html'; return; }
  if (window.terapkanTema) window.terapkanTema(me); /* MODE GELAP */
  if (window.simpanTemaLokal) window.simpanTemaLokal(me.pref_tema); /* cache anti-kilatan (22 Nov 16:34) */

  const depan = me.nama.split(' ')[0];
  const avatar = box.querySelector('.avatar');
  if (me.foto_ada) avatar.innerHTML = '<img src="api/auth.php?r=foto&v=' + (me.foto_ver || 1) + '" alt="">';
  else avatar.textContent = depan.charAt(0).toUpperCase();
  avatar.nextElementSibling.textContent = 'Halo, ' + depan;

  box.style.cursor = 'pointer';
  box.title = 'Akun saya';
  const menu = document.createElement('div');
  menu.className = 'user-menu'; menu.hidden = true;
  menu.innerHTML = '<a href="profil.html">Edit Profil</a><button type="button" class="user-logout">Keluar</button>';
  box.appendChild(menu);
  box.addEventListener('click', e => {
    if (e.target.closest('.user-menu a') || e.target.closest('.user-logout')) return;
    menu.hidden = !menu.hidden;
  });
  menu.querySelector('.user-logout').addEventListener('click', async () => {
    if (!confirm('Keluar dari akun ' + me.nama + '?')) return;
    try { await MK.api('auth.php?r=logout', {}); } catch (e) { /* sesi sudah berakhir di server */ }
    if (window.hapusTemaLokal) window.hapusTemaLokal(); /* cache tema ikut dihapus */
    location.href = 'tamu.html';
  });
  document.addEventListener('click', e => { if (!box.contains(e.target)) menu.hidden = true; });

  if (me.role !== 'admin') document.querySelectorAll('a[href="admin.html"]').forEach(a => a.remove());
});

/* ================= BANNER VOUCHER (SLIDER) =================
   Isi diambil dari promo yang AKTIF di panel admin (api/shop.php?r=banner).
   Admin menonaktifkan promo -> promo itu hilang dari slider;
   semua promo nonaktif/kadaluarsa -> banner tidak tampil sama sekali.
   Posisi: tepat di bawah hero pada Beranda; halaman tanpa hero tepat di bawah header.
   Gaya slider: satu voucher tampil bergantian tiap 4 detik (Amar 22 Nov 16:32; dulu 4,5 detik), bisa diswipe atau klik panahnya.
   Klik voucher = salin kode. */
document.addEventListener('DOMContentLoaded', async () => {
  const topbar = document.querySelector('.topbar');
  if (!topbar || document.querySelector('.promo-banner')) return;
  if (location.pathname.split('/').pop() !== 'dashboard.html') return; // banner hanya di tampilan awal (Beranda)
  let promos;
  try {
    const res = await fetch('api/shop.php?r=banner');
    const j = await res.json();
    if (!j.ok) return;
    promos = j.data;
  } catch (e) { return; }
  if (!Array.isArray(promos) || !promos.length) return;

  const bln = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
  const tglSingkat = s => { const d = new Date(String(s).slice(0, 10) + 'T00:00:00'); return d.getDate() + ' ' + bln[d.getMonth()] + ' ' + d.getFullYear(); };
  const teksItem = p => {
    const bagian = [p.tipe === 'persen' ? 'Diskon ' + p.nilai + '%' : 'Potongan ' + MK.money(p.nilai)];
    if (+p.min_transaksi > 0) bagian.push('min. belanja ' + MK.money(p.min_transaksi));
    if (p.berlaku_sampai) bagian.push('s/d ' + tglSingkat(p.berlaku_sampai));
    return bagian.join(' · ');
  };


  /* Foto asli per voucher: dipilih otomatis dari kata kunci di kode/judul promo.
     Semua foto adalah foto beneran dari web ini sendiri (produk & suasana kafe). */
  const FOTO_PROMO = {
    kopi: 'img/promo-ngopi.jpg',
    hemat: 'img/promo-hemat.jpg',
    kafe: 'img/promo-mampir.jpg',
    segar: 'img/promo-segar.jpg',
    persen: 'img/promo-ngopi.jpg'
  };
  const fotoUntuk = p => {
    const kata = ((p.kode || '') + ' ' + (p.judul || '')).toUpperCase();
    if (/NGOPI|KOPI/.test(kata)) return FOTO_PROMO.kopi;
    if (/HEMAT|IRIT/.test(kata)) return FOTO_PROMO.hemat;
    if (/MAMPIR/.test(kata)) return FOTO_PROMO.kafe;
    if (/GRATIS|FREE|BONUS/.test(kata)) return FOTO_PROMO.segar;
    if (p.tipe === 'persen') return FOTO_PROMO.persen;
    return FOTO_PROMO.kafe;
  };

  const banner = document.createElement('div');
  banner.className = 'promo-banner';
  banner.setAttribute('role', 'region');
  banner.setAttribute('aria-label', 'Promo voucher yang sedang aktif');
  const slides = promos.map(p =>
    '<button type="button" class="promo-slide" data-kode="' + MK.esc(p.kode) + '" title="Klik untuk menyalin kode voucher"><img class="promo-photo" src="' + fotoUntuk(p) + '" alt="" aria-hidden="true" loading="lazy"><span class="promo-copy"><span class="promo-kicker">Voucher Promo</span><b>' + MK.esc(p.kode) + '</b><small>' + MK.esc(teksItem(p)) + '</small></span></button>'
  ).join('');
  banner.innerHTML = '<div class="promo-viewport"><div class="promo-slides">' + slides + '</div></div>'
    + (promos.length > 1
      ? '<button type="button" class="promo-nav prev" aria-label="Voucher sebelumnya">‹</button>'
        + '<button type="button" class="promo-nav next" aria-label="Voucher berikutnya">›</button>'
        + '<div class="promo-dots" aria-hidden="true">' + promos.map((_, i) => '<i' + (i === 0 ? ' class="on"' : '') + '></i>').join('') + '</div>'
      : '');

  const hero = document.querySelector('.hero');
  if (hero) { banner.classList.add('in-card'); hero.insertAdjacentElement('afterend', banner); }
  else topbar.insertAdjacentElement('afterend', banner);

  const track = banner.querySelector('.promo-slides');
  const dots = [...banner.querySelectorAll('.promo-dots i')];
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let idx = 0, timer = null, swiped = false;
  const go = i => { idx = (i + promos.length) % promos.length; track.style.transform = 'translateX(-' + (idx * 100) + '%)'; dots.forEach((d, k) => d.classList.toggle('on', k === idx)); };
  const play = () => { if (promos.length > 1 && !reduceMotion && !timer) timer = setInterval(() => go(idx + 1), 4000); };
  const stop = () => { if (timer) { clearInterval(timer); timer = null; } };

  if (promos.length > 1) {
    banner.querySelector('.promo-nav.prev').addEventListener('click', e => { e.stopPropagation(); go(idx - 1); });
    banner.querySelector('.promo-nav.next').addEventListener('click', e => { e.stopPropagation(); go(idx + 1); });
    banner.addEventListener('mouseenter', stop);
    banner.addEventListener('mouseleave', play);
    let x0 = null;
    banner.addEventListener('pointerdown', e => { x0 = e.clientX; swiped = false; stop(); });
    banner.addEventListener('pointerup', e => {
      if (x0 === null) return;
      const dx = e.clientX - x0;
      if (Math.abs(dx) > 40) { swiped = true; go(idx + (dx < 0 ? 1 : -1)); setTimeout(() => { swiped = false; }, 80); }
      x0 = null; play();
    });
    play();
  }

  banner.addEventListener('click', async e => {
    if (swiped) return;
    const item = e.target.closest('.promo-slide');
    if (!item) return;
    const kode = item.dataset.kode;
    try { await navigator.clipboard.writeText(kode); MK.toast('Kode ' + kode + ' disalin. Tempel di keranjang ya.'); }
    catch (err) { MK.toast('Kode voucher: ' + kode); }
  });
});
