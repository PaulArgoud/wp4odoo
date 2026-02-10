/**
 * Customer portal — tab switching and AJAX pagination.
 *
 * Depends on: wp4odooPortal (localized by Portal_Manager::render_portal).
 *
 * @package WP4Odoo
 * @since   1.1.0
 */
(function () {
	'use strict';

	var portal = document.querySelector('.wp4odoo-portal');
	if (!portal) return;

	var cfg = window.wp4odooPortal || {};

	/* ─── Tab switching ──────────────────────────────────── */

	portal.querySelectorAll('.wp4odoo-tab').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var tab = btn.dataset.tab;

			// Update active tab button.
			portal.querySelectorAll('.wp4odoo-tab').forEach(function (b) {
				b.classList.toggle('active', b === btn);
			});

			// Update active panel.
			portal.querySelectorAll('.wp4odoo-panel').forEach(function (p) {
				p.classList.toggle('active', p.dataset.panel === tab);
			});
		});
	});

	/* ─── AJAX pagination ────────────────────────────────── */

	portal.addEventListener('click', function (e) {
		var btn = e.target.closest('.wp4odoo-page');
		if (!btn) return;

		var nav  = btn.closest('.wp4odoo-pagination');
		var tab  = nav.dataset.tab;
		var page = parseInt(btn.dataset.page, 10);

		// Mark active page button.
		nav.querySelectorAll('.wp4odoo-page').forEach(function (b) {
			b.classList.toggle('active', b === btn);
		});

		fetchPage(tab, page);
	});

	function fetchPage(tab, page) {
		var panel = portal.querySelector('[data-panel="' + tab + '"]');
		panel.innerHTML = '<p class="wp4odoo-portal-loading">' + (cfg.i18n ? cfg.i18n.loading : 'Loading...') + '</p>';

		var body = new FormData();
		body.append('action', 'wp4odoo_portal_data');
		body.append('_ajax_nonce', cfg.nonce);
		body.append('tab', tab);
		body.append('page', page);
		body.append('partner_id', cfg.partnerId);

		fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (json) {
				if (json.success) {
					renderPanel(panel, tab, json.data);
				} else {
					panel.innerHTML = '<p class="wp4odoo-empty">' + (json.data && json.data.message ? json.data.message : cfg.i18n.error) + '</p>';
				}
			})
			.catch(function () {
				panel.innerHTML = '<p class="wp4odoo-empty">' + (cfg.i18n ? cfg.i18n.error : 'Error') + '</p>';
			});
	}

	function renderPanel(panel, tab, data) {
		if (!data.items || !data.items.length) {
			panel.innerHTML = '<p class="wp4odoo-empty">' + (tab === 'orders' ? 'No orders found.' : 'No invoices found.') + '</p>';
			return;
		}

		var html = '<table class="wp4odoo-portal-table"><thead><tr>';

		if (tab === 'orders') {
			html += '<th>Reference</th><th>Date</th><th>Total</th><th>Status</th>';
		} else {
			html += '<th>Reference</th><th>Date</th><th>Total</th><th>Status</th><th>Payment</th>';
		}

		html += '</tr></thead><tbody>';

		data.items.forEach(function (item) {
			html += '<tr>';
			html += '<td>' + esc(item.title) + '</td>';

			if (tab === 'orders') {
				html += '<td>' + esc(item._order_date || '\u2014') + '</td>';
				html += '<td>' + esc(item._order_total || '\u2014') + '</td>';
				html += '<td><span class="wp4odoo-status wp4odoo-status--' + esc(item._order_state || 'draft') + '">' + esc(item._order_state || '\u2014') + '</span></td>';
			} else {
				html += '<td>' + esc(item._invoice_date || '\u2014') + '</td>';
				html += '<td>' + esc(item._invoice_total || '\u2014') + '</td>';
				html += '<td><span class="wp4odoo-status wp4odoo-status--' + esc(item._invoice_state || 'draft') + '">' + esc(item._invoice_state || '\u2014') + '</span></td>';
				html += '<td><span class="wp4odoo-payment wp4odoo-payment--' + esc(item._payment_state || 'not_paid') + '">' + esc(item._payment_state || '\u2014') + '</span></td>';
			}

			html += '</tr>';
		});

		html += '</tbody></table>';

		// Pagination.
		if (data.pages > 1) {
			html += '<nav class="wp4odoo-pagination" data-tab="' + tab + '" data-pages="' + data.pages + '">';
			for (var i = 1; i <= data.pages; i++) {
				html += '<button type="button" class="wp4odoo-page' + (i === data.page ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
			}
			html += '</nav>';
		}

		panel.innerHTML = html;
	}

	function esc(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(String(str)));
		return div.innerHTML;
	}
})();
