/* ===== CMS KONTEN — loader publik (22 Nov 2026) =====
   Satu loader bersama untuk semua halaman yang kontennya bisa diedit
   Super Admin dari panel admin (tamu, dashboard, tentang, faq, syarat,
   privasi, login). Cara kerja:
   - Ambil api/shop.php?r=konten sekali saat halaman dimuat.
   - [data-konten="kunci"] -> textContent diganti dari API.
   - [data-konten-paragraf="kunci"] -> anak <p> diganti per paragraf
     (pisah baris kosong); anak non-<p> (mis. tautan) dipertahankan.
   - [data-konten-list="kunci"] -> <li> diganti per baris.
   - [data-konten-md="kunci"] -> markdown-lite: "# " = subjudul, "- " =
     item list, baris kosong = paragraf baru.
   - .faq-list, .galeri-grid, .andalan-grid dirender ulang dari API.
   FALLBACK: teks hardcode di HTML tetap ada. Bila fetch gagal, key
   kosong, atau JS mati, halaman menampilkan teks lama seperti semula.
   Semua output memakai textContent / createElement — tidak ada innerHTML
   dari teks mentah (anti-XSS). */
(function () {
  'use strict';
  var formatPrice = function (v) { return 'Rp ' + Number(v || 0).toLocaleString('id-ID'); };
  var bersih = function (s) { return String(s == null ? '' : s).trim(); };

  /* Markdown-lite -> simpul DOM. "# " = h3 (gayanya sama dengan h2 di
     .prose), "- " = item <ul>, blok teks = <p>. */
  function renderMarkdown(wadah, teks) {
    var baris = String(teks).split(/\r?\n/);
    var frag = document.createDocumentFragment();
    var para = [], list = [];
    var siramPara = function () {
      if (!para.length) return;
      var p = document.createElement('p');
      p.textContent = para.join(' ');
      frag.appendChild(p);
      para = [];
    };
    var siramList = function () {
      if (!list.length) return;
      var ul = document.createElement('ul');
      list.forEach(function (item) {
        var li = document.createElement('li');
        li.textContent = item;
        ul.appendChild(li);
      });
      frag.appendChild(ul);
      list = [];
    };
    baris.forEach(function (b) {
      var t = b.trim();
      if (t.indexOf('# ') === 0) {
        siramPara(); siramList();
        var h = document.createElement('h3');
        h.textContent = t.slice(2).trim();
        frag.appendChild(h);
      } else if (t.indexOf('- ') === 0) {
        siramPara();
        list.push(t.slice(2).trim());
      } else if (t === '') {
        siramPara(); siramList();
      } else {
        siramList();
        para.push(t);
      }
    });
    siramPara(); siramList();
    wadah.replaceChildren(frag);
  }

  function renderParagraf(wadah, teks) {
    var blok = String(teks).split(/\n\s*\n/).map(bersih).filter(Boolean);
    if (!blok.length) return;
    var jangkar = null;
    Array.prototype.slice.call(wadah.children).forEach(function (el) {
      if (el.tagName === 'P') wadah.removeChild(el);
      else if (!jangkar) jangkar = el;
    });
    blok.forEach(function (b) {
      var p = document.createElement('p');
      p.textContent = b;
      if (jangkar) wadah.insertBefore(p, jangkar);
      else wadah.appendChild(p);
    });
  }

  function renderList(ul, teks) {
    var item = String(teks).split(/\r?\n/).map(bersih).filter(Boolean);
    if (!item.length) return;
    ul.replaceChildren();
    item.forEach(function (t) {
      var li = document.createElement('li');
      li.textContent = t;
      ul.appendChild(li);
    });
  }

  function renderFaq(box, rows) {
    box.replaceChildren();
    rows.forEach(function (f) {
      var d = document.createElement('details');
      d.className = 'faq-item';
      var s = document.createElement('summary');
      s.textContent = f.pertanyaan;
      var b = document.createElement('div');
      b.className = 'faq-body';
      b.textContent = f.jawaban;
      d.appendChild(s);
      d.appendChild(b);
      box.appendChild(d);
    });
  }

  function renderGaleri(grid, rows) {
    var peta = grid.querySelector('.galeri-peta');
    Array.prototype.slice.call(grid.querySelectorAll('.galeri-item')).forEach(function (el) { el.remove(); });
    rows.forEach(function (g) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'galeri-item' + (+g.lebar ? ' galeri-lebar' : '');
      btn.dataset.foto = g.gambar;
      btn.dataset.nama = g.judul;
      btn.setAttribute('aria-label', 'Perbesar foto ' + g.judul);
      var img = document.createElement('img');
      img.src = g.gambar;
      img.alt = g.judul;
      img.loading = 'lazy';
      var cap = document.createElement('span');
      cap.className = 'galeri-nama';
      cap.textContent = g.judul;
      btn.appendChild(img);
      btn.appendChild(cap);
      if (peta) grid.insertBefore(btn, peta);
      else grid.appendChild(btn);
    });
  }

  function renderAndalan(grid, rows) {
    grid.replaceChildren();
    rows.forEach(function (m) {
      var fig = document.createElement('figure');
      fig.className = 'andalan-item';
      var img = document.createElement('img');
      img.src = m.gambar;
      img.alt = m.nama;
      img.loading = 'lazy';
      var cap = document.createElement('figcaption');
      var nama = document.createElement('span');
      nama.className = 'andalan-nama';
      nama.textContent = m.nama;
      var harga = document.createElement('span');
      harga.className = 'andalan-harga';
      harga.textContent = formatPrice(m.harga);
      cap.appendChild(nama);
      cap.appendChild(harga);
      fig.appendChild(img);
      fig.appendChild(cap);
      grid.appendChild(fig);
    });
    /* Admin mengosongkan keempat pilihan = strip disembunyikan. */
    var strip = grid.closest('.andalan-strip');
    if (strip) strip.style.display = rows.length ? '' : 'none';
  }

  /* Sapaan dashboard: template hero_dash_judul boleh memakai {nama}
     (diganti nama depan pelanggan dari endpoint me). Bila template
     tidak mengandung {nama}, langsung dipakai apa adanya. Flag
     window.__kontenSapa mencegah shop.js menimpa dengan sapaan baku. */
  function terapkanSapaan(el, template) {
    if (template.indexOf('{nama}') === -1) {
      el.textContent = template;
      window.__kontenSapa = true;
      return;
    }
    fetch('api/auth.php?r=me').then(function (r) { return r.json(); }).then(function (j) {
      var nama = (j && j.data && j.data.nama) ? String(j.data.nama).split(' ')[0] : '';
      if (!nama) return;
      el.textContent = template.split('{nama}').join(nama);
      window.__kontenSapa = true;
    }).catch(function () { /* biarkan sapaan baku shop.js */ });
  }

  document.addEventListener('DOMContentLoaded', function () {
    /* Login: tunggu fetch CSRF halaman selesai dulu (lihat login.html)
       supaya request ini memakai sesi yang sama dengan token CSRF. */
    var siap = (window.__csrfReady && window.__csrfReady.then) ? window.__csrfReady.catch(function () {}) : Promise.resolve();
    siap.then(function () {
    fetch('api/shop.php?r=konten').then(function (r) { return r.json(); }).then(function (j) {
      if (!j || !j.ok || !j.data) return;
      var data = j.data;
      window.__konten = data;
      var teks = data.teks || {};
      document.querySelectorAll('[data-konten]').forEach(function (el) {
        var v = bersih(teks[el.getAttribute('data-konten')]);
        if (!v) return;
        if (el.getAttribute('data-konten') === 'hero_dash_judul') terapkanSapaan(el, v);
        else el.textContent = v;
      });
      document.querySelectorAll('[data-konten-paragraf]').forEach(function (el) {
        var v = bersih(teks[el.getAttribute('data-konten-paragraf')]);
        if (v) renderParagraf(el, v);
      });
      document.querySelectorAll('[data-konten-list]').forEach(function (el) {
        var v = bersih(teks[el.getAttribute('data-konten-list')]);
        if (v) renderList(el, v);
      });
      document.querySelectorAll('[data-konten-md]').forEach(function (el) {
        var v = bersih(teks[el.getAttribute('data-konten-md')]);
        if (v) renderMarkdown(el, v);
      });
      var faqBox = document.querySelector('.faq-list');
      if (faqBox && Array.isArray(data.faq)) renderFaq(faqBox, data.faq);
      var galGrid = document.querySelector('.galeri-grid');
      if (galGrid && Array.isArray(data.galeri)) renderGaleri(galGrid, data.galeri);
      var andGrid = document.querySelector('.andalan-grid');
      if (andGrid && Array.isArray(data.andalan)) renderAndalan(andGrid, data.andalan);
    }).catch(function () { /* gagal: teks hardcode tetap tampil */ });
    });
  });
})();
