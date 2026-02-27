<?php
/**
 * Plugin Name: AI Formatter (Clean Copy)
 * Plugin URI:  https://github.com/peopleofverso2/PluginSansDoute
 * Description: Colle/importe un texte, nettoie les tics LLM, corrige orthographe + typographie FR, et applique un style CSS preset. Respecte la voix des auteurs. Inclut SansDoute Influence pour le marketing d'influence par le contenu.
 * Version:     1.2.0
 * Author:      Peopleofverso
 * Author URI:  https://github.com/peopleofverso2
 * Text Domain: ai-formatter
 * Requires PHP: 7.4
 * License:     MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIF_VERSION', '1.2.0' );
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
require_once AIF_PATH . 'includes/tone-detector.php';

/* SansDoute Influence — marketing d'influence par le contenu */
require_once AIF_PATH . 'includes/influence/loader.php';

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

	/* Mammoth.js pour import DOCX (cote client) */
	wp_enqueue_script( 'mammoth', 'https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js', [], '1.6.0', true );

	wp_enqueue_style( 'aif-admin', AIF_URL . 'assets/admin.css', [], AIF_VERSION );
	wp_enqueue_script( 'aif-admin', AIF_URL . 'assets/admin.js', [ 'wp-api-fetch', 'mammoth' ], AIF_VERSION, true );
	wp_localize_script( 'aif-admin', 'AIF', [
		'restUrl'       => esc_url_raw( rest_url( 'ai-formatter/v1/format' ) ),
		'translateUrl'  => esc_url_raw( rest_url( 'ai-formatter/v1/translate' ) ),
		'toneUrl'       => esc_url_raw( rest_url( 'ai-formatter/v1/detect-tone' ) ),
		'presetSaveUrl' => esc_url_raw( rest_url( 'ai-formatter/v1/preset' ) ),
		'nonce'         => wp_create_nonce( 'wp_rest' ),
		'presets'       => array_keys( aif_css_presets() ),
		'presetLabels'  => aif_css_presets(),
		'customPresets' => aif_get_custom_presets(),
		'languages'     => aif_translation_languages(),
	] );
} );

/* ================================================================ */
/*  CSS presets registry (built-in + custom)                        */
/* ================================================================ */
function aif_css_presets(): array {
	$built_in = [
		'clean'     => 'Clean',
		'sansdoute' => 'SansDoute',
		'tech'      => 'Doc technique',
		'apple'     => 'Apple clean',
	];

	$custom = aif_get_custom_presets();
	foreach ( $custom as $preset ) {
		$slug = sanitize_key( $preset['name'] );
		if ( $slug && ! isset( $built_in[ $slug ] ) ) {
			$built_in[ $slug ] = $preset['name'];
		}
	}

	return $built_in;
}

/**
 * Recupere les presets CSS personnalises.
 *
 * @return array Liste de presets [{name, css}]
 */
function aif_get_custom_presets(): array {
	$raw = get_option( 'aif_custom_presets', '[]' );
	$decoded = json_decode( $raw, true );
	return is_array( $decoded ) ? $decoded : [];
}

/* ================================================================ */
/*  Admin page render                                               */
/* ================================================================ */
function aif_render_admin_page() {
	$presets = aif_css_presets();
	?>
	<div class="wrap aif-wrap">
		<h1>AI Formatter <span class="aif-version">v<?php echo esc_html( AIF_VERSION ); ?></span></h1>

		<div class="aif-grid">
			<!-- Left: input -->
			<div class="aif-panel">
				<h2>Texte source</h2>

				<!-- Import DOCX -->
				<div class="aif-import-row">
					<label class="button aif-import-btn" for="aif-docx-input">
						Importer .docx
					</label>
					<input type="file" id="aif-docx-input" accept=".docx" style="display:none;" />
					<span id="aif-import-status" class="aif-import-status"></span>
				</div>

				<textarea id="aif-input" rows="18" placeholder="Colle ici ton texte brut (ou glisse un fichier .txt / .md / .docx)..."></textarea>

				<!-- Tone detector -->
				<div id="aif-tone-bar" class="aif-tone-bar" style="display:none;">
					<span class="aif-tone-label">Ton detecte :</span>
					<span id="aif-tone-badge" class="aif-tone-badge"></span>
					<span id="aif-tone-confidence" class="aif-tone-confidence"></span>
				</div>

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
					<button class="button aif-btn-small" id="aif-edit-preset" title="Editer / creer un preset CSS">+</button>
				</div>

				<!-- Mode strict -->
				<div class="aif-row">
					<label for="aif-strict">
						<input type="checkbox" id="aif-strict" />
						Mode strict (tout en paragraphes, pas de listes)
					</label>
				</div>

				<div class="aif-actions">
					<button class="button button-primary" id="aif-run">Nettoyer &amp; Formater</button>
					<button class="button" id="aif-copy" disabled>Copier HTML</button>
					<button class="button" id="aif-export-md" disabled>Export .md</button>
					<button class="button" id="aif-export-pdf" disabled>Export PDF</button>
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

				<!-- History -->
				<div class="aif-history-section">
					<h3>
						Historique
						<button class="button aif-btn-small" id="aif-history-toggle">Afficher</button>
						<button class="button aif-btn-small" id="aif-history-clear" style="display:none;">Vider</button>
					</h3>
					<div id="aif-history-list" class="aif-history-list" style="display:none;"></div>
					<div id="aif-diff-view" class="aif-diff-view" style="display:none;"></div>
				</div>
			</div>
		</div>

		<!-- CSS Preset Editor (modal) -->
		<div id="aif-preset-modal" class="aif-modal" style="display:none;">
			<div class="aif-modal-content">
				<div class="aif-modal-header">
					<h2>Editeur de preset CSS</h2>
					<button class="button aif-modal-close" id="aif-preset-modal-close">&times;</button>
				</div>
				<div class="aif-modal-body">
					<div class="aif-row">
						<label for="aif-preset-name">Nom du preset</label>
						<input type="text" id="aif-preset-name" placeholder="Mon preset" class="regular-text" />
					</div>
					<label>CSS (applique a <code>.aif-preview</code>)</label>
					<textarea id="aif-preset-css" rows="12" class="code" placeholder=".aif-css-monpreset {
  font-family: Georgia, serif;
  font-size: 16px;
  line-height: 1.7;
  color: #333;
}"></textarea>
					<div class="aif-row" style="margin-top: 12px;">
						<div id="aif-preset-preview" class="aif-preview" style="min-height: 80px; flex: 1;">
							<p>Previsualisation du preset en temps reel.</p>
							<p><strong>Gras</strong>, <em>italique</em>, et <a href="#">lien</a>.</p>
						</div>
					</div>
				</div>
				<div class="aif-modal-footer">
					<button class="button button-primary" id="aif-preset-save">Sauvegarder</button>
					<button class="button" id="aif-preset-cancel">Annuler</button>
				</div>
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

	/* Tone detection */
	register_rest_route( 'ai-formatter/v1', '/detect-tone', [
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'callback'            => 'aif_rest_detect_tone',
	] );

	/* Custom preset save */
	register_rest_route( 'ai-formatter/v1', '/preset', [
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
		'callback'            => 'aif_rest_save_preset',
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
	$strict = ! empty( $params['strict'] );

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

	/* Conversion texte -> HTML (mode strict optionnel) */
	$html = aif_markdownish_to_html( $clean, $strict );

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

/**
 * Handler REST : detection de ton.
 */
function aif_rest_detect_tone( WP_REST_Request $req ): WP_REST_Response {
	$params = $req->get_json_params();
	$text   = isset( $params['text'] ) ? wp_unslash( $params['text'] ) : '';

	if ( ! $text ) {
		return new WP_REST_Response( [ 'error' => 'Texte vide.' ], 400 );
	}

	$result = aif_detect_tone( $text );

	return new WP_REST_Response( $result, 200 );
}

/**
 * Handler REST : sauvegarde d'un preset CSS custom.
 */
function aif_rest_save_preset( WP_REST_Request $req ): WP_REST_Response {
	$params = $req->get_json_params();
	$name   = sanitize_text_field( $params['name'] ?? '' );
	$css    = wp_strip_all_tags( $params['css'] ?? '' );

	if ( ! $name ) {
		return new WP_REST_Response( [ 'error' => 'Nom du preset requis.' ], 400 );
	}

	$presets = aif_get_custom_presets();
	$slug    = sanitize_key( $name );

	/* Mise a jour ou ajout */
	$found = false;
	foreach ( $presets as &$p ) {
		if ( sanitize_key( $p['name'] ) === $slug ) {
			$p['css'] = $css;
			$found    = true;
			break;
		}
	}
	unset( $p );

	if ( ! $found ) {
		$presets[] = [ 'name' => $name, 'css' => $css ];
	}

	update_option( 'aif_custom_presets', wp_json_encode( $presets ) );

	return new WP_REST_Response( [
		'success' => true,
		'slug'    => $slug,
		'presets' => $presets,
	], 200 );
}
