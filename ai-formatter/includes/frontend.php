<?php
/**
 * Front-end : applique le preset CSS choisi sur les articles publies.
 *
 * Lit le post meta `_aif_css_preset` et :
 * 1. Ajoute une body class `aif-preset-{slug}`
 * 2. Enqueue la feuille de style frontend avec les presets
 *
 * @author Peopleofverso
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajoute la body class sur les single posts qui ont un preset.
 */
add_filter( 'body_class', function ( array $classes ): array {
	if ( ! is_singular() ) {
		return $classes;
	}

	$preset = get_post_meta( get_the_ID(), '_aif_css_preset', true );
	if ( $preset && in_array( $preset, array_keys( aif_css_presets() ), true ) ) {
		$classes[] = 'aif-preset-' . sanitize_html_class( $preset );
	}

	return $classes;
} );

/**
 * Enqueue le CSS frontend si le post a un preset.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() ) {
		return;
	}

	$preset = get_post_meta( get_the_ID(), '_aif_css_preset', true );
	if ( $preset && in_array( $preset, array_keys( aif_css_presets() ), true ) ) {
		wp_enqueue_style( 'aif-frontend', AIF_URL . 'assets/frontend.css', [], AIF_VERSION );
	}
} );
