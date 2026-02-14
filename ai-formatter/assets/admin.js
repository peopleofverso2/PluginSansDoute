/**
 * AI Formatter - Admin JS
 *
 * Gere l'interface de l'outil : envoi du texte a la REST API,
 * affichage du resultat et gestion des presets CSS.
 */
(function () {
	'use strict';

	const input   = document.getElementById('aif-input');
	const output  = document.getElementById('aif-output');
	const preview = document.getElementById('aif-preview');
	const btn     = document.getElementById('aif-run');
	const copyBtn = document.getElementById('aif-copy');
	const mode    = document.getElementById('aif-mode');
	const style   = document.getElementById('aif-style');
	const css     = document.getElementById('aif-css');

	const styleRow = document.querySelector('.aif-style-row');

	/* ---- Preset CSS ---- */
	function setCssPreset(preset) {
		const presets = (window.AIF && AIF.presets) || ['clean', 'sansdoute', 'tech', 'apple'];
		presets.forEach(function (p) {
			preview.classList.remove('aif-css-' + p);
		});
		preview.classList.add('aif-css-' + preset);
	}

	css.addEventListener('change', function () {
		setCssPreset(css.value);
	});
	setCssPreset(css.value);

	/* ---- Toggle style row quand mode = ai_proofread ---- */
	mode.addEventListener('change', function () {
		styleRow.style.display = mode.value === 'ai_proofread' ? 'flex' : 'none';
	});

	/* ---- Bouton principal ---- */
	btn.addEventListener('click', async function () {
		var text = input.value.trim();
		if (!text) {
			alert('Colle du texte avant de lancer le traitement.');
			return;
		}

		btn.disabled = true;
		btn.textContent = 'Traitement\u2026';
		copyBtn.disabled = true;

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
				alert('Erreur : ' + res.error);
				return;
			}

			output.value = res.html;
			preview.innerHTML = res.html;
			setCssPreset(res.css);
			copyBtn.disabled = false;
		} catch (e) {
			alert(e && e.message ? e.message : 'Erreur inconnue.');
		} finally {
			btn.disabled = false;
			btn.textContent = 'Nettoyer & Formater';
		}
	});

	/* ---- Bouton copier ---- */
	copyBtn.addEventListener('click', function () {
		if (!output.value) return;

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(output.value).then(function () {
				copyBtn.textContent = 'Copie !';
				setTimeout(function () { copyBtn.textContent = 'Copier HTML'; }, 1500);
			});
		} else {
			output.select();
			document.execCommand('copy');
			copyBtn.textContent = 'Copie !';
			setTimeout(function () { copyBtn.textContent = 'Copier HTML'; }, 1500);
		}
	});

	/* ---- Drag & drop de fichier texte ---- */
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
			};
			reader.readAsText(file, 'UTF-8');
		} else {
			alert('Format non supporte. Utilisez .txt ou .md.');
		}
	});
})();
