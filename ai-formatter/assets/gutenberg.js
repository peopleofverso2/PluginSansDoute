/**
 * AI Formatter - Gutenberg Sidebar Plugin
 *
 * Ajoute un panneau "AI Formatter" dans la sidebar de l'editeur
 * pour formater le contenu du post courant sans quitter l'editeur.
 *
 * @author Peopleofverso
 */
(function (wp) {
	'use strict';

	var el              = wp.element.createElement;
	var Fragment        = wp.element.Fragment;
	var useState        = wp.element.useState;
	var PluginSidebar   = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
	var PanelBody       = wp.components.PanelBody;
	var SelectControl   = wp.components.SelectControl;
	var Button          = wp.components.Button;
	var Spinner         = wp.components.Spinner;
	var Notice          = wp.components.Notice;
	var useSelect       = wp.data.useSelect;
	var useDispatch      = wp.data.useDispatch;
	var registerPlugin  = wp.plugins.registerPlugin;

	function AIFormatterSidebar() {
		var _useState1 = useState('clean_only');
		var mode = _useState1[0];
		var setMode = _useState1[1];

		var _useState2 = useState('neutre');
		var style = _useState2[0];
		var setStyle = _useState2[1];

		var _useState3 = useState('clean');
		var cssPreset = _useState3[0];
		var setCssPreset = _useState3[1];

		var _useState4 = useState(false);
		var loading = _useState4[0];
		var setLoading = _useState4[1];

		var _useState5 = useState(null);
		var notice = _useState5[0];
		var setNotice = _useState5[1];

		var _useState6 = useState('en');
		var targetLang = _useState6[0];
		var setTargetLang = _useState6[1];

		var _useState7 = useState(false);
		var translating = _useState7[0];
		var setTranslating = _useState7[1];

		var content = useSelect(function (select) {
			return select('core/editor').getEditedPostContent();
		}, []);

		var editPost = useDispatch('core/editor').editPost;

		function handleTranslate() {
			if (!content || !content.trim()) {
				setNotice({ type: 'error', msg: 'Le contenu de l\'article est vide.' });
				return;
			}

			setTranslating(true);
			setNotice(null);

			wp.apiFetch({
				url: AIF_GUTENBERG.restUrl.replace('/format', '/translate'),
				method: 'POST',
				headers: { 'X-WP-Nonce': AIF_GUTENBERG.nonce },
				data: {
					html: content,
					source_lang: 'fr',
					target_lang: targetLang,
				},
			}).then(function (res) {
				setTranslating(false);
				if (res.error) {
					setNotice({ type: 'error', msg: res.error });
					return;
				}

				editPost({ content: res.html });
				editPost({ meta: { _aif_translation_lang: targetLang } });
				setNotice({ type: 'success', msg: 'Traduit vers ' + res.target_name + ' !' });
			}).catch(function (err) {
				setTranslating(false);
				setNotice({ type: 'error', msg: err.message || 'Erreur de traduction.' });
			});
		}

		function handleFormat() {
			if (!content || !content.trim()) {
				setNotice({ type: 'error', msg: 'Le contenu de l\'article est vide.' });
				return;
			}

			setLoading(true);
			setNotice(null);

			// Strip HTML to get raw text for processing
			var tmp = document.createElement('div');
			tmp.innerHTML = content;
			var rawText = tmp.textContent || tmp.innerText || '';

			wp.apiFetch({
				url: AIF_GUTENBERG.restUrl,
				method: 'POST',
				headers: { 'X-WP-Nonce': AIF_GUTENBERG.nonce },
				data: {
					text: rawText,
					mode: mode,
					style: style,
					css: cssPreset,
				},
			}).then(function (res) {
				setLoading(false);
				if (res.error) {
					setNotice({ type: 'error', msg: res.error });
					return;
				}

				// Replace post content with formatted HTML
				editPost({ content: res.html });

				// Save CSS preset as post meta
				editPost({ meta: { _aif_css_preset: cssPreset } });

				var statsMsg = '';
				if (res.stats) {
					statsMsg = ' (' + res.stats.words + ' mots)';
				}
				setNotice({ type: 'success', msg: 'Contenu formate avec succes !' + statsMsg });
			}).catch(function (err) {
				setLoading(false);
				setNotice({ type: 'error', msg: err.message || 'Erreur inconnue.' });
			});
		}

		return el(Fragment, null,
			el(PluginSidebarMoreMenuItem, { target: 'aif-sidebar' }, 'AI Formatter'),
			el(PluginSidebar, {
				name: 'aif-sidebar',
				title: 'AI Formatter',
				icon: 'editor-paste-text',
			},
				el(PanelBody, { title: 'Formatage', initialOpen: true },
					notice && el(Notice, {
						status: notice.type === 'error' ? 'error' : 'success',
						isDismissible: true,
						onRemove: function () { setNotice(null); },
					}, notice.msg),

					el(SelectControl, {
						label: 'Mode',
						value: mode,
						options: [
							{ label: 'Nettoyage + typographie', value: 'clean_only' },
							{ label: 'IA : correction', value: 'ai_proofread' },
						],
						onChange: setMode,
					}),

					mode === 'ai_proofread' && el(SelectControl, {
						label: 'Style',
						value: style,
						options: [
							{ label: 'Neutre', value: 'neutre' },
							{ label: 'Journalistique', value: 'journalistique' },
							{ label: 'Gonzo-pro', value: 'gonzo' },
							{ label: 'Corporate', value: 'corporate' },
						],
						onChange: setStyle,
					}),

					el(SelectControl, {
						label: 'Preset CSS',
						value: cssPreset,
						options: [
							{ label: 'Clean', value: 'clean' },
							{ label: 'SansDoute', value: 'sansdoute' },
							{ label: 'Doc technique', value: 'tech' },
							{ label: 'Apple clean', value: 'apple' },
						],
						onChange: setCssPreset,
					}),

					el(Button, {
						variant: 'primary',
						onClick: handleFormat,
						disabled: loading,
						style: { marginTop: '12px', width: '100%', justifyContent: 'center' },
					}, loading ? el(Spinner, null) : 'Formater le contenu'),

					el('p', {
						className: 'description',
						style: { marginTop: '12px', fontSize: '11px', color: '#787c82' },
					}, 'Le texte sera corrige sans modifier le style de l\'auteur.')
				),

				/* ---- Translation panel ---- */
				el(PanelBody, { title: 'Traduction', initialOpen: false },
					el(SelectControl, {
						label: 'Langue cible',
						value: targetLang,
						options: [
							{ label: 'English', value: 'en' },
							{ label: 'Espanol', value: 'es' },
							{ label: 'Deutsch', value: 'de' },
							{ label: 'Italiano', value: 'it' },
							{ label: 'Portugues', value: 'pt' },
							{ label: 'Nederlands', value: 'nl' },
							{ label: 'Polski', value: 'pl' },
							{ label: 'Romana', value: 'ro' },
							{ label: 'Svenska', value: 'sv' },
						],
						onChange: setTargetLang,
					}),

					el(Button, {
						variant: 'secondary',
						onClick: handleTranslate,
						disabled: translating,
						style: { marginTop: '8px', width: '100%', justifyContent: 'center' },
					}, translating ? el(Spinner, null) : 'Traduire l\'article')
				)
			)
		);
	}

	registerPlugin('ai-formatter', {
		render: AIFormatterSidebar,
	});

})(window.wp);
