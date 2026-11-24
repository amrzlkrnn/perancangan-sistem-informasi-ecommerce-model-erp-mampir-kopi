const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
}[char]));
const formatPrice = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID');
const imagePath = value => value || 'img/menu/default.svg';
/* Nama tampil menu: item "Manual Brew" ditampilkan sebagai V60 (tanpa mengubah database). */
const namaTampil = value => value === 'Manual Brew' ? 'V60' : value;
fetch('api/shop.php?r=kunjung').catch(() => {});
/* Grid kartu menu dihapus dari tamu (22 Nov 2026 — diganti galeri +
   pengenalan tempat + strip andalan). Fetch menu tetap jalan untuk
   slider foto hero; bila gagal, hero tetap statis seperti sebelumnya. */
document.addEventListener('DOMContentLoaded', async () => {
  try {
    const response = await fetch('api/shop.php?r=menu');
    const result = await response.json();
    if (!response.ok || !result.ok) return;
    result.data.forEach(c => (c.menu || []).forEach(i => { i.nama = namaTampil(i.nama); }));
    // slider foto hero: semua menu bergantian (foto + nama + harga)
    if (window.initHeroSlider) window.initHeroSlider(result.data.flatMap(c => (c.menu || []).map(i => ({ nama: i.nama, gambar: i.gambar, harga: i.harga, kategori: c.nama }))));
  } catch (error) { /* hero tetap statis */ }
});

/* ===== Banner voucher SLIDER (promo aktif dari panel admin) =====
   Posisi: tepat di bawah hero halaman tamu. Klik banner = ke halaman masuk. */
document.addEventListener('DOMContentLoaded', async () => {
  const topbar = document.querySelector('.topbar');
  if (!topbar || document.querySelector('.promo-banner')) return;
  let promos;
  try {
    const response = await fetch('api/shop.php?r=banner');
    const result = await response.json();
    if (!response.ok || !result.ok) return;
    promos = result.data;
  } catch (e) { return; }
  if (!Array.isArray(promos) || !promos.length) return;

  const bln = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
  const tglSingkat = s => { const d = new Date(String(s).slice(0, 10) + 'T00:00:00'); return d.getDate() + ' ' + bln[d.getMonth()] + ' ' + d.getFullYear(); };
  const teksItem = p => {
    const bagian = [p.tipe === 'persen' ? 'Diskon ' + p.nilai + '%' : 'Potongan ' + formatPrice(p.nilai)];
    if (+p.min_transaksi > 0) bagian.push('min. belanja ' + formatPrice(p.min_transaksi));
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
  banner.title = 'Masuk atau daftar untuk memakai voucher';
  banner.style.cursor = 'pointer';
  const slides = promos.map(p =>
    '<div class="promo-slide"><img class="promo-photo" src="' + fotoUntuk(p) + '" alt="" aria-hidden="true" loading="lazy"><span class="promo-copy"><span class="promo-kicker">Voucher Promo</span><b>' + escapeHtml(p.kode) + '</b><small>' + escapeHtml(teksItem(p)) + '</small></span></div>'
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
  const play = () => { if (promos.length > 1 && !reduceMotion && !timer) timer = setInterval(() => go(idx + 1), 4000); }; /* Amar 22 Nov 16:32: voucher 4 detik (dulu 4,5 detik) */
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

  banner.addEventListener('click', () => { if (!swiped) location.href = 'login.html'; });
});

/* ===== Lightbox galeri tamu (vanilla JS, tanpa library) =====
   Delegasi klik di .galeri-grid + daftar item dibaca live tiap tampil:
   grid galeri dirender ulang oleh js/konten.js dari API, jadi binding
   per-tombol saat load akan mati untuk tombol hasil render. */
document.addEventListener('DOMContentLoaded', () => {
  const grid = document.querySelector('.galeri-grid');
  const box = document.getElementById('lightbox');
  if (!grid || !box) return;
  const items = () => [...document.querySelectorAll('.galeri-item')];
  const img = document.getElementById('lightboxImg');
  const cap = document.getElementById('lightboxCap');
  let idx = 0;
  const tampil = i => {
    const daftar = items();
    if (!daftar.length) return;
    idx = (i + daftar.length) % daftar.length;
    const it = daftar[idx];
    img.src = it.dataset.foto;
    img.alt = it.dataset.nama;
    cap.textContent = it.dataset.nama + ' — ' + (idx + 1) + ' / ' + daftar.length;
  };
  const buka = i => {
    tampil(i);
    box.classList.add('buka');
    document.body.style.overflow = 'hidden';
    box.querySelector('.lightbox-tutup').focus();
  };
  const tutup = () => { box.classList.remove('buka'); document.body.style.overflow = ''; };
  grid.addEventListener('click', e => {
    const it = e.target.closest('.galeri-item');
    if (!it) return;
    buka(items().indexOf(it));
  });
  box.querySelector('.lightbox-tutup').addEventListener('click', tutup);
  box.querySelector('.lightbox-nav.prev').addEventListener('click', e => { e.stopPropagation(); tampil(idx - 1); });
  box.querySelector('.lightbox-nav.next').addEventListener('click', e => { e.stopPropagation(); tampil(idx + 1); });
  box.addEventListener('click', e => { if (e.target === box) tutup(); });
  document.addEventListener('keydown', e => {
    if (!box.classList.contains('buka')) return;
    if (e.key === 'Escape') tutup();
    else if (e.key === 'ArrowLeft') tampil(idx - 1);
    else if (e.key === 'ArrowRight') tampil(idx + 1);
  });
});
