(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var data = window.fgrMsData;
		if (!data) {
			return;
		}

		var select = document.getElementById('fgr-ms-period');
		if (select) {
			select.addEventListener('change', function () {
				renderPeriod(data, select.value);
			});
			renderPeriod(data, select.value);
		}

		renderChart(data.trend || []);
	});

	function renderPeriod(data, key) {
		var period = (data.periods || {})[key];
		if (!period) {
			return;
		}

		setField('visits', period.visits);
		setField('pageviews', period.pageviews);
		setField('bounce_rate', period.bounce_rate);
		setField('avg_time', period.avg_time);

		renderTable('fgr-ms-top-pages', period.top_pages || [], 'Keine Seitenaufrufe in diesem Zeitraum.');
		renderTable('fgr-ms-referrers', period.referrer_types || [], 'Keine Daten in diesem Zeitraum.');
	}

	function setField(field, value) {
		var el = document.querySelector('[data-field="' + field + '"]');
		if (el) {
			el.textContent = value === undefined || value === null ? '–' : value;
		}
	}

	function renderTable(tableId, rows, emptyText) {
		var table = document.getElementById(tableId);
		if (!table) {
			return;
		}
		var tbody = table.querySelector('tbody');
		tbody.innerHTML = '';

		if (!rows.length) {
			var emptyRow = document.createElement('tr');
			var emptyCell = document.createElement('td');
			emptyCell.textContent = emptyText;
			emptyRow.appendChild(emptyCell);
			tbody.appendChild(emptyRow);
			return;
		}

		rows.forEach(function (row) {
			var tr = document.createElement('tr');

			var labelCell = document.createElement('td');
			labelCell.textContent = row.label || '(unbekannt)';
			tr.appendChild(labelCell);

			var visitsCell = document.createElement('td');
			visitsCell.style.textAlign = 'right';
			visitsCell.textContent = row.visits;
			tr.appendChild(visitsCell);

			tbody.appendChild(tr);
		});
	}

	function renderChart(trend) {
		var svg = document.getElementById('fgr-ms-chart');
		if (!svg || !trend.length) {
			return;
		}

		var width = 600;
		var height = 160;
		var padding = 10;

		var max = Math.max.apply(null, trend.map(function (p) { return p.visits; }).concat([1]));
		var stepX = (width - padding * 2) / Math.max(trend.length - 1, 1);

		var points = trend.map(function (p, i) {
			var x = padding + i * stepX;
			var y = height - padding - (p.visits / max) * (height - padding * 2);
			return x + ',' + y;
		});

		var polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
		polyline.setAttribute('points', points.join(' '));
		polyline.setAttribute('fill', 'none');
		polyline.setAttribute('stroke', '#2271b1');
		polyline.setAttribute('stroke-width', '2');
		svg.appendChild(polyline);
	}
})();
