<?php
/**
 * Integration Gutenberg : sidebar panel "AI Formatter".
 *
 * Ajoute un panneau dans la sidebar de l'editeur Gutenberg
 * pour formater le contenu du post courant.
 *
 * @author Peopleofverso
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue le script Gutenberg sidebar sur l'editeur.
 */
add_action( 'enqueue_block_editor_assets', function () {
	wp_enqueue_script(
		'aif-gutenberg',
		AIF_URL . 'assets/gutenberg.js',
		[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch' ],
		AIF_VERSION,
		true
	);

	wp_localize_script( 'aif-gutenberg', 'AIF_GUTENBERG', [
		'restUrl' => esc_url_raw( rest_url( 'ai-formatter/v1/format' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
		'presets' => array_keys( aif_css_presets() ),
	] );
} );

/**
 * Enregistre le post meta pour le preset CSS.
 */
add_action( 'init', function () {
	register_post_meta( '', '_aif_css_preset', [
		'show_in_rest'  => true,
		'single'        => true,
		'type'          => 'string',
		'default'       => '',
		'auth_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	] );
} );
