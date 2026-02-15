<?php
/**
 * Convertit du pseudo-markdown (texte nettoye) en HTML propre.
 *
 * Gere : titres, listes a puces, listes numerotees, blockquotes,
 * separateurs, et formatage inline (gras, italique, code, liens).
 *
 * @author Peopleofverso
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convertit un texte markdownish en HTML semantique.
 *
 * @param string $text Le texte a convertir.
 * @return string HTML propre.
 */
function aif_markdownish_to_html( string $text ): string {
	$lines       = explode( "\n", $text );
	$html        = '';
	$in_ul       = false;
	$in_ol       = false;
	$in_bq       = false;
	$bq_buffer   = [];

	foreach ( $lines as $line ) {
		$l = trim( $line );

		/* Ligne vide : ferme les contextes ouverts */
		if ( $l === '' ) {
			$html .= aif_close_list( $in_ul, $in_ol );
			if ( $in_bq ) {
				$html    .= '<blockquote>' . aif_inline_format( implode( ' ', $bq_buffer ) ) . "</blockquote>\n";
				$in_bq    = false;
				$bq_buffer = [];
			}
			continue;
		}

		/* Separateur horizontal : --- ou ___ ou *** (seuls sur une ligne) */
		if ( preg_match( '/^[-_\*]{3,}$/', $l ) ) {
			$html .= aif_close_list( $in_ul, $in_ol );
			$html .= "<hr>\n";
			continue;
		}

		/* Blockquote : > texte */
		if ( preg_match( '/^>\s*(.*)$/u', $l, $m ) ) {
			$html .= aif_close_list( $in_ul, $in_ol );
			$in_bq       = true;
			$bq_buffer[] = $m[1];
			continue;
		}

		/* Si on etait dans un blockquote et la ligne ne commence pas par >, on ferme */
		if ( $in_bq ) {
			$html    .= '<blockquote>' . aif_inline_format( implode( ' ', $bq_buffer ) ) . "</blockquote>\n";
			$in_bq    = false;
			$bq_buffer = [];
		}

		/* Titres : # -> h2, ## -> h3, ### -> h4 */
		if ( preg_match( '/^(#{1,3})\s+(.*)$/u', $l, $m ) ) {
			$html .= aif_close_list( $in_ul, $in_ol );
			$level = strlen( $m[1] ) + 1; // # = h2, ## = h3, ### = h4
			$html .= '<h' . $level . '>' . aif_inline_format( $m[2] ) . '</h' . $level . ">\n";
			continue;
		}

		/* Liste a puces : "- texte" ou "* texte" */
		if ( preg_match( '/^[-\*]\s+(.*)$/u', $l, $m ) ) {
			if ( $in_ol ) {
				$html .= "</ol>\n";
				$in_ol = false;
			}
			if ( ! $in_ul ) {
				$html .= "<ul>\n";
				$in_ul = true;
			}
			$html .= '<li>' . aif_inline_format( $m[1] ) . "</li>\n";
			continue;
		}

		/* Liste numerotee : "1. texte", "2) texte" */
		if ( preg_match( '/^\d+[\.\)]\s+(.*)$/u', $l, $m ) ) {
			if ( $in_ul ) {
				$html .= "</ul>\n";
				$in_ul = false;
			}
			if ( ! $in_ol ) {
				$html .= "<ol>\n";
				$in_ol = true;
			}
			$html .= '<li>' . aif_inline_format( $m[1] ) . "</li>\n";
			continue;
		}

		/* Paragraphe */
		$html .= aif_close_list( $in_ul, $in_ol );
		$html .= '<p>' . aif_inline_format( $l ) . "</p>\n";
	}

	/* Ferme les contextes restants */
	$html .= aif_close_list( $in_ul, $in_ol );
	if ( $in_bq ) {
		$html .= '<blockquote>' . aif_inline_format( implode( ' ', $bq_buffer ) ) . "</blockquote>\n";
	}

	return $html;
}

/**
 * Ferme les listes ouvertes.
 *
 * @param bool &$in_ul Reference : est-on dans une <ul> ?
 * @param bool &$in_ol Reference : est-on dans une <ol> ?
 * @return string Le HTML de fermeture.
 */
function aif_close_list( bool &$in_ul, bool &$in_ol ): string {
	$out = '';
	if ( $in_ul ) {
		$out  .= "</ul>\n";
		$in_ul = false;
	}
	if ( $in_ol ) {
		$out  .= "</ol>\n";
		$in_ol = false;
	}
	return $out;
}

/**
 * Applique le formatage inline : gras, italique, liens, code inline.
 * Echappe d'abord le HTML, puis applique les styles.
 *
 * @param string $text La ligne de texte.
 * @return string Le HTML inline.
 */
function aif_inline_format( string $text ): string {
	$t = esc_html( $text );

	/* Code inline : `code` */
	$t = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $t );

	/* Gras : **texte** ou __texte__ */
	$t = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $t );
	$t = preg_replace( '/__([^_]+)__/', '<strong>$1</strong>', $t );

	/* Italique : *texte* ou _texte_ (pas au milieu d'un mot) */
	$t = preg_replace( '/(?<!\w)\*([^*]+)\*(?!\w)/', '<em>$1</em>', $t );
	$t = preg_replace( '/(?<!\w)_([^_]+)_(?!\w)/', '<em>$1</em>', $t );

	/* Liens : [texte](url) */
	$t = preg_replace(
		'/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/',
		'<a href="$2" rel="noopener" target="_blank">$1</a>',
		$t
	);

	return $t;
}
