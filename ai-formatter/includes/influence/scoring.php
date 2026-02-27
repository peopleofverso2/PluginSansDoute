<?php
/**
 * SansDoute Influence — Content Scoring Engine.
 *
 * Analyse la qualite d'un contenu sans bullshit :
 * - Authenticite : le contenu sonne-t-il naturel ou comme du copywriting ?
 * - Lisibilite : est-il facile a lire ?
 * - SEO basique : sera-t-il trouvable ?
 *
 * Tout est heuristique (pas d'appel IA) pour etre instantane.
 *
 * @author Peopleofverso
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Score un contenu.
 *
 * @param string $content Le contenu HTML du createur.
 * @param array  $brief   Le brief structure (optionnel, pour scoring contextuel).
 * @return array {
 *   score:         int     0-100 score global,
 *   authenticity:  array   Detail du score d'authenticite,
 *   readability:   array   Detail du score de lisibilite,
 *   seo:           array   Detail du score SEO,
 *   suggestions:   array   Suggestions d'amelioration,
 * }
 */
function aif_score_content( string $content, array $brief = [] ): array {
	$text = wp_strip_all_tags( $content );
	$text_lower = mb_strtolower( $text );

	$authenticity = aif_score_authenticity( $text, $text_lower );
	$readability  = aif_score_readability( $text );
	$seo          = aif_score_seo( $content, $text, $brief );

	/* Weighted average : authenticity 40%, readability 30%, SEO 30% */
	$global = (int) round(
		$authenticity['score'] * 0.40 +
		$readability['score']  * 0.30 +
		$seo['score']          * 0.30
	);

	$suggestions = array_merge(
		$authenticity['suggestions'],
		$readability['suggestions'],
		$seo['suggestions']
	);

	return [
		'score'        => $global,
		'authenticity' => $authenticity,
		'readability'  => $readability,
		'seo'          => $seo,
		'suggestions'  => $suggestions,
	];
}

/* ================================================================ */
/*  Authenticite                                                     */
/* ================================================================ */

function aif_score_authenticity( string $text, string $text_lower ): array {
	$suggestions = [];
	$penalties   = 0;
	$bonuses     = 0;

	$word_count = str_word_count( $text, 0, "\xC0-\xFF" );
	if ( $word_count < 10 ) {
		return [
			'score'       => 0,
			'details'     => [ 'word_count' => $word_count ],
			'suggestions' => [ 'Le contenu est trop court pour etre analyse.' ],
		];
	}

	/* --- Tics publicitaires (cliches marketing) --- */
	$ad_cliches = [
		'revolutionnaire', 'game changer', 'disruptif', 'disruptive',
		'incontournable', 'indispensable', 'absolument genial',
		'n\'attendez plus', 'profitez', 'offre exclusive',
		'meilleur du marche', 'numero un', 'leader du marche',
		'solution ideale', 'rapport qualite prix imbattable',
		'vous allez adorer', 'vous ne le regretterez pas',
		'foncez', 'c\'est un must', 'le must have',
		'a ne pas manquer', 'coup de coeur', 'coup de foudre',
		'je suis fan', 'je suis accro',
	];

	$cliche_count = 0;
	$found_cliches = [];
	foreach ( $ad_cliches as $cliche ) {
		$count = mb_substr_count( $text_lower, $cliche );
		if ( $count > 0 ) {
			$cliche_count += $count;
			$found_cliches[] = $cliche;
		}
	}

	/* Penalty : -5 per cliche, max -40 */
	$cliche_penalty = min( $cliche_count * 5, 40 );
	$penalties += $cliche_penalty;

	if ( $cliche_count > 0 ) {
		$suggestions[] = sprintf(
			'%d cliche(s) publicitaire(s) detecte(s) : %s. Un contenu authentique evite le langage marketing.',
			$cliche_count,
			implode( ', ', array_slice( $found_cliches, 0, 3 ) )
		);
	}

	/* --- Voix personnelle (bonus) --- */
	$personal_markers = [
		'/\bje\b/iu', '/\bmon\b/iu', '/\bma\b/iu', '/\bmes\b/iu',
		'/\bj\'ai\b/iu', '/\bj\'aime\b/iu',
		'/\bpersonnellement\b/iu', '/\bmon experience\b/iu',
		'/\bmon avis\b/iu', '/\bfranchement\b/iu',
	];

	$personal_count = 0;
	foreach ( $personal_markers as $pattern ) {
		$personal_count += preg_match_all( $pattern, $text );
	}

	/* Normalized per 100 words */
	$personal_density = ( $personal_count / $word_count ) * 100;

	if ( $personal_density >= 1.5 ) {
		$bonuses += 15; /* Strong personal voice */
	} elseif ( $personal_density >= 0.5 ) {
		$bonuses += 8;
	} else {
		$suggestions[] = 'Peu de voix personnelle detectee. Utilisez « je », « mon experience », etc. pour un ton plus authentique.';
	}

	/* --- Diversite du vocabulaire --- */
	$words = preg_split( '/\s+/u', $text_lower );
	$words = array_filter( $words, function ( $w ) {
		return mb_strlen( $w ) > 3; /* Skip short words */
	} );
	$unique_words = array_unique( $words );
	$total_sig_words = count( $words );
	$unique_sig_words = count( $unique_words );

	$vocab_diversity = $total_sig_words > 0
		? $unique_sig_words / $total_sig_words
		: 0;

	if ( $vocab_diversity >= 0.65 ) {
		$bonuses += 10;
	} elseif ( $vocab_diversity < 0.45 ) {
		$penalties += 10;
		$suggestions[] = 'Vocabulaire repetitif. Variez les termes pour un contenu plus riche.';
	}

	/* --- Superlatifs excessifs --- */
	$superlatives = [
		'/\bplus\s+(?:beau|belle|grand|meilleur|fort|incroyable)\b/iu',
		'/\ble\s+meilleur\b/iu', '/\bla\s+meilleure\b/iu',
		'/\babsolument\b/iu', '/\btotalement\b/iu',
		'/\bincroyable(?:ment)?\b/iu', '/\bextraordinaire\b/iu',
		'/\bfabuleu/iu', '/\bsensationnel/iu',
		'/\bexceptionnel/iu', '/\bparfait(?:ement)?\b/iu',
	];

	$superlative_count = 0;
	foreach ( $superlatives as $pattern ) {
		$superlative_count += preg_match_all( $pattern, $text );
	}

	$superlative_density = ( $superlative_count / $word_count ) * 100;
	if ( $superlative_density > 2.0 ) {
		$penalties += 15;
		$suggestions[] = 'Trop de superlatifs. Un contenu credible reste mesure.';
	} elseif ( $superlative_density > 1.0 ) {
		$penalties += 5;
	}

	/* --- Score --- */
	$base_score = 70;
	$score = max( 0, min( 100, $base_score - $penalties + $bonuses ) );

	return [
		'score'       => $score,
		'details'     => [
			'ad_cliches'       => $cliche_count,
			'personal_voice'   => round( $personal_density, 2 ),
			'vocab_diversity'  => round( $vocab_diversity, 2 ),
			'superlatives'     => $superlative_count,
			'word_count'       => $word_count,
		],
		'suggestions' => $suggestions,
	];
}

/* ================================================================ */
/*  Lisibilite                                                       */
/* ================================================================ */

function aif_score_readability( string $text ): array {
	$suggestions = [];
	$score = 70;

	$sentences = preg_split( '/[.!?]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
	$sentences = array_filter( $sentences, function ( $s ) {
		return mb_strlen( trim( $s ) ) > 5;
	} );
	$sentence_count = count( $sentences );
	$word_count = str_word_count( $text, 0, "\xC0-\xFF" );

	if ( $sentence_count === 0 || $word_count < 10 ) {
		return [
			'score'       => 0,
			'details'     => [],
			'suggestions' => [ 'Contenu trop court pour analyser la lisibilite.' ],
		];
	}

	/* --- Longueur moyenne des phrases --- */
	$avg_sentence_len = $word_count / $sentence_count;

	if ( $avg_sentence_len >= 15 && $avg_sentence_len <= 22 ) {
		$score += 15; /* Sweet spot */
	} elseif ( $avg_sentence_len > 30 ) {
		$score -= 20;
		$suggestions[] = sprintf(
			'Phrases trop longues (moyenne : %d mots). Cible : 15-22 mots par phrase.',
			(int) $avg_sentence_len
		);
	} elseif ( $avg_sentence_len > 25 ) {
		$score -= 10;
		$suggestions[] = sprintf(
			'Phrases un peu longues (moyenne : %d mots). Essayez de raccourcir.',
			(int) $avg_sentence_len
		);
	} elseif ( $avg_sentence_len < 8 ) {
		$score -= 5;
		$suggestions[] = 'Phrases tres courtes. Variez la longueur pour un meilleur rythme.';
	}

	/* --- Variation de longueur (bon signe) --- */
	$lengths = array_map( function ( $s ) {
		return str_word_count( trim( $s ), 0, "\xC0-\xFF" );
	}, $sentences );

	if ( count( $lengths ) > 2 ) {
		$mean = array_sum( $lengths ) / count( $lengths );
		$variance = 0;
		foreach ( $lengths as $len ) {
			$variance += ( $len - $mean ) ** 2;
		}
		$std_dev = sqrt( $variance / count( $lengths ) );
		$cv = $mean > 0 ? $std_dev / $mean : 0;

		if ( $cv > 0.4 ) {
			$score += 5; /* Good variation */
		} elseif ( $cv < 0.15 ) {
			$score -= 5;
			$suggestions[] = 'Toutes les phrases ont la meme longueur. Variez le rythme.';
		}
	}

	/* --- Paragraphes --- */
	$paragraphs = preg_split( '/\n\s*\n/', $text );
	$para_count = count( array_filter( $paragraphs, function ( $p ) {
		return mb_strlen( trim( $p ) ) > 10;
	} ) );

	if ( $para_count >= 3 && $word_count > 200 ) {
		$score += 5;
	} elseif ( $para_count < 2 && $word_count > 300 ) {
		$score -= 10;
		$suggestions[] = 'Le contenu manque de paragraphes. Aerez le texte.';
	}

	$score = max( 0, min( 100, $score ) );

	return [
		'score'       => $score,
		'details'     => [
			'avg_sentence_length' => round( $avg_sentence_len, 1 ),
			'sentence_count'      => $sentence_count,
			'paragraph_count'     => $para_count,
			'word_count'          => $word_count,
		],
		'suggestions' => $suggestions,
	];
}

/* ================================================================ */
/*  SEO basique                                                      */
/* ================================================================ */

function aif_score_seo( string $html, string $text, array $brief = [] ): array {
	$suggestions = [];
	$score = 50; /* Start neutral */

	$word_count = str_word_count( $text, 0, "\xC0-\xFF" );

	/* --- Longueur du contenu --- */
	if ( $word_count >= 1200 ) {
		$score += 20;
	} elseif ( $word_count >= 800 ) {
		$score += 15;
	} elseif ( $word_count >= 500 ) {
		$score += 5;
	} else {
		$score -= 10;
		$suggestions[] = sprintf(
			'Contenu court (%d mots). Pour un contenu durable, visez 800+ mots.',
			$word_count
		);
	}

	/* --- Structure de headings --- */
	preg_match_all( '/<h([2-4])[^>]*>/i', $html, $heading_matches );
	$heading_count = count( $heading_matches[0] );

	if ( $heading_count >= 2 ) {
		$score += 10;
	} elseif ( $heading_count === 0 && $word_count > 300 ) {
		$score -= 10;
		$suggestions[] = 'Ajoutez des sous-titres (H2, H3) pour structurer le contenu.';
	}

	/* Check heading hierarchy */
	if ( ! empty( $heading_matches[1] ) ) {
		$levels = array_map( 'intval', $heading_matches[1] );
		$has_h2 = in_array( 2, $levels, true );
		$has_h3_without_h2 = in_array( 3, $levels, true ) && ! $has_h2;

		if ( $has_h3_without_h2 ) {
			$score -= 5;
			$suggestions[] = 'H3 sans H2 parent. Respectez la hierarchie des titres.';
		}
	}

	/* --- Mots-cles dans les headings --- */
	if ( ! empty( $brief['keywords_required'] ) && $heading_count > 0 ) {
		preg_match_all( '/<h[2-4][^>]*>(.*?)<\/h[2-4]>/is', $html, $heading_texts );
		$headings_text = mb_strtolower( implode( ' ', $heading_texts[1] ?? [] ) );

		$kw_in_headings = 0;
		foreach ( $brief['keywords_required'] as $kw ) {
			if ( mb_strpos( $headings_text, mb_strtolower( $kw ) ) !== false ) {
				$kw_in_headings++;
			}
		}

		if ( $kw_in_headings > 0 ) {
			$score += 5;
		} else {
			$suggestions[] = 'Aucun mot-cle du brief dans les sous-titres. Integrez-les naturellement.';
		}
	}

	/* --- Liens --- */
	preg_match_all( '/<a\s[^>]*href/i', $html, $link_matches );
	$link_count = count( $link_matches[0] );

	if ( $link_count >= 2 ) {
		$score += 5;
	} elseif ( $link_count === 0 && $word_count > 300 ) {
		$suggestions[] = 'Aucun lien dans le contenu. Ajoutez des liens pertinents.';
	}

	$score = max( 0, min( 100, $score ) );

	return [
		'score'       => $score,
		'details'     => [
			'word_count'       => $word_count,
			'heading_count'    => $heading_count,
			'link_count'       => $link_count,
		],
		'suggestions' => $suggestions,
	];
}
