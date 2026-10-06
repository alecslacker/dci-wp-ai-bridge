<?php
/**
 * Harness uji runtime DCI MCP Bridge (PHP 8.3+, stub WordPress + namespace WPAICG).
 * Menjalankan plugin secara nyata — termasuk regresi seluruh bug lampau
 * (liat docs/QA-PLAYBOOK.md). Jalankan: php tools/harness.php
 */

/* ---------- Stub kelas AI Puffer (namespace asli: WPAICG) ---------- */
namespace WPAICG\Core {
	class AIPKit_AI_Caller {
		public function make_standard_call( $provider, $model, $messages, $ai_params = array(), $base = null, $ctx = array() ) {
			if ( ! empty( $GLOBALS['dci_internal_result'] ) ) {
				return $GLOBALS['dci_internal_result'];
			}
			return array( 'content' => 'INTERNAL-OK', 'model' => 'stub' );
		}
	}
}

namespace WPAICG {
	class AIPKit_Providers {
		public static function normalize_provider_label( $provider ) {
			$provider = trim( (string) $provider );
			foreach ( array( 'OpenAI', 'Claude', 'Google' ) as $known ) {
				if ( strtolower( $known ) === strtolower( $provider ) ) {
					return $known;
				}
			}
			return $provider;
		}
		public static function get_text_generation_providers() {
			return array( 'OpenAI', 'Claude', 'Google' );
		}
	}
}

/* ---------- Harness global ---------- */
namespace {
	define( 'ABSPATH', 'C:/fake/' );
	define( 'HOUR_IN_SECONDS', 3600 );

	$GLOBALS['dci_store']      = array();
	$GLOBALS['dci_abilities']  = array();
	$GLOBALS['dci_category']   = null;
	$GLOBALS['dci_actions']    = array();
	$GLOBALS['dci_filters']    = array();
	$GLOBALS['dci_http_log']   = array();
	$GLOBALS['dci_http_queue'] = array();
	$GLOBALS['dci_post']       = null;
	$GLOBALS['dci_meta']       = array();
	$GLOBALS['dci_internal_result'] = null;
	$GLOBALS['dci_plugins']    = array();
	$GLOBALS['dci_posts_list'] = array();
	$GLOBALS['dci_url_map']    = array();
	$GLOBALS['dci_revisions']  = array();

	class WP_Error {
		public $code; public $message;
		public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; }
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
	function is_wp_error( $t ) { return $t instanceof WP_Error; }

	function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['dci_actions'][ $h ][] = $cb; }
	function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['dci_filters'][ $h ][] = $cb; }
	function __( $s, $d = null ) { return $s; }
	function esc_html__( $s, $d = null ) { return $s; }
	function esc_attr__( $s, $d = null ) { return $s; }
	function _n( $s, $p, $n, $d = null ) { return $p; }
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function esc_url( $s ) { return $s; }
	function absint( $n ) { return abs( (int) $n ); }
	function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
	function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ) ); }
	function wp_unslash( $v ) { return $v; }
	function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
	function wp_kses_post( $s ) { return $s; }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['dci_store'] ) ? $GLOBALS['dci_store'][ $k ] : $d; }
	function update_option( $k, $v, $a = null ) { $GLOBALS['dci_store'][ $k ] = $v; return true; }
	function get_post( $id ) {
		foreach ( (array) $GLOBALS['dci_posts_list'] as $p ) { if ( (int) $p->ID === (int) $id ) { return $p; } }
		return $GLOBALS['dci_post'];
	}
	function get_permalink( $id ) { return 'https://example.test/p' . $id; }
	function get_edit_post_link( $id, $ctx ) { return 'https://example.test/wp-admin/post.php?post=' . $id . '&action=edit'; }
	function get_preview_post_link( $id ) { return 'https://example.test/?p=' . $id . '&preview=1'; }
	function get_current_user_id() { return 1; }
	function current_user_can( $c ) { return true; }
	function get_rest_url( $b = null, $p = '' ) { return 'https://example.test/wp-json/' . $p; }
	function home_url( $p = '' ) { return 'https://example.test/' . $p; }
	function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
	function get_post_meta( $id, $k, $s = false ) {
		if ( isset( $GLOBALS['dci_meta'][ (int) $id ][ $k ] ) ) { return $GLOBALS['dci_meta'][ (int) $id ][ $k ]; }
		return isset( $GLOBALS['dci_meta'][ $k ] ) ? $GLOBALS['dci_meta'][ $k ] : '';
	}
	function update_post_meta( $id, $k, $v ) { $GLOBALS['dci_meta'][ (int) $id ][ $k ] = $v; return true; }
	function wp_update_post( $arr, $err = false ) { return $arr['ID']; }
	function wp_insert_post( $arr, $err = false ) { return 777; }
	function number_format_i18n( $n ) { return (string) $n; }
	function wp_nonce_field( $a ) { echo ''; }
	function wp_nonce_url( $u, $a ) { return $u . '&_wpnonce=x'; }
	function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
	function add_query_arg( $k, $v = null, $u = null ) { return $u; }
	function check_admin_referer( $a ) { return true; }
	function wp_safe_redirect( $u ) { return true; }
	function submit_button( $t, $c, $n, $w ) { echo ''; }
	function doing_action( $h ) { return false; }
	function wp_remote_post( $url, $args ) {
		$GLOBALS['dci_http_log'][] = array( 'url' => $url, 'args' => $args );
		if ( empty( $GLOBALS['dci_http_queue'] ) ) { return array( 'response' => array( 'code' => 500 ), 'body' => '' ); }
		$next = array_shift( $GLOBALS['dci_http_queue'] );
		return array( 'response' => array( 'code' => $next['code'] ), 'body' => json_encode( $next['body'] ) );
	}
	function wp_remote_retrieve_response_code( $r ) { return isset( $r['response']['code'] ) ? $r['response']['code'] : 0; }
	function wp_remote_retrieve_body( $r ) { return isset( $r['body'] ) ? $r['body'] : ''; }
	function wp_register_ability( $name, $args ) { $GLOBALS['dci_abilities'][ $name ] = $args; return true; }
	function wp_register_ability_category( $slug, $args ) { $GLOBALS['dci_category'] = array( $slug, $args ); return true; }
	function wp_has_ability( $n ) { return isset( $GLOBALS['dci_abilities'][ $n ] ); }
	function get_plugins( $folder = '' ) { return isset( $GLOBALS['dci_plugins'] ) ? $GLOBALS['dci_plugins'] : array(); }
	function is_plugin_active( $file ) { return isset( $GLOBALS['dci_plugins'][ $file ] ); }
	function get_posts( $args = array() ) { return isset( $GLOBALS['dci_posts_list'] ) ? $GLOBALS['dci_posts_list'] : array(); }
	function url_to_postid( $url ) { return isset( $GLOBALS['dci_url_map'][ $url ] ) ? $GLOBALS['dci_url_map'][ $url ] : 0; }
	function wp_trim_words( $text, $num = 55, $more = '…' ) { $words = preg_split( '/\s+/u', trim( (string) $text ) ); if ( count( $words ) <= $num ) { return implode( ' ', $words ); } return implode( ' ', array_slice( $words, 0, $num ) ) . $more; }
	function esc_url_raw( $url ) { return $url; }
	function get_bloginfo( $k = '' ) { return 'Situs Uji'; }
	function wp_specialchars_decode( $text, $quotes = '' ) { return htmlspecialchars_decode( (string) $text, $quotes ? $quotes : ENT_QUOTES ); }
	function wp_revisions_enabled( $post ) { return true; }
	function wp_save_post_revision( $post_id ) { $GLOBALS['dci_revisions'][] = $post_id; return (int) $post_id; }
	function plugin_basename( $file ) { return 'dci-mcp-bridge/dci-mcp-bridge.php'; }
	function apply_filters( $tag, $value ) { return $value; }
	function get_transient( $k ) { return isset( $GLOBALS['dci_transients'][ $k ] ) ? $GLOBALS['dci_transients'][ $k ] : false; }
	function set_transient( $k, $v, $exp = 0 ) { $GLOBALS['dci_transients'][ $k ] = $v; return true; }
	function delete_transient( $k ) { unset( $GLOBALS['dci_transients'][ $k ] ); return true; }
	function get_site_transient( $k ) { return isset( $GLOBALS['dci_transients'][ 'site_' . $k ] ) ? $GLOBALS['dci_transients'][ 'site_' . $k ] : false; }
	function wp_get_theme() { return new class { public function get( $h ) { return 'Name' === $h ? 'Tema Uji' : '1.2.3'; } }; }
	function wp_count_posts( $type = 'post' ) { return (object) array( 'publish' => 7, 'inherit' => 12 ); }
	function wp_get_attachment_url( $id ) { return 'https://example.test/wp-content/uploads/img' . $id . '.jpg'; }
	function get_locale() { return 'id_ID'; }
	function wp_next_scheduled( $hook ) { return 1700000000; }
	function wp_slash( $v ) { return $v; }
	$GLOBALS['dci_transients'] = array();
	$GLOBALS['dci_get_log']    = array();
	function wp_remote_get( $url, $args = array() ) {
		$GLOBALS['dci_get_log'][] = array( 'url' => $url, 'args' => $args );
		if ( empty( $GLOBALS['dci_get_queue'] ) ) { return array( 'response' => array( 'code' => 404 ), 'body' => '' ); }
		$next = array_shift( $GLOBALS['dci_get_queue'] );
		return array( 'response' => array( 'code' => $next['code'] ), 'body' => json_encode( $next['body'] ) );
	}
	$GLOBALS['dci_get_queue'] = array();

	/* ---------- muat plugin (path relatif repo) ---------- */
	require __DIR__ . '/../dci-mcp-bridge.php';

	$PASS = 0; $FAIL = 0;
	function check( $label, $cond ) {
		global $PASS, $FAIL;
		if ( $cond ) { $PASS++; echo "PASS  $label\n"; }
		else { $FAIL++; echo "FAIL  $label\n"; }
	}

	/* T1 — plugin termuat tanpa fatal */
	check( 'T1 plugin termuat', defined( 'DCI_MCP_BRIDGE_VERSION' ) );

	/* T2 — hooks terpasang di nama yang benar */
	check( 'T2a hook ability', isset( $GLOBALS['dci_actions']['wp_abilities_api_init'] ) );
	check( 'T2b hook kategori', isset( $GLOBALS['dci_actions']['wp_abilities_api_categories_init'] ) );
	check( 'T2c hook admin menu', isset( $GLOBALS['dci_actions']['admin_menu'] ) );
	check( 'T2d hook simpan kunci', isset( $GLOBALS['dci_actions']['admin_post_dci_bridge_save_key'] ) );
	check( 'T2e filter hardening', isset( $GLOBALS['dci_filters']['mcp_adapter_default_transport_permission_user_capability'] ) );
	check( 'T2f filter identitas server', isset( $GLOBALS['dci_filters']['mcp_adapter_default_server_config'] ) );

	/* T3 — registrasi seluruh ability valid */
	dci_mcp_bridge_register_category();
	dci_mcp_bridge_register_abilities();
	$names = array_keys( $GLOBALS['dci_abilities'] );
	$all_valid = true;
	foreach ( $GLOBALS['dci_abilities'] as $name => $args ) {
		if ( ! preg_match( '/^[a-z0-9-]+\/[a-z0-9-]+$/', $name ) ) { $all_valid = false; }
		if ( empty( $args['execute_callback'] ) || ! function_exists( $args['execute_callback'] ) ) { $all_valid = false; }
		if ( empty( $args['permission_callback'] ) || ! function_exists( $args['permission_callback'] ) ) { $all_valid = false; }
		if ( true !== $args['meta']['mcp']['public'] ) { $all_valid = false; }
	}
	check( 'T3b nama/callback/meta valid semua', $all_valid );
	check( 'T3c kategori terdaftar', 'dci-content' === $GLOBALS['dci_category'][0] );

	/* T19 — Tier 1 operasional (v2.1.0) */
	$GLOBALS['dci_transients']['site_update_plugins'] = (object) array( 'response' => array(
		'seo-by-rank-math/rank-math.php' => (object) array( 'new_version' => '9.9.9' ),
	) );
	$rep = dci_mcp_bridge_execute_site_report();
	check( 'T19a site-report: versi + tema + cron + hitungan', '1.2.3' === $rep['theme']['version'] && true === $rep['cron']['version_check_scheduled'] && 7 === $rep['counts']['post'] );
	check( 'T19b site-report: pembaruan plugin dari transient', 1 === $rep['plugins']['pending_updates'] && '9.9.9' === $rep['plugins']['updates'][0]['new_version'] );

	$GLOBALS['dci_posts_list'] = array(
		(object) array( 'ID' => 201, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Artikel Sehat', 'post_date' => '2026-02-01', 'post_excerpt' => '', 'post_content' => '<p>' . str_repeat( 'kontainer kantor berkualitas tinggi untuk proyek anda ', 40 ) . '</p>' ),
		(object) array( 'ID' => 202, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Artikel Rusak', 'post_date' => '2026-01-01', 'post_excerpt' => '', 'post_content' => '<h1>H1</h1><img src="x.jpg">' ),
	);
	$GLOBALS['dci_meta'] = array(
		201 => array(), 202 => array(),
	);
	$bulk = dci_mcp_bridge_execute_bulk_audit( array( 'count' => 2 ) );
	check( 'T19c bulk-audit: 2 artikel terproses', 2 === $bulk['total'] );
	check( 'T19d bulk-audit: urut terburuk-dulu', $bulk['items'][0]['post_id'] === 202 && $bulk['items'][0]['fail'] >= 1 );

	$GLOBALS['dci_posts_list'] = array(
		(object) array( 'ID' => 301, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'Foto Kontainer', 'post_date' => '2026-03-01', 'post_excerpt' => '', 'post_content' => '', 'post_parent' => 0, 'post_mime_type' => 'image/jpeg' ),
	);
	$GLOBALS['dci_meta'] = array( 301 => array() );
	$lm = dci_mcp_bridge_execute_list_media( array() );
	check( 'T19e list-media: item + alt kosong terbaca', 1 === count( $lm['items'] ) && '' === $lm['items'][0]['alt'] && false !== strpos( $lm['items'][0]['url'], 'img301' ) );

	$alt = dci_mcp_bridge_execute_set_media_alt( array( 'attachment_id' => 301, 'alt' => 'Kontainer kantor modifikasi dua lantai' ) );
	check( 'T19f set-media-alt tersimpan', ! is_wp_error( $alt ) && 'Kontainer kantor modifikasi dua lantai' === $GLOBALS['dci_meta'][301]['_wp_attachment_image_alt'] );

	$GLOBALS['dci_posts_list'][] = (object) array( 'ID' => 210, 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Draf Uji', 'post_date' => '2026-04-01', 'post_excerpt' => '', 'post_content' => '<p>isi</p>' );
	$fi = dci_mcp_bridge_execute_set_featured_image( array( 'post_id' => 210, 'attachment_id' => 301 ) );
	check( 'T19g set-featured-image draf sukses', ! is_wp_error( $fi ) && 301 === (int) $GLOBALS['dci_meta'][210]['_thumbnail_id'] );
	$GLOBALS['dci_posts_list'][] = (object) array( 'ID' => 211, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Terbit Uji', 'post_date' => '2026-04-02', 'post_excerpt' => '', 'post_content' => '<p>isi</p>' );
	$fi2 = dci_mcp_bridge_execute_set_featured_image( array( 'post_id' => 211, 'attachment_id' => 301 ) );
	check( 'T19h artikel terbit tanpa flag → ditolak', is_wp_error( $fi2 ) );

	/* T20 — halaman + Elementor (v2.2.0) */
	$GLOBALS['dci_posts_list'] = array(
		(object) array( 'ID' => 401, 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Halaman Draf', 'post_date' => '2026-05-01', 'post_excerpt' => '', 'post_content' => '<p>halaman draf</p>' ),
		(object) array( 'ID' => 402, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Halaman Terbit', 'post_date' => '2026-05-02', 'post_excerpt' => '', 'post_content' => '<p>halaman terbit</p>' ),
		(object) array( 'ID' => 403, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Landing Elementor', 'post_date' => '2026-05-03', 'post_excerpt' => '', 'post_content' => '<div>render</div>' ),
	);
	$GLOBALS['dci_meta'] = array(
		403 => array(
			'_elementor_edit_mode' => 'builder',
			'_elementor_data' => json_encode( array( array( 'id' => 'a1', 'elType' => 'section', 'elements' => array(
				array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Judul Lama Kontainer' ) ),
				array( 'id' => 'w2', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>Deskripsi Lama di sini</p>' ) ),
			) ) ) ),
		),
	);

	$up_page = dci_mcp_bridge_execute_update_draft_post( array( 'post_id' => 401, 'title' => 'Halaman Draf Baru' ) );
	check( 'T20a draf PAGE kini bisa diedit', ! is_wp_error( $up_page ) && array( 'title' ) === $up_page['updated'] );
	$pub_page = dci_mcp_bridge_execute_update_published_post( array( 'post_id' => 402, 'excerpt' => 'Ringkasan halaman' ) );
	check( 'T20b halaman terbit bisa diperbaiki', ! is_wp_error( $pub_page ) && array( 'excerpt' ) === $pub_page['updated'] );

	$GLOBALS['dci_post'] = $GLOBALS['dci_posts_list'][2];
	$gc_el = dci_mcp_bridge_execute_get_content( array( 'post_id' => 403 ) );
	check( 'T20c get-content deteksi Elementor + teks widget terbaca', true === $gc_el['elementor']['is_builder'] && 2 === count( $gc_el['elementor']['texts'] ) && false !== strpos( implode( ' ', $gc_el['elementor']['texts'] ), 'Judul Lama' ) );

	$el_no = dci_mcp_bridge_execute_update_elementor_text( array( 'post_id' => 403, 'find' => 'Lama', 'replace' => 'Baru', 'allow_published' => true ) );
	$stored = json_decode( $GLOBALS['dci_meta'][403]['_elementor_data'], true );
	check( 'T20d update-elementor-text mengganti di sumber', ! is_wp_error( $el_no ) && 2 === $el_no['replacements'] && false !== strpos( $stored[0]['elements'][0]['settings']['title'], 'Baru' ) );

	$el_miss = dci_mcp_bridge_execute_update_elementor_text( array( 'post_id' => 403, 'find' => 'TIDAKADA', 'replace' => 'x', 'allow_published' => true ) );
	check( 'T20e teks tak ditemukan → error jelas', is_wp_error( $el_miss ) && 'dci_text_not_found' === $el_miss->get_error_code() );
	$el_plain = dci_mcp_bridge_execute_update_elementor_text( array( 'post_id' => 402, 'find' => 'a', 'replace' => 'b', 'allow_published' => true ) );
	check( 'T20f non-Elementor → diarahkan ke jalur post_content', is_wp_error( $el_plain ) && 'dci_not_elementor' === $el_plain->get_error_code() );

	/* T21 — meta Rank Math pada PAGE terbit (v2.2.1, regresi) */
	$GLOBALS['dci_meta'][402] = array();
	$meta_page = dci_mcp_bridge_execute_set_post_seo_meta( array( 'post_id' => 402, 'meta_title' => 'SEO Halaman Terbit', 'focus_keyword' => 'kontainer kantor', 'allow_published' => true ) );
	check( 'T21a meta Rank Math page terbit via allow_published', ! is_wp_error( $meta_page ) && in_array( 'rank_math_title', $meta_page['updated'], true ) && in_array( 'rank_math_focus_keyword', $meta_page['updated'], true ) );
	$meta_page_no = dci_mcp_bridge_execute_set_post_seo_meta( array( 'post_id' => 402, 'meta_title' => 'Tanpa Flag' ) );
	check( 'T21b page terbit tanpa allow_published → tetap ditolak', is_wp_error( $meta_page_no ) );

	/* T17 — konsistensi UI ↔ registry (anti lupa baris tabel admin) */
	$table_rows = dci_mcp_bridge_ability_table();
	$table_names = array();
	foreach ( $table_rows as $row ) { $table_names[] = $row['name']; }
	check( 'T17a tabel admin memuat semua ability registry', 0 === count( array_diff( $names, $table_names ) ) && 0 === count( array_diff( $table_names, $names ) ) );
	check( 'T17b jumlah tabel = jumlah registry', count( $table_names ) === count( $names ) );

	/* T4 — parsing multi-kunci + migrasi legacy */
	$GLOBALS['dci_store']['dci_mcp_bridge_options'] = array( 'winston_api_key' => 'LEGACYKEY' );
	check( 'T4a migrasi kunci tunggal', array( 'LEGACYKEY' ) === dci_mcp_bridge_winston_keys() );
	$GLOBALS['dci_store']['dci_mcp_bridge_options'] = array( 'winston_api_keys' => array( '', 'AAA', 'AAA', ' BBB ', 'CCC' ) );
	check( 'T4b duplikat/kosong dibersihkan', array( 'AAA', 'BBB', 'CCC' ) === dci_mcp_bridge_winston_keys() );

	/* T5 — rotasi + failover 402 */
	$GLOBALS['dci_store'] = array(
		'dci_mcp_bridge_options' => array( 'winston_api_keys' => array( 'K1AAAA1111', 'K2BBBB2222' ) ),
	);
	$GLOBALS['dci_http_queue'] = array(
		array( 'code' => 402, 'body' => array( 'message' => 'no credits' ) ),
		array( 'code' => 200, 'body' => array( 'result' => array( 'score' => 5 ) ) ),
	);
	$resp = dci_mcp_bridge_winston_request_rotated( 'v2/plagiarism', array( 'text' => 'x' ), 5 );
	check( 'T5a failover 402 lalu sukses', ! is_wp_error( $resp ) && 5 === $resp['result']['score'] );
	$auths = array();
	foreach ( $GLOBALS['dci_http_log'] as $log ) { $auths[] = $log['args']['headers']['Authorization']; }
	check( 'T5b kunci dicoba berurutan', array( 'Bearer K1AAAA1111', 'Bearer K2BBBB2222' ) === $auths );
	$cool = get_option( 'dci_mcp_bridge_key_cooldown' );
	check( 'T5c kunci gagal kena cooldown', 1 === count( $cool ) && isset( $cool[ md5( 'K1AAAA1111' ) ] ) );
	check( 'T5d indeks rotasi maju', 0 === (int) get_option( 'dci_mcp_bridge_rotation_index' ) );

	/* T6 — semua kunci cooldown → error jelas */
	$GLOBALS['dci_store']['dci_mcp_bridge_key_cooldown'] = array(
		md5( 'K1AAAA1111' ) => time(),
		md5( 'K2BBBB2222' ) => time(),
	);
	$GLOBALS['dci_http_queue'] = array();
	$err = dci_mcp_bridge_winston_request_rotated( 'v2/plagiarism', array( 'text' => 'x' ), 5 );
	check( 'T6a error saat semua cooldown', is_wp_error( $err ) && 'dci_winston_all_keys_failed' === $err->get_error_code() );
	check( 'T6b tidak ada HTTP terbuang', 2 === count( $GLOBALS['dci_http_log'] ) );

	/* T7 — check-originality end-to-end (mock Winston) */
	$GLOBALS['dci_store'] = array(
		'dci_mcp_bridge_options' => array( 'winston_api_keys' => array( 'KW' ) ),
	);
	$GLOBALS['dci_http_queue'] = array(
		array( 'code' => 200, 'body' => array(
			'score' => 55, 'readability_score' => 62,
			'sentences' => array( array( 'text' => 'frasa yang sangat dicurigai bot di sini', 'score' => 8 ) ),
			'credits_used' => 600, 'credits_remaining' => 1400,
		) ),
		array( 'code' => 200, 'body' => array(
			'result' => array( 'score' => 12, 'textWordCounts' => 700 ),
			'sources' => array( array( 'url' => 'https://sumber.test/a', 'title' => 'Sumber A', 'score' => 80 ) ),
			'credits_used' => 1200, 'credits_remaining' => 200,
		) ),
	);
	$GLOBALS['dci_post'] = (object) array(
		'ID' => 42, 'post_type' => 'post', 'post_status' => 'draft',
		'post_title' => 'Uji Coba Kontainer',
		'post_content' => '<h2>Bagian</h2><p>' . str_repeat( 'kalimat penting tentang kontainer kantor ', 30 ) . '</p>',
		'post_excerpt' => 'Ringkasan',
	);
	$out = dci_mcp_bridge_execute_check_originality( array( 'post_id' => 42 ) );
	check( 'T7a sukses tanpa error', ! is_wp_error( $out ) );
	check( 'T7b skor manusia terbaca', 55 === $out['ai_detect']['human_score'] );
	check( 'T7c verdict zona abu-abu', false !== strpos( $out['ai_detect']['verdict'], 'abu-abu' ) );
	check( 'T7d kalimat ter-flag', 1 === count( $out['ai_detect']['sentences_flagged'] ) && false !== strpos( $out['ai_detect']['sentences_flagged'][0]['text'], 'dicurigai' ) );
	check( 'T7e plagiarisme 12%', 12 === $out['plagiarism']['score_percent'] && 'https://sumber.test/a' === $out['plagiarism']['top_sources'][0]['url'] );
	check( 'T7f kredit terakumulasi', 1800 === $out['credits']['used'] && 200 === $out['credits']['remaining'] );

	/* T8 — audit artikel menampilkan cek baru */
	$GLOBALS['dci_meta'] = array(
		'rank_math_title' => str_repeat( 'a', 48 ),
		'rank_math_description' => str_repeat( 'd', 140 ),
		'rank_math_focus_keyword' => 'kontainer',
		'rank_math_seo_score' => '80',
	);
	$GLOBALS['dci_post']->post_content = '<h1>H1 Ilegal</h1><h2>Judul</h2><p>Kontainer adalah solusi di era digital ini untuk kantor. ' . str_repeat( 'kontainer kantor modular sangat berguna sekali bagi perusahaan yang tumbuh. ', 25 ) . '</p><a href="/internal">klik di sini</a><a href="https://eksternal.test/sumber">sumber rujukan resmi</a><img src="x.jpg">';
	$audit = dci_mcp_bridge_execute_audit_article( array( 'post_id' => 42 ) );
	$ids = array();
	foreach ( $audit['checks'] as $c ) { $ids[ $c['id'] ] = $c['status']; }
	check( 'T8a h1 ganda terdeteksi FAIL', 'FAIL' === $ids['h1'] );
	check( 'T8b frasa AI terdeteksi', 'WARN' === $ids['frasa_ai'] || 'FAIL' === $ids['frasa_ai'] );
	check( 'T8c anchor generik terdeteksi', 'FAIL' === $ids['anchor_text'] );
	check( 'T8d gambar tanpa alt terdeteksi', 'FAIL' === $ids['gambar'] );
	check( 'T8e skor Rank Math terbaca', 80 === $audit['rank_math']['score'] );
	$sum = array_sum( array( $audit['summary']['pass'], $audit['summary']['warn'], $audit['summary']['fail'] ) );
	check( 'T8f ringkasan konsisten', $sum === count( $audit['checks'] ) );

	/* T9 — publish draf */
	$GLOBALS['dci_post']->post_status = 'draft';
	$pub = dci_mcp_bridge_execute_publish_post( array( 'post_id' => 42 ) );
	check( 'T9 publish sukses + link', ! is_wp_error( $pub ) && 'publish' === $pub['status'] && '' !== $pub['link'] );

	/* T10 — publish menolak artikel terbit */
	$GLOBALS['dci_post']->post_status = 'publish';
	$rej = dci_mcp_bridge_execute_publish_post( array( 'post_id' => 42 ) );
	check( 'T10 artikel terbit ditolak', is_wp_error( $rej ) );

	/* T11 — jalur internal AI Puffer (regresi bug namespace v1.5.0) */
	$GLOBALS['dci_store'] = array(
		'dci_mcp_bridge_options' => array(),
	);
	$aip = dci_mcp_bridge_aip_status();
	check( 'T11a kelas WPAICG terdeteksi', true === $aip['installed'] );
	$http_before = count( $GLOBALS['dci_http_log'] );
	$gen = dci_mcp_bridge_execute_generate_text( array( 'prompt' => 'coba', 'provider' => 'openai' ) );
	check( 'T11b generate lewat jalur internal (tanpa HTTP)', ! is_wp_error( $gen ) && 'INTERNAL-OK' === $gen['content'] && count( $GLOBALS['dci_http_log'] ) === $http_before );
	$bad = dci_mcp_bridge_execute_generate_text( array( 'prompt' => 'coba', 'provider' => 'TidakAda' ) );
	check( 'T11c provider tak dikenal ditolak + daftar valid', is_wp_error( $bad ) && 'dci_invalid_provider' === $bad->get_error_code() && false !== strpos( $bad->get_error_message(), 'OpenAI' ) );
	$cfg = dci_mcp_bridge_execute_get_seo_config( array() );
	check( 'T11d get-seo-config: aip.installed true', true === $cfg['aip']['installed'] );

	/* T12 — deteksi kanonik v1.6.1 (regresi bug cabang api_keys vs providers) */
	$GLOBALS['dci_plugins'] = array(
		'gpt3-ai-content-generator/gpt3-ai-content-generator.php' => array( 'Version' => '2.4.95' ),
	);
	$GLOBALS['dci_store'] = array(
		'dci_mcp_bridge_options' => array(),
		'aipkit_options' => array(
			'providers' => array(
				'OpenAI' => array( 'api_key' => 'sk-xxx', 'model' => 'gpt-4o-mini' ),
				'Google' => array( 'api_key' => '', 'model' => '' ),
			),
			'api_keys' => array( 'public_api_enabled' => '0', 'public_api_key' => '' ),
		),
	);
	$aip12 = dci_mcp_bridge_aip_status();
	check( 'T12a terpasang via is_plugin_active + versi', true === $aip12['installed'] && '2.4.95' === $aip12['version'] );
	check( 'T12b provider terbaca dari cabang providers', array( 'OpenAI' ) === $aip12['providers_configured'] );
	$GLOBALS['dci_plugins'] = array();
	$aip12b = dci_mcp_bridge_aip_status();
	check( 'T12c tanpa plugin: fallback kelas internal', true === $aip12b['installed'] && null === $aip12b['version'] );
	check( 'T12d nama server domain+wordpress', 'example-wordpress' === dci_mcp_bridge_server_name() );
	define( 'DCI_MCP_SERVER_NAME', 'kustom-tes' );
	check( 'T12e override konstanta nama server', 'kustom-tes' === dci_mcp_bridge_server_name() );

	/* T13 — kemampuan baca konten (v1.8.0) */
	$GLOBALS['dci_posts_list'] = array(
		(object) array( 'ID' => 101, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Kontak Uji', 'post_date' => '2026-01-02', 'post_excerpt' => '', 'post_content' => '<p>Hubungi 085272943966 atau email halo untuk penawaran kontainer kantor.</p>' ),
		(object) array( 'ID' => 102, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Tentang Kami', 'post_date' => '2025-11-01', 'post_excerpt' => 'Profil perusahaan', 'post_content' => '<p>Profil singkat perusahaan.</p>' ),
	);
	$GLOBALS['dci_url_map'] = array( 'https://example.test/kontak' => 101 );
	$GLOBALS['dci_post']    = $GLOBALS['dci_posts_list'][0];

	$sr = dci_mcp_bridge_execute_search_content( array( 'query' => '' ) );
	check( 'T13a search-content mengembalikan item', 2 === count( $sr['items'] ) && 101 === $sr['items'][0]['id'] && 'post' === $sr['items'][0]['type'] );
	check( 'T13b excerpt bebas HTML & terpotong', false === strpos( $sr['items'][0]['excerpt'], '<p>' ) );

	$gc = dci_mcp_bridge_execute_get_content( array( 'post_id' => 101 ) );
	check( 'T13c get-content teks penuh terbaca', false !== strpos( $gc['content_text'], '085272943966' ) && '<p>' !== substr( $gc['content_text'], 0, 3 ) );
	check( 'T16a get-content lossless (content_html = mentah tersimpan)', false !== strpos( $gc['content_html'], '<p>' ) && $gc['content_html'] === $GLOBALS['dci_post']->post_content );

	$gc2 = dci_mcp_bridge_execute_get_content( array( 'url' => 'https://example.test/kontak' ) );
	check( 'T13d get-content via URL situs', 101 === $gc2['post_id'] );

	$gc3 = dci_mcp_bridge_execute_get_content( array() );
	check( 'T13e tanpa id/url → error jelas', is_wp_error( $gc3 ) );

	/* T14 — filter identitas server menyuntik domain */
	$cfg_srv = dci_mcp_bridge_server_identity( array() );
	check( 'T14a nama server = situs + WordPress MCP', false !== strpos( $cfg_srv['server_name'], 'Situs Uji' ) && false !== strpos( $cfg_srv['server_name'], 'WordPress MCP' ) );
	check( 'T14b instruksi memuat domain resmi + larangan substitusi', false !== strpos( $cfg_srv['server_description'], 'example.test' ) && false !== strpos( $cfg_srv['server_description'], 'NEVER substitute' ) );

	/* T15 — update-published-post (v1.9.0) */
	$GLOBALS['dci_post']->post_status = 'publish';
	$GLOBALS['dci_revisions'] = array();
	$up = dci_mcp_bridge_execute_update_published_post( array( 'post_id' => 101, 'title' => 'Judul Baru Terbit' ) );
	check( 'T15a update terbit sukses + snapshot revisi', ! is_wp_error( $up ) && array( 'title' ) === $up['updated'] && true === $up['revision_saved'] && array( 101 ) === $GLOBALS['dci_revisions'] );
	$GLOBALS['dci_post']->post_status = 'draft';
	$up2 = dci_mcp_bridge_execute_update_published_post( array( 'post_id' => 101, 'title' => 'X' ) );
	check( 'T15b draf ditolak + diarahkan ke update-draft-post', is_wp_error( $up2 ) && false !== strpos( $up2->get_error_message(), 'dci/update-draft-post' ) );
	$GLOBALS['dci_post']->post_status = 'publish';
	$GLOBALS['dci_meta']['rank_math_title'] = '';
	$meta_pub = dci_mcp_bridge_execute_set_post_seo_meta( array( 'post_id' => 101, 'meta_title' => 'SEO Terbit', 'allow_published' => true ) );
	check( 'T15c meta artikel terbit via allow_published', ! is_wp_error( $meta_pub ) && in_array( 'rank_math_title', $meta_pub['updated'], true ) );
	$meta_rej = dci_mcp_bridge_execute_set_post_seo_meta( array( 'post_id' => 101, 'meta_title' => 'Tanpa Flag' ) );
	check( 'T15d tanpa allow_published → ditolak', is_wp_error( $meta_rej ) );

	/* T18 — updater GitHub (v2.0.0) */
	check( 'T18a host GitHub valid / host asing ditolak', true === dci_mcp_bridge_is_github_url( 'https://github.com/x/y.zip' ) && true === dci_mcp_bridge_is_github_url( 'https://objects.githubusercontent.com/asset.zip' ) && false === dci_mcp_bridge_is_github_url( 'https://evil.com/github.com/a.zip' ) && false === dci_mcp_bridge_is_github_url( 'http://github.com/a.zip' ) );

	$GLOBALS['dci_get_queue'] = array(
		array( 'code' => 200, 'body' => array(
			'tag_name' => 'v99.0.0',
			'html_url' => 'https://github.com/alecslacker/dci-wp-ai-bridge/releases/tag/v99.0.0',
			'assets' => array( array( 'name' => 'dci-mcp-bridge.zip', 'browser_download_url' => 'https://github.com/alecslacker/dci-wp-ai-bridge/releases/download/v99.0.0/dci-mcp-bridge.zip' ) ),
		) ),
	);
	$GLOBALS['dci_transients'] = array();
	$rel = dci_mcp_bridge_fetch_latest_release();
	check( 'T18b rilis terbaca (tag v → 99.0.0, aset tepat)', null !== $rel && '99.0.0' === $rel['version'] && false !== strpos( $rel['zip'], 'dci-mcp-bridge.zip' ) );

	$tr = new stdClass();
	$tr->response = array();
	$tr2 = dci_mcp_bridge_inject_update( $tr );
	$entry = $tr2->response['dci-mcp-bridge/dci-mcp-bridge.php'] ?? null;
	check( 'T18c pembaruan tersuntik ke transien WP', null !== $entry && '99.0.0' === $entry->new_version && false !== strpos( $entry->package, 'github.com' ) );

	$GLOBALS['dci_get_queue'] = array(
		array( 'code' => 200, 'body' => array( 'tag_name' => 'v0.1.0', 'html_url' => 'x', 'assets' => array( array( 'name' => 'dci-mcp-bridge.zip', 'browser_download_url' => 'https://github.com/a/b.zip' ) ) ) ),
	);
	$GLOBALS['dci_transients'] = array();
	$tr3 = dci_mcp_bridge_inject_update( new stdClass() );
	check( 'T18d versi lama/lokal lebih baru → tak tersuntik', empty( $tr3->response ) );

	$GLOBALS['dci_get_queue'] = array(
		array( 'code' => 200, 'body' => array( 'tag_name' => 'v99.0.0', 'html_url' => 'x', 'assets' => array( array( 'name' => 'dci-mcp-bridge.zip', 'browser_download_url' => 'https://evil.example.com/bad.zip' ) ) ) ),
	);
	$GLOBALS['dci_transients'] = array();
	check( 'T18e aset dari host asing → ditolak senyap', null === dci_mcp_bridge_fetch_latest_release() );

	$GLOBALS['dci_get_log']    = array();
	$GLOBALS['dci_get_queue']  = array( array( 'code' => 404, 'body' => array() ) );
	$GLOBALS['dci_transients'] = array();
	$GLOBALS['dci_store']      = array( 'dci_mcp_bridge_options' => array( 'github_token' => 'ghp_test123' ) );
	dci_mcp_bridge_fetch_latest_release();
	$used_header = isset( $GLOBALS['dci_get_log'][0]['args']['headers']['Authorization'] ) ? $GLOBALS['dci_get_log'][0]['args']['headers']['Authorization'] : '';
	check( 'T18f token privat terkirim sebagai Bearer', 'Bearer ghp_test123' === $used_header );

	echo "\n=== HASIL: $PASS PASS, $FAIL FAIL ===\n";
	if ( ! defined( 'DCI_HARNESS_NO_EXIT' ) ) {
		exit( $FAIL > 0 ? 1 : 0 );
	}
}
