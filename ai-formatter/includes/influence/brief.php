<?php
/**
 * SansDoute Influence — Brief management.
 *
 * CRUD operations for campaign briefs.
 * Briefs are stored as `aif_brief` custom post type with structured meta.
 *
 * @author Peopleofverso
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta keys for briefs.
 */
function aif_brief_meta_keys(): array {
	return [
		'_aif_brand',
		'_aif_keywords_required',
		'_aif_keywords_forbidden',
		'_aif_links_required',
		'_aif_tone',
		'_aif_legal_mentions',
		'_aif_deadline',
		'_aif_publish_date',
		'_aif_budget',
	];
}

/**
 * Create or update a brief.
 *
 * @param array $data Brief data.
 * @param int   $brief_id Existing brief ID (0 for new).
 * @return int|WP_Error The brief post ID or error.
 */
function aif_brief_save( array $data, int $brief_id = 0 ) {
	$title       = sanitize_text_field( $data['title'] ?? '' );
	$description = wp_kses_post( $data['description'] ?? '' );

	if ( empty( $title ) ) {
		return new WP_Error( 'aif_brief_no_title', 'Le nom de la campagne est requis.' );
	}

	$post_data = [
		'post_type'    => 'aif_brief',
		'post_title'   => $title,
		'post_content' => $description,
		'post_status'  => 'publish',
	];

	if ( $brief_id > 0 ) {
		$post_data['ID'] = $brief_id;
		$result = wp_update_post( $post_data, true );
	} else {
		$result = wp_insert_post( $post_data, true );
	}

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$post_id = (int) $result;

	/* Save meta fields */
	$brand = sanitize_text_field( $data['brand'] ?? '' );
	update_post_meta( $post_id, '_aif_brand', $brand );

	$keywords_req = aif_sanitize_csv( $data['keywords_required'] ?? '' );
	update_post_meta( $post_id, '_aif_keywords_required', $keywords_req );

	$keywords_forb = aif_sanitize_csv( $data['keywords_forbidden'] ?? '' );
	update_post_meta( $post_id, '_aif_keywords_forbidden', $keywords_forb );

	$links = aif_sanitize_links( $data['links_required'] ?? '' );
	update_post_meta( $post_id, '_aif_links_required', $links );

	$tone = sanitize_text_field( $data['tone'] ?? 'neutre' );
	$valid_tones = [ 'neutre', 'journalistique', 'gonzo', 'corporate', 'informel' ];
	if ( ! in_array( $tone, $valid_tones, true ) ) {
		$tone = 'neutre';
	}
	update_post_meta( $post_id, '_aif_tone', $tone );

	$legal = aif_sanitize_lines( $data['legal_mentions'] ?? '' );
	update_post_meta( $post_id, '_aif_legal_mentions', $legal );

	$deadline = sanitize_text_field( $data['deadline'] ?? '' );
	update_post_meta( $post_id, '_aif_deadline', $deadline );

	$publish_date = sanitize_text_field( $data['publish_date'] ?? '' );
	update_post_meta( $post_id, '_aif_publish_date', $publish_date );

	$budget = sanitize_text_field( $data['budget'] ?? '' );
	update_post_meta( $post_id, '_aif_budget', $budget );

	return $post_id;
}

/**
 * Get a brief as a structured array.
 *
 * @param int $brief_id Post ID.
 * @return array|null Brief data or null.
 */
function aif_brief_get( int $brief_id ): ?array {
	$post = get_post( $brief_id );
	if ( ! $post || $post->post_type !== 'aif_brief' ) {
		return null;
	}

	return [
		'id'                 => $post->ID,
		'title'              => $post->post_title,
		'description'        => $post->post_content,
		'status'             => $post->post_status,
		'author'             => (int) $post->post_author,
		'brand'              => get_post_meta( $post->ID, '_aif_brand', true ) ?: '',
		'keywords_required'  => get_post_meta( $post->ID, '_aif_keywords_required', true ) ?: [],
		'keywords_forbidden' => get_post_meta( $post->ID, '_aif_keywords_forbidden', true ) ?: [],
		'links_required'     => get_post_meta( $post->ID, '_aif_links_required', true ) ?: [],
		'tone'               => get_post_meta( $post->ID, '_aif_tone', true ) ?: 'neutre',
		'legal_mentions'     => get_post_meta( $post->ID, '_aif_legal_mentions', true ) ?: [],
		'deadline'           => get_post_meta( $post->ID, '_aif_deadline', true ) ?: '',
		'publish_date'       => get_post_meta( $post->ID, '_aif_publish_date', true ) ?: '',
		'budget'             => get_post_meta( $post->ID, '_aif_budget', true ) ?: '',
		'created'            => $post->post_date,
		'collab_count'       => aif_brief_collab_count( $post->ID ),
	];
}

/**
 * List all briefs.
 *
 * @param array $args Optional WP_Query args overrides.
 * @return array List of brief arrays.
 */
function aif_brief_list( array $args = [] ): array {
	$defaults = [
		'post_type'      => 'aif_brief',
		'posts_per_page' => 50,
		'post_status'    => 'any',
		'orderby'        => 'date',
		'order'          => 'DESC',
	];

	$query = new WP_Query( array_merge( $defaults, $args ) );
	$briefs = [];

	foreach ( $query->posts as $post ) {
		$briefs[] = aif_brief_get( $post->ID );
	}

	return $briefs;
}

/**
 * Count collabs for a brief.
 */
function aif_brief_collab_count( int $brief_id ): int {
	$query = new WP_Query( [
		'post_type'      => 'aif_collab',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => [
			[
				'key'   => '_aif_brief_id',
				'value' => $brief_id,
				'type'  => 'NUMERIC',
			],
		],
	] );

	return $query->found_posts;
}

/* ================================================================ */
/*  Helpers                                                          */
/* ================================================================ */

/**
 * Parse comma-separated string into array.
 */
function aif_sanitize_csv( $input ): array {
	if ( is_array( $input ) ) {
		return array_values( array_filter( array_map( 'sanitize_text_field', $input ) ) );
	}
	if ( ! is_string( $input ) || trim( $input ) === '' ) {
		return [];
	}
	$parts = explode( ',', $input );
	return array_values( array_filter( array_map( function ( $s ) {
		return sanitize_text_field( trim( $s ) );
	}, $parts ) ) );
}

/**
 * Parse newline-separated URLs into array.
 */
function aif_sanitize_links( $input ): array {
	if ( is_array( $input ) ) {
		return array_values( array_filter( array_map( 'esc_url_raw', $input ) ) );
	}
	if ( ! is_string( $input ) || trim( $input ) === '' ) {
		return [];
	}
	$lines = preg_split( '/[\r\n]+/', $input );
	return array_values( array_filter( array_map( function ( $s ) {
		$s = trim( $s );
		return $s ? esc_url_raw( $s ) : '';
	}, $lines ) ) );
}

/**
 * Parse newline-separated text into array.
 */
function aif_sanitize_lines( $input ): array {
	if ( is_array( $input ) ) {
		return array_values( array_filter( array_map( 'sanitize_text_field', $input ) ) );
	}
	if ( ! is_string( $input ) || trim( $input ) === '' ) {
		return [];
	}
	$lines = preg_split( '/[\r\n]+/', $input );
	return array_values( array_filter( array_map( function ( $s ) {
		return sanitize_text_field( trim( $s ) );
	}, $lines ) ) );
}
