<?php
/**
 * Plugin Name: AI Formatter (Clean Copy)
 * Plugin URI:  https://github.com/peopleofverso2/PluginSansDoute
 * Description: Colle/importe un texte, nettoie les tics LLM, corrige orthographe + typographie FR, et applique un style CSS preset. Respecte la voix des auteurs.
 * Version:     1.0.0
 * Author:      Peopleofverso
 * Author URI:  https://github.com/peopleofverso2
 * Text Domain: ai-formatter
 * Requires PHP: 7.4
 * License:     MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIF_VERSION', '1.0.0' );
define( 'AIF_PATH', plugin_dir_path( __FILE__ ) );
define( 'AIF_URL', plugin_dir_url( __FILE__ ) );

/* ================================================================ */
/*  Includes                                                        */
/* ================================================================ */
require_once AIF_PATH . 'includes/clean.php';
require_once AIF_PATH . 'includes/typography.php';
require_once AIF_PATH . 'includes/html.php';
require_once AIF_PATH . 'includes/ai-provider.php';
require_once AIF_PATH . 'includes/settings.php';
require_once AIF_PATH . 'includes/translation.php';
require_once AIF_PATH . 'includes/gutenberg.php';
require_once AIF_PATH . 'includes/frontend.php';

/* ================================================================ */
/*  Admin page : Outils -> AI Formatter                             */
/* ================================================================ */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'tools.php',
		'AI Formatter',
		'AI Formatter',
		'edit_posts',
		'ai-formatter',
		'aif_render_admin_page'
	);
} );

/* ================================================================ */
/*  Enqueue assets (admin page only)                                */
/* ================================================================ */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( $hook !== 'tools_page_ai-formatter' ) {
		return;
	}

	wp_enqueue_style( 'aif-admin', AIF_URL . 'assets/admin.css', [], AIF_VERSION );
	wp_enqueue_script( 'aif-admin', AIF_URL . 'assets/admin.js', [ 'wp-api-fetch' ], AIF_VERSION, true );
	wp_localize_script( 'aif-admin', 'AIF', [
		'restUrl'      => esc_url_raw( rest_url( 'ai-formatter/v1/format' ) ),
		'translateUrl' => esc_url_raw( rest_url( 'ai-formatter/v1/translate' ) ),
		'nonce'        => wp_create_nonce( 'wp_rest' ),
		'presets'      => array_keys( aif_css_presets() ),
		'languages'    => aif_translation_languages(),
	] );
} );

/* ================================================================ */
/*  CSS presets registry                                            */
/* ================================================================ */
function aif_css_presets(): array {
	return [
		'clean'     => 'Clean',
		'sansdoute' => 'SansDoute',
		'tech'      => 'Doc technique',
		'apple'     => 'Apple clean',
	];
}

/* ================================================================ */
/*  Admin page render                                               */
/* ================================================================ */
function aif_render_admin_page() {
	$presets = aif_css_presets();
	?>
	<div class="wrap aif-wrap">
		<h1>AI Formatter</h1>

		<div class="aif-grid">
			<!-- Left: input -->
			<div class="aif-panel">
				<h2>Texte source</h2>
				<textarea id="aif-input" rows="18" placeholder="Colle ici ton texte brut (ou glisse un fichier .txt / .md)..."></textarea>

				<div class="aif-row">
					<label for="aif-mode">Mode</label>
					<select id="aif-mode">
						<option value="clean_only">Nettoyage LLM + typographie (sans IA)</option>
						<option value="typo_only">Typographie seule (texte d'auteur)</option>
						<option value="ai_proofread">IA : correction orthographe</option>
					</select>
				</div>

				<div class="aif-row aif-style-row" style="display:none;">
					<label for="aif-style">Style IA</label>
					<select id="aif-style">
						<option value="neutre">Neutre (corrections minimales)</option>
						<option value="journalistique">Journalistique</option>
						<option value="gonzo">Gonzo-pro</option>
						<option value="corporate">Corporate</option>
					</select>
				</div>

				<div class="aif-row">
					<label for="aif-css">Preset CSS</label>
					<select id="aif-css">
						<?php foreach ( $presets as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="aif-actions">
					<button class="button button-primary" id="aif-run">Nettoyer &amp; Formater</button>
					<button class="button" id="aif-copy" disabled>Copier HTML</button>
					<span id="aif-stats" class="aif-stats"></span>
				</div>

				<p class="description">
					<strong>Typographie seule</strong> : pour les textes d'auteurs (pas de nettoyage LLM).<br>
					Le mode IA necessite une cle API dans <a href="<?php echo esc_url( admin_url( 'options-general.php?page=ai-formatter-settings' ) ); ?>">Reglages &rarr; AI Formatter</a>.<br>
					<kbd>Ctrl</kbd>+<kbd>Entree</kbd> pour lancer le formatage.
				</p>
			</div>

			<!-- Right: output -->
			<div class="aif-panel">
				<h2>Resultat (HTML)</h2>
				<textarea id="aif-output" rows="10" readonly></textarea>

				<h2>Previsualisation</h2>
				<div id="aif-preview" class="aif-preview aif-css-clean"></div>
			</div>
		</div>

		<!-- Translation section -->
		<div class="aif-panel aif-translation-panel" style="margin-top: 24px;">
			<h2>Traduction</h2>
			<p class="description" style="margin-bottom: 12px;">Traduisez le resultat formate vers une langue europeenne. Necessite une cle API.</p>

			<div class="aif-row">
				<label for="aif-source-lang">Depuis</label>
				<select id="aif-source-lang">
					<option value="fr" selected>Francais</option>
					<?php foreach ( aif_translation_languages() as $code => $name ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="aif-row">
				<label for="aif-target-lang">Vers</label>
				<select id="aif-target-lang">
					<?php foreach ( aif_translation_languages() as $code => $name ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="aif-actions">
				<button class="button button-primary" id="aif-translate" disabled>Traduire</button>
				<button class="button" id="aif-copy-translation" disabled>Copier traduction</button>
			</div>

			<div class="aif-grid" style="margin-top: 16px;">
				<div>
					<h3>Traduction (HTML)</h3>
					<textarea id="aif-translation-output" rows="8" readonly></textarea>
				</div>
				<div>
					<h3>Previsualisation</h3>
					<div id="aif-translation-preview" class="aif-preview aif-css-clean"></div>
				</div>
			</div>
		</div>

		<div class="aif-footer">
			AI Formatter v<?php echo esc_html( AIF_VERSION ); ?> &mdash; par <a href="https://github.com/peopleofverso2" target="_blank" rel="noopener">Peopleofverso</a>
		</div>
	</div>
	<?php
}

/* ================================================================ */
/*  REST API : /wp-json/ai-formatter/v1/format                      */
/* ================================================================ */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ai-formatter/v1', '/format', [
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'callback'            => 'aif_rest_format',
	] );
} );

/**
 * Handler REST : pipeline de traitement.
 *
 * Modes :
 * - clean_only   : nettoyage LLM + typographie + conversion HTML
 * - typo_only    : typographie seule + conversion HTML (pas de nettoyage LLM)
 * - ai_proofread : clean_only + correction IA
 */
function aif_rest_format( WP_REST_Request $req ): WP_REST_Response {
	$params = $req->get_json_params();
	$text   = isset( $params['text'] ) ? wp_unslash( $params['text'] ) : '';
	$mode   = $params['mode'] ?? 'clean_only';
	$style  = $params['style'] ?? 'neutre';
	$css    = $params['css'] ?? 'clean';

	if ( ! $text ) {
		return new WP_REST_Response( [ 'error' => 'Texte vide.' ], 400 );
	}

	$clean = $text;

	/* Layer A : nettoyage LLM (sauf mode typo_only) */
	if ( $mode !== 'typo_only' ) {
		$clean = aif_clean_llm_ticks( $clean );
	}

	/* Layer B : typographie FR (toujours) */
	$clean = aif_apply_french_typography( $clean );

	/* Conversion texte -> HTML */
	$html = aif_markdownish_to_html( $clean );

	/* Layer C : IA (optionnelle) */
	if ( $mode === 'ai_proofread' ) {
		$result = aif_ai_proofread( $html, $style );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				[ 'error' => $result->get_error_message() ],
				500
			);
		}
		$html = $result;
	}

	/* Stats */
	$stats = aif_count_stats( $html );

	return new WP_REST_Response( [
		'html'  => $html,
		'css'   => sanitize_key( $css ),
		'stats' => $stats,
	], 200 );
}
