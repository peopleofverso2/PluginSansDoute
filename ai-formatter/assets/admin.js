/**
 * AI Formatter - Admin JS
 *
 * Interface de l'outil : envoi du texte a la REST API,
 * affichage du resultat, presets CSS, toast notifications,
 * compteur de mots, drag & drop.
 *
 * @author Peopleofverso
 */
(function () {
	'use strict';

	var input    = document.getElementById('aif-input');
	var output   = document.getElementById('aif-output');
	var preview  = document.getElementById('aif-preview');
	var btn      = document.getElementById('aif-run');
	var copyBtn  = document.getElementById('aif-copy');
	var mode     = document.getElementById('aif-mode');
	var style    = document.getElementById('aif-style');
	var css      = document.getElementById('aif-css');
	var statsEl  = document.getElementById('aif-stats');
	var styleRow = document.querySelector('.aif-style-row');

	/* ================================================================ */
	/*  Toast notifications                                             */
	/* ================================================================ */
	function toast(message, type) {
		type = type || 'success';
		var el = document.createElement('div');
		el.className = 'aif-toast aif-toast-' + type;
		el.textContent = message;
		document.body.appendChild(el);

		// Force reflow then animate in
		el.offsetHeight; // eslint-disable-line no-unused-expressions
		el.classList.add('aif-toast-visible');

		setTimeout(function () {
			el.classList.remove('aif-toast-visible');
			setTimeout(function () { el.remove(); }, 300);
		}, 3000);
	}

	/* ================================================================ */
	/*  Word / char counter                                             */
	/* ================================================================ */
	function updateStats() {
		if (!statsEl) return;
		var text = input.value.trim();
		if (!text) {
			statsEl.textContent = '';
			return;
		}
		var words = text.split(/\s+/).filter(function (w) { return w.length > 0; }).length;
		var chars = text.length;
		statsEl.textContent = words + ' mots \u00b7 ' + chars + ' car.';
	}

	if (input) {
		input.addEventListener('input', updateStats);
		updateStats();
	}

	/* ================================================================ */
	/*  Preset CSS                                                      */
	/* ================================================================ */
	function setCssPreset(preset) {
		var presets = (window.AIF && AIF.presets) || ['clean', 'sansdoute', 'tech', 'apple'];
		presets.forEach(function (p) {
			preview.classList.remove('aif-css-' + p);
		});
		preview.classList.add('aif-css-' + preset);
	}

	css.addEventListener('change', function () { setCssPreset(css.value); });
	setCssPreset(css.value);

	/* ================================================================ */
	/*  Toggle style row                                                */
	/* ================================================================ */
	mode.addEventListener('change', function () {
		styleRow.style.display = mode.value === 'ai_proofread' ? 'flex' : 'none';
	});

	/* ================================================================ */
	/*  Main button : format                                            */
	/* ================================================================ */
	btn.addEventListener('click', async function () {
		var text = input.value.trim();
		if (!text) {
			toast('Colle du texte avant de lancer le traitement.', 'error');
			return;
		}

		btn.disabled = true;
		btn.innerHTML = '<span class="aif-spinner"></span> Traitement\u2026';
		copyBtn.disabled = true;
		preview.classList.add('aif-loading');

		try {
			var res = await window.wp.apiFetch({
				url: AIF.restUrl,
				method: 'POST',
				headers: { 'X-WP-Nonce': AIF.nonce },
				data: {
					text:  text,
					mode:  mode.value,
					style: style.value,
					css:   css.value,
				},
			});

			if (res.error) {
				toast('Erreur : ' + res.error, 'error');
				return;
			}

			output.value = res.html;
			preview.innerHTML = res.html;
			setCssPreset(res.css);
			copyBtn.disabled = false;

			/* Stats de sortie */
			var statsParts = [];
			if (res.stats) {
				statsParts.push(res.stats.words + ' mots');
				statsParts.push(res.stats.chars + ' car.');
			}
			toast('Formatage termine !' + (statsParts.length ? ' (' + statsParts.join(', ') + ')' : ''), 'success');

		} catch (e) {
			toast(e && e.message ? e.message : 'Erreur inconnue.', 'error');
		} finally {
			btn.disabled = false;
			btn.innerHTML = 'Nettoyer &amp; Formater';
			preview.classList.remove('aif-loading');
		}
	});

	/* ================================================================ */
	/*  Copy button                                                     */
	/* ================================================================ */
	copyBtn.addEventListener('click', function () {
		if (!output.value) return;

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(output.value).then(function () {
				toast('HTML copie dans le presse-papier.', 'success');
			});
		} else {
			output.select();
			document.execCommand('copy');
			toast('HTML copie dans le presse-papier.', 'success');
		}
	});

	/* ================================================================ */
	/*  Drag & drop                                                     */
	/* ================================================================ */
	input.addEventListener('dragover', function (e) {
		e.preventDefault();
		input.classList.add('aif-dragover');
	});

	input.addEventListener('dragleave', function () {
		input.classList.remove('aif-dragover');
	});

	input.addEventListener('drop', function (e) {
		e.preventDefault();
		input.classList.remove('aif-dragover');

		var files = e.dataTransfer.files;
		if (!files.length) return;

		var file = files[0];
		var name = file.name.toLowerCase();

		if (name.endsWith('.txt') || name.endsWith('.md')) {
			var reader = new FileReader();
			reader.onload = function (ev) {
				input.value = ev.target.result;
				updateStats();
				toast('Fichier "' + file.name + '" charge.', 'success');
			};
			reader.readAsText(file, 'UTF-8');
		} else {
			toast('Format non supporte. Utilisez .txt ou .md.', 'error');
		}
	});

	/* ================================================================ */
	/*  Keyboard shortcut : Ctrl+Enter to format                        */
	/* ================================================================ */
	input.addEventListener('keydown', function (e) {
		if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
			e.preventDefault();
			btn.click();
		}
	});

	/* ================================================================ */
	/*  Translation                                                     */
	/* ================================================================ */
	var translateBtn     = document.getElementById('aif-translate');
	var copyTransBtn     = document.getElementById('aif-copy-translation');
	var translationOut   = document.getElementById('aif-translation-output');
	var translationPrev  = document.getElementById('aif-translation-preview');
	var sourceLang       = document.getElementById('aif-source-lang');
	var targetLang       = document.getElementById('aif-target-lang');

	if (translateBtn && output) {
		/* Enable translate button when output has content */
		var observer = new MutationObserver(function () {
			translateBtn.disabled = !output.value;
		});
		/* Also check on output change */
		output.addEventListener('input', function () {
			translateBtn.disabled = !output.value;
		});
		/* Check periodically (output is set programmatically) */
		setInterval(function () {
			if (translateBtn) translateBtn.disabled = !output.value;
		}, 500);

		translateBtn.addEventListener('click', async function () {
			var html = output.value;
			if (!html) {
				toast('Formatez d\'abord le texte avant de traduire.', 'error');
				return;
			}

			translateBtn.disabled = true;
			translateBtn.innerHTML = '<span class="aif-spinner"></span> Traduction\u2026';
			copyTransBtn.disabled = true;

			try {
				var res = await window.wp.apiFetch({
					url: AIF.translateUrl,
					method: 'POST',
					headers: { 'X-WP-Nonce': AIF.nonce },
					data: {
						html: html,
						source_lang: sourceLang.value,
						target_lang: targetLang.value,
					},
				});

				if (res.error) {
					toast('Erreur traduction : ' + res.error, 'error');
					return;
				}

				translationOut.value = res.html;
				translationPrev.innerHTML = res.html;
				/* Apply same CSS preset as preview */
				var currentPreset = css.value;
				var presets = (window.AIF && AIF.presets) || ['clean', 'sansdoute', 'tech', 'apple'];
				presets.forEach(function (p) {
					translationPrev.classList.remove('aif-css-' + p);
				});
				translationPrev.classList.add('aif-css-' + currentPreset);

				copyTransBtn.disabled = false;
				toast('Traduction vers ' + res.target_name + ' terminee !', 'success');
			} catch (e) {
				toast(e && e.message ? e.message : 'Erreur de traduction.', 'error');
			} finally {
				translateBtn.disabled = false;
				translateBtn.innerHTML = 'Traduire';
			}
		});

		/* Copy translation */
		copyTransBtn.addEventListener('click', function () {
			if (!translationOut.value) return;

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(translationOut.value).then(function () {
					toast('Traduction copiee dans le presse-papier.', 'success');
				});
			} else {
				translationOut.select();
				document.execCommand('copy');
				toast('Traduction copiee dans le presse-papier.', 'success');
			}
		});
	}
})();
