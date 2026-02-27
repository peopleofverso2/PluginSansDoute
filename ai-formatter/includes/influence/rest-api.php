<?php
/**
 * SansDoute Influence — REST API.
 *
 * Endpoints :
 * POST   /influence/briefs              Creer un brief
 * GET    /influence/briefs              Lister les briefs
 * GET    /influence/briefs/{id}         Detail d'un brief
 * POST   /influence/collabs             Creer / mettre a jour un contenu
 * GET    /influence/collabs             Lister les contenus
 * PATCH  /influence/collabs/{id}/status Changer le statut
 * POST   /influence/check-compliance    Verifier la compliance
 * POST   /influence/score-content       Scorer un contenu
 *
 * @author Peopleofverso
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', function () {
	$ns = 'ai-formatter/v1/influence';

	/* ---- Briefs ---- */
	register_rest_route( $ns, '/briefs', [
		[
			'methods'             => 'POST',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'callback'            => 'aif_inf_rest_create_brief',
		],
		[
			'methods'             => 'GET',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'callback'            => 'aif_inf_rest_list_briefs',
		],
	] );

	register_rest_route( $ns, '/briefs/(?P<id>\d+)', [
		'methods'             => 'GET',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'callback'            => 'aif_inf_rest_get_brief',
	] );

	/* ---- Collabs ---- */
	register_rest_route( $ns, '/collabs', [
		[
			'methods'             => 'POST',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'callback'            => 'aif_inf_rest_save_collab',
		],
		[
			'methods'             => 'GET',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'callback'            => 'aif_inf_rest_list_collabs',
		],
	] );

	register_rest_route( $ns, '/collabs/(?P<id>\d+)/status', [
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'callback'            => 'aif_inf_rest_update_collab_status',
	] );

	/* ---- Compliance & Scoring ---- */
	register_rest_route( $ns, '/check-compliance', [
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'callback'            => 'aif_inf_rest_check_compliance',
	] );

	register_rest_route( $ns, '/score-content', [
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'callback'            => 'aif_inf_rest_score_content',
	] );
} );

/* ================================================================ */
/*  Brief handlers                                                   */
/* ================================================================ */

function aif_inf_rest_create_brief( WP_REST_Request $req ): WP_REST_Response {
	$params   = $req->get_json_params();
	$brief_id = isset( $params['id'] ) ? absint( $params['id'] ) : 0;

	$result = aif_brief_save( $params, $brief_id );

	if ( is_wp_error( $result ) ) {
		return new WP_REST_Response(
			[ 'error' => $result->get_error_message() ],
			400
		);
	}

	$brief = aif_brief_get( $result );

	return new WP_REST_Response( [
		'success' => true,
		'brief'   => $brief,
	], $brief_id > 0 ? 200 : 201 );
}

function aif_inf_rest_list_briefs( WP_REST_Request $req ): WP_REST_Response {
	$briefs = aif_brief_list();
	return new WP_REST_Response( [ 'briefs' => $briefs ], 200 );
}

function aif_inf_rest_get_brief( WP_REST_Request $req ): WP_REST_Response {
	$id    = absint( $req->get_param( 'id' ) );
	$brief = aif_brief_get( $id );

	if ( ! $brief ) {
		return new WP_REST_Response( [ 'error' => 'Brief introuvable.' ], 404 );
	}

	return new WP_REST_Response( [ 'brief' => $brief ], 200 );
}

/* ================================================================ */
/*  Collab handlers                                                  */
/* ================================================================ */

function aif_inf_rest_save_collab( WP_REST_Request $req ): WP_REST_Response {
	$params    = $req->get_json_params();
	$collab_id = isset( $params['id'] ) ? absint( $params['id'] ) : 0;
	$brief_id  = isset( $params['brief_id'] ) ? absint( $params['brief_id'] ) : 0;
	$title     = sanitize_text_field( $params['title'] ?? '' );
	$content   = wp_kses_post( $params['content'] ?? '' );

	if ( empty( $title ) ) {
		return new WP_REST_Response( [ 'error' => 'Titre requis.' ], 400 );
	}

	if ( $brief_id > 0 ) {
		$brief = aif_brief_get( $brief_id );
		if ( ! $brief ) {
			return new WP_REST_Response( [ 'error' => 'Brief introuvable.' ], 404 );
		}
	}

	$post_data = [
		'post_type'    => 'aif_collab',
		'post_title'   => $title,
		'post_content' => $content,
		'post_status'  => 'publish',
	];

	if ( $collab_id > 0 ) {
		$post_data['ID'] = $collab_id;
		$result = wp_update_post( $post_data, true );
	} else {
		$result = wp_insert_post( $post_data, true );
	}

	if ( is_wp_error( $result ) ) {
		return new WP_REST_Response(
			[ 'error' => $result->get_error_message() ],
			500
		);
	}

	$post_id = (int) $result;

	/* Meta */
	if ( $brief_id > 0 ) {
		update_post_meta( $post_id, '_aif_brief_id', $brief_id );
	}

	$status = sanitize_text_field( $params['status'] ?? 'draft' );
	$valid_statuses = [ 'draft', 'submitted', 'revision', 'approved', 'published' ];
	if ( ! in_array( $status, $valid_statuses, true ) ) {
		$status = 'draft';
	}
	update_post_meta( $post_id, '_aif_collab_status', $status );

	return new WP_REST_Response( [
		'success' => true,
		'collab'  => aif_inf_format_collab( get_post( $post_id ) ),
	], $collab_id > 0 ? 200 : 201 );
}

function aif_inf_rest_list_collabs( WP_REST_Request $req ): WP_REST_Response {
	$brief_id = absint( $req->get_param( 'brief_id' ) );

	$args = [
		'post_type'      => 'aif_collab',
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'DESC',
	];

	if ( $brief_id > 0 ) {
		$args['meta_query'] = [
			[
				'key'   => '_aif_brief_id',
				'value' => $brief_id,
				'type'  => 'NUMERIC',
			],
		];
	}

	$query   = new WP_Query( $args );
	$collabs = array_map( 'aif_inf_format_collab', $query->posts );

	return new WP_REST_Response( [ 'collabs' => $collabs ], 200 );
}

function aif_inf_rest_update_collab_status( WP_REST_Request $req ): WP_REST_Response {
	$id     = absint( $req->get_param( 'id' ) );
	$params = $req->get_json_params();
	$status = sanitize_text_field( $params['status'] ?? '' );

	$valid_statuses = [ 'draft', 'submitted', 'revision', 'approved', 'published' ];
	if ( ! in_array( $status, $valid_statuses, true ) ) {
		return new WP_REST_Response( [ 'error' => 'Statut invalide.' ], 400 );
	}

	$post = get_post( $id );
	if ( ! $post || $post->post_type !== 'aif_collab' ) {
		return new WP_REST_Response( [ 'error' => 'Contenu introuvable.' ], 404 );
	}

	update_post_meta( $id, '_aif_collab_status', $status );

	return new WP_REST_Response( [
		'success' => true,
		'collab'  => aif_inf_format_collab( $post ),
	], 200 );
}

/* ================================================================ */
/*  Compliance & Scoring handlers                                    */
/* ================================================================ */

function aif_inf_rest_check_compliance( WP_REST_Request $req ): WP_REST_Response {
	$params   = $req->get_json_params();
	$content  = wp_unslash( $params['content'] ?? '' );
	$brief_id = absint( $params['brief_id'] ?? 0 );

	if ( empty( $content ) ) {
		return new WP_REST_Response( [ 'error' => 'Contenu vide.' ], 400 );
	}

	if ( ! $brief_id ) {
		return new WP_REST_Response( [ 'error' => 'brief_id requis.' ], 400 );
	}

	$brief = aif_brief_get( $brief_id );
	if ( ! $brief ) {
		return new WP_REST_Response( [ 'error' => 'Brief introuvable.' ], 404 );
	}

	$result = aif_compliance_check( $content, $brief );

	/* Cache result if collab_id provided */
	$collab_id = absint( $params['collab_id'] ?? 0 );
	if ( $collab_id > 0 ) {
		update_post_meta( $collab_id, '_aif_compliance_result', $result );
	}

	return new WP_REST_Response( $result, 200 );
}

function aif_inf_rest_score_content( WP_REST_Request $req ): WP_REST_Response {
	$params   = $req->get_json_params();
	$content  = wp_unslash( $params['content'] ?? '' );
	$brief_id = absint( $params['brief_id'] ?? 0 );

	if ( empty( $content ) ) {
		return new WP_REST_Response( [ 'error' => 'Contenu vide.' ], 400 );
	}

	$brief = $brief_id > 0 ? ( aif_brief_get( $brief_id ) ?? [] ) : [];

	$result = aif_score_content( $content, $brief );

	/* Cache result if collab_id provided */
	$collab_id = absint( $params['collab_id'] ?? 0 );
	if ( $collab_id > 0 ) {
		update_post_meta( $collab_id, '_aif_scoring_result', $result );
	}

	return new WP_REST_Response( $result, 200 );
}

/* ================================================================ */
/*  Helpers                                                          */
/* ================================================================ */

/**
 * Format a collab post for API response.
 */
function aif_inf_format_collab( WP_Post $post ): array {
	$brief_id   = (int) get_post_meta( $post->ID, '_aif_brief_id', true );
	$brief      = $brief_id > 0 ? aif_brief_get( $brief_id ) : null;
	$compliance = get_post_meta( $post->ID, '_aif_compliance_result', true );
	$scoring    = get_post_meta( $post->ID, '_aif_scoring_result', true );

	return [
		'id'               => $post->ID,
		'title'            => $post->post_title,
		'content'          => $post->post_content,
		'author'           => (int) $post->post_author,
		'author_name'      => get_the_author_meta( 'display_name', $post->post_author ),
		'brief_id'         => $brief_id,
		'brief_title'      => $brief ? $brief['title'] : '',
		'status'           => get_post_meta( $post->ID, '_aif_collab_status', true ) ?: 'draft',
		'compliance_score' => is_array( $compliance ) ? ( $compliance['score'] ?? null ) : null,
		'quality_score'    => is_array( $scoring ) ? ( $scoring['score'] ?? null ) : null,
		'created'          => $post->post_date,
		'modified'         => $post->post_modified,
	];
}
