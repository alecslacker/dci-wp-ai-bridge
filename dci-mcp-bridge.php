<?php
/**
 * Plugin Name:       DCI MCP Bridge
 * Description:       Hardening gerbang MCP Adapter + mengekspos kemampuan konten (AI Puffer) sebagai Abilities agar dapat dipakai AI agent. Bagian dari standar operasional Duta Corpora Indonesia.
 * Version:           2.2.1
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

define( 'DCI_MCP_BRIDGE_VERSION', '2.2.1' );

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
 * Suntikkan identitas situs ke server MCP default.
 *
 * Field "instructions" pada handshake MCP diambil dari server_description
 * (InitializeHandler.php milik MCP Adapter), sehingga setiap AI client yang
 * terhubung otomatis tahu situs mana yang dipegang — termasuk domain resmi
 * yang tidak boleh diganti (mis. .co.id ≠ .com).
 *
 * @param array $config Konfigurasi default server dari MCP Adapter.
 * @return array
 */
function dci_mcp_bridge_server_identity( $config ) {
	if ( ! is_array( $config ) ) {
		$config = array();
	}

	$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$site_url  = home_url();
	$site_host = (string) wp_parse_url( $site_url, PHP_URL_HOST );

	$config['server_name']        = $site_name . ' — WordPress MCP';
	$config['server_description'] = sprintf(
		'All abilities on this MCP server operate on the WordPress site "%1$s" (%2$s). The official domain is %3$s — always use this exact domain when referencing or verifying this site; NEVER substitute it (e.g. do not change .co.id to .com or guess similar domains). To read this site\'s own content (posts/pages), use dci/search-content and dci/get-content instead of web browsing.',
		$site_name,
		$site_url,
		$site_host
	);

	return $config;
}
add_filter( 'mcp_adapter_default_server_config', 'dci_mcp_bridge_server_identity' );

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
			'description' => __( "Update an existing DRAFT post's title, body, and/or excerpt. Works ONLY on drafts (status draft/pending/auto-draft); published posts are rejected. content_html is a FULL replacement: first fetch content_html via dci/get-content, edit only the target part, then send back the complete document (preserve block markup). Typical flow: audit with dci/audit-article, generate improved content with dci/generate-text, then apply it here.", 'dci-mcp-bridge' ),
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
			'description' => __( "Write Rank Math SEO fields on a post or page: meta_title (ideal 40-60 characters), meta_description (ideal 120-160 characters, include the focus keyword naturally), and focus_keyword. Works on DRAFTS by default; pass allow_published=true to also target PUBLISHED posts AND pages (meta only — content is untouched). Meta keys used: rank_math_title, rank_math_description, rank_math_focus_keyword.", 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'          => array(
						'type'        => 'integer',
						'description' => __( 'ID of the draft post.', 'dci-mcp-bridge' ),
					),
					'allow_published'  => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Optional. Set true to also allow targeting a PUBLISHED post (meta only).', 'dci-mcp-bridge' ),
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

	/* --------------------------------------------------------
	 * Ability 9: dci/search-content
	 * Mencari/mendaftar artikel & laman milik situs ini.
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/search-content',
		array(
			'label'       => __( 'Search Site Content', 'dci-mcp-bridge' ),
			'description' => __( 'Search or list THIS site\'s posts and pages (not the web). Leave "query" empty to list the newest content. Use this — not web browsing — whenever you need to inspect, verify, or cite the site\'s own articles and pages. Returns id, type, status, title, url, date, and a short excerpt per item; then fetch full text with dci/get-content.', 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'query'      => array(
						'type'        => 'string',
						'description' => __( 'Optional. Search keyword; empty = list newest.', 'dci-mcp-bridge' ),
					),
					'post_types' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'string' ),
						'default' => array( 'post', 'page' ),
						'description' => __( 'Optional. Post types to search (default: post, page).', 'dci-mcp-bridge' ),
					),
					'per_page'   => array(
						'type'        => 'integer',
						'default'     => 20,
						'description' => __( 'Optional. Results per page (1-50).', 'dci-mcp-bridge' ),
					),
					'page'       => array(
						'type'        => 'integer',
						'default'     => 1,
						'description' => __( 'Optional. Page number for pagination.', 'dci-mcp-bridge' ),
					),
				),
			),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array(
					'query' => array( 'type' => 'string' ),
					'page'  => array( 'type' => 'integer' ),
					'items' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				),
				'required'   => array( 'items' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_search_content',
			'permission_callback' => 'dci_mcp_bridge_permission_read',
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
	 * Ability 10: dci/get-content
	 * Mengambil isi penuh satu artikel/laman milik situs ini.
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/get-content',
		array(
			'label'       => __( 'Get Site Content', 'dci-mcp-bridge' ),
			'description' => __( "Fetch ONE post/page of THIS site by post_id or by its URL (must be on this site). Returns title, url, status, date, plus BOTH content_text (plain text) and content_html (the raw stored HTML including WordPress block markup). For read-modify-write with dci/update-draft-post or dci/update-published-post: take content_html as the base, edit ONLY the target part, and send back the COMPLETE html — preserve block comments and everything you did not intend to change. Do NOT use the REST API for this purpose.", 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'Post ID on this site.', 'dci-mcp-bridge' ),
					),
					'url'     => array(
						'type'        => 'string',
						'description' => __( 'Optional. URL of the post/page on this site (alternative to post_id).', 'dci-mcp-bridge' ),
					),
				),
			),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'   => array( 'type' => 'integer' ),
					'type'      => array( 'type' => 'string' ),
					'status'    => array( 'type' => 'string' ),
					'title'     => array( 'type' => 'string' ),
					'url'       => array( 'type' => 'string' ),
					'date'      => array( 'type' => 'string' ),
					'content_text' => array( 'type' => 'string' ),
					'content_html' => array( 'type' => 'string' ),
				),
				'required'   => array( 'post_id', 'title', 'content_text', 'content_html' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_get_content',
			'permission_callback' => 'dci_mcp_bridge_permission_read',
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
	 * Ability 11: dci/update-published-post
	 * Memperbaiki artikel yang SUDAH TERBIT — perubahan langsung
	 * live, tapi snapshot revisi WordPress dibuat otomatis lebih dulu
	 * sebagai titik pemulihan.
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/update-published-post',
		array(
			'label'       => __( 'Update Published Post', 'dci-mcp-bridge' ),
			'description' => __( "Update a PUBLISHED post's title/body/excerpt. Changes go LIVE immediately, but a WordPress revision snapshot is saved first as a restore point (requires the edit_published_posts capability). For drafts, use dci/update-draft-post instead. content_html is a FULL replacement: fetch content_html via dci/get-content FIRST, edit only the target part, then send back the complete document (preserve block markup) — never rewrite the article from scratch. Change one section at a time and verify after each.", 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'      => array(
						'type'        => 'integer',
						'description' => __( 'ID of the published post.', 'dci-mcp-bridge' ),
					),
					'title'        => array(
						'type'        => 'string',
						'description' => __( 'Optional. New title (plain text).', 'dci-mcp-bridge' ),
					),
					'content_html' => array(
						'type'        => 'string',
						'description' => __( 'Optional. Replacement body as safe HTML (full replacement, not a patch).', 'dci-mcp-bridge' ),
					),
					'excerpt'      => array(
						'type'        => 'string',
						'description' => __( 'Optional. New excerpt.', 'dci-mcp-bridge' ),
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
					'revision_saved' => array( 'type' => 'boolean' ),
					'link'         => array( 'type' => 'string' ),
					'edit_link'    => array( 'type' => 'string' ),
				),
				'required'   => array( 'post_id', 'status', 'updated' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_update_published_post',
			'permission_callback' => 'dci_mcp_bridge_permission_edit_published',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,   // Mengubah konten yang sedang live.
					'idempotent'  => true,
				),
				'public'      => true,
				'mcp'         => array( 'public' => true ),
			),
		)
	);

	/* --------------------------------------------------------
	 * Ability 12: dci/site-report — laporan operasional situs.
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/site-report',
		array(
			'label'       => __( 'Site Report (Ops Agensi)', 'dci-mcp-bridge' ),
			'description' => __( 'Read-only operational snapshot of THIS site in one call: WordPress & PHP version, active theme, permalink structure, cron health, plugin inventory (total/active/how many have pending updates, with the pending list), and content counts (posts/pages/media). Plugin-update data reflects WordPress\'s own last scheduled check (transient update_plugins) — no live API ping. Use it for agency-style monitoring across client sites.', 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array( 'type' => 'object', 'properties' => array() ),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array(
					'site'    => array( 'type' => 'object' ),
					'theme'   => array( 'type' => 'object' ),
					'cron'    => array( 'type' => 'object' ),
					'plugins' => array( 'type' => 'object' ),
					'counts'  => array( 'type' => 'object' ),
				),
				'required'   => array( 'site', 'plugins' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_site_report',
			'permission_callback' => 'dci_mcp_bridge_permission_read',
			'meta'                => array(
				'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				'public'      => true,
				'mcp'         => array( 'public' => true ),
			),
		)
	);

	/* --------------------------------------------------------
	 * Ability 13: dci/bulk-audit — audit banyak artikel sekaligus.
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/bulk-audit',
		array(
			'label'       => __( 'Bulk Audit Articles', 'dci-mcp-bridge' ),
			'description' => __( 'Run the dci/audit-article checks on MANY posts in ONE call (default: 10 newest published; set status/count for other slices, e.g. drafts). Returns per-post summary (pass/warn/fail + failed check labels) and a ranked list starting with the worst posts. Cheaper and faster than auditing post-by-post via MCP.', 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'count'  => array( 'type' => 'integer', 'default' => 10, 'description' => __( 'Optional. How many posts (1-50).' ) ),
					'status' => array( 'type' => 'string', 'default' => 'publish', 'enum' => array( 'publish', 'draft' ), 'description' => __( 'Optional. post_status slice.' ) ),
				),
			),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array(
					'total'   => array( 'type' => 'integer' ),
					'summary' => array( 'type' => 'object' ),
					'items'   => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				),
				'required'   => array( 'total', 'items' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_bulk_audit',
			'permission_callback' => 'dci_mcp_bridge_permission_read',
			'meta'                => array(
				'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				'public'      => true,
				'mcp'         => array( 'public' => true ),
			),
		)
	);

	/* --------------------------------------------------------
	 * Ability 14: dci/list-media — inventaris media (gambar) + alt.
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/list-media',
		array(
			'label'       => __( 'List Media (Images)', 'dci-mcp-bridge' ),
			'description' => __( 'List this site\'s media library (default: images only, newest first) with id, title, URL, ALT TEXT, attached post id, and date — paginated. Use it to find images with empty alt text (SEO gap) before fixing them with dci/set-media-alt.', 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'per_page' => array( 'type' => 'integer', 'default' => 20, 'description' => __( 'Optional. 1-50.' ) ),
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
				),
			),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array( 'items' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ), 'page' => array( 'type' => 'integer' ) ),
				'required'   => array( 'items' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_list_media',
			'permission_callback' => 'dci_mcp_bridge_permission_read',
			'meta'                => array(
				'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				'public'      => true,
				'mcp'         => array( 'public' => true ),
			),
		)
	);

	/* --------------------------------------------------------
	 * Ability 15: dci/set-media-alt — perbaiki alt text gambar.
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/set-media-alt',
		array(
			'label'       => __( 'Set Media Alt Text', 'dci-mcp-bridge' ),
			'description' => __( 'Set the ALT TEXT of a media library image (attachment_id) to a descriptive value. Pairs with dci/list-media to close the empty-alt SEO gap. Plain text, no HTML.', 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'attachment_id' => array( 'type' => 'integer' ),
					'alt'           => array( 'type' => 'string', 'description' => __( 'Descriptive alt text (plain text).' ) ),
				),
				'required'   => array( 'attachment_id', 'alt' ),
			),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array( 'attachment_id' => array( 'type' => 'integer' ), 'alt' => array( 'type' => 'string' ) ),
				'required'   => array( 'attachment_id', 'alt' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_set_media_alt',
			'permission_callback' => 'dci_mcp_bridge_permission_edit_posts',
			'meta'                => array(
				'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
				'public'      => true,
				'mcp'         => array( 'public' => true ),
			),
		)
	);

	/* --------------------------------------------------------
	 * Ability 16: dci/set-featured-image — gambar utama artikel.
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/set-featured-image',
		array(
			'label'       => __( 'Set Featured Image', 'dci-mcp-bridge' ),
			'description' => __( 'Attach an existing media library IMAGE as the featured image of a post. Works on DRAFTS by default; pass allow_published=true to target a published post. Find image ids via dci/list-media. Does NOT upload new files.', 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'        => array( 'type' => 'integer' ),
					'attachment_id'  => array( 'type' => 'integer' ),
					'allow_published' => array( 'type' => 'boolean', 'default' => false ),
				),
				'required'   => array( 'post_id', 'attachment_id' ),
			),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array( 'post_id' => array( 'type' => 'integer' ), 'attachment_id' => array( 'type' => 'integer' ), 'image_url' => array( 'type' => 'string' ) ),
				'required'   => array( 'post_id', 'attachment_id' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_set_featured_image',
			'permission_callback' => 'dci_mcp_bridge_permission_edit_posts',
			'meta'                => array(
				'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
				'public'      => true,
				'mcp'         => array( 'public' => true ),
			),
		)
	);

	/* --------------------------------------------------------
	 * Ability 17: dci/update-elementor-text — sunting teks halaman
	 * Elementor langsung di sumbernya (_elementor_data).
	 * -------------------------------------------------------- */
	wp_register_ability(
		'dci/update-elementor-text',
		array(
			'label'       => __( 'Update Elementor Text', 'dci-mcp-bridge' ),
			'description' => __( "Edit text on an Elementor-built page/post at its TRUE source (_elementor_data JSON) via exact find/replace — editing post_content on Elementor pages gets overwritten by the builder, so use THIS instead. Steps: read texts via dci/get-content (elementor.texts), then pass one find/replace pair (exact string). Whitelisted widget text keys: title (heading), editor (text editor), text. Works on DRAFTS by default; pass allow_published=true for published content. Clears Elementor CSS cache after saving.", 'dci-mcp-bridge' ),
			'category'    => 'dci-content',
			'input_schema'    => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'         => array( 'type' => 'integer' ),
					'find'            => array( 'type' => 'string', 'description' => __( 'Exact text to find (copy from elementor.texts).' ) ),
					'replace'         => array( 'type' => 'string', 'description' => __( 'Replacement text (plain text; kept simple on purpose).' ) ),
					'allow_published' => array( 'type' => 'boolean', 'default' => false ),
				),
				'required'   => array( 'post_id', 'find', 'replace' ),
			),
			'output_schema'   => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'     => array( 'type' => 'integer' ),
					'replacements' => array( 'type' => 'integer' ),
					'css_cache_cleared' => array( 'type' => 'boolean' ),
				),
				'required'   => array( 'post_id', 'replacements' ),
			),
			'execute_callback'    => 'dci_mcp_bridge_execute_update_elementor_text',
			'permission_callback' => 'dci_mcp_bridge_permission_edit_posts',
			'meta'                => array(
				'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
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
	// v2.2.0: kapabilitas mengikuti tipe konten (page memakai kapabilitas *_pages).
	$cap    = 'publish_posts';
	$target = is_array( $input ) ? get_post( absint( $input['post_id'] ?? 0 ) ) : null;

	if ( $target && 'page' === $target->post_type ) {
		$cap = 'publish_pages';
	}

	if ( ! current_user_can( $cap ) ) {
		return new WP_Error(
			'dci_forbidden_publish',
			sprintf(
				/* translators: %s: nama kapabilitas yang dibutuhkan. */
				__( 'Pengguna Anda tidak punya kapabilitas %s (diperlukan untuk menerbitkan konten ini).', 'dci-mcp-bridge' ),
				$cap
			),
			array( 'status' => 403 )
		);
	}

	return true;
}

/**
 * Izin minimal untuk kemampuan baca konten: user ter-login.
 *
 * @param mixed $input Argumen ability (tidak dipakai di sini).
 * @return bool|WP_Error
 */
function dci_mcp_bridge_permission_read( $input = null ) {
	unset( $input );

	if ( ! current_user_can( 'read' ) ) {
		return new WP_Error(
			'dci_forbidden',
			__( 'Anda tidak berwenang membaca konten situs ini.', 'dci-mcp-bridge' ),
			array( 'status' => 403 )
		);
	}

	return true;
}

/**
 * Izin khusus memperbaiki artikel terbit: kemampuan WordPress
 * 'edit_published_posts' (Editor dan Administrator memilikinya).
 *
 * @param mixed $input Argumen ability (tidak dipakai di sini).
 * @return bool|WP_Error
 */
function dci_mcp_bridge_permission_edit_published( $input = null ) {
	// v2.2.0: kapabilitas mengikuti tipe konten.
	$cap    = 'edit_published_posts';
	$target = is_array( $input ) ? get_post( absint( $input['post_id'] ?? 0 ) ) : null;

	if ( $target && 'page' === $target->post_type ) {
		$cap = 'edit_published_pages';
	}

	if ( ! current_user_can( $cap ) ) {
		return new WP_Error(
			'dci_forbidden_published',
			sprintf(
				/* translators: %s: nama kapabilitas yang dibutuhkan. */
				__( 'Pengguna Anda tidak punya kapabilitas %s (diperlukan untuk memperbaiki konten terbit ini).', 'dci-mcp-bridge' ),
				$cap
			),
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

	// v2.2.0: draf POST maupun PAGE (halaman) sama-sama bisa dijembatani.
	if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return new WP_Error(
			'dci_post_not_found',
			__( 'Konten dengan ID tersebut tidak ditemukan (hanya post dan page yang didukung).', 'dci-mcp-bridge' )
		);
	}

	// Keputusan keamanan: ability ini hanya menyentuh draf. Artikel terbit
	// memakai jalur terpisah (dci/update-published-post) yang membuat
	// snapshot revisi sebelum mengubah apa pun.
	$allowed_statuses = array( 'draft', 'pending', 'auto-draft' );

	if ( ! in_array( $post->post_status, $allowed_statuses, true ) ) {
		return new WP_Error(
			'dci_not_editable_draft',
			sprintf(
				/* translators: 1: status post saat ini, 2: nama ability pengganti. */
				__( 'Post ini berstatus "%1$s". Untuk draf gunakan ability ini; untuk artikel terbit gunakan %2$s.', 'dci-mcp-bridge' ),
				$post->post_status,
				'dci/update-published-post'
			)
		);
	}

	return $post;
}

/**
 * Eksekusi ability dci/update-published-post: perbaiki artikel terbit.
 * Snapshot revisi WordPress dibuat otomatis sebelum perubahan.
 *
 * @param array $input Argumen ability.
 * @return array|WP_Error
 */
function dci_mcp_bridge_execute_update_published_post( $input = array() ) {
	$input   = is_array( $input ) ? $input : array();
	$post_id = absint( $input['post_id'] ?? 0 );
	$post    = $post_id > 0 ? get_post( $post_id ) : null;

	// v2.2.0: artikel maupun halaman terbit.
	if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return new WP_Error(
			'dci_post_not_found',
			__( 'Konten dengan ID tersebut tidak ditemukan (hanya post dan page yang didukung).', 'dci-mcp-bridge' )
		);
	}

	if ( 'publish' !== $post->post_status ) {
		return new WP_Error(
			'dci_not_published',
			sprintf(
				/* translators: 1: status post, 2: nama ability untuk draf. */
				__( 'Konten ini berstatus "%1$s" — belum terbit. Untuk draf, gunakan %2$s.', 'dci-mcp-bridge' ),
				$post->post_status,
				'dci/update-draft-post'
			)
		);
	}

	$update  = array( 'ID' => $post->ID );
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

	// Titik pemulihan: paksa snapshot revisi SEBELUM konten live berubah.
	$revision_saved = false;

	if ( function_exists( 'wp_save_post_revision' ) && function_exists( 'wp_revisions_enabled' ) && wp_revisions_enabled( $post ) ) {
		$revision_saved = (bool) wp_save_post_revision( $post->ID );
	}

	$result = wp_update_post( $update, true );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$permalink = get_permalink( $post->ID );
	$edit_link = get_edit_post_link( $post->ID, 'raw' );

	return array(
		'post_id'        => (int) $post->ID,
		'status'         => 'publish',
		'updated'        => $updated,
		'revision_saved' => $revision_saved,
		'link'           => $permalink ? $permalink : '',
		'edit_link'      => $edit_link ? $edit_link : '',
	);
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

	$allow_published = ! empty( $input['allow_published'] );

	if ( $allow_published ) {
		// Mode konten terbit (post MAUPUN page — v2.2.1): hanya meta yang
		// diubah, konten tak disentuh.
		$post_id = absint( $input['post_id'] ?? 0 );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return new WP_Error(
				'dci_post_not_found',
				__( 'Konten dengan ID tersebut tidak ditemukan (hanya post dan page yang didukung).', 'dci-mcp-bridge' )
			);
		}
	} else {
		$post = dci_mcp_bridge_get_editable_draft( $input['post_id'] ?? 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}
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
 * Resolusi FQCN kelas AI Puffer dengan kandidat ruang nama.
 *
 * TERVERIFIKASI dari source v2.4.95: kelas internal berada di namespace
 * WPAICG (AIPKit_AI_Caller = WPAICG\Core\AIPKit_AI_Caller; AIPKit_Providers
 * = WPAICG\AIPKit_Providers). Kandidat global dipertahankan sebagai
 * defensif bila plugin berubah di masa depan.
 *
 * @param string $short Nama kelas pendek (mis. AIPKit_AI_Caller).
 * @return string FQCN yang ditemukan, atau '' bila tidak ada.
 */
function dci_mcp_bridge_aipkit_class( $short ) {
	$candidates = array(
		'\\WPAICG\\Core\\' . $short,
		'\\WPAICG\\' . $short,
		'\\' . $short,
	);

	foreach ( $candidates as $candidate ) {
		if ( class_exists( $candidate ) ) {
			return $candidate;
		}
	}

	return '';
}

/**
 * Status mesin AI Puffer (v1.6.1 — deteksi kanonik, tahan versi).
 *
 * Dua akar salah deteksi versi lama, keduanya terbukti dari source:
 *  1. "Terpasang" kini dicek cara kanonik WordPress (is_plugin_active +
 *     versi dari get_plugins), bukan lewat kelas internal yang bisa
 *     berpindah namespace antar versi plugin.
 *  2. Kunci API provider tersimpan di aipkit_options['providers'][<Label>]['api_key']
 *     (lihat classes/ai/settings.php:1017), BUKAN di ['api_keys'] yang
 *     hanya memuat pengaturan Public API REST. Baca via aksesor resmi
 *     get_all_providers() bila tersedia, fallback baca cabang mentah.
 *
 * @return array installed, version, providers_configured, public_api_enabled, internal_ready.
 */
function dci_mcp_bridge_aip_status() {
	$plugin_file = 'gpt3-ai-content-generator/gpt3-ai-content-generator.php';

	// 1) Deteksi kanonik via WordPress.
	$installed = false;
	$version   = null;

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( function_exists( 'is_plugin_active' ) ) {
		$installed = is_plugin_active( $plugin_file );

		if ( $installed && function_exists( 'get_plugins' ) ) {
			$all_plugins = get_plugins();

			if ( isset( $all_plugins[ $plugin_file ]['Version'] ) ) {
				$version = (string) $all_plugins[ $plugin_file ]['Version'];
			}
		}
	}

	// 2) Fallback kelas internal bila fungsi admin tak tersedia (konteks jarang).
	$internal_ready = '' !== dci_mcp_bridge_aipkit_class( 'AIPKit_AI_Caller' );

	if ( ! $installed && $internal_ready ) {
		$installed = true;
	}

	// 3) Provider terisi — aksesor resmi dulu, lalu cabang mentah 'providers'.
	$raw_providers = null;

	$settings_class = dci_mcp_bridge_aipkit_class( 'AIPKIT_AI_Settings' );

	if ( $settings_class && method_exists( $settings_class, 'get_all_providers' ) ) {
		$raw_providers = call_user_func( array( $settings_class, 'get_all_providers' ) );
	}

	if ( ! is_array( $raw_providers ) ) {
		$aip_opts = get_option( 'aipkit_options', array() );
		$raw_providers = ( isset( $aip_opts['providers'] ) && is_array( $aip_opts['providers'] ) ) ? $aip_opts['providers'] : array();
	}

	$providers_configured = array();

	foreach ( $raw_providers as $label => $data ) {
		if ( ! is_array( $data ) ) {
			continue;
		}

		$key   = isset( $data['api_key'] ) ? trim( (string) $data['api_key'] ) : '';
		$model = isset( $data['model'] ) ? trim( (string) $data['model'] ) : '';

		// AIPufferCloud tidak memakai api_key — koneksi cloud dianggap siap bila model terisi.
		if ( '' !== $key || ( 'AIPufferCloud' === $label && '' !== $model ) ) {
			$providers_configured[] = (string) $label;
		}
	}

	// 4) Flag Public API tetap dari cabang api_keys.
	$aip_opts  = get_option( 'aipkit_options', array() );
	$aip_keys  = ( isset( $aip_opts['api_keys'] ) && is_array( $aip_opts['api_keys'] ) ) ? $aip_opts['api_keys'] : array();
	$public_api = array_key_exists( 'public_api_enabled', $aip_keys )
		? ( '1' === (string) $aip_keys['public_api_enabled'] )
		: false;

	return array(
		'installed'            => $installed,
		'version'              => $version,
		'providers_configured' => $providers_configured,
		'public_api_enabled'   => $public_api,
		'internal_ready'       => $internal_ready,
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

	// v2.2.0: post maupun page.
	if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return new WP_Error(
			'dci_post_not_found',
			__( 'Konten dengan ID tersebut tidak ditemukan (hanya post dan page yang didukung).', 'dci-mcp-bridge' )
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
 * BAGIAN 4E — OPERASIONAL SITUS & MEDIA (TIER 1, v2.1.0)
 *
 * Kontrak core yang dipakai (verifikasi playbook kelas A):
 * - Pembaruan plugin: transient 'update_plugins' (wp-includes/update.php)
 *   → ->response[file]->new_version; data = hasil pengecekan terjadwal WP.
 * - Tema: wp_get_theme()->get('Name'/'Version') (wp-includes/theme.php).
 * - Cron: wp_next_scheduled('wp_version_check') + konstanta DISABLE_WP_CRON
 *   (wp-includes/cron.php) — sinyal kesehatan penjadwal.
 * - Media: attachment = post type 'attachment' status 'inherit';
 *   alt text = postmeta '_wp_attachment_image_alt';
 *   featured image = postmeta '_thumbnail_id' (wp-includes/post.php).
 * ============================================================ */

/**
 * Eksekusi ability dci/update-elementor-text: sunting teks di sumbernya.
 *
 * @param array $input Argumen ability.
 * @return array|WP_Error
 */
function dci_mcp_bridge_execute_update_elementor_text( $input = array() ) {
	$input = is_array( $input ) ? $input : array();

	$post = ! empty( $input['allow_published'] )
		? ( absint( $input['post_id'] ?? 0 ) > 0 ? get_post( absint( $input['post_id'] ) ) : null )
		: dci_mcp_bridge_get_editable_draft( $input['post_id'] ?? 0 );

	if ( is_wp_error( $post ) || ! $post ) {
		return is_wp_error( $post ) ? $post : new WP_Error( 'dci_post_not_found', __( 'Konten tidak ditemukan (post/page).', 'dci-mcp-bridge' ) );
	}

	if ( 'builder' !== (string) get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
		return new WP_Error(
			'dci_not_elementor',
			__( 'Konten ini TIDAK dibangun dengan Elementor — gunakan dci/update-draft-post atau dci/update-published-post (edit post_content).', 'dci-mcp-bridge' )
		);
	}

	$find    = isset( $input['find'] ) ? (string) $input['find'] : '';
	$replace = isset( $input['replace'] ) ? (string) $input['replace'] : '';

	if ( '' === $find || $find === $replace ) {
		return new WP_Error( 'dci_invalid_args', __( 'Parameter find wajib diisi dan berbeda dari replace.', 'dci-mcp-bridge' ) );
	}

	$raw = get_post_meta( $post->ID, '_elementor_data', true );
	$data = is_string( $raw ) ? json_decode( $raw, true ) : null;

	if ( ! is_array( $data ) ) {
		return new WP_Error( 'dci_elementor_data_invalid', __( 'Data Elementor (_elementor_data) kosong/rusak.', 'dci-mcp-bridge' ) );
	}

	$replacements = dci_mcp_bridge_elementor_replace_texts( $data, $find, $replace );

	if ( 0 === $replacements ) {
		return new WP_Error(
			'dci_text_not_found',
			__( 'Teks "find" tidak ditemukan di teks widget. Ambil teks persisnya dari dci/get-content → elementor.texts.', 'dci-mcp-bridge' )
		);
	}

	// wp_slash: update_post_meta meng-unslash; JSON bertanda kutip butuh dilindungi.
	update_post_meta( $post->ID, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );

	// Bersihkan cache CSS Elementor bila kelasnya ada (core/files/manager.php: clear_cache).
	$cache_cleared = false;

	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) && \Elementor\Plugin::$instance->files_manager ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
		$cache_cleared = true;
	}

	return array(
		'post_id'           => (int) $post->ID,
		'replacements'      => $replacements,
		'css_cache_cleared' => $cache_cleared,
	);
}

/**
 * Eksekusi ability dci/site-report: snapshot operasional situs.
 *
 * @return array
 */
function dci_mcp_bridge_execute_site_report() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$all = function_exists( 'get_plugins' ) ? get_plugins() : array();
	$active = array();
	$total  = 0;

	foreach ( (array) $all as $file => $data ) {
		$total++;
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $file ) ) {
			$active[] = (string) $file;
		}
	}

	$pending = array();
	$updates = get_site_transient( 'update_plugins' );

	if ( is_object( $updates ) && ! empty( $updates->response ) ) {
		foreach ( (array) $updates->response as $file => $u ) {
			if ( is_object( $u ) && ! empty( $u->new_version ) && count( $pending ) < 30 ) {
				$pending[] = array(
					'plugin'      => (string) $file,
					'new_version' => (string) $u->new_version,
				);
			}
		}
	}

	$theme_name = '';
	$theme_ver  = '';
	if ( function_exists( 'wp_get_theme' ) ) {
		$theme      = wp_get_theme();
		$theme_name = (string) $theme->get( 'Name' );
		$theme_ver  = (string) $theme->get( 'Version' );
	}

	$counts = array();
	foreach ( array( 'post' => 'post', 'page' => 'page', 'attachment' => 'attachment' ) as $type => $key ) {
		if ( function_exists( 'wp_count_posts' ) ) {
			$obj  = wp_count_posts( $type );
			$cnt  = ( $obj && isset( $obj->publish ) ) ? (int) $obj->publish : 0;
			if ( 'attachment' === $type ) { $cnt = ( $obj && isset( $obj->inherit ) ) ? (int) $obj->inherit : $cnt; }
			$counts[ $key ] = $cnt;
		}
	}

	return array(
		'site'    => array(
			'name'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'url'     => home_url(),
			'version' => get_bloginfo( 'version' ),
			'php'     => PHP_VERSION,
			'locale'  => function_exists( 'get_locale' ) ? get_locale() : '',
			'permalink_structure' => (string) get_option( 'permalink_structure', '' ),
			'plugin_version'      => DCI_MCP_BRIDGE_VERSION,
		),
		'theme'   => array( 'name' => $theme_name, 'version' => $theme_ver ),
		'cron'    => array(
			'disabled'              => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'version_check_scheduled' => function_exists( 'wp_next_scheduled' ) ? (bool) wp_next_scheduled( 'wp_version_check' ) : null,
		),
		'plugins' => array(
			'total'    => $total,
			'active'   => count( $active ),
			'pending_updates' => count( $pending ),
			'updates'  => $pending,
		),
		'counts'  => $counts,
	);
}

/**
 * Eksekusi ability dci/bulk-audit: audit banyak artikel sekaligus.
 *
 * @param array $input Argumen ability.
 * @return array|WP_Error
 */
function dci_mcp_bridge_execute_bulk_audit( $input = array() ) {
	$input  = is_array( $input ) ? $input : array();
	$count  = min( 50, max( 1, absint( $input['count'] ?? 10 ) ) );
	$status = ( isset( $input['status'] ) && 'draft' === $input['status'] ) ? 'draft' : 'publish';
	// v2.2.0: opsi ikut-serta halaman.
	$types  = ! empty( $input['include_pages'] ) ? array( 'post', 'page' ) : array( 'post' );

	$posts = get_posts( array(
		'post_type'      => $types,
		'post_status'    => $status,
		'posts_per_page' => $count,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	if ( ! is_array( $posts ) ) {
		$posts = array();
	}

	$items  = array();
	$t_pass = 0; $t_warn = 0; $t_fail = 0;

	foreach ( $posts as $post_item ) {
		$GLOBALS['dci_bulk_current'] = $post_item;
		$audit = dci_mcp_bridge_execute_audit_article( array( 'post_id' => (int) $post_item->ID ) );
		unset( $GLOBALS['dci_bulk_current'] );

		if ( is_wp_error( $audit ) ) {
			continue;
		}

		$fails = array();
		$labels = array();
		foreach ( $audit['checks'] as $chk ) {
			if ( 'FAIL' === $chk['status'] ) { $labels[] = $chk['label']; }
			if ( 'PASS' === $chk['status'] ) { $t_pass++; }
			elseif ( 'WARN' === $chk['status'] ) { $t_warn++; }
			else { $t_fail++; }
		}

		$items[] = array(
			'post_id' => (int) $post_item->ID,
			'title'   => (string) $post_item->post_title,
			'url'     => (string) get_permalink( $post_item->ID ),
			'status'  => $status,
			'pass'    => (int) $audit['summary']['pass'],
			'warn'    => (int) $audit['summary']['warn'],
			'fail'    => (int) $audit['summary']['fail'],
			'failed_checks' => array_slice( $labels, 0, 3 ),
		);
	}

	// Urutkan: paling banyak FAIL dulu (target perbaikan utama).
	usort( $items, function ( $a, $b ) { return $b['fail'] <=> $a['fail']; } );

	return array(
		'total'   => count( $items ),
		'summary' => array( 'pass' => $t_pass, 'warn' => $t_warn, 'fail' => $t_fail ),
		'items'   => $items,
	);
}

/**
 * Eksekusi ability dci/list-media: inventaris media gambar + alt.
 *
 * @param array $input Argumen ability.
 * @return array
 */
function dci_mcp_bridge_execute_list_media( $input = array() ) {
	$input    = is_array( $input ) ? $input : array();
	$per_page = min( 50, max( 1, absint( $input['per_page'] ?? 20 ) ) );
	$paged    = max( 1, absint( $input['page'] ?? 1 ) );

	$media = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => 'image',
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	if ( ! is_array( $media ) ) {
		$media = array();
	}

	$items = array();
	foreach ( $media as $m ) {
		$items[] = array(
			'id'        => (int) $m->ID,
			'title'     => (string) $m->post_title,
			'url'       => function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( $m->ID ) : '',
			'alt'       => (string) get_post_meta( $m->ID, '_wp_attachment_image_alt', true ),
			'parent_id' => (int) $m->post_parent,
			'date'      => (string) $m->post_date,
		);
	}

	return array( 'page' => $paged, 'items' => $items );
}

/**
 * Eksekusi ability dci/set-media-alt: perbaiki alt text gambar.
 *
 * @param array $input Argumen ability.
 * @return array|WP_Error
 */
function dci_mcp_bridge_execute_set_media_alt( $input = array() ) {
	$input = is_array( $input ) ? $input : array();
	$att   = absint( $input['attachment_id'] ?? 0 );
	$alt   = isset( $input['alt'] ) ? sanitize_text_field( (string) $input['alt'] ) : '';

	if ( $att <= 0 || '' === $alt ) {
		return new WP_Error( 'dci_invalid_args', __( 'attachment_id dan alt wajib diisi (alt tidak boleh kosong).' ) );
	}

	$attachment = get_post( $att );
	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return new WP_Error( 'dci_not_attachment', __( 'ID tersebut bukan media library (attachment).' ) );
	}

	update_post_meta( $att, '_wp_attachment_image_alt', $alt );

	return array( 'attachment_id' => $att, 'alt' => $alt );
}

/**
 * Eksekusi ability dci/set-featured-image: gambar utama artikel.
 *
 * @param array $input Argumen ability.
 * @return array|WP_Error
 */
function dci_mcp_bridge_execute_set_featured_image( $input = array() ) {
	$input = is_array( $input ) ? $input : array();

	$post = ! empty( $input['allow_published'] )
		? ( absint( $input['post_id'] ?? 0 ) > 0 ? get_post( absint( $input['post_id'] ) ) : null )
		: dci_mcp_bridge_get_editable_draft( $input['post_id'] ?? 0 );

	if ( is_wp_error( $post ) || ! $post ) {
		return is_wp_error( $post ) ? $post : new WP_Error( 'dci_post_not_found', __( 'Post tidak ditemukan.' ) );
	}

	$att = absint( $input['attachment_id'] ?? 0 );
	$attachment = $att > 0 ? get_post( $att ) : null;

	if ( ! $attachment || 'attachment' !== $attachment->post_type || 0 !== strpos( (string) $attachment->post_mime_type, 'image/' ) ) {
		return new WP_Error( 'dci_not_image', __( 'attachment_id harus media bertipe gambar.' ) );
	}

	update_post_meta( $post->ID, '_thumbnail_id', $att );

	return array(
		'post_id'       => (int) $post->ID,
		'attachment_id' => $att,
		'image_url'     => function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( $att ) : '',
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
	$caller_class   = dci_mcp_bridge_aipkit_class( 'AIPKit_AI_Caller' );
	$provider_class = dci_mcp_bridge_aipkit_class( 'AIPKit_Providers' );

	if ( '' !== $caller_class && '' !== $provider_class ) {
		if ( method_exists( $provider_class, 'normalize_provider_label' ) ) {
			$provider = call_user_func( array( $provider_class, 'normalize_provider_label' ), $provider );
		}

		if ( method_exists( $provider_class, 'get_text_generation_providers' ) ) {
			$valid_providers = call_user_func( array( $provider_class, 'get_text_generation_providers' ) );

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

		$caller = new $caller_class( false, 'mcp' );
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

/**
 * Eksekusi ability dci/search-content: cari/daftar konten situs ini.
 *
 * @param array $input Argumen ability.
 * @return array|WP_Error
 */
function dci_mcp_bridge_execute_search_content( $input = array() ) {
	$input = is_array( $input ) ? $input : array();

	$query = isset( $input['query'] ) ? trim( (string) $input['query'] ) : '';

	$types = ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) )
		? array_values( array_filter( array_map( 'sanitize_key', array_map( 'strval', $input['post_types'] ) ) ) )
		: array();

	if ( empty( $types ) ) {
		$types = array( 'post', 'page' );
	}

	$per_page = min( 50, max( 1, absint( $input['per_page'] ?? 20 ) ) );
	$paged    = max( 1, absint( $input['page'] ?? 1 ) );

	$args = array(
		'post_type'           => $types,
		'post_status'         => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'posts_per_page'      => $per_page,
		'paged'               => $paged,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
	);

	if ( '' !== $query ) {
		$args['s'] = $query;
	}

	$posts = get_posts( $args );

	if ( ! is_array( $posts ) ) {
		$posts = array();
	}

	$items = array();

	foreach ( $posts as $post_item ) {
		$source = ( ! empty( $post_item->post_excerpt ) ) ? $post_item->post_excerpt : $post_item->post_content;

		$items[] = array(
			'id'      => (int) $post_item->ID,
			'type'    => (string) $post_item->post_type,
			'status'  => (string) $post_item->post_status,
			'title'   => (string) $post_item->post_title,
			'url'     => (string) get_permalink( $post_item->ID ),
			'date'    => (string) $post_item->post_date,
			'excerpt' => wp_trim_words( wp_strip_all_tags( (string) $source ), 30 ),
		);
	}

	return array(
		'query' => $query,
		'page'  => $paged,
		'items' => $items,
	);
}

/**
 * Eksekusi ability dci/get-content: ambil isi penuh satu konten.
 *
 * @param array $input Argumen ability.
 * @return array|WP_Error
 */
function dci_mcp_bridge_execute_get_content( $input = array() ) {
	$input = is_array( $input ) ? $input : array();

	$post_id = absint( $input['post_id'] ?? 0 );

	if ( 0 === $post_id && ! empty( $input['url'] ) && is_string( $input['url'] ) ) {
		$post_id = url_to_postid( esc_url_raw( trim( $input['url'] ) ) );
	}

	$post = $post_id > 0 ? get_post( $post_id ) : null;

	// v2.2.0: post maupun page.
	if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return new WP_Error(
			'dci_post_not_found',
			__( 'Konten tidak ditemukan di situs ini (hanya post dan page yang didukung). Berikan post_id atau URL yang valid pada domain situs ini.', 'dci-mcp-bridge' )
		);
	}

	$content_text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $post->post_content ) ) );

	/* v2.2.0 — sadar Elementor: kalau halaman dibangun dengan Elementor
	 * (meta _elementor_edit_mode === 'builder', terverifikasi dari source),
	 * post_content hanyalah hasil render — sumber kebenarannya _elementor_data.
	 * Ekstrak teks widget agar agent bisa membaca tanpa membongkar JSON. */
	$elementor = array( 'is_builder' => false, 'texts' => array() );

	$edit_mode = get_post_meta( $post->ID, '_elementor_edit_mode', true );

	if ( 'builder' === (string) $edit_mode ) {
		$elementor['is_builder'] = true;
		$raw_data = get_post_meta( $post->ID, '_elementor_data', true );
		$data     = is_string( $raw_data ) ? json_decode( $raw_data, true ) : null;

		if ( is_array( $data ) ) {
			$elementor['texts'] = array_slice( dci_mcp_bridge_elementor_extract_texts( $data ), 0, 120 );
		}
	}

	return array(
		'post_id'      => (int) $post->ID,
		'type'         => (string) $post->post_type,
		'status'       => (string) $post->post_status,
		'title'        => (string) $post->post_title,
		'url'          => (string) get_permalink( $post->ID ),
		'date'         => (string) $post->post_date,
		'excerpt'      => wp_trim_words( $content_text, 30 ),
		'content_text' => $content_text,
		// HTML mentah tersimpan (termasuk markup blok) — basis read-modify-write.
		'content_html' => (string) $post->post_content,
		'elementor'    => $elementor,
	);
}

/**
 * Rekursif ekstrak teks dari struktur _elementor_data.
 * Kunci teks diverifikasi dari source widget Elementor:
 * heading = settings.title, text-editor = settings.editor,
 * kontrol teks generik = settings.text.
 *
 * @param array $node Node/struktur Elementor.
 * @return array Daftar teks.
 */
function dci_mcp_bridge_elementor_extract_texts( array $node ) {
	$texts = array();

	foreach ( $node as $key => $value ) {
		if ( 'settings' === $key && is_array( $value ) ) {
			foreach ( array( 'title', 'editor', 'text' ) as $text_key ) {
				if ( isset( $value[ $text_key ] ) && is_string( $value[ $text_key ] ) && '' !== trim( $value[ $text_key ] ) ) {
					$texts[] = trim( wp_strip_all_tags( $value[ $text_key ] ) );
				}
			}
		} elseif ( is_array( $value ) ) {
			// 'elements' (children) maupun list section numerik — turuni semua.
			$texts = array_merge( $texts, dci_mcp_bridge_elementor_extract_texts( $value ) );
		}
	}

	return $texts;
}

/**
 * Rekursif find/replace pada kunci teks whitelisted di _elementor_data.
 *
 * @param array  $node    Node (by reference).
 * @param string $find    Teks dicari.
 * @param string $replace Teks pengganti.
 * @return int Jumlah penggantian.
 */
function dci_mcp_bridge_elementor_replace_texts( array &$node, $find, $replace ) {
	$count = 0;

	foreach ( $node as $key => $value ) {
		if ( is_array( $value ) ) {
			$count += dci_mcp_bridge_elementor_replace_texts( $node[ $key ], $find, $replace );
		} elseif ( is_string( $value ) && in_array( $key, array( 'title', 'editor', 'text' ), true ) ) {
			$node[ $key ] = str_replace( $find, $replace, $value, $n );
			$count += $n;
		}
	}

	return $count;
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

	// v2.2.0: post maupun page.
	if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return new WP_Error(
			'dci_post_not_found',
			__( 'Konten dengan ID tersebut tidak ditemukan (hanya post dan page yang didukung).', 'dci-mcp-bridge' )
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
 * BAGIAN 7 — PEMBARUAN OTOMATIS DARI GITHUB RELEASES
 *
 * Kontrak diverifikasi dari docs.github.com (REST Releases):
 * GET https://api.github.com/repos/{owner}/{repo}/releases/latest
 * → 200 { tag_name, html_url, assets[]: { name, browser_download_url } };
 * hanya rilis publik (draft/prerelease dikecualikan); 404 bila belum ada
 * rilis ATAU repo privat tanpa token. Auth opsional: Bearer <token>
 * (fine-grained PAT, Contents: Read-only, khusus repo ini).
 * Paket pembaruan WAJIB aset Release bernama dci-mcp-bridge.zip
 * (struktur folder di dalam zip harus "dci-mcp-bridge/" — dijamin oleh
 * tools/verify.php gerbang 7) agar WordPress meng-update, bukan duplikat.
 * ============================================================ */

/**
 * Repo sumber pembaruan (bisa dioverride via filter).
 *
 * @return string "owner/repo".
 */
function dci_mcp_bridge_github_repo() {
	return apply_filters( 'dci_mcp_bridge_github_repo', 'alecslacker/dci-wp-ai-bridge' );
}

/**
 * Token GitHub opsional untuk repo privat: konstanta menang, lalu opsi admin.
 *
 * @return string
 */
function dci_mcp_bridge_github_token() {
	if ( defined( 'DCI_GITHUB_TOKEN' ) && is_string( DCI_GITHUB_TOKEN ) && '' !== trim( DCI_GITHUB_TOKEN ) ) {
		return trim( DCI_GITHUB_TOKEN );
	}

	$opts = get_option( 'dci_mcp_bridge_options', array() );

	return ( isset( $opts['github_token'] ) && is_string( $opts['github_token'] ) ) ? trim( $opts['github_token'] ) : '';
}

/**
 * Validasi URL unduhan: hanya HTTPS di domain GitHub yang sah
 * (anti pembelokan paket ke host asing — gerbang keamanan unduhan).
 *
 * @param string $url URL aset.
 * @return bool
 */
function dci_mcp_bridge_is_github_url( $url ) {
	$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$scheme = (string) wp_parse_url( $url, PHP_URL_SCHEME );

	if ( 'https' !== $scheme || '' === $host ) {
		return false;
	}

	foreach ( array( 'github.com', 'githubusercontent.com', 'githubassets.com' ) as $allowed ) {
		if ( $host === $allowed || dci_mcp_bridge_ends_with( '.' . $allowed, $host, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Helper akhiran string (case-insensitive opsional).
 *
 * @param string $needle  Akhiran yang dicari.
 * @param string $haystack Teks sumber.
 * @param bool   $insensitive Abaikan besar-kecil.
 * @return bool
 */
function dci_mcp_bridge_ends_with( $needle, $haystack, $insensitive = false ) {
	if ( '' === $needle ) { return true; }
	if ( strlen( $needle ) > strlen( $haystack ) ) { return false; }
	if ( $insensitive ) {
		return false !== stripos( $haystack, $needle, strlen( $haystack ) - strlen( $needle ) );
	}
	return substr( $haystack, -strlen( $needle ) ) === $needle;
}

/**
 * Ambil info rilis terbaru (cache 1 jam; 404/tanpa rilis → null).
 *
 * @param bool $force_cache_bypass Lewati cache.
 * @return array|null {version, zip, url} atau null.
 */
function dci_mcp_bridge_fetch_latest_release( $force_cache_bypass = false ) {
	$cache_key = 'dci_mcp_bridge_latest_release';

	if ( ! $force_cache_bypass ) {
		$cached = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) { return $cached; }
	}

	$token = dci_mcp_bridge_github_token();
	$headers = array( 'Accept' => 'application/vnd.github+json' );

	if ( '' !== $token ) {
		$headers['Authorization'] = 'Bearer ' . $token;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . dci_mcp_bridge_github_repo() . '/releases/latest',
		array( 'timeout' => 15, 'headers' => $headers )
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null; // belum ada rilis / repo privat tanpa token / gangguan — senyap.
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
		return null;
	}

	$version = ltrim( (string) $body['tag_name'], 'v' );
	$zip_url = '';

	if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
		$fallback_zip = '';
		foreach ( $body['assets'] as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['browser_download_url'] ) ) { continue; }
			$name = isset( $asset['name'] ) ? (string) $asset['name'] : '';
			if ( 'dci-mcp-bridge.zip' === $name ) {
				$zip_url = (string) $asset['browser_download_url'];
				break;
			}
			if ( '' === $fallback_zip && dci_mcp_bridge_ends_with( '.zip', $name, true ) ) {
				$fallback_zip = (string) $asset['browser_download_url'];
			}
		}
		if ( '' === $zip_url ) { $zip_url = $fallback_zip; }
	}

	if ( '' === $zip_url || '' === $version || ! dci_mcp_bridge_is_github_url( $zip_url ) ) {
		return null;
	}

	$data = array(
		'version' => $version,
		'zip'     => $zip_url,
		'url'     => isset( $body['html_url'] ) ? (string) $body['html_url'] : '',
	);

	set_transient( $cache_key, $data, HOUR_IN_SECONDS );

	return $data;
}

/**
 * Suntikkan pembaruan ke transien WordPress (muncul di halaman Updates).
 *
 * @param object $transient Transien update_plugins.
 * @return object
 */
function dci_mcp_bridge_inject_update( $transient ) {
	if ( ! is_object( $transient ) ) {
		return $transient;
	}

	$latest = dci_mcp_bridge_fetch_latest_release();

	if ( null === $latest || ! isset( $latest['version'], $latest['zip'] ) ) {
		return $transient;
	}

	if ( version_compare( DCI_MCP_BRIDGE_VERSION, $latest['version'], '>=' ) ) {
		return $transient;
	}

	$transient->response[ plugin_basename( __FILE__ ) ] = (object) array(
		'slug'        => 'dci-mcp-bridge',
		'plugin'      => plugin_basename( __FILE__ ),
		'new_version' => $latest['version'],
		'url'         => $latest['url'],
		'package'     => $latest['zip'],
	);

	return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'dci_mcp_bridge_inject_update' );

/**
 * Info pembaruan di layar detail plugin (mencegah permintaan ke wp.org).
 *
 * @param object|false $result Hasil bawaan.
 * @param string       $action  Aksi plugins_api.
 * @param object       $args    Argumen.
 * @return object|false
 */
function dci_mcp_bridge_plugins_api( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! isset( $args->slug ) || 'dci-mcp-bridge' !== $args->slug ) {
		return $result;
	}

	$latest = dci_mcp_bridge_fetch_latest_release();

	if ( null === $latest ) {
		return $result;
	}

	return (object) array(
		'name'          => 'DCI MCP Bridge',
		'slug'          => 'dci-mcp-bridge',
		'version'       => $latest['version'],
		'download_link' => $latest['zip'],
		'sections'      => array(
			'description' => __( 'Pembaruan dari GitHub Releases (alecslacker/dci-wp-ai-bridge). Rincian perubahan lengkap ada di halaman rilis.', 'dci-mcp-bridge' ),
		),
	);
}
add_filter( 'plugins_api', 'dci_mcp_bridge_plugins_api', 10, 3 );

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
				'tab'       => 'integritas',
				'dci-saved' => '1',
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_dci_bridge_save_key', 'dci_mcp_bridge_handle_save_key' );

/**
 * Simpan token GitHub (opsional, untuk repo privat) dari tab Tentang.
 */
function dci_mcp_bridge_handle_save_github_token() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Anda tidak berwenang melakukan ini.', 'dci-mcp-bridge' ) );
	}

	check_admin_referer( 'dci_bridge_save_github_token' );

	$token = isset( $_POST['dci_github_token'] ) ? sanitize_text_field( wp_unslash( $_POST['dci_github_token'] ) ) : '';

	$opts = get_option( 'dci_mcp_bridge_options', array() );

	if ( ! is_array( $opts ) ) {
		$opts = array();
	}

	if ( '' !== $token ) {
		$opts['github_token'] = $token;
	} else {
		unset( $opts['github_token'] );
	}

	update_option( 'dci_mcp_bridge_options', $opts );
	delete_transient( 'dci_mcp_bridge_latest_release' );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'      => 'dci-mcp-bridge',
				'tab'       => 'tentang',
				'dci-saved' => '1',
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_dci_bridge_save_github_token', 'dci_mcp_bridge_handle_save_github_token' );

/**
 * Status tiga dependensi: MCP Adapter, AI Puffer (Public API), Rank Math.
 *
 * @return array Array status per komponen.
 */
function dci_mcp_bridge_env_status() {
	// MCP Adapter: nama kelas inti diverifikasi dari dokumentasi resminya.
	$mcp_active = class_exists( '\WP\MCP\Core\McpAdapter' );

	// AI Puffer: deteksi kanonik (plugin aktif + versi + provider terisi).
	$aip = dci_mcp_bridge_aip_status();
	$aip_state  = 'nonaktif';
	$aip_detail = __( 'Plugin AI Puffer tidak terdeteksi — kemampuan generate teks tidak akan berfungsi.', 'dci-mcp-bridge' );
	$aip_vers   = ( null !== $aip['version'] ) ? sprintf( 'v%s ', $aip['version'] ) : '';

	if ( $aip['installed'] ) {
		if ( ! empty( $aip['providers_configured'] ) ) {
			$aip_state  = 'aktif';
			$aip_detail = sprintf(
				/* translators: 1: versi, 2: daftar provider, 3: status jalur. */
				__( 'AI Puffer %1$sterpasang — mesin AI siap. Provider terisi: %2$s. Jalur: %3$s.', 'dci-mcp-bridge' ),
				$aip_vers,
				implode( ', ', $aip['providers_configured'] ),
				$aip['internal_ready']
					? __( 'internal langsung (tanpa HTTP)', 'dci-mcp-bridge' )
					: __( 'versi lama — perbarui AI Puffer agar memakai jalur internal', 'dci-mcp-bridge' )
			);
		} else {
			$aip_state  = 'perlu-konfigurasi';
			$aip_detail = sprintf(
				/* translators: %s: versi (boleh kosong). */
				__( 'AI Puffer %1$sterpasang, tetapi belum ada API key provider yang terisi (AIPKit → Settings). Isi minimal satu provider agar generate teks berfungsi.', 'dci-mcp-bridge' ),
				$aip_vers
			);
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
			'nama'   => __( 'AI Puffer (Mesin AI)', 'dci-mcp-bridge' ),
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
		array( 'name' => 'dci/update-published-post', 'label' => __( 'Update Published Post', 'dci-mcp-bridge' ), 'sifat' => __( 'Tulis — live, snapshot revisi otomatis', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/set-post-seo-meta', 'label' => __( 'Set Post SEO Meta (Rank Math)', 'dci-mcp-bridge' ), 'sifat' => __( 'Tulis — khusus draf', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/audit-article', 'label' => __( 'Audit Article (SEO On-Page)', 'dci-mcp-bridge' ), 'sifat' => __( 'Baca', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/publish-post', 'label' => __( 'Publish Draft Post', 'dci-mcp-bridge' ), 'sifat' => __( 'Tulis — aksi eksplisit', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/get-seo-config', 'label' => __( 'Get Rank Math SEO Config', 'dci-mcp-bridge' ), 'sifat' => __( 'Baca', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/check-originality', 'label' => __( 'Check Originality (AI + Plagiarism)', 'dci-mcp-bridge' ), 'sifat' => __( 'Baca — berbiaya kredit', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/search-content', 'label' => __( 'Search Site Content', 'dci-mcp-bridge' ), 'sifat' => __( 'Baca', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/get-content', 'label' => __( 'Get Site Content', 'dci-mcp-bridge' ), 'sifat' => __( 'Baca', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/site-report', 'label' => __( 'Site Report (Ops Agensi)', 'dci-mcp-bridge' ), 'sifat' => __( 'Baca', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/bulk-audit', 'label' => __( 'Bulk Audit Articles', 'dci-mcp-bridge' ), 'sifat' => __( 'Baca', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/list-media', 'label' => __( 'List Media (Images)', 'dci-mcp-bridge' ), 'sifat' => __( 'Baca', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/set-media-alt', 'label' => __( 'Set Media Alt Text', 'dci-mcp-bridge' ), 'sifat' => __( 'Tulis — meta media', 'dci-mcp-bridge' ) ),
		 array( 'name' => 'dci/set-featured-image', 'label' => __( 'Set Featured Image', 'dci-mcp-bridge' ), 'sifat' => __( 'Tulis — draf (ops. terbit)', 'dci-mcp-bridge' ) ),
		array( 'name' => 'dci/update-elementor-text', 'label' => __( 'Update Elementor Text', 'dci-mcp-bridge' ), 'sifat' => __( 'Tulis — sumber _elementor_data', 'dci-mcp-bridge' ) ),
	);
}

/**
 * Nama server MCP untuk snippet koneksi: domain situs + penanda WordPress.
 * Contoh: www.namadomain.co.id → "namadomain-wordpress".
 * Nama situs di depan agar daftar klien mengelompok per situs (agensi
 * multi-klien); sufiks -wordpress menyatakan sistem yang disambungkan.
 * Override: konstanta DCI_MCP_SERVER_NAME di wp-config.php.
 *
 * @return string Nama server (hurf kecil, aman untuk semua klien MCP).
 */
function dci_mcp_bridge_server_name() {
	if ( defined( 'DCI_MCP_SERVER_NAME' ) && is_string( DCI_MCP_SERVER_NAME ) && '' !== trim( DCI_MCP_SERVER_NAME ) ) {
		return sanitize_key( trim( DCI_MCP_SERVER_NAME ) );
	}

	$site_host = preg_replace( '/^www\./i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$host_seg  = explode( '.', (string) $site_host );
	$slug      = sanitize_key( ( isset( $host_seg[0] ) && '' !== $host_seg[0] ) ? $host_seg[0] : '' );

	if ( '' === $slug ) {
		$slug = 'situs';
	}

	return $slug . '-wordpress';
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

	/* Nama MCP: domain situs + penanda WordPress (www.namadomain.co.id
	 * → namadomain-wordpress). Menyatakan sistem yang disambungkan tanpa
	 * mengorbankan pengelompokan per-situs (agensi multi-klien).
	 * Override manual: define( 'DCI_MCP_SERVER_NAME', 'nama-lain' ); */
	$mcp_name = dci_mcp_bridge_server_name();

	/* Navigasi tab — pakai kelas native WordPress (nav-tab) agar konsisten
	 * dengan tampilan admin inti; tab aktif dari parameter ?tab=. */
	$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'status';
	$tabs        = array(
		'status'     => __( 'Status', 'dci-mcp-bridge' ),
		'integritas' => __( 'Integritas Konten', 'dci-mcp-bridge' ),
		'koneksi'    => __( 'Koneksi AI Agent', 'dci-mcp-bridge' ),
		'panduan'    => __( 'Cara Penggunaan', 'dci-mcp-bridge' ),
		'tentang'    => __( 'Tentang', 'dci-mcp-bridge' ),
	);

	if ( ! isset( $tabs[ $current_tab ] ) ) {
		$current_tab = 'status';
	}

	$page_base = admin_url( 'admin.php?page=dci-mcp-bridge' );

	/* Potongan konfigurasi per klien — format dari dokumentasi resmi masing-masing.
	 * __BASIC_AUTH__ = placeholder yang diisi JavaScript di browser
	 * (base64 dari user:application-password, tidak pernah dikirim ke server). */
	$token = '__BASIC_AUTH__';
	$snippets = array(
		'claude-code' => array(
			'label' => __( 'Claude Code', 'dci-mcp-bridge' ),
			'lokasi' => __( 'Perintah terminal (langsung), atau file .mcp.json di root proyek', 'dci-mcp-bridge' ),
			'code' => "claude mcp add --transport http {$mcp_name} {$endpoint_url} \\\n  --header \"Authorization: Basic {$token}\"\n\n/* .mcp.json (proyek) */\n{\n  \"mcpServers\": {\n    \"{$mcp_name}\": {\n      \"type\": \"http\",\n      \"url\": \"{$endpoint_url}\",\n      \"headers\": {\n        \"Authorization\": \"Basic {$token}\"\n      }\n    }\n  }\n}",
		),
		'cursor' => array(
			'label' => __( 'Cursor', 'dci-mcp-bridge' ),
			'lokasi' => __( '~/.cursor/mcp.json (global) atau .cursor/mcp.json (proyek)', 'dci-mcp-bridge' ),
			'code' => "{\n  \"mcpServers\": {\n    \"{$mcp_name}\": {\n      \"type\": \"http\",\n      \"url\": \"{$endpoint_url}\",\n      \"headers\": {\n        \"Authorization\": \"Basic {$token}\"\n      }\n    }\n  }\n}",
		),
		'codex' => array(
			'label' => __( 'Codex (OpenAI CLI)', 'dci-mcp-bridge' ),
			'lokasi' => __( '~/.codex/config.toml — atau: codex mcp add --url <endpoint> <slug>', 'dci-mcp-bridge' ),
			'code' => "[mcp_servers.{$mcp_name}]\nurl = \"{$endpoint_url}\"\nhttp_headers = { \"Authorization\" = \"Basic {$token}\" }",
		),
		'trae' => array(
			'label' => __( 'TRAE', 'dci-mcp-bridge' ),
			'lokasi' => __( 'Settings → MCP → Add MCP Server → JSON', 'dci-mcp-bridge' ),
			'code' => "{\n  \"mcpServers\": {\n    \"{$mcp_name}\": {\n      \"type\": \"streamable-http\",\n      \"url\": \"{$endpoint_url}\",\n      \"headers\": {\n        \"Authorization\": \"Basic {$token}\"\n      }\n    }\n  }\n}",
		),
		'openclaw' => array(
			'label' => __( 'OpenClaw', 'dci-mcp-bridge' ),
			'lokasi' => __( 'Perintah terminal (tersimpan otomatis di openclaw.json → mcp.servers)', 'dci-mcp-bridge' ),
			'code' => "openclaw mcp add {$mcp_name} \\\n  --url {$endpoint_url} \\\n  --transport streamable-http \\\n  --header \"Authorization: Basic {$token}\"\n\n# verifikasi:\nopenclaw mcp probe {$mcp_name}",
		),
		'antigravity' => array(
			'label' => __( 'Antigravity (Google)', 'dci-mcp-bridge' ),
			'lokasi' => __( '~/.gemini/antigravity/mcp_config.json (perhatikan: serverUrl, bukan url)', 'dci-mcp-bridge' ),
			'code' => "{\n  \"mcpServers\": {\n    \"{$mcp_name}\": {\n      \"serverUrl\": \"{$endpoint_url}\",\n      \"headers\": {\n        \"Authorization\": \"Basic {$token}\"\n      }\n    }\n  }\n}",
		),
		'hermes' => array(
			'label' => __( 'Hermes', 'dci-mcp-bridge' ),
			'lokasi' => __( 'config.yaml — blok mcp_servers (cek dokumentasi versi Anda)', 'dci-mcp-bridge' ),
			'code' => "mcp_servers:\n  {$mcp_name}:\n    url: \"{$endpoint_url}\"\n    headers:\n      Authorization: \"Basic {$token}\"",
		),
		'autoclaw' => array(
			'label' => __( 'AutoClaw', 'dci-mcp-bridge' ),
			'lokasi' => __( 'UI: Settings → MCP Servers → Add (restart sesi setelah simpan)', 'dci-mcp-bridge' ),
			'code' => "{\n  \"mcpServers\": {\n    \"{$mcp_name}\": {\n      \"type\": \"http\",\n      \"url\": \"{$endpoint_url}\",\n      \"headers\": {\n        \"Authorization\": \"Basic {$token}\"\n      }\n    }\n  }\n}",
		),
		'zcode' => array(
			'label' => __( 'Z Code', 'dci-mcp-bridge' ),
			'lokasi' => __( '~/.zcode/cli/config.json — di dalam objek "mcp" → "servers"', 'dci-mcp-bridge' ),
			'code' => "{\n  \"mcp\": {\n    \"servers\": {\n      \"{$mcp_name}\": {\n        \"type\": \"http\",\n        \"url\": \"{$endpoint_url}\",\n        \"headers\": {\n          \"Authorization\": \"Basic {$token}\"\n        }\n      }\n    }\n  }\n}",
		),
		'freebuff' => array(
			'label' => __( 'FreeBuff / Codebuff', 'dci-mcp-bridge' ),
			'lokasi' => __( '.agents/mcp.json (proyek) atau ~/.agents/mcp.json (global) — mendukung rujukan env $VAR', 'dci-mcp-bridge' ),
			'code' => "{\n  \"mcpServers\": {\n    \"{$mcp_name}\": {\n      \"type\": \"http\",\n      \"url\": \"{$endpoint_url}\",\n      \"headers\": {\n        \"Authorization\": \"Basic {$token}\"\n      }\n    }\n  }\n}",
		),
		'custom' => array(
			'label' => __( 'Klien Lain (Custom)', 'dci-mcp-bridge' ),
			'lokasi' => __( 'Pola standar mcpServers — sesuaikan lokasi file dengan klien Anda', 'dci-mcp-bridge' ),
			'code' => "/* Format standar (Claude/Cursor/dkk) */\n{\n  \"mcpServers\": {\n    \"{$mcp_name}\": {\n      \"type\": \"http\",\n      \"url\": \"{$endpoint_url}\",\n      \"headers\": {\n        \"Authorization\": \"Basic {$token}\"\n      }\n    }\n  }\n}\n\n/* Cadangan bila klien hanya mendukung STDIO (perlu Node.js): */\n{\n  \"mcpServers\": {\n    \"{$mcp_name}\": {\n      \"command\": \"npx\",\n      \"args\": [\"-y\", \"mcp-remote\", \"{$endpoint_url}\", \"--header\", \"Authorization: Basic {$token}\"]\n    }\n  }\n}",
		),
	);

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

		<nav class="nav-tab-wrapper" style="margin-bottom:4px;">
			<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_key, $page_base ) ); ?>" class="nav-tab<?php echo $tab_key === $current_tab ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $tab_label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if ( 'status' === $current_tab ) : ?>
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
		<?php endif; ?>

		<?php if ( 'integritas' === $current_tab ) : ?>
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
		<?php endif; ?>

		<?php if ( 'koneksi' === $current_tab ) : ?>
		<div class="dci-card">
			<h2><span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Menghubungkan AI Agent', 'dci-mcp-bridge' ); ?></h2>
			<p style="margin-top:0;"><?php esc_html_e( 'Endpoint MCP server (protokol streamable HTTP, revisi 2025-11-25 / 2026-07-28):', 'dci-mcp-bridge' ); ?></p>
			<p><code class="dci-code"><?php echo esc_url( $endpoint_url ); ?></code></p>
			<p style="margin-top:0;">
				<?php
				printf(
					/* translators: %s: nama server MCP. */
					esc_html__( 'Nama server MCP yang tercantum di semua potongan: %s — domain situs ini + penanda "-wordpress" agar jelas server ini menghubungkan AI ke WordPress situs Anda (bisa diganti lewat konstanta DCI_MCP_SERVER_NAME di wp-config.php).', 'dci-mcp-bridge' ),
					'<code class="dci-code">' . esc_html( $mcp_name ) . '</code>'
				);
				?>
			</p>
			<ol style="margin-bottom:12px;">
				<li><?php esc_html_e( 'Siapkan user WordPress khusus untuk AI (disarankan peran Editor, bukan Administrator).', 'dci-mcp-bridge' ); ?></li>
				<li><?php esc_html_e( 'Buat Application Password: Users → Profile → Application Passwords, lalu salin.', 'dci-mcp-bridge' ); ?></li>
				<li><?php esc_html_e( 'Isi keduanya di bawah → potongan konfigurasi terisi otomatis → salin ke klien AI pilihan Anda.', 'dci-mcp-bridge' ); ?></li>
			</ol>

			<table class="form-table" role="presentation" style="max-width:640px;">
				<tr>
					<th scope="row"><label for="dci-mcp-user"><?php esc_html_e( 'Username WordPress', 'dci-mcp-bridge' ); ?></label></th>
					<td><input type="text" id="dci-mcp-user" class="regular-text" autocomplete="off" placeholder="mis. johndoe" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="dci-mcp-pass"><?php esc_html_e( 'Application Password', 'dci-mcp-bridge' ); ?></label></th>
					<td><input type="password" id="dci-mcp-pass" class="regular-text code" autocomplete="off" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" /></td>
				</tr>
			</table>
			<p class="description" style="margin-top:0;">
				<?php esc_html_e( 'Keduanya hanya dipakai di browser ini untuk menghitung header autentikasi — tidak dikirim ke mana pun, tidak disimpan, dan hilang saat halaman ditutup.', 'dci-mcp-bridge' ); ?>
			</p>

			<?php foreach ( $snippets as $snippet ) : ?>
				<details style="margin:10px 0;border:1px solid #dcdcde;border-radius:6px;padding:10px 14px;background:#f6f7f7;">
					<summary style="cursor:pointer;font-weight:600;"><?php echo esc_html( $snippet['label'] ); ?></summary>
					<p class="description" style="margin:8px 0 4px;"><?php echo esc_html( $snippet['lokasi'] ); ?></p>
					<div style="position:relative;">
						<button type="button" class="button dci-copy" style="position:absolute;top:8px;right:8px;"><?php esc_html_e( 'Salin', 'dci-mcp-bridge' ); ?></button>
						<pre class="dci-snip" style="margin:6px 0 0;overflow:auto;padding:14px;background:#1d2327;color:#d4d4d4;border-radius:6px;"><code><?php echo esc_html( $snippet['code'] ); ?></code></pre>
					</div>
				</details>
			<?php endforeach; ?>

			<p class="description" style="margin-bottom:0;">
				<?php esc_html_e( 'Keamanan: header Authorization ini setara username + Application Password — jangan pernah dibagikan atau di-commit ke repo. Gunakan Application Password khusus (bukan password utama) dan cabut bila tidak dipakai.', 'dci-mcp-bridge' ); ?>
			</p>
		</div>
		<?php endif; ?>

		<?php if ( 'panduan' === $current_tab ) : ?>
		<div class="dci-card">
			<h2><span class="dashicons dashicons-book"></span> <?php esc_html_e( 'Alur Kerja Standar', 'dci-mcp-bridge' ); ?></h2>
			<p style="margin-top:0;"><?php esc_html_e( 'Plugin ini adalah "tangan"; AI agent adalah "otak". Anda cukup memberi perintah dalam bahasa sehari-hari — AI menjalankan seluruh tahap di bawah ini secara otomatis:', 'dci-mcp-bridge' ); ?></p>
			<ol>
				<li><?php esc_html_e( 'Membaca setelan SEO situs ini (Rank Math) agar konten selaras sejak awal.', 'dci-mcp-bridge' ); ?></li>
				<li><?php esc_html_e( 'Mencari data/sumber nyata di internet sebagai referensi (klaim angka wajib bersumber).', 'dci-mcp-bridge' ); ?></li>
				<li><?php esc_html_e( 'Menulis artikel: answer-first, hierarki heading, tautan internal, bebas frasa klise AI.', 'dci-mcp-bridge' ); ?></li>
				<li><?php esc_html_e( 'Menyimpan sebagai DRAF — tidak pernah langsung terbit.', 'dci-mcp-bridge' ); ?></li>
				<li><?php esc_html_e( 'Audit 14 pemeriksaan on-page; gagal → diperbaiki → audit ulang sampai bersih.', 'dci-mcp-bridge' ); ?></li>
				<li><?php esc_html_e( 'Mengisi meta SEO Rank Math (judul, deskripsi, kata kunci, OG, robots).', 'dci-mcp-bridge' ); ?></li>
				<li><?php esc_html_e( 'Pemeriksaan integritas sekali menjelang final (deteksi AI + plagiarisme — berbiaya kredit Winston).', 'dci-mcp-bridge' ); ?></li>
				<li><?php esc_html_e( 'Terbit hanya atas perintah eksplisit Anda — atau tetap draf untuk ditinjau manual.', 'dci-mcp-bridge' ); ?></li>
			</ol>
		</div>

		<div class="dci-card">
			<h2><span class="dashicons dashicons-format-chat"></span> <?php esc_html_e( 'Contoh Perintah (tinggal salin ke AI agent Anda)', 'dci-mcp-bridge' ); ?></h2>
			<p style="margin-top:0;"><?php esc_html_e( 'Ganti bagian dalam [kurung siku] sesuai kebutuhan, lalu tempel apa adanya ke AI agent Anda — tidak perlu istilah teknis, agent memilih alat yang tepat sendiri.', 'dci-mcp-bridge' ); ?></p>
			<p style="background:#f0f6fc;border-left:4px solid #2271b1;padding:10px 14px;border-radius:4px;">
				<strong><?php esc_html_e( 'Kebiasaan emas: sebutkan nama situsnya.', 'dci-mcp-bridge' ); ?></strong>
				<?php esc_html_e( 'Kalau aplikasi AI Anda terhubung ke lebih dari satu situs, selalu awali perintah dengan nama situs (contoh di bawah memakai [Nama Situs]) — begitu juga saat menindaklanjuti pembicaraan lama. Kalau hanya satu situs yang terhubung, tidak wajib.', 'dci-mcp-bridge' ); ?>
			</p>
			<pre class="dci-snip" style="overflow:auto;padding:14px;background:#1d2327;color:#d4d4d4;border-radius:6px;"><code><?php echo esc_html( "1) Membuat artikel baru (selalu masuk draft dulu):\n   \"Buatkan artikel tentang [topik] untuk situs [Nama Situs],\n    sekitar [800] kata. Pakai data aktual dari internet sebagai\n    referensi, sertakan tautan ke artikel terkait di situs itu.\"\n\n2) Mengaudit dan memperbaiki artikel yang sudah ada:\n   \"Cek artikel [judul artikel] di situs [Nama Situs]:\n    audit SEO-nya, lalu perbaiki semua yang bermasalah.\"\n\n3) Menelusuri isi situs sendiri (tanpa browsing, tidak mungkin salah domain):\n   \"Di situs [Nama Situs], telusuri semua artikel dan halaman:\n    ada nomor telepon lain selain [nomor default]? Sebutkan di artikel mana.\"\n\n4) Pemeriksaan akhir sebelum terbit (memakai kredit Winston):\n   \"Untuk artikel [judul] di [Nama Situs]: periksa dulu apakah\n    terdeteksi AI atau ada plagiarisme. Laporkan skornya.\"\n\n5) Meminta revisi gaya bahasa:\n   \"Bagian pembuka artikel [judul] di [Nama Situs] masih terasa\n    kaku. Tulis ulang biar lebih mengalir seperti orang bicara.\"\n\n6) Memperbaiki artikel yang SUDAH TERBIT (perubahan langsung live,\n    WordPress menyimpan snapshot revisi otomatis sebagai titik pemulihan):\n   \"Perbaiki artikel terbit [judul] di [Nama Situs]: [perbaikan yang diminta].\n    Ingat ini artikel live, ubah satu bagian dulu.\"\n\n7) Menerbitkan (hanya kalau Anda benar-benar yakin):\n   \"Terbitkan artikel [judul] di [Nama Situs] sekarang.\"" ); ?></code></pre>
			<p class="description" style="margin-bottom:0;">
				<?php esc_html_e( 'Pola dasarnya selalu sama: SEBUT SITUSNYA → sebut tugasnya → sebatas hasil yang diinginkan (draft atau terbit). Pemeriksaan integritas (nomor 4) memakai kredit Winston AI per kata — mintalah sekali menjelang final, bukan di setiap revisi.', 'dci-mcp-bridge' ); ?>
			</p>
		</div>

		<div class="dci-card">
			<h2><span class="dashicons dashicons-location-alt"></span> <?php esc_html_e( 'Bagaimana AI Mengenali Situs Ini?', 'dci-mcp-bridge' ); ?></h2>
			<p style="margin-top:0;">
				<?php
				printf(
					/* translators: %s: nama server MCP. */
					esc_html__( 'Setiap AI client yang terhubung otomatis menerima identitas situs ini (nama, URL, dan domain resmi %1$s) saat handshake — termasuk peringatan agar tidak mengganti domain (mis. .co.id menjadi .com). Nama server yang tercantum di aplikasi AI Anda: %2$s (lihat tab Koneksi AI Agent untuk menggantinya).', 'dci-mcp-bridge' ),
					'<code class="dci-code">' . esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</code>',
					'<code class="dci-code">' . esc_html( $mcp_name ) . '</code>'
				);
				?>
			</p>
			<p class="description" style="margin-bottom:0;">
				<?php esc_html_e( 'Untuk tugas yang menyangkut isi situs sendiri, mintalah AI memakai dci/search-content dan dci/get-content (membaca database langsung) — bukan browsing — sehingga tidak mungkin salah domain. Menyebut domain di prompt juga tetap membantu: "…pada situs www.namadomain.co.id".', 'dci-mcp-bridge' ); ?>
			</p>
		</div>
		<?php endif; ?>

		<?php if ( 'tentang' === $current_tab ) : ?>
		<div class="dci-card">
			<h2><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Tentang DCI MCP Bridge', 'dci-mcp-bridge' ); ?></h2>
			<p style="margin-top:0;">
				<strong>DCI MCP Bridge v<?php echo esc_html( DCI_MCP_BRIDGE_VERSION ); ?></strong> —
				<?php esc_html_e( 'plugin pendamping MCP Adapter resmi WordPress. Ia mengamankan gerbang MCP situs ini dan mengekspos 8 kemampuan produksi konten SEO (generate, buat/perbaiki draf, set meta Rank Math, audit on-page, deteksi AI + plagiarisme, terbit, baca konfigurasi SEO) melalui Abilities API WordPress 6.9+.', 'dci-mcp-bridge' ); ?>
			</p>
			<p><?php esc_html_e( 'Prinsip desain: plugin adalah tangan, AI agent adalah otak. Setiap penulisan konten selalu berhenti di draf; penerbitan adalah aksi eksplisit terpisah; kredensial tidak pernah bocor ke AI.', 'dci-mcp-bridge' ); ?></p>
			<table>
				<tbody>
					<tr><th scope="row" style="width:180px;"><?php esc_html_e( 'Pengembang', 'dci-mcp-bridge' ); ?></th><td><?php esc_html_e( 'Mas Wondho — Duta Corpora Indonesia', 'dci-mcp-bridge' ); ?></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Kode sumber', 'dci-mcp-bridge' ); ?></th><td><a href="https://github.com/alecslacker/dci-wp-ai-bridge" target="_blank" rel="noopener noreferrer">github.com/alecslacker/dci-wp-ai-bridge</a></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Lisensi', 'dci-mcp-bridge' ); ?></th><td><?php esc_html_e( 'GPL-2.0-or-later (standar plugin WordPress)', 'dci-mcp-bridge' ); ?></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Dependensi', 'dci-mcp-bridge' ); ?></th><td><?php esc_html_e( 'MCP Adapter (wajib), WordPress 6.9+, PHP 7.4+. Opsional: AI Puffer (generate), Rank Math (meta SEO), Winston AI (integritas).', 'dci-mcp-bridge' ); ?></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Layanan pihak ketiga', 'dci-mcp-bridge' ); ?></th><td><?php esc_html_e( 'Winston AI (deteksi AI & plagiarisme) — teks dikirim hanya saat pemeriksaan integritas dijalankan, atas permintaan eksplisit.', 'dci-mcp-bridge' ); ?></td></tr>
				</tbody>
			</table>
		</div>

		<div class="dci-card">
			<h2><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Pembaruan Otomatis dari GitHub', 'dci-mcp-bridge' ); ?></h2>
			<?php
			$latest_rel = dci_mcp_bridge_fetch_latest_release();
			if ( null === $latest_rel ) {
				echo '<p style="margin-top:0;"><span class="dci-state dci-state-perlu-konfigurasi">' . esc_html__( 'BELUM TERSEDIA', 'dci-mcp-bridge' ) . '</span> ';
				esc_html_e( 'Belum ada rilis GitHub yang bisa dibaca. Jika repo bersifat privat, isi token di bawah; jika sudah ada rilis, pastikan aset dci-mcp-bridge.zip dilampirkan pada rilis tersebut.', 'dci-mcp-bridge' );
				echo '</p>';
			} elseif ( version_compare( DCI_MCP_BRIDGE_VERSION, $latest_rel['version'], '>=' ) ) {
				echo '<p style="margin-top:0;"><span class="dci-state dci-state-aktif">' . esc_html__( 'TERBARU', 'dci-mcp-bridge' ) . '</span> ';
				printf(
					/* translators: %s: versi terpasang. */
					esc_html__( 'Versi terpasang v%s adalah yang terbaru dari GitHub Releases.', 'dci-mcp-bridge' ),
					esc_html( DCI_MCP_BRIDGE_VERSION )
				);
				echo '</p>';
			} else {
				echo '<p style="margin-top:0;"><span class="dci-state dci-state-perlu-konfigurasi">' . esc_html__( 'PEMBARUAN TERSEDIA', 'dci-mcp-bridge' ) . '</span> ';
				printf(
					/* translators: 1: versi terpasang, 2: versi terbaru. */
					esc_html__( 'v%1$s → v%2$s — buka menu Dashboard → Updates untuk memasang, atau perbarui dari menu Plugins seperti plugin biasa.', 'dci-mcp-bridge' ),
					esc_html( DCI_MCP_BRIDGE_VERSION ),
					esc_html( $latest_rel['version'] )
				);
				echo '</p>';
			}
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="dci_bridge_save_github_token" />
				<?php wp_nonce_field( 'dci_bridge_save_github_token' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dci_github_token"><?php esc_html_e( 'Token GitHub (opsional)', 'dci-mcp-bridge' ); ?></label></th>
						<td>
							<input type="text" id="dci_github_token" name="dci_github_token" class="regular-text code" value="" autocomplete="off" placeholder="<?php esc_attr_e( 'Hanya perlu bila repo privat — fine-grained PAT, Contents: Read-only', 'dci-mcp-bridge' ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Plugin memeriksa GitHub Releases (cache 1 jam) dan bila ada versi baru, pembaruan muncul di halaman Updates WordPress seperti plugin resmi. Token tersimpan tersembunyi dan hanya dipakai untuk membaca rilis.', 'dci-mcp-bridge' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Simpan Token', 'dci-mcp-bridge' ), 'secondary', 'submit', true ); ?>
			</form>
		</div>
		<?php endif; ?>

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
	<script>
	( function () {
		'use strict';
		var userEl  = document.getElementById( 'dci-mcp-user' );
		var passEl  = document.getElementById( 'dci-mcp-pass' );
		var snips   = document.querySelectorAll( '.dci-snip' );
		var PH      = '__BASIC_AUTH__';

		function originals() {
			snips.forEach( function ( pre ) {
				if ( ! pre.dataset.orig ) { pre.dataset.orig = pre.textContent; }
			} );
		}

		function render() {
			var u = userEl ? userEl.value.trim() : '';
			var p = passEl ? passEl.value : '';
			var token = '';
			if ( u && p ) {
				try { token = window.btoa( u + ':' + p ); } catch ( e ) { token = ''; }
			}
			snips.forEach( function ( pre ) {
				if ( ! pre.dataset.orig ) { pre.dataset.orig = pre.textContent; }
				pre.textContent = token ? pre.dataset.orig.split( PH ).join( token ) : pre.dataset.orig;
			} );
		}

		if ( userEl && passEl && snips.length ) {
			originals();
			userEl.addEventListener( 'input', render );
			passEl.addEventListener( 'input', render );

			document.querySelectorAll( '.dci-copy' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var pre = btn.closest( 'div' ).querySelector( '.dci-snip' );
					if ( ! pre ) { return; }
					if ( pre.textContent.indexOf( PH ) !== -1 ) { return; }
					if ( navigator.clipboard && navigator.clipboard.writeText ) {
						navigator.clipboard.writeText( pre.textContent );
						btn.textContent = '<?php echo esc_js( __( 'Tersalin ✓', 'dci-mcp-bridge' ) ); ?>';
						setTimeout( function () { btn.textContent = '<?php echo esc_js( __( 'Salin', 'dci-mcp-bridge' ) ); ?>'; }, 1600 );
					}
				} );
			} );
		}
	} )();
	</script>
	<?php
}
