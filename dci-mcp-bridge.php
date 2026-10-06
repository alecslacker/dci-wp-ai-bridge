<?php
/**
 * Plugin Name:       DCI MCP Bridge
 * Description:       Hardening gerbang MCP Adapter + mengekspos kemampuan konten (AI Puffer) sebagai Abilities agar dapat dipakai AI agent. Bagian dari standar operasional Duta Corpora Indonesia.
 * Version:           1.5.0
 * Author:            Mas Wondho - Duta Corpora Indonesia
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Requires Plugins:  mcp-adapter
 * Text Domain:       dci-mcp-bridge
 *
 * @package DCI_MCP_Bridge
 */

// Cegah akses langsung ke file ini.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DCI_MCP_BRIDGE_VERSION', '1.5.0' );

/* ============================================================
 * BAGIAN 1 — HARDENING GERBANG MCP (TRANSPORT HTTP)
 *
 * Filter resmi MCP Adapter: kemampuan minimum user untuk masuk
 * gerbang HTTP default server. Default pabriknya 'read' (terlalu
 * longgar: subscriber pun lolos). Kita naikkan ke 'edit_posts'
 * sehingga hanya Editor/Administrator yang bisa menyambung.
 *
 * Bisa dioverride per-site dari wp-config.php:
 *   define( 'DCI_MCP_MIN_CAPABILITY', 'manage_options' );
 * ============================================================ */
add_filter( 'mcp_adapter_default_transport_permission_user_capability', 'dci_mcp_bridge_transport_capability', 10, 2 );

/**
 * Naikkan kemampuan minimum gerbang MCP HTTP.
 *
 * @param string $capability Kemampuan default dari MCP Adapter ('read').
 * @param mixed  $context    Konteks transport (tidak dipakai).
 * @return string Kemampuan minimum yang dipakai.
 */
function dci_mcp_bridge_transport_capability( $capability, $context = null ) {
	unset( $capability, $context );

	if ( defined( 'DCI_MCP_MIN_CAPABILITY' ) && is_string( DCI_MCP_MIN_CAPABILITY ) && '' !== DCI_MCP_MIN_CAPABILITY ) {
		return DCI_MCP_MIN_CAPABILITY;
	}

	return 'edit_posts';
}

/* ============================================================
 * BAGIAN 2 — REGISTRASI KEMAMPUAN (ABILITIES API)
 *
 * WAJIB: kategori didaftarkan di hook 'wp_abilities_api_categories_init'
 * dan ability di hook 'wp_abilities_api_init'. Salah hook = ditolak core.
 * Nama ability hanya boleh huruf kecil, angka, dan tanda hubung
 * (pola: 'namespace/nama-ability', TANPA underscore).
 * ============================================================ */
add_action( 'wp_abilities_api_categories_init', 'dci_mcp_bridge_register_category' );

/**
 * Daftarkan kategori kemampuan milik DCI.
 */
function dci_mcp_bridge_register_category() {
	if ( ! function_exists( 'wp_register_ability_category' ) ) {
		return; // WordPress di bawah 6.9 — jangan fatal, cukup berhenti.
	}

	wp_register_ability_category(
		'dci-content',
		array(
			'label'       => __( 'DCI Content', 'dci-mcp-bridge' ),
			'description' => __( 'Kemampuan produksi konten Duta Corpora: generasi teks via AI Puffer dan pembuatan draf artikel.', 'dci-mcp-bridge' ),
		)
	);
}

add_action( 'wp_abilities_api_init', 'dci_mcp_bridge_register_abilities' );

/**
 * Daftarkan semua ability DCI ke Abilities API.
 * Ability dengan meta 'mcp.public = true' otomatis tampil di MCP Adapter.
 */
function dci_mcp_bridge_register_abilities() {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return; // WordPress di bawah 6.9 — jangan fatal.
	}

	/* --------------------------------------------------------
	 * Ability 1: dci/generate-text
	 * Menghasilkan teks via mesin AI Puffer yang terpasang di situs.
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/generate-text',
		array(
			'label'       => __( 'Generate Text (AI Puffer)', 'dci-mcp-bridge' ),
			'description' => __( 'Generate text using the AI Puffer engine installed on this site. Use it to draft article bodies, headlines, meta descriptions, or any writing task. Provider labels: OpenAI, Google, Claude, OpenRouter, Azure, Ollama, DeepSeek, xAI, AIPufferCloud. Returns the generated text as "content".', 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'prompt'             => array(
						'type'        => 'string',
						'description' => __( 'The writing instruction or question for the AI.', 'dci-mcp-bridge' ),
					),
					'system_instruction' => array(
						'type'        => 'string',
						'description' => __( 'Optional. Writing style / rules, e.g. tone, language, structure.', 'dci-mcp-bridge' ),
					),
					'provider'           => array(
						'type'        => 'string',
						'default'     => 'OpenAI',
						'description' => __( 'Optional. AI Puffer provider label.', 'dci-mcp-bridge' ),
					),
					'model'              => array(
						'type'        => 'string',
						'default'     => 'gpt-4o-mini',
						'description' => __( 'Optional. Model name for the chosen provider.', 'dci-mcp-bridge' ),
					),
					'max_tokens'         => array(
						'type'        => 'integer',
						'default'     => 1500,
						'description' => __( 'Optional. Maximum output tokens.', 'dci-mcp-bridge' ),
					),
					'temperature'        => array(
						'type'        => 'number',
						'default'     => 0.7,
						'description' => __( 'Optional. Creativity: 0.0 (strict) to 1.0 (creative).', 'dci-mcp-bridge' ),
					),
				),
				'required'   => array( 'prompt' ),
			),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array(
					'content'  => array( 'type' => 'string', 'description' => __( 'The generated text.', 'dci-mcp-bridge' ) ),
					'provider' => array( 'type' => 'string' ),
					'model'    => array( 'type' => 'string' ),
				),
				'required'   => array( 'content' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_generate_text',
			'permission_callback' => 'dci_mcp_bridge_permission_edit_posts',
			'meta'                => array(
				'annotations' => array(
					'readonly'   => true,   // Tidak mengubah data situs (kredit AI tetap terpakai).
					'destructive' => false,
					'idempotent' => false,
				),
				'public'      => true,
				'mcp'         => array( 'public' => true ),
			),
		)
	);

	/* --------------------------------------------------------
	 * Ability 2: dci/create-draft-post
	 * Menyimpan artikel baru sebagai DRAF — tidak pernah publish.
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/create-draft-post',
		array(
			'label'       => __( 'Create Draft Post', 'dci-mcp-bridge' ),
			'description' => __( 'Save a new WordPress post as a DRAFT (never published). Use it after generating content, e.g. with dci/generate-text. Returns the draft post ID, edit link and preview link so a human can review and publish.', 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'title'        => array(
						'type'        => 'string',
						'description' => __( 'Post title (plain text).', 'dci-mcp-bridge' ),
					),
					'content_html' => array(
						'type'        => 'string',
						'description' => __( 'Post body as safe HTML (h2/h3/p/ul/ol/a/strong/em allowed).', 'dci-mcp-bridge' ),
					),
					'excerpt'      => array(
						'type'        => 'string',
						'description' => __( 'Optional. Short summary of the post.', 'dci-mcp-bridge' ),
					),
				),
				'required'   => array( 'title', 'content_html' ),
			),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'      => array( 'type' => 'integer' ),
					'status'       => array( 'type' => 'string' ),
					'edit_link'    => array( 'type' => 'string', 'description' => __( 'Admin edit URL.', 'dci-mcp-bridge' ) ),
					'preview_link' => array( 'type' => 'string', 'description' => __( 'Front-end preview URL.', 'dci-mcp-bridge' ) ),
				),
				'required'   => array( 'post_id', 'status' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_create_draft_post',
			'permission_callback' => 'dci_mcp_bridge_permission_edit_posts',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,  // Membuat konten baru di situs.
					'destructive' => false,  // Tidak menghapus/mengubah yang sudah ada.
					'idempotent'  => false,  // Dipanggil dua kali = dua draf.
				),
				'public'      => true,
				'mcp'         => array( 'public' => true ),
			),
		)
	);

	/* --------------------------------------------------------
	 * Ability 3: dci/update-draft-post
	 * Memperbaiki isi draf yang sudah ada — HANYA draf, bukan artikel terbit.
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/update-draft-post',
		array(
			'label'       => __( 'Update Draft Post', 'dci-mcp-bridge' ),
			'description' => __( "Update an existing DRAFT post's title, body, and/or excerpt. Works ONLY on drafts (status draft/pending/auto-draft); published or private posts are rejected. Typical flow: audit with dci/audit-article, generate improved content with dci/generate-text, then apply it here.", 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'      => array(
						'type'        => 'integer',
						'description' => __( 'ID of the draft post to update.', 'dci-mcp-bridge' ),
					),
					'title'        => array(
						'type'        => 'string',
						'description' => __( 'Optional. New post title (plain text).', 'dci-mcp-bridge' ),
					),
					'content_html' => array(
						'type'        => 'string',
						'description' => __( 'Optional. Replacement post body as safe HTML (full replacement, not a patch).', 'dci-mcp-bridge' ),
					),
					'excerpt'      => array(
						'type'        => 'string',
						'description' => __( 'Optional. New excerpt (plain text).', 'dci-mcp-bridge' ),
					),
				),
				'required'   => array( 'post_id' ),
			),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'      => array( 'type' => 'integer' ),
					'status'       => array( 'type' => 'string' ),
					'updated'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'edit_link'    => array( 'type' => 'string' ),
					'preview_link' => array( 'type' => 'string' ),
				),
				'required'   => array( 'post_id', 'status', 'updated' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_update_draft_post',
			'permission_callback' => 'dci_mcp_bridge_permission_edit_posts',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false, // Hanya draf; penggantian penuh tapi tidak menghapus data.
					'idempotent'  => true,  // Nilai akhir sama walau dipanggil ulang.
				),
				'public'      => true,
				'mcp'         => array( 'public' => true ),
			),
		)
	);

	/* --------------------------------------------------------
	 * Ability 4: dci/set-post-seo-meta
	 * Mengisi field SEO Rank Math pada draf (judul/deskripsi/keyword).
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/set-post-seo-meta',
		array(
			'label'       => __( 'Set Post SEO Meta (Rank Math)', 'dci-mcp-bridge' ),
			'description' => __( "Write Rank Math SEO fields on a DRAFT post: meta_title (ideal 40-60 characters), meta_description (ideal 120-160 characters, include the focus keyword naturally), and focus_keyword. Works ONLY on drafts (draft/pending/auto-draft). Meta keys used: rank_math_title, rank_math_description, rank_math_focus_keyword.", 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'          => array(
						'type'        => 'integer',
						'description' => __( 'ID of the draft post.', 'dci-mcp-bridge' ),
					),
					'meta_title'       => array(
						'type'        => 'string',
						'description' => __( 'SEO title, ideal 40-60 characters, contains the focus keyword.', 'dci-mcp-bridge' ),
					),
					'meta_description' => array(
						'type'        => 'string',
						'description' => __( 'SEO meta description, ideal 120-160 characters.', 'dci-mcp-bridge' ),
					),
					'focus_keyword'    => array(
						'type'        => 'string',
						'description' => __( 'Primary focus keyword for Rank Math analysis.', 'dci-mcp-bridge' ),
					),
				),
				'required'   => array( 'post_id' ),
			),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'  => array( 'type' => 'integer' ),
					'updated'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'meta'     => array( 'type' => 'object' ),
				),
				'required'   => array( 'post_id', 'updated' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_set_post_seo_meta',
			'permission_callback' => 'dci_mcp_bridge_permission_edit_posts',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
				'public'      => true,
				'mcp'         => array( 'public' => true ),
			),
		)
	);

	/* --------------------------------------------------------
	 * Ability 5: dci/audit-article
	 * Audit on-page deterministik + data Rank Math, metodologi
	 * dari skill internal blog-seo-check / blog-rewrite.
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/audit-article',
		array(
			'label'       => __( 'Audit Article (SEO On-Page)', 'dci-mcp-bridge' ),
			'description' => __( 'Run a deterministic on-page SEO audit on any post (draft or published): meta title & description lengths, focus keyword placement and density, heading structure (single-H1 rule, no skipped levels), internal/external link counts, generic anchor text detection, image alt coverage, word count, and Rank Math score. Returns a per-check PASS/WARN/FAIL list with Indonesian fix suggestions. Combine the results with editorial judgment (E-E-A-T, AI-slop scan) before rewriting via dci/update-draft-post.', 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'ID of the post to audit.', 'dci-mcp-bridge' ),
					),
				),
				'required'   => array( 'post_id' ),
			),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'    => array( 'type' => 'integer' ),
					'status'     => array( 'type' => 'string' ),
					'word_count' => array( 'type' => 'integer' ),
					'summary'    => array( 'type' => 'object' ),
					'checks'     => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
					'rank_math'  => array( 'type' => 'object' ),
				),
				'required'   => array( 'post_id', 'checks', 'summary' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_audit_article',
			'permission_callback' => 'dci_mcp_bridge_permission_edit_posts',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,   // Hanya membaca, tidak mengubah apa pun.
					'destructive' => false,
					'idempotent'  => true,
				),
				'public'      => true,
				'mcp'         => array( 'public' => true ),
			),
		)
	);

	/* --------------------------------------------------------
	 * Ability 6: dci/publish-post
	 * Menerbitkan draf — aksi eksplisit, terpisah dari pembuatan.
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/publish-post',
		array(
			'label'       => __( 'Publish Draft Post', 'dci-mcp-bridge' ),
			'description' => __( 'Publish an existing DRAFT post immediately. This is an explicit, separate action: create/update always stay in draft, then this ability publishes when the user clearly asks for it (e.g. "langsung terbit"). Requires the publish_posts capability (Editor+). Returns the live permalink.', 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'ID of the draft post to publish.', 'dci-mcp-bridge' ),
					),
				),
				'required'   => array( 'post_id' ),
			),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array( 'type' => 'integer' ),
					'status'  => array( 'type' => 'string' ),
					'link'    => array( 'type' => 'string' ),
				),
				'required'   => array( 'post_id', 'status', 'link' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_publish_post',
			'permission_callback' => 'dci_mcp_bridge_permission_publish_posts',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,  // Mengubah status konten menjadi publik.
					'idempotent'  => true,
				),
				'public'      => true,
				'mcp'         => array( 'public' => true ),
			),
		)
	);

	/* --------------------------------------------------------
	 * Ability 7: dci/get-seo-config
	 * Membaca konfigurasi best-practice Rank Math situs ini agar
	 * konten yang dibuat AI selalu sinkron dengan setelannya.
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/get-seo-config',
		array(
			'label'       => __( 'Get Rank Math SEO Config', 'dci-mcp-bridge' ),
			'description' => __( "Read this site's SEO configuration so generated content stays in sync: Rank Math modules, post title format (e.g. \"%title% %sep% %sitename%\" — do NOT append the site name manually when this template already does), meta description template, default robots directives, default schema/article type for posts, AND the AI Puffer engine status including which providers have API keys configured (names only) — use a configured provider with dci/generate-text. Call this ONCE before generating a new article.", 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(),
			),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array(
					'rank_math_active' => array( 'type' => 'boolean' ),
					'modules'          => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'post_type_post'   => array( 'type' => 'object' ),
					'aip'              => array( 'type' => 'object' ),
					'integrity'        => array( 'type' => 'object' ),
				),
				'required'   => array( 'rank_math_active' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_get_seo_config',
			'permission_callback' => 'dci_mcp_bridge_permission_edit_posts',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'public'      => true,
				'mcp'         => array( 'public' => true ),
			),
		)
	);

	/* --------------------------------------------------------
	 * Ability 8: dci/check-originality
	 * Gerbang finalisasi: deteksi AI + plagiarisme via Winston AI.
	 * MAHAL (kredit per kata) — dipanggil SEKALI menjelang final.
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/check-originality',
		array(
			'label'       => __( 'Check Originality (AI Detector + Plagiarism)', 'dci-mcp-bridge' ),
			'description' => __( "FINALIZATION GATE, costs real credits via Winston AI (1 credit/word for AI detection, 2 credits/word for plagiarism — a 2000-word post costs ~6000 credits). Call it ONCE near the end of the workflow, not per iteration. Returns: Winston human score (low = AI-suspect) with per-sentence flags, readability, plagiarism percentage, and top matching sources. Requires the Winston AI API key saved in DCI Bridge settings (or the DCI_WINSTON_API_KEY constant). To save credits, pass checks:['ai_detect'] when plagiarism was already verified and content is unchanged.", 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'          => array(
						'type'        => 'integer',
						'description' => __( 'ID of the post to check.', 'dci-mcp-bridge' ),
					),
					'checks'           => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string', 'enum' => array( 'ai_detect', 'plagiarism' ) ),
						'description' => __( 'Which checks to run. Default: both.', 'dci-mcp-bridge' ),
					),
					'language'         => array(
						'type'        => 'string',
						'default'     => 'id',
						'description' => __( '2-letter language code (id = Indonesian).', 'dci-mcp-bridge' ),
					),
					'excluded_sources' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => __( 'Optional. Domains/URLs to exclude from plagiarism matching.', 'dci-mcp-bridge' ),
					),
				),
				'required'   => array( 'post_id' ),
			),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'    => array( 'type' => 'integer' ),
					'status'     => array( 'type' => 'string' ),
					'ai_detect'  => array( 'type' => array( 'object', 'null' ) ),
					'plagiarism' => array( 'type' => array( 'object', 'null' ) ),
					'credits'    => array( 'type' => 'object' ),
					'notes'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
				'required'   => array( 'post_id', 'credits', 'notes' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_check_originality',
			'permission_callback' => 'dci_mcp_bridge_permission_edit_posts',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,   // Situs tidak berubah (kredit layanan tetap terpakai).
					'destructive' => false,
					'idempotent'  => false,  // Setiap panggilan mengonsumsi kredit baru.
				),
				'public'      => true,
				'mcp'         => array( 'public' => true ),
			),
		)
	);
}

/* ============================================================
 * BAGIAN 3 — PERMISSION CALLBACK (LAPIS IZIN PER-ABILITY)
 * ============================================================ */

/**
 * Izin dasar semua ability DCI: minimal Editor.
 *
 * @param mixed $input Argumen ability (tidak dipakai di sini).
 * @return bool|WP_Error True jika berhak.
 */
function dci_mcp_bridge_permission_edit_posts( $input = null ) {
	unset( $input );

	if ( ! current_user_can( 'edit_posts' ) ) {
		return new WP_Error(
			'dci_forbidden',
			__( 'Hanya pengguna dengan peran Editor atau Administrator yang dapat memakai kemampuan ini.', 'dci-mcp-bridge' ),
			array( 'status' => 403 )
		);
	}

	return true;
}

/* ============================================================
 * BAGIAN 4 — EXECUTE CALLBACK
 * ============================================================ */

/**
 * Eksekusi ability dci/generate-text: meneruskan permintaan ke AI Puffer.
 *
 * @param array $input Argumen tervalidasi dari ability.
 * @return array|WP_Error Hasil generasi atau kesalahan.
 */
function dci_mcp_bridge_execute_generate_text( $input = array() ) {
	$input = is_array( $input ) ? $input : array();

	$prompt = isset( $input['prompt'] ) ? (string) $input['prompt'] : '';
	if ( '' === trim( $prompt ) ) {
		return new WP_Error(
			'dci_missing_prompt',
			__( 'Parameter "prompt" wajib diisi.', 'dci-mcp-bridge' )
		);
	}

	return dci_mcp_bridge_aipkit_generate( $input );
}

/**
 * Eksekusi ability dci/create-draft-post: simpan draf artikel.
 *
 * @param array $input Argumen tervalidasi dari ability.
 * @return array|WP_Error Data draf yang dibuat atau kesalahan.
 */
function dci_mcp_bridge_execute_create_draft_post( $input = array() ) {
	$input = is_array( $input ) ? $input : array();

	$title = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
	if ( '' === trim( $title ) ) {
		return new WP_Error(
			'dci_missing_title',
			__( 'Parameter "title" wajib diisi.', 'dci-mcp-bridge' )
		);
	}

	$content = isset( $input['content_html'] ) ? wp_kses_post( $input['content_html'] ) : '';
	if ( '' === trim( $content ) ) {
		return new WP_Error(
			'dci_missing_content',
			__( 'Parameter "content_html" wajib diisi.', 'dci-mcp-bridge' )
		);
	}

	$excerpt = isset( $input['excerpt'] ) ? sanitize_text_field( $input['excerpt'] ) : '';

	// Status dikunci 'draft' — keputusan publish selalu di tangan manusia.
	$post_id = wp_insert_post(
		array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_status'  => 'draft',
			'post_type'    => 'post',
			'post_author'  => get_current_user_id(),
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	$edit_link = get_edit_post_link( $post_id, 'raw' );

	return array(
		'post_id'      => (int) $post_id,
		'status'       => 'draft',
		'edit_link'    => $edit_link ? $edit_link : '',
		'preview_link' => get_preview_post_link( $post_id ),
	);
}

/**
 * Izin khusus penerbitan: kemampuan WordPress 'publish_posts'
 * (Editor dan Administrator memilikinya; Author tidak).
 *
 * @param mixed $input Argumen ability (tidak dipakai di sini).
 * @return bool|WP_Error True jika berhak.
 */
function dci_mcp_bridge_permission_publish_posts( $input = null ) {
	unset( $input );

	if ( ! current_user_can( 'publish_posts' ) ) {
		return new WP_Error(
			'dci_forbidden_publish',
			__( 'Hanya pengguna dengan kemampuan publish_posts (Editor/Administrator) yang dapat menerbitkan artikel.', 'dci-mcp-bridge' ),
			array( 'status' => 403 )
		);
	}

	return true;
}

/* ============================================================
 * BAGIAN 4B — JAGAAN DRAF (DIPAKAI ABILITY PERBAIKI/SET-META)
 * ============================================================ */

/**
 * Ambil post dan pastikan statusnya masih draf (boleh diubah AI).
 *
 * @param mixed $post_id ID post.
 * @return WP_Post|WP_Error Objek post atau kesalahan.
 */
function dci_mcp_bridge_get_editable_draft( $post_id ) {
	$post_id = absint( $post_id );

	if ( $post_id <= 0 ) {
		return new WP_Error(
			'dci_invalid_post_id',
			__( 'Parameter "post_id" harus berupa angka ID yang valid.', 'dci-mcp-bridge' )
		);
	}

	$post = get_post( $post_id );

	if ( ! $post || 'post' !== $post->post_type ) {
		return new WP_Error(
			'dci_post_not_found',
			__( 'Post dengan ID tersebut tidak ditemukan (atau bukan post type "post").', 'dci-mcp-bridge' )
		);
	}

	// Keputusan keamanan: AI hanya boleh menyentuh draf. Artikel terbit
	// harus diperbaiki lewat editor manusia agar ada kontrol penuh.
	$allowed_statuses = array( 'draft', 'pending', 'auto-draft' );

	if ( ! in_array( $post->post_status, $allowed_statuses, true ) ) {
		return new WP_Error(
			'dci_not_editable_draft',
			sprintf(
				/* translators: %s: status post saat ini. */
				__( 'Post ini berstatus "%s". Hanya draf (draft/pending) yang boleh diubah lewat AI — artikel yang sudah terbit silakan edit manual di editor.', 'dci-mcp-bridge' ),
				$post->post_status
			)
		);
	}

	return $post;
}

/**
 * Eksekusi ability dci/update-draft-post.
 *
 * @param array $input Argumen ability.
 * @return array|WP_Error
 */
function dci_mcp_bridge_execute_update_draft_post( $input = array() ) {
	$input = is_array( $input ) ? $input : array();

	$post = dci_mcp_bridge_get_editable_draft( $input['post_id'] ?? 0 );
	if ( is_wp_error( $post ) ) {
		return $post;
	}

	$update = array( 'ID' => $post->ID );
	$updated = array();

	if ( isset( $input['title'] ) && '' !== trim( (string) $input['title'] ) ) {
		$update['post_title'] = sanitize_text_field( (string) $input['title'] );
		$updated[] = 'title';
	}

	if ( isset( $input['content_html'] ) && '' !== trim( (string) $input['content_html'] ) ) {
		$update['post_content'] = wp_kses_post( (string) $input['content_html'] );
		$updated[] = 'content';
	}

	if ( isset( $input['excerpt'] ) ) {
		$update['post_excerpt'] = sanitize_text_field( (string) $input['excerpt'] );
		$updated[] = 'excerpt';
	}

	if ( empty( $updated ) ) {
		return new WP_Error(
			'dci_nothing_to_update',
			__( 'Tidak ada kolom yang dikirim. Sertakan minimal salah satu: title, content_html, atau excerpt.', 'dci-mcp-bridge' )
		);
	}

	$result = wp_update_post( $update, true );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$edit_link = get_edit_post_link( $post->ID, 'raw' );

	return array(
		'post_id'      => (int) $post->ID,
		'status'       => 'draft',
		'updated'      => $updated,
		'edit_link'    => $edit_link ? $edit_link : '',
		'preview_link' => get_preview_post_link( $post->ID ),
	);
}

/**
 * Eksekusi ability dci/set-post-seo-meta: isi field SEO Rank Math pada draf.
 *
 * @param array $input Argumen ability.
 * @return array|WP_Error
 */
function dci_mcp_bridge_execute_set_post_seo_meta( $input = array() ) {
	$input = is_array( $input ) ? $input : array();

	$post = dci_mcp_bridge_get_editable_draft( $input['post_id'] ?? 0 );
	if ( is_wp_error( $post ) ) {
		return $post;
	}

	// Kunci postmeta diverifikasi dari source Rank Math (seo-by-rank-math).
	$fields = array(
		'meta_title'       => array( 'key' => 'rank_math_title', 'sanitize' => 'sanitize_text_field' ),
		'meta_description' => array( 'key' => 'rank_math_description', 'sanitize' => 'sanitize_text_field' ),
		'focus_keyword'    => array( 'key' => 'rank_math_focus_keyword', 'sanitize' => 'sanitize_text_field' ),
		'og_title'         => array( 'key' => 'rank_math_facebook_title', 'sanitize' => 'sanitize_text_field' ),
		'og_description'   => array( 'key' => 'rank_math_facebook_description', 'sanitize' => 'sanitize_text_field' ),
	);

	$updated = array();
	$current = array();

	foreach ( $fields as $field => $config ) {
		if ( ! isset( $input[ $field ] ) ) {
			continue;
		}

		$value = call_user_func( $config['sanitize'], (string) $input[ $field ] );

		if ( '' === $value ) {
			continue;
		}

		update_post_meta( $post->ID, $config['key'], $value );
		$updated[] = $config['key'];
		$current[ $field ] = $value;
	}

	// Direktif robots (array) — hanya nilai yang dikenal Rank Math yang diterima.
	if ( isset( $input['robots'] ) && is_array( $input['robots'] ) ) {
		$allowed_robots = array(
			'index', 'noindex', 'follow', 'nofollow', 'noarchive', 'noimageindex',
			'notranslate', 'nositelinkssearchbox', 'max-snippet:-1',
			'max-image-preview:large', 'max-video-preview:-1',
		);

		$robots = array_values( array_intersect( array_map( 'sanitize_text_field', array_map( 'strval', $input['robots'] ) ), $allowed_robots ) );

		if ( ! empty( $robots ) ) {
			update_post_meta( $post->ID, 'rank_math_robots', $robots );
			$updated[] = 'rank_math_robots';
			$current['robots'] = $robots;
		}
	}

	if ( empty( $updated ) ) {
		return new WP_Error(
			'dci_nothing_to_update',
			__( 'Tidak ada meta yang dikirim. Sertakan minimal salah satu: meta_title, meta_description, focus_keyword, og_title, og_description, atau robots.', 'dci-mcp-bridge' )
		);
	}

	return array(
		'post_id' => (int) $post->ID,
		'updated' => $updated,
		'meta'    => $current,
	);
}

/**
 * Eksekusi ability dci/publish-post: terbitkan draf.
 *
 * @param array $input Argumen ability.
 * @return array|WP_Error
 */
function dci_mcp_bridge_execute_publish_post( $input = array() ) {
	$input = is_array( $input ) ? $input : array();

	$post = dci_mcp_bridge_get_editable_draft( $input['post_id'] ?? 0 );
	if ( is_wp_error( $post ) ) {
		return $post;
	}

	$result = wp_update_post(
		array(
			'ID'          => $post->ID,
			'post_status' => 'publish',
		),
		true
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$permalink = get_permalink( $post->ID );

	return array(
		'post_id' => (int) $post->ID,
		'status'  => 'publish',
		'link'    => $permalink ? $permalink : '',
	);
}

/**
 * Status mesin AI Puffer untuk ability get-seo-config: keberadaan kelas
 * internal + daftar nama provider yang API key-nya terisi (tanpa nilai).
 *
 * @return array
 */
function dci_mcp_bridge_aip_status() {
	$aip_installed = class_exists( 'AIPKit_AI_Caller' );
	$aip_providers = array();
	$aip_public_api = false;

	if ( $aip_installed ) {
		$aip_opts = get_option( 'aipkit_options', array() );
		$aip_keys = ( isset( $aip_opts['api_keys'] ) && is_array( $aip_opts['api_keys'] ) ) ? $aip_opts['api_keys'] : array();

		foreach ( $aip_keys as $label => $config ) {
			if ( is_array( $config ) && isset( $config['api_key'] ) && is_string( $config['api_key'] ) && '' !== trim( $config['api_key'] ) ) {
				$aip_providers[] = (string) $label;
			}
		}

		$aip_public_api = array_key_exists( 'public_api_enabled', $aip_keys )
			? ( '1' === (string) $aip_keys['public_api_enabled'] )
			: false;
	}

	return array(
		'installed'            => $aip_installed,
		'providers_configured' => $aip_providers,
		'public_api_enabled'   => $aip_public_api,
	);
}

/**
 * Eksekusi ability dci/get-seo-config: baca konfigurasi Rank Math.
 *
 * Nama opsi memakai TANDA HUBUNG (verifikasi source class-installer.php
 * dan class-settings Rank Math) — salah format = senyap kosong.
 *
 * @param array $input Argumen ability (tidak dipakai).
 * @return array
 */
function dci_mcp_bridge_execute_get_seo_config( $input = array() ) {
	unset( $input );

	$is_active = defined( 'RANK_MATH_VERSION' ) || class_exists( '\\RankMath' );

	if ( ! $is_active ) {
		return array(
			'rank_math_active' => false,
			'modules'          => array(),
			'post_type_post'   => array(),
			'aip'              => dci_mcp_bridge_aip_status(),
			'integrity'        => array(
				'provider'   => 'winston',
				'configured' => ! empty( dci_mcp_bridge_winston_keys() ),
				'keys'       => count( dci_mcp_bridge_winston_keys() ),
			),
		);
	}

	$modules = get_option( 'rank_math_modules', array() );
	$titles  = get_option( 'rank-math-options-titles', array() );

	// Hanya kunci yang terverifikasi dari installer Rank Math yang dibaca.
	$post_config = array();

	$title_keys = array(
		'pt_post_title'                => 'title_format',
		'pt_post_description'          => 'description_format',
		'pt_post_robots'               => 'robots_default',
		'pt_post_custom_robots'        => 'robots_custom_enabled',
		'pt_post_default_rich_snippet' => 'rich_snippet_default',
		'pt_post_default_article_type' => 'article_type_default',
	);

	foreach ( $title_keys as $option_key => $output_key ) {
		if ( isset( $titles[ $option_key ] ) ) {
			$post_config[ $output_key ] = $titles[ $option_key ];
		}
	}

	// Status AI Puffer: ambil dari satu sumber (helper).
	$aip = dci_mcp_bridge_aip_status();

	return array(
		'rank_math_active' => true,
		'modules'          => is_array( $modules ) ? array_values( $modules ) : array(),
		'post_type_post'   => $post_config,
		'aip'              => $aip,
		'integrity'        => array(
			'provider'   => 'winston',
			'configured' => ! empty( dci_mcp_bridge_winston_keys() ),
			'keys'       => count( dci_mcp_bridge_winston_keys() ),
		),
	);
}

/* ============================================================
 * BAGIAN 4C — AUDIT ARTIKEL (METODOLOGI SKILL BLOG-SEO-CHECK)
 * Pemeriksaan deterministik di sisi server; penilaian editorial
 * (E-E-A-T, anti-slop) tetap dilakukan oleh AI agent.
 * ============================================================ */

/**
 * Tambahkan satu baris hasil pemeriksaan.
 *
 * @param array  $checks Array hasil (by reference).
 * @param string $id     ID pemeriksaan.
 * @param string $label  Nama pemeriksaan (Bahasa Indonesia).
 * @param string $status PASS|WARN|FAIL.
 * @param string $detail Kondisi terukur saat ini.
 * @param string $fix    Saran perbaikan (Bahasa Indonesia).
 */
function dci_mcp_bridge_audit_check( array &$checks, $id, $label, $status, $detail, $fix ) {
	$checks[] = array(
		'id'     => (string) $id,
		'label'  => (string) $label,
		'status' => (string) $status,
		'detail' => (string) $detail,
		'fix'    => (string) $fix,
	);
}

/**
 * Pencarian teks tanpa membedakan huruf besar/kecil — aman bila ekstensi
 * mbstring tidak tersedia di server (fallback ke stripos bawaan).
 *
 * @param string $haystack Teks sumber.
 * @param string $needle   Yang dicari.
 * @return int|false Posisi atau false.
 */
function dci_mcp_bridge_stripos( $haystack, $needle ) {
	return function_exists( 'mb_stripos' ) ? mb_stripos( $haystack, $needle ) : stripos( $haystack, $needle );
}

/**
 * Huruf kecil — aman bila ekstensi mbstring tidak tersedia.
 *
 * @param string $text Teks masukan.
 * @return string Teks huruf kecil.
 */
function dci_mcp_bridge_strtolower( $text ) {
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
}

/**
 * Eksekusi ability dci/audit-article.
 *
 * @param array $input Argumen ability.
 * @return array|WP_Error
 */
function dci_mcp_bridge_execute_audit_article( $input = array() ) {
	$input = is_array( $input ) ? $input : array();

	$post_id = absint( $input['post_id'] ?? 0 );
	$post    = $post_id > 0 ? get_post( $post_id ) : null;

	if ( ! $post || 'post' !== $post->post_type ) {
		return new WP_Error(
			'dci_post_not_found',
			__( 'Post dengan ID tersebut tidak ditemukan (atau bukan post type "post").', 'dci-mcp-bridge' )
		);
	}

	$content = (string) $post->post_content;
	$text    = trim( wp_strip_all_tags( $content ) );

	// Data Rank Math (kunci terverifikasi dari source seo-by-rank-math).
	$rm_title       = (string) get_post_meta( $post->ID, 'rank_math_title', true );
	$rm_description = (string) get_post_meta( $post->ID, 'rank_math_description', true );
	$rm_keyword     = (string) get_post_meta( $post->ID, 'rank_math_focus_keyword', true );
	$rm_score_raw   = get_post_meta( $post->ID, 'rank_math_seo_score', true );

	$effective_title = '' !== $rm_title ? $rm_title : $post->post_title;
	$title_len       = function_exists( 'mb_strlen' ) ? mb_strlen( $effective_title ) : strlen( $effective_title );
	$desc_len        = function_exists( 'mb_strlen' ) ? mb_strlen( $rm_description ) : strlen( $rm_description );

	// Hitung jumlah kata (aman UTF-8 untuk Bahasa Indonesia).
	preg_match_all( '/[\p{L}\p{N}]+/u', $text, $word_matches );
	$word_count = count( $word_matches[0] );

	$checks = array();

	/* --- Pemeriksaan judul & deskripsi meta --- */
	if ( '' === $rm_title && '' === $post->post_title ) {
		dci_mcp_bridge_audit_check( $checks, 'judul', 'Judul artikel', 'FAIL', 'Judul kosong.', 'Beri judul yang spesifik dan memuat kata kunci utama.' );
	} elseif ( $title_len >= 40 && $title_len <= 60 ) {
		dci_mcp_bridge_audit_check( $checks, 'judul', 'Panjang judul SEO', 'PASS', sprintf( 'Judul %d karakter (ideal 40-60).', $title_len ), '' );
	} elseif ( $title_len >= 30 && $title_len <= 75 ) {
		dci_mcp_bridge_audit_check( $checks, 'judul', 'Panjang judul SEO', 'WARN', sprintf( 'Judul %d karakter (ideal 40-60).', $title_len ), 'Perpendek atau perpanjang judul agar masuk rentang ideal dan tidak terpotong di hasil pencarian.' );
	} else {
		dci_mcp_bridge_audit_check( $checks, 'judul', 'Panjang judul SEO', 'FAIL', sprintf( 'Judul %d karakter (ideal 40-60).', $title_len ), 'Tulis ulang judul dalam rentang 40-60 karakter.' );
	}

	if ( '' === $rm_description ) {
		dci_mcp_bridge_audit_check( $checks, 'deskripsi_meta', 'Meta description', 'FAIL', 'Meta description belum diisi.', 'Tulis deskripsi 120-160 karakter yang merangkum manfaat halaman dan memuat kata kunci.' );
	} elseif ( $desc_len >= 120 && $desc_len <= 160 ) {
		dci_mcp_bridge_audit_check( $checks, 'deskripsi_meta', 'Meta description', 'PASS', sprintf( 'Deskripsi %d karakter (ideal 120-160).', $desc_len ), '' );
	} elseif ( $desc_len >= 80 && $desc_len <= 180 ) {
		dci_mcp_bridge_audit_check( $checks, 'deskripsi_meta', 'Meta description', 'WARN', sprintf( 'Deskripsi %d karakter (ideal 120-160).', $desc_len ), 'Sesuaikan panjang deskripsi ke rentang 120-160 karakter.' );
	} else {
		dci_mcp_bridge_audit_check( $checks, 'deskripsi_meta', 'Meta description', 'FAIL', sprintf( 'Deskripsi %d karakter (ideal 120-160).', $desc_len ), 'Tulis ulang deskripsi dalam rentang 120-160 karakter.' );
	}

	/* --- Pemeriksaan kata kunci --- */
	if ( '' === $rm_keyword ) {
		dci_mcp_bridge_audit_check( $checks, 'keyword', 'Kata kunci utama', 'FAIL', 'Focus keyword Rank Math belum diisi.', 'Tentukan satu kata kunci utama yang mencerminkan niat pencarian pembaca.' );
	} else {
		dci_mcp_bridge_audit_check( $checks, 'keyword', 'Kata kunci utama', 'PASS', sprintf( 'Kata kunci: "%s".', $rm_keyword ), '' );

		$in_title = false !== dci_mcp_bridge_stripos( $effective_title, $rm_keyword );
		dci_mcp_bridge_audit_check(
			$checks,
			'keyword_di_judul',
			'Kata kunci di judul',
			$in_title ? 'PASS' : 'FAIL',
			$in_title ? 'Kata kunci ada di judul.' : 'Kata kunci tidak ada di judul.',
			$in_title ? '' : 'Sisipkan kata kunci secara natural di awal judul.'
		);

		$first_words = array_slice( $word_matches[0], 0, 150 );
		$in_intro    = false !== dci_mcp_bridge_stripos( implode( ' ', $first_words ), $rm_keyword );
		dci_mcp_bridge_audit_check(
			$checks,
			'keyword_di_pembuka',
			'Kata kunci di paragraf pembuka',
			$in_intro ? 'PASS' : 'WARN',
			$in_intro ? 'Kata kunci muncul di 150 kata pertama.' : 'Kata kunci tidak muncul di 150 kata pertama.',
			$in_intro ? '' : 'Sebutkan kata kunci di kalimat awal artikel (pola answer-first).'
		);

		$pattern = '/' . preg_quote( $rm_keyword, '/' ) . '/iu';
		preg_match_all( $pattern, $text, $kw_matches );
		$occurrences = count( $kw_matches[0] );
		$density     = $word_count > 0 ? round( ( $occurrences / $word_count ) * 100, 2 ) : 0.0;

		if ( $density >= 0.4 && $density <= 3.0 ) {
			dci_mcp_bridge_audit_check( $checks, 'keyword_density', 'Kepadatan kata kunci', 'PASS', sprintf( 'Muncul %dx dari %d kata (kepadatan %s%%).', $occurrences, $word_count, $density ), '' );
		} elseif ( $density < 0.4 ) {
			dci_mcp_bridge_audit_check( $checks, 'keyword_density', 'Kepadatan kata kunci', 'WARN', sprintf( 'Muncul %dx dari %d kata (kepadatan %s%%).', $occurrences, $word_count, $density ), 'Tambahkan beberapa penyebutan natural kata kunci dan variasinya.' );
		} else {
			dci_mcp_bridge_audit_check( $checks, 'keyword_density', 'Kepadatan kata kunci', 'FAIL', sprintf( 'Muncul %dx dari %d kata (kepadatan %s%%).', $occurrences, $word_count, $density ), 'Kepadatan terlalu tinggi (risiko keyword stuffing) — kurangi pengulangan, pakai sinonim.' );
		}
	}

	/* --- Struktur heading --- */
	preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $content, $heading_matches, PREG_SET_ORDER );
	$levels      = array();
	$h1_count    = 0;
	$prev_level  = 1;

	foreach ( $heading_matches as $heading ) {
		$level    = (int) $heading[1];
		$levels[] = $level;

		if ( 1 === $level ) {
			$h1_count++;
		}
	}

	if ( $h1_count > 0 ) {
		dci_mcp_bridge_audit_check( $checks, 'h1', 'Satu-satunya H1', 'FAIL', sprintf( 'Ada %d tag H1 di isi konten.', $h1_count ), 'Hapus H1 dari isi — judul post sudah menjadi H1; turunkan menjadi H2.' );
	} else {
		dci_mcp_bridge_audit_check( $checks, 'h1', 'Satu-satunya H1', 'PASS', 'Tidak ada H1 ganda di isi konten.', '' );
	}

	$has_h2 = in_array( 2, $levels, true );
	dci_mcp_bridge_audit_check(
		$checks,
		'heading_h2',
		'Ada subjudul H2',
		$has_h2 ? 'PASS' : 'WARN',
		$has_h2 ? sprintf( 'Struktur heading: %s.', implode( ' > ', $levels ) ) : 'Tidak ada H2 sama sekali.',
		$has_h2 ? '' : 'Pecah isi menjadi bagian-bagian dengan H2 agar mudah dipindai.'
	);

	$skipped = false;
	foreach ( $levels as $level ) {
		if ( $level > $prev_level + 1 ) {
			$skipped = true;
		}
		$prev_level = $level;
	}
	dci_mcp_bridge_audit_check(
		$checks,
		'heading_urutan',
		'Urutan heading bertingkat',
		$skipped ? 'WARN' : 'PASS',
		$skipped ? 'Ada tingkat heading yang dilompati (mis. H2 langsung ke H4).' : 'Tidak ada tingkat heading yang dilompati.',
		$skipped ? 'Turunkan heading yang melompat agar hierarki H2 > H3 > H4 konsisten.' : ''
	);

	/* --- Tautan internal & eksternal --- */
	preg_match_all( '/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $link_matches, PREG_SET_ORDER );
	$site_host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$internal     = 0;
	$external     = 0;
	$generic_hits = array();
	$href_seen    = array();
	$generic_anchors = array( 'klik di sini', 'di sini', 'baca selengkapnya', 'baca lebih lanjut', 'click here', 'read more', 'link' );

	foreach ( $link_matches as $link ) {
		$href  = trim( (string) $link[1] );
		$label = trim( wp_strip_all_tags( (string) $link[2] ) );
		$host  = (string) wp_parse_url( $href, PHP_URL_HOST );

		if ( '' !== $host ) {
			$host_norm = preg_replace( '/^www\./i', '', $host );
			$site_norm = preg_replace( '/^www\./i', '', $site_host );

			if ( '' !== $site_norm && null !== $host_norm && strtolower( $host_norm ) === strtolower( $site_norm ) ) {
				$internal++;
			} else {
				$external++;
			}
		} elseif ( 0 === strpos( $href, '/' ) && 0 !== strpos( $href, '//' ) ) {
			// Tautan relatif dianggap internal; "//host/..." adalah eksternal.
			$internal++;
		}

		$label_norm = dci_mcp_bridge_strtolower( $label );
		if ( in_array( $label_norm, $generic_anchors, true ) ) {
			$generic_hits[] = $label;
		}

		$href_seen[ $href ] = ( $href_seen[ $href ] ?? 0 ) + 1;
	}

	// Deteksi tautan duplikat ke URL yang sama (metodologi link dedup).
	$total_links  = count( $link_matches );
	$duplicates   = $total_links - count( $href_seen );
	dci_mcp_bridge_audit_check(
		$checks,
		'tautan_duplikat',
		'Tautan duplikat',
		0 === $duplicates ? 'PASS' : 'WARN',
		sprintf( '%d tautan, %d URL unik.', $total_links, count( $href_seen ) ),
		0 === $duplicates ? '' : 'Gabungkan tautan berulang ke URL yang sama — sisakan satu dengan anchor paling deskriptif.'
	);

	if ( $internal >= 3 && $internal <= 10 ) {
		dci_mcp_bridge_audit_check( $checks, 'tautan_internal', 'Jumlah tautan internal', 'PASS', sprintf( '%d tautan internal (ideal 3-10).', $internal ), '' );
	} elseif ( $internal > 0 ) {
		dci_mcp_bridge_audit_check( $checks, 'tautan_internal', 'Jumlah tautan internal', 'WARN', sprintf( 'Hanya %d tautan internal (ideal 3-10).', $internal ), 'Tautkan ke 2-3 artikel/halaman terkait lainnya di situs ini dengan anchor deskriptif.' );
	} else {
		dci_mcp_bridge_audit_check( $checks, 'tautan_internal', 'Jumlah tautan internal', 'FAIL', 'Tidak ada tautan internal.', 'Tambahkan 3-10 tautan ke halaman terkait agar pembaca (dan crawler) bisa menjelajah.' );
	}

	dci_mcp_bridge_audit_check(
		$checks,
		'tautan_eksternal',
		'Tautan eksternal otoritatif',
		$external >= 1 ? 'PASS' : 'WARN',
		sprintf( '%d tautan eksternal.', $external ),
		$external >= 1 ? '' : 'Tambahkan 1-3 rujukan ke sumber eksternal kredibel (bukan kompetitor).'
	);

	dci_mcp_bridge_audit_check(
		$checks,
		'anchor_text',
		'Kualitas anchor text',
		empty( $generic_hits ) ? 'PASS' : 'FAIL',
		empty( $generic_hits ) ? 'Semua anchor text deskriptif.' : 'Anchor generik ditemukan: ' . implode( ', ', array_unique( $generic_hits ) ) . '.',
		empty( $generic_hits ) ? '' : 'Ganti anchor generik ("klik di sini") dengan frasa yang menjelaskan tujuan tautan.'
	);

	/* --- Frasa AI generik (metodologi skill blog-rewrite Phase 1) ---
	 * Advisory: mendeteksi pola klise, bukan mendeteksi kepenulisan AI.
	 * Daftar EN dari skill blog-rewrite; daftar ID adalah padanan umumnya. */
	$slop_phrases = array(
		// English (verbatim dari daftar skill).
		"in today's digital landscape",
		"it's important to note",
		'dive into',
		'game-changer',
		'navigate the landscape',
		'revolutionize',
		'seamlessly',
		'cutting-edge',
		'harness the power',
		'delve',
		'multifaceted',
		'tapestry',
		'embark on',
		// Indonesian (padanan frasa klise yang umum di konten AI).
		'di era digital ini',
		'di era digital yang serba cepat',
		'tidak dapat dipungkiri',
		'penting untuk dicatat',
		'mari kita selami',
		'mengubah permainan',
		'dengan mulus',
		'teknologi mutakhir',
		'manfaatkan kekuatan',
		'panduan komprehensif',
	);

	$text_lower        = dci_mcp_bridge_strtolower( $text );
	$slop_hits         = array();

	foreach ( $slop_phrases as $phrase ) {
		if ( false !== strpos( $text_lower, $phrase ) ) {
			$slop_hits[] = $phrase;
		}
	}

	$slop_count = count( $slop_hits );
	if ( 0 === $slop_count ) {
		dci_mcp_bridge_audit_check( $checks, 'frasa_ai', 'Frasa klise AI generik', 'PASS', 'Tidak ditemukan frasa klise umum.', '' );
	} elseif ( $slop_count <= 2 ) {
		dci_mcp_bridge_audit_check( $checks, 'frasa_ai', 'Frasa klise AI generik', 'WARN', sprintf( 'Ditemukan %d frasa klise: "%s".', $slop_count, implode( '", "', $slop_hits ) ), 'Tulis ulang kalimat tersebut dengan gaya bahasa natural khas brand.' );
	} else {
		dci_mcp_bridge_audit_check( $checks, 'frasa_ai', 'Frasa klise AI generik', 'FAIL', sprintf( 'Ditemukan %d frasa klise: "%s".', $slop_count, implode( '", "', $slop_hits ) ), 'Tulis ulang dengan suara penulis manusia: kalimat bervariasi, contoh konkret spesifik lokal/industri, tanpa frasa template.' );
	}

	/* --- Gambar & alt text --- */
	preg_match_all( '/<img\b[^>]*>/is', $content, $img_matches );
	$img_total  = count( $img_matches[0] );
	$img_no_alt = 0;

	foreach ( $img_matches[0] as $img ) {
		if ( ! preg_match( '/\balt=["\'][^"\']+["\']/i', $img ) ) {
			$img_no_alt++;
		}
	}

	if ( 0 === $img_total ) {
		dci_mcp_bridge_audit_check( $checks, 'gambar', 'Gambar & alt text', 'WARN', 'Belum ada gambar di dalam konten.', 'Tambahkan gambar pendukung dengan alt text deskriptif.' );
	} elseif ( 0 === $img_no_alt ) {
		dci_mcp_bridge_audit_check( $checks, 'gambar', 'Gambar & alt text', 'PASS', sprintf( '%d gambar, semua punya alt text.', $img_total ), '' );
	} else {
		dci_mcp_bridge_audit_check( $checks, 'gambar', 'Gambar & alt text', 'FAIL', sprintf( '%d dari %d gambar tanpa alt text.', $img_no_alt, $img_total ), 'Isi alt text deskriptif pada semua gambar.' );
	}

	/* --- Ringkasan & panjang konten --- */
	if ( $word_count >= 600 ) {
		dci_mcp_bridge_audit_check( $checks, 'panjang', 'Panjang konten', 'PASS', sprintf( '%d kata.', $word_count ), '' );
	} elseif ( $word_count >= 300 ) {
		dci_mcp_bridge_audit_check( $checks, 'panjang', 'Panjang konten', 'WARN', sprintf( '%d kata (ideal 600+ untuk artikel informatif).', $word_count ), 'Perkaya isi dengan detail, contoh, atau FAQ agar lebih lengkap dibanding pesaing.' );
	} else {
		dci_mcp_bridge_audit_check( $checks, 'panjang', 'Panjang konten', 'FAIL', sprintf( 'Hanya %d kata — terlalu tipis.', $word_count ), 'Tulis ulang menjadi minimal 300 kata, idealnya 600+ kata yang benar-benar menjawab kebutuhan pembaca.' );
	}

	dci_mcp_bridge_audit_check(
		$checks,
		'excerpt',
		'Ringkasan (excerpt)',
		'' !== trim( (string) $post->post_excerpt ) ? 'PASS' : 'WARN',
		'' !== trim( (string) $post->post_excerpt ) ? 'Excerpt terisi.' : 'Excerpt kosong.',
		'' !== trim( (string) $post->post_excerpt ) ? '' : 'Isi excerpt singkat untuk arsip dan tampilan daftar artikel.'
	);

	$pass = 0;
	$warn = 0;
	$fail = 0;

	foreach ( $checks as $check ) {
		if ( 'PASS' === $check['status'] ) {
			$pass++;
		} elseif ( 'WARN' === $check['status'] ) {
			$warn++;
		} else {
			$fail++;
		}
	}

	return array(
		'post_id'    => (int) $post->ID,
		'status'     => (string) $post->post_status,
		'word_count' => (int) $word_count,
		'summary'    => array(
			'pass' => $pass,
			'warn' => $warn,
			'fail' => $fail,
		),
		'checks'     => $checks,
		'rank_math'  => array(
			'title'       => $rm_title,
			'description' => $rm_description,
			'keyword'     => $rm_keyword,
			'score'       => ( '' !== (string) $rm_score_raw ) ? (int) $rm_score_raw : null,
		),
	);
}

/* ============================================================
 * BAGIAN 5 — JEMBATAN KE AI PUFFER (LOOPBACK REST)
 *
 * Memanggil endpoint resmi AI Puffer (aipkit/v1/generate) dari
 * dalam server. Kontrak diverifikasi dari source plugin v2.4.95:
 * auth via Bearer Public API Key, respons 200 berisi {content, provider, model}.
 * Prasyarat di pengaturan AIPKit:
 *   1. Aktifkan "Public API access" (public_api_enabled = 1).
 *   2. Isi Public API Key.
 * ============================================================ */

/**
 * Panggil mesin AI Puffer dan kembalikan hasil generasi.
 *
 * Jalur utama: panggilan internal langsung ke kelas AIPKit_AI_Caller —
 * tanpa HTTP, tanpa Public API Key (kontrak diverifikasi dari source
 * v2.4.95: constructor(bool, string) dan make_standard_call(provider,
 * model, messages, ai_params, base_system_instruction)).
 * Jalur cadangan: loopback REST aipkit/v1/generate — dipakai hanya bila
 * kelas internal tidak ditemukan (mis. plugin berubah besar); jalur ini
 * butuh Public API aktif dan tidak berjalan di host yang memblokir loopback.
 *
 * @param array $args Argumen: prompt, system_instruction, provider, model, max_tokens, temperature.
 * @return array|WP_Error Array {content, provider, model} atau kesalahan.
 */
function dci_mcp_bridge_aipkit_generate( array $args ) {
	$prompt = isset( $args['prompt'] ) ? (string) $args['prompt'] : '';

	if ( '' === trim( $prompt ) ) {
		return new WP_Error(
			'dci_missing_prompt',
			__( 'Parameter "prompt" wajib diisi.', 'dci-mcp-bridge' )
		);
	}

	$provider = isset( $args['provider'] ) && is_string( $args['provider'] ) && '' !== trim( $args['provider'] )
		? trim( $args['provider'] )
		: 'OpenAI';
	$model    = isset( $args['model'] ) && is_string( $args['model'] ) && '' !== trim( $args['model'] )
		? trim( $args['model'] )
		: 'gpt-4o-mini';
	$system   = isset( $args['system_instruction'] ) && is_string( $args['system_instruction'] ) && '' !== trim( $args['system_instruction'] )
		? trim( $args['system_instruction'] )
		: null;

	$ai_params = array();
	if ( isset( $args['max_tokens'] ) ) {
		// Kunci internal lintas-provider AI Puffer adalah 'max_completion_tokens'
		// (lihat classes/ai/providers/*.php); 'max_tokens' hanya dinormalisasi
		// oleh driver OpenAI, driver Claude/xAI/OpenRouter mengabaikannya.
		$ai_params['max_completion_tokens'] = (int) $args['max_tokens'];
	}
	if ( isset( $args['temperature'] ) ) {
		$ai_params['temperature'] = (float) $args['temperature'];
	}

	/* ---------- JALUR UTAMA: panggilan internal langsung ---------- */
	if ( class_exists( 'AIPKit_AI_Caller' ) && class_exists( 'AIPKit_Providers' ) ) {
		if ( method_exists( 'AIPKit_Providers', 'normalize_provider_label' ) ) {
			$provider = AIPKit_Providers::normalize_provider_label( $provider );
		}

		if ( method_exists( 'AIPKit_Providers', 'get_text_generation_providers' ) ) {
			$valid_providers = AIPKit_Providers::get_text_generation_providers();

			if ( ! in_array( $provider, $valid_providers, true ) ) {
				return new WP_Error(
					'dci_invalid_provider',
					sprintf(
						/* translators: 1: provider yang dikirim, 2: daftar provider valid. */
						__( 'Provider "%1$s" tidak dikenal. Yang tersedia: %2$s.', 'dci-mcp-bridge' ),
						$provider,
						implode( ', ', $valid_providers )
					)
				);
			}
		}

		$caller = new AIPKit_AI_Caller( false, 'mcp' );
		$result = $caller->make_standard_call(
			$provider,
			$model,
			array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			$ai_params,
			$system
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'content'  => isset( $result['content'] ) ? (string) $result['content'] : '',
			'provider' => $provider,
			'model'    => isset( $result['model'] ) ? (string) $result['model'] : $model,
		);
	}

	/* ---------- JALUR CADANGAN: loopback REST (butuh Public API) ---------- */
	$opts     = get_option( 'aipkit_options', array() );
	$api_keys = ( isset( $opts['api_keys'] ) && is_array( $opts['api_keys'] ) ) ? $opts['api_keys'] : array();
	$api_key  = isset( $api_keys['public_api_key'] ) ? trim( (string) $api_keys['public_api_key'] ) : '';

	// Logika kompatibilitas yang sama dengan classes/api/rest.php plugin AI Puffer.
	if ( array_key_exists( 'public_api_enabled', $api_keys ) ) {
		$enabled = ( '1' === (string) $api_keys['public_api_enabled'] );
	} else {
		$enabled = ( '' !== $api_key ); // Instalasi lama: aktif bila kunci pernah diisi.
	}

	if ( ! $enabled || '' === $api_key ) {
		return new WP_Error(
			'dci_aipkit_not_configured',
			__( 'Mesin AI Puffer tidak dapat dijangkau (kelas internal tidak ditemukan) dan jalur cadangan REST juga belum dikonfigurasi: aktifkan "Public API access" di menu AIPKit > Settings, atau perbarui plugin AI Puffer.', 'dci-mcp-bridge' )
		);
	}

	$payload = array(
		'provider' => $provider,
		'model'    => $model,
		'messages' => array(
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		),
		'ai_params' => $ai_params,
	);

	if ( null !== $system ) {
		$payload['system_instruction'] = $system;
	}

	$response = wp_remote_post(
		get_rest_url( null, 'aipkit/v1/generate' ),
		array(
			'timeout' => 120,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $code || ! is_array( $body ) ) {
		$message = ( is_array( $body ) && isset( $body['message'] ) && is_string( $body['message'] ) )
			? $body['message']
			: __( 'Respons tidak valid dari AI Puffer.', 'dci-mcp-bridge' );

		return new WP_Error(
			'dci_aipkit_error',
			sprintf( 'HTTP %d: %s', $code, $message )
		);
	}

	return array(
		'content'  => isset( $body['content'] ) ? (string) $body['content'] : '',
		'provider' => isset( $body['provider'] ) ? (string) $body['provider'] : $provider,
		'model'    => isset( $body['model'] ) ? (string) $body['model'] : $model,
	);
}

/* ============================================================
 * BAGIAN 4D — INTEGRITAS KONTEN (WINSTON AI)
 * Kontrak API diverifikasi dari docs.gowinston.ai (v2, sinkron):
 * - POST /v2/ai-content-detection  {text, sentences, language} → {score
 *   (skala "manusia" 0-100; rendah = indikasi AI), sentences[],
 *   readability_score, credits_used/remaining} — min 300 karakter.
 * - POST /v2/plagiarism            {text, language, excluded_sources} →
 *   {result{score = persen plagiarisme}, sources[], credits} — min 100
 *   karakter, 2 kredit per kata.
 * Autentikasi: Bearer token. Bahasa Indonesia didukung (kode 'id').
 * ============================================================ */

/**
 * Ambil daftar API key Winston AI (ROTASI).
 *
 * Sumber (urutan prioritas):
 *  1. Konstanta DCI_WINSTON_API_KEYS di wp-config.php (pisahkan dengan koma/spasi/baris).
 *  2. Opsi 'winston_api_keys' (array) dari halaman DCI Bridge.
 *  3. Kunci tunggal lama 'winston_api_key' (migrasi otomatis).
 * Kunci dinormalisasi: dipangkas, kosong dibuang, duplikat digabung.
 *
 * @return string[] Daftar kunci unik.
 */
function dci_mcp_bridge_winston_keys() {
	$raw = array();

	if ( defined( 'DCI_WINSTON_API_KEYS' ) && is_string( DCI_WINSTON_API_KEYS ) && '' !== trim( DCI_WINSTON_API_KEYS ) ) {
		$raw = preg_split( '/[\s,]+/', trim( DCI_WINSTON_API_KEYS ) );
	} else {
		$opts = get_option( 'dci_mcp_bridge_options', array() );

		if ( isset( $opts['winston_api_keys'] ) && is_array( $opts['winston_api_keys'] ) ) {
			$raw = $opts['winston_api_keys'];
		} elseif ( isset( $opts['winston_api_key'] ) && is_string( $opts['winston_api_key'] ) && '' !== trim( $opts['winston_api_key'] ) ) {
			// Migrasi otomatis dari versi satu-kunci.
			$raw = array( $opts['winston_api_key'] );
		}
	}

	$clean = array();

	foreach ( (array) $raw as $key ) {
		$key = trim( (string) $key );

		if ( '' !== $key && ! in_array( $key, $clean, true ) ) {
			$clean[] = $key;
		}
	}

	return $clean;
}

/**
 * Satu panggilan HTTP mentah ke API Winston AI dengan satu kunci.
 * TIDAK mengurus rotasi — gunakan dci_mcp_bridge_winston_request_rotated().
 *
 * @param string $path    Path endpoint (mis. v2/plagiarism).
 * @param array  $body    Payload JSON.
 * @param string $key     Bearer token.
 * @param int    $timeout Batas waktu detik.
 * @return array|WP_Error Array {code:int, body:array} atau WP_Error jaringan.
 */
function dci_mcp_bridge_winston_http( $path, array $body, $key, $timeout ) {
	$response = wp_remote_post(
		'https://api.gowinston.ai/' . $path,
		array(
			'timeout' => $timeout,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code   = (int) wp_remote_retrieve_response_code( $response );
	$parsed = json_decode( wp_remote_retrieve_body( $response ), true );

	return array(
		'code' => $code,
		'body' => is_array( $parsed ) ? $parsed : array(),
	);
}

/**
 * Panggil API Winston AI dengan ROTASI kunci + FAILOVER otomatis.
 *
 * - Round-robin: indeks kunci diputar dan disimpan, beban tersebar merata.
 * - Failover: 401 (kunci ditolak), 402 (kredit habis), 429 (batas laju),
 *   dan error jaringan otomatis pindah ke kunci berikutnya.
 * - Cooldown: kunci yang gagal level-kunci diistirahatkan 30 menit agar
 *   tidak membuang waktu percobaan berulang.
 * - 400/415/5xx = kesalahan permintaan/server (bukan soal kunci) — langsung
 *   dilaporkan tanpa membakar kunci lain.
 *
 * @param string $path    Path endpoint (mis. v2/plagiarism).
 * @param array  $body    Payload JSON.
 * @param int    $timeout Batas waktu detik per percobaan.
 * @return array|WP_Error Array hasil decode (HTTP 200) atau kesalahan.
 */
function dci_mcp_bridge_winston_request_rotated( $path, array $body, $timeout ) {
	$keys = dci_mcp_bridge_winston_keys();

	if ( empty( $keys ) ) {
		return new WP_Error(
			'dci_winston_not_configured',
			__( 'API key Winston AI belum diisi. Tambahkan minimal satu kunci di menu DCI Bridge → Integritas Konten, atau konstanta DCI_WINSTON_API_KEYS di wp-config.php.', 'dci-mcp-bridge' )
		);
	}

	$count    = count( $keys );
	$idx      = (int) get_option( 'dci_mcp_bridge_rotation_index', 0 );
	$cooldown = get_option( 'dci_mcp_bridge_key_cooldown', array() );

	if ( ! is_array( $cooldown ) ) {
		$cooldown = array();
	}

	$now        = time();
	$tried      = 0;
	$last_code  = 0;
	$last_msg   = '';
	$network_err = null;

	for ( $i = 0; $i < $count; $i++ ) {
		$pos  = ( $idx + $i ) % $count;
		$key  = $keys[ $pos ];
		$hash = md5( $key );

		// Lewati kunci yang sedang diistirahatkan.
		if ( isset( $cooldown[ $hash ] ) && ( $now - (int) $cooldown[ $hash ] ) < 1800 ) {
			continue;
		}

		$attempt = dci_mcp_bridge_winston_http( $path, $body, $key, $timeout );
		$tried++;

		if ( is_wp_error( $attempt ) ) {
			// Gangguan jaringan ke Winston — coba kunci berikutnya.
			$network_err = $attempt;
			continue;
		}

		$code = $attempt['code'];

		if ( 200 === $code ) {
			// Sukses: maju ke kunci berikutnya untuk panggilan selanjutnya.
			update_option( 'dci_mcp_bridge_rotation_index', ( $pos + 1 ) % $count, false );

			if ( isset( $cooldown[ $hash ] ) ) {
				unset( $cooldown[ $hash ] );
				update_option( 'dci_mcp_bridge_key_cooldown', $cooldown, false );
			}

			return $attempt['body'];
		}

		if ( in_array( $code, array( 401, 402, 429 ), true ) ) {
			// Masalah level kunci: pendinginkan, lalu lanjut ke kunci berikutnya.
			$cooldown[ $hash ] = $now;
			update_option( 'dci_mcp_bridge_key_cooldown', $cooldown, false );
			$last_code = $code;
			continue;
		}

		// Kesalahan permintaan/server (400/415/500/503 dst.) — laporkan langsung.
		$last_code = $code;
		$last_msg  = ( isset( $attempt['body']['message'] ) && is_string( $attempt['body']['message'] ) )
			? $attempt['body']['message']
			: __( 'Respons tidak valid dari Winston AI.', 'dci-mcp-bridge' );

		return new WP_Error(
			'dci_winston_error',
			sprintf( 'HTTP %d: %s', $code, $last_msg )
		);
	}

	// Semua kunci yang layak dicoba sudah gagal.
	if ( 0 === $tried ) {
		return new WP_Error(
			'dci_winston_all_keys_failed',
			__( 'Semua API key Winston AI sedang dalam masa cooldown (maksimal 30 menit setelah kegagalan terakhir). Coba lagi beberapa saat lagi.', 'dci-mcp-bridge' )
		);
	}

	$hint = array(
		401 => __( 'kunci ditolak (periksa kunci di DCI Bridge)', 'dci-mcp-bridge' ),
		402 => __( 'kredit habis (isi ulang di dev.gowinston.ai)', 'dci-mcp-bridge' ),
		429 => __( 'batas laju (tunggu atau tambah kunci)', 'dci-mcp-bridge' ),
	);

	$detail = ( 0 !== $last_code && isset( $hint[ $last_code ] ) )
		? sprintf( 'Penyebab terakhir HTTP %d: %s.', $last_code, $hint[ $last_code ] )
		: ( null !== $network_err ? __( 'Gangguan jaringan ke Winston AI.', 'dci-mcp-bridge' ) : '' );

	if ( null !== $network_err && '' === $detail ) {
		return $network_err;
	}

	return new WP_Error(
		'dci_winston_all_keys_failed',
		sprintf(
			/* translators: 1: jumlah kunci dicoba, 2: jumlah kunci total, 3: penjelasan. */
			__( 'Semua API key Winston AI gagal (%1$d dari %2$d kunci dicoba). %3$s', 'dci-mcp-bridge' ),
			$tried,
			$count,
			$detail
		)
	);
}

/**
 * Eksekusi ability dci/check-originality.
 *
 * @param array $input Argumen ability.
 * @return array|WP_Error
 */
function dci_mcp_bridge_execute_check_originality( $input = array() ) {
	$input   = is_array( $input ) ? $input : array();
	$post_id = absint( $input['post_id'] ?? 0 );
	$post    = $post_id > 0 ? get_post( $post_id ) : null;

	if ( ! $post || 'post' !== $post->post_type ) {
		return new WP_Error(
			'dci_post_not_found',
			__( 'Post dengan ID tersebut tidak ditemukan (atau bukan post type "post").', 'dci-mcp-bridge' )
		);
	}

	$checks = isset( $input['checks'] ) && is_array( $input['checks'] )
		? array_values( array_intersect( array_map( 'strval', $input['checks'] ), array( 'ai_detect', 'plagiarism' ) ) )
		: array();
	if ( empty( $checks ) ) {
		$checks = array( 'ai_detect', 'plagiarism' );
	}

	$language = ( isset( $input['language'] ) && is_string( $input['language'] ) && preg_match( '/^[a-z]{2}$/', $input['language'] ) )
		? $input['language']
		: 'id';

	$keys = dci_mcp_bridge_winston_keys();

	if ( empty( $keys ) ) {
		return new WP_Error(
			'dci_winston_not_configured',
			__( 'API key Winston AI belum diisi. Tambahkan minimal satu kunci di menu DCI Bridge → Integritas Konten, atau konstanta DCI_WINSTON_API_KEYS di wp-config.php.', 'dci-mcp-bridge' )
		);
	}

	// Bersihkan HTML & entitas, rapatkan spasi — teks murni untuk dipindai.
	$text    = html_entity_decode( wp_strip_all_tags( (string) $post->post_content ), ENT_QUOTES, 'UTF-8' );
	$text    = trim( preg_replace( '/\s+/u', ' ', $text ) );
	$text_len = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );

	$out = array(
		'post_id'    => (int) $post->ID,
		'status'     => (string) $post->post_status,
		'ai_detect'  => null,
		'plagiarism' => null,
		'credits'    => array(
			'used'      => 0,
			'remaining' => null,
		),
		'notes'      => array(),
	);

	if ( $text_len < 100 ) {
		$out['notes'][] = __( 'Konten terlalu pendek (di bawah 100 karakter) untuk pemeriksaan mana pun.', 'dci-mcp-bridge' );

		return $out;
	}

	/* --- Deteksi AI (Winston v2, 1 kredit/kata) --- */
	if ( in_array( 'ai_detect', $checks, true ) ) {
		if ( $text_len < 300 ) {
			$out['notes'][] = __( 'Konten di bawah 300 karakter — deteksi AI dilewati (batas minimum Winston).', 'dci-mcp-bridge' );
		} else {
			$resp = dci_mcp_bridge_winston_request_rotated(
				'v2/ai-content-detection',
				array(
					'text'      => $text,
					'sentences' => true,
					'language'  => $language,
				),
				60
			);

			if ( is_wp_error( $resp ) ) {
				return $resp;
			}

			$sentences_flagged = array();

			if ( isset( $resp['sentences'] ) && is_array( $resp['sentences'] ) ) {
				foreach ( $resp['sentences'] as $sentence ) {
					if ( ! is_array( $sentence ) || ! isset( $sentence['score'] ) ) {
						continue;
					}

					$score = (int) $sentence['score'];
					$body  = isset( $sentence['text'] ) ? trim( (string) $sentence['text'] ) : '';

					if ( $score < 20 && '' !== $body ) {
						$sentences_flagged[] = array(
							'score' => $score,
							'text'  => function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 160 ) : substr( $body, 0, 160 ),
						);
					}

					if ( count( $sentences_flagged ) >= 5 ) {
						break;
					}
				}
			}

			$human = isset( $resp['score'] ) ? (int) $resp['score'] : null;

			if ( null === $human ) {
				$verdict = '';
			} elseif ( $human >= 60 ) {
				$verdict = __( 'Sangat mungkin tulisan manusia', 'dci-mcp-bridge' );
			} elseif ( $human >= 40 ) {
				$verdict = __( 'Zona abu-abu / campuran', 'dci-mcp-bridge' );
			} elseif ( $human >= 20 ) {
				$verdict = __( 'Indikasi kuat AI', 'dci-mcp-bridge' );
			} else {
				$verdict = __( 'Sangat mungkin AI', 'dci-mcp-bridge' );
			}

			$out['ai_detect'] = array(
				'human_score'       => $human,
				'verdict'           => $verdict,
				'readability_score' => isset( $resp['readability_score'] ) ? (int) $resp['readability_score'] : null,
				'sentences_flagged' => $sentences_flagged,
			);

			$out['credits']['used']      += isset( $resp['credits_used'] ) ? (int) $resp['credits_used'] : 0;
			$out['credits']['remaining']  = isset( $resp['credits_remaining'] ) ? (int) $resp['credits_remaining'] : null;

			if ( $text_len < 600 ) {
				$out['notes'][] = __( 'Teks di bawah 600 karakter — skor AI kurang andal menurut Winston.', 'dci-mcp-bridge' );
			}
		}
	}

	/* --- Plagiarisme (Winston v2, 2 kredit/kata) --- */
	if ( in_array( 'plagiarism', $checks, true ) ) {
		$pl_body = array( 'text' => $text, 'language' => $language );

		if ( ! empty( $input['excluded_sources'] ) && is_array( $input['excluded_sources'] ) ) {
			$excluded = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', $input['excluded_sources'] ) ) ) );

			if ( ! empty( $excluded ) ) {
				$pl_body['excluded_sources'] = $excluded;
			}
		}

		$resp = dci_mcp_bridge_winston_request_rotated( 'v2/plagiarism', $pl_body, 120 );

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$result  = ( isset( $resp['result'] ) && is_array( $resp['result'] ) ) ? $resp['result'] : array();
		$sources = array();

		if ( isset( $resp['sources'] ) && is_array( $resp['sources'] ) ) {
			foreach ( $resp['sources'] as $src ) {
				if ( ! is_array( $src ) ) {
					continue;
				}

				$sources[] = array(
					'url'   => isset( $src['url'] ) ? (string) $src['url'] : '',
					'title' => isset( $src['title'] ) ? (string) $src['title'] : '',
					'score' => isset( $src['score'] ) ? (int) $src['score'] : 0,
				);

				if ( count( $sources ) >= 5 ) {
					break;
				}
			}

			usort( $sources, function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			} );
		}

		$out['plagiarism'] = array(
			'score_percent' => isset( $result['score'] ) ? (int) $result['score'] : null,
			'total_words'   => isset( $result['textWordCounts'] ) ? (int) $result['textWordCounts'] : null,
			'top_sources'   => $sources,
		);

		$out['credits']['used']      += isset( $resp['credits_used'] ) ? (int) $resp['credits_used'] : 0;
		$out['credits']['remaining']  = isset( $resp['credits_remaining'] ) ? (int) $resp['credits_remaining'] : null;
	}

	// Kejujuran metodologis: detektor adalah sinyal, bukan vonis.
	$out['notes'][] = __( 'Detektor AI bersifat heuristik — positif palsu mungkin terjadi (terutama bahasa non-Inggris). Gunakan bersama audit on-page dan tinjauan manusia sebagai keputusan akhir.', 'dci-mcp-bridge' );

	return $out;
}

/* ============================================================
 * BAGIAN 6 — HALAMAN ADMIN "DCI BRIDGE" (TAMPILAN PROFESIONAL)
 *
 * Menu wp-admin khusus: status kesehatan integrasi, daftar
 * kemampuan terdaftar, konfigurasi keamanan aktif, dan panduan
 * koneksi untuk AI agent. Hanya untuk Administrator.
 * ============================================================ */

/**
 * Daftarkan menu admin.
 */
function dci_mcp_bridge_admin_menu() {
	add_menu_page(
		__( 'DCI Bridge', 'dci-mcp-bridge' ),
		__( 'DCI Bridge', 'dci-mcp-bridge' ),
		'manage_options',
		'dci-mcp-bridge',
		'dci_mcp_bridge_render_admin_page',
		'dashicons-rest-api',
		59
	);
}
add_action( 'admin_menu', 'dci_mcp_bridge_admin_menu' );

/**
 * Notifikasi error bila dependensi MCP Adapter tidak aktif.
 */
function dci_mcp_bridge_dependency_notice() {
	if ( current_user_can( 'manage_options' ) && ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
		echo '<div class="notice notice-error"><p><strong>DCI MCP Bridge:</strong> ';
		esc_html_e( 'Plugin MCP Adapter belum aktif. Aktifkan terlebih dahulu agar kemampuan DCI dapat dipakai AI agent.', 'dci-mcp-bridge' );
		echo '</p></div>';
	}
}
add_action( 'admin_notices', 'dci_mcp_bridge_dependency_notice' );

/**
 * Notifikasi selamat datang sekali saja, mengarahkan ke halaman DCI Bridge.
 */
function dci_mcp_bridge_welcome_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Penanganan tombol tutup (dengan nonce).
	if ( isset( $_GET['dci_bridge_dismiss'], $_GET['_wpnonce'] )
		&& wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'dci_bridge_dismiss' ) ) {
		update_option( 'dci_mcp_bridge_welcome_done', '1' );
		return;
	}

	if ( get_option( 'dci_mcp_bridge_welcome_done' ) ) {
		return;
	}

	$dismiss = wp_nonce_url( add_query_arg( 'dci_bridge_dismiss', '1' ), 'dci_bridge_dismiss' );
	$page    = admin_url( 'admin.php?page=dci-mcp-bridge' );

	echo '<div class="notice notice-info is-dismissible dci-bridge-welcome"><p>';
	printf(
		/* translators: 1: tautan halaman, 2: versi plugin. */
		wp_kses_post( __( 'Plugin <strong>DCI MCP Bridge v%2$s</strong> aktif. Buka <a href="%1$s">halaman DCI Bridge</a> untuk melihat status integrasi dan cara menghubungkan AI agent.', 'dci-mcp-bridge' ) ),
		esc_url( $page ),
		esc_html( DCI_MCP_BRIDGE_VERSION )
	);
	echo ' <a href="' . esc_url( $dismiss ) . '" style="text-decoration:none;">&#x2715;</a></p></div>';
}
add_action( 'admin_notices', 'dci_mcp_bridge_welcome_notice' );

/**
 * Simpan API key Winston AI dari halaman DCI Bridge (admin-only, ber-nonce).
 */
function dci_mcp_bridge_handle_save_key() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Anda tidak berwenang melakukan ini.', 'dci-mcp-bridge' ) );
	}

	check_admin_referer( 'dci_bridge_save_key' );

	// Terima beberapa kunci sekaligus: dipisah baris baru atau koma.
	$raw_keys = isset( $_POST['dci_winston_api_keys'] ) ? wp_unslash( $_POST['dci_winston_api_keys'] ) : '';
	$raw_keys = is_string( $raw_keys ) ? $raw_keys : '';
	$candidates = preg_split( '/\r\n|\r|\n|,/', $raw_keys );
	$keys = array();

	foreach ( (array) $candidates as $candidate ) {
		$candidate = trim( sanitize_text_field( (string) $candidate ) );

		if ( '' !== $candidate && ! in_array( $candidate, $keys, true ) ) {
			$keys[] = $candidate;
		}
	}

	$opts = get_option( 'dci_mcp_bridge_options', array() );

	if ( ! is_array( $opts ) ) {
		$opts = array();
	}

	if ( ! empty( $keys ) ) {
		$opts['winston_api_keys'] = $keys;
	} else {
		// Kolom dikosongkan = hapus semua kunci (termasuk sisa kunci tunggal lama).
		unset( $opts['winston_api_keys'], $opts['winston_api_key'] );
	}

	update_option( 'dci_mcp_bridge_options', $opts );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'      => 'dci-mcp-bridge',
				'dci-saved' => '1',
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_dci_bridge_save_key', 'dci_mcp_bridge_handle_save_key' );

/**
 * Status tiga dependensi: MCP Adapter, AI Puffer (Public API), Rank Math.
 *
 * @return array Array status per komponen.
 */
function dci_mcp_bridge_env_status() {
	// MCP Adapter: nama kelas inti diverifikasi dari dokumentasi resminya.
	$mcp_active = class_exists( '\WP\MCP\Core\McpAdapter' );

	// AI Puffer: deteksi via kelas internal + jumlah provider terisi.
	$aip_installed = class_exists( 'AIPKit_AI_Caller' );
	$aip_state     = 'nonaktif';
	$aip_detail    = __( 'Plugin AI Puffer tidak terdeteksi — kemampuan generate teks tidak akan berfungsi.', 'dci-mcp-bridge' );

	if ( $aip_installed ) {
		$aip = dci_mcp_bridge_aip_status();

		if ( ! empty( $aip['providers_configured'] ) ) {
			$aip_state  = 'aktif';
			$aip_detail = sprintf(
				/* translators: 1: daftar nama provider. */
				__( 'Mesin AI siap (panggilan internal, tanpa HTTP). Provider terisi: %1$s. Public API: %2$s.', 'dci-mcp-bridge' ),
				implode( ', ', $aip['providers_configured'] ),
				$aip['public_api_enabled'] ? __( 'aktif', 'dci-mcp-bridge' ) : __( 'opsional (belum aktif)', 'dci-mcp-bridge' )
			);
		} else {
			$aip_state  = 'perlu-konfigurasi';
			$aip_detail = __( 'AI Puffer terpasang, tetapi belum ada API key provider yang diisi (AIPKit → Settings). Isi minimal satu provider agar generate teks berfungsi.', 'dci-mcp-bridge' );
		}
	}

	// Rank Math.
	$rm_active = defined( 'RANK_MATH_VERSION' ) || class_exists( '\RankMath' );

	return array(
		array(
			'nama'   => __( 'MCP Adapter', 'dci-mcp-bridge' ),
			'state'  => $mcp_active ? 'aktif' : 'nonaktif',
			'detail' => $mcp_active
				? __( 'Gerbang MCP aktif — AI agent dapat terhubung.', 'dci-mcp-bridge' )
				: __( 'Plugin MCP Adapter belum aktif (dependensi wajib).', 'dci-mcp-bridge' ),
		),
		array(
			'nama'   => __( 'AI Puffer (Public API)', 'dci-mcp-bridge' ),
			'state'  => $aip_state,
			'detail' => $aip_detail,
		),
		array(
			'nama'   => __( 'Rank Math SEO', 'dci-mcp-bridge' ),
			'state'  => $rm_active ? 'aktif' : 'nonaktif',
			'detail' => $rm_active
				? __( 'Field SEO Rank Math dapat dibaca/diisi (meta, OG, robots).', 'dci-mcp-bridge' )
				: __( 'Tidak terdeteksi — kemampuan meta Rank Math tidak akan berfungsi.', 'dci-mcp-bridge' ),
		),
	);
}

/**
 * Daftar kemampuan DCI untuk tabel halaman admin (label statis + status registrasi dinamis).
 *
 * @return array
 */
function dci_mcp_bridge_ability_table() {
	return array(
		array( 'name' => 'dci/generate-text', 'label' => __( 'Generate Text (AI Puffer)', 'dci-mcp-bridge' ), 'sifat' => __( 'Baca — memakai kredit AI', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/create-draft-post', 'label' => __( 'Create Draft Post', 'dci-mcp-bridge' ), 'sifat' => __( 'Tulis — selalu draf', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/update-draft-post', 'label' => __( 'Update Draft Post', 'dci-mcp-bridge' ), 'sifat' => __( 'Tulis — khusus draf', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/set-post-seo-meta', 'label' => __( 'Set Post SEO Meta (Rank Math)', 'dci-mcp-bridge' ), 'sifat' => __( 'Tulis — khusus draf', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/audit-article', 'label' => __( 'Audit Article (SEO On-Page)', 'dci-mcp-bridge' ), 'sifat' => __( 'Baca', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/publish-post', 'label' => __( 'Publish Draft Post', 'dci-mcp-bridge' ), 'sifat' => __( 'Tulis — aksi eksplisit', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/get-seo-config', 'label' => __( 'Get Rank Math SEO Config', 'dci-mcp-bridge' ), 'sifat' => __( 'Baca', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/check-originality', 'label' => __( 'Check Originality (AI + Plagiarism)', 'dci-mcp-bridge' ), 'sifat' => __( 'Baca — berbiaya kredit', 'dci-mcp-bridge' ) ),
	);
}

/**
 * Render halaman admin DCI Bridge.
 */
function dci_mcp_bridge_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Anda tidak berwenang mengakses halaman ini.', 'dci-mcp-bridge' ) );
	}

	$statuses     = dci_mcp_bridge_env_status();
	$abilities    = dci_mcp_bridge_ability_table();
	$min_cap      = ( defined( 'DCI_MCP_MIN_CAPABILITY' ) && is_string( DCI_MCP_MIN_CAPABILITY ) && '' !== DCI_MCP_MIN_CAPABILITY )
		? DCI_MCP_MIN_CAPABILITY
		: 'edit_posts';
	$endpoint_url = rest_url( 'mcp/mcp-adapter-default-server' );
	$saved_notice = isset( $_GET['dci-saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['dci-saved'] ) );
	$stored_keys  = dci_mcp_bridge_winston_keys();
	$key_count    = count( $stored_keys );
	$masked_list  = array();

	foreach ( $stored_keys as $stored_key ) {
		$masked_list[] = '••••' . substr( $stored_key, -4 );
	}

	$state_label = array(
		'aktif'             => __( 'AKTIF', 'dci-mcp-bridge' ),
		'perlu-konfigurasi' => __( 'PERLU KONFIGURASI', 'dci-mcp-bridge' ),
		'nonaktif'          => __( 'NONAKTIF', 'dci-mcp-bridge' ),
	);
	?>
	<div class="wrap dci-bridge-page">
		<style>
			.dci-bridge-page { max-width: 920px; }
			.dci-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 20px 24px; margin: 16px 0; }
			.dci-card h2 { margin-top: 0; display: flex; align-items: center; gap: 8px; }
			.dci-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
			.dci-state { display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: .04em; padding: 3px 10px; border-radius: 999px; }
			.dci-state-aktif { background: #e6f2e8; color: #116329; border: 1px solid #68de7c; }
			.dci-state-perlu-konfigurasi { background: #fcf9e8; color: #996800; border: 1px solid #f0c33c; }
			.dci-state-nonaktif { background: #fce8e8; color: #8a2424; border: 1px solid #ffabaf; }
			.dci-bridge-page table { width: 100%; border-collapse: collapse; }
			.dci-bridge-page th, .dci-bridge-page td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #f0f0f1; vertical-align: top; }
			.dci-bridge-page th { background: #f6f7f7; font-weight: 600; }
			.dci-badge-ok { color: #116329; font-weight: 600; }
			.dci-badge-no { color: #8a2424; font-weight: 600; }
			.dci-code { font-family: Consolas, Menlo, monospace; background: #f6f7f7; padding: 2px 6px; border-radius: 4px; word-break: break-all; }
			.dci-footer { color: #646970; font-size: 12.5px; margin-top: 24px; }
		</style>

		<h1 class="dci-title">
			<span class="dashicons dashicons-rest-api" style="font-size:28px;margin-right:6px;"></span>
			<?php esc_html_e( 'DCI MCP Bridge', 'dci-mcp-bridge' ); ?>
			<span style="font-size:13px;font-weight:400;color:#646970;">v<?php echo esc_html( DCI_MCP_BRIDGE_VERSION ); ?></span>
		</h1>
		<p class="dci-sub"><?php esc_html_e( 'Jembatan AI profesional untuk WordPress — produksi konten SEO end-to-end: riset, tulis, audit, set meta Rank Math, lalu terbit atau simpan sebagai draf.', 'dci-mcp-bridge' ); ?></p>

		<div class="dci-card">
			<h2><span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Status Integrasi', 'dci-mcp-bridge' ); ?></h2>
			<div class="dci-grid">
				<?php foreach ( $statuses as $status ) : ?>
					<div>
						<strong><?php echo esc_html( $status['nama'] ); ?></strong>
						<?php if ( isset( $state_label[ $status['state'] ] ) ) : ?>
							<span class="dci-state dci-state-<?php echo esc_attr( $status['state'] ); ?>"><?php echo esc_html( $state_label[ $status['state'] ] ); ?></span>
						<?php endif; ?>
						<p style="margin:6px 0 0;color:#50575e;"><?php echo esc_html( $status['detail'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="dci-card">
			<h2><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Konfigurasi Keamanan Aktif', 'dci-mcp-bridge' ); ?></h2>
			<p style="margin:0;">
				<?php
				printf(
					/* translators: %s: nama kapabilitas WordPress. */
					esc_html__( 'Gerbang HTTP MCP dibatasi untuk pengguna dengan kapabilitas %s (default: edit_posts — Editor dan Administrator).', 'dci-mcp-bridge' ),
					'<code class="dci-code">' . esc_html( $min_cap ) . '</code>'
				);
				?>
			</p>
			<p style="margin:8px 0 0;color:#50575e;">
				<?php esc_html_e( 'Untuk memperketat ke Administrator saja, tambahkan pada wp-config.php:', 'dci-mcp-bridge' ); ?>
				<code class="dci-code">define( 'DCI_MCP_MIN_CAPABILITY', 'manage_options' );</code>
			</p>
		</div>

		<div class="dci-card">
			<h2><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Kemampuan (Abilities) DCI', 'dci-mcp-bridge' ); ?></h2>
			<table>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Nama Ability', 'dci-mcp-bridge' ); ?></th>
						<th><?php esc_html_e( 'Label', 'dci-mcp-bridge' ); ?></th>
						<th><?php esc_html_e( 'Sifat', 'dci-mcp-bridge' ); ?></th>
						<th><?php esc_html_e( 'Status', 'dci-mcp-bridge' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $abilities as $ability ) : ?>
						<?php $registered = function_exists( 'wp_has_ability' ) && wp_has_ability( $ability['name'] ); ?>
						<tr>
							<td><code class="dci-code"><?php echo esc_html( $ability['name'] ); ?></code></td>
							<td><?php echo esc_html( $ability['label'] ); ?></td>
							<td><?php echo esc_html( $ability['sifat'] ); ?></td>
							<td>
								<?php if ( $registered ) : ?>
									<span class="dci-badge-ok">&#10003; <?php esc_html_e( 'Terdaftar', 'dci-mcp-bridge' ); ?></span>
								<?php else : ?>
									<span class="dci-badge-no">&#10007; <?php esc_html_e( 'Belum (butuh WP 6.9+ & MCP Adapter aktif)', 'dci-mcp-bridge' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="dci-card">
			<h2><span class="dashicons dashicons-shield-alt"></span> <?php esc_html_e( 'Integritas Konten (AI Detector + Plagiarism)', 'dci-mcp-bridge' ); ?></h2>
			<?php if ( $saved_notice ) : ?>
				<div class="notice notice-success inline" style="margin:0 0 12px;"><p><?php esc_html_e( 'API key berhasil disimpan.', 'dci-mcp-bridge' ); ?></p></div>
			<?php endif; ?>
			<p style="margin-top:0;">
				<?php esc_html_e( 'Kemampuan dci/check-originality memeriksa artikel lewat Winston AI sebelum final: skor "kemiripan manusia" (deteksi AI, mendukung Bahasa Indonesia) dan persentase plagiarisme beserta sumbernya.', 'dci-mcp-bridge' ); ?>
				<strong><?php esc_html_e( 'Berbiaya kredit per kata', 'dci-mcp-bridge' ); ?></strong>
				<?php esc_html_e( ' (AI: 1 kredit/kata, plagiarisme: 2 kredit/kata) — karena itu dipanggil eksplisit menjelang final, bukan otomatis di setiap audit.', 'dci-mcp-bridge' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="dci_bridge_save_key" />
				<?php wp_nonce_field( 'dci_bridge_save_key' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dci_winston_api_keys"><?php esc_html_e( 'API Key Winston AI', 'dci-mcp-bridge' ); ?></label></th>
						<td>
							<?php if ( $key_count > 0 ) : ?>
								<p style="margin-top:0;">
									<span class="dci-state dci-state-aktif"><?php
										printf(
											/* translators: %s: jumlah kunci. */
											esc_html( _n( '%s KUNCI AKTIF', '%s KUNCI AKTIF', $key_count, 'dci-mcp-bridge' ) ),
											esc_html( number_format_i18n( $key_count ) )
										);
									?></span>
									<code class="dci-code"><?php echo esc_html( implode( ', ', $masked_list ) ); ?></code>
									— <?php esc_html_e( 'rotasi otomatis + failover aktif.', 'dci-mcp-bridge' ); ?>
								</p>
							<?php endif; ?>
							<textarea id="dci_winston_api_keys" name="dci_winston_api_keys" rows="4" class="large-text code" placeholder="<?php esc_attr_e( 'Tempel satu API key per baris (atau pisahkan dengan koma). Contoh:' . "\n" . 'winstondUL8xxxxxxxxxxxx' . "\n" . 'winstonKq2Mxxxxxxxxxxxx', 'dci-mcp-bridge' ); ?>" autocomplete="off"></textarea>
							<p class="description">
								<?php esc_html_e( 'Dari dev.gowinston.ai. Semua kunci akan diputar bergantian (round-robin); bila satu kunci ditolak/habis kredit/kena batas laju, otomatis pindah ke kunci berikutnya dan kunci tersebut diistirahatkan 30 menit. Kosongkan + Simpan untuk menghapus semua. Kunci tidak pernah dikirim ke AI agent.', 'dci-mcp-bridge' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Simpan API Key', 'dci-mcp-bridge' ), 'primary', 'submit', true ); ?>
			</form>
		</div>

		<div class="dci-card">
			<h2><span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Menghubungkan AI Agent', 'dci-mcp-bridge' ); ?></h2>
			<p style="margin-top:0;"><?php esc_html_e( 'Endpoint MCP server (protokol streamable HTTP, revisi 2025-11-25 / 2026-07-28):', 'dci-mcp-bridge' ); ?></p>
			<p><code class="dci-code"><?php echo esc_url( $endpoint_url ); ?></code></p>
			<ol style="margin-bottom:0;">
				<li><?php esc_html_e( 'Siapkan user WordPress khusus untuk AI (disarankan peran Editor, bukan Administrator).', 'dci-mcp-bridge' ); ?></li>
				<li><?php esc_html_e( 'Buat Application Password: Users → Profile → Application Passwords, lalu simpan dengan aman.', 'dci-mcp-bridge' ); ?></li>
				<li><?php esc_html_e( 'Arahkan MCP client AI ke endpoint di atas dengan autentikasi Basic (user + application password).', 'dci-mcp-bridge' ); ?></li>
				<li><?php esc_html_e( 'AI agent dapat menemukan semua kemampuan lewat meta-tool discover-abilities milik MCP Adapter.', 'dci-mcp-bridge' ); ?></li>
			</ol>
		</div>

		<p class="dci-footer">
			<?php
			printf(
				/* translators: %s: versi plugin. */
				esc_html__( 'DCI MCP Bridge %s — dikembangkan oleh Mas Wondho, Duta Corpora Indonesia. Semua aksi tulis terkendali: pembuatan selalu menjadi draf; penerbitan adalah aksi eksplisit terpisah.', 'dci-mcp-bridge' ),
				esc_html( DCI_MCP_BRIDGE_VERSION )
			);
			?>
		</p>
	</div>
	<?php
}
