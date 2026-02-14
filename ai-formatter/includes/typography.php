<?php
/**
 * Layer B : typographie francaise.
 *
 * Applique les regles typographiques du francais :
 * - Espaces insecables (fines) avant : ; ? !
 * - Guillemets francais avec espaces insecables
 * - Apostrophes courbes
 * - Tirets cadratin / demi-cadratin
 * - Nettoyage des doubles espaces
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Espace fine insecable (U+202F). Preferable devant la ponctuation double.
 */
define( 'AIF_NNBSP', "\xE2\x80\xAF" ); // U+202F narrow no-break space
define( 'AIF_NBSP', "\xC2\xA0" );       // U+00A0 no-break space

function aif_apply_french_typography( string $text ): string {
	$t = $text;

	/* ---- Apostrophes courbes ---- */
	// l'homme -> l\u2019homme
	$t = preg_replace( "/(?<=\\w)'(?=\\w)/u", "\xE2\x80\x99", $t );

	/* ---- Espaces avant ponctuation double ---- */
	// Regle : espace fine insecable avant : ; ? !
	// On remplace tout espace(s) precedant par une fine insecable,
	// ou on en insere une s'il n'y en a pas.
	$t = preg_replace( '/\h*([;?!])/u', AIF_NNBSP . '$1', $t );
	// Deux-points : espace insecable normale (convention)
	$t = preg_replace( '/\h*(:)(?!\/)/', AIF_NBSP . '$1', $t );

	/* ---- Guillemets francais ---- */
	// Normalise d'abord les guillemets typographiques anglais en guillemets droits
	$t = preg_replace( '/[\x{201C}\x{201D}\x{201E}\x{201F}]/u', '"', $t );

	// Convertit "texte" en << texte >>
	$t = preg_replace(
		'/"([^"]+)"/u',
		"\xC2\xAB" . AIF_NNBSP . '$1' . AIF_NNBSP . "\xC2\xBB",
		$t
	);

	/* ---- Tirets ---- */
	// -- en tiret demi-cadratin (en-dash)
	$t = str_replace( '--', "\xE2\x80\x93", $t );

	/* ---- Doubles espaces ---- */
	$t = preg_replace( '/[ \t]{2,}/', ' ', $t );

	/* ---- Points de suspension ---- */
	$t = str_replace( '...', "\xE2\x80\xA6", $t );

	return $t;
}
