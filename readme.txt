=== DCI MCP Bridge ===
Contributors: dutacorpora
Tags: mcp, ai-agent, seo, rank-math, konten, otomasi
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Jembatan AI profesional untuk WordPress: hubungkan AI agent ke situs Anda untuk produksi konten SEO end-to-end — riset, tulis, audit, set meta Rank Math, lalu terbit atau simpan sebagai draf.

== Description ==

DCI MCP Bridge melengkapi plugin MCP Adapter resmi WordPress menjadi meja kerja konten SEO yang lengkap dan aman untuk AI agent (Claude, Cursor, Z Code, dan klien MCP lainnya).

= Fitur Utama =

* **Hardening gerbang MCP** — akses HTTP MCP Adapter dibatasi untuk Editor/Administrator (default pabriknya hanya "user login"). Bisa diperketat lewat konstanta `DCI_MCP_MIN_CAPABILITY`.
* **7 kemampuan (abilities) siap MCP** — semuanya tampil otomatis di MCP Adapter:
	* `dci/generate-text` — generate teks via mesin AI Puffer yang terpasang di situs (OpenAI, Claude, Gemini, DeepSeek, dll).
	* `dci/create-draft-post` — simpan artikel baru; **selalu menjadi draf**, tidak pernah langsung terbit.
	* `dci/update-draft-post` — perbaiki isi draf; artikel yang sudah terbit ditolak.
	* `dci/set-post-seo-meta` — isi field SEO Rank Math pada draf: meta title/description, focus keyword, OG title/description, direktif robots.
	* `dci/audit-article` — audit SEO on-page deterministik: panjang judul & meta, posisi & kepadatan kata kunci, struktur heading, tautan internal/eksternal, anchor generik, tautan duplikat, alt text gambar, panjang konten, skor Rank Math, plus pemindai frasa klise AI generik (EN+ID).
	* `dci/publish-post` — terbitkan draf sebagai aksi eksplisit terpisah (butuh kapabilitas `publish_posts`).
	* `dci/get-seo-config` — baca konfigurasi best-practice Rank Math (modul aktif, format judul/deskripsi, robots default, tipe schema) agar konten selalu sinkron.
* **Halaman admin "DCI Bridge"** — status kesehatan integrasi (MCP Adapter, AI Puffer, Rank Math), tabel kemampuan terdaftar, konfigurasi keamanan aktif, dan panduan koneksi AI agent.
* **Prinsip keamanan berlapis** — gerbang transport + izin per-kemampuan; konten disanitasi `wp_kses_post`; kunci API tidak pernah di-hardcode.

= Pembagian Kerja =

Plugin adalah "tangan"; AI agent adalah "otak". Riset data aktual dan penulisan anti-klise dilakukan AI agent; plugin menyediakan jalur buat → audit → perbaiki → set meta → terbit/draf yang aman dan terkendali.

== Installation ==

1. Pasang dan aktifkan plugin **MCP Adapter** terlebih dahulu (dependensi wajib).
2. Buka `Plugins → Add New → Upload Plugin`, unggah `dci-mcp-bridge.zip`, lalu aktifkan.
3. Untuk kemampuan generate teks: pastikan ada **API key provider AI** yang terisi di AIPKit (AI Puffer) → Settings (mis. OpenAI / Claude / Google / DeepSeek). Public API access bersifat opsional sejak v1.3.1.
4. Buka menu **DCI Bridge** di wp-admin untuk memastikan ketiga status integrasi berwarna hijau.
5. Hubungkan AI agent: arahkan MCP client ke endpoint yang tertera di halaman DCI Bridge, autentikasi dengan user + Application Password (disarankan user khusus peran Editor).

== Frequently Asked Questions ==

= Apakah AI bisa menerbitkan artikel sendiri tanpa sepengetahuan saya? =

Tidak otomatis. Semua pembuatan dan perbaikan selalu berhenti di draf. Penerbitan hanya terjadi bila `dci/publish-post` dipanggil secara eksplisit — dan tetap dibatasi kapabilitas `publish_posts`. Untuk kontrol penuh, minta AI selalu berhenti di draf dan tinjau manual sebelum terbitkan.

= Apa bedanya dengan plugin MCP Adapter? =

MCP Adapter adalah gerbang resmi yang mengekspos "abilities" WordPress ke protokol MCP. DCI MCP Bridge adalah lapisan pendamping: mengamankan gerbangnya, menambah kemampuan produksi konten SEO, dan menjembatani mesin AI Puffer serta field Rank Math ke dalam abilities.

= Apakah aman untuk situs produksi? =

Dirancang dengan prinsip keamanan berlapis: gerbang transport diperketat, izin per-kemampuan, sanitasi konten, whitelist direktif robots, status draf terkunci pada kemampuan tulis konten, dan penerbitan sebagai aksi eksplisit. Selalu gunakan akun khusus berperan minimal untuk AI.

= Plugin ini punya halaman pengaturan? =

Ya. Menu **DCI Bridge** di wp-admin menampilkan status integrasi secara langsung. Konfigurasi tambahan (opsional) cukup satu baris di `wp-config.php`:

`define( 'DCI_MCP_MIN_CAPABILITY', 'manage_options' ); // hanya Administrator`

= Mengapa kemampuan DCI tidak muncul di daftar abilities? =

Pastikan WordPress 6.9+ (Abilities API ada di inti sejak 6.9), plugin MCP Adapter aktif, dan halaman DCI Bridge menampilkan status "Terdaftar" pada tabel kemampuan.

== Screenshots ==

1. Halaman DCI Bridge — status integrasi, konfigurasi keamanan, tabel kemampuan, dan panduan koneksi AI agent.

== Changelog ==

= 2.2.0 =
* Baru: **dukungan halaman (page) menyeluruh** — update-draft-post, update-published-post, get-content, audit-article, check-originality, set-post-seo-meta, set-featured-image, dan publish-post kini menerima post MAUPUN page, dengan kapabilitas WordPress yang mengikuti tipe (*_posts vs *_pages).
* Baru: kemampuan `dci/update-elementor-text` — sunting teks halaman Elementor **di sumber sebenarnya** (`_elementor_data`) lewat find/replace persis; mengedit post_content halaman Elementor akan tertimpa builder, jadi jalur inilah yang benar. Cache CSS Elementor dibersihkan otomatis setelah simpan.
* Peningkatan: `dci/get-content` kini sadar Elementor — mendeteksi halaman builder dan mengembalikan `elementor.texts` (teks semua widget heading/text-editor) agar AI bisa membaca tanpa membongkar JSON.
* Peningkatan: `dci/bulk-audit` mendapat opsi `include_pages`.
* Kontrak Elementor terverifikasi dari source: meta `_elementor_data`/`_elementor_edit_mode`, kunci teks widget `title` (heading) & `editor` (text-editor), dan `Files_Manager::clear_cache()`.
* Total 17 kemampuan. Harness +6 uji (74/74 PASS).

= 2.1.0 =
* Baru (Tier 1 "Mata Agensi", semua read-only kecuali dua perbaikan media): `dci/site-report` — snapshot operasional satu panggilan (versi WP/PHP, tema, struktur permalink, kesehatan cron, inventaris plugin + daftar pembaruan menunggu dari pengecekan terjadwal WP, hitungan konten/media).
* Baru: `dci/bulk-audit` — audit banyak artikel sekaligus (1-50) dengan ringkasan per-artikel + peringkat terburuk-dulu.
* Baru: `dci/list-media` — inventaris gambar lengkap dengan ALT TEXT (menemukan celah SEO alt kosong).
* Baru: `dci/set-media-alt` — perbaiki alt text gambar langsung dari AI.
* Baru: `dci/set-featured-image` — pasang gambar utama artikel dari media library (draf default; artikel terbit butuh allow_published).
* Total 16 kemampuan. Harness +8 uji (68/68 PASS).

= 2.0.0 =
* Baru: **pembaruan otomatis dari GitHub Releases** — plugin memeriksa `releases/latest` (cache 1 jam); bila ada versi baru, pembaruan muncul di Dashboard → Updates WordPress seperti plugin resmi. Tidak perlu plugin pihak ketiga.
* Baru: kartu **Pembaruan** di tab Tentang (status versi + kolom token GitHub opsional untuk repo privat; alternatif konstanta `DCI_GITHUB_TOKEN`).
* Keamanan: URL paket divalidasi — hanya HTTPS di domain github.com/githubusercontent.com/githubassets.com.
* Catatan rilis: aset Release wajib bernama `dci-mcp-bridge.zip` (struktur folder dijamin tools/verify.php) agar WordPress meng-update, bukan memasang duplikat.

= 1.9.2 =
* Audit menyeluruh + sistem pencegahan permanen (docs/QA-PLAYBOOK.md); 6 titik domain klien nyata pada contoh diganti netral; tools/verify.php (gerbang rilis 7 lapis, tanpa shell-exec); harness resmi masuk repo + T17 konsistensi UI↔registry.

= 1.9.1 =
* Perbaikan desain (laporan lapangan: agent terpaksa ambil HTML via REST API karena tidak tersedia di jembatan): `dci/get-content` kini mengembalikan `content_html` — HTML mentah tersimpan termasuk markup blok WordPress — sehingga siklus baca→sunting→tulis penuh (read-modify-write) selesai di satu jalur terkendali, tanpa REST, tanpa risiko kehilangan format.
* Peningkatan: deskripsi `dci/update-draft-post` & `dci/update-published-post` kini mewajibkan pola aman — ambil content_html dulu, sunting hanya bagian targetnya, kirim ulang dokumen lengkap.

= 1.9.0 =
* Baru: kemampuan `dci/update-published-post` — memperbaiki artikel yang SUDAH TERBIT lewat AI (laporan lapangan: sebelumnya selalu ditolak). Pengaman: snapshot revisi WordPress dibuat otomatis SEBELUM perubahan (titik pemulihan), capability `edit_published_posts` wajib, dan perubahan langsung live.
* Peningkatan: `dci/set-post-seo-meta` kini mendukung parameter `allow_published` (default false) untuk mengisi meta pada artikel terbit — hanya meta, konten tak disentuh.
* Pesan penolakan diarahkan: draf ditolak update-published-post (→ update-draft-post), dan sebaliknya.
* Tab Cara Penggunaan: contoh #6 memperbaiki artikel terbit (dengan penekanan ubah bertahap).

= 1.8.1 =
* Peningkatan: contoh perintah di tab Cara Penggunaan ditulis ulang untuk pengguna awam — bahasa sehari-hari penuh (tanpa istilah teknis ability), 6 contoh mencakup buat/audit/telusuri/cek-integritas/revisi/terbit.
* Baru: kotak "Kebiasaan emas" — selalu sebut nama situs di perintah saat aplikasi AI terhubung ke lebih dari satu situs (best practice multi-situs), plus pola dasar perintah: sebut situs → tugas → hasil yang diharapkan.

= 1.8.0 =
* Perbaikan penting (laporan lapangan): AI agent tidak mengenali situs yang dipegangnya dan menebak domain keliru (.com alih-alih .co.id). Dua akar masalah diperbaiki:
* Baru: **identitas situs otomatis** — setiap AI client kini menerima nama situs, URL, dan domain resmi saat handshake (via server_description MCP Adapter), termasuk larangan eksplisit mengganti domain.
* Baru: kemampuan `dci/search-content` — cari/daftar artikel & laman situs sendiri (bukan browsing web).
* Baru: kemampuan `dci/get-content` — ambil teks penuh satu artikel/laman berdasarkan ID atau URL situs.
* Peningkatan: tab Cara Penggunaan kini memuat bagian "Bagaimana AI Mengenali Situs Ini?" + contoh perintah menelusuri isi situs sendiri (kasus: memindai nomor telepon di semua artikel).

= 1.7.0 =
* Baru: halaman admin diorganisasi ke dalam **5 tab native WordPress** — Status, Integritas Konten, Koneksi AI Agent, **Cara Penggunaan** (alur kerja 8 langkah + contoh perintah siap-salin), dan **Tentang** (pengembang, kode sumber, lisensi, dependensi, layanan pihak ketiga).
* Baru: potongan koneksi untuk **FreeBuff / Codebuff** (klien ke-11) — file `.agents/mcp.json` (proyek) atau `~/.agents/mcp.json` (global), format terverifikasi dari dokumentasi resmi Codebuff (type http + url + headers, mendukung rujukan variabel lingkungan $VAR).

= 1.6.1 =
* Perbaikan penting (laporan lapangan): kartu AI Puffer tetap salah deteksi meski provider sudah aktif — kunci API provider ternyata tersimpan di `aipkit_options['providers']` (bukan `['api_keys']` yang hanya memuat Public API; terverifikasi dari `classes/ai/settings.php` plugin AI Puffer). Deteksi kini membaca cabang yang benar, memakai aksesor resmi `get_all_providers()` bila tersedia.
* Deteksi "terpasang" kini kanonik WordPress (`is_plugin_active` + versi dari `get_plugins`) — tahan terhadap perbedaan versi plugin AI Puffer; versi terpasang kini tampil di kartu status, plus petunjuk bila versi terlalu lama untuk jalur internal.
* Nama MCP kini `<domain>-wordpress` (mis. domain `tokokue.co.id` → `tokokue-wordpress`) — jelas bahwa server ini menghubungkan AI ke WordPress situs Anda; bisa dioverride konstanta `DCI_MCP_SERVER_NAME`.
* Contoh username pada form koneksi diganti generik (`johndoe`).
* Harness: +5 uji regresi (total 40/40 PASS).

= 1.6.0 =
* Baru: **generator koneksi AI agent** di halaman DCI Bridge — potongan konfigurasi siap-copy-paste untuk 10 klien: Claude Code, Cursor, Codex CLI (TOML), TRAE, OpenClaw (CLI), Antigravity (serverUrl), Hermes (YAML), AutoClaw, Z Code, dan pola custom (termasuk cadangan mcp-remote untuk klien STDIO-only).
* Baru: **nama MCP otomatis dari domain situs** (mis. www.tokokue.co.id → tokokue).
* Baru: **kalkulator autentikasi di-browser** — isi username + Application Password sekali, seluruh potongan terisi header Authorization otomatis (base64 dihitung di browser, tidak dikirim ke mana pun, tidak disimpan).
* Baru: tombol Salin per potongan; format tiap klien diambil dari dokumentasi resminya (bukan opini).

= 1.5.1 =
* Perbaikan penting: deteksi mesin AI Puffer gagal di situs nyata karena kelas internal berada di namespace WPAICG (WPAICG\Core\AIPKit_AI_Caller) — sebelumnya diperiksa sebagai kelas global, sehingga kartu status menampilkan NONAKTIF dan generate selalu jatuh ke jalur cadangan. Kini resolusi FQCN mencoba WPAICG\Core\, WPAICG\, lalu global.
* Penamaan kartu status "AI Puffer (Public API)" diperjelas menjadi "AI Puffer (Mesin AI)" — Public API access bukan prasyarat jembatan ini.
* Regresi: harness runtime kini menyertakan stub namespace WPAICG + 4 uji baru (35/35 PASS).

= 1.5.0 =
* Baru: **rotasi multi API key Winston AI** — tempel beberapa kunci (satu per baris) di halaman DCI Bridge, atau konstanta `DCI_WINSTON_API_KEYS` di wp-config.php. Semua kunci diputar bergantian (round-robin) agar beban kredit tersebar merata.
* Baru: **failover otomatis** — bila satu kunci ditolak (401), habis kredit (402), atau kena batas laju (429), sistem langsung pindah ke kunci berikutnya tanpa gagal.
* Baru: **cooldown 30 menit per kunci** yang bermasalah — tidak membuang percobaan berulang; kunci otomatis kembali dipakai setelah jeda.
* Migrasi otomatis: kunci tunggal versi lama tetap terbaca tanpa perlu diinput ulang.
* Peningkatan: `dci/get-seo-config` melaporkan jumlah kunci terpasang; halaman admin menampilkan status "N KUNCI AKTIF" dengan 4 karakter terakhir masing-masing.

= 1.4.0 =
* Baru: kemampuan `dci/check-originality` — gerbang finalisasi artikel via **Winston AI**: skor "kemiripan manusia" (deteksi AI, mendukung Bahasa Indonesia) dengan kalimat yang di-flag, skor keterbacaan, persentase plagiarisme, dan 5 sumber kecocokan teratas. Berbiaya kredit per kata (AI: 1/kata, plagiarisme: 2/kata) sehingga dipanggil eksplisit menjelang final.
* Baru: kolom **API Key Winston AI** di halaman admin DCI Bridge (tersimpan tersembunyi; alternatif: konstanta `DCI_WINSTON_API_KEY` di wp-config.php).
* Peningkatan: `dci/get-seo-config` kini melaporkan status integritas (apakah API key Winston sudah terpasang) agar AI agent tahu kapan pemeriksaan bisa dijalankan.
* Catatan metodologis: hasil detektor AI bersifat heuristik — sinyal, bukan vonis; selalu dipadukan audit on-page dan tinjauan manusia.

= 1.3.1 =
* Perbaikan penting: `dci/generate-text` kini memanggil mesin AI Puffer secara internal langsung (tanpa HTTP) — mengatasi koneksi loopback yang diblokir sebagian hosting (timeout). Public API access AI Puffer kini TIDAK lagi wajib; cukup ada API key provider yang terisi di AIPKit.
* Peningkatan: `dci/get-seo-config` kini juga melaporkan status mesin AI Puffer dan daftar nama provider yang API key-nya terisi (nama saja, nilai kunci tidak pernah dibocorkan) sehingga AI agent selalu memakai provider yang benar.
* Halaman admin DCI Bridge: status AI Puffer kini mendeteksi provider terisi, bukan hanya flag Public API.
* Jalur cadangan REST loopback tetap tersedia otomatis bila kelas internal AI Puffer tidak ditemukan.

= 1.3.0 =
* Baru: halaman admin "DCI Bridge" — status kesehatan integrasi (MCP Adapter / AI Puffer / Rank Math), tabel kemampuan terdaftar, info keamanan aktif, panduan koneksi AI agent.
* Baru: notifikasi selamat datang (sekali tampil) dan peringatan otomatis bila dependensi MCP Adapter tidak aktif.
* Branding resmi: Mas Wondho - Duta Corpora Indonesia.
* Dokumentasi profesional: readme.txt standar WordPress.org + CHANGELOG.md.

= 1.2.0 =
* Baru: kemampuan `dci/publish-post` — penerbitan eksplisit terpisah (kapabilitas `publish_posts`).
* Baru: kemampuan `dci/get-seo-config` — baca konfigurasi best-practice Rank Math (modul aktif, format title/description, robots default, schema).
* Peningkatan: `dci/set-post-seo-meta` kini mendukung OG title/description dan direktif robots (whitelist nilai valid).
* Peningkatan: `dci/audit-article` menambah pemindai frasa klise AI generik (EN+ID) agar tulisan tidak terbaca generik.

= 1.1.0 =
* Baru: kemampuan `dci/update-draft-post` — perbaiki draf (artikel terbit ditolak).
* Baru: kemampuan `dci/set-post-seo-meta` — isi field SEO Rank Math pada draf.
* Baru: kemampuan `dci/audit-article` — audit SEO on-page deterministik dengan saran perbaikan Bahasa Indonesia.
* Perbaikan: fallback ekstensi mbstring, klasifikasi tautan protocol-relative, deteksi tautan duplikat.

= 1.0.0 =
* Rilis perdana: hardening gerbang MCP Adapter, kemampuan `dci/generate-text` (jembatan AI Puffer) dan `dci/create-draft-post`.
