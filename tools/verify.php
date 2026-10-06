<?php
/**
 * Gerbang rilis otomatis DCI MCP Bridge — jalankan SEBELUM commit/tag/push:
 *   php tools/verify.php
 * Menjalankan 7 gerbang docs/QA-PLAYBOOK.md bagian 3. Exit 0 = semua hijau.
 *
 * Desain: TANPA eksekusi shell (aman dari risiko command injection) —
 * harness dijalankan in-process (require berhasil = plugin terparse penuh,
 * setara php -l), gerbang lain murni pembacaan file statis.
 */

$root = __DIR__ . '/..';

/* Gerbang 1+2 — Harness runtime in-process (mencakup parse/lint: require gagal = fatal) */
define( 'DCI_HARNESS_NO_EXIT', true );
require __DIR__ . '/harness.php'; // meng-echo hasil tiap uji sendiri

$pass = 0;
$fail = 0;

function gate( $label, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $label\n"; }
	else { $fail++; echo "FAIL  $label" . ( '' !== $detail ? "  -- $detail" : '' ) . "\n"; }
}

gate( 'G1+G2 lint+harness runtime (' . $PASS . ' uji)', 0 === $FAIL );

/* Gerbang 3 — Konsistensi versi di 5 tempat */
$src    = (string) file_get_contents( $root . '/dci-mcp-bridge.php' );
$readme = (string) file_get_contents( $root . '/readme.txt' );
$chg    = (string) file_get_contents( $root . '/CHANGELOG.md' );
$md     = (string) file_get_contents( $root . '/README.md' );

preg_match( '/\* Version:\s+([0-9.]+)/', $src, $m1 );
preg_match( "/define\( 'DCI_MCP_BRIDGE_VERSION', '([0-9.]+)' \)/", $src, $m2 );
preg_match( '/Stable tag:\s+([0-9.]+)/', $readme, $m3 );
preg_match( '/## \[([0-9.]+)\]/', $chg, $m4 );
preg_match( '/version-([0-9.]+)-blue/', $md, $m5 );

$versions = array( $m1[1] ?? '', $m2[1] ?? '', $m3[1] ?? '', $m4[1] ?? '', $m5[1] ?? '' );
gate( 'G3 versi konsisten (5 tempat)', count( array_unique( $versions ) ) === 1 && '' !== $versions[0], implode( ' | ', $versions ) );

/* Gerbang 4 — Anti-Typo: smart quotes / nbsp / debug code */
$typo_hits = array();
foreach ( array( $root . '/dci-mcp-bridge.php', $root . '/README.md', $root . '/readme.txt' ) as $f ) {
	$lines = explode( "\n", (string) file_get_contents( $f ) );
	foreach ( $lines as $i => $line ) {
		if ( preg_match( '/[\x{2018}\x{2019}\x{201C}\x{201D}\x{00A0}]/u', $line ) ) {
			$typo_hits[] = basename( $f ) . ':' . ( $i + 1 );
		}
	}
}
foreach ( explode( "\n", $src ) as $i => $line ) {
	if ( preg_match( '/var_dump|print_r\(|var_export|console\.log/', $line ) ) {
		$typo_hits[] = 'plugin-debug:' . ( $i + 1 );
	}
}
gate( 'G4 anti-typo (quote aneh/nbsp/debug)', empty( $typo_hits ), implode( ', ', array_slice( $typo_hits, 0, 5 ) ) );

/* Gerbang 5 — Anti-leak: identifikator klien nyata (CHANGELOG historis dibebaskan) */
$leak_patterns = array( 'djayakontainer', 'djaya kontainer', 'miftakululum', 'salimahcatering', 'ONCF', 'Nindhita', 'ulum@dutacorpora' );
$leak_hits     = array();
foreach ( array( $root . '/dci-mcp-bridge.php', $root . '/README.md', $root . '/readme.txt' ) as $f ) {
	$lines = explode( "\n", (string) file_get_contents( $f ) );
	foreach ( $lines as $i => $line ) {
		foreach ( $leak_patterns as $pat ) {
			if ( false !== stripos( $line, $pat ) ) {
				$leak_hits[] = basename( $f ) . ':' . ( $i + 1 ) . " ($pat)";
			}
		}
	}
}
gate( 'G5 anti-leak (tanpa identitas klien nyata)', empty( $leak_hits ), implode( ', ', array_slice( $leak_hits, 0, 5 ) ) );

/* Gerbang 6 — Tabel admin memuat semua ability yang teregistrasi */
preg_match_all( "/wp_register_ability\(\s*\n\s*'([^']+)'/", $src, $reg );
preg_match_all( "/array\( 'name' => '(dci\/[a-z-]+)'/", $src, $tbl );
$missing = array_diff( $reg[1], $tbl[1] );
gate( 'G6 tabel admin = registry ability', empty( $missing ), 'hilang: ' . implode( ', ', $missing ) );

/* Gerbang 7 — ZIP rilis (PHP ZipArchive, forward-slash) + verifikasi isi */
$zip_path = dirname( $root ) . '/dci-mcp-bridge.zip';
$zip_ok   = false;
$zip      = new ZipArchive();
if ( true === $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	foreach ( array( 'dci-mcp-bridge.php', 'README.md', 'CHANGELOG.md', 'readme.txt' ) as $f ) {
		$zip->addFile( $root . '/' . $f, 'dci-mcp-bridge/' . $f );
	}
	$zip->close();

	$zip2   = new ZipArchive();
	$zip_ok = ( true === $zip2->open( $zip_path ) );
	$names  = array();
	for ( $i = 0; $i < $zip2->numFiles; $i++ ) { $names[] = $zip2->getNameIndex( $i ); }
	$zip2->close();
	$zip_ok = $zip_ok && 4 === count( $names ) && ! in_array( false, array_map( 'strpos', $names, array_fill( 0, count( $names ), '/' ) ), true );
}
gate( 'G7 ZIP rilis (4 file, forward-slash)', $zip_ok );

echo "\n=== VERIFIKASI GERBANG: $pass PASS, $fail FAIL ===\n";
exit( $fail > 0 ? 1 : 0 );
