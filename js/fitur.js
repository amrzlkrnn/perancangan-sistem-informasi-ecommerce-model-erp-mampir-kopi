/* MAMPIR KOPI — halaman fitur: Tentang, Kontak, FAQ, Testimoni, Blog, Promo, Lacak. */
(function () {
  const $ = s => document.querySelector(s);
  const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
  const rp = n => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
  const img = g => g || 'img/menu/default.svg';
  /* Server selalu WIB (L-m25): datetime tanpa zona diurai sebagai +07:00 dan
     diformat di zona Asia/Jakarta, bukan zona browser. */
  const tgl = s => { if (!s) return ''; const iso0 = String(s).replace(' ', 'T'); const iso = /^\d{4}-\d{2}-\d{2}$/.test(iso0) ? iso0 + 'T00:00:00' : iso0; const d = new Date(/[Zz]|[+-]\d{2}:?\d{2}$/.test(iso) ? iso : iso + '+07:00'); if (isNaN(d)) return String(s); const p = {}; new Intl.DateTimeFormat('en-US', {timeZone: 'Asia/Jakarta', day: 'numeric', month: 'numeric', year: 'numeric'}).formatToParts(d).forEach(x => { p[x.type] = x.value; }); const b = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des']; return p.day + ' ' + b[+p.month - 1] + ' ' + p.year; };
  /* D-m7: pill status yang sama dengan daftar pesanan admin. */
  const tagClass = s => ({Selesai: 'selesai', Diproses: 'diproses', Menunggu: 'menunggu', Dibatalkan: 'dibatalkan', Terverifikasi: 'selesai', 'Menunggu Verifikasi': 'menunggu', Ditolak: 'dibatalkan', Kadaluarsa: 'dibatalkan', Refund: 'diproses'}[s] || 'menunggu');
  let _csrf = null;
  async function csrf() { if (!_csrf) { const r = await fetch('api/auth.php?r=csrf'); const j = await r.json(); _csrf = j.data.token; } return _csrf; }
  async function api(url, body) {
    const opt = body ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': await csrf() }, body: JSON.stringify(body) } : {};
    const r = await fetch(url, opt); const j = await r.json().catch(() => null);
    if (!j || !j.ok) throw new Error((j && j.error) || 'Terjadi kesalahan. Coba lagi.');
    return j.data;
  }
  function pesan(el, teks, ok) { if (!el) return; el.textContent = teks; el.style.color = ok ? '#3B6E4C' : '#B9382C'; el.hidden = !teks; }

  // Penghitung kunjungan (untuk Analitik admin) — sekali per sesi per hari di sisi server.
  fetch('api/shop.php?r=kunjung').catch(() => {});

  // Info kedai (Tentang & Kontak)
  const infoEls = document.querySelectorAll('[data-info]');
  if (infoEls.length) {
    api('api/shop.php?r=info').then(info => {
      infoEls.forEach(el => { const k = el.dataset.info; if (info[k]) el.textContent = info[k]; });
      const tel = $('[data-info-tel]');
      if (tel) { if (info.telepon) { const d = info.telepon.replace(/\D/g, '').replace(/^0/, '62'); tel.href = 'https://wa.me/' + d; tel.target = '_blank'; tel.rel = 'noopener'; } else { tel.closest('p,div')?.remove(); } }
      const mail = $('[data-info-mail]');
      if (mail) { if (info.email) { mail.href = 'mailto:' + info.email; } else { mail.closest('p,div')?.remove(); } }
    }).catch(() => {});
  }

  // Form Kontak
  const formKontak = $('#formKontak');
  if (formKontak) formKontak.addEventListener('submit', async e => {
    e.preventDefault();
    const out = $('#kontakPesan');
    try {
      await api('api/shop.php?r=kontak', { nama: $('#kNama').value, email: $('#kEmail').value, pesan: $('#kPesan').value });
      formKontak.reset(); pesan(out, 'Pesanmu sudah terkirim. Terima kasih sudah menghubungi Mampir Kopi!', true);
    } catch (err) { pesan(out, err.message, false); }
  });

  // Testimoni: daftar + form kirim
  const tGrid = $('#testiGrid');
  if (tGrid) {
    const inisial = nama => nama.trim().split(/\s+/).slice(0, 2).map(k => k.charAt(0).toUpperCase()).join('') || '?';
    const avatar = x => (+x.foto_ada && +x.id_pelanggan)
      ? `<img class="testi-avatar" src="api/auth.php?r=foto_publik&id=${+x.id_pelanggan}&v=${+x.foto_ver || 0}" alt="" loading="lazy">`
      : `<span class="testi-avatar testi-inisial" aria-hidden="true">${esc(inisial(x.nama || ''))}</span>`;
    const muatTesti = () => api('api/shop.php?r=testimoni').then(rows => {
      tGrid.innerHTML = rows.length ? rows.map(x => `<article class="testi-card"><span class="bintang">${'★'.repeat(x.rating)}${'☆'.repeat(5 - x.rating)}</span><p>&ldquo;${esc(x.isi)}&rdquo;</p><div class="testi-author">${avatar(x)}<div><b>${esc(x.nama)}</b><span class="tgl">${tgl(x.dibuat)}</span></div></div></article>`).join('') : '<p class="f-note">Belum ada testimoni.</p>';
    }).catch(() => { tGrid.innerHTML = '<p class="f-note">Testimoni belum bisa dimuat.</p>'; });
    muatTesti();
    const f = $('#formTesti');
    if (f) {
      // Penilaian: bintang yang ditekan (bukan dropdown).
      const bintangBtn = f.querySelectorAll('.star-pick button');
      const isiBintang = nilai => bintangBtn.forEach(b => b.classList.toggle('on', +b.dataset.nilai <= nilai));
      bintangBtn.forEach(b => b.addEventListener('click', () => { $('#tRating').value = b.dataset.nilai; isiBintang(+b.dataset.nilai); }));
      isiBintang(5);
      f.addEventListener('submit', async e => {
        e.preventDefault();
        const out = $('#testiPesan');
        try {
          await api('api/shop.php?r=testimoni_kirim', { isi: $('#tIsi').value, rating: $('#tRating').value });
          f.reset(); isiBintang(5); pesan(out, 'Terima kasih! Testimonimu sudah tampil.', true); muatTesti();
        } catch (err) { pesan(out, err.message.includes('login') ? 'Silakan masuk dulu untuk menulis testimoni.' : err.message, false); }
      });
    }
  }

  // Blog: daftar + detail
  const bGrid = $('#blogGrid');
  if (bGrid) {
    api('api/shop.php?r=blog').then(rows => {
      bGrid.innerHTML = rows.length ? rows.map(a => `<article class="blog-card">${a.gambar ? `<img src="${esc(a.gambar)}" alt="">` : ''}<span class="tgl">${tgl(a.dibuat)}</span><h2><a href="artikel.html?slug=${encodeURIComponent(a.slug)}">${esc(a.judul)}</a></h2><p>${esc(a.ringkasan)}</p><a class="guest-directions" href="artikel.html?slug=${encodeURIComponent(a.slug)}">Baca artikel</a></article>`).join('') : '<p class="f-note">Belum ada artikel.</p>';
    }).catch(() => { bGrid.innerHTML = '<p class="f-note">Artikel belum bisa dimuat.</p>'; });
  }
  const art = $('#artikelIsi');
  if (art) {
    const slug = new URLSearchParams(location.search).get('slug') || '';
    api('api/shop.php?r=artikel&slug=' + encodeURIComponent(slug)).then(a => {
      document.title = a.judul + ' — Mampir Kopi';
      $('#artikelJudul').textContent = a.judul;
      $('#artikelTgl').textContent = tgl(a.dibuat);
      art.innerHTML = (a.gambar ? `<img src="${esc(a.gambar)}" alt="">` : '') + a.isi.split(/\n\s*\n/).map(p => `<p>${esc(p.trim())}</p>`).join('');
    }).catch(err => { art.innerHTML = '<p class="f-note">' + esc(err.message) + '</p>'; });
  }

  // Promo: daftar promo aktif + salin kode
  const pGrid = $('#promoGrid');
  if (pGrid) {
    api('api/shop.php?r=banner').then(rows => {
      pGrid.innerHTML = rows.length ? rows.map(x => {
        const nilai = x.tipe === 'persen' ? 'Diskon ' + x.nilai + '%' : 'Potongan ' + rp(x.nilai);
        const syarat = [(+x.min_transaksi > 0 ? 'min. belanja ' + rp(x.min_transaksi) : 'tanpa min. belanja'), x.berlaku_sampai ? 's/d ' + tgl(x.berlaku_sampai) : 'tanpa batas tanggal'].join(' · ');
        return `<article class="promo-card"><span class="kode">${esc(x.kode)}</span><b>${esc(x.judul || nilai)}</b><p>${nilai} · ${syarat}. Tukar kode ini di halaman pembayaran.</p><button class="btn btn-primary btn-sm" data-salir="${esc(x.kode)}">Salin Kode</button></article>`;
      }).join('') : '<p class="f-note">Tidak ada promo yang sedang berlaku.</p>';
      pGrid.querySelectorAll('[data-salir]').forEach(b => b.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(b.dataset.salir); b.textContent = 'Kode Tersalin ✓'; setTimeout(() => b.textContent = 'Salin Kode', 1800); }
        catch { b.textContent = b.dataset.salir; }
      }));
    }).catch(() => { pGrid.innerHTML = '<p class="f-note">Promo belum bisa dimuat.</p>'; });
  }

  // Lacak Pesanan
  const fLacak = $('#formLacak');
  if (fLacak) fLacak.addEventListener('submit', async e => {
    e.preventDefault();
    const out = $('#lacakPesan'), hasil = $('#lacakHasil');
    hasil.hidden = true; pesan(out, '', true);
    try {
      const d = await api('api/shop.php?r=lacak', { kode: $('#lKode').value, email: $('#lEmail').value });
      const tahap = ['Menunggu', 'Diproses', 'Selesai'];
      const idx = tahap.indexOf(d.status);
      const pilBayar = d.status_bayar ? ` <span class="tag ${tagClass(d.status_bayar)}">${esc(d.status_bayar)}</span>` : '';
      // KARTU ITEM LACAK (22 Nov 16:53, Amar): isi pesanan ditampilkan
      // sebagai kartu seperti di keranjang (foto + nama + varian + harga),
      // bukan hanya teks — read-only, tanpa tombol ubah/hapus/jumlah.
      const kartu = (d.items || []).map(it => `
        <div class="cart-item">
          <div class="cart-thumb"><img alt="" src="${esc(img(it.gambar))}"></div>
          <div class="cart-info">
            <p class="name">${esc(it.nama)}</p>
            <p class="variant">${esc([it.ukuran, it.gula, it.es].filter(Boolean).join(' · ')) || '&nbsp;'}</p>
            ${it.catatan ? '<p class="note">“' + esc(it.catatan) + '”</p>' : ''}
            <p class="price">${rp(it.subtotal)}${it.jumlah > 1 ? '<small>@ ' + rp(it.harga) + ' × ' + it.jumlah + '</small>' : ''}</p>
          </div>
        </div>`).join('');
      const isi = kartu ? `<p class="mk-meta">Isi pesanan</p>${kartu}` : '';
      $('#lacakIsi').innerHTML = d.status === 'Dibatalkan'
        ? `<h2>${esc(d.kode)}</h2><p class="mk-meta">${tgl(d.tanggal)}</p><p><span class="tag dibatalkan">Dibatalkan</span>${pilBayar}</p>${isi}<p class="f-note">Pesanan ini sudah dibatalkan. Total ${rp(d.total)} · ${esc(d.cara_ambil)}</p>`
        : `<h2>${esc(d.kode)}</h2><p class="mk-meta">${tgl(d.tanggal)}</p>
           <p><span class="tag ${tagClass(d.status)}">${esc(d.status)}</span>${pilBayar}</p>
           <div class="timeline">${tahap.map((s, i) => `<div class="step${i <= idx ? ' on' : ''}">${s}</div>`).join('')}</div>
           ${isi}
           <p class="f-note">Total ${rp(d.total)} · ${esc(d.cara_ambil)}${d.cara_ambil === 'Diantar' ? ' (sedang disiapkan untuk diantar)' : ''}</p>`;
      hasil.hidden = false;
    } catch (err) { pesan(out, err.message, false); }
  });
})();
