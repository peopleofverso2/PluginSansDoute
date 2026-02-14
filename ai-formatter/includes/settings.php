<?php
/**
 * Page de reglages : Reglages -> AI Formatter.
 *
 * Stocke : provider (openai / anthropic), cle API, modele.
 *
 * @author Peopleofverso
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---- Menu ---- */
add_action( 'admin_menu', function () {
	add_options_page(
		'AI Formatter - Reglages',
		'AI Formatter',
		'manage_options',
		'ai-formatter-settings',
		'aif_render_settings_page'
	);
} );

/* ---- Register settings ---- */
add_action( 'admin_init', function () {
	register_setting( 'aif_settings_group', 'aif_provider', [
		'type'              => 'string',
		'default'           => 'openai',
		'sanitize_callback' => 'sanitize_text_field',
	] );
	register_setting( 'aif_settings_group', 'aif_api_key', [
		'type'              => 'string',
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
	] );
	register_setting( 'aif_settings_group', 'aif_model', [
		'type'              => 'string',
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
	] );

	add_settings_section(
		'aif_main_section',
		'Configuration du provider IA',
		function () {
			echo '<p>Configurez le provider et la cle API pour le mode "IA : correction orthographe + style".</p>';
		},
		'ai-formatter-settings'
	);

	add_settings_field( 'aif_provider', 'Provider', function () {
		$val = get_option( 'aif_provider', 'openai' );
		?>
		<select name="aif_provider" id="aif_provider">
			<option value="openai" <?php selected( $val, 'openai' ); ?>>OpenAI</option>
			<option value="anthropic" <?php selected( $val, 'anthropic' ); ?>>Anthropic</option>
		</select>
		<?php
	}, 'ai-formatter-settings', 'aif_main_section' );

	add_settings_field( 'aif_api_key', 'Cle API', function () {
		$val = get_option( 'aif_api_key', '' );
		?>
		<input type="password" name="aif_api_key" id="aif_api_key" class="regular-text"
			   value="<?php echo esc_attr( $val ); ?>" autocomplete="off" />
		<p class="description">Votre cle API ne sera jamais exposee cote client.</p>
		<?php
	}, 'ai-formatter-settings', 'aif_main_section' );

	add_settings_field( 'aif_model', 'Modele (optionnel)', function () {
		$val = get_option( 'aif_model', '' );
		?>
		<input type="text" name="aif_model" id="aif_model" class="regular-text"
			   value="<?php echo esc_attr( $val ); ?>"
			   placeholder="gpt-4o-mini / claude-sonnet-4-20250514" />
		<p class="description">Laissez vide pour utiliser le modele par defaut du provider.</p>
		<?php
	}, 'ai-formatter-settings', 'aif_main_section' );
} );

/* ---- Render ---- */
function aif_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>AI Formatter - Reglages</h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'aif_settings_group' );
			do_settings_sections( 'ai-formatter-settings' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}
