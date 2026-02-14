<?php
/**
 * Convertit du pseudo-markdown (texte nettoye) en HTML propre.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convertit un texte « markdownish » en HTML semantique.
 *
 * Gere : titres (#, ##, ###), listes a puces (- ), gras (**), italique (*),
 * liens [texte](url), et paragraphes.
 */
function aif_markdownish_to_html( string $text ): string {
	$lines   = explode( "\n", $text );
	$html    = '';
	$in_list = false;

	foreach ( $lines as $line ) {
		$l = trim( $line );

		/* Ligne vide : ferme la liste si ouverte */
		if ( $l === '' ) {
			if ( $in_list ) {
				$html   .= "</ul>\n";
				$in_list = false;
			}
			continue;
		}

		/* Titres : # -> h2, ## -> h3, ### -> h4 */
		if ( preg_match( '/^(#{1,3})\s+(.*)$/u', $l, $m ) ) {
			if ( $in_list ) {
				$html   .= "</ul>\n";
				$in_list = false;
			}
			$level = strlen( $m[1] ) + 1; // # = h2, ## = h3, ### = h4
			$html .= '<h' . $level . '>' . aif_inline_format( $m[2] ) . '</h' . $level . ">\n";
			continue;
		}

		/* Puces : "- texte" */
		if ( preg_match( '/^-\s+(.*)$/u', $l, $m ) ) {
			if ( ! $in_list ) {
				$html   .= "<ul>\n";
				$in_list = true;
			}
			$html .= '<li>' . aif_inline_format( $m[1] ) . "</li>\n";
			continue;
		}

		/* Paragraphe */
		if ( $in_list ) {
			$html   .= "</ul>\n";
			$in_list = false;
		}
		$html .= '<p>' . aif_inline_format( $l ) . "</p>\n";
	}

	if ( $in_list ) {
		$html .= "</ul>\n";
	}

	return $html;
}

/**
 * Applique le formatage inline : gras, italique, liens, code inline.
 * Echappe d'abord le HTML, puis applique les styles.
 */
function aif_inline_format( string $text ): string {
	$t = esc_html( $text );

	/* Code inline : `code` */
	$t = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $t );

	/* Gras : **texte** */
	$t = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $t );

	/* Italique : *texte* (pas au milieu d'un mot) */
	$t = preg_replace( '/(?<!\w)\*([^*]+)\*(?!\w)/', '<em>$1</em>', $t );

	/* Liens : [texte](url) */
	$t = preg_replace(
		'/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/',
		'<a href="$2" rel="noopener" target="_blank">$1</a>',
		$t
	);

	return $t;
}
