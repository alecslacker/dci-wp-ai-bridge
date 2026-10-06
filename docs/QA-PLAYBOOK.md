# QA Playbook — DCI MCP Bridge

Dokumen audit retrospetif + sistem pencegahan. Dibuat 2026-10-08 setelah serangkaian
kesalahan lapangan. **Setiap perubahan plugin WAJIB melewati gerbang di bawah.**
Alat otomatis: `php tools/verify.php` (menjalankan gerbang 1–5 sekaligus).

## 1. Inventaris Kesalahan (jujur, kronologis)

| # | Insiden | Terdeteksi | Versi Perbaikan | Kelas |
|---|---------|-----------|-----------------|-------|
| 1 | `class_exists('AIPKit_AI_Caller')` salah namespace (asli `WPAICG\Core\...`) | **Lapangan** (kartu NONAKTIF) | v1.5.1 | A |
| 2 | Baca kunci provider di `aipkit_options['api_keys']` — salah cabang (asli `['providers']`, bukti settings.php:1017) | **Lapangan** (masih salah deteksi) | v1.6.1 | A |
| 3 | `get-content` lossy (teks polos) vs `update-*-post` full-replacement → agent kabur ke REST API | **Lapangan** (tanya user) | v1.9.1 | B |
| 4 | Tidak ada ability BACA sama sekali + tanpa identitas situs di handshake → agent browsing & salah domain (.com vs .co.id) | **Lapangan** | v1.8.0 | C |
| 5 | Username asli (miftakululum) sebagai placeholder contoh → ikut ter-push ke repo publik | **User** (Mas Wondho) | v1.6.1 | D |
| 6 | Nama klien nyata pada contoh perintah | **User** | v1.8.1-revisi | D |
| 7 | Domain klien nyata pada contoh/dokumentasi (6 titik) | **Audit ini** | v1.9.2 | D |
| 8 | `git add -A` menyedot folder tooling `.mimosa/` ke commit perdana | Internal (sebelum push) | amend + .gitignore | E |
| 9 | ZIP PowerShell ber-backslash (risiko ekstraksi Linux) | Internal | rebuild PHP ZipArchive | E |
| 10 | Header komentar seksi (BAGIAN 5/6) tertelan saat penyisipan Edit — **2× terulang** | Internal (lint) | — | F |
| 11 | Paragraf memori tertimpa saat update — **2× terulang** | Internal | — | F |
| 12 | Ekspektasi hitungan harness usang (8→10→11) | Internal | kini T17 dinamis | F |
| 13 | Typo CHANGELOG ("SELESAU") | Internal (self-review) | — | G |
| 14 | `max_tokens` vs `max_completion_tokens` lintas-provider AI Puffer | Internal (cek source) | sejak v1.0.0 | A (dicegah) |
| 15 | Opsi Rank Math tanda hubung vs postmeta underscore | Internal (cek source) | sejak v1.2.0 | A (dicegah) |
| 16 | verify.php G7: `dirname('...tools/..')` = `.../tools` → zip dibangun di lokasi salah, gerbang PASS padahal zip distribusi tetap v1.9.1; aset rilis terunggah dari zip basi | **User (Mas Wondho)** "zip belum diupdate" | v2.0.0 (asset diganti) | E |

## 2. Analisis Akar-Masalah per Kelas

### Kelas A — Asumsi kontrak internal pihak ketiga tanpa bukti source (paling mahal: 2× kena di lapangan)
**Akar**: menulis `class_exists`/baca opsi dari ingatan/inferensi, bukan dari source.
**Aturan pencegahan (WAJIB)**:
1. Sebelum memakai kelas/metode/opsi plugin pihak ketiga: grep source aslinya,
   tulis `file:line` sebagai komentar pada kode pemanggil.
2. Prioritaskan jalur **kanonik** (is_plugin_active + get_plugins) dan **aksesor publik**
   plugin itu sendiri — bukan struktur internal mentah.
3. Stub harness WAJIB memakai namespace/FQCN asli (pelajaran v1.5.1: stub global
   tidak menangkap salah namespace).

### Kelas B — Pasangan read/write tidak lossless-kompatibel
**Akar**: ability dirancang sendiri-sendiri, tidak diuji sebagai siklus.
**Aturan**: setiap ability TULIS harus punya uji "round-trip": baca → sunting → tulis →
baca lagi identik di luar bagian yang diubah. Format yang dituntut write wajib
disediakan read (v1.9.1: `content_html`).

### Kelas C — Desain tanpa menelusuri lingkar tugas agent
**Akar**: hanya merancang dari SOP produksi, tidak bertanya "apa yang perlu DILIHAT
agent untuk menyelesaikan permintaan pengguna nyata?"
**Aturan**: setiap use case pengguna ditelusuri ujung-ke-ujung (prompt contoh nyata
→ alat apa saja yang disentuh). Bila agent bakal " keluar jalur" (browsing/REST),
itu kebutuhan ability yang belum ada.

### Kelas D — Data klien nyata di contoh/teks templat
**Aturan**: contoh selalu pakai placeholder/netral ([Nama Situs], johndoe,
namadomain.co.id). Gerbang 5 memindai ini otomatis.

### Kelas E — Higien artefak build/git
**Aturan**: ZIP hanya via PHP ZipArchive (forward-slash); sebelum commit periksa
`git status` — folder tooling/sekret tidak boleh masuk; **path tooling wajib
`realpath()` sebelum `dirname()`** (jebakan `dirname('/..')` = direktori salah,
insiden #16); **verifikasi versi SELALU dibaca dari DALAM arsip** (bukan mtime/nama);
aset rilis GitHub diberi **nama berversi** (`dci-mcp-bridge-{VERSI}.zip`) agar
kebal cache CDN pada penggantian aset senama (updater memakai fallback "zip pertama").

### Kelas F — Pola kesalahan penyuntingan berulang
**Aturan**: (a) saat menyisipkan seksi via edit teks, anchor WAJIB memuat baris
header seksi berikutnya lalu dikembalikan; (b) update memori/panjang: anchor unik
di akhir konten, bukan di kepala paragraf lain; (c) hitungan dalam uji jangan
hardcode — turunkan dari registry (T17).

### Kelas G — Typo
**Aturan**: gerbang Anti-Typo 3 lapis (struktur/identifier/semantik) dijalankan untuk
semua file yang dikirim, termasuk dokumen.

## 3. Gerbang Rilis (WAJIB — jalankan `php tools/verify.php`)

1. **Lint**: `php -l` plugin — 0 error.
2. **Harness**: `php tools/harness.php` — 0 FAIL (termasuk regresi tiap bug lampau:
   T11 namespace, T12 cabang providers, T13/16 baca lossless, T15 artikel terbit).
3. **Konsistensi versi**: header plugin = konstanta = Stable tag readme = judul
   CHANGELOG terbaru = badge README — semua identik.
4. **Anti-Typo**: tanpa smart-quote/nbsp/debug-code di file yang dikirim.
5. **Anti-leak**: tanpa identifikator klien nyata (domain/nama/user/kunci) di
   `dci-mcp-bridge.php`, `README.md`, `readme.txt` (CHANGELOG dibebaskan —
   catatan sejarah).
6. **Konsistensi UI**: tabel ability di halaman admin = registry (dijamin T17).
7. **Rilis**: ZIP dibangun PHP ZipArchive, isi diverifikasi, commit + tag + push.

## 4. Checklist Pra-Rilis Manual (tidak bisa diautomasi)

- [ ] Ability baru: apakah ada ability BACA yang menyediakan semua format yang
      dibutuhkan ability TULIS? (Kelas B)
- [ ] Ability baru: telusuri satu prompt nyata pengguna ujung-ke-ujung (Kelas C).
- [ ] Integrasi pihak ketiga baru: bukti `file:line` dari source di komentar (Kelas A).
- [ ] Teks contoh/UI: placeholder netral saja (Kelas D).
- [ ] Bump versi di 4 file sekaligus (plugin header, konstanta, readme, CHANGELOG).
- [ ] RILIS (sejak v2.0.0): buat GitHub Release dari tag, **lampirkan aset ZIP
      dengan NAMA BERVERSI** `dci-mcp-bridge-{VERSI}.zip` (salin/rename hasil G7
      verify.php) — nama berversi mencegah CDN menyajikan byte lama saat aset
      diganti, dan tanpa aset updater tidak menemukan paket; zip tanpa struktur
      folder yang benar akan terpasang sebagai duplikat.
