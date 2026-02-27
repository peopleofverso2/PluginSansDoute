<?php
/**
 * SansDoute Influence — Module loader.
 *
 * Registers Custom Post Types, admin pages, and enqueues assets.
 *
 * @author Peopleofverso
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AIF_INFLUENCE_VERSION', '0.1.0' );

/* ================================================================ */
/*  Includes                                                         */
/* ================================================================ */
require_once __DIR__ . '/brief.php';
require_once __DIR__ . '/compliance.php';
require_once __DIR__ . '/scoring.php';
require_once __DIR__ . '/rest-api.php';

/* ================================================================ */
/*  Custom Post Types                                                */
/* ================================================================ */
add_action( 'init', function () {

	/* ---- Brief (campagne) ---- */
	register_post_type( 'aif_brief', [
		'labels' => [
			'name'               => 'Briefs',
			'singular_name'      => 'Brief',
			'add_new'            => 'Nouveau brief',
			'add_new_item'       => 'Creer un brief',
			'edit_item'          => 'Modifier le brief',
			'view_item'          => 'Voir le brief',
			'search_items'       => 'Chercher un brief',
			'not_found'          => 'Aucun brief trouve.',
			'not_found_in_trash' => 'Aucun brief dans la corbeille.',
		],
		'public'       => false,
		'show_ui'      => false, // We use our own admin pages
		'show_in_rest' => false, // We use our own REST routes
		'supports'     => [ 'title', 'editor', 'author' ],
		'capability_type' => 'post',
		'map_meta_cap'    => true,
	] );

	/* ---- Collab (contenu du createur) ---- */
	register_post_type( 'aif_collab', [
		'labels' => [
			'name'               => 'Contenus',
			'singular_name'      => 'Contenu',
			'add_new'            => 'Nouveau contenu',
			'add_new_item'       => 'Creer un contenu',
			'edit_item'          => 'Modifier le contenu',
			'view_item'          => 'Voir le contenu',
			'search_items'       => 'Chercher un contenu',
			'not_found'          => 'Aucun contenu trouve.',
			'not_found_in_trash' => 'Aucun contenu dans la corbeille.',
		],
		'public'       => false,
		'show_ui'      => false,
		'show_in_rest' => false,
		'supports'     => [ 'title', 'editor', 'author', 'comments' ],
		'capability_type' => 'post',
		'map_meta_cap'    => true,
	] );
} );

/* ================================================================ */
/*  Admin menus                                                      */
/* ================================================================ */
add_action( 'admin_menu', function () {

	/* Top-level menu */
	add_menu_page(
		'SansDoute Influence',
		'Influence',
		'edit_posts',
		'aif-influence',
		'aif_influence_render_dashboard',
		'dashicons-megaphone',
		30
	);

	/* Sub-pages */
	add_submenu_page(
		'aif-influence',
		'Dashboard',
		'Dashboard',
		'edit_posts',
		'aif-influence',
		'aif_influence_render_dashboard'
	);

	add_submenu_page(
		'aif-influence',
		'Nouveau brief',
		'Nouveau brief',
		'edit_posts',
		'aif-influence-brief',
		'aif_influence_render_brief_editor'
	);

	add_submenu_page(
		'aif-influence',
		'Workspace',
		'Workspace',
		'edit_posts',
		'aif-influence-workspace',
		'aif_influence_render_workspace'
	);
} );

/* ================================================================ */
/*  Enqueue assets                                                   */
/* ================================================================ */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$pages = [
		'toplevel_page_aif-influence',
		'influence_page_aif-influence-brief',
		'influence_page_aif-influence-workspace',
	];

	if ( ! in_array( $hook, $pages, true ) ) {
		return;
	}

	wp_enqueue_style(
		'aif-influence',
		AIF_URL . 'assets/influence.css',
		[],
		AIF_INFLUENCE_VERSION
	);

	wp_enqueue_script(
		'aif-influence',
		AIF_URL . 'assets/influence.js',
		[ 'wp-api-fetch' ],
		AIF_INFLUENCE_VERSION,
		true
	);

	wp_localize_script( 'aif-influence', 'AIF_INF', [
		'restBase'  => esc_url_raw( rest_url( 'ai-formatter/v1/influence/' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'userId'    => get_current_user_id(),
		'briefPage' => admin_url( 'admin.php?page=aif-influence-brief' ),
		'workPage'  => admin_url( 'admin.php?page=aif-influence-workspace' ),
		'dashPage'  => admin_url( 'admin.php?page=aif-influence' ),
	] );
} );

/* ================================================================ */
/*  Admin page renderers                                             */
/* ================================================================ */

/**
 * Dashboard : liste des briefs + stats.
 */
function aif_influence_render_dashboard() {
	?>
	<div class="wrap aif-inf-wrap">
		<h1>SansDoute Influence <span class="aif-inf-version">v<?php echo esc_html( AIF_INFLUENCE_VERSION ); ?></span></h1>
		<p class="aif-inf-tagline">Le marketing d'influence par le contenu. Pas de bullshit.</p>

		<div class="aif-inf-actions-bar">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aif-influence-brief' ) ); ?>" class="button button-primary">
				+ Nouveau brief
			</a>
		</div>

		<!-- Stats cards -->
		<div class="aif-inf-stats-grid" id="aif-inf-stats">
			<div class="aif-inf-stat-card">
				<span class="aif-inf-stat-number" id="aif-inf-stat-briefs">-</span>
				<span class="aif-inf-stat-label">Briefs actifs</span>
			</div>
			<div class="aif-inf-stat-card">
				<span class="aif-inf-stat-number" id="aif-inf-stat-collabs">-</span>
				<span class="aif-inf-stat-label">Contenus</span>
			</div>
			<div class="aif-inf-stat-card">
				<span class="aif-inf-stat-number" id="aif-inf-stat-pending">-</span>
				<span class="aif-inf-stat-label">En attente</span>
			</div>
			<div class="aif-inf-stat-card">
				<span class="aif-inf-stat-number" id="aif-inf-stat-compliance">-</span>
				<span class="aif-inf-stat-label">Compliance moy.</span>
			</div>
		</div>

		<!-- Briefs list -->
		<div class="aif-inf-section">
			<h2>Campagnes</h2>
			<table class="widefat aif-inf-table" id="aif-inf-briefs-table">
				<thead>
					<tr>
						<th>Campagne</th>
						<th>Marque</th>
						<th>Ton</th>
						<th>Deadline</th>
						<th>Contenus</th>
						<th>Statut</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody id="aif-inf-briefs-body">
					<tr><td colspan="7" class="aif-inf-loading">Chargement...</td></tr>
				</tbody>
			</table>
		</div>

		<!-- Recent collabs -->
		<div class="aif-inf-section">
			<h2>Derniers contenus</h2>
			<table class="widefat aif-inf-table" id="aif-inf-collabs-table">
				<thead>
					<tr>
						<th>Titre</th>
						<th>Campagne</th>
						<th>Createur</th>
						<th>Compliance</th>
						<th>Qualite</th>
						<th>Statut</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody id="aif-inf-collabs-body">
					<tr><td colspan="7" class="aif-inf-loading">Chargement...</td></tr>
				</tbody>
			</table>
		</div>

		<div class="aif-inf-footer">
			SansDoute Influence v<?php echo esc_html( AIF_INFLUENCE_VERSION ); ?> &mdash; Module pour <a href="<?php echo esc_url( admin_url( 'tools.php?page=ai-formatter' ) ); ?>">AI Formatter</a>
		</div>
	</div>
	<?php
}

/**
 * Brief editor : creer / modifier un brief.
 */
function aif_influence_render_brief_editor() {
	$brief_id = isset( $_GET['brief_id'] ) ? absint( $_GET['brief_id'] ) : 0;
	$is_edit  = $brief_id > 0;
	?>
	<div class="wrap aif-inf-wrap">
		<h1><?php echo $is_edit ? 'Modifier le brief' : 'Nouveau brief'; ?></h1>

		<form id="aif-inf-brief-form" class="aif-inf-form">
			<input type="hidden" id="aif-inf-brief-id" value="<?php echo esc_attr( $brief_id ); ?>" />

			<div class="aif-inf-form-grid">
				<!-- Left column -->
				<div class="aif-inf-form-main">
					<div class="aif-inf-field">
						<label for="aif-inf-campaign-name">Nom de la campagne *</label>
						<input type="text" id="aif-inf-campaign-name" required placeholder="Ex : Lancement collection ete 2026" />
					</div>

					<div class="aif-inf-field">
						<label for="aif-inf-brand">Marque *</label>
						<input type="text" id="aif-inf-brand" required placeholder="Ex : Maison Duval" />
					</div>

					<div class="aif-inf-field">
						<label for="aif-inf-description">Description du brief</label>
						<textarea id="aif-inf-description" rows="6" placeholder="Decrivez le contexte, les objectifs, le message cle..."></textarea>
					</div>

					<div class="aif-inf-field">
						<label for="aif-inf-keywords-required">Mots-cles obligatoires</label>
						<input type="text" id="aif-inf-keywords-required" placeholder="Separes par des virgules : eco-responsable, made in France, lin" />
						<p class="description">Le contenu devra contenir ces termes.</p>
					</div>

					<div class="aif-inf-field">
						<label for="aif-inf-keywords-forbidden">Mots-cles interdits</label>
						<input type="text" id="aif-inf-keywords-forbidden" placeholder="Separes par des virgules : cheap, pas cher, concurrent X" />
						<p class="description">Le contenu ne devra PAS contenir ces termes.</p>
					</div>

					<div class="aif-inf-field">
						<label for="aif-inf-links">Liens obligatoires</label>
						<textarea id="aif-inf-links" rows="3" placeholder="Un lien par ligne :&#10;https://maison-duval.fr/collection-ete?utm_source=influence&#10;https://maison-duval.fr/engagements"></textarea>
						<p class="description">URLs que le contenu devra inclure (avec UTM).</p>
					</div>

					<div class="aif-inf-field">
						<label for="aif-inf-legal">Mentions legales requises</label>
						<textarea id="aif-inf-legal" rows="2" placeholder="Un par ligne :&#10;En partenariat avec Maison Duval&#10;#pub"></textarea>
						<p class="description">Mentions ARPP obligatoires (loi francaise).</p>
					</div>
				</div>

				<!-- Right column -->
				<div class="aif-inf-form-sidebar">
					<div class="aif-inf-sidebar-card">
						<h3>Parametres</h3>

						<div class="aif-inf-field">
							<label for="aif-inf-tone">Ton attendu</label>
							<select id="aif-inf-tone">
								<option value="neutre">Neutre</option>
								<option value="journalistique">Journalistique</option>
								<option value="gonzo">Gonzo (authentique, personnel)</option>
								<option value="corporate">Corporate</option>
								<option value="informel">Informel</option>
							</select>
						</div>

						<div class="aif-inf-field">
							<label for="aif-inf-deadline">Deadline</label>
							<input type="date" id="aif-inf-deadline" />
						</div>

						<div class="aif-inf-field">
							<label for="aif-inf-publish-date">Date de publication</label>
							<input type="date" id="aif-inf-publish-date" />
						</div>

						<div class="aif-inf-field">
							<label for="aif-inf-budget">Budget par contenu</label>
							<input type="text" id="aif-inf-budget" placeholder="Ex : 500 EUR" />
						</div>
					</div>

					<div class="aif-inf-sidebar-card">
						<h3>Resume</h3>
						<div id="aif-inf-brief-summary" class="aif-inf-brief-summary">
							<p class="aif-inf-muted">Remplissez le formulaire pour voir le resume.</p>
						</div>
					</div>

					<div class="aif-inf-sidebar-card">
						<button type="submit" class="button button-primary button-hero" id="aif-inf-save-brief" style="width:100%;">
							<?php echo $is_edit ? 'Mettre a jour le brief' : 'Creer le brief'; ?>
						</button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=aif-influence' ) ); ?>" class="button" style="width:100%;margin-top:8px;text-align:center;">
							Retour au dashboard
						</a>
					</div>
				</div>
			</div>
		</form>
	</div>
	<?php
}

/**
 * Workspace : espace de redaction du createur.
 */
function aif_influence_render_workspace() {
	$brief_id  = isset( $_GET['brief_id'] ) ? absint( $_GET['brief_id'] ) : 0;
	$collab_id = isset( $_GET['collab_id'] ) ? absint( $_GET['collab_id'] ) : 0;
	?>
	<div class="wrap aif-inf-wrap">
		<h1>Workspace createur</h1>

		<div class="aif-inf-workspace-grid">
			<!-- Left: Editor -->
			<div class="aif-inf-workspace-editor">
				<input type="hidden" id="aif-inf-ws-brief-id" value="<?php echo esc_attr( $brief_id ); ?>" />
				<input type="hidden" id="aif-inf-ws-collab-id" value="<?php echo esc_attr( $collab_id ); ?>" />

				<div class="aif-inf-field">
					<label for="aif-inf-ws-title">Titre du contenu</label>
					<input type="text" id="aif-inf-ws-title" placeholder="Titre de votre article..." />
				</div>

				<div class="aif-inf-field">
					<textarea id="aif-inf-ws-content" rows="24" placeholder="Ecrivez votre contenu ici...&#10;&#10;Le brief s'affiche a droite. Les scores de compliance et qualite se mettent a jour en temps reel."></textarea>
				</div>

				<div class="aif-inf-ws-actions">
					<button class="button" id="aif-inf-ws-check">Verifier compliance</button>
					<button class="button" id="aif-inf-ws-score">Scorer qualite</button>
					<button class="button" id="aif-inf-ws-format">Formater (AI Formatter)</button>
					<button class="button button-primary" id="aif-inf-ws-save">Sauvegarder brouillon</button>
					<button class="button aif-inf-btn-submit" id="aif-inf-ws-submit" disabled>Soumettre pour review</button>
				</div>
			</div>

			<!-- Right: Brief + Scores -->
			<div class="aif-inf-workspace-sidebar">
				<!-- Brief -->
				<div class="aif-inf-sidebar-card" id="aif-inf-ws-brief-card">
					<h3>Brief</h3>
					<div id="aif-inf-ws-brief-content">
						<p class="aif-inf-muted">Selectionnez un brief pour commencer.</p>

						<?php if ( ! $brief_id ) : ?>
						<div class="aif-inf-field" style="margin-top: 12px;">
							<label for="aif-inf-ws-brief-select">Choisir un brief</label>
							<select id="aif-inf-ws-brief-select">
								<option value="">-- Selectionnez --</option>
							</select>
						</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Compliance score -->
				<div class="aif-inf-sidebar-card">
					<h3>Compliance</h3>
					<div class="aif-inf-score-ring" id="aif-inf-ws-compliance-ring">
						<span class="aif-inf-score-value">-</span>
					</div>
					<div id="aif-inf-ws-compliance-details" class="aif-inf-score-details">
						<p class="aif-inf-muted">Cliquez « Verifier compliance » pour analyser.</p>
					</div>
				</div>

				<!-- Quality score -->
				<div class="aif-inf-sidebar-card">
					<h3>Qualite</h3>
					<div class="aif-inf-score-ring" id="aif-inf-ws-quality-ring">
						<span class="aif-inf-score-value">-</span>
					</div>
					<div id="aif-inf-ws-quality-details" class="aif-inf-score-details">
						<p class="aif-inf-muted">Cliquez « Scorer qualite » pour analyser.</p>
					</div>
				</div>

				<!-- Status -->
				<div class="aif-inf-sidebar-card">
					<h3>Statut</h3>
					<span class="aif-inf-status-badge" id="aif-inf-ws-status">brouillon</span>
				</div>
			</div>
		</div>
	</div>
	<?php
}
