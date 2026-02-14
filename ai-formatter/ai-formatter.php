<?php
/**
 * Plugin Name: AI Formatter (Clean Copy)
 * Plugin URI:  https://github.com/peopleofverso2/PluginSansDoute
 * Description: Colle/importe un texte, nettoie les tics LLM, corrige orthographe + typographie FR, et applique un style CSS preset.
 * Version:     0.1.0
 * Author:      SansDoute
 * Text Domain: ai-formatter
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIF_VERSION', '0.1.0' );
define( 'AIF_PATH', plugin_dir_path( __FILE__ ) );
define( 'AIF_URL', plugin_dir_url( __FILE__ ) );

/* ------------------------------------------------------------------ */
/*  Includes                                                          */
/* ------------------------------------------------------------------ */
require_once AIF_PATH . 'includes/clean.php';
require_once AIF_PATH . 'includes/typography.php';
require_once AIF_PATH . 'includes/html.php';
require_once AIF_PATH . 'includes/ai-provider.php';
require_once AIF_PATH . 'includes/settings.php';

/* ------------------------------------------------------------------ */
/*  Admin page : Outils -> AI Formatter                               */
/* ------------------------------------------------------------------ */
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

/* ------------------------------------------------------------------ */
/*  Enqueue assets (admin page only)                                  */
/* ------------------------------------------------------------------ */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( $hook !== 'tools_page_ai-formatter' ) {
		return;
	}

	wp_enqueue_style( 'aif-admin', AIF_URL . 'assets/admin.css', [], AIF_VERSION );
	wp_enqueue_script( 'aif-admin', AIF_URL . 'assets/admin.js', [ 'wp-api-fetch' ], AIF_VERSION, true );
	wp_localize_script( 'aif-admin', 'AIF', [
		'restUrl' => esc_url_raw( rest_url( 'ai-formatter/v1/format' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
		'presets' => array_keys( aif_css_presets() ),
	] );
} );

/* ------------------------------------------------------------------ */
/*  CSS presets registry                                              */
/* ------------------------------------------------------------------ */
function aif_css_presets(): array {
	return [
		'clean'     => 'Clean',
		'sansdoute' => 'SansDoute',
		'tech'      => 'Doc technique',
		'apple'     => 'Apple clean',
	];
}

/* ------------------------------------------------------------------ */
/*  Admin page render                                                 */
/* ------------------------------------------------------------------ */
function aif_render_admin_page() {
	$presets = aif_css_presets();
	?>
	<div class="wrap aif-wrap">
		<h1>AI Formatter</h1>

		<div class="aif-grid">
			<!-- Left: input -->
			<div class="aif-panel">
				<h2>Texte source</h2>
				<textarea id="aif-input" rows="18" placeholder="Colle ici ton texte brut (souvent sorti d'un LLM)..."></textarea>

				<div class="aif-row">
					<label for="aif-mode">Mode</label>
					<select id="aif-mode">
						<option value="clean_only">Nettoyage + typographie (sans IA)</option>
						<option value="ai_proofread">IA : correction orthographe + style</option>
					</select>
				</div>

				<div class="aif-row aif-style-row" style="display:none;">
					<label for="aif-style">Style IA</label>
					<select id="aif-style">
						<option value="neutre">Neutre</option>
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
				</div>

				<p class="description">Le mode IA necessite une cle API configuree dans <a href="<?php echo esc_url( admin_url( 'options-general.php?page=ai-formatter-settings' ) ); ?>">Reglages &rarr; AI Formatter</a>.</p>
			</div>

			<!-- Right: output -->
			<div class="aif-panel">
				<h2>Resultat (HTML)</h2>
				<textarea id="aif-output" rows="10" readonly></textarea>

				<h2>Previsualisation</h2>
				<div id="aif-preview" class="aif-preview aif-css-clean"></div>
			</div>
		</div>
	</div>
	<?php
}

/* ------------------------------------------------------------------ */
/*  REST API : /wp-json/ai-formatter/v1/format                        */
/* ------------------------------------------------------------------ */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ai-formatter/v1', '/format', [
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'callback'            => 'aif_rest_format',
	] );
} );

function aif_rest_format( WP_REST_Request $req ): WP_REST_Response {
	$params = $req->get_json_params();
	$text   = isset( $params['text'] ) ? wp_unslash( $params['text'] ) : '';
	$mode   = $params['mode'] ?? 'clean_only';
	$style  = $params['style'] ?? 'neutre';
	$css    = $params['css'] ?? 'clean';

	if ( ! $text ) {
		return new WP_REST_Response( [ 'error' => 'Texte vide.' ], 400 );
	}

	/* Layer A : nettoyage LLM (sans IA) */
	$clean = aif_clean_llm_ticks( $text );

	/* Layer B : typographie FR */
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

	return new WP_REST_Response( [
		'html' => $html,
		'css'  => sanitize_key( $css ),
	], 200 );
}
