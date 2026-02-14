<?php
/**
 * Layer A : nettoyage des tics LLM (sans IA).
 *
 * IMPORTANT : ce nettoyage ne s'applique qu'aux textes colles depuis un LLM.
 * Les textes d'auteurs (mode "correction seule") ne passent PAS par cette couche.
 *
 * @author Peopleofverso
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supprime les tics de mise en page typiques des textes generes par LLM.
 *
 * @param string $text Le texte brut a nettoyer.
 * @return string Le texte nettoye.
 */
function aif_clean_llm_ticks( string $text ): string {
	$t = trim( $text );

	/* Normalise les fins de ligne */
	$t = str_replace( [ "\r\n", "\r" ], "\n", $t );

	/* ---- Introductions cliches ---- */
	$intros = [
		'/^\s*(bien\s+s[uû]r|bien\s+entendu|avec\s+plaisir|absolument)\s*[!:.;\-\x{2013}\x{2014}]*\s*/iu',
		'/^\s*(voici|voil[aà])\s+[^.\n]{0,80}[:.!]\s*/iu',
		'/^\s*(je\s+vais\s+vous\s+(expliquer|pr[eé]senter|montrer))\s+[^.\n]{0,80}[:.!]\s*/iu',
		'/^\s*(laissez[- ]moi\s+vous)\s+[^.\n]{0,80}[:.!]\s*/iu',
		'/^\s*(c\'est\s+une\s+(excellente|tr[eè]s\s+bonne|bonne)\s+question)\s*[!:.;\-]*\s*/iu',
		'/^\s*(super\s+question)\s*[!:.;\-]*\s*/iu',
	];
	foreach ( $intros as $p ) {
		$t = preg_replace( $p, '', $t );
	}

	/* ---- Conclusions cliches ---- */
	$conclusions = [
		'/\n\s*(en\s+r[eé]sum[eé]|pour\s+r[eé]sumer|en\s+conclusion|pour\s+conclure)\s*[!:.;\-\x{2013}\x{2014}]*\s*\n?/iu',
		'/\n\s*n\'h[eé]sitez\s+pas\s+[^\n]{0,120}\s*$/iu',
		'/\n\s*j\'esp[eè]re\s+(que\s+)?(cela|cette|ce|cette\s+r[eé]ponse)\s+[^\n]{0,120}\s*$/iu',
		'/\n\s*(si\s+vous\s+avez\s+d\'autres\s+questions)\s*[^\n]{0,80}\s*$/iu',
		'/\n\s*(je\s+reste\s+[aà]\s+votre\s+disposition)\s*[^\n]{0,80}\s*$/iu',
	];
	foreach ( $conclusions as $p ) {
		$t = preg_replace( $p, "\n", $t );
	}

	/* ---- Emojis de checklist / decoratifs en debut de ligne ---- */
	$t = preg_replace(
		'/^\s*[\x{2705}\x{2714}\x{2611}\x{1F539}\x{1F538}\x{1F4A1}\x{1F449}\x{27A1}\x{2B50}\x{1F680}\x{2022}\x{1F4CC}\x{1F4DD}\x{26A0}\x{1F6A8}\x{1F50D}\x{1F4E2}\x{1F3AF}\x{1F4A5}]\x{FE0F}?\s*/mu',
		'- ',
		$t
	);

	/* ---- Titres abusifs (plus de 4 ## d'affilee = aplatir) ---- */
	$t = preg_replace( '/^#{4,}\s+/m', '### ', $t );

	/* ---- Supprime les titres "Conclusion" / "Introduction" factices ---- */
	$t = preg_replace( '/^\s*#{0,3}\s*(conclusion|introduction)\s*$/im', '', $t );

	/* ---- Lignes de separateurs inutiles (---, ===, ***, ___) ---- */
	$t = preg_replace( '/^\s*[-=_\*]{3,}\s*$/m', '', $t );

	/* ---- FAQ automatiques non demandees ---- */
	$t = preg_replace( '/^\s*#{0,3}\s*(FAQ|Foire\s+aux\s+questions|Questions\s+fr[eé]quentes)\s*$/im', '', $t );

	/* ---- Multiples sauts de ligne (nettoyage final) ---- */
	$t = preg_replace( "/\n{3,}/", "\n\n", $t );

	return trim( $t );
}

/**
 * Comptage de mots et caracteres.
 *
 * @param string $text Le texte a analyser.
 * @return array{words: int, chars: int, chars_no_spaces: int}
 */
function aif_count_stats( string $text ): array {
	$clean = wp_strip_all_tags( $text );
	return [
		'words'           => str_word_count( $clean, 0, "\xC0-\xFF" ),
		'chars'           => mb_strlen( $clean, 'UTF-8' ),
		'chars_no_spaces' => mb_strlen( preg_replace( '/\s+/u', '', $clean ), 'UTF-8' ),
	];
}
