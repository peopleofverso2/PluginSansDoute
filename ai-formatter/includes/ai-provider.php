<?php
/**
 * Layer C : provider IA pour correction orthographe / style.
 *
 * IMPORTANT : les textes sont ecrits par des auteurs (parfois des plumes celebres).
 * L'IA doit CORRIGER (orthographe, grammaire, typographie) sans MODIFIER
 * le style, le ton, ou la voix de l'auteur. Aucune reecriture.
 *
 * Supporte OpenAI et Anthropic. La cle API et le provider sont
 * stockes dans les options WordPress (page Reglages).
 *
 * @author Peopleofverso
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
			$result = aif_call_anthropic( $api_key, $system_prompt, $html );
			break;
		case 'openai':
		default:
			$result = aif_call_openai( $api_key, $system_prompt, $html );
			break;
	}

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	/* Sanitize la sortie IA (securite) */
	return wp_kses_post( $result );
}

/**
 * Construit le prompt systeme.
 *
 * Le prompt insiste sur le respect absolu du texte de l'auteur :
 * corriger, jamais reecrire.
 */
function aif_build_system_prompt( string $style ): string {
	$base = implode( "\n", [
		'Tu es un correcteur / relecteur professionnel pour une publication editoriale francaise.',
		'',
		'REGLES ABSOLUES :',
		'1. RESPECTE la voix, le ton et le style de l\'auteur. Ne reecris JAMAIS le texte.',
		'2. Corrige UNIQUEMENT : orthographe, grammaire, accords, conjugaison.',
		'3. Applique la typographie francaise : guillemets francais, espaces insecables, apostrophes courbes.',
		'4. Ne rajoute aucun preambule, aucune conclusion, aucun commentaire, aucun emoji.',
		'5. Ne supprime et n\'ajoute aucune phrase. Ne reformule pas.',
		'6. Conserve la structure exacte du document (titres, paragraphes, listes).',
		'7. Retourne uniquement du HTML valide avec les balises : p, h2, h3, h4, ul, ol, li, blockquote, strong, em, a, code, hr.',
		'8. Si le texte est deja correct, retourne-le tel quel.',
		'',
		'Tu travailles pour "SansDoute", une publication ou ecrivent des plumes celebres.',
		'Le respect du texte original est sacre.',
	] );

	$styles = [
		'neutre'         => 'Le texte doit rester tel quel. Corrections minimales uniquement.',
		'journalistique' => 'Si des corrections mineures de fluidite sont necessaires, prefere des phrases courtes et directes, sans alterer le sens.',
		'gonzo'          => 'Respecte le style expressif et personnel de l\'auteur. Ne lisse pas, ne censure pas. Corrige seulement les fautes.',
		'corporate'      => 'Corrige les fautes tout en gardant le registre professionnel de l\'auteur.',
	];

	$tone = $styles[ $style ] ?? $styles['neutre'];

	return $base . "\n\n" . $tone;
}

/**
 * Appel OpenAI (Chat Completions).
 *
 * @param string $api_key       Cle API OpenAI.
 * @param string $system_prompt Le prompt systeme.
 * @param string $html          Le HTML a corriger.
 * @return string|WP_Error
 */
function aif_call_openai( string $api_key, string $system_prompt, string $html ) {
	$model = get_option( 'aif_model', '' );
	if ( empty( $model ) ) {
		$model = 'gpt-4o-mini';
	}

	$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
		'timeout' => 90,
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
			'temperature' => 0.15,
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
 *
 * @param string $api_key       Cle API Anthropic.
 * @param string $system_prompt Le prompt systeme.
 * @param string $html          Le HTML a corriger.
 * @return string|WP_Error
 */
function aif_call_anthropic( string $api_key, string $system_prompt, string $html ) {
	$model = get_option( 'aif_model', '' );
	if ( empty( $model ) ) {
		$model = 'claude-sonnet-4-20250514';
	}

	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'timeout' => 90,
		'headers' => [
			'Content-Type'      => 'application/json',
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
		],
		'body'    => wp_json_encode( [
			'model'      => $model,
			'max_tokens' => 8192,
			'system'     => $system_prompt,
			'messages'   => [
				[ 'role' => 'user', 'content' => $html ],
			],
			'temperature' => 0.15,
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
