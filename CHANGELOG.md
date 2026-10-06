# Changelog — DCI MCP Bridge

Semua perubahan penting pada proyek ini akan didokumentasikan di sini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/),
versi mengikuti [Semantic Versioning](https://semver.org/lang/id/).

Dikembangkan oleh **Mas Wondho — Duta Corpora Indonesia**.

## [1.5.1] — 2026-10-07

### Diperbaiki
- **Deteksi mesin AI Puffer gagal di situs nyata** (dilaporkan Mas Wondho: kartu status
  "NONAKTIF" padahal plugin terpasang): akar masalah — kelas internal AIPKit berada di
  namespace `WPAICG` (terverifikasi: `WPAICG\Core\AIPKit_AI_Caller`,
  `WPAICG\AIPKit_Providers`, `WPAICG\AIPKIT_AI_Settings`), sedangkan plugin memeriksa
  nama kelas global. Kini `dci_mcp_bridge_aipkit_class()` mencoba kandidat FQCN
  `WPAICG\Core\` → `WPAICG\` → global. Dampak perbaikan: kartu status akurat dan
  `dci/generate-text` memakai jalur internal (bukan loopback REST yang timeout).
- Label kartu "AI Puffer (Public API)" diperjelas menjadi **"AI Puffer (Mesin AI)"** —
  fitur "AI connectors" milik AI Puffer tidak diperlukan oleh jembatan ini; Public API
  access juga bukan prasyarat.

### Pengujian
- Harness runtime diperluas dengan stub namespace WPAICG + 4 uji regresi baru
  (deteksi kelas, jalur internal tanpa HTTP, penolakan provider tak dikenal,
  status installed) — total **35/35 PASS**.

## [1.5.0] — 2026-10-07

### Ditambahkan
- **Rotasi multi API key Winston AI**:
  - Sumber kunci: textarea halaman DCI Bridge (satu per baris / koma) atau konstanta
    `DCI_WINSTON_API_KEYS` di wp-config.php (menimpa opsi); kunci tunggal lama dimigrasi
    otomatis; duplikat digabung.
  - **Round-robin**: indeks rotasi dipersistenkan — beban kredit tersebar merata ke semua kunci.
  - **Failover otomatis**: 401 (ditolak), 402 (kredit habis), 429 (batas laju), dan error
    jaringan langsung pindah ke kunci berikutnya tanpa menggagalkan pemeriksaan.
  - **Cooldown 30 menit** per kunci bermasalah (disimpan per-hash kunci — nilai kunci tidak
    ikut tersimpan di state), otomatis kembali dipakai setelah jeda.
  - 400/415/5xx = kesalahan permintaan/server (bukan soal kunci) dilaporkan langsung tanpa
    membakar kunci lain.
- `dci/get-seo-config`: blok `integrity` kini menyertakan `keys` (jumlah kunci terpasang).
- Halaman admin: status "N KUNCI AKTIF" dengan 4 karakter terakhir tiap kunci; textarea
  multi-kunci menggantikan input tunggal.

### Keamanan
- State rotasi/cooldown hanya menyimpan hash MD5 kunci — nilai kunci tidak pernah muncul
  di opsi state, di UI, maupun di respons MCP.

## [1.4.0] — 2026-10-07

### Ditambahkan
- Kemampuan **`dci/check-originality`** — gerbang finalisasi artikel via **Winston AI**
  (dipilih hasil riset: satu provider untuk AI-detector **dan** plagiarisme, respons
  sinkron, mendukung Bahasa Indonesia `id`; alternatif Originality.ai mengunci API-nya
  untuk paket Enterprise):
  - Deteksi AI: `POST /v2/ai-content-detection` — skor "manusia" 0–100 (rendah =
    indikasi AI), kalimat yang di-flag (<20), skor keterbacaan; min. 300 karakter.
  - Plagiarisme: `POST /v2/plagiarism` — persentase plagiarisme, 5 sumber teratas,
    dukungan `excluded_sources`; min. 100 karakter; 2 kredit/kata.
  - Output normal + `credits_used/remaining` dan catatan metodologis (detektor =
    heuristik, bukan vonis).
- Kolom **API Key Winston AI** di halaman admin DCI Bridge: admin-only, ber-nonce,
  disimpan tersembunyi (ditampilkan hanya 4 karakter terakhir), tidak pernah dikirim
  ke AI agent. Alternatif: konstanta `DCI_WINSTON_API_KEY` di wp-config.php.
- `dci/get-seo-config` kini melaporkan blok `integrity` (apakah key Winston terpasang).

### Keamanan
- API key layanan pihak ketiga tidak pernah ditampilkan penuh di UI maupun
  dikembalikan melalui MCP — hanya status terpasang/belum.

## [1.3.1] — 2026-10-07

### Diperbaiki
- **`dci/generate-text` timeout pada sebagian hosting** (ditemukan saat uji live di
  Djayakontainer.co.id: `cURL error 28 — Connection timeout`): server menolak koneksi
  loopback HTTP ke dirinya sendiri. Solusi: panggilan **internal langsung** ke
  `AIPKit_AI_Caller::make_standard_call()` (kontrak diverifikasi dari source v2.4.95) —
  tanpa HTTP sama sekali. Jalur loopback REST tetap ada sebagai cadangan otomatis.
- Konsekuensi: **Public API access AI Puffer tidak lagi wajib** — cukup ada API key
  provider yang terisi di AIPKit (yang sudah dipakai editor sehari-hari).

### Ditambahkan
- `dci/get-seo-config` kini melaporkan status mesin AI Puffer: `installed`,
  `providers_configured` (daftar nama provider yang API key-nya terisi — nilai kunci
  tidak pernah dibaca/dikembalikan), dan `public_api_enabled`.
- Halaman admin DCI Bridge: kartu status AI Puffer kini berbasis kelas internal +
  provider terisi, bukan hanya flag Public API.

## [1.3.0] — 2026-10-07

### Ditambahkan
- Halaman admin **"DCI Bridge"** (menu wp-admin, hanya Administrator): status kesehatan
  integrasi (MCP Adapter / AI Puffer Public API / Rank Math), tabel 7 kemampuan beserta
  status registrasinya, ringkasan konfigurasi keamanan aktif, dan panduan koneksi AI agent
  lengkap dengan endpoint MCP situs.
- Notifikasi selamat datang sekali-tampil dengan tautan langsung ke halaman DCI Bridge
  (tombol tutup ber-nonce).
- Peringatan otomatis di wp-admin bila dependensi MCP Adapter tidak aktif.
- `readme.txt` standar WordPress.org (Bahasa Indonesia) dan `CHANGELOG.md` ini.
- Identitas plugin: "Mas Wondho - Duta Corpora Indonesia" (tanpa situs plugin).

## [1.2.0] — 2026-10-07

### Ditambahkan
- Kemampuan `dci/publish-post` — menerbitkan draf sebagai **aksi eksplisit terpisah**;
  membutuhkan kapabilitas `publish_posts` (Editor ke atas).
- Kemampuan `dci/get-seo-config` — membaca konfigurasi best-practice Rank Math:
  modul aktif, format judul/deskripsi post, robots default, tipe schema/artikel.
- Parameter `og_title`, `og_description`, dan `robots` (whitelist nilai valid Rank Math)
  pada `dci/set-post-seo-meta`.
- Pemeriksaan **frasa klise AI generik dwibahasa** (EN dari metodologi skill blog-rewrite +
  padanan ID) pada `dci/audit-article`.

### Keamanan
- Whitelist direktif robots menolak nilai tak dikenal secara senyap — nilai invalid ditolak.

## [1.1.0] — 2026-10-07

### Ditambahkan
- Kemampuan `dci/update-draft-post` — memperbaiki judul/isi/excerpt draf;
  artikel berstatus terbit ditolak dengan pesan jelas.
- Kemampuan `dci/set-post-seo-meta` — mengisi `rank_math_title`,
  `rank_math_description`, `rank_math_focus_keyword` pada draf.
- Kemampuan `dci/audit-article` — audit on-page deterministik: panjang judul/meta,
  posisi & kepadatan kata kunci, struktur heading (satu-H1, tanpa lompatan tingkat),
  tautan internal/eksternal, anchor generik, tautan duplikat, alt text, panjang konten,
  plus skor Rank Math — keluaran PASS/WARN/FAIL dengan saran Bahasa Indonesia.

### Diperbaiki
- Fallback fungsi mbstring (`mb_stripos`/`mb_strtolower`/`mb_strlen`) agar aman di server
  tanpa ekstensi mbstring.
- Tautan protocol-relative (`//host/...`) kini terhitung eksternal, bukan internal.
- Deteksi tautan duplikat diaktifkan (sebelumnya setengah jadi).

## [1.0.0] — 2026-10-07

### Ditambahkan
- Rilis perdana.
- Hardening gerbang HTTP MCP Adapter via filter resmi
  `mcp_adapter_default_transport_permission_user_capability` → `edit_posts`
  (dapat dioverride konstanta `DCI_MCP_MIN_CAPABILITY`).
- Kategori ability `dci-content`.
- Kemampuan `dci/generate-text` — jembatan loopback REST ke AI Puffer
  (`aipkit/v1/generate`, autentikasi Bearer Public API Key).
- Kemampuan `dci/create-draft-post` — status dikunci `draft`; konten disanitasi `wp_kses_post`.
- Izin per-kemampuan `edit_posts` pada seluruh ability.
