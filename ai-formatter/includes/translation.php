<?php
/**
 * Systeme de traduction d'articles vers les langues europeennes.
 *
 * Utilise le provider IA configure (OpenAI / Anthropic) pour produire
 * des traductions de haute qualite qui respectent le style de l'auteur.
 *
 * Langues supportees : EN, ES, DE, IT, PT, NL, PL, RO, SV, DA, FI, EL, CS, HU, HR, BG, SK, SL, ET, LV, LT.
 *
 * @author Peopleofverso
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Langues europeennes disponibles.
 *
 * @return array<string, string> Code ISO => Nom natif
 */
function aif_translation_languages(): array {
	return [
		'en' => 'English',
		'es' => 'Espanol',
		'de' => 'Deutsch',
		'it' => 'Italiano',
		'pt' => 'Portugues',
		'nl' => 'Nederlands',
		'pl' => 'Polski',
		'ro' => 'Romana',
		'sv' => 'Svenska',
		'da' => 'Dansk',
		'fi' => 'Suomi',
		'el' => 'Ellinika',
		'cs' => 'Cestina',
		'hu' => 'Magyar',
		'hr' => 'Hrvatski',
		'bg' => 'Bulgarski',
		'sk' => 'Slovencina',
		'sl' => 'Slovenscina',
		'et' => 'Eesti',
		'lv' => 'Latviesu',
		'lt' => 'Lietuviu',
	];
}

/**
 * Enregistre le endpoint REST pour la traduction.
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ai-formatter/v1', '/translate', [
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'callback'            => 'aif_rest_translate',
	] );
} );

/**
 * Handler REST : traduction d'un texte HTML.
 */
function aif_rest_translate( WP_REST_Request $req ): WP_REST_Response {
	$params   = $req->get_json_params();
	$html     = isset( $params['html'] ) ? wp_unslash( $params['html'] ) : '';
	$target   = $params['target_lang'] ?? '';
	$source   = $params['source_lang'] ?? 'fr';

	if ( ! $html ) {
		return new WP_REST_Response( [ 'error' => 'Contenu vide.' ], 400 );
	}

	$languages = aif_translation_languages();
	if ( ! isset( $languages[ $target ] ) ) {
		return new WP_REST_Response( [ 'error' => 'Langue cible non supportee : ' . $target ], 400 );
	}

	$result = aif_translate_html( $html, $source, $target );

	if ( is_wp_error( $result ) ) {
		return new WP_REST_Response(
			[ 'error' => $result->get_error_message() ],
			500
		);
	}

	return new WP_REST_Response( [
		'html'        => $result,
		'source_lang' => $source,
		'target_lang' => $target,
		'target_name' => $languages[ $target ],
	], 200 );
}

/**
 * Traduit du HTML via l'IA.
 *
 * @param string $html   Le HTML a traduire.
 * @param string $source Code langue source (ex: 'fr').
 * @param string $target Code langue cible (ex: 'en').
 * @return string|WP_Error Le HTML traduit ou une erreur.
 */
function aif_translate_html( string $html, string $source, string $target ) {
	$provider = get_option( 'aif_provider', 'openai' );
	$api_key  = get_option( 'aif_api_key', '' );

	if ( empty( $api_key ) ) {
		return new WP_Error(
			'aif_no_key',
			'Cle API manquante. Configurez-la dans Reglages > AI Formatter.'
		);
	}

	$languages    = aif_translation_languages();
	$source_name  = $languages[ $source ] ?? $source;
	$target_name  = $languages[ $target ] ?? $target;

	$system_prompt = aif_build_translation_prompt( $source_name, $target_name );

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

	return wp_kses_post( $result );
}

/**
 * Construit le prompt systeme pour la traduction.
 *
 * Le prompt est concu pour produire la meilleure traduction possible :
 * fidele au sens, naturelle dans la langue cible, respectueuse du
 * style et du ton de l'auteur.
 */
function aif_build_translation_prompt( string $source_name, string $target_name ): string {
	return implode( "\n", [
		'Tu es un traducteur professionnel de niveau editorial.',
		'',
		'MISSION : Traduis le texte HTML suivant de ' . $source_name . ' vers ' . $target_name . '.',
		'',
		'REGLES DE TRADUCTION :',
		'1. Produis une traduction NATURELLE et FLUIDE dans la langue cible, pas du mot-a-mot.',
		'2. RESPECTE absolument le style, le ton et la voix de l\'auteur. Si le texte est litteraire, la traduction doit l\'etre aussi. Si le texte est technique, idem.',
		'3. Adapte les expressions idiomatiques : trouve l\'equivalent naturel dans la langue cible plutot que de traduire litteralement.',
		'4. Conserve la STRUCTURE HTML exacte (balises p, h2, h3, h4, ul, ol, li, blockquote, strong, em, a, code, hr).',
		'5. Ne modifie PAS les URLs dans les liens.',
		'6. Applique les regles typographiques de la langue cible (guillemets, ponctuation, etc.).',
		'7. Ne rajoute aucun preambule, aucune note du traducteur, aucun commentaire.',
		'8. Retourne UNIQUEMENT le HTML traduit, rien d\'autre.',
		'9. Les noms propres, marques et termes techniques reconnus restent en version originale sauf convention etablie.',
		'10. Si un passage est ambigu, choisis l\'interpretation la plus coherente avec le contexte.',
		'',
		'Tu travailles pour "SansDoute", une publication editoriale. La qualite de traduction doit etre irreprochable.',
	] );
}

/**
 * Enregistre le post meta pour la langue de traduction.
 */
add_action( 'init', function () {
	register_post_meta( '', '_aif_translation_lang', [
		'show_in_rest'  => true,
		'single'        => true,
		'type'          => 'string',
		'default'       => '',
		'auth_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	] );
} );
