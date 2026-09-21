<?php
/**
 * Plugin Name:       Hermes SEO Abilities
 * Description:       A minimal set of Abilities for an SEO agent. Drafts only — no publishing, deletion, media, or settings.
 * Version:           1.2.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            DD Systems - dedykowane systemy IT
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hermes-seo
 * Domain Path:       /languages
 *
 * DESIGN PRINCIPLE
 * The agent can only do what is registered here. There is no "publish",
 * "delete", or "change settings" ability — because none were written.
 * Post status is hard-coded to 'draft' in the code and is NOT accepted
 * from input.
 *
 * YOAST SEO
 * Three fields are supported: SEO title, meta description, and focus
 * keyword. Canonical, robots, and indexing settings are deliberately
 * NOT included — the agent should not be able to de-index a post or
 * redirect it off-domain.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads translations from /languages.
 */
add_action( 'plugins_loaded', 'hermes_seo_load_textdomain' );
function hermes_seo_load_textdomain() {
	load_plugin_textdomain( 'hermes-seo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * The Abilities API has been part of WordPress core since 6.9 (the
 * "Requires at least" header should enforce this on activation via
 * wp-admin). This notice is purely a safety net in case the plugin
 * gets activated outside the normal path (e.g. manual file upload,
 * WP-CLI against an older core).
 */
add_action( 'admin_notices', 'hermes_seo_maybe_notice_missing_abilities_api' );
function hermes_seo_maybe_notice_missing_abilities_api() {
	if ( function_exists( 'wp_register_ability' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'Hermes SEO Abilities requires WordPress 6.9 or later (the Abilities API has been part of core since that version). Please update WordPress for this plugin to work.', 'hermes-seo' )
	);
}

/**
 * Category for all abilities registered by this plugin.
 */
add_action( 'wp_abilities_api_categories_init', 'hermes_seo_register_category' );
function hermes_seo_register_category() {
	wp_register_ability_category(
		'hermes-seo',
		array(
			'label'       => __( 'Hermes SEO', 'hermes-seo' ),
			'description' => __( 'Editorial operations for the SEO agent — drafts only.', 'hermes-seo' ),
		)
	);
}

/**
 * Shared metadata: ability visible to both REST and MCP.
 * 'public' is the unified flag (WP 7.1+), 'mcp.public' is the older,
 * still-respected key. We set both so it works regardless of version.
 */
function hermes_seo_meta( $readonly = false, $destructive = false, $idempotent = false ) {
	return array(
		'public'       => true,
		'show_in_rest' => true,
		'mcp'          => array(
			'public' => true,
			'type'   => 'tool',
		),
		'annotations'  => array(
			'readonly'    => $readonly,
			'destructive' => $destructive,
			'idempotent'  => $idempotent,
		),
	);
}

/**
 * Read permission — anyone who can edit posts.
 */
function hermes_seo_can_read() {
	return current_user_can( 'edit_posts' );
}

add_action( 'wp_abilities_api_init', 'hermes_seo_register_abilities' );
function hermes_seo_register_abilities() {

	/* ------------------------------------------------------------------
	 * 1. CREATE DRAFT
	 * ------------------------------------------------------------------ */
	wp_register_ability(
		'hermes-seo/create-draft',
		array(
			'label'       => __( 'Create draft post', 'hermes-seo' ),
			'description' => __( 'Creates a new post ALWAYS as a draft. Publishing is impossible through this ability.', 'hermes-seo' ),
			'category'    => 'hermes-seo',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'title' => array(
						'type'        => 'string',
						'description' => 'Post title.',
						'minLength'   => 1,
						'maxLength'   => 300,
					),
					'content' => array(
						'type'        => 'string',
						'description' => 'Post content in HTML or Gutenberg blocks.',
						'minLength'   => 1,
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'URL slug. Optional — WordPress will generate one from the title.',
					),
					'excerpt' => array(
						'type'        => 'string',
						'description' => 'Post excerpt (post_excerpt). Shown on archive listings.',
						'maxLength'   => 300,
					),
					'seo_title' => array(
						'type'        => 'string',
						'description' => 'Yoast SEO title (<title> tag). Recommended up to 60 characters.',
						'maxLength'   => 200,
					),
					'meta_description' => array(
						'type'        => 'string',
						'description' => 'Yoast meta description. Recommended up to 155 characters.',
						'maxLength'   => 300,
					),
					'focus_keyword' => array(
						'type'        => 'string',
						'description' => 'Yoast focus keyword.',
						'maxLength'   => 200,
					),
					'category_ids' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => 'Category IDs. Fetch them via list-categories.',
					),
					'tag_ids' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => 'Tag IDs. Fetch them via list-tags.',
					),
				),
				'required'             => array( 'title', 'content' ),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'      => array( 'type' => 'integer' ),
					'status'       => array( 'type' => 'string' ),
					'slug'         => array( 'type' => 'string' ),
					'edit_url'     => array( 'type' => 'string' ),
					'preview_url'  => array( 'type' => 'string' ),
					'yoast_active' => array( 'type' => 'boolean', 'description' => 'Whether Yoast SEO is active.' ),
					'yoast_set'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Which Yoast fields were saved.' ),
				),
			),
			'execute_callback'    => 'hermes_seo_create_draft',
			'permission_callback' => 'hermes_seo_can_read',
			'meta'                => hermes_seo_meta( false, false, false ),
		)
	);

	/* ------------------------------------------------------------------
	 * 2. UPDATE DRAFT
	 * ------------------------------------------------------------------ */
	wp_register_ability(
		'hermes-seo/update-draft',
		array(
			'label'       => __( 'Update draft', 'hermes-seo' ),
			'description' => __( 'Updates an existing draft. Refuses if the post is already published.', 'hermes-seo' ),
			'category'    => 'hermes-seo',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'          => array( 'type' => 'integer', 'description' => 'ID of the post to update.' ),
					'title'            => array( 'type' => 'string', 'maxLength' => 300 ),
					'content'          => array( 'type' => 'string' ),
					'slug'             => array( 'type' => 'string', 'maxLength' => 200, 'description' => 'New URL slug.' ),
					'excerpt'          => array( 'type' => 'string', 'maxLength' => 300 ),
					'seo_title'        => array( 'type' => 'string', 'maxLength' => 200, 'description' => 'Yoast SEO title.' ),
					'meta_description' => array( 'type' => 'string', 'maxLength' => 300, 'description' => 'Yoast meta description.' ),
					'focus_keyword'    => array( 'type' => 'string', 'maxLength' => 200, 'description' => 'Yoast focus keyword.' ),
					'category_ids'     => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
					'tag_ids'          => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
				),
				'required'             => array( 'post_id' ),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'      => array( 'type' => 'integer' ),
					'status'       => array( 'type' => 'string' ),
					'slug'         => array( 'type' => 'string' ),
					'updated'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'yoast_active' => array( 'type' => 'boolean' ),
				),
			),
			'execute_callback'    => 'hermes_seo_update_draft',
			'permission_callback' => 'hermes_seo_can_read',
			'meta'                => hermes_seo_meta( false, false, true ),
		)
	);

	/* ------------------------------------------------------------------
	 * 3. LIST CATEGORIES
	 * ------------------------------------------------------------------ */
	wp_register_ability(
		'hermes-seo/list-categories',
		array(
			'label'       => __( 'List categories', 'hermes-seo' ),
			'description' => __( 'Returns categories that exist on the site, with IDs and post counts.', 'hermes-seo' ),
			'category'    => 'hermes-seo',
			'input_schema' => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer' ),
						'name'  => array( 'type' => 'string' ),
						'slug'  => array( 'type' => 'string' ),
						'count' => array( 'type' => 'integer' ),
					),
				),
			),
			'execute_callback'    => 'hermes_seo_list_categories',
			'permission_callback' => 'hermes_seo_can_read',
			'meta'                => hermes_seo_meta( true, false, true ),
		)
	);

	/* ------------------------------------------------------------------
	 * 4. LIST TAGS
	 * ------------------------------------------------------------------ */
	wp_register_ability(
		'hermes-seo/list-tags',
		array(
			'label'       => __( 'List tags', 'hermes-seo' ),
			'description' => __( 'Returns tags that exist on the site, with IDs and post counts.', 'hermes-seo' ),
			'category'    => 'hermes-seo',
			'input_schema' => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer' ),
						'name'  => array( 'type' => 'string' ),
						'slug'  => array( 'type' => 'string' ),
						'count' => array( 'type' => 'integer' ),
					),
				),
			),
			'execute_callback'    => 'hermes_seo_list_tags',
			'permission_callback' => 'hermes_seo_can_read',
			'meta'                => hermes_seo_meta( true, false, true ),
		)
	);

	/* ------------------------------------------------------------------
	 * 5. SEARCH POSTS (for internal linking)
	 * ------------------------------------------------------------------ */
	wp_register_ability(
		'hermes-seo/search-posts',
		array(
			'label'       => __( 'Search posts', 'hermes-seo' ),
			'description' => __( 'Searches published posts by keyword. Used for suggesting internal links.', 'hermes-seo' ),
			'category'    => 'hermes-seo',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'query' => array(
						'type'        => 'string',
						'description' => 'Phrase to search for in titles and content.',
						'minLength'   => 2,
						'maxLength'   => 200,
					),
					'limit' => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results (1-20).',
						'minimum'     => 1,
						'maximum'     => 20,
						'default'     => 10,
					),
				),
				'required'             => array( 'query' ),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer' ),
						'title' => array( 'type' => 'string' ),
						'url'   => array( 'type' => 'string' ),
						'date'  => array( 'type' => 'string' ),
					),
				),
			),
			'execute_callback'    => 'hermes_seo_search_posts',
			'permission_callback' => 'hermes_seo_can_read',
			'meta'                => hermes_seo_meta( true, false, true ),
		)
	);

	/* ------------------------------------------------------------------
	 * 6. LIST DRAFTS (duplicate check)
	 * ------------------------------------------------------------------ */
	wp_register_ability(
		'hermes-seo/list-drafts',
		array(
			'label'       => __( 'List drafts', 'hermes-seo' ),
			'description' => __( 'Returns drafts (draft/pending) visible to the current account. Use it to check whether a post with a given slug or title already exists before creating a new one.', 'hermes-seo' ),
			'category'    => 'hermes-seo',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'search' => array(
						'type'        => 'string',
						'description' => 'Optional phrase to filter by title/content.',
						'maxLength'   => 200,
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'Optional exact slug to check.',
						'maxLength'   => 200,
					),
					'limit' => array(
						'type'        => 'integer',
						'description' => 'Maximum number of results (1-50).',
						'minimum'     => 1,
						'maximum'     => 50,
						'default'     => 20,
					),
				),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer' ),
						'title'    => array( 'type' => 'string' ),
						'slug'     => array( 'type' => 'string' ),
						'status'   => array( 'type' => 'string' ),
						'modified' => array( 'type' => 'string' ),
						'edit_url' => array( 'type' => 'string' ),
					),
				),
			),
			'execute_callback'    => 'hermes_seo_list_drafts',
			'permission_callback' => 'hermes_seo_can_read',
			'meta'                => hermes_seo_meta( true, false, true ),
		)
	);

	/* ------------------------------------------------------------------
	 * 7. GET DRAFT (verify a save)
	 * ------------------------------------------------------------------ */
	wp_register_ability(
		'hermes-seo/get-draft',
		array(
			'label'       => __( 'Get draft', 'hermes-seo' ),
			'description' => __( 'Returns the full data of a draft: title, slug, content, excerpt, Yoast fields, categories, and tags. Use it to verify that a save succeeded.', 'hermes-seo' ),
			'category'    => 'hermes-seo',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array( 'type' => 'integer', 'description' => 'Post ID.' ),
				),
				'required'             => array( 'post_id' ),
				'additionalProperties' => false,
			),
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'id'               => array( 'type' => 'integer' ),
					'title'            => array( 'type' => 'string' ),
					'slug'             => array( 'type' => 'string' ),
					'status'           => array( 'type' => 'string' ),
					'content'          => array( 'type' => 'string' ),
					'excerpt'          => array( 'type' => 'string' ),
					'seo_title'        => array( 'type' => 'string' ),
					'meta_description' => array( 'type' => 'string' ),
					'focus_keyword'    => array( 'type' => 'string' ),
					'categories'       => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
					'tags'             => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
					'edit_url'         => array( 'type' => 'string' ),
					'preview_url'      => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => 'hermes_seo_get_draft',
			'permission_callback' => 'hermes_seo_can_read',
			'meta'                => hermes_seo_meta( true, false, true ),
		)
	);
}

/* ======================================================================
 * YOAST SEO
 * ====================================================================== */

/**
 * Map: API field name -> Yoast post meta key.
 * Deliberately narrow. No canonical, robots, or schema here — the
 * agent should not be able to de-index a post or redirect it elsewhere.
 */
function hermes_seo_yoast_map() {
	return array(
		'seo_title'        => '_yoast_wpseo_title',
		'meta_description' => '_yoast_wpseo_metadesc',
		'focus_keyword'    => '_yoast_wpseo_focuskw',
	);
}

/**
 * Whether Yoast SEO is active.
 */
function hermes_seo_yoast_active() {
	return defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' );
}

/**
 * Saves Yoast fields for a post. Returns the list of fields actually
 * written. Does not bail out when Yoast is inactive — values still
 * land in post meta and get picked up if Yoast is activated later.
 */
function hermes_seo_apply_yoast( $post_id, $input ) {
	$written = array();

	foreach ( hermes_seo_yoast_map() as $field => $meta_key ) {
		if ( ! isset( $input[ $field ] ) ) {
			continue;
		}

		$value = sanitize_text_field( $input[ $field ] );

		if ( '' === $value ) {
			delete_post_meta( $post_id, $meta_key );
		} else {
			update_post_meta( $post_id, $meta_key, $value );
		}

		$written[] = $field;
	}

	return $written;
}

/**
 * Exposes Yoast keys in the REST API for the 'post' type.
 * This makes them visible via wp/v2/posts too, which makes
 * verification easier.
 */
add_action( 'init', 'hermes_seo_register_yoast_meta' );
function hermes_seo_register_yoast_meta() {
	foreach ( hermes_seo_yoast_map() as $meta_key ) {
		register_post_meta(
			'post',
			$meta_key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}

/* ======================================================================
 * IMPLEMENTATIONS
 * ====================================================================== */

/**
 * Creates a draft. Status is hard-coded — input cannot control it.
 */
function hermes_seo_create_draft( $input ) {

	$postarr = array(
		'post_type'    => 'post',
		'post_status'  => 'draft',   // NOT overridable from input. Deliberate.
		'post_title'   => sanitize_text_field( $input['title'] ),
		'post_content' => wp_kses_post( $input['content'] ),
	);

	// Slug: explicit value or generated from the title.
	// Without this, WordPress leaves post_name empty until publish,
	// but SEO content needs a known, approvable slug ahead of time.
	if ( ! empty( $input['slug'] ) ) {
		$postarr['post_name'] = sanitize_title( $input['slug'] );
	} else {
		$postarr['post_name'] = sanitize_title( $input['title'] );
	}

	if ( ! empty( $input['excerpt'] ) ) {
		$postarr['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
	}

	$post_id = wp_insert_post( $postarr, true );

	if ( is_wp_error( $post_id ) ) {
		return new WP_Error(
			'hermes_seo_create_failed',
			$post_id->get_error_message(),
			array( 'status' => 500 )
		);
	}

	// Taxonomies — existing terms only, no new terms are created.
	if ( ! empty( $input['category_ids'] ) ) {
		$ids = array_map( 'absint', (array) $input['category_ids'] );
		wp_set_post_terms( $post_id, $ids, 'category', false );
	}

	if ( ! empty( $input['tag_ids'] ) ) {
		$ids = array_map( 'absint', (array) $input['tag_ids'] );
		wp_set_post_terms( $post_id, $ids, 'post_tag', false );
	}

	// Yoast SEO — SEO title, meta description, focus keyword.
	$yoast_set = hermes_seo_apply_yoast( $post_id, $input );

	$post = get_post( $post_id );

	return array(
		'post_id'      => (int) $post_id,
		'status'       => $post->post_status,
		'slug'         => $post->post_name,
		'edit_url'     => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
		'preview_url'  => get_preview_post_link( $post_id ),
		'yoast_active' => hermes_seo_yoast_active(),
		'yoast_set'    => $yoast_set,
	);
}

/**
 * Updates a draft. Refuses for published posts.
 */
function hermes_seo_update_draft( $input ) {

	$post_id = absint( $input['post_id'] );
	$post    = get_post( $post_id );

	if ( ! $post || 'post' !== $post->post_type ) {
		return new WP_Error(
			'hermes_seo_not_found',
			__( 'No post exists with the given ID.', 'hermes-seo' ),
			array( 'status' => 404 )
		);
	}

	// Hard barrier: never touch what is already live on the site.
	if ( ! in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
		return new WP_Error(
			'hermes_seo_not_a_draft',
			__( 'This post is not a draft. This ability does not modify published content.', 'hermes-seo' ),
			array( 'status' => 403 )
		);
	}

	// Per-post permission check — WordPress itself ensures a Contributor
	// can only touch their own posts.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error(
			'hermes_seo_forbidden',
			__( 'You do not have permission to edit this post.', 'hermes-seo' ),
			array( 'status' => 403 )
		);
	}

	$postarr = array( 'ID' => $post_id );
	$updated = array();

	if ( isset( $input['title'] ) ) {
		$postarr['post_title'] = sanitize_text_field( $input['title'] );
		$updated[]             = 'title';
	}

	if ( isset( $input['content'] ) ) {
		$postarr['post_content'] = wp_kses_post( $input['content'] );
		$updated[]               = 'content';
	}

	if ( isset( $input['slug'] ) && '' !== trim( $input['slug'] ) ) {
		$postarr['post_name'] = sanitize_title( $input['slug'] );
		$updated[]            = 'slug';
	}

	if ( isset( $input['excerpt'] ) ) {
		$postarr['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
		$updated[]               = 'excerpt';
	}

	if ( count( $postarr ) > 1 ) {
		$result = wp_update_post( $postarr, true );
		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'hermes_seo_update_failed',
				$result->get_error_message(),
				array( 'status' => 500 )
			);
		}
	}

	if ( isset( $input['category_ids'] ) ) {
		wp_set_post_terms( $post_id, array_map( 'absint', (array) $input['category_ids'] ), 'category', false );
		$updated[] = 'categories';
	}

	if ( isset( $input['tag_ids'] ) ) {
		wp_set_post_terms( $post_id, array_map( 'absint', (array) $input['tag_ids'] ), 'post_tag', false );
		$updated[] = 'tags';
	}

	$yoast_set = hermes_seo_apply_yoast( $post_id, $input );
	$updated   = array_merge( $updated, $yoast_set );

	return array(
		'post_id'      => $post_id,
		'status'       => get_post_status( $post_id ),
		'slug'         => get_post_field( 'post_name', $post_id ),
		'updated'      => $updated,
		'yoast_active' => hermes_seo_yoast_active(),
	);
}

/**
 * Returns categories.
 */
function hermes_seo_list_categories() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'number'     => 200,
		)
	);

	if ( is_wp_error( $terms ) ) {
		return new WP_Error( 'hermes_seo_terms_failed', $terms->get_error_message(), array( 'status' => 500 ) );
	}

	return array_map(
		function ( $t ) {
			return array(
				'id'    => (int) $t->term_id,
				'name'  => $t->name,
				'slug'  => $t->slug,
				'count' => (int) $t->count,
			);
		},
		$terms
	);
}

/**
 * Returns tags.
 */
function hermes_seo_list_tags() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'post_tag',
			'hide_empty' => false,
			'number'     => 200,
		)
	);

	if ( is_wp_error( $terms ) ) {
		return new WP_Error( 'hermes_seo_terms_failed', $terms->get_error_message(), array( 'status' => 500 ) );
	}

	return array_map(
		function ( $t ) {
			return array(
				'id'    => (int) $t->term_id,
				'name'  => $t->name,
				'slug'  => $t->slug,
				'count' => (int) $t->count,
			);
		},
		$terms
	);
}

/**
 * Searches published posts — material for internal linking.
 */
function hermes_seo_search_posts( $input ) {

	$limit = isset( $input['limit'] ) ? absint( $input['limit'] ) : 10;
	$limit = max( 1, min( 20, $limit ) );

	$query = new WP_Query(
		array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			's'                      => sanitize_text_field( $input['query'] ),
			'posts_per_page'         => $limit,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$out = array();
	foreach ( $query->posts as $p ) {
		$out[] = array(
			'id'    => (int) $p->ID,
			'title' => get_the_title( $p ),
			'url'   => get_permalink( $p ),
			'date'  => get_the_date( 'Y-m-d', $p ),
		);
	}

	return $out;
}

/**
 * Drafts visible to the current account.
 * WP_Query with 'draft'/'pending' already restricts a Contributor to
 * their own posts — no need to filter by author manually.
 */
function hermes_seo_list_drafts( $input ) {

	$input = is_array( $input ) ? $input : array();
	$limit = isset( $input['limit'] ) ? absint( $input['limit'] ) : 20;
	$limit = max( 1, min( 50, $limit ) );

	$args = array(
		'post_type'              => 'post',
		'post_status'            => array( 'draft', 'pending' ),
		'posts_per_page'         => $limit,
		'orderby'                => 'modified',
		'order'                  => 'DESC',
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	);

	// A Contributor sees only their own — explicit, so we don't rely on
	// WP_Query's default behavior across different roles.
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		$args['author'] = get_current_user_id();
	}

	if ( ! empty( $input['slug'] ) ) {
		$args['name'] = sanitize_title( $input['slug'] );
	}

	if ( ! empty( $input['search'] ) ) {
		$args['s'] = sanitize_text_field( $input['search'] );
	}

	$query = new WP_Query( $args );

	$out = array();
	foreach ( $query->posts as $p ) {
		$out[] = array(
			'id'       => (int) $p->ID,
			'title'    => get_the_title( $p ),
			'slug'     => $p->post_name,
			'status'   => $p->post_status,
			'modified' => get_the_modified_date( 'Y-m-d H:i', $p ),
			'edit_url' => admin_url( 'post.php?post=' . $p->ID . '&action=edit' ),
		);
	}

	return $out;
}

/**
 * Full draft data — to verify that a save succeeded.
 * Drafts only: returns an error for published posts, same as update-draft.
 */
function hermes_seo_get_draft( $input ) {

	$post_id = absint( $input['post_id'] );
	$post    = get_post( $post_id );

	if ( ! $post || 'post' !== $post->post_type ) {
		return new WP_Error(
			'hermes_seo_not_found',
			__( 'No post exists with the given ID.', 'hermes-seo' ),
			array( 'status' => 404 )
		);
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error(
			'hermes_seo_forbidden',
			__( 'You do not have permission to read this post.', 'hermes-seo' ),
			array( 'status' => 403 )
		);
	}

	$yoast = array();
	foreach ( hermes_seo_yoast_map() as $field => $meta_key ) {
		$yoast[ $field ] = (string) get_post_meta( $post_id, $meta_key, true );
	}

	return array(
		'id'               => (int) $post_id,
		'title'            => $post->post_title,
		'slug'             => $post->post_name,
		'status'           => $post->post_status,
		'content'          => $post->post_content,
		'excerpt'          => $post->post_excerpt,
		'seo_title'        => $yoast['seo_title'],
		'meta_description' => $yoast['meta_description'],
		'focus_keyword'    => $yoast['focus_keyword'],
		'categories'       => array_map( 'intval', wp_get_post_categories( $post_id ) ),
		'tags'             => array_map( 'intval', wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) ) ),
		'edit_url'         => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
		'preview_url'      => get_preview_post_link( $post_id ),
	);
}
