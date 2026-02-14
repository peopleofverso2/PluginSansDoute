<?php
/**
 * Layer A : nettoyage des tics LLM (sans IA).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supprime les tics de mise en page typiques des textes generes par LLM.
 */
function aif_clean_llm_ticks( string $text ): string {
	$t = trim( $text );

	/* Normalise les fins de ligne */
	$t = str_replace( [ "\r\n", "\r" ], "\n", $t );

	/* ---- Introductions cliches ---- */
	$intros = [
		'/^\s*(bien\s+s[uû]r|bien\s+entendu|avec\s+plaisir|absolument)\s*[!:.;\-\x{2013}\x{2014}]*\s*/iu',
		'/^\s*(voici|voil[aà])\s+[^.\n]{0,80}[:.!]\s*/iu',
		'/^\s*(je\s+vais\s+vous\s+expliquer|laissez[- ]moi\s+vous)\s+[^.\n]{0,80}[:.!]\s*/iu',
	];
	foreach ( $intros as $p ) {
		$t = preg_replace( $p, '', $t );
	}

	/* ---- Conclusions cliches ---- */
	$conclusions = [
		'/\n\s*(en\s+r[eé]sum[eé]|pour\s+r[eé]sumer|en\s+conclusion|pour\s+conclure)\s*[!:.;\-\x{2013}\x{2014}]*\s*\n?/iu',
		'/\n\s*n\'h[eé]sitez\s+pas\s+[^\n]{0,120}\s*$/iu',
	];
	foreach ( $conclusions as $p ) {
		$t = preg_replace( $p, "\n", $t );
	}

	/* ---- Emojis de checklist / decoratifs en debut de ligne ---- */
	$t = preg_replace(
		'/^\s*[\x{2705}\x{2714}\x{2611}\x{1F539}\x{1F538}\x{1F4A1}\x{1F449}\x{27A1}\x{2B50}\x{1F680}\x{2022}]\s*/mu',
		'- ',
		$t
	);

	/* ---- Titres abusifs (plus de 4 ## d'affilee = aplatir) ---- */
	$t = preg_replace( '/^#{4,}\s+/m', '### ', $t );

	/* ---- Multiples sauts de ligne ---- */
	$t = preg_replace( "/\n{3,}/", "\n\n", $t );

	/* ---- Lignes de separateurs inutiles ---- */
	$t = preg_replace( '/^\s*[-=_\*]{3,}\s*$/m', '', $t );

	/* ---- Supprime "Conclusion" seul sur une ligne (titre factice) ---- */
	$t = preg_replace( '/^\s*#{0,3}\s*conclusion\s*$/im', '', $t );

	/* ---- Nettoyage final ---- */
	$t = preg_replace( "/\n{3,}/", "\n\n", $t );

	return trim( $t );
}
