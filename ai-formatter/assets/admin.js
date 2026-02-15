/**
 * AI Formatter - Admin JS v1.1.0
 *
 * Features :
 * - Import DOCX (mammoth.js)
 * - Detection automatique du ton
 * - Historique de versions (diff avant/apres)
 * - Presets CSS personnalisables
 * - Mode strict
 * - Export PDF / Markdown
 * - Nettoyage & formatage REST
 * - Traduction
 *
 * @author Peopleofverso
 */
(function () {
	'use strict';

	/* ================================================================ */
	/*  DOM refs                                                        */
	/* ================================================================ */
	var input        = document.getElementById('aif-input');
	var output       = document.getElementById('aif-output');
	var preview      = document.getElementById('aif-preview');
	var btn          = document.getElementById('aif-run');
	var copyBtn      = document.getElementById('aif-copy');
	var mode         = document.getElementById('aif-mode');
	var style        = document.getElementById('aif-style');
	var css          = document.getElementById('aif-css');
	var statsEl      = document.getElementById('aif-stats');
	var styleRow     = document.querySelector('.aif-style-row');
	var strictCheck  = document.getElementById('aif-strict');
	var exportMdBtn  = document.getElementById('aif-export-md');
	var exportPdfBtn = document.getElementById('aif-export-pdf');

	/* DOCX import */
	var docxInput    = document.getElementById('aif-docx-input');
	var importStatus = document.getElementById('aif-import-status');

	/* Tone detector */
	var toneBar        = document.getElementById('aif-tone-bar');
	var toneBadge      = document.getElementById('aif-tone-badge');
	var toneConfidence = document.getElementById('aif-tone-confidence');

	/* History */
	var historyToggle = document.getElementById('aif-history-toggle');
	var historyClear  = document.getElementById('aif-history-clear');
	var historyList   = document.getElementById('aif-history-list');
	var diffView      = document.getElementById('aif-diff-view');

	/* Preset editor */
	var presetModal      = document.getElementById('aif-preset-modal');
	var presetModalClose = document.getElementById('aif-preset-modal-close');
	var presetEditBtn    = document.getElementById('aif-edit-preset');
	var presetNameInput  = document.getElementById('aif-preset-name');
	var presetCssInput   = document.getElementById('aif-preset-css');
	var presetPreview    = document.getElementById('aif-preset-preview');
	var presetSaveBtn    = document.getElementById('aif-preset-save');
	var presetCancelBtn  = document.getElementById('aif-preset-cancel');

	/* History storage */
	var history = [];
	var MAX_HISTORY = 20;

	/* ================================================================ */
	/*  Toast notifications                                             */
	/* ================================================================ */
	function toast(message, type) {
		type = type || 'success';
		var el = document.createElement('div');
		el.className = 'aif-toast aif-toast-' + type;
		el.textContent = message;
		document.body.appendChild(el);
		el.offsetHeight; // force reflow
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
	/*  1. Import DOCX via mammoth.js                                   */
	/* ================================================================ */
	function handleDocxFile(file) {
		if (!window.mammoth) {
			toast('Mammoth.js non charge. Rechargez la page.', 'error');
			return;
		}

		importStatus.textContent = 'Conversion en cours...';

		var reader = new FileReader();
		reader.onload = function (ev) {
			var arrayBuffer = ev.target.result;
			mammoth.convertToHtml({ arrayBuffer: arrayBuffer })
				.then(function (result) {
					/* Convertit le HTML en texte brut pour le pipeline */
					var tmp = document.createElement('div');
					tmp.innerHTML = result.value;
					input.value = htmlToPlaintext(tmp);
					updateStats();
					detectTone();
					importStatus.textContent = 'Fichier "' + file.name + '" importe.';
					toast('DOCX importe avec succes.', 'success');

					if (result.messages.length > 0) {
						console.warn('Mammoth warnings:', result.messages);
					}
				})
				.catch(function (err) {
					importStatus.textContent = '';
					toast('Erreur import DOCX : ' + err.message, 'error');
				});
		};
		reader.readAsArrayBuffer(file);
	}

	/**
	 * Convertit du HTML en texte pseudo-markdown pour le pipeline.
	 */
	function htmlToPlaintext(el) {
		var out = '';
		var children = el.childNodes;
		for (var i = 0; i < children.length; i++) {
			var node = children[i];
			if (node.nodeType === 3) {
				out += node.textContent;
				continue;
			}
			if (node.nodeType !== 1) continue;

			var tag = node.tagName.toLowerCase();
			switch (tag) {
				case 'h1': out += '# ' + node.textContent.trim() + '\n\n'; break;
				case 'h2': out += '# ' + node.textContent.trim() + '\n\n'; break;
				case 'h3': out += '## ' + node.textContent.trim() + '\n\n'; break;
				case 'h4': out += '### ' + node.textContent.trim() + '\n\n'; break;
				case 'p':  out += node.textContent.trim() + '\n\n'; break;
				case 'br': out += '\n'; break;
				case 'ul':
				case 'ol':
					var items = node.querySelectorAll(':scope > li');
					items.forEach(function (li, idx) {
						if (tag === 'ol') {
							out += (idx + 1) + '. ' + li.textContent.trim() + '\n';
						} else {
							out += '- ' + li.textContent.trim() + '\n';
						}
					});
					out += '\n';
					break;
				case 'blockquote':
					out += '> ' + node.textContent.trim() + '\n\n';
					break;
				case 'strong':
				case 'b':
					out += '**' + node.textContent + '**';
					break;
				case 'em':
				case 'i':
					out += '*' + node.textContent + '*';
					break;
				default:
					out += htmlToPlaintext(node);
			}
		}
		return out.replace(/\n{3,}/g, '\n\n').trim();
	}

	if (docxInput) {
		docxInput.addEventListener('change', function () {
			var file = docxInput.files[0];
			if (file) handleDocxFile(file);
			docxInput.value = '';
		});
	}

	/* ================================================================ */
	/*  2. Detection automatique du ton                                 */
	/* ================================================================ */
	var toneTimeout = null;

	function detectTone() {
		var text = input.value.trim();
		if (text.length < 50) {
			toneBar.style.display = 'none';
			return;
		}

		clearTimeout(toneTimeout);
		toneTimeout = setTimeout(function () {
			window.wp.apiFetch({
				url: AIF.toneUrl,
				method: 'POST',
				headers: { 'X-WP-Nonce': AIF.nonce },
				data: { text: text },
			}).then(function (res) {
				if (res.tone) {
					toneBar.style.display = 'flex';
					toneBadge.textContent = res.tone;
					toneBadge.className = 'aif-tone-badge aif-tone-' + res.tone;
					toneConfidence.textContent = res.confidence + '%';
				}
			}).catch(function () {
				toneBar.style.display = 'none';
			});
		}, 800);
	}

	if (input) {
		input.addEventListener('input', detectTone);
	}

	/* ================================================================ */
	/*  3. Historique de versions                                       */
	/* ================================================================ */
	function addToHistory(inputText, outputHtml) {
		history.unshift({
			input: inputText,
			output: outputHtml,
			time: new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
			date: new Date().toLocaleDateString('fr-FR'),
		});
		if (history.length > MAX_HISTORY) {
			history.pop();
		}
		renderHistory();
	}

	function renderHistory() {
		if (!historyList) return;
		if (history.length === 0) {
			historyList.innerHTML = '<p class="aif-history-empty">Aucun historique.</p>';
			return;
		}

		var html = '<table class="aif-history-table"><thead><tr><th>#</th><th>Heure</th><th>Apercu</th><th>Actions</th></tr></thead><tbody>';
		history.forEach(function (h, idx) {
			var snippet = h.input.substring(0, 60).replace(/</g, '&lt;') + (h.input.length > 60 ? '...' : '');
			html += '<tr>'
				+ '<td>' + (idx + 1) + '</td>'
				+ '<td>' + h.time + '</td>'
				+ '<td class="aif-history-snippet">' + snippet + '</td>'
				+ '<td>'
				+ '<button class="button aif-btn-small" data-action="load" data-idx="' + idx + '">Charger</button> '
				+ '<button class="button aif-btn-small" data-action="diff" data-idx="' + idx + '">Diff</button>'
				+ '</td>'
				+ '</tr>';
		});
		html += '</tbody></table>';
		historyList.innerHTML = html;

		/* Event delegation */
		historyList.onclick = function (e) {
			var btn = e.target.closest('[data-action]');
			if (!btn) return;
			var idx = parseInt(btn.dataset.idx, 10);
			if (btn.dataset.action === 'load') {
				input.value = history[idx].input;
				output.value = history[idx].output;
				preview.innerHTML = history[idx].output;
				updateStats();
				copyBtn.disabled = false;
				exportMdBtn.disabled = false;
				exportPdfBtn.disabled = false;
				toast('Version #' + (idx + 1) + ' chargee.', 'success');
			} else if (btn.dataset.action === 'diff') {
				showDiff(idx);
			}
		};
	}

	function showDiff(idx) {
		if (!diffView) return;
		var entry = history[idx];
		var currentOutput = output.value || '';

		diffView.style.display = 'block';
		diffView.innerHTML = '<h4>Diff : version #' + (idx + 1) + ' vs. resultat actuel</h4>'
			+ '<div class="aif-diff-columns">'
			+ '<div class="aif-diff-col"><h5>Version #' + (idx + 1) + '</h5><pre>' + escapeHtml(entry.output) + '</pre></div>'
			+ '<div class="aif-diff-col"><h5>Resultat actuel</h5><pre>' + escapeHtml(currentOutput) + '</pre></div>'
			+ '</div>'
			+ '<div class="aif-diff-inline">' + computeInlineDiff(entry.output, currentOutput) + '</div>';
	}

	function computeInlineDiff(oldText, newText) {
		var oldWords = oldText.split(/(\s+)/);
		var newWords = newText.split(/(\s+)/);
		var result = '';
		var maxLen = Math.max(oldWords.length, newWords.length);

		for (var i = 0; i < maxLen; i++) {
			var ow = oldWords[i] || '';
			var nw = newWords[i] || '';
			if (ow === nw) {
				result += escapeHtml(nw);
			} else {
				if (ow) result += '<del>' + escapeHtml(ow) + '</del>';
				if (nw) result += '<ins>' + escapeHtml(nw) + '</ins>';
			}
		}
		return '<pre class="aif-diff-inline-pre">' + result + '</pre>';
	}

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(text));
		return div.innerHTML;
	}

	if (historyToggle) {
		historyToggle.addEventListener('click', function () {
			var visible = historyList.style.display !== 'none';
			historyList.style.display = visible ? 'none' : 'block';
			historyClear.style.display = visible ? 'none' : 'inline-block';
			historyToggle.textContent = visible ? 'Afficher' : 'Masquer';
			if (!visible) renderHistory();
		});
	}

	if (historyClear) {
		historyClear.addEventListener('click', function () {
			history = [];
			renderHistory();
			diffView.style.display = 'none';
			toast('Historique vide.', 'success');
		});
	}

	/* ================================================================ */
	/*  4. Presets CSS personnalisables                                  */
	/* ================================================================ */
	var customStyleEl = null;

	function injectCustomPresetCSS() {
		if (!customStyleEl) {
			customStyleEl = document.createElement('style');
			customStyleEl.id = 'aif-custom-preset-styles';
			document.head.appendChild(customStyleEl);
		}

		var cssText = '';
		var presets = (window.AIF && AIF.customPresets) || [];
		presets.forEach(function (p) {
			if (p.css) cssText += p.css + '\n';
		});
		customStyleEl.textContent = cssText;
	}

	injectCustomPresetCSS();

	if (presetEditBtn) {
		presetEditBtn.addEventListener('click', function () {
			presetModal.style.display = 'flex';
			presetNameInput.value = '';
			presetCssInput.value = '';
		});
	}

	if (presetModalClose) {
		presetModalClose.addEventListener('click', function () {
			presetModal.style.display = 'none';
		});
	}

	if (presetCancelBtn) {
		presetCancelBtn.addEventListener('click', function () {
			presetModal.style.display = 'none';
		});
	}

	/* Live preview in preset editor */
	if (presetCssInput && presetPreview) {
		presetCssInput.addEventListener('input', function () {
			var tmpStyle = document.getElementById('aif-preset-tmp');
			if (!tmpStyle) {
				tmpStyle = document.createElement('style');
				tmpStyle.id = 'aif-preset-tmp';
				document.head.appendChild(tmpStyle);
			}
			tmpStyle.textContent = presetCssInput.value;
		});
	}

	if (presetSaveBtn) {
		presetSaveBtn.addEventListener('click', async function () {
			var name = presetNameInput.value.trim();
			var cssVal = presetCssInput.value.trim();

			if (!name) {
				toast('Donnez un nom au preset.', 'error');
				return;
			}

			presetSaveBtn.disabled = true;
			try {
				var res = await window.wp.apiFetch({
					url: AIF.presetSaveUrl,
					method: 'POST',
					headers: { 'X-WP-Nonce': AIF.nonce },
					data: { name: name, css: cssVal },
				});

				if (res.success) {
					/* Ajoute l'option au select si nouvelle */
					var slug = res.slug;
					var exists = false;
					for (var i = 0; i < css.options.length; i++) {
						if (css.options[i].value === slug) {
							exists = true;
							break;
						}
					}
					if (!exists) {
						var opt = document.createElement('option');
						opt.value = slug;
						opt.textContent = name;
						css.appendChild(opt);
					}
					css.value = slug;

					/* Maj les presets custom en memoire */
					AIF.customPresets = res.presets;
					injectCustomPresetCSS();
					setCssPreset(slug);

					/* Nettoie le style temporaire */
					var tmpStyle = document.getElementById('aif-preset-tmp');
					if (tmpStyle) tmpStyle.remove();

					presetModal.style.display = 'none';
					toast('Preset "' + name + '" sauvegarde.', 'success');
				} else {
					toast(res.error || 'Erreur.', 'error');
				}
			} catch (e) {
				toast(e && e.message ? e.message : 'Erreur sauvegarde preset.', 'error');
			} finally {
				presetSaveBtn.disabled = false;
			}
		});
	}

	/* Close modal on backdrop click */
	if (presetModal) {
		presetModal.addEventListener('click', function (e) {
			if (e.target === presetModal) {
				presetModal.style.display = 'none';
			}
		});
	}

	/* ================================================================ */
	/*  5. Preset CSS switch                                            */
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
	/*  6. Toggle style row                                             */
	/* ================================================================ */
	mode.addEventListener('change', function () {
		styleRow.style.display = mode.value === 'ai_proofread' ? 'flex' : 'none';
	});

	/* ================================================================ */
	/*  7. Main button : format                                         */
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
		exportMdBtn.disabled = true;
		exportPdfBtn.disabled = true;
		preview.classList.add('aif-loading');

		try {
			var res = await window.wp.apiFetch({
				url: AIF.restUrl,
				method: 'POST',
				headers: { 'X-WP-Nonce': AIF.nonce },
				data: {
					text:   text,
					mode:   mode.value,
					style:  style.value,
					css:    css.value,
					strict: strictCheck && strictCheck.checked,
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
			exportMdBtn.disabled = false;
			exportPdfBtn.disabled = false;

			/* Ajout a l'historique */
			addToHistory(text, res.html);

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
	/*  8. Copy button                                                  */
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
	/*  9. Export Markdown                                              */
	/* ================================================================ */
	function htmlToMarkdown(html) {
		var tmp = document.createElement('div');
		tmp.innerHTML = html;
		return nodeToMarkdown(tmp).replace(/\n{3,}/g, '\n\n').trim();
	}

	function nodeToMarkdown(el) {
		var md = '';
		var children = el.childNodes;

		for (var i = 0; i < children.length; i++) {
			var node = children[i];
			if (node.nodeType === 3) {
				md += node.textContent;
				continue;
			}
			if (node.nodeType !== 1) continue;

			var tag = node.tagName.toLowerCase();
			var inner = nodeToMarkdown(node);

			switch (tag) {
				case 'h2': md += '\n## ' + inner.trim() + '\n\n'; break;
				case 'h3': md += '\n### ' + inner.trim() + '\n\n'; break;
				case 'h4': md += '\n#### ' + inner.trim() + '\n\n'; break;
				case 'p':  md += inner.trim() + '\n\n'; break;
				case 'br': md += '\n'; break;
				case 'strong':
				case 'b':
					md += '**' + inner + '**';
					break;
				case 'em':
				case 'i':
					md += '*' + inner + '*';
					break;
				case 'code':
					md += '`' + inner + '`';
					break;
				case 'a':
					md += '[' + inner + '](' + (node.getAttribute('href') || '') + ')';
					break;
				case 'ul':
					var lis = node.querySelectorAll(':scope > li');
					lis.forEach(function (li) {
						md += '- ' + nodeToMarkdown(li).trim() + '\n';
					});
					md += '\n';
					break;
				case 'ol':
					var olis = node.querySelectorAll(':scope > li');
					olis.forEach(function (li, idx) {
						md += (idx + 1) + '. ' + nodeToMarkdown(li).trim() + '\n';
					});
					md += '\n';
					break;
				case 'li':
					md += inner;
					break;
				case 'blockquote':
					md += '> ' + inner.trim() + '\n\n';
					break;
				case 'hr':
					md += '\n---\n\n';
					break;
				default:
					md += inner;
			}
		}
		return md;
	}

	if (exportMdBtn) {
		exportMdBtn.addEventListener('click', function () {
			if (!output.value) return;

			var markdown = htmlToMarkdown(output.value);
			var blob = new Blob([markdown], { type: 'text/markdown;charset=utf-8' });
			var url = URL.createObjectURL(blob);
			var a = document.createElement('a');
			a.href = url;
			a.download = 'ai-formatter-export.md';
			a.click();
			URL.revokeObjectURL(url);
			toast('Export Markdown telecharge.', 'success');
		});
	}

	/* ================================================================ */
	/*  10. Export PDF                                                   */
	/* ================================================================ */
	if (exportPdfBtn) {
		exportPdfBtn.addEventListener('click', function () {
			if (!output.value) return;

			var printWin = window.open('', '_blank', 'width=800,height=600');
			if (!printWin) {
				toast('Popup bloquee. Autorisez les popups pour exporter en PDF.', 'error');
				return;
			}

			var presetClass = 'aif-css-' + css.value;
			var previewStyles = '';

			/* Copie les styles pertinents */
			var sheets = document.styleSheets;
			for (var i = 0; i < sheets.length; i++) {
				try {
					var rules = sheets[i].cssRules || sheets[i].rules;
					for (var j = 0; j < rules.length; j++) {
						var rule = rules[j].cssText || '';
						if (rule.indexOf('aif-preview') !== -1 || rule.indexOf('aif-css-') !== -1) {
							previewStyles += rule + '\n';
						}
					}
				} catch (e) { /* cross-origin */ }
			}

			/* Ajoute les styles custom */
			if (customStyleEl) {
				previewStyles += customStyleEl.textContent + '\n';
			}

			printWin.document.write(
				'<!DOCTYPE html><html><head><meta charset="utf-8">'
				+ '<title>AI Formatter - Export PDF</title>'
				+ '<style>'
				+ 'body { max-width: 700px; margin: 40px auto; padding: 0 20px; }'
				+ previewStyles
				+ '</style></head><body>'
				+ '<div class="aif-preview ' + presetClass + '">'
				+ output.value
				+ '</div></body></html>'
			);
			printWin.document.close();
			printWin.focus();

			setTimeout(function () {
				printWin.print();
			}, 400);

			toast('Fenetre d\'impression ouverte.', 'success');
		});
	}

	/* ================================================================ */
	/*  11. Drag & drop (txt, md, docx)                                 */
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

		if (name.endsWith('.docx')) {
			handleDocxFile(file);
		} else if (name.endsWith('.txt') || name.endsWith('.md')) {
			var reader = new FileReader();
			reader.onload = function (ev) {
				input.value = ev.target.result;
				updateStats();
				detectTone();
				toast('Fichier "' + file.name + '" charge.', 'success');
			};
			reader.readAsText(file, 'UTF-8');
		} else {
			toast('Format non supporte. Utilisez .txt, .md ou .docx.', 'error');
		}
	});

	/* ================================================================ */
	/*  12. Keyboard shortcut : Ctrl+Enter to format                    */
	/* ================================================================ */
	input.addEventListener('keydown', function (e) {
		if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
			e.preventDefault();
			btn.click();
		}
	});

	/* ================================================================ */
	/*  13. Translation                                                 */
	/* ================================================================ */
	var translateBtn    = document.getElementById('aif-translate');
	var copyTransBtn    = document.getElementById('aif-copy-translation');
	var translationOut  = document.getElementById('aif-translation-output');
	var translationPrev = document.getElementById('aif-translation-preview');
	var sourceLang      = document.getElementById('aif-source-lang');
	var targetLang      = document.getElementById('aif-target-lang');

	if (translateBtn && output) {
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
