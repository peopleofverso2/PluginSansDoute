/**
 * SansDoute Influence — Admin JavaScript
 *
 * Handles: Dashboard, Brief editor, Creator workspace.
 * Depends on: wp-api-fetch (WordPress REST API helper).
 *
 * @author Peopleofverso
 */

/* global AIF_INF, wp */
(function () {
	'use strict';

	if (typeof AIF_INF === 'undefined') return;

	const API = AIF_INF.restBase;
	const NONCE = AIF_INF.nonce;

	/* ================================================================ */
	/*  Helpers                                                          */
	/* ================================================================ */

	async function apiCall(endpoint, method, body) {
		const opts = {
			method: method || 'GET',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': NONCE,
			},
		};
		if (body) opts.body = JSON.stringify(body);

		const resp = await fetch(API + endpoint, opts);
		const data = await resp.json();

		if (!resp.ok) {
			throw new Error(data.error || 'Erreur API');
		}
		return data;
	}

	function $(sel, ctx) {
		return (ctx || document).querySelector(sel);
	}

	function $$(sel, ctx) {
		return Array.from((ctx || document).querySelectorAll(sel));
	}

	function scoreLevel(score) {
		if (score >= 70) return 'high';
		if (score >= 40) return 'medium';
		return 'low';
	}

	function toast(msg, isError) {
		const el = document.createElement('div');
		el.className = 'aif-inf-toast' + (isError ? ' error' : '');
		el.textContent = msg;
		document.body.appendChild(el);
		requestAnimationFrame(() => el.classList.add('visible'));
		setTimeout(() => {
			el.classList.remove('visible');
			setTimeout(() => el.remove(), 300);
		}, 3000);
	}

	function statusLabel(status) {
		const labels = {
			draft: 'Brouillon',
			submitted: 'Soumis',
			revision: 'Revision',
			approved: 'Approuve',
			published: 'Publie',
			publish: 'Actif',
			closed: 'Termine',
		};
		return labels[status] || status;
	}

	/* ================================================================ */
	/*  Dashboard                                                        */
	/* ================================================================ */

	async function initDashboard() {
		const briefsBody = $('#aif-inf-briefs-body');
		const collabsBody = $('#aif-inf-collabs-body');

		if (!briefsBody) return; // Not on dashboard page

		try {
			const [briefsRes, collabsRes] = await Promise.all([
				apiCall('briefs'),
				apiCall('collabs'),
			]);

			const briefs = briefsRes.briefs || [];
			const collabs = collabsRes.collabs || [];

			// Stats
			const statBriefs = $('#aif-inf-stat-briefs');
			const statCollabs = $('#aif-inf-stat-collabs');
			const statPending = $('#aif-inf-stat-pending');
			const statCompliance = $('#aif-inf-stat-compliance');

			if (statBriefs) statBriefs.textContent = briefs.length;
			if (statCollabs) statCollabs.textContent = collabs.length;
			if (statPending) {
				statPending.textContent = collabs.filter(
					(c) => c.status === 'submitted'
				).length;
			}
			if (statCompliance) {
				const scores = collabs
					.filter((c) => c.compliance_score !== null)
					.map((c) => c.compliance_score);
				statCompliance.textContent =
					scores.length > 0
						? Math.round(scores.reduce((a, b) => a + b, 0) / scores.length) + '%'
						: '-';
			}

			// Briefs table
			if (briefs.length === 0) {
				briefsBody.innerHTML =
					'<tr><td colspan="7" style="text-align:center;color:#999;padding:24px;">Aucun brief. <a href="' +
					AIF_INF.briefPage +
					'">Creer le premier</a>.</td></tr>';
			} else {
				briefsBody.innerHTML = briefs
					.map(
						(b) => `
					<tr>
						<td><strong>${esc(b.title)}</strong></td>
						<td>${esc(b.brand)}</td>
						<td>${esc(b.tone)}</td>
						<td>${b.deadline || '-'}</td>
						<td>${b.collab_count}</td>
						<td><span class="aif-inf-status-badge" data-status="${b.status}">${statusLabel(b.status)}</span></td>
						<td>
							<a href="${AIF_INF.briefPage}&brief_id=${b.id}" class="button">Modifier</a>
							<a href="${AIF_INF.workPage}&brief_id=${b.id}" class="button">Ecrire</a>
						</td>
					</tr>`
					)
					.join('');
			}

			// Collabs table
			if (collabs.length === 0) {
				collabsBody.innerHTML =
					'<tr><td colspan="7" style="text-align:center;color:#999;padding:24px;">Aucun contenu encore.</td></tr>';
			} else {
				collabsBody.innerHTML = collabs
					.map(
						(c) => `
					<tr>
						<td><strong>${esc(c.title)}</strong></td>
						<td>${esc(c.brief_title)}</td>
						<td>${esc(c.author_name)}</td>
						<td>${renderScoreInline(c.compliance_score)}</td>
						<td>${renderScoreInline(c.quality_score)}</td>
						<td><span class="aif-inf-status-badge" data-status="${c.status}">${statusLabel(c.status)}</span></td>
						<td>
							<a href="${AIF_INF.workPage}&collab_id=${c.id}&brief_id=${c.brief_id}" class="button">Ouvrir</a>
						</td>
					</tr>`
					)
					.join('');
			}
		} catch (err) {
			briefsBody.innerHTML =
				'<tr><td colspan="7" style="color:#e74c3c;">' +
				esc(err.message) +
				'</td></tr>';
		}
	}

	function renderScoreInline(score) {
		if (score === null || score === undefined) return '<span style="color:#999;">-</span>';
		const level = scoreLevel(score);
		return `<span class="aif-inf-score-inline" data-level="${level}">${score}%</span>`;
	}

	function esc(str) {
		if (!str) return '';
		const el = document.createElement('span');
		el.textContent = str;
		return el.innerHTML;
	}

	/* ================================================================ */
	/*  Brief Editor                                                     */
	/* ================================================================ */

	async function initBriefEditor() {
		const form = $('#aif-inf-brief-form');
		if (!form) return;

		const briefId = parseInt($('#aif-inf-brief-id').value, 10) || 0;

		// Load existing brief
		if (briefId > 0) {
			try {
				const data = await apiCall('briefs/' + briefId);
				const b = data.brief;
				$('#aif-inf-campaign-name').value = b.title;
				$('#aif-inf-brand').value = b.brand;
				$('#aif-inf-description').value = b.description;
				$('#aif-inf-keywords-required').value = (b.keywords_required || []).join(', ');
				$('#aif-inf-keywords-forbidden').value = (b.keywords_forbidden || []).join(', ');
				$('#aif-inf-links').value = (b.links_required || []).join('\n');
				$('#aif-inf-legal').value = (b.legal_mentions || []).join('\n');
				$('#aif-inf-tone').value = b.tone;
				$('#aif-inf-deadline').value = b.deadline;
				$('#aif-inf-publish-date').value = b.publish_date;
				$('#aif-inf-budget').value = b.budget;
			} catch (err) {
				toast('Erreur chargement brief : ' + err.message, true);
			}
		}

		// Live summary
		const summaryFields = [
			'aif-inf-campaign-name',
			'aif-inf-brand',
			'aif-inf-keywords-required',
			'aif-inf-keywords-forbidden',
			'aif-inf-tone',
			'aif-inf-deadline',
		];
		summaryFields.forEach((id) => {
			const el = document.getElementById(id);
			if (el) el.addEventListener('input', updateBriefSummary);
		});

		// Submit
		form.addEventListener('submit', async (e) => {
			e.preventDefault();
			const btn = $('#aif-inf-save-brief');
			btn.disabled = true;
			btn.textContent = 'Sauvegarde...';

			try {
				const payload = {
					title: $('#aif-inf-campaign-name').value,
					brand: $('#aif-inf-brand').value,
					description: $('#aif-inf-description').value,
					keywords_required: $('#aif-inf-keywords-required').value,
					keywords_forbidden: $('#aif-inf-keywords-forbidden').value,
					links_required: $('#aif-inf-links').value,
					legal_mentions: $('#aif-inf-legal').value,
					tone: $('#aif-inf-tone').value,
					deadline: $('#aif-inf-deadline').value,
					publish_date: $('#aif-inf-publish-date').value,
					budget: $('#aif-inf-budget').value,
				};

				if (briefId > 0) payload.id = briefId;

				const result = await apiCall('briefs', 'POST', payload);

				toast('Brief sauvegarde !');

				// Redirect to edit mode if new
				if (!briefId && result.brief) {
					window.location.href =
						AIF_INF.briefPage + '&brief_id=' + result.brief.id;
				}
			} catch (err) {
				toast('Erreur : ' + err.message, true);
			} finally {
				btn.disabled = false;
				btn.textContent = briefId > 0 ? 'Mettre a jour le brief' : 'Creer le brief';
			}
		});
	}

	function updateBriefSummary() {
		const el = $('#aif-inf-brief-summary');
		if (!el) return;

		const name = $('#aif-inf-campaign-name').value || '(sans nom)';
		const brand = $('#aif-inf-brand').value || '(marque)';
		const tone = $('#aif-inf-tone').value || 'neutre';
		const kwReq = $('#aif-inf-keywords-required').value;
		const kwForb = $('#aif-inf-keywords-forbidden').value;
		const deadline = $('#aif-inf-deadline').value;

		let html = `<strong>${esc(name)}</strong> pour <strong>${esc(brand)}</strong><br>`;
		html += `Ton : ${esc(tone)}`;
		if (deadline) html += ` | Deadline : ${esc(deadline)}`;
		if (kwReq) html += `<br>Mots-cles : ${esc(kwReq)}`;
		if (kwForb) html += `<br><span style="color:#c53030;">Interdits : ${esc(kwForb)}</span>`;

		el.innerHTML = html;
	}

	/* ================================================================ */
	/*  Workspace                                                        */
	/* ================================================================ */

	let currentBrief = null;

	async function initWorkspace() {
		const wsContent = $('#aif-inf-ws-content');
		if (!wsContent) return;

		const briefId = parseInt($('#aif-inf-ws-brief-id').value, 10) || 0;
		const collabId = parseInt($('#aif-inf-ws-collab-id').value, 10) || 0;

		// Load brief selector if no brief specified
		if (!briefId) {
			const sel = $('#aif-inf-ws-brief-select');
			if (sel) {
				try {
					const data = await apiCall('briefs');
					(data.briefs || []).forEach((b) => {
						const opt = document.createElement('option');
						opt.value = b.id;
						opt.textContent = b.title + ' (' + b.brand + ')';
						sel.appendChild(opt);
					});
					sel.addEventListener('change', () => {
						if (sel.value) {
							window.location.href =
								AIF_INF.workPage + '&brief_id=' + sel.value;
						}
					});
				} catch (err) {
					toast('Erreur chargement briefs : ' + err.message, true);
				}
			}
		}

		// Load brief details
		if (briefId > 0) {
			try {
				const data = await apiCall('briefs/' + briefId);
				currentBrief = data.brief;
				renderBriefInWorkspace(currentBrief);
			} catch (err) {
				toast('Erreur chargement brief : ' + err.message, true);
			}
		}

		// Load existing collab
		if (collabId > 0) {
			try {
				const data = await apiCall('collabs');
				const collab = (data.collabs || []).find((c) => c.id === collabId);
				if (collab) {
					$('#aif-inf-ws-title').value = collab.title;
					wsContent.value = collab.content;
					updateWorkspaceStatus(collab.status);
				}
			} catch (err) {
				// Ignore
			}
		}

		// Buttons
		const btnCheck = $('#aif-inf-ws-check');
		const btnScore = $('#aif-inf-ws-score');
		const btnFormat = $('#aif-inf-ws-format');
		const btnSave = $('#aif-inf-ws-save');
		const btnSubmit = $('#aif-inf-ws-submit');

		if (btnCheck) btnCheck.addEventListener('click', wsCheckCompliance);
		if (btnScore) btnScore.addEventListener('click', wsScoreContent);
		if (btnFormat) btnFormat.addEventListener('click', wsFormatContent);
		if (btnSave) btnSave.addEventListener('click', () => wsSave('draft'));
		if (btnSubmit) btnSubmit.addEventListener('click', () => wsSave('submitted'));
	}

	function renderBriefInWorkspace(brief) {
		const container = $('#aif-inf-ws-brief-content');
		if (!container) return;

		let html = '<dl class="aif-inf-brief-display">';
		html += `<dt>Campagne</dt><dd><strong>${esc(brief.title)}</strong> — ${esc(brief.brand)}</dd>`;

		if (brief.description) {
			html += `<dt>Description</dt><dd>${esc(brief.description)}</dd>`;
		}

		html += `<dt>Ton attendu</dt><dd>${esc(brief.tone)}</dd>`;

		if (brief.keywords_required && brief.keywords_required.length) {
			html += '<dt>Mots-cles obligatoires</dt><dd>';
			html += brief.keywords_required
				.map((k) => `<span class="tag tag-required">${esc(k)}</span>`)
				.join('');
			html += '</dd>';
		}

		if (brief.keywords_forbidden && brief.keywords_forbidden.length) {
			html += '<dt>Mots-cles interdits</dt><dd>';
			html += brief.keywords_forbidden
				.map((k) => `<span class="tag tag-forbidden">${esc(k)}</span>`)
				.join('');
			html += '</dd>';
		}

		if (brief.links_required && brief.links_required.length) {
			html += '<dt>Liens obligatoires</dt><dd>';
			html += brief.links_required.map((l) => `<code>${esc(l)}</code>`).join('<br>');
			html += '</dd>';
		}

		if (brief.legal_mentions && brief.legal_mentions.length) {
			html += '<dt>Mentions legales</dt><dd>';
			html += brief.legal_mentions.map((m) => `<strong>${esc(m)}</strong>`).join('<br>');
			html += '</dd>';
		}

		if (brief.deadline) {
			html += `<dt>Deadline</dt><dd>${esc(brief.deadline)}</dd>`;
		}

		if (brief.budget) {
			html += `<dt>Budget</dt><dd>${esc(brief.budget)}</dd>`;
		}

		html += '</dl>';
		container.innerHTML = html;
	}

	async function wsCheckCompliance() {
		const content = $('#aif-inf-ws-content').value;
		const briefId =
			parseInt($('#aif-inf-ws-brief-id').value, 10) ||
			(currentBrief ? currentBrief.id : 0);

		if (!content) {
			toast('Ecrivez du contenu avant de verifier.', true);
			return;
		}

		if (!briefId) {
			toast('Selectionnez un brief.', true);
			return;
		}

		const btn = $('#aif-inf-ws-check');
		btn.disabled = true;
		btn.textContent = 'Verification...';

		try {
			const collab_id = parseInt($('#aif-inf-ws-collab-id').value, 10) || 0;
			const result = await apiCall('check-compliance', 'POST', {
				content: content,
				brief_id: briefId,
				collab_id: collab_id,
			});

			renderComplianceResult(result);
			updateSubmitButton(result.score);
		} catch (err) {
			toast('Erreur compliance : ' + err.message, true);
		} finally {
			btn.disabled = false;
			btn.textContent = 'Verifier compliance';
		}
	}

	function renderComplianceResult(result) {
		const ring = $('#aif-inf-ws-compliance-ring');
		const details = $('#aif-inf-ws-compliance-details');

		if (ring) {
			ring.setAttribute('data-level', scoreLevel(result.score));
			ring.querySelector('.aif-inf-score-value').textContent = result.score + '%';
		}

		if (!details) return;

		let html = `<p>${result.passed}/${result.total} checks passes</p>`;

		// Checks list
		html += '<ul class="aif-inf-check-list">';
		(result.checks || []).forEach((check) => {
			const icon = check.passed ? 'pass' : 'fail';
			const symbol = check.passed ? '\u2713' : '\u2717';
			html += `<li>
				<span class="aif-inf-check-icon ${icon}">${symbol}</span>
				<span>${esc(check.label)}</span>
			</li>`;
		});
		html += '</ul>';

		// Blockers
		if (result.blockers && result.blockers.length) {
			html += '<div class="aif-inf-blockers"><h4>Bloquants</h4><ul>';
			result.blockers.forEach((b) => {
				html += `<li>${esc(b)}</li>`;
			});
			html += '</ul></div>';
		}

		// Warnings
		if (result.warnings && result.warnings.length) {
			html += '<div class="aif-inf-warnings"><h4>Avertissements</h4><ul>';
			result.warnings.forEach((w) => {
				html += `<li>${esc(w)}</li>`;
			});
			html += '</ul></div>';
		}

		details.innerHTML = html;
	}

	async function wsScoreContent() {
		const content = $('#aif-inf-ws-content').value;
		const briefId =
			parseInt($('#aif-inf-ws-brief-id').value, 10) ||
			(currentBrief ? currentBrief.id : 0);

		if (!content) {
			toast('Ecrivez du contenu avant de scorer.', true);
			return;
		}

		const btn = $('#aif-inf-ws-score');
		btn.disabled = true;
		btn.textContent = 'Analyse...';

		try {
			const collab_id = parseInt($('#aif-inf-ws-collab-id').value, 10) || 0;
			const result = await apiCall('score-content', 'POST', {
				content: content,
				brief_id: briefId || 0,
				collab_id: collab_id,
			});

			renderScoreResult(result);
		} catch (err) {
			toast('Erreur scoring : ' + err.message, true);
		} finally {
			btn.disabled = false;
			btn.textContent = 'Scorer qualite';
		}
	}

	function renderScoreResult(result) {
		const ring = $('#aif-inf-ws-quality-ring');
		const details = $('#aif-inf-ws-quality-details');

		if (ring) {
			ring.setAttribute('data-level', scoreLevel(result.score));
			ring.querySelector('.aif-inf-score-value').textContent = result.score + '%';
		}

		if (!details) return;

		let html = '';

		// Sub-scores
		html += '<dl style="margin:0;">';

		if (result.authenticity) {
			html += `<dt>Authenticite</dt>`;
			html += `<dd><span class="aif-inf-score-inline" data-level="${scoreLevel(result.authenticity.score)}">${result.authenticity.score}%</span></dd>`;
		}

		if (result.readability) {
			html += `<dt>Lisibilite</dt>`;
			html += `<dd><span class="aif-inf-score-inline" data-level="${scoreLevel(result.readability.score)}">${result.readability.score}%</span></dd>`;
		}

		if (result.seo) {
			html += `<dt>SEO</dt>`;
			html += `<dd><span class="aif-inf-score-inline" data-level="${scoreLevel(result.seo.score)}">${result.seo.score}%</span></dd>`;
		}

		html += '</dl>';

		// Suggestions
		if (result.suggestions && result.suggestions.length) {
			html += '<ul class="aif-inf-suggestions">';
			result.suggestions.forEach((s) => {
				html += `<li>${esc(s)}</li>`;
			});
			html += '</ul>';
		}

		details.innerHTML = html;
	}

	async function wsFormatContent() {
		const content = $('#aif-inf-ws-content').value;
		if (!content) {
			toast('Rien a formater.', true);
			return;
		}

		const btn = $('#aif-inf-ws-format');
		btn.disabled = true;
		btn.textContent = 'Formatage...';

		try {
			// Use the main AI Formatter endpoint
			const resp = await fetch(
				AIF_INF.restBase.replace('/influence/', '/format'),
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
					body: JSON.stringify({
						text: content,
						mode: 'clean_only',
						style: 'neutre',
						css: 'clean',
						strict: false,
					}),
				}
			);

			const data = await resp.json();
			if (data.html) {
				$('#aif-inf-ws-content').value = data.html;
				toast('Contenu formate !');
			}
		} catch (err) {
			toast('Erreur formatage : ' + err.message, true);
		} finally {
			btn.disabled = false;
			btn.textContent = 'Formater (AI Formatter)';
		}
	}

	async function wsSave(status) {
		const title = $('#aif-inf-ws-title').value;
		const content = $('#aif-inf-ws-content').value;
		const briefId =
			parseInt($('#aif-inf-ws-brief-id').value, 10) ||
			(currentBrief ? currentBrief.id : 0);
		const collabId = parseInt($('#aif-inf-ws-collab-id').value, 10) || 0;

		if (!title) {
			toast('Donnez un titre a votre contenu.', true);
			return;
		}

		if (!content) {
			toast('Le contenu est vide.', true);
			return;
		}

		const btn = status === 'submitted' ? $('#aif-inf-ws-submit') : $('#aif-inf-ws-save');
		btn.disabled = true;
		const origText = btn.textContent;
		btn.textContent = 'Sauvegarde...';

		try {
			const payload = {
				title: title,
				content: content,
				brief_id: briefId,
				status: status,
			};

			if (collabId > 0) payload.id = collabId;

			const result = await apiCall('collabs', 'POST', payload);

			if (result.collab && !collabId) {
				$('#aif-inf-ws-collab-id').value = result.collab.id;
			}

			updateWorkspaceStatus(status);
			toast(
				status === 'submitted' ? 'Contenu soumis pour review !' : 'Brouillon sauvegarde !'
			);
		} catch (err) {
			toast('Erreur : ' + err.message, true);
		} finally {
			btn.disabled = false;
			btn.textContent = origText;
		}
	}

	function updateWorkspaceStatus(status) {
		const badge = $('#aif-inf-ws-status');
		if (badge) {
			badge.textContent = statusLabel(status);
			badge.setAttribute('data-status', status);
		}
	}

	function updateSubmitButton(complianceScore) {
		const btn = $('#aif-inf-ws-submit');
		if (!btn) return;
		// Allow submission only if compliance score > 40
		btn.disabled = complianceScore <= 40;
		if (complianceScore <= 40) {
			btn.title = 'Corrigez les problemes de compliance avant de soumettre.';
		} else {
			btn.title = '';
		}
	}

	/* ================================================================ */
	/*  Init                                                             */
	/* ================================================================ */

	document.addEventListener('DOMContentLoaded', () => {
		initDashboard();
		initBriefEditor();
		initWorkspace();
	});
})();
