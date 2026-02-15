<?php
/**
 * Detection automatique du ton d'un texte.
 *
 * Analyse heuristique basee sur :
 * - Longueur moyenne des phrases
 * - Frequence des marqueurs formels / informels / techniques / litteraires
 * - Ponctuation (exclamations, questions)
 * - Personne utilisee (vous / tu)
 *
 * @author Peopleofverso
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detecte le ton dominant d'un texte.
 *
 * @param string $text Le texte brut a analyser.
 * @return array{tone: string, confidence: int, details: array}
 */
function aif_detect_tone( string $text ): array {
	$clean = wp_strip_all_tags( $text );
	$clean = trim( $clean );

	if ( mb_strlen( $clean ) < 20 ) {
		return [ 'tone' => 'indetermine', 'confidence' => 0, 'details' => [] ];
	}

	$sentences      = preg_split( '/[.!?]+/u', $clean, -1, PREG_SPLIT_NO_EMPTY );
	$sentence_count = count( $sentences );
	$word_count     = str_word_count( $clean, 0, "\xC0-\xFF" );

	if ( $sentence_count === 0 || $word_count === 0 ) {
		return [ 'tone' => 'indetermine', 'confidence' => 0, 'details' => [] ];
	}

	$avg_sentence_len = $word_count / $sentence_count;
	$exclamations     = substr_count( $clean, '!' );
	$questions        = substr_count( $clean, '?' );

	/* ---- Marqueurs formels ---- */
	$formal_patterns = [
		'/\bvous\b/iu', '/\bmonsieur\b/iu', '/\bmadame\b/iu',
		'/\bcordialement\b/iu', '/\bveuillez\b/iu',
		'/\bn[eé]anmoins\b/iu', '/\btoutefois\b/iu',
		'/\bpar\s+cons[eé]quent\b/iu', '/\ben\s+outre\b/iu',
		'/\bainsi\s+que\b/iu', '/\bconform[eé]ment\b/iu',
	];
	$formal_count = 0;
	foreach ( $formal_patterns as $p ) {
		$formal_count += preg_match_all( $p, $clean );
	}

	/* ---- Marqueurs informels ---- */
	$informal_patterns = [
		'/\btu\b/iu', '/\bton\b/iu', '/\bta\b/iu', '/\btes\b/iu',
		'/\bmec\b/iu', '/\bcool\b/iu', '/\bgenre\b/iu',
		'/\btrop\b/iu', '/\bkiffer\b/iu', '/\bouf\b/iu',
		'/\bcarr[eé]ment\b/iu', '/\bgrave\b/iu', '/\bwesh\b/iu',
		'/\blol\b/iu', '/\bmdr\b/iu',
	];
	$informal_count = 0;
	foreach ( $informal_patterns as $p ) {
		$informal_count += preg_match_all( $p, $clean );
	}

	/* ---- Marqueurs techniques ---- */
	$tech_patterns = [
		'/\bAPI\b/', '/\bframework\b/i', '/\balgorithm/i',
		'/\bfonction\b/i', '/\bvariable\b/i', '/\bparam[eè]tre\b/i',
		'/\bserveur\b/i', '/\bdatabase\b/i', '/\bbase\s+de\s+donn[eé]es\b/i',
		'/\bcode\b/i', '/\bd[eé]ploiement\b/i', '/\bconfiguration\b/i',
		'/`[^`]+`/',
	];
	$tech_count = 0;
	foreach ( $tech_patterns as $p ) {
		$tech_count += preg_match_all( $p, $clean );
	}

	/* ---- Marqueurs litteraires ---- */
	$literary_patterns = [
		'/\bcomme\s+si\b/iu', '/\btandis\s+que\b/iu',
		'/\bainsi\b/iu', '/\bsemblait\b/iu', '/\bmurmur/iu',
		'/\bsouffle\b/iu', '/\bombre\b/iu', '/\blumi[eè]re\b/iu',
		'/\bsilence\b/iu', '/\bm[eé]lancolie\b/iu',
		'/\br[eê]v/iu', '/\b[aâ]me\b/iu',
	];
	$literary_count = 0;
	foreach ( $literary_patterns as $p ) {
		$literary_count += preg_match_all( $p, $clean );
	}

	/* ---- Marqueurs journalistiques ---- */
	$journo_patterns = [
		'/\bselon\b/iu', '/\bd\'apr[eè]s\b/iu', '/\brapporte\b/iu',
		'/\bsource\b/iu', '/\bd[eé]clar/iu', '/\bannonce\b/iu',
		'/\bexclusi/iu', '/\benqu[eê]te\b/iu',
	];
	$journo_count = 0;
	foreach ( $journo_patterns as $p ) {
		$journo_count += preg_match_all( $p, $clean );
	}

	/* ---- Scoring ---- */
	$scores = [
		'formel'         => $formal_count * 2 + ( $avg_sentence_len > 20 ? 2 : 0 ),
		'informel'       => $informal_count * 2 + $exclamations * 0.5 + ( $avg_sentence_len < 12 ? 2 : 0 ),
		'journalistique' => $journo_count * 2 + ( $avg_sentence_len >= 12 && $avg_sentence_len <= 20 ? 2 : 0 ) + ( $questions > 1 ? 1 : 0 ),
		'litteraire'     => $literary_count * 2 + ( $avg_sentence_len > 25 ? 3 : 0 ),
		'technique'      => $tech_count * 2,
	];

	arsort( $scores );
	$top       = key( $scores );
	$max_score = current( $scores );
	$total     = array_sum( $scores );

	if ( $max_score === 0 || $total === 0 ) {
		return [
			'tone'       => 'neutre',
			'confidence' => 50,
			'details'    => [
				'avg_sentence_length' => round( $avg_sentence_len, 1 ),
				'word_count'          => $word_count,
				'scores'              => $scores,
			],
		];
	}

	$confidence = (int) round( ( $max_score / $total ) * 100 );
	$confidence = min( $confidence, 95 );

	return [
		'tone'       => $top,
		'confidence' => $confidence,
		'details'    => [
			'avg_sentence_length' => round( $avg_sentence_len, 1 ),
			'word_count'          => $word_count,
			'scores'              => $scores,
		],
	];
}
