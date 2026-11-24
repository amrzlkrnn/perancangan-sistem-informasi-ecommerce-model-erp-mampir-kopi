# Mampir Kopi — Design System (skill /awwwards, Nov 2026)

Diterapkan dari skill `awwwards` (juliobsz/skills, MIT). Stack proyek tetap:
PHP + MySQL + vanilla JS. Tidak ada framework baru, tidak ada loader, tidak ada
WebGL — skill ini sendiri yang menurunkan teater untuk situs restoran.

## Brief

- **Subjek:** Mampir Kopi, kedai kopi kecil di Kebon Jeruk, Jakarta Barat.
  "Mampir" = singgah sebentar. Tagline: "Ruang untuk Menikmati".
  Alamat: Jl. Ff No. 24, RT.7/RW.5, Sukabumi Selatan, Kebon Jeruk,
  Jakarta Barat 11560. IG @mampir.kopi_. Buka setiap hari 08.00-22.00 WIB.
- **Audiens:** pelanggan yang mampir (anak muda Jakarta) + audiens presentasi
  tugas kuliah Amar (dosen dan teman sekelas menilai aplikasinya).
- **Pekerjaan situs:** lihat menu, pesan, bayar (QRIS/e-wallet/COD), lacak
  pesanan; dan menunjukkan sistem e-commerce + ERP yang lengkap.
- **Aset nyata:** 18 foto menu asli, foto QRIS merchant asli, copy hangat
  berbahasa Indonesia milik Amar, data menu/harga asli dari database.
- **Bahasa:** Indonesia.
- **Batasan (tidak bisa ditawar):** semua fitur tetap jalan (keranjang,
  checkout, timer bayar 5 menit, admin, wishlist, lacak, blog, testimoni,
  promo, pengembalian, CRM). Copy hero asli Amar TIDAK diubah kata-katanya.
  Tidak ada fitur rating, tidak ada game, tidak ada cropper foto.
  Presentasi di localhost dari HP — situs harus tetap bekerja offline
  (font dan library di-self-host).

## Konsep

Tiga konsep dari teritori berbeda, skor O/F/Fe/P (originality, fit,
feasibility, portability — portability makin kecil makin baik):

| | A. Mampir / Jeda (nama) | B. Seduhan (proses) | C. Struk (formal) |
|---|---|---|---|
| Ide | Situs ini adalah jeda dalam harimu: mampir, duduk, cahaya hari berubah | Scroll = proses menyeduh, dari biji sampai tersaji | Situs ini struk: semua baris adalah item yang dicetak |
| Anchor | Nama "Mampir" + tagline "Ruang untuk Menikmati" + copy "Luangkan waktu sejenak" | V60 manual brew adalah proses nyata di menu | Fitur struk termal sudah ada dan unik di aplikasi ini |
| Signature | Cahaya hari: ground + matahari hero bergeser mengikuti jam lokal | Satu cangkir berubah lintas chapter (pinned) | Struk tercetak baris demi baris saat checkout |
| O/F/Fe/P | 4 / 5 / 4 / 2 | 3 / 4 / 3 / 3 | 4 / 4 / 4 / 2 |

**Pilihan: A.** Paling pas ke nama, tagline, dan copy yang sudah ada; satu
sistem penggerak (jam hari) yang murah dibangun tanpa GL. B ditolak: terlalu
dekat dengan contoh "mise en place" yang dilarang dipakai ulang, dan narasi
proses melawan pekerjaan situs (pesan cepat). C dicuri sebagai sistem sekunder:
lapisan niaga (keranjang, checkout, konfirmasi) memakai bahasa struk —
angka mono tabular, baris item, kertas termal — karena itu memang sudah bahasa
asli aplikasi ini.

### Concept Card

```
Idea:        Situs ini adalah jeda dalam harimu: kamu mampir, duduk sebentar,
             dan cahaya di dalam kedai berubah mengikuti jam.
Metaphor:    Cahaya satu hari di dalam kedai — pagi cerah, siang hangat,
             sore keemasan, malam lampu. Satu variabel global (jam lokal)
             menggerakkan ground, aksen, dan posisi "matahari" hero.
Signature:   Foto menu yang "ikut ke mejamu": dari kartu menu, fotonya
             tumbuh menjadi hero halaman detail (shared-element transition,
             resep motion.md) — minumanmu datang ke meja.
Emotion:     3 detik pertama: tenang, hangat, tidak terburu-buru — seperti
             masuk kedai dan cahayanya sedang bagus.
Motion verb: "settle" — semua yang datang, duduk pelan ke tempatnya.
             Tidak ada yang menyentak.
Screenshot:  Hero sebagai poster: wordmark raksasa + satu foto produk besar
             dalam cahaya jam itu, dengan jam buka yang nyata di pojok.
```

## Art direction

### Palet (peran, bukan dekorasi)

| Peran | Hex | Catatan |
|---|---|---|
| ground (pagi 06-11) | #f8f5ee | kertas pagi, cerah |
| ground (siang 11-15) | #f4efe3 | |
| ground (sore 15-19) | #f1e6cf | keemasan, paling hangat |
| ground (malam 19-06) | #ece1c9 | paling dalam, lampu |
| paper | #fffdf8 | kartu, permukaan |
| ink | #1e2a22 | hijau-hitam, teks utama |
| muted | #665f52 | teks sekunder, AA ≥4.5 di ground tergelap (malam: 4.87) |
| line | rgba(30,42,34,.14) | hairline |
| accent (roast) | #8a4b23 | cokelat sangrai: harga, aktif, matahari |
| curtain | #1e2a22 | transisi halaman |

Satu tema terang yang berkomitmen (skill mengizinkan). Ground bergeser halus
lewat satu variabel `--ground` di `<html>`; semua teks mengikuti variabel yang
sama, jadi kontras terjaga di semua keadaan. Warna hangat di sini punya alasan
(cahaya matahari di kedai), bukan "krem editorial karena default AI".

### Tipografi

| Peran | Keluarga | Sumber |
|---|---|---|
| Display (hero, judul besar, wordmark) | Clash Display 600 | Fontshare, self-host |
| Text & UI | Switzer 400/500/600 | Fontshare, self-host |
| Data (harga, kode pesanan, struk, meta) | IBM Plex Mono 400/600 | Google, self-host |

Fraunces ditinggalkan: skill menandai Fraunces + krem + serif editorial
sebagai default generatif yang harus lolos uji genericness. Penggantinya
grotesk kontemporer (Clash Display) + grotesk netral (Switzer) + mono untuk
angka — ketiganya punya peran yang jelas, bukan sekadar "serif cantik".

Skala fluid: `--t-mega: clamp(3.4rem, 1rem + 13vw, 13rem)` (hero),
`--t-xl: clamp(2.4rem, 1.3rem + 4.6vw, 5.5rem)`,
`--t-l: clamp(1.7rem, 1.3rem + 1.8vw, 2.8rem)`,
`--t-m: clamp(1.2rem, 1.1rem + .5vw, 1.5rem)`,
body 16-18px, leading 1.5. Display tracking -0.03em, leading 0.92,
optical indent -0.05em. Angka harga: tabular-nums.

### Grid & ritme

- Desktop 12 kolom, gutter fluid `clamp(16px, 3vw, 32px)`, margin luar =
  gutter. Garis vertikal kuat di kolom 1, 5, 9 — semua blok teks bermula di
  salah satunya.
- HP 4 kolom; layout dikomposisi ulang, bukan dikecilkan.
- Ritme vertikal 3 mood: rapat (64px), normal (120px), lapang (200px).
- Radius: satu aturan — kartu 16px, pil 999px, foto 20px. Tidak ada
  radius acak di tempat lain.

### Bentuk & gerak

- Ease: `expo.out` (reveal), `power3.out` (feedback), `expo.inOut`
  (transisi). Reveal 1.0-1.3s stagger 70ms. Hover 0.3s.
- Motion verb "settle": elemen masuk dari 24px di bawah + opacity 0,
  lalu duduk. Foto masuk lewat clip-path (tirai terbuka dari bawah).
- Idle state: matahari hero melayang pelan (±6px, 9s) dan ground
  bergeser mengikuti jam — konsepnya yang bernapas, bukan dekorasi.
  Di bawah reduced motion: semuanya diam, semua konten terlihat penuh.
- Satu jam: semua animasi lewat GSAP ticker. Tidak ada listener scroll
  sendiri, tidak ada rAF sendiri.
- Penyimpangan yang dicatat: Lenis TIDAK dipakai. Aplikasi ini punya scroll
  yang digerakkan JS (sidebar menu, jangkar kategori) — smooth-scroll
  library hanya akan bertarung dengannya. Scroll native + ScrollTrigger
  sudah cukup untuk brief restoran ini.

### Rencana komposisi (satu keluarga layout per section)

```
HERO (Poster + split frame):
+--------------------------------------------------+
| MAMPIR KOPI            Menu  Tentang  [Masuk]    |
|                                                      |
| TEMUKAN KOPI        +----------------------+      |
| UNTUK               |  foto produk besar   |      |
| MENEMANI            |  (matahari di        |      |
| HARIMU.             |   belakangnya)       |      |
| sub copy asli       +----------------------+      |
| [Lihat Menu]  Buka hari ini 08.00-22.00          |
+--------------------------------------------------+
MENU (Index list, desktop; kartu, HP):
  baris penuh: nama besar | kategori | harga mono | [+]
  hover: foto melayang mengikuti pointer (desktop)
  tap: foto tumbuh ke halaman detail (shared element)
DETAIL PESANAN: foto besar kiri (7 kol), opsi kanan (4 kol, offset)
KERANJANG/CHECKOUT: bahasa struk — kertas termal, mono, garis putus
FOOTER (finale): MAMPIR KOPI raksasa, alamat nyata, jam buka, IG
404: "Halaman ini sedang jeda." + jalan kembali ke menu
```

### Uji genericness (apa yang diubah dari desain lama)

- Krem + Fraunces + serif editorial → diganti sistem grotesk + mono
  dengan alasan konsep (cahaya hari + struk). Bukan ganti demi ganti.
- Penomoran dekoratif "01/02/03" pada kategori → dihapus (bukan urutan).
- Eyebrow di atas setiap heading → dihapus; hanya hero yang punya kicker.
- Kartu seragam + bayangan lembut yang sama → keluarga layout berbeda
  per section (poster, index list, pasangan asimetris, struk).
- Satu kata headline dimiringkan/diwarnai sebagai satu-satunya ide
  tipografi → tidak lagi; tipografi bekerja lewat skala dan grid.

### Perlakuan interaksi (satu per peran)

- CTA utama: fill berubah menyeluruh ke accent saat hover (pola yang
  sama seperti tombol Daftar). Flood ::after z-index:-1 DIBATALKAN —
  tersembunyi di balik background tombol sendiri, hover-nya mati total
  (ditemukan kritikus 1.0). Di HP jadi press scale .97.
- Link nav: underline yang menggambar (draw); halaman aktif bergaris.
- Kartu/baris menu: foto mengintip + nama bergeser 4px ke arah panah.
- Link inline di teks: underline di keadaan diam, naik jadi highlight.
- Link utilitas (legal, sosial): cukup berubah warna.
- Semua hover juga ada di `:focus-visible`; di reduced motion jadi
  perubahan warna/underline tanpa gerakan.

## QA

Target (rubrik quality-bar.md): setiap kategori 8+. Dua pass penuh minimum,
production build (server PHP lokal + DB), screenshot desktop 1440, HP 390,
reduced motion, fokus keyboard, hover; audit SEO/meta/a11y/berat; kritik
buta dari subagent baru tiap pass (tanpa DESIGN.md, tanpa kode).
Keputusan yang dicatat: tidak ada loader (situs restoran), tidak ada GL,
idle motion hanya drift matahari (halus, ambient).

## Kritik buta ronde 1 (15 Nov 2026, ~18:07 WIB)

Skor tertimbang **6,8** — Honorable Mention (6,5), di bawah SOTD (7,2–7,6).
Design 7, Usability 6, Creativity 7, Content 8. Ide satu kalimat kritikus:
"Mampir Kopi is a pause in your day — a warm, sunlit neighborhood coffee
shop where you stop, breathe, and order coffee, with the ordering itself
styled like a paper receipt." — konsep terbaca persis seperti kartu konsep
(jeda + struk). Catatan: pergeseran cahaya harian tak terlihat dalam satu
sesi — wajar, satu kunjungan hanya menunjukkan satu daypart.

Perbaikan yang diterapkan (semua terverifikasi render/computed style):

- Tombol utama: flood ::after diganti fill accent menyeluruh (pola Daftar);
  `#kirim` login dan `.guest-login` ikut terisi. Aturan hover TIDAK dibungkus
  `@media (hover:hover)` lagi — headless Chrome melaporkan hover:none,
  jadi aturan yang dibungkus tak pernah jalan di QA.
- Navigasi HP: tab bar bawah fixed (Beranda/Menu/Pesanan/Riwayat/Profil
  untuk customer; Beranda/Promo/Blog/Lacak/Masuk untuk tamu), tab aktif
  ditandai motion.js, badge jumlah keranjang ikut `MK.badge()`.
- Riwayat HP: tabel jadi wilayah geser (`overflow-x:auto` + fade mask);
  strip tab dan strip kategori menu ikut diberi fade tepi kanan.
- Footer: 10 halaman tamu diseragamkan ke finale (wordmark + meta);
  7 halaman dalam dapat footer ringkas `.page-footer`. Footer dibuat
  mandiri di awwwards.css (tidak bergantung pada guest.css) — ini yang
  menyebabkan celah 200px di footer HP dashboard (SVG Instagram mengembang
  ke ukuran bawaan 300×150).
- Tipografi: angka stat admin ke Clash; Fraunces DIHAPUS dari fonts.css
  (4 bobot × ~67KB; tak ada lagi elemen yang memakainya) — payload font
  terdeklarasi 422KB → ~177KB.
- Target sentuh ≥24px: tautan footer, tautan login, `.text-btn` admin,
  tombol rincian pembayaran, judul/tautan artikel blog.
- Judul artikel blog dapat underline yang sama seperti "Baca artikel".
- Keranjang HP: ringkasan dikembalikan ke bawah item (`grid-row:auto`).
- Nama item "Manual Brew" ditampilkan sebagai V60 (pemetaan tampil di
  shop.js/guest.js; database tidak diubah, slug tetap `manual-brew`).
- Meta: canonical + OG/Twitter + `img/og.jpg` (1200×630 dari foto hero)
  di semua halaman; heading seksi pembayaran 14px → 16px.

Temuan yang DIBANTAH dengan bukti (bukan bug, tidak diubah):

- "Heading pembayaran tercoret": tidak ada garis di piksel manapun —
  ilusi optik dari stroke horizontal Clash Display 600 di 14px (scan
  piksel + zoom 4× bersih; tidak ada border/background/text-decoration
  di DOM).
- "Panah slider menumpuk kode voucher di dashboard": artefak tangkapan
  di tengah transisi auto-slide (dot aktif tidak cocok dengan slide
  yang tampil); diukur di keadaan diam, teks 10px bersih dari panah
  di kedua halaman. QA kini menunggu slider diam sebelum fullPage.
- "Slider maju sendiri di reduced motion": kode sudah memeriksa
  `matchMedia('(prefers-reduced-motion: reduce)')` sejak awal.
- "Caption login dan heading admin serif": terukur Clash Display
  (computed style); yang benar-benar Fraunces hanya angka stat admin.
- `reduced.hiddenText: 18` di tamu: varian responsif (hint "Masuk untuk
  pesan" yang disembunyikan di desktop, diganti label ::after) — bukan
  konten yang hilang. QA kini melaporkan delta terhadap lari normal.

## QA ronde 2 (15 Nov 2026, ~18:37 WIB)

13/13 halaman OK — 0 console error, 0 pelanggaran axe, 0 overflow,
semua target sentuh ≥24px. Audit: 1 FAIL (artefak server 404, sama
seperti ronde 1) + 25 WARN (dari 88); halaman terberat /tamu.html
~281KB (dari ~518KB; font terdeklarasi 422KB → 184KB).

Satu regresi ditemukan dan diperbaiki: CLS HP pada konten yang
di-render fetch — menu 0.53, blog 0.42, promo 0.35. Penyebab: footer
(tamu/blog/promo kini footer finale ~450px) tampil di viewport awal
lalu terdorong keluar saat konten JS terisi. Perbaikan: `min-height`
pada wadah konten JS (14d), nilainya = tinggi render terukur —
menu 0.0011, blog 0.0001, promo 0.0001 setelah perbaikan.
Sisa 0.001 adalah font-swap header, dapat diabaikan.

Catatan harness: pasangan hover baris menu tamu (h-06) sempat
menangkap hero, bukan baris — `scrollIntoView` halus dari posisi
bawah halaman belum selesai saat klip diukur. Diperbaiki dengan
`behavior:'instant'` + menunggu posisi scroll stabil.

## Kritik buta ronde 2 (15 Nov 2026, ~19:02 WIB)

Skor tertimbang **7,0** (naik dari 6,8) — Design 7, Usability 7,
Creativity 7, Content 7. Masih di atas Honorable Mention (6,5), masih
di bawah SOTD (7,2–7,6). Developer: Semantics/SEO 6, Animations 6,
Accessibility 7, WPO 7, Responsive 7, Markup/Meta-data 6. Ide satu
kalimat kritikus lagi-lagi cocok dengan kartu konsep (jeda + cahaya
hangat). Catatan kritikus sendiri: audit.txt yang ia baca adalah audit
ronde 1 (basi ~55 menit), jadi skor SEO/Markup/WPO sebagian menilai
keadaan lama; tidak ada frame transisi d-90..92 (transisi tidak
dinilai, bukan gagal); panel peta kosong kemungkinan artefak headless.

Perbaikan ronde 3 (diterapkan ~19:05–19:15 WIB; CSS/JS terverifikasi
di berkas, tidak sempat di-render ulang — Chrome headless mati, lihat
catatan di bawah):

- **Hover baris menu hidup kembali**: pasangan h-06 ronde 2 hanya
  berbeda 28 piksel (0,0093%) — aturan `:hover` di awwwards.css:413
  sengaja mematikan efek baris, dan `.product-add:hover` masih
  terbungkus `@media (hover:hover)` (mati di headless). Sekarang:
  latar baris jadi paper, nama bergeser 4px, tombol `+` terisi rust
  saat hover — aturan dibuka dari media query.
- **"Masuk" yang nyaris tak terlihat**: aturan lama
  `.guest-login:hover{ background:var(--aw-paper) }` (baris 194)
  menimpa aturan isi-ink ronde 2 karena urutan yang sama spesifisitas
  — lebih parah, teksnya ikut jadi paper-di-atas-paper (tidak
  terbaca). Aturan basi itu dihapus.
- **Brand link**: tidak ada aturan hover sama sekali di semua halaman.
  Sekarang wordmark berubah ke accent + ikon cangkir membesar 1,06×.
- **Heading "Katalog Menu" dashboard hilang di tangkapan normal**
  (muncul di reduced motion): ScrollTrigger-nya basi karena konten
  async ter-render setelah refresh saat load. Ditambah pengaman di
  motion.js: `ST.refresh()` sekali di 1,2 detik + fallback 2,5 detik
  yang memaksa reveal elemen `[data-reveal]` yang masih opacity 0.
  Larangan lama tetap berlaku: jangan pernah panggil refresh di dalam
  MutationObserver (loop tak berujung).
- **Blog**: grid 3 kolom dengan 2 artikel → 2 kolom di ≥821px
  (breakpoint HP fitur.css tetap menang di bawah itu).
- **Login**: ikon mata password diberi warna accent saat hover/fokus;
  tautan "Belum punya akun? Daftar" (`#ganti`, 150×19) diberi
  min-height 24px.
- **Tentang**: ditambah foto suasana kedai (`img/promo-segar.jpg`,
  1400×788) di samping "Cerita kami" dengan caption mono.
- **Timer pembayaran di reduced motion**: teks hitung mundur kini
  diperbarui 1×/menit (pemeriksaan kadaluarsa tetap tiap 250ms).
- **Keranjang**: tautan kembali 314×20 → min-height 24px.
- **Fokus sidebar admin**: aturan lama (0,2,1, cokelat gelap di
  sidebar gelap) dikalahkan selektor baru (0,3,1) dengan outline
  emas #f2dcae.
- **Pil "6 metode"**: kini dinamis — "5 metode" saat Ambil di Kasir,
  "6 metode" saat Diantar (COD hanya ada di pengiriman).
- **Logo & foto hero**: semua `img` logo mendapat width/height
  100×100; foto hero tamu/dashboard 1000×558.
- **robots.txt + sitemap.xml** ditambahkan di root aplikasi.

Sengaja TIDAK diubah: kebiasaan em-dash di copy (suara Amar sendiri;
marginal), panel peta kosong (kemungkinan artefak headless — perlu
dicek Amar di HP-nya), status 404 (artefak server bawaan PHP; tidak
bisa diperbaiki tanpa mengubah cara Amar menjalankan situs).

## Insiden pemotongan berkas (15 Nov 2026, ~19:15–19:40 WIB)

Saat menerapkan perbaikan lebar/tinggi logo lewat skrip exec
(python/sed), penulisan exec gagal berselang-seling dengan EPERM
"Operation not permitted" — dan `open(f,'w')` di Python MEMOTONG
target sebelum gagal menulis. **12 berkas HTML jadi 0 byte**: 404,
admin, artikel, blog, dashboard, faq, keranjang, kontak, lacak,
login, menu, pembayaran. CSS/JS tidak tersentuh (tidak pernah
ditulis lewat exec) — semua perbaikan ronde 2/3 di sana utuh.

Pemulihan: ke-12 berkas ditulis ulang lewat muse.write dari ZIP
terakhir yang dikirim (build 10 Nov 2026) sebagai basis, lalu
transformasi awwwards diterapkan ulang dengan berkas utuh sebagai
templat (promo.html = templat tamu, riwayat.html = templat
customer). Semua 12 berkas terverifikasi: HTTP 200 dari server uji
dengan ukuran byte yang tepat, dan berisi penanda awwwards
(awwwards.css, #mkCurtain, skip-link, id="konten", tabbar di tempat
yang seharusnya). Aturan baru yang berdiri: **semua penulisan berkas
hanya lewat muse.write/muse.edit; exec hanya baca.**

Catatan lingkungan: Chrome headless mati sejak ~18:51 WIB (exit 133
SIGTRAP dari kernel saat startup, kedua biner cache) — tidak ada
screenshot baru yang bisa diambil sampai VM diganti. Semua bukti
visual ronde 3 adalah berkas, bukan render. Audit segar (fetch-based,
tanpa Chrome) setelah pemulihan: 3 FAIL (2 soal halaman index yang
hanya pengalihan + artefak server 404) + 15 WARN; halaman terberat
/tentang.html ~351KB (foto baru 84KB), font tetap 184KB.

## Versi aset

Semua aset awwwards memakai `?v=20261115a` (fonts.css, awwwards.css,
motion.js, shop.js, guest.js). Versi ini BELUM PERNAH dikirim ke HP
Amar — build awwwards ini pengiriman pertamanya — jadi tidak ada
cache lama yang perlu dibongkar; kenaikan ke `20261115b` sengaja
tidak dilakukan. Perubahan visual berikutnya WAJIB menaikkan versi.

**Update 15 Nov malam:** aset kini `?v=20261115c` (awwwards.css,
motion.js, shop.js, guest.js, fitur.js); fonts.css tetap
`20261115a`. ZIP `mampirkopi-awwwards.zip` (v15a, ronde 1–3) sudah
dikirim 20:13 WIB. ZIP berikutnya (ronde 4–6) memakai nama berkas
BARU, jangan pernah timpa ZIP lama.

## Kritik buta ronde 3 (15 Nov 2026, 21:20 WIB) — 7.0

Ronde 4 yang dinilai: chip daypart ("Malam di kedai"), JSON-LD
CafeOrCoffeeShop, 4 woff2 Fraunces mati dihapus, artikel blog ke-3,
perbaikan duplikat lacak, `?v=20261115b`. QA: 13/13 OK, 0 console
error, 0 pelanggaran axe, 0 overflow. Design 7 / Usability 7 /
Creativity 7 / Content 7; dev: SEO 7, Anim 7, A11y 7, WPO 8
(naik), Resp 7, Markup 7. Kritikus menemukan bug nyata: heading
"Katalog Menu" dasbor tidak terlihat — `dashboard.css`
`.section-head{animation:customer-enter}` menimpa opacity inline
GSAP selamanya sementara visibility tetap hidden, dan fallback
force-reveal motion.js hanya memeriksa opacity.

## Ronde 5 (15 Nov 2026, ~21:26–21:41 WIB)

15 perbaikan dari daftar kritikus ronde 3: nav hover rust 2px,
brand hover scale(1.1) rotate(-6deg), hover item admin aktif,
skip-link di index.html + tangkapan Tab pertama, peta statis SVG
(menggantikan iframe Google embed yang mati), artikel blog ke-4,
kalimat kedua Visi, `.section-head{animation:none}` + fallback
reveal memeriksa visibility, syarat promo "tanpa min. belanja",
tombol Salin Kode rata bawah, target sentuh 24px, deskripsi menu
di kartu, riwayat HP (kolom disembunyikan + table-layout fixed),
`?v=20261115c`. QA ulang bersih; dua sisa: `.link-more` 102x20
(diperbaiki: min-height 24px) dan pasangan row tamu yang dua kali
menangkap hero — akar penyebab tak bisa direproduksi di luar lari
QA; `rowHoverPair` ditulis ulang memakai element screenshot dan
pasangan tamu kini benar (kartu Kopi Susu Mampir + deskripsi).

## Kritik buta ronde 4 (15 Nov 2026, 21:52 WIB) — 7.0

Design 7 / Usability 7 / Creativity 7 / Content 7; dev: SEO 7,
Anim 7, A11y 7, WPO 8, **Responsive 8 (naik)**, **Markup 8
(naik)**. Dua klaim kritikus TERBUKTI salah baca lewat analisis
piksel: hover pil Daftar/Masuk + Salin Kode + judul blog SEMUA
berubah (ink → rust, terukur per piksel) — klip h- kecil menipu
mata. Klaim lain terverifikasi benar: subtitle peta tertabrak
garis jalan, 3 menu tanpa deskripsi, ruang kosong pembayaran +
promo, cincin fokus admin tak pernah muncul (aturan menarget `a`,
itemnya BUTTON), foto tentang bukan foto kedai, placeholder
"whipped cream", skip-link menutupi wordmark, hero HP di bawah
lipatan, V60 sendirian. Yang TIDAK dikerjakan (sengaja): tanggal
blog dimundurkan (memalsukan riwayat), status 404 (keputusan
tanpa router tetap), em-dash (suara pemilik).

## Ronde 6 (15 Nov 2026, ~21:55–22:01 WIB)

Judul blog underline 2px; chip kertas di belakang label peta +
"JL. FF" digeser ke x=560; 3 deskripsi menu via UPDATE
auto-migrate di config.php (hanya isi yang kosong); strip "Cara
pakai kode" di promo; ringkasan pesanan di bawah panel QRIS
(pembayaran.html + shop.js, total ikut segarTotal); foto tentang
diganti foto V60 asli kedai; placeholder "less sugar, tanpa es";
fokus admin `.admin-nav-item:focus-visible` emas; skip-link
muncul di top:76px; hero HP dirapatkan (H1 2rem — perlu
spesifisitas `.dashboard-page` karena body tamu membawa kelas
itu — meta 2 baris, aspect 16/9; media-top 730→468px, cangkir
penuh terlihat); kartu tunggal V60 membentang penuh di HP.
Verifikasi 10/10 dari render langsung. Frame transisi d-90–d-92
ditangkap ulang (peta baru).

## Gangguan platform kedua (15 Nov 2026, ~22:01 WIB — BERLANGSUNG)

Chrome headless mati lagi (exit 133 SIGTRAP, kedua biner) dan
tulisan exec gagal EPERM — pola yang sama seperti 18:51–20:11.
Penyebab pertama yang tertangkap: crashpad Chrome "write:
Operation not permitted". muse.write/muse.edit MASIH berfungsi
(edit ini buktinya); baca + API masih jalan. QA penuh + juri
ronde 5 + ZIP ronde 4–6 TERBLOKIR sampai gangguan reda (kemungkinan
perlu pergantian VM seperti sebelumnya). Jangan ulangi lari QA
sampai Chrome hidup lagi — lari 22:01 menimpa summary.json dengan
12 ERROR (report.json per halaman masih utuh dari ronde 5).

## Kritik buta ronde 5 (16 Nov 2026, 04:11 WIB) — 7.0

Gangguan reda setelah VM diganti ~02:13 WIB; lingkungan uji
dipulihkan (MariaDB + impor DB + seed timeruji/MK-9001). QA penuh
bersih: 13/13 OK, 0 console error, 0 pelanggaran axe, 0 overflow.
Design 7 / Usability 7 / Creativity 7 / Content 7; dev: SEO 7,
**Anim 8 (naik — hover pil pixel-verified kali ini)**, A11y 7,
**WPO 7 (turun — keranjang mobile CLS 0.0792)**, Resp 8, Markup 8.
Bobot tertahan di 7.0 untuk ronde keempat beruntun. Daftar ronde 7
(belum dikerjakan): strip ringkasan pembayaran membungkus jelek,
404 tanpa footer, tombol "+" menu nyaris tak terlihat di desktop,
daypart tak terlihat dalam satu sesi, CLS keranjang, tanggal blog
+ foto generik, cincin fokus admin tipis, 18 hint tersembunyi,
kartu kategori ke-5 dasbor yatim di HP. ZIP ronde 4–6 dikirim
16 Nov ~04:12 WIB sebagai `mampirkopi-awwwards-r6.zip`
(1.653.928 byte, 124 berkas — 4 lebih sedikit dari ZIP sebelumnya
karena woff2 Fraunces mati dihapus; integritas OK).


## Slider foto hero (16 Nov 2026, permintaan Amar)

Foto hero di tamu + dashboard kini berputar seperti slider voucher:
ke-18 menu tampil bergantian tiap 4,5 detik — foto, nama, dan harga
ikut berubah (harga = harga Small; item satu ukuran = harga tunggal).
Keterangan: item pertama "— andalan kami", sisanya "— <kategori>"
(mis. "Chocolate — Non Coffee"). Panah ‹ › 36px di atas foto, 18
titik indikator di bawah caption, swipe, jeda saat hover; reduced
motion = diam di slide pertama (panah tetap jalan). Implementasi:
`motion.js` `window.initHeroSlider(items)` (SEBELUM early return —
jalan tanpa GSAP, ganti instan), dipanggil shop.js `initHome` dan
guest.js setelah menu dimuat; `.hero-photo` membungkus `<img>` agar
panah terpusat di foto; CSS §18; qa-all.js menunggu
`[data-hero-sliding]` hilang sebelum tangkapan. Tanpa JS/menu, hero
tetap statis seperti semula. Verifikasi 14/14 (rotasi, harga vs API,
panah, reduced motion, tanpa error) + QA penuh 13/13 bersih.
Aset `?v=20261116a`. CATATAN: tinggi foto hero HP 558px (bukan 199px
dari aspect-ratio 16/9) adalah perilaku LAMA — terverifikasi identik
di backup pra-slider; bukan regresi, jangan "diperbaiki" tanpa
diminta Amar. ZIP `mampirkopi-awwwards-hero.zip` dikirim 16 Nov.

## Peta kembali ke Google Maps (16 Nov 2026, permintaan Amar)

Denah skematis ronde 5 DICABUT atas permintaan Amar ("Kembalikan
jadi google map saja"). Iframe embed Google Maps yang asli (dari ZIP
10 Nov, `maps.google.com/maps?q=MAMPIR KOPI, Jl. Ff No.24...&z=15
&output=embed`) dikembalikan di tamu.html + tentang.html; CSS
`.guest-map-wrap iframe` di guest.css masih utuh (270px desktop /
220px HP), aturan `.map-static` di awwwards.css dihapus, awwwards.css
naik ke `?v=20261116b`. Jujur soal verifikasi: elemen iframe + src +
ukurun terverifikasi live, tapi ubin peta Google TIDAK bisa
dirender di lingkungan uji (Google tidak terjangkau dari VM) — di HP
Amar dengan internet normal seharusnya tampil seperti semula. Jangan
ganti lagi ke skematis tanpa diminta. ZIP
`mampirkopi-awwwards-gmap.zip` dikirim 16 Nov.

## Teks kecil diperbesar sedikit (16 Nov 2026, permintaan Amar)

Amar bertanya apakah semua teks kecil terlalu kecil — jawabannya: iya,
sebagian. Diukur di 20 halaman x 2 viewport (390px + 1440px): yang paling
kecil 9px (menu-kicker), 9.5px (promo-kicker, span brand login), 10px
(page-kicker, tag ANDALAN, caption login), 10.5px (label tabbar) — total
108 kemunculan di bawah 11px. Perbaikan (§19 awwwards.css + blok <style>
login.html + profil.html): kicker/tag/hint 9–10px -> 11px; label tabbar
10.5px -> 12px; pill topbar, hero-meta, nama brand, header tabel 11px ->
12px; kicker hero, caption hero, kicker section, label kategori, tanggal
blog, tautan, footer, label form 12px -> 13px. 12.5px ke atas dibiarkan.
Hasil akhir: NOL teks di bawah 11px; hero-meta HP tetap 2 baris, caption
hero terpanjang (Butterscotch) tidak terpotong, nav atas tetap 14px
(pernah salah baca: tautan 12px itu tautan FOOTER, bukan nav atas —
override nav-links yang sempat ditulis langsung dicabut). awwwards.css
?v=20261116c. QA tamu/dashboard/menu/login/riwayat bersih + profil
diperiksa terpisah (bukan bagian harness 13 halaman). ZIP
`mampirkopi-awwwards-teks.zip` dikirim 16 Nov.

## Animasi hover menu + tirai iris DIHAPUS (16 Nov 2026, permintaan Amar)

Amar: hover menu "keseret", lalu video tirai iris saat pindah halaman,
lalu "suka stuck kalau ingin back/kembali". Ketiganya satu paket
perbaikan. Yang dihapus: (1) pratinjau menu melayang `initIndexPreview`
— kotak foto yang mengikuti kursor saat hover baris menu (desktop
>=1024px) — beserta CSS `.mk-preview`; (2) geser nama menu 4px saat
hover (`.product-card:hover .product-name{translate:4px 0}`) beserta
transition-nya; (3) tirai transisi iris `mkCurtain` seutuhnya — JS §2
(coverFrom/uncover/intersepsi klik/semua referensi sessionStorage),
CSS `#mkCurtain` + variabel `--aw-curtain`, dan div tirai di 22 file
HTML. Penyebab stuck saat back: tirai tertinggal menutupi layar
(visibility visible + pointer-events auto) bila flag sessionStorage
sudah terpakai saat load pertama, sehingga uncover tidak pernah jalan.
Navigasi kini langsung tanpa intersepsi klik. Yang TIDAK dihapus: transisi
FLIP foto kartu -> detail (klik, bukan hover), rotasi tombol + 90° saat
hover, hati wishlist yang muncul saat hover. Verifikasi 11/11: hover
desktop tanpa preview + nama diam (dx 0.0px), navigasi 125ms (dulu
~1.5 detik), back ke tamu lalu klik Promo berfungsi, HP nama diam,
0 console error. QA tamu/menu/dashboard bersih. motion.js
?v=20261116b, awwwards.css ?v=20261116d. ZIP
`mampirkopi-awwwards-noanim.zip` dikirim 16 Nov.

## Wordmark footer raksasa DIHAPUS (16 Nov 2026, permintaan Amar)

Amar: "Footernya jelek banget ga sih" + "Mampir kopi gede banget buat
apaan". Jujur: iya, berlebihan — wordmark 59px di HP (membungkus dua
baris, 106px) / 160px di desktop (144px), padahal nama brand "Mampir
Kopi" 18px sudah ada tepat di bawahnya, jadi duplikasi. Footer 555px di
HP hanya untuk dekorasi (aria-hidden pula). Yang dihapus: baris
`<p class="guest-footer-wordmark">` di 12 file (tamu, tentang, blog,
artikel, promo, faq, lacak, kontak, testimoni, syarat, privasi,
dashboard) + aturan CSS-nya; padding atas footer dirapatkan
(clamp 40–64px -> 32–48px). Hasil: footer HP 555px -> 417px, desktop
431px -> 231px; brand + tagline + 9 tautan + Instagram + alamat/jam/
copyright tetap utuh. QA tamu/tentang/dashboard bersih. awwwards.css
?v=20261116e. ZIP `mampirkopi-awwwards-footer.zip` dikirim 16 Nov.

## Font display ganti ke Fraunces (16 Nov 2026, permintaan Amar)

Amar: "Fontnya ganti dong yg lebih cocok dengan kopinya". Clash Display
(grotesque modern, dingin) dicabut; Fraunces kembali jadi font display —
serif hangat khas kedai, dan ini memang font asli situs sebelum pass
awwwards (file woff2-nya dipulihkan dari ZIP 10 Nov, md5 terverifikasi:
500/600/700 + 600-italic). Efek samping yang bagus: animasi.css,
admin.css, dan fitur.css selama ini masih mereferensikan Fraunces tapi
jatuh ke Georgia karena @font-face-nya dihapus ronde 4 — sekarang
semuanya merender Fraunces asli, tipografi display situs menyatu.
Switzer (isi) dan IBM Plex Mono (label/struk) tetap. Biaya: +~229KB
font (4 file Fraunces ~244KB gantikan Clash 15KB) — tidak masalah untuk
presentasi localhost. fonts.css ?v=20261116a, awwwards.css ?v=20261116f.
Verifikasi 8/8 (document.fonts.check Fraunces 500/600, h1/brand/nama
produk terhitung Fraunces, 0 request gagal, 0 error) + QA
tamu/menu/dashboard bersih. ZIP `mampirkopi-awwwards-font.zip` dikirim
16 Nov.

## Tanpa versi mobile (22 Nov 2026, perintah Amar: "Gausah ada versi mobile/hp")

Meta viewport (`<meta name="viewport" ...>`) dihapus dari 23 file HTML.
Tanpa viewport meta, browser HP memakai layout viewport default (~980px)
sehingga situs SELALU merender layout desktop: media query <=900px
(tabbar bawah, hero mobile, grid 2 kolom) tidak pernah aktif di HP.
Di HP, tampilan desktop diskalakan mengecil agar muat layar (pinch-zoom
tetap bisa). Tabbar tidak dihapus dari kode — hanya tidak pernah tampil
karena media query-nya tidak terpicu. Tidak ada perubahan CSS/JS.
Terverifikasi: HP (emulasi Redmi Note 12 Pro 5G) innerWidth 980,
tabbar display none, topbar desktop tampil, grid menu 4 kolom,
tombol + menambah keranjang; desktop 1440 tidak berubah.
QA tamu/menu/dashboard bersih (1 console error di tamu = iframe Google
Maps yang tidak terjangkau dari VM, bukan cacat situs).
ZIP `mampirkopi-awwwards-nomobile.zip` dikirim 22 Nov.
Catatan: salinan kanonis kini ~/workspace/mkreport-fix/mampirkopi/
(kondisi revert 16 Nov + 48 perbaikan audit 21 Nov); salinan lama di
~/workspace/mampirkopi-fixed/mampirkopi/ sudah tertinggal 32 file.

## Nav tamu dipangkas (22 Nov 2026, pertanyaan Amar: "Ngapain promo sama lacak ada di kanan atas di menu tamu?")

Link Promo + Lacak dihapus dari `<nav class="guest-nav">` di tamu.html
dan lacak.html (dua-satunya halaman yang punya nav tamu; halaman tamu
lain memang tidak punya link nav). Nav atas tamu kini hanya Menu +
Tentang (+ Daftar/Masuk). Promo dan Lacak tetap bisa dibuka lewat
link footer (Promo, Lacak Pesanan) dan URL langsung; halamannya tidak
diubah. Konteks: Lacak masuk nav atas lewat perbaikan audit D-m4
(21 Nov) demi keterlihatan; Amar menilai keduanya tidak pantas di nav
atas. ZIP `mampirkopi-awwwards-nav.zip` dikirim 22 Nov.

## Perbaikan logika 17 + 7 (22 Nov 2026, perintah Amar: "Masih banyak logika cacat juga" lalu "kamu pikir sendiri")

Ronde 2 menemukan 17 cacat (5 Major + 12 Minor), ronde 3 menemukan 7
lagi (1 Major + 6 Minor) — semua diperbaiki dan digabung (laporan:
~/workspace/mkreport-logika2/ dan ~/workspace/mkreport-logika3/).
Yang paling penting: pendaftaran username/email `admin` kini dibalas
sukses palsu tanpa membuat akun (L2-M1); pesanan Selesai terkunci —
tidak bisa diubah lagi, dan kolom `pesanan.pernah_selesai` mencegah
stok dipotong dua kali saat pesanan dibuka ulang (L2-M2/L3-M1);
Diproses mensyaratkan pembayaran terverifikasi kecuali COD (L2-M4);
menolak pembayaran COD ikut membatalkan pesanannya (L2-M5); definisi
pendapatan diselaraskan di semua modul — Selesai DAN terbayar, basis
tanggal pesanan (L3-m1); input terlalu panjang kini HTTP 400, bukan
500 (L3-m2); versi sesi admin (`admin.sesi_versi`) memaksa logout
sesi lama saat password admin diganti (L2-m9). Semua migrasi otomatis
di `pastikan_tabel_fitur()`.

## Tulis ulang copy (22 Nov 2026, Amar: "tulisan tulisannya masih terlihat ai")

18 deskripsi menu + cerita Tentang + 7 jawaban FAQ + 4 artikel blog +
3 deskripsi promo ditulis ulang dalam bahasa Indonesia natural
(before/after: ~/workspace/mkreport-copy/COPY.md). Mekanisme: migrasi
otomatis di `pastikan_tabel_fitur()` hanya menimpa teks yang masih
persis sama dengan teks lama (teks yang sudah diedit admin aman).
TIDAK disentuh: hero tamu, caption login, hero dashboard (kata-kata
Amar sendiri), syarat/privasi, testimoni seed.

## Redesign ronde 1 + restrukturisasi tamu + desain4 "Kedai Malam" (22 Nov 2026)

Ronde 1 (§20 awwwards.css): ground daypart lebih dalam, hero foto
dalam panel tinta gelap, 18 titik slider diganti counter `01 / 18` +
progress bar tipis, menu desktop kembali ke kartu foto besar, footer
gelap, panah voucher diperbaiki. Amar MENOLAK kartu di tamu
("Caranya jelek" / "Card") dan mengarahkan: tamu seperti website kafe
Indo — galeri + pengenalan tempat + foto dari Google Maps.
Restrukturisasi tamu (§21): grid 18 kartu HILANG dari tamu; susunan
baru hero → voucher → intro "Tempat singgah di Kebon Jeruk" → Andalan
kami (4 item tanpa kartu) → Galeri (lightbox) → lokasi → footer gelap.
Amar: "Masih seperti yg lama" — strukturnya berubah tapi hero/palet/
tipografi tidak. Desain4 (§22, "Kedai Malam"): hero tamu blok espresso
solid #241610 + Fraunces krem raksasa + kicker emas + foto slider
full-bleed; palet lebih hangat (ground #efdcb2→#e0b978, tinta #2a1a10,
aksen #7e2f0d); nomor seksi 01–04; galeri pita gelap; logo v3 sesuai
poster (lengkung ganda, M membulat, cangkir + tatakan, 2 biji kopi,
garis bawah). Foto galeri: 8 foto asli dari listing Google Maps
(01–07, 09) di `img/galeri/`; foto 08 dan 10 DIKECUALIKAN (wajah orang
yang teridentifikasi, tanpa persetujuan). Hero dashboard TIDAK ikut
diubah (§22 hanya `.guest-page`) — tetap panel gelap ronde 1.
Skip link "Lewati ke konten utama" tersembunyi saat tidak fokus
(top:-48px, terverifikasi di render hasil gabungan).

## Telepon + WA, harga poster, perbaikan rubrik (22 Nov 2026)

Telepon toko +62 896-2264-2662 (seed + migrasi idempotent di config.php;
Kontak Kami menampilkan ikon WA hijau + link wa.me). Toko TIDAK punya
email — jangan pernah mengarang. Harga: harga POSTER yang benar (10
item naik; Sea Salt Regular kini Rp22.000, JSON-LD priceRange
Rp5.000–Rp22.000); Lemon Tea + Yakult Rosella tetap harga web
(12/14) atas keputusan Amar. Perbaikan rubrik tugas (67/67 cek):
tambah/edit pelanggan, ongkir bisa diubah admin (`pengaturan.ongkir`),
gambar kategori, detail pesanan admin, teks statis "Super Admin" (tidak
ada peran kasir — keputusan pemilik), upload logo SVG, halaman CRM
(leads dari pesan_kontak + pelanggan teratas + interaksi). Kontak Kami
kini juga punya embed Google Maps + fallback peta (seperti tamu).

## Mobile kembali + font baru (22 Nov 2026)

Amar membatalkan "gausah mobile": viewport meta kembali di 23/23 file
HTML; tabbar tamu selaras nav (Beranda/Menu/Tentang/Masuk).
Font (22 Nov, Amar: "cari yang paling bagus fontnya"): display tetap
Fraunces (diuji lawan Gloock — Gloock satu bobot saja dan terlalu
"poster keras" untuk konsep hangat); body Switzer → Plus Jakarta Sans
(variable 200–800, buatan Indonesia — Tokotype); mono IBM Plex Mono →
JetBrains Mono (variable 100–800). Pasangan ini = rekomendasi "Modern
Roastery" dari panduan font kopi; diuji render berdampingan tiga
varian sebelum diterapkan. File Switzer/IBM Plex Mono dihapus.
Admin TETAP PUTIH (Amar: "Admin bedain aja sendiri" + "Tetap putih" +
"Gausah diubah") — `.admin-page` #f7f6f2, tidak ikut palet hangat;
terverifikasi computed background rgb(247,246,242).

## Penggabungan + verifikasi (22 Nov 2026)

Semua pekerjaan di atas digabung ke salinan kanonis ini dari 6 cabang
paralel (perbaikan logika 17, perbaikan logika ronde 3, copy, desain3,
tamu, rubrik-fix, desain4) dengan merge manual per file untuk
api/config.php, api/admin.php, api/auth.php, api/shop.php,
api/export.php, js/admin.js, js/shop.js, js/fitur.js (rencana:
~/workspace/mkreport-merge/CHECKLIST.md). Verifikasi hasil gabungan:
php -l 5/5 + node --check 7/7; uji migrasi DB segar dari dump (kolom
baru, ongkir, telepon, harga 18/18, copy 18/18 + artikel 4/4 + promo
3/3, idempotent); repro logika 19/19 (aliran stok, kunci Selesai,
pendapatan selaras 3 modul Rp5.000, tolak admin-namespace, endpoint
baru); render tamu/menu/dashboard/kontak/admin + CRM + galeri 11/11
foto, 0 error JS (4 error tunnel = iframe Maps yang tak terjangkau
dari VM, bukan cacat situs).

## Hati wishlist pindah ke kanan harga (22 Nov 2026, Amar: "di tiap kanan sudut ada love")

Hati wishlist keluar dari foto ke baris bawah kartu: harga kiri, hati
kanan, selalu terlihat (sebelumnya cuma muncul saat hover). Template
menu di js/shop.js kini membungkus harga + hati dalam
`.product-foot` (flex, garis harga pindah ke pembungkusnya); CSS §20g
di awwwards.css. Halaman Wishlist TIDAK berubah — hatinya di sana
tetap di tengah baris (tombol hapus). Perbaikan ini juga menemukan
dua sisa layout baris §19: `translate:-50%` yang membuat hati naik
17px dan terpotong tepi kartu (diperbaiki dengan `translate:none`),
dan `padding:0` di info kartu andalan yang membuat hati menempel tepi
kanan (dikembalikan ke 18px 20px).

## Nomor section tamu dihapus (22 Nov 2026, Amar: "gausah ada nomer 1 2 3 4 di menu tamu")

Empat aturan `::before` penomoran section (§22 awwwards.css) dihapus
dari tamu; kicker teksnya tetap. Jangan tambahkan lagi.

## Upgrade animasi (22 Nov 2026, Amar: "Animasinya jelek banget, upgrade")

Diagnosis: slider hero BERKEDIP (opacity foto jatuh ke 0 tiap 4,5 dtk);
reveal seragam di semua tempat (y:24, 1 dtk); FLIP menggepengkan foto
(scaleX != scaleY); animasi ganda pada h1 menu + kartu stat dashboard;
bounce `mkPop` pada login dan kartu customize; zoom hover kartu MATI
(aturan §19 menimpa); transisi daypart 1,6 dtk terlalu lambat.
Perbaikan (motion.js + awwwards.css + animasi.css, tanpa ubah HTML):
slider jadi crossfade sejati (overlay img, foto utama tidak pernah
turun opacity-nya); progress bar terisi kontinu mengikuti interval;
intro diperketat; reveal y:16 + stagger baris kartu + scale 0.97;
FLIP skala seragam; kill animasi ganda; zoom hover 1.045 hidup lagi;
bounce diganti fade-up; transisi daypart 1 dtk. Terverifikasi: opacity
slider min 1; FLIP a==d di semua sampel; hover matrix 1.045; 18/18
kartu + 5/5 reveal tamu berakhir terlihat; reduced motion diam dan
konten terlihat; 0 error JS.

## Panel opsi halaman detail (22 Nov 2026, Amar: "kayak nyatu desainnya sama webnya")

Kartu customize di pesanan.html dibuat transparan oleh aturan
awwwards §19 (`background:transparent; border:none; box-shadow:none;
padding:0`) sehingga semua opsi melayang di atas ground dan nyaris
tak terbaca. Kartu opsi (kanan) dikembalikan jadi panel kertas
(putih, border, radius 20px, bayangan lembut, padding 24px); kartu
foto (kiri) tetap editorial tanpa kotak. Bonus: nav atas pesanan.html
salah menandai Keranjang sebagai aktif — dipindah ke Menu.

## Palet Warm Artisanal & Cozy Café (22 Nov 2026, Amar mengirim 4 opsi palet)

Pilihan: Palet 1 (Warm Artisanal) — satu-satunya yang cocok dengan
konsep Mampir/Jeda + Fraunces + karakter kedai tetangga. Palet 2
(dark luxury) bertabrakan dengan konsep cahaya/daypart; palet 3
(roastery industrial, aksen hijau) terlalu dingin; palet 4 (matcha)
salah identitas (menu utama kopi). Diterapkan 22 Nov:
- Ground daypart: pagi #FAF6F0, siang #F7F1E7, sore #F3EBDC,
  malam #EFE5D2 (dulu #efdcb2 s/d #e0b978 — terlalu jenuh, penyebab
  "nyatu").
- Teks #2C1E16 (kontras 14.95), sekunder #6B584C (6.24), garis
  #E2D5C3, aksen #B85D2B (4.53 di kartu putih; teks putih di atasnya
  4.53), aksen-gelap #9A4A20 untuk teks aksen kecil di ground (5.78).
- Kartu PUTIH #FFFFFF (bukan Latte Foam — foam sebagai kartu hanya
  kontras 1.1 terhadap ground oat dan akan "nyatu" lagi); Latte Foam
  #F1E9DD jadi permukaan sekunder (pita kepala menu, isian input).
- Status: error #B9382C, sukses #3B6E4C (sesuai panduan Amar).
- Jangkar gelap dipertahankan: hero/blok galeri/footer #241610,
  emas kicker #e8c88a, teks krem #F5EFE6.
- 334 penggantian hex di 8 file CSS + fitur.js + 3 HTML (backup:
  ~/workspace/mkreport-paleta/css-backup/); aset ?v=20261122m.
  Admin TIDAK tersentuh (tetap putih rgb(247,246,242)).
  Terverifikasi 11 halaman + admin + HP 390, 0 error JS.

## CMS Konten Website (22 Nov 2026, Amar: super admin bisa edit semua dari panel)

Seksi admin baru "Konten Website" (10 kartu): hero tamu, hero
dashboard, caption login, intro tamu + judul Andalan/galeri, Tentang
(lead/Visi/Misi/Cerita), FAQ (CRUD, tabel baru `faq`), Syarat &
Privasi (markdown-lite), Galeri (CRUD + upload, tabel baru `galeri`),
Andalan (pilih 4 menu — nama/foto/HARGA ikut menu, menutup jebakan
harga hardcode di strip Andalan).
- Teks di tabel `pengaturan` (30 key, seed verbatim dari HTML,
  idempotent); endpoint publik `api/shop.php?r=konten` (GET, tanpa
  login); loader `js/konten.js` (atribut `data-konten`); teks hardcode
  di HTML tetap sebagai fallback (tanpa JS → tampil lama).
- Batas: password/login tidak pernah bisa dilihat/diubah dari panel —
  hanya reset password pelanggan (fitur lama).
- FAQ yang dihapus total tidak di-seed ulang; subheading
  syarat/privasi dirender `h3` (tampilan sama, 19px).
- Bug saat build: konten.js di login.html memicu race CSRF (dua sesi
  PHP) → login gagal ~50%; diperbaiki dengan `window.__csrfReady`
  (konten.js menunggu token CSRF dulu); login admin 5/5 setelah
  perbaikan.
- Verifikasi merge: fresh-DB 19 tabel, faq 7, galeri 10, 30 key;
  API 57 PASS 0 FAIL (CMS+refund); edit panel → halaman publik
  berubah → dipulihkan.

## Refund dana (22 Nov 2026, Amar: "tambahin user bisa refund dana")

- Tabel `pengembalian` +4 kolom (`jenis` Refund/Pengembalian,
  `metode_refund` Transfer Bank/GoPay/OVO/DANA/ShopeePay,
  `tujuan_refund`, `jumlah_refund` = total order, dihitung server).
  Satu tombol di detail Riwayat: "Ajukan Refund / Pengembalian" —
  pesanan Selesai bisa pilih Refund atau Kembalikan pesanan; pesanan
  berbayar Menunggu/Diproses hanya Refund; COD belum bayar ditolak
  dengan pesan jelas.
- Admin: seksi "Pengembalian & Refund" (9 kolom: Tanggal/Kode/
  Pelanggan/Jenis/Alasan/Tujuan Refund/Jumlah/Status/Aksi);
  menyetujui = pembayaran → Refund, stok kembali, pesanan →
  Dibatalkan; transfer uang manual oleh admin ke tujuan tercantum
  (dialog konfirmasi menampilkan nominal + tujuan).
- Auto-refund: admin membatalkan pesanan berbayar → pembayaran
  otomatis jadi Refund dalam transaksi yang sama. Refund selalu
  penuh (termasuk ongkir); pengajuan Ditolak tidak bisa diajukan
  ulang dari UI; membuka ulang pesanan refund tidak punya jalur
  bayar ulang (sweeper mengadaluarsakannya bersih).
- Verifikasi: API refund end-to-end 15/15 (stok 99→98→99);
  penolakan 7/7; auto-refund 2/2.

## Nav tamu dipangkas habis + animasi diringankan lagi (22 Nov 2026, 14:27 & 14:29)

- Amar: "Menu sama tentang hapus aja di menu tamu" / "Tombol yg di
  kanan atas itu" — link Menu + Tentang dihapus dari
  `<nav class="guest-nav">` di tamu.html + lacak.html (satu-satunya
  file dengan guest-nav); nav atas kini hanya Daftar/Masuk. Halaman
  menu/tentang tetap ada; footer + tabbar HP (Beranda/Menu/Tentang/
  Masuk) tidak berubah.
- Amar: "Animasinya masih kayak berat" — parameter diringankan lagi
  (hanya durasi/jarak, struktur tidak berubah): reveal y16→8px,
  0,8→0,5s; kartu menu scale 0,99, stagger 0,03s; intro SplitText
  0,6s; slider 4,5→6 detik, crossfade 0,4s; FLIP 0,6s; transisi
  daypart 1→0,6s. Slider tetap crossfade murni (opacity gambar
  utama minimum 1,0 selama transisi).
- Aset berubah versi ke `?v=20261122n` (motion.js, awwwards.css,
  admin-fitur.js); konten.js/shop.js/guest.js/admin.css di
  `?v=20261122m`.

## Hero tamu dikecilkan (22 Nov 2026, 14:43)

- Amar: "Ini terlalu zoom ga sih webnya" — diukur di laptop 1366x768:
  h1 87px, hero 696px = 91% viewport. Penyebab: h1
  `clamp(3rem, 1rem + 5.2vw, 5.9rem)` + hero `min-height:
  min(78vh, 760px)` (awwwards.css §22b). Tidak ada properti `zoom`
  CSS; font dasar tetap 16px.
- Perbaikan (§22b + §22i mobile): hero `min-height` jadi
  `min(62vh, 560px)`; h1 jadi `clamp(2.5rem, 1rem + 3.4vw, 4rem)`;
  padding konten hero 36px 44px 32px; mobile h1
  `clamp(2.1rem, 1rem + 6.5vw, 2.6rem)`, min-height konten 520px -> 0.
  Tinggi foto hero TIDAK diubah. Hasil terverifikasi: 1366 -> h1
  62,4px, hero 67% viewport; 1920 -> h1 64px, hero 52%; HP 390 ->
  h1 41,35px, foto kini mengintip di layar pertama.
- awwwards.css naik ke `?v=20261122o` (22/22 file HTML).

## Lupa password berfungsi (22 Nov 2026, 14:43-14:50)

- Amar: "fitur lupa password pastikan berfungsi" + "Di login html".
  Sebelumnya link "Lupa password?" hanya display-only; pemulihan
  satu-satunya adalah reset oleh admin.
- Desain awal (kode reset 6 digit + tabel baru) DIBATALKAN oleh Amar
  14:47 WIB: "Gausah masukan kode" + "Masukan password lama".
  Desain akhir: verifikasi PASSWORD LAMA, tanpa kode, tanpa email,
  tanpa tabel baru, tanpa perubahan config.php.
- `api/auth.php` — satu route baru `reset` (POST, CSRF), body
  `{identifier, password_lama, password, konfirmasi}`. Identifier =
  username atau email (seperti login); hanya pelanggan Aktif. Bila
  benar: password baru (min 6 + konfirmasi cocok) +
  `sesi_versi + 1` (sesi lain logout). Rate limit 5 percobaan/jam
  per sesi (pola L2-m11), dihitung setelah validasi kolom kosong.
- Anti-enumerasi (pola L3-m6): identifier tak dikenal dan password
  lama salah mengembalikan pesan yang SAMA: "Password lama salah."
- `login.html` — panel satu langkah di dalam `.box`: username/email
  + password lama + password baru + konfirmasi (ketiganya dengan
  eye-toggle). Notifikasi error "Password lama salah." (#B9382C)
  dan notifikasi sukses "Password berhasil diganti." (#3B6E4C)
  tampil di panel dan tetap tampil sampai pengguna bertindak (Amar,
  14:47 + 14:48 WIB). JS memakai token CSRF yang sama dengan login
  (`window.__csrfReady`, tanpa fetch kedua — pelajaran race CSRF
  dari build CMS).
- Batasan jujur: pengguna yang benar-benar lupa password lamanya
  tetap butuh reset oleh admin (tanpa mail server dan tanpa email
  toko, tidak ada jalur pemulihan mandiri lain yang aman).
- Verifikasi: API 19/19 (subagent) + 6/6 di canonical (termasuk
  reset -> login baru -> kembalikan, state timeruji dipulihkan);
  browser desktop + HP 16/16 (subagent) + panel/error/sukses/login
  baru di canonical, 0 JS error, login normal 3/3 tanpa regresi.
  Laporan: `~/workspace/mkreport-lupa/REPORT.md`.

## Galeri tamu 100% foto Google Maps (22 Nov 2026, 14:49-14:55)

- Amar: "foto galerinya ganti pakai yang ada di google maps aja" +
  "Jangan foto aset dari menu web". Ternyata ada 3 foto menu di
  galeri (bukan 2): Green Tea Latte, Sea Salt Latte, Americano
  Peach (urutan 7, 9, 10).
- 5 foto baru diunduh dari listing Maps (18 foto; 10 sudah dimiliki
  sebelumnya). Dipakai 3, semuanya tanpa wajah yang dikenali:
  11 "Pesanan dibungkus" (dua gelas kopi susu dalam kantong
  plastik di konter), 12 "Disiapkan di konter" (dua gelas di meja
  kayu, dua orang terlihat dari belakang), 13 "Life happens"
  (tangan memegang gelas berslogan). Foto 14 (es cokelat malam
  hari) + papan menu disimpan sebagai cadangan di
  `~/workspace/mkreport-tamu/foto-maps/` (tidak dipakai di galeri).
  Foto 08 + 10 (wajah jelas) dan foto 12 + 18 (wajah staf
  sebagian) tetap TIDAK dipakai.
- Foto baru di-resize ke lebar 1080px (JPEG q82, 145-284 KB,
  konsisten dengan foto galeri lain) ke `img/galeri/`; berkas
  asli disimpan di `~/workspace/mkreport-tamu/foto-maps/`.
- `api/config.php` — seed galeri diganti (3 entri menu -> 3 foto
  Maps) + migrasi terarah di `pastikan_konten()`: UPDATE baris
  galeri yang gambarnya masih persis foto menu lama (edit admin
  tidak tertimpa; idempotent). `tamu.html` — 3 tombol galeri di
  fallback diganti sama persis (data-foto/nama/alt/caption +
  dimensi 1080x1440 / 1080x1920).
- Verifikasi: DB lama (3 baris menu -> 3 foto Maps, sisa_menu 0);
  DB baru (seed langsung 10 foto Maps, 0 menu, idempotent);
  render tamu 10/10 foto termuat, 0 foto menu, lightbox terbuka
  di foto 11; 0 JS error (hanya artefak tunnel Maps + 401 yang
  diharapkan).

## Ronde 22 Nov sore — Pesanan Terakhir, nav Lacak, header tamu sadar login, CRM gabung, zoom dashboard, profil admin (15:26-16:00 WIB)

- Beranda user terlalu sepi (Amar 15:26): diukur — hero + voucher +
  statistik nol + 5 kartu kategori, 0 produk. Ditambah seksi
  "Pesanan Terakhir" (Amar memilih opsi 2; opsi Andalan TIDAK
  dibuat): 3 pesanan terbaru (kode mono + chip status + tanggal +
  ringkasan menu + total) + tombol "Pesan Lagi" (ambil detail,
  tambahkan item yang masih ada stoknya dengan HARGA SAAT INI ke
  keranjang, lalu ke keranjang.html; item tak tersedia dilewati +
  dihitung di toast). Empty state: "Belum ada pesanan" +
  "Mulai Pesan". `api/shop.php` route `detail` kini ikut
  mengembalikan `id_menu`. Gaya di `css/dashboard.css`
  (kartu putih, kode mono 13px). Terverifikasi: 3 kartu urutan
  terbaru dulu, Pesan Lagi -> keranjang berisi 2 item Rp 28.000
  (ukuran/gula/es/catatan dipertahankan), HP 390 tanpa overflow,
  0 JS error.
- Lacak Pesanan (Amar 15:45): link "Lacak" ditambahkan ke nav atas
  8 halaman user (setelah Riwayat; di pesanan/riwayat/wishlist
  juga di page-footer agar kedua nav sinkron). Link "Lacak
  Pesanan" DIHAPUS dari footer 11 halaman tamu — tamu belum login
  tidak seharusnya melihatnya. CATATAN: halamannya sendiri
  (lacak.html, kode+email) masih bisa diakses publik karena
  pelacakan publik adalah item rubrik coursework (ERP Backend 2);
  JANGAN login-gate tanpa perintah Amar.
- Header tamu sadar login (Amar 15:47): di 12 halaman tamu
  (termasuk 404), bila sudah masuk, tombol Daftar/Masuk disembunyikan
  dan diganti menu akun `.nav-user` (avatar + "Halo, <nama>" +
  dropdown Beranda/Edit Profil/Keluar; admin: Panel Admin/Keluar).
  Logika di `js/motion.js` §1d (SEBELUM early return — jalan tanpa
  GSAP), fetch `api/auth.php?r=me`. Tab "Masuk" di tabbar HP ikut
  berubah jadi "Profil" (ikon orang sudah cocok). Perangkap:
  `.nav-user` dan `.guest-auth` sama-sama display:flex dari
  stylesheet lain, jadi atribut hidden saja tidak cukup —
  `.nav-user[hidden], .guest-auth[hidden]{ display:none; }` di
  `css/animasi.css`. Terverifikasi: logout + masuk di 3 halaman,
  dropdown, tabbar HP, logout lewat dropdown -> tamu.html +
  Daftar/Masuk kembali; 0 JS error.
- CRM digabung ke Dashboard admin (Amar 15:51): seksi CRM terpisah
  + tombol nav-nya dihapus; kartu ringkasan + Leads + Pelanggan
  Teratas + Interaksi Terbaru pindah ke dalam `#section-dashboard`
  (setelah Stok Menipis). `loadDashboard()` di `js/admin.js` kini
  memanggil `loadCrm()` (guard typeof; `loadCrm` tinggal di
  `js/admin-fitur.js`). Entri `crm` dihapus dari peta loaders.
  Endpoint `api/admin.php?r=crm` TIDAK berubah. Terverifikasi:
  4 kartu CRM ter-render di dashboard (3 pelanggan, Rp 96.000).
- Zoom beranda user (Amar 15:55): diukur — h1 83px, hero 827px =
  108% viewport di 1366; foto 558px karena atribut height HTML
  mengalahkan aspect-ratio (4/5 maupun 16/9 tidak berpengaruh).
  Perbaikan §23 di `css/awwwards.css` (hanya .dashboard-page):
  h1 clamp(2.5rem,1rem + 3.4vw,4rem), foto height:340px desktop /
  220px HP, padding hero 96/68px -> 40/32px. Hasil: h1 62px,
  hero 517px = 67% di 1366 (1920: 48%; HP: 96%, foto 220px).
  Hero tamu (§22b) tidak disentuh.
- Profil admin (Amar 15:56-15:58, difoto dropdown user "Halo,
  Amar"): admin kini bisa edit profil + foto + password seperti
  user. Migrasi `pastikan_kolom_foto_admin()` (kolom foto/
  foto_mime/foto_diubah di tabel admin). `me` untuk admin ikut
  mengembalikan nama/username/email/foto; `r=foto` melayani foto
  admin. Route baru di `api/admin.php`: `profil_admin` (GET),
  `profil_admin_update` (nama/email/foto/password; username TIDAK
  bisa diubah — diturunkan dari email saat akun dibuat, seperti
  admin_save), `profil_admin_hapus_foto`. Aturan cermin pelanggan:
  password saat ini wajib untuk ganti email/password; password
  baru min 6 + konfirmasi; ganti password menaikkan sesi_versi.
  UI: topbar `.admin-profile` kini dinamis (foto/inisial + nama +
  dropdown Edit Profil/Keluar) + seksi "Profil Saya" (nav setelah
  Dashboard) dengan formulir cermin profil.html. Terverifikasi:
  API 15/15 (termasuk tolak tanpa/salah password lama, ganti
  password + login ulang, upload + hapus foto, r=foto 200) +
  browser (dropdown, form terisi, upload via UI -> avatar topbar
  berganti foto, hapus -> kembali inisial); 0 JS error. Password
  admin dikembalikan ke admin123 dan foto uji dihapus sesudah tes.

## Mode gelap (Amar, 22 Nov 15:59 WIB — selesai + digabung 16:20 WIB)

- Toggle "Tema Tampilan" (radio Terang/Gelap) di Edit Profil, sejajar
  preferensi notifikasi. Klik langsung mengubah halaman; tersimpan ke
  server saat tombol Simpan ditekan (pola pref_notifikasi). Preferensi
  per akun: kolom `pelanggan.pref_tema` (auto-migrasi
  `pastikan_kolom_tema()` di `pastikan_tabel_fitur()`), dikembalikan
  `me`/`profil`, diterima `profil_update` (nilai invalid diabaikan,
  tidak menggagalkan update). Berlaku di 19 halaman user + tamu,
  bertahan antar sesi. Admin tetap putih; login + 404 tidak dimuat.
- Penerapan: `window.terapkanTema(me)` di `js/motion.js` §1c (SEBELUM
  early return) — set/hapus `data-tema="gelap"` di `<html>`; dipanggil
  dari blok header tamu §1d (halaman tamu) dan blok AKUN `js/shop.js`
  (halaman user). Tanpa localStorage: satu sumber kebenaran di server
  (konsekuensi: kilatan terang sepersekian detik saat muat halaman
  gelap, karena tema diterapkan setelah fetch `me`).
- CSS: file baru `css/tema-gelap.css` (347 baris), dimuat TERAKHIR
  setelah awwwards.css di 19 halaman. Semua aturan di bawah
  `html[data-tema="gelap"]` (spesifisitas 0,1,1 — setara selektor
  daypart, menang lewat urutan file). Palet "Kedai Malam": ground
  #1A130E, permukaan #2E2118, teks #F5EFE6, muted #C9BBA6, emas
  #E8C88A, karamel terang #E08A4C. §0 me-remap token --aw-*, --home-*,
  --customer-* (64 pemakaian) + §1-§20 per permukaan. Sengaja tidak
  digelapkan: iframe Google Maps (konten Google) dan kode QR/QRIS
  (tetap putih demi scan).
- Verifikasi subagen (report ~/workspace/mkreport-gelap/REPORT.md):
  API 10/10 (x2, awal + rebase), migrasi fresh-DB 2x PASS, render
  17/17 halaman gelap (ground #1A130E, kartu #2E2118, foto menu
  18/18), kontras terukur 12 sampel SEMUA >= 4,5:1 (teks isi 16,07:1,
  harga aksen 5,87:1, tombol emas 11,42:1), toggle end-to-end, mode
  terang tidak berubah (ground sore #F3EBDC, harga #B85D2B), 0 JS
  error. 5 bug ditemukan + diperbaiki saat verifikasi (panel judul
  menu, avatar krem, kicker menu, header tabel riwayat, token
  --customer-*). Verifikasi parent sesudah merge: dashboard/menu/
  tamu gelap + terang kembali via radio profil + admin tetap putih
  + CRM + Profil Saya utuh, 0 error.

## Hero tamu disamakan dengan beranda user + mode gelap dibatasi ke halaman user (22 Nov 16:25-16:30 WIB)

- HERO TAMU (Amar 16:25, difoto laptop: "menu tamu samain dong
  zoomnya atau tata letaknya kayak menu user"): blok espresso §22b
  (hero gelap full-bleed) DIHAPUS seluruhnya — hero tamu kini memakai
  aturan hero yang sama persis dengan beranda user: latar terang,
  teks ink, foto di panel gelap §20a (radius 24px), ukuran dari §23
  yang diperluas ke `.guest-page` (h1 clamp 2.5rem-4rem = 62px di
  1366 / 64px di 1920, foto 340px desktop / 220px HP, padding hero
  40/32px; HP h1 2rem). Tidak ada lagi override
  `.guest-page .hero-*` — bila keduanya berbeda lagi, itu bug.
  Terverifikasi: h1 tamu = dasbor (62,44px di 1366, 64px di 1920,
  32px di HP), foto 340/220px, panel 24px, slider jalan (panah
  next mengganti foto + caption + counter). CATATAN JUJUR: tinggi
  hero tamu 626px vs dasbor 517px di 1366 — SELISIHNYA ISI, bukan
  tata letak: judul tamu 3 baris ("Temukan kopi untuk menemani
  harimu.", copy asli Amar) vs sapaan dasbor 1 baris ("Halo,
  Timer!"). Ukuran + tata letak sudah identik; judul tidak
  diubah/dirapatkan tanpa perintah Amar.
- MODE GELAP DIBATASI (Amar 16:27-16:28: "pas dinyalain mode gelap
  malah masuk ke menu tamu tapi masih login" + "darkmode bisa dipakai
  jika sudah login saja jadi tidak bocor ke halaman tamu"): mode
  gelap kini HANYA berlaku di 8 halaman user (dashboard, menu,
  keranjang, pesanan, riwayat, wishlist, profil, pembayaran).
  Link `tema-gelap.css` dicabut dari 11 halaman tamu (tamu,
  tentang, kontak, faq, syarat, privasi, testimoni, blog, artikel,
  promo, lacak) + panggilan `terapkanTema(me)` dihapus dari blok
  header tamu §1d di `js/motion.js` (definisi `terapkanTema` tetap
  ada — dipakai blok AKUN + radio profil di `js/shop.js`). Halaman
  tamu kini SELALU terang, walau dibuka dalam keadaan login dengan
  pref_tema=gelap; menu akun di header tamu tetap jalan (Daftar/
  Masuk hilang saat login). Terverifikasi: tamu + tentang login =
  terang (ground #F3EBDC sore, tanpa data-tema, tanpa CSS gelap);
  dasbor + menu login = gelap (#1A130E); tamu logout = terang +
  Daftar terlihat; 0 JS error. Komentar header `tema-gelap.css` +
  §1c diperbarui (hanya komentar — tidak ada bump versi untuk file
  itu). Aset: awwwards.css q, motion.js p.

## Interval slider: hero 3 detik, voucher 4 detik (Amar, 22 Nov 16:32 WIB)

- "Transisi menu jadi 3 detik aja" + "Voucher juga" + koreksi "Eh
  voucher 4 detik": hero slider `SLIDE_MS` 6000 -> 3000 ms di
  `js/motion.js` (bar progres ikut otomatis karena durasinya
  `SLIDE_MS/1000`); banner voucher 4500 -> 4000 ms di DUA tempat —
  `js/shop.js` (dasbor) dan `js/guest.js` (tamu) — keduanya punya
  salinan slider voucher sendiri, jangan ubah satu saja. Crossfade
  0,4 detik tidak berubah. Terverifikasi dengan pengukuran langsung
  (12 detik per halaman): hero berganti tiap 3,0 detik di dasbor +
  tamu; voucher tiap 4,0 detik di dasbor + tamu; 0 JS error. Aset:
  motion.js q, shop.js o, guest.js n.

## Mode gelap tanpa kilatan putih (Amar, 22 Nov 16:34 WIB)

- Keluhan Amar: "pas darkmode masih muncul persemian detik mode
  putihnya". Penyebabnya: tema diterapkan sesudah fetch `me` dari
  server, jadi halaman sempat ter-cat terang dulu — kilatan yang
  sebelumnya dicatat sebagai keterbatasan yang diketahui.
- Perbaikan: cache tema di localStorage kunci `mk_tema`.
  (1) Script inline sinkron tepat sesudah `<meta charset>` di 8
  halaman user membaca cache SEBELUM cat pertama dan menyetel
  `data-tema="gelap"` — halaman gelap langsung gelap.
  (2) Server tetap sumber kebenaran: cache HANYA ditulis dari
  respons server — login (auth.php `login` kini mengembalikan
  `pref_tema`), blok AKUN di shop.js, dan segarkan profil; radio
  yang diklik tapi belum disimpan TIDAK menulis cache.
  (3) Cache dihapus saat keluar (3 jalur: dropdown user shop.js,
  dropdown tamu motion.js §1d, keluarAdmin admin.js) agar tidak
  bocor ke akun berikutnya. Helper di motion.js §1c:
  `simpanTemaLokal` / `hapusTemaLokal` (try/catch, mode privat aman).
- Terverifikasi dengan pencatat frame (rAF sejak dokumen dibuat):
  dasbor pertama sesudah login sampel pertamanya sudah gelap
  (t=206ms, rgb(26,19,14), tidak pernah berubah); menu/profil
  sama (t=68-69ms); tamu saat login gelap tetap terang; logout
  -> cache null; mode terang tetap terang tanpa kilatan gelap;
  radio tanpa simpan tidak meracuni cache. 0 JS error. Aset:
  motion.js r, shop.js p, admin.js f.

## Lacak ikut mode gelap + logo kembali ke dasbor (Amar, 22 Nov 16:43-16:44 WIB)

- "Buat [lacak] mengikuti beranda, menu, keranjang, riwayat, dan
  wishlist agar bisa ikut berubah saat darkmode maupun mode terang":
  lacak.html kini memuat tema-gelap.css + script anti-kilatan di
  <head> (sama seperti 8 halaman user); tema diterapkan di motion.js
  §1d dari fetch `me`, DIBATASI ke pathname lacak.html saja
  (lacak.html tidak memuat shop.js sehingga blok AKUN tidak jalan
  di sana). TIDAK ada aturan CSS baru yang dibutuhkan: kicker
  mengikuti --aw-accent-deep (emas di mode gelap), tag mengikuti
  token --tag-* + aturan .tag gelap yang sudah ada, timeline sudah
  punya §17 di tema-gelap.css, topbar tercakup lewat class
  .dashboard-page di body lacak.html.
- "Pas pencet ikon mampir kopi kenapa ke tamu, harusnya balik ke
  user": di §1d, saat sudah login logo .brand di SEMUA halaman tamu
  diarahkan ke dashboard.html (user) / admin.html (admin). Saat
  belum login href statis tamu.html dibiarkan. (8 halaman user
  memang sudah mengarah ke dashboard.html sejak dulu.)
- BATAS TAMU/USER (Amar 22 Nov 16:44: "jangan jebol — tamu ya di
  tamu, user ya di user"): lacak gelap HANYA saat login; tamu yang
  belum login selalu terang di semua halaman; halaman tamu lain
  (tamu, tentang, dst.) selalu terang walau yang membuka login
  gelap; lacak publik (kode+email, tanpa login) tetap berfungsi.
  Terverifikasi: lacak login-gelap sampel pertama sudah gelap
  (t=74ms); tamu/tentang login-gelap tetap terang #F3EBDC;
  lacak logout terang + logo ke tamu.html + pencarian MK-T002
  berhasil; logo admin ke admin.html; 0 JS error. Aset: motion.js s.

## Andalan + galeri dikecilkan (Amar, 22 Nov 16:49 WIB)

- "Kecilin aja semuanya" (sesudah "andalan kami sama galeri terlalu
  besar ga sih"). ANDALAN: foto 4/5 -> 1/1, gap 28 -> 20px, margin
  atas 64 -> 48px; section desktop 554 -> 481px, HP 759 -> 668px.
  GALERI: foto 3/4 -> 4/3, ubin peta 3/4 -> 4/3, padding pita
  52/60 -> 36/40px, margin atas 72 -> 48px, margin grid 28 -> 20px;
  section desktop 1400 -> 877px, HP 1539 -> 1085px; total halaman
  4572 -> 3936px desktop. JEBAKAN: ubin lebar (.galeri-lebar) dengan
  aspect-ratio:auto memaksa baris pertama tetap 371px dari rasio
  intrinsik foto splash (3:2) — diperbaiki dengan aspect-ratio:8/3
  supaya sejajar baris (~209px desktop, ~118px HP). Foto portrait
  dari Maps terpotong atas-bawah (object-fit:cover) tapi subjek
  tetap terbaca; klik tetap membuka lightbox ukuran penuh
  (terverifikasi). Aset: awwwards.css r.

## Header lacak = header user (Amar, 22 Nov 16:51 WIB)

- "Samain kayak gambar yg di kiri yang section lacaknya" (foto
  keranjang vs lacak): saat SUDAH LOGIN, lacak.html kini menampilkan
  nav pengguna lengkap (Beranda/Menu/Keranjang/Riwayat/Lacak aktif/
  Wishlist) + tabbar pengguna di HP — sama seperti halaman user.
  Saat BELUM LOGIN, lacak tetap berheader tamu (Daftar/Masuk, tanpa
  nav user, terang) — batas tamu/user dipertahankan.
- Implementasi: <nav class="nav-links" hidden> + <nav class="tabbar
  tabbar-user" hidden> di lacak.html (tabbar tamu jadi .tabbar-guest);
  motion.js §1d menampilkannya saat `me` ada (isLacak). JEBAKAN:
  .guest-controls punya margin-left:auto sehingga nav menempel logo
  ("MAMPIR KOPIBeranda") — diperbaiki di guest.css (.guest-page
  .nav-links{ flex:1; justify-content:center }) supaya nav mengisi
  ruang tengah dan tautannya terpusat seperti halaman user.
- "Ngapain ada beranda di profile pas di section lacak": dropdown
  akun di lacak TIDAK lagi menampilkan Beranda (sudah ada di nav) —
  hanya Edit Profil + Keluar, sama seperti halaman user (shop.js
  AKUN). Di halaman tamu LAIN, Beranda tetap ada di dropdown karena
  nav-nya tidak punya Beranda. Terverifikasi: lacak login (gelap +
  terang) nav 6 tautan + Lacak aktif + dropdown tanpa Beranda +
  logo ke dashboard; lacak logout header tamu + tabbar tamu; tamu
  login tidak berubah (tanpa nav user, dropdown ADA Beranda, terang);
  pencarian lacak tetap berfungsi; 0 JS error. Aset: motion.js t,
  guest.css n.

## Kartu wishlist = kartu menu; kartu item lacak (Amar, 22 Nov 16:53 WIB)

- "Wishlist sama lacak belum punya card kayak di menu sama
  keranjang, coba buat". WISHLIST: kartunya dulu layout baris
  sendiri (blok override #wishList di awwwards.css: satu kolom,
  foto 110px, tanpa deskripsi, tanpa tombol +, hati menempel di
  foto) — blok override DIHAPUS total (123 baris + 1 aturan #wishList
  di §7), body wishlist.html menjadi "dashboard-page customer-page",
  dan customer-theme.css dimuat (sama seperti menu.html) sehingga
  wishlist memakai kartu foto standar §20d: foto full-bleed, nama,
  deskripsi, harga + hati di kaki kartu, tombol + (tambah ke
  keranjang dengan pilihan standar, sama seperti di Menu). API
  wishlist kini mengembalikan deskripsi + tipe + harga_regular.
  Terverifikasi: 3 kartu berdeskripsi + tombol + berfungsi
  (keranjang 0->1) + hati menghapus (3->2 kartu); 0 JS error.
- LACAK: hasil lacak dulu hanya teks nama menu ("2x Kopi Susu
  Mampir, ..."). API lacak kini mengembalikan array items (nama,
  ukuran, gula, es, catatan, harga, jumlah, subtotal, gambar —
  join detail_pesanan + menu, meniru route detail; id internal
  di-unset sebelum respons) dan fitur.js merendernya sebagai kartu
  .cart-item read-only seperti di keranjang (foto 60px + nama +
  varian + harga, TANPA tombol ubah/hapus/jumlah). Label "Isi
  pesanan" di atas kartu; teks item dihapus dari baris meta.
  Terverifikasi: MK-T001 2 kartu (foto termuat, varian + "@ Rp
  13.000 x 2" benar), MK-T003 varian Regular/Less Sugar/Less Ice;
  0 JS error. Aset: shop.js q, fitur.js n, awwwards.css s.

## Lacak dihapus dari footer halaman user (Amar, 22 Nov 17:05 WIB)

- "Lacak pesanan yg dibawah untuk menu user hilangkan saja karna
  sudah jadi fitur di atas bareng beranda, menu, keranjang, riwayat,
  dan wishlist": tautan Lacak dihapus dari footer KEDELAPAN halaman
  user (5 .guest-footer: dashboard/menu/keranjang/profil/pembayaran
  — "Lacak Pesanan"; 3 .page-footer: riwayat/wishlist/pesanan —
  "Lacak"). Lacak kini HANYA ada di nav atas halaman user.
  Terverifikasi grep: 8/8 nav berisi Lacak, 8/8 footer tanpa Lacak.
  (Footer halaman TAMU sudah tanpa Lacak sejak siang — penghapusan
  sore itu hanya menyentuh footer user.)

## Tombol −/+ keranjang di mode gelap (Amar, 22 Nov 17:22 WIB)

- Video dari laptop Amar: di keranjang mode gelap, tombol jumlah
  −/+ masih PUTIH dan glifnya tak terlihat. Akar masalah: aturan
  gelap hanya ada untuk :hover (tema-gelap.css §10), sementara
  latar tombolnya hardcoded #fff di style.css (BUKAN token, walau
  komentar lama mengklaim "mengikuti token") — jadi di gelap,
  tombol putih + glif krem = glif hilang. Perbaikan: aturan dasar
  eksplisit di §10 (background #2E2118, border #4A3728, teks
  #F5EFE6); aturan hover lama (#241610) tetap berlaku. Komentar
  usang diperbaiki. Tombol .qty-btn di pesanan.html TIDAK kena
  masalah ini (background-nya var(--paper), ikut token).
  Terverifikasi render: 4 tombol (2 item) bg #2E2118 + glif krem,
  hover #241610, angka krem, 0 JS error; mode terang tidak berubah.
  Aset: tema-gelap.css b.

## Mode gelap global + kartu konsisten menu (Amar, 22 Nov 16:54–17:31 WIB)

- MODE GELAP GLOBAL (16:54, "buat semua konsisten ke darkmode kecuali
  admin"): halaman tamu ikut gelap saat pengguna login + pref gelap.
  Merge dari subagent (REPORT ~/workspace/mkreport-darkglobal/):
  11 halaman tamu (+link tema-gelap.css + skrip anti-kilatan),
  motion.js §1d (terapkanTema tanpa gate lacak; me null → paksa
  terang + hapus cache), tema-gelap.css §21 (token --muted #C9BBA6,
  lokasi tamu, kicker, tab riwayat, UI metode pembayaran). Admin +
  login tetap terang; logout tetap terang.
- PEMBAYARAN GELAP (video Amar 17:30): baris metode #2E2118, terpilih
  hijau gelap #1E3A28 + border #4C8A5D, teks terbaca; QR/QRIS SENGAJA
  tetap putih (harus bisa dipindai). Baris gelap tapi beda dari
  ground #1A130E — "gelap tapi tetap terlihat, jangan menyatu".
- KARTU KONSISTEN MENU (Amar 17:26, §24 awwwards.css): keranjang,
  riwayat, lacak memakai permukaan + teks kartu menu (latar putih /
  #2E2118, border #E2D5C3, radius 10px, nama ink, sekunder muted,
  harga #B85D2B / #E08A4C). Token --customer-* + fallback --aw-*.
  Riwayat: tabel → kartu .riwayat-card (kode jadi judul — di tabel
  lama kode tidak tampil); filter tab app.js diperbarui
  (.riwayat-card). Jebakan: .order-row-card SUDAH dipakai kartu
  Pesanan Terakhir dashboard (style.css:554, flex) — riwayat TIDAK
  boleh memakai kelas itu. Aturan tabel HP dihapus. Wishlist sudah
  kartu menu sejak 17:15 (wishlist Amar kosong — 0 baris di DB-nya).
- KICKER: "Catatan Pesanan" (riwayat), "Pesananmu" (keranjang) —
  Title Case (teks HTML + text-transform:none; kicker lain tetap
  lowercase). Amar sempat bilang "Pesanku" lalu dikoreksi 17:27.
- HARGA MENU SEBENARNYA #B85D2B/#E08A4C (--customer-coffee
  didefinisi ulang jadi var(--aw-accent) di awwwards.css:55), bukan
  #9A4A20 dari customer-theme.css:3 — fallback lacak memakai
  --aw-accent supaya persis sama di kedua mode.
- VERIFIKASI LAWAN DB ASLI AMAR (dump 17:31, 9 pesanan, wishlist 0,
  pref gelap; salinan uji db_kedai_kopi_amar + server 8897, password
  akun amar di SALINAN diganti hash uji — DB asli tidak disentuh):
  riwayat 9 kartu + filter + modal, keranjang 2 kartu + stepper,
  lacak MK-0009 2 item, pembayaran 6 metode, tamu gelap, terang +
  HP 390 bersih, 0 JS error. Aset: awwwards t / motion u / shop r /
  app a (baru diberi versi) / tema-gelap c.
