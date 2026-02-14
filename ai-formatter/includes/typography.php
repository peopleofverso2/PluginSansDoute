<?php
/**
 * Layer B : typographie francaise.
 *
 * Applique les regles typographiques du francais :
 * - Espaces fines insecables (U+202F) avant ; ? !
 * - Espace insecable (U+00A0) avant :
 * - Guillemets francais << >> avec espaces insecables
 * - Apostrophes courbes typographiques
 * - Tirets demi-cadratin / cadratin selon contexte
 * - Points de suspension typographiques
 * - Nettoyage des doubles espaces
 * - Protection des URLs (pas de modifications dans les liens)
 *
 * @author Peopleofverso
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Espace fine insecable (U+202F). Preferable devant la ponctuation double.
 */
if ( ! defined( 'AIF_NNBSP' ) ) {
	define( 'AIF_NNBSP', "\xE2\x80\xAF" ); // U+202F narrow no-break space
}
if ( ! defined( 'AIF_NBSP' ) ) {
	define( 'AIF_NBSP', "\xC2\xA0" );       // U+00A0 no-break space
}

/**
 * Applique les regles typographiques francaises au texte.
 *
 * Protege les URLs et le code inline avant de traiter.
 *
 * @param string $text Le texte a corriger typographiquement.
 * @return string Le texte avec la typographie francaise appliquee.
 */
function aif_apply_french_typography( string $text ): string {
	/* ---- Extraction et protection des URLs et code ---- */
	$protected = [];
	$idx       = 0;

	// Protege les URLs
	$t = preg_replace_callback(
		'#https?://[^\s\)\]>]+#i',
		function ( $m ) use ( &$protected, &$idx ) {
			$token              = "\x00URL{$idx}\x00";
			$protected[ $token ] = $m[0];
			$idx++;
			return $token;
		},
		$text
	);

	// Protege le code inline `...`
	$t = preg_replace_callback(
		'/`[^`]+`/',
		function ( $m ) use ( &$protected, &$idx ) {
			$token              = "\x00CODE{$idx}\x00";
			$protected[ $token ] = $m[0];
			$idx++;
			return $token;
		},
		$t
	);

	/* ---- Apostrophes courbes ---- */
	$t = preg_replace( "/(?<=\\w)'(?=\\w)/u", "\xE2\x80\x99", $t );
	// Apostrophe en debut de mot aussi : 'tain, 'jour
	$t = preg_replace( "/(?<=\\s)'(?=\\w)/u", "\xE2\x80\x99", $t );

	/* ---- Espaces avant ponctuation double ---- */
	// Espace fine insecable avant ; ? !
	$t = preg_replace( '/\h*([;?!])/u', AIF_NNBSP . '$1', $t );
	// Espace insecable normale avant : (sauf dans les URLs, deja protegees)
	$t = preg_replace( '/\h*(:)(?!\x00)/', AIF_NBSP . '$1', $t );

	/* ---- Espace apres ponctuation ---- */
	// S'assure d'un espace apres . , ; : ? ! s'il y a un mot qui suit
	$t = preg_replace( '/([.;:?!,])(?=[A-Za-z\xC0-\xFF])/u', '$1 ', $t );

	/* ---- Guillemets francais ---- */
	// Normalise les guillemets typographiques anglais
	$t = preg_replace( '/[\x{201C}\x{201D}\x{201E}\x{201F}]/u', '"', $t );
	// Convertit "texte" en << texte >>
	$t = preg_replace(
		'/"([^"]+)"/u',
		"\xC2\xAB" . AIF_NNBSP . '$1' . AIF_NNBSP . "\xC2\xBB",
		$t
	);

	/* ---- Tirets ---- */
	// --- en tiret cadratin (em-dash) pour les incises
	$t = str_replace( '---', "\xE2\x80\x94", $t );
	// -- en tiret demi-cadratin (en-dash) pour les intervalles
	$t = str_replace( '--', "\xE2\x80\x93", $t );

	/* ---- Points de suspension ---- */
	$t = str_replace( '...', "\xE2\x80\xA6", $t );

	/* ---- Ligatures OE (mots connus uniquement) ---- */
	$oe_words = [
		'coeur'  => "c\xC5\x93ur",
		'oeuvre' => "\xC5\x93uvre",
		'oeil'   => "\xC5\x93il",
		'oeuf'   => "\xC5\x93uf",
		'oeufs'  => "\xC5\x93ufs",
		'noeud'  => "n\xC5\x93ud",
		'voeu'   => "v\xC5\x93u",
		'voeux'  => "v\xC5\x93ux",
		'soeur'  => "s\xC5\x93ur",
		'moeurs' => "m\xC5\x93urs",
	];
	foreach ( $oe_words as $from => $to ) {
		$t = preg_replace( '/\b' . preg_quote( $from, '/' ) . '\b/iu', $to, $t );
	}

	/* ---- Doubles espaces ---- */
	$t = preg_replace( '/[ \t]{2,}/', ' ', $t );

	/* ---- Restaure les elements proteges ---- */
	foreach ( $protected as $token => $original ) {
		$t = str_replace( $token, $original, $t );
	}

	return $t;
}
