<?php
/**
 * Layer C : provider IA pour correction orthographe / style.
 *
 * Supporte OpenAI et Anthropic. La cle API et le provider sont
 * stockes dans les options WordPress (page Reglages).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Corrige le HTML via un appel IA.
 *
 * @param string $html  Le HTML a corriger.
 * @param string $style Le style souhaite (neutre, journalistique, gonzo, corporate).
 * @return string|WP_Error Le HTML corrige ou une erreur.
 */
function aif_ai_proofread( string $html, string $style = 'neutre' ) {
	$provider = get_option( 'aif_provider', 'openai' );
	$api_key  = get_option( 'aif_api_key', '' );

	if ( empty( $api_key ) ) {
		return new WP_Error(
			'aif_no_key',
			'Cle API manquante. Configurez-la dans Reglages > AI Formatter.'
		);
	}

	$system_prompt = aif_build_system_prompt( $style );

	switch ( $provider ) {
		case 'anthropic':
			return aif_call_anthropic( $api_key, $system_prompt, $html );
		case 'openai':
		default:
			return aif_call_openai( $api_key, $system_prompt, $html );
	}
}

/**
 * Construit le prompt systeme selon le style demande.
 */
function aif_build_system_prompt( string $style ): string {
	$base = implode( "\n", [
		'Tu es un correcteur de texte en francais.',
		'Corrige l\'orthographe, la grammaire et les accords.',
		'Applique la typographie francaise (guillemets, espaces insecables, etc.).',
		'Ne rajoute aucun preambule, aucune conclusion, aucun emoji.',
		'Retourne uniquement du HTML valide avec les balises p, h2, h3, h4, ul, li, strong, em, a.',
		'Ne modifie pas la structure du document sauf si elle est manifestement incorrecte.',
	] );

	$styles = [
		'neutre'         => 'Adopte un ton neutre et clair.',
		'journalistique' => 'Adopte un ton journalistique : phrases courtes, incisives, factuel.',
		'gonzo'          => 'Adopte un style gonzo-pro : expressif, personnel, percutant, sans filtres inutiles.',
		'corporate'      => 'Adopte un ton corporate : professionnel, lisse, consensuel.',
	];

	$tone = $styles[ $style ] ?? $styles['neutre'];

	return $base . "\n" . $tone;
}

/**
 * Appel OpenAI (Chat Completions).
 */
function aif_call_openai( string $api_key, string $system_prompt, string $html ) {
	$model = get_option( 'aif_model', 'gpt-4o-mini' );

	$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
		'timeout' => 60,
		'headers' => [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
		],
		'body'    => wp_json_encode( [
			'model'    => $model,
			'messages' => [
				[ 'role' => 'system', 'content' => $system_prompt ],
				[ 'role' => 'user', 'content' => $html ],
			],
			'temperature' => 0.3,
		] ),
	] );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code !== 200 ) {
		$msg = $body['error']['message'] ?? 'Erreur OpenAI (HTTP ' . $code . ')';
		return new WP_Error( 'aif_openai_error', $msg );
	}

	return $body['choices'][0]['message']['content'] ?? $html;
}

/**
 * Appel Anthropic (Messages API).
 */
function aif_call_anthropic( string $api_key, string $system_prompt, string $html ) {
	$model = get_option( 'aif_model', 'claude-sonnet-4-20250514' );

	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'timeout' => 60,
		'headers' => [
			'Content-Type'      => 'application/json',
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
		],
		'body'    => wp_json_encode( [
			'model'      => $model,
			'max_tokens' => 4096,
			'system'     => $system_prompt,
			'messages'   => [
				[ 'role' => 'user', 'content' => $html ],
			],
			'temperature' => 0.3,
		] ),
	] );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code !== 200 ) {
		$msg = $body['error']['message'] ?? 'Erreur Anthropic (HTTP ' . $code . ')';
		return new WP_Error( 'aif_anthropic_error', $msg );
	}

	return $body['content'][0]['text'] ?? $html;
}
