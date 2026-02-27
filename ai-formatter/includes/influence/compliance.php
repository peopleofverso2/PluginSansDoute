<?php
/**
 * SansDoute Influence — Compliance Checker.
 *
 * Verifie automatiquement :
 * 1. Conformite ARPP (mentions legales de partenariat commercial)
 * 2. Conformite au brief (mots-cles, liens, ton)
 *
 * Reglementation ARPP :
 * - Art. L. 121-1 du Code de la consommation
 * - Recommandation « Communication publicitaire digitale » de l'ARPP
 * - La mention doit etre explicite, instantanee, non ambigue
 * - Position : visible des le debut du contenu (pas en bas, pas en petit)
 *
 * @author Peopleofverso
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifie la compliance d'un contenu par rapport a un brief.
 *
 * @param string $content   Le contenu texte/HTML du createur.
 * @param array  $brief     Le brief structure (sortie de aif_brief_get).
 * @return array {
 *   score:      int     0-100 score global,
 *   checks:     array   Liste detaillee des checks,
 *   passed:     int     Nombre de checks passes,
 *   total:      int     Nombre total de checks,
 *   warnings:   array   Messages d'avertissement,
 *   blockers:   array   Problemes bloquants (compliance impossible),
 * }
 */
function aif_compliance_check( string $content, array $brief ): array {
	$text   = wp_strip_all_tags( $content );
	$text_lower = mb_strtolower( $text );
	$html_lower = mb_strtolower( $content );

	$checks   = [];
	$warnings = [];
	$blockers = [];

	/* ================================================================ */
	/*  1. ARPP — Mentions legales                                      */
	/* ================================================================ */

	$legal_mentions = ! empty( $brief['legal_mentions'] ) ? $brief['legal_mentions'] : [];

	/* Si pas de mentions definies dans le brief, on verifie les mentions ARPP standard */
	$arpp_patterns = [
		'en partenariat avec',
		'partenariat commercial',
		'contenu sponsorise',
		'sponsorise par',
		'collaboration commerciale',
		'#pub',
		'#sponsorise',
		'#partenariat',
		'#ad',
		'#sponsored',
	];

	/* Check 1a : presence d'au moins une mention ARPP */
	$arpp_found = false;
	$arpp_matches = [];
	foreach ( $arpp_patterns as $pattern ) {
		if ( mb_strpos( $text_lower, $pattern ) !== false ) {
			$arpp_found = true;
			$arpp_matches[] = $pattern;
		}
	}

	$checks[] = [
		'id'       => 'arpp_presence',
		'label'    => 'Mention ARPP presente',
		'passed'   => $arpp_found,
		'details'  => $arpp_found
			? 'Trouve : ' . implode( ', ', $arpp_matches )
			: 'Aucune mention de partenariat commercial detectee.',
		'weight'   => 25,
		'category' => 'legal',
	];

	if ( ! $arpp_found ) {
		$blockers[] = 'Aucune mention ARPP detectee. La loi francaise exige une mention explicite du partenariat commercial (ex : « En partenariat avec [marque] », #pub, #sponsorise).';
	}

	/* Check 1b : position de la mention (doit etre dans le premier tiers) */
	if ( $arpp_found && mb_strlen( $text ) > 100 ) {
		$first_third = mb_substr( $text_lower, 0, (int) ( mb_strlen( $text_lower ) / 3 ) );
		$position_ok = false;
		foreach ( $arpp_matches as $match ) {
			if ( mb_strpos( $first_third, $match ) !== false ) {
				$position_ok = true;
				break;
			}
		}

		$checks[] = [
			'id'       => 'arpp_position',
			'label'    => 'Mention ARPP visible (premier tiers)',
			'passed'   => $position_ok,
			'details'  => $position_ok
				? 'La mention est bien placee dans le debut du contenu.'
				: 'La mention est presente mais trop bas dans le contenu. Elle doit etre visible immediatement.',
			'weight'   => 15,
			'category' => 'legal',
		];

		if ( ! $position_ok ) {
			$warnings[] = 'Deplacez la mention de partenariat plus haut dans le contenu (premier tiers).';
		}
	}

	/* Check 1c : mentions legales specifiques du brief */
	foreach ( $legal_mentions as $mention ) {
		$mention_lower = mb_strtolower( $mention );
		$found = mb_strpos( $text_lower, $mention_lower ) !== false;

		$checks[] = [
			'id'       => 'legal_' . sanitize_key( $mention ),
			'label'    => 'Mention : « ' . $mention . ' »',
			'passed'   => $found,
			'details'  => $found
				? 'Mention trouvee dans le contenu.'
				: 'Mention absente du contenu.',
			'weight'   => 10,
			'category' => 'legal',
		];

		if ( ! $found ) {
			$blockers[] = 'Mention legale manquante : « ' . $mention . ' »';
		}
	}

	/* ================================================================ */
	/*  2. Mots-cles obligatoires                                       */
	/* ================================================================ */

	$keywords_req = ! empty( $brief['keywords_required'] ) ? $brief['keywords_required'] : [];
	$kw_found = 0;
	$kw_total = count( $keywords_req );

	foreach ( $keywords_req as $kw ) {
		$kw_lower = mb_strtolower( trim( $kw ) );
		$found = mb_strpos( $text_lower, $kw_lower ) !== false;

		$checks[] = [
			'id'       => 'kw_req_' . sanitize_key( $kw ),
			'label'    => 'Mot-cle : « ' . $kw . ' »',
			'passed'   => $found,
			'details'  => $found
				? 'Mot-cle present dans le contenu.'
				: 'Mot-cle absent du contenu.',
			'weight'   => 5,
			'category' => 'brief',
		];

		if ( $found ) {
			$kw_found++;
		}
	}

	if ( $kw_total > 0 && $kw_found < $kw_total ) {
		$warnings[] = sprintf(
			'%d/%d mots-cles obligatoires presents. Manquants : %s',
			$kw_found,
			$kw_total,
			implode( ', ', array_filter( $keywords_req, function ( $kw ) use ( $text_lower ) {
				return mb_strpos( $text_lower, mb_strtolower( trim( $kw ) ) ) === false;
			} ) )
		);
	}

	/* ================================================================ */
	/*  3. Mots-cles interdits                                          */
	/* ================================================================ */

	$keywords_forb = ! empty( $brief['keywords_forbidden'] ) ? $brief['keywords_forbidden'] : [];
	$forb_found = [];

	foreach ( $keywords_forb as $kw ) {
		$kw_lower = mb_strtolower( trim( $kw ) );
		$found = mb_strpos( $text_lower, $kw_lower ) !== false;

		$checks[] = [
			'id'       => 'kw_forb_' . sanitize_key( $kw ),
			'label'    => 'Interdit : « ' . $kw . ' »',
			'passed'   => ! $found,
			'details'  => $found
				? 'ATTENTION : terme interdit present dans le contenu.'
				: 'OK — terme interdit absent.',
			'weight'   => 8,
			'category' => 'brief',
		];

		if ( $found ) {
			$forb_found[] = $kw;
		}
	}

	if ( ! empty( $forb_found ) ) {
		$blockers[] = 'Termes interdits detectes : ' . implode( ', ', $forb_found );
	}

	/* ================================================================ */
	/*  4. Liens obligatoires                                           */
	/* ================================================================ */

	$links_req = ! empty( $brief['links_required'] ) ? $brief['links_required'] : [];

	foreach ( $links_req as $link ) {
		/* Check in HTML (links might be in href attributes) */
		$found = mb_strpos( $html_lower, mb_strtolower( $link ) ) !== false;

		/* Also check without protocol */
		if ( ! $found ) {
			$link_no_proto = preg_replace( '#^https?://#', '', $link );
			$found = mb_strpos( $html_lower, mb_strtolower( $link_no_proto ) ) !== false;
		}

		$checks[] = [
			'id'       => 'link_' . sanitize_key( $link ),
			'label'    => 'Lien : ' . aif_truncate_url( $link ),
			'passed'   => $found,
			'details'  => $found
				? 'Lien present dans le contenu.'
				: 'Lien absent du contenu.',
			'weight'   => 8,
			'category' => 'brief',
		];

		if ( ! $found ) {
			$warnings[] = 'Lien obligatoire manquant : ' . $link;
		}
	}

	/* ================================================================ */
	/*  5. Calcul du score                                              */
	/* ================================================================ */

	$total_weight  = 0;
	$passed_weight = 0;
	$passed_count  = 0;

	foreach ( $checks as $check ) {
		$total_weight += $check['weight'];
		if ( $check['passed'] ) {
			$passed_weight += $check['weight'];
			$passed_count++;
		}
	}

	$score = $total_weight > 0
		? (int) round( ( $passed_weight / $total_weight ) * 100 )
		: 100;

	/* Blockers cap the score at 40 */
	if ( ! empty( $blockers ) ) {
		$score = min( $score, 40 );
	}

	return [
		'score'    => $score,
		'checks'   => $checks,
		'passed'   => $passed_count,
		'total'    => count( $checks ),
		'warnings' => $warnings,
		'blockers' => $blockers,
	];
}

/**
 * Truncate URL for display.
 */
function aif_truncate_url( string $url, int $max = 40 ): string {
	$clean = preg_replace( '#^https?://#', '', $url );
	if ( mb_strlen( $clean ) > $max ) {
		return mb_substr( $clean, 0, $max - 3 ) . '...';
	}
	return $clean;
}
