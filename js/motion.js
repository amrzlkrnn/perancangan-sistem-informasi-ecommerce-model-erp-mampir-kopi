/* ============================================================
   MAMPIR KOPI — mesin gerak (port ringkas engine /awwwards)
   Satu jam (GSAP ticker), tanpa Lenis: aplikasi ini punya
   scroll yang digerakkan JS (shop.js), jadi smooth-scroll
   library hanya akan bertarung dengannya. Scroll native +
   ScrollTrigger sudah cukup untuk brief restoran ini.
   Semua keadaan awal yang tersembunyi diterapkan LEWAT JS,
   jadi tanpa JS / reduced motion semua konten tetap terlihat.
   ============================================================ */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var hasGsap = typeof window.gsap !== 'undefined';
  var docEl = document.documentElement;

  /* ---------- 1. Penggerak cahaya hari (satu variabel global) ---------- */
  function daypart(h) {
    if (h >= 6 && h < 11) return 'pagi';
    if (h >= 11 && h < 15) return 'siang';
    if (h >= 15 && h < 19) return 'sore';
    return 'malam';
  }
  var DAYPART_LABEL = { pagi: 'Pagi di kedai', siang: 'Siang di kedai', sore: 'Sore di kedai', malam: 'Malam di kedai' };
  function applyDaypart() {
    var dp = daypart(new Date().getHours());
    docEl.setAttribute('data-daypart', dp);
    document.querySelectorAll('.daypart-chip').forEach(function (chip) {
      var label = chip.querySelector('.daypart-label');
      if (label) label.textContent = DAYPART_LABEL[dp];
      chip.hidden = false;
    });
  }
  applyDaypart();
  setInterval(applyDaypart, 10 * 60 * 1000);

  /* ---------- 1b. Tab bar HP: tandai tab halaman ini ----------
     (ditunda sampai DOMContentLoaded — nav .tabbar ada di akhir <body>,
     setelah tag <script> ini, jadi belum ada saat IIFE ini jalan) */
  function markTabbar() {
    var bar = document.querySelector('.tabbar'); if (!bar) return;
    var page = (location.pathname.split('/').pop() || '').split('?')[0] || 'tamu.html';
    var map = { 'pesanan.html': 'menu.html', 'pembayaran.html': 'keranjang.html', 'artikel.html': 'blog.html' };
    var target = map[page] || page;
    bar.querySelectorAll('a').forEach(function (a) {
      if (a.getAttribute('href') === target) { a.classList.add('active'); a.setAttribute('aria-current', 'page'); }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', markTabbar);
  else markTabbar();

  /* ---------- 1c. Slider foto hero: semua menu bergantian ----------
     Dipanggil shop.js (dashboard) / guest.js (tamu) setelah menu
     dimuat, dengan daftar datar {nama, gambar, harga, kategori}.
     Perilaku meniru slider voucher: tiap 3 detik (Amar 22 Nov 16:32:
     "transisi menu jadi 3 detik aja"; dulu 6 detik), panah ‹ ›,
     swipe, jeda saat hover; reduced motion = diam di slide pertama
     (panah tetap bisa diklik). Tanpa JS/menu, hero tetap statis
     menampilkan Kopi Susu Mampir seperti semula. Harus di sini,
     SEBELUM early return di bawah — slider jalan tanpa GSAP juga
     (ganti instan tanpa fade). DENGAN GSAP: crossfade lewat lapisan
     kedua (tidak ada frame kosong) + bar progres mengisi 0→100%
     selama satu slide tampil. */
  window.initHeroSlider = function (items) {
    var fig = document.querySelector('.hero-media');
    if (!fig || fig.dataset.heroSlider) return; /* sekali saja */
    if (!Array.isArray(items) || items.length < 2) return;
    var photo = fig.querySelector('.hero-photo');
    var img = fig.querySelector('img');
    var cap = fig.querySelector('.hero-media-cap');
    if (!photo || !img || !cap) return;
    var capName = cap.querySelector('span'), capPrice = cap.querySelector('b');
    if (!capName || !capPrice) return;
    fig.dataset.heroSlider = '1';

    var idx = 0, timer = null, barTween = null;
    var SLIDE_MS = 3000; /* Amar 22 Nov 16:32: transisi menu 3 detik (dulu 6 detik) */
    var price = function (n) { return 'Rp' + Number(n || 0).toLocaleString('id-ID'); };
    var label = function (m, i) { return m.nama + ' — ' + (i === 0 ? 'andalan kami' : (m.kategori || 'Menu')); };

    /* Lapisan crossfade: foto baru muncul DI ATAS foto lama (bukan
       foto lama memudar ke kosong dulu — kedip itu yang terasa murahan).
       Radius disalin dari foto utama supaya sama di semua halaman. */
    var overlay = document.createElement('img');
    overlay.className = 'hero-slide-next';
    overlay.alt = '';
    overlay.setAttribute('aria-hidden', 'true');
    photo.appendChild(overlay);
    try { overlay.style.borderRadius = window.getComputedStyle(img).borderRadius; } catch (e) {}

    var prev = document.createElement('button');
    prev.type = 'button'; prev.className = 'hero-nav prev';
    prev.setAttribute('aria-label', 'Menu sebelumnya'); prev.textContent = '‹';
    var next = document.createElement('button');
    next.type = 'button'; next.className = 'hero-nav next';
    next.setAttribute('aria-label', 'Menu berikutnya'); next.textContent = '›';
    photo.appendChild(prev); photo.appendChild(next);
    var countWrap = document.createElement('div');
    countWrap.className = 'hero-count'; countWrap.setAttribute('aria-hidden', 'true');
    countWrap.innerHTML = '<span class="hero-count-num"><span class="hero-count-now">01</span><span class="hero-count-total"> / ' + items.length + '</span></span><span class="hero-progress"><i></i></span>';
    cap.appendChild(countWrap);
    var countNow = countWrap.querySelector('.hero-count-now');
    var countBar = countWrap.querySelector('.hero-progress i');

    /* preload: foto grid menu sudah di-cache browser, jadi ini hampir gratis */
    items.forEach(function (m) { var p = new Image(); p.src = m.gambar || 'img/menu/default.svg'; });

    var apply = function () {
      var m = items[idx];
      img.src = m.gambar || 'img/menu/default.svg';
      img.alt = idx === 0 ? 'Kopi Susu Mampir, menu andalan Mampir Kopi' : m.nama + ', menu Mampir Kopi';
      capName.textContent = label(m, idx);
      capPrice.textContent = price(m.harga);
      countNow.textContent = (idx + 1 < 10 ? '0' : '') + (idx + 1);
    };
    /* Bar progres mengisi 0→100% selama satu slide tampil (bukan lompat
       per slide) — bar yang bergerak memberi tahu kapan foto berganti. */
    var startBar = function () {
      if (!hasGsap || reduceMotion) {
        countBar.style.width = ((idx + 1) / items.length * 100) + '%';
        return;
      }
      if (barTween) barTween.kill();
      barTween = window.gsap.fromTo(countBar, { width: '0%' },
        { width: '100%', duration: SLIDE_MS / 1000, ease: 'none', overwrite: true });
    };
    var go = function (i, manual) {
      idx = (i + items.length) % items.length;
      if (manual) { stop(); play(); } /* klik/swipe: timer + bar mulai lagi */
      if (reduceMotion || !hasGsap) { apply(); return; }
      var m = items[idx];
      fig.setAttribute('data-hero-sliding', '1');
      overlay.src = m.gambar || 'img/menu/default.svg';
      countNow.textContent = (idx + 1 < 10 ? '0' : '') + (idx + 1);
      /* Caption: teks lama naik sedikit sambil memudar, teks baru masuk
         dari bawah — counter dan bar progres tidak ikut berkedip. */
      window.gsap.to([capName, capPrice], { autoAlpha: 0, y: -6, duration: 0.12,
        ease: 'power1.in', overwrite: 'auto', onComplete: function () {
          capName.textContent = label(m, idx);
          capPrice.textContent = price(m.harga);
          window.gsap.fromTo([capName, capPrice], { y: 8 },
            { autoAlpha: 1, y: 0, duration: 0.25, ease: 'power2.out', overwrite: 'auto' });
        }});
      var faded = false;
      var fadeIn = function () {
        if (faded) return; faded = true;
        window.gsap.to(overlay, { opacity: 1, duration: 0.4, ease: 'power2.out',
          overwrite: 'auto', onComplete: function () {
            apply(); /* foto utama kini yang baru; lapisan disembunyikan */
            window.gsap.set(overlay, { opacity: 0 });
            fig.removeAttribute('data-hero-sliding');
            startBar();
          }});
      };
      if (overlay.complete) fadeIn();
      else { overlay.onload = fadeIn; setTimeout(fadeIn, 700); }
    };
    var play = function () {
      if (reduceMotion) return;
      if (!timer) timer = setInterval(function () { go(idx + 1); }, SLIDE_MS);
      if (barTween) barTween.play();
    };
    var stop = function () {
      if (timer) { clearInterval(timer); timer = null; }
      if (barTween) barTween.pause();
    };

    prev.addEventListener('click', function () { go(idx - 1, true); });
    next.addEventListener('click', function () { go(idx + 1, true); });
    fig.addEventListener('mouseenter', stop);
    fig.addEventListener('mouseleave', play);
    var x0 = null;
    fig.addEventListener('pointerdown', function (e) { x0 = e.clientX; stop(); });
    fig.addEventListener('pointerup', function (e) {
      if (x0 === null) return;
      var dx = e.clientX - x0;
      if (Math.abs(dx) > 40) go(idx + (dx < 0 ? 1 : -1), true);
      x0 = null; play();
    });
    play();
    startBar();
  };

  /* ---------- 1c. Mode gelap (22 Nov, Amar) ----------
     Satu-satunya pengalih tema: atribut data-tema di <html>, dibaca CSS
     css/tema-gelap.css. Terang = default (tanpa atribut). Harus di sini,
     SEBELUM early return — tema jalan tanpa GSAP juga. Dipanggil dari
     js/shop.js (halaman user: blok AKUN + radio profil) DAN dari §1d
     di bawah (semua halaman tamu, Amar 22 Nov 16:54: mode gelap
     berlaku di semua halaman saat sudah login, kecuali admin). */
  window.terapkanTema = function (me) {
    if (me && me.pref_tema === 'gelap') docEl.setAttribute('data-tema', 'gelap');
    else docEl.removeAttribute('data-tema');
  };
  /* Cache tema di localStorage (22 Nov 16:34, Amar: kilatan putih
     sepersekian detik saat mode gelap). Script inline di <head> halaman
     user membaca cache ini SEBELUM cat pertama, jadi halaman gelap
     langsung gelap tanpa menunggu fetch `me`. Server tetap sumber
     kebenaran: cache HANYA ditulis dari respons server (login, blok
     AKUN, segarkan profil) — TIDAK dari radio yang belum disimpan;
     dihapus saat keluar agar tidak bocor ke akun berikutnya. */
  window.simpanTemaLokal = function (v) {
    try { localStorage.setItem('mk_tema', v === 'gelap' ? 'gelap' : 'terang'); } catch (e) {}
  };
  window.hapusTemaLokal = function () {
    try { localStorage.removeItem('mk_tema'); } catch (e) {}
  };

  /* ---------- 1d. Header tamu sadar login (22 Nov, Amar) ----------
     Bila sudah masuk, tombol Daftar/Masuk di pojok kanan atas disembunyikan
     dan diganti menu akun (avatar + nama + dropdown), sama seperti di
     halaman user; tab Masuk di tabbar HP diganti Profil; logo Mampir
     Kopi mengarah ke beranda akun (22 Nov 16:44); tema akun diterapkan
     ke SEMUA halaman tamu (22 Nov 16:54: semua konsisten ke darkmode
     saat sudah login, kecuali admin). Di lacak.html SAJA headernya
     menjadi header user lengkap — nav Beranda/Menu/Keranjang/Riwayat/
     Lacak/Wishlist + tabbar pengguna (22 Nov 16:51). Bila belum masuk,
     header dibiarkan apa adanya dan tema dipaksa terang (cache basi
     dibersihkan) — tamu logout tidak pernah melihat halaman gelap.
     Harus di sini, SEBELUM early return — header jalan tanpa GSAP juga. */
  document.addEventListener('DOMContentLoaded', function () {
    var auth = document.querySelector('.guest-auth');
    var box = document.querySelector('.guest-controls .nav-user');
    var tabMasuk = document.querySelector('.tabbar a[href="login.html"]');
    var isLacak = /lacak\.html$/.test(location.pathname);
    if (!auth && !box && !tabMasuk) return;
    fetch('api/auth.php?r=me').then(function (r) { return r.json(); }).then(function (j) {
      var me = j && j.ok ? j.data : null;
      if (!me) {
        /* Belum login: paksa terang + bersihkan cache basi (22 Nov
           16:54) — tamu logout tidak pernah melihat halaman gelap
           dari cache akun sebelumnya (sesi kedaluwarsa tanpa logout).
           Login berikutnya menulis ulang cache dari respons server. */
        docEl.removeAttribute('data-tema');
        window.hapusTemaLokal();
        return;
      }
      /* Logo Mampir Kopi (Amar 22 Nov 16:44): saat sudah login, logo
         kembali ke beranda akun (dasbor user / panel admin), bukan ke
         tamu. Saat belum login href statisnya (tamu.html) dibiarkan. */
      var brand = document.querySelector('.brand');
      if (brand) brand.href = me.role === 'admin' ? 'admin.html' : 'dashboard.html';
      /* MODE GELAP GLOBAL (Amar 22 Nov 16:54): saat sudah login, tema
         akun diterapkan di SEMUA halaman tamu — bukan hanya lacak.
         Admin tidak punya pref_tema sehingga terapkanTema mengembalikan
         terang; admin.html sendiri tidak memuat tema-gelap.css.
         Diterapkan di sini karena halaman tamu tidak memuat shop.js
         (blok AKUN tidak jalan di sana). */
      window.terapkanTema(me);
      window.simpanTemaLokal(me.pref_tema);
      /* LACAK = HEADER USER saat sudah login (Amar 22 Nov 16:51):
         HANYA lacak yang menampilkan nav pengguna lengkap + tabbar
         pengguna ("samain kayak gambar yg di kiri"). Halaman tamu
         lain tetap berheader tamu (tanpa nav user). */
      if (isLacak) {
        var navUser = document.querySelector('.nav-links');
        if (navUser) navUser.hidden = false;
        var tabTamu = document.querySelector('.tabbar-guest');
        var tabUser = document.querySelector('.tabbar-user');
        if (tabTamu) tabTamu.hidden = true;
        if (tabUser) tabUser.hidden = false;
      }
      if (tabMasuk) {
        var label = tabMasuk.querySelector('span');
        if (me.role === 'admin') { tabMasuk.href = 'admin.html'; if (label) label.textContent = 'Admin'; }
        else { tabMasuk.href = 'profil.html'; if (label) label.textContent = 'Profil'; }
      }
      if (!auth || !box) return;
      var depan = (me.nama || '?').split(' ')[0];
      var avatar = box.querySelector('.avatar');
      if (me.foto_ada) avatar.innerHTML = '<img src="api/auth.php?r=foto&v=' + (me.foto_ver || 1) + '" alt="">';
      else avatar.textContent = depan.charAt(0).toUpperCase();
      avatar.nextElementSibling.textContent = 'Halo, ' + depan;
      auth.hidden = true;
      box.hidden = false;
      box.style.cursor = 'pointer';
      box.title = 'Akun saya';
      var menu = document.createElement('div');
      menu.className = 'user-menu'; menu.hidden = true;
      /* Dropdown akun: di lacak, Beranda TIDAK ditampilkan (Amar 22 Nov
         16:51: "ngapain ada beranda di profile pas di section lacak")
         karena Beranda sudah ada di nav — sama seperti halaman user
         (shop.js AKUN: Edit Profil + Keluar saja). Di halaman tamu
         lain Beranda tetap ada karena nav-nya tidak punya Beranda. */
      menu.innerHTML = (me.role === 'admin'
        ? '<a href="admin.html">Panel Admin</a>'
        : (isLacak ? '' : '<a href="dashboard.html">Beranda</a>') + '<a href="profil.html">Edit Profil</a>')
        + '<button type="button" class="user-logout">Keluar</button>';
      box.appendChild(menu);
      box.addEventListener('click', function (e) {
        if (e.target.closest('.user-menu a') || e.target.closest('.user-logout')) return;
        menu.hidden = !menu.hidden;
      });
      menu.querySelector('.user-logout').addEventListener('click', function () {
        if (!confirm('Keluar dari akun ' + me.nama + '?')) return;
        fetch('api/auth.php?r=csrf').then(function (r) { return r.json(); }).then(function (cj) {
          return fetch('api/auth.php?r=logout', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cj.data.token }, body: '{}' });
        }).catch(function () {}).then(function () { if (window.hapusTemaLokal) window.hapusTemaLokal(); location.href = 'tamu.html'; });
      });
      document.addEventListener('click', function (e) { if (!box.contains(e.target)) menu.hidden = true; });
    }).catch(function () { /* header tamu tetap tampil apa adanya */ });
  });

  if (!hasGsap || reduceMotion) return; /* gerak berhenti di sini; konten tetap penuh */

  var gsap = window.gsap;
  if (window.ScrollTrigger) gsap.registerPlugin(window.ScrollTrigger);
  var ST = window.ScrollTrigger || null;
  var EASE_OUT = 'expo.out', EASE_SOFT = 'power3.out';

  /* Tirai transisi antar-halaman DIHAPUS (permintaan Amar 16 Nov 2026):
     lingkaran iris sering membuat halaman macet saat back/kembali —
     tirai tertinggal menutupi layar bila flag sessionStorage sudah
     terpakai saat load pertama. Navigasi kini langsung tanpa
     intersepsi klik. */

  /* ---------- 3. Intro hero ---------- */
  function playIntro() {
    var hero = document.querySelector('.hero[data-intro]');
    if (!hero) return;
    var h1 = hero.querySelector('h1');
    var media = hero.querySelector('.hero-media');
    var tl = gsap.timeline({ defaults: { ease: EASE_OUT } });
    /* SplitText hanya untuk hero tamu (teks statis). H1 dashboard diganti
       shop.js saat runtime ("Halo, <Nama>!") — split akan hancur, jadi
       pakai tween elemen biasa yang tahan terhadap pergantian teks. */
    var useSplit = window.SplitText && document.body.classList.contains('guest-page');
    if (h1 && useSplit) {
      var split = new window.SplitText(h1, { type: 'lines', mask: 'lines' });
      tl.from(split.lines, { yPercent: 110, duration: 0.6, stagger: 0.04 }, 0);
    } else if (h1) {
      tl.from(h1, { y: 24, autoAlpha: 0, duration: 0.6 }, 0);
    }
    tl.from(hero.querySelector('.hero-kicker'), { y: 14, autoAlpha: 0, duration: 0.7 }, 0.05)
      .from(hero.querySelectorAll('.hero-content p:not(.hero-kicker), .hero-meta'),
        { y: 18, autoAlpha: 0, duration: 0.8, stagger: 0.07 }, 0.22);
    /* Tombol dipisah dari tween stagger di atas: from() pada tombol ini —
       baik di dalam stagger maupun mandiri di timeline — selesai dengan
       y tetap 20px (opacity-nya normal), sehingga tombol menumpuk teks
       .hero-meta. set()+to() tidak membaca nilai akhir dari elemen, jadi
       tidak kena masalah yang sama (terverifikasi 2026-11-15). */
    var cta = hero.querySelector('.hero-content .btn');
    if (cta) {
      gsap.set(cta, { y: 20, autoAlpha: 0 });
      tl.to(cta, { y: 0, autoAlpha: 1, duration: 0.9 }, 0.33);
    }
    if (media) {
      tl.from(media.querySelector('img'), { clipPath: 'inset(100% 0 0 0)', duration: 0.7, ease: 'expo.inOut' }, 0.15)
        .from(media.querySelector('.hero-media-cap'), { autoAlpha: 0, y: 10, duration: 0.6 }, 0.6);
    }
  }

  /* ---------- 4. Reveal saat scroll ----------
     Upgrade 22 Nov: naiknya lebih pendek (24→16px) dan lebih cepat
     (1s→0,8s) dengan ease yang lebih lembut — reveal lama terasa
     melayang dan monoton karena semua elemen geraknya persis sama.
     Diringankan lagi 22 Nov (Amar: "animasinya masih kayak berat"):
     naik 16→8px, durasi 0,8→0,5s. */
  function initReveals() {
    if (!ST) return;
    gsap.utils.toArray('[data-reveal]').forEach(function (el) {
      gsap.from(el, {
        y: 8, autoAlpha: 0, duration: 0.5, ease: EASE_SOFT,
        scrollTrigger: { trigger: el, start: 'top 88%', once: true }
      });
    });
  }

  /* Kartu menu dirender async oleh shop.js/guest.js: amati dan animasikan
     kartu yang baru muncul, masing-masing dengan trigger-nya sendiri.
     Kartu sebaris diberi stagger kecil (indeks dalam grid % 4 × 0,03s)
     supaya satu baris naik bergelombang, bukan serentak. Diringankan
     22 Nov: y 16→8, scale 0,97→0,99, durasi 0,75→0,5s. */
  function watchMenuCards() {
    if (!ST) return;
    var seen = new WeakSet();
    function arm(card) {
      if (seen.has(card)) return; seen.add(card);
      var sibs = card.parentNode ? Array.prototype.indexOf.call(card.parentNode.children, card) : 0;
      gsap.from(card, {
        y: 8, scale: 0.99, autoAlpha: 0, duration: 0.5, ease: EASE_SOFT,
        delay: (sibs % 4) * 0.03,
        scrollTrigger: { trigger: card, start: 'top 94%', once: true }
      });
    }
    document.querySelectorAll('.product-card').forEach(arm);
    var obs = new MutationObserver(function (muts) {
      muts.forEach(function (m) {
        m.addedNodes.forEach(function (n) {
          if (n.nodeType !== 1) return;
          if (n.classList && n.classList.contains('product-card')) arm(n);
          if (n.querySelectorAll) n.querySelectorAll('.product-card').forEach(arm);
          /* label kategori yang baru dirender ikut di-reveal (sekali saja) */
          if (n.querySelectorAll) n.querySelectorAll('.category-label, .guest-category h3').forEach(function (h) {
            if (seen.has(h)) return; seen.add(h);
            gsap.from(h, { y: 8, autoAlpha: 0, duration: 0.5, ease: EASE_SOFT,
              scrollTrigger: { trigger: h, start: 'top 92%', once: true } });
          });
        });
      });
      /* JANGAN panggil ST.refresh() di sini: ScrollTrigger.refresh() menambah
         lalu menghapus elemen ukur sementara di <body> — mutasi itu memicu
         observer ini lagi, dan putaran itu tidak pernah berhenti (membekukan
         thread utama; terverifikasi 2026-11-15). Trigger baru sudah mengukur
         posisinya sendiri saat dibuat; refresh global cukup sekali saat load. */
    });
    obs.observe(document.body, { childList: true, subtree: true });
  }

  /* Pratinjau menu melayang DIHAPUS (permintaan Amar 16 Nov 2026):
     kotak foto yang mengikuti kursor saat hover baris menu. */

  /* ---------- 6. Shared element: foto kartu -> foto detail ---------- */
  document.addEventListener('click', function (e) {
    var card = e.target.closest('a.product-card[href*="pesanan.html?item="]');
    if (!card) return;
    var img = card.querySelector('.product-img');
    if (!img) return;
    var r = img.getBoundingClientRect();
    try {
      sessionStorage.setItem('mkFlip', JSON.stringify({
        src: img.getAttribute('src'),
        left: r.left, top: r.top, width: r.width, height: r.height
      }));
    } catch (err) {}
  });

  function playFlip() {
    var photo = document.getElementById('itemPhoto');
    if (!photo) return;
    var flip = null;
    try { flip = JSON.parse(sessionStorage.getItem('mkFlip') || 'null'); sessionStorage.removeItem('mkFlip'); } catch (e) {}
    var run = function () {
      var img = photo.querySelector('img');
      if (!img) return;
      var revealRest = function () {
        gsap.from('#itemName, #itemPrice, #itemDesc', { y: 18, autoAlpha: 0, duration: 0.5, stagger: 0.03, ease: EASE_SOFT });
        gsap.from('.detail-page .customize-layout > .customize-card:last-child',
          { y: 20, autoAlpha: 0, duration: 0.9, ease: EASE_SOFT, delay: 0.1 });
      };
      if (!flip || img.getAttribute('src') !== flip.src) {
        gsap.from(img, { clipPath: 'inset(0 0 100% 0)', duration: 1.1, ease: 'expo.inOut' });
        revealRest();
        return;
      }
      /* FLIP: dari rect kartu ke rect foto detail. Skala SERAGAM (bukan
         scaleX/scaleY terpisah — itu meregangkan foto selama terbang
         karena rasio aspek kartu dan detail berbeda). Foto tidak pernah
         gepeng; wadahnya yang meng-crop selama transisi. */
      var t = img.getBoundingClientRect();
      var dx = flip.left - t.left, dy = flip.top - t.top;
      var s = flip.width / t.width;
      gsap.fromTo(img,
        { x: dx, y: dy, scale: s, transformOrigin: '0 0', borderRadius: '16px' },
        { x: 0, y: 0, scale: 1, borderRadius: '20px', duration: 0.6, ease: 'power3.inOut',
          onComplete: function () { gsap.set(img, { clearProps: 'all' }); } });
      revealRest();
    };
    if (photo.querySelector('img')) {
      var im = photo.querySelector('img');
      if (im.complete) run(); else im.addEventListener('load', run, { once: true });
    } else {
      var obs = new MutationObserver(function () {
        var im2 = photo.querySelector('img');
        if (!im2) return;
        obs.disconnect();
        if (im2.complete) run(); else im2.addEventListener('load', run, { once: true });
      });
      obs.observe(photo, { childList: true });
    }
  }

  /* ---------- Boot ---------- */
  function boot() {
    playIntro();
    initReveals();
    watchMenuCards();
    playFlip();
    window.addEventListener('load', function () { if (ST) ST.refresh(); });
    /* Pengaman reveal (kritikus 2.0): heading "Katalog Menu" dashboard
       tak pernah muncul — posisi trigger diukur saat boot, lalu konten
       async (menu/stat) menggeser layout dan triggernya tak pernah
       tercapai. Refresh sekali lagi setelah konten settle, dan paksa
       tampilkan elemen [data-reveal] yang masih tersembunyi setelah
       2,5 detik. Sekali jalan, bukan di observer (lihat larangan di atas). */
    setTimeout(function () { if (ST) ST.refresh(); }, 1200);
    setTimeout(function () {
      if (!ST) return;
      gsap.utils.toArray('[data-reveal]').forEach(function (el) {
        /* Ronde 5: cek visibility juga, bukan cuma opacity. Animasi CSS lama
           (fill both) bisa menimpa opacity inline GSAP sehingga computed
           opacity = 1 sementara visibility tetap hidden — elemen tak
           terlihat tapi lolos dari cek lama (section-head dashboard). */
        var cs = window.getComputedStyle(el);
        if (cs.opacity !== '0' && cs.visibility !== 'hidden') return;
        ST.getAll().forEach(function (t) { if (t.trigger === el) t.kill(); });
        gsap.killTweensOf(el);
        gsap.set(el, { clearProps: 'transform,opacity,visibility' });
      });
    }, 2500);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
