# Changelog — DCI MCP Bridge

Semua perubahan penting pada proyek ini akan didokumentasikan di sini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/),
versi mengikuti [Semantic Versioning](https://semver.org/lang/id/).

Dikembangkan oleh **Mas Wondho — Duta Corpora Indonesia**.

## [1.9.1] — 2026-10-08

### Diperbaiki
- **Celah read-modify-write** (laporan lapangan: agent mengambil HTML asli via REST API
  karena jembatan tidak menyediakannya). Akar: `dci/get-content` hanya mengembalikan
  teks polos (lossy), padahal `dci/update-*-post` menuntut HTML penuh → agent yang benar
  mencari HTML akan keluar dari jalur jembatan.
- `dci/get-content` kini juga mengembalikan **`content_html`** — HTML mentah tersimpan
  (termasuk markup blok `<!-- wp:... -->`); lebih setia untuk round-trip daripada
  `content.rendered` REST.
- Deskripsi `dci/update-draft-post` & `dci/update-published-post` mewajibkan pola aman:
  fetch `content_html` → sunting hanya bagian target → kirim ulang dokumen lengkap
  (jangan pernah menulis ulang artikel dari nol untuk perubahan kecil).

### Pengujian
- Harness +1 uji (T16a: content_html identik dengan mentah tersimpan) — **52/52 PASS**.

## [1.9.0] — 2026-10-08

### Ditambahkan
- **`dci/update-published-post`** (kemampuan ke-11) — memperbaiki artikel yang sudah
  terbit lewat AI, melengkapi laporan lapangan "PUBLISHED. Ditolak.". Pengaman berlapis:
  - Snapshot revisi WordPress **dipaksa dibuat SEBELUM** perubahan (titik pemulihan);
  - Capability `edit_published_posts` wajib (Editor/Administrator);
  - Konten disanitasi; status selain `publish` ditolak dan diarahkan ke
    `dci/update-draft-post`.
- `dci/set-post-seo-meta`: parameter opsional **`allow_published`** (default false)
  agar meta Rank Math bisa diisi pada artikel terbit — hanya meta, konten tak disentuh.
- Contoh perintah #7 di tab Panduan: memperbaiki artikel terbit (ubah bertahap).

### Berubah
- Pesan penolakan kini saling mengarahkan: draf → `dci/update-draft-post`,
  terbit → `dci/update-published-post` (tidak lagi "edit manual di editor").

### Pengujian
- Harness +4 uji regresi (T15: update terbit + snapshot, tolak draf, meta
  allow_published, tolak tanpa flag) — total **51/51 PASS**.

## [1.8.1] — 2026-10-08

### Berubah
- Contoh perintah di tab **Cara Penggunaan** ditulis ulang untuk pengguna awam:
  - Bahasa sehari-hari penuh — tidak lagi menyebut nama ability teknis
    (`dci/...`); agent memilih alatnya sendiri dari deskripsi kemampuan.
  - **Best practice multi-situs ditanam di contoh**: setiap perintah menyebut
    nama situs ([Nama Situs]) — kebiasaan wajib saat aplikasi AI terhubung
    ke lebih dari satu situs WordPress.
  - 6 contoh: buat artikel, audit & perbaiki, telusuri isi situs, cek integritas,
    revisi gaya bahasa, terbitkan — plus kotak "Kebiasaan emas" dan pola dasar
    perintah (sebut situs → tugas → hasil).

## [1.8.0] — 2026-10-08

### Diperbaiki
- **AI agent salah mengenali domain situs** (laporan lapangan: agent browsing ke
  djayakontainer.com padahal situs .co.id). Akar ganda, keduanya diperbaiki:
  1. Agent tidak pernah diberi konteks identitas situs → kini `instructions`
     handshake berisi nama situs, URL, domain resmi, dan larangan eksplisit
     mensubstitusi domain (via filter `mcp_adapter_default_server_config`;
     `instructions` = server_description, terverifikasi InitializeHandler.php:67).
  2. Tidak ada kemampuan membaca isi situs → agent terpaksa browsing.

### Ditambahkan
- `dci/search-content` — cari/daftar post & page milik situs (kata kunci opsional,
  filter post type, paginasi 1-50; status publish/draft/pending/private/future).
- `dci/get-content` — teks penuh satu konten via post_id atau URL domain situs
  (url_to_postid).
- Kedua ability baca berizin minimal `read`.
- Tab Panduan: bagian "Bagaimana AI Mengenali Situs Ini?" + contoh perintah #5
  (menelusuri isi situs sendiri tanpa browsing, mis. memindai nomor telepon).

### Pengujian
- Harness +7 uji (T13-T14: search/get-content, URL→post, error, identitas server)
  — total **47/47 PASS**.

## [1.7.0] — 2026-10-07

### Ditambahkan
- **Navigasi 5 tab** di halaman DCI Bridge (kelas nav-tab native WordPress):
  Status · Integritas Konten · Koneksi AI Agent · **Cara Penggunaan** · **Tentang**.
- **Tab Cara Penggunaan**: alur kerja standar 8 langkah + 4 contoh perintah siap-salin
  (buat artikel, audit & perbaiki, cek integritas, terbitkan) + catatan biaya kredit.
- **Tab Tentang**: versi, filosofi desain, pengembang, tautan repo GitHub, lisensi,
  dependensi, dan transparansi layanan pihak ketiga (Winston AI).
- **Snippet koneksi FreeBuff / Codebuff** (klien ke-11) — hasil riset dokumentasi
  resmi: file `.agents/mcp.json` (proyek) atau `~/.agents/mcp.json` (global);
  skema `mcpServers` → `type: "http"|"sse"` + `url` + `headers`; file config belakangan
  pada jalur pencarian menimpa yang awal; mendukung rujukan env `$VAR`.
- Redirect setelah simpan API key kembali ke tab Integritas Konten.

## [1.6.1] — 2026-10-07

### Diperbaiki
- **Kartu AI Puffer tetap salah deteksi di situs nyata** (laporan Mas Wondho, meski
  v1.5.1 sudah memperbaiki namespace): kunci API provider tersimpan di
  `aipkit_options['providers'][<Label>]['api_key']` — BUKAN di `['api_keys']`
  (cabang itu hanya memuat `public_api_key`/`public_api_enabled`; bukti:
  `classes/ai/settings.php:1017` milik plugin AI Puffer). Status kini membaca
  cabang yang benar, memprioritaskan aksesor resmi `AIPKIT_AI_Settings::get_all_providers()`;
  `AIPufferCloud` dianggap siap bila model-nya terisi (koneksi cloud tanpa api_key).
- Deteksi "terpasang" diganti ke cara kanonik WordPress: `is_plugin_active()` +
  versi dari `get_plugins()` — tidak lagi bergantung pada kelas internal yang
  bisa berpindah namespace antar versi. Versi terpasang ditampilkan di kartu;
  bila kelas jalur internal tak ditemukan, muncul saran memperbarui AI Puffer.

### Berubah
- Nama server MCP pada semua snippet kini **`<domain>-wordpress`**
  (mis. `djayakontainer-wordpress`) — sufiks menyatakan sistem yang disambungkan,
  domain di depan menjaga pengelompokan per-situs untuk agensi multi-klien.
  Override manual: `define( 'DCI_MCP_SERVER_NAME', 'nama-lain' );`
- Contoh username pada form koneksi memakai `johndoe` (tidak lagi menyebut user nyata).

### Pengujian
- Harness +5 uji regresi (deteksi cabang providers, versi plugin, fallback kelas,
  penamaan server, override konstanta) — total **40/40 PASS**.

## [1.6.0] — 2026-10-07

### Ditambahkan
- **Generator koneksi AI agent** di halaman admin DCI Bridge — 10 potongan konfigurasi
  siap-copy-paste, formatnya diverifikasi dari dokumentasi resmi masing-masing klien:
  - **Claude Code**: `claude mcp add --transport http ... --header` + `.mcp.json` (`type/http/url/headers`).
  - **Cursor**: `~/.cursor/mcp.json` (`type/http/url/headers`).
  - **Codex (OpenAI)**: `~/.codex/config.toml` `[mcp_servers.*]` `url` + `http_headers`.
  - **TRAE**: `mcpServers` via Settings → MCP (`streamable-http`).
  - **OpenClaw**: `openclaw mcp add <slug> --url ... --transport streamable-http --header`.
  - **Antigravity (Google)**: `~/.gemini/antigravity/mcp_config.json` dengan **`serverUrl`**
    (bukan `url` — perbedaan skema antar klien yang mudah keliru).
  - **Hermes**: blok `mcp_servers` di `config.yaml`.
  - **AutoClaw**: JSON standar via Settings → MCP Servers.
  - **Z Code**: `~/.zcode/cli/config.json` → `mcp.servers` (dikonfirmasi dari file lokal).
  - **Custom**: pola standar + cadangan `mcp-remote` (npx) untuk klien STDIO-only.
- **Nama MCP otomatis dari domain situs** (www.djayakontainer.co.id → `djayakontainer`).
- **Kalkulator autentikasi di-browser**: username + Application Password dimasukkan sekali,
  header `Authorization: Basic <base64>` terhitung di browser (JavaScript, tidak dikirim ke
  server, tidak disimpan) dan mengisi seluruh potongan secara live.
- Tombol **Salin** per potongan (clipboard API).

### Keamanan
- Kredensial yang diketik di halaman admin tidak memiliki atribut `name`, tidak pernah
  dikirim ke server, dan tidak disimpan — hilang saat halaman ditutup.

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
