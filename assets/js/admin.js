(function () {
	'use strict';

	var SVG_NS = 'http://www.w3.org/2000/svg';

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

		window.addEventListener('resize', function () {
			if (select) {
				renderChart((data.periods || {})[select.value]);
			}
		});
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

		renderChart(period);
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

	function renderChart(period) {
		var svg = document.getElementById('fgr-ms-chart');
		var empty = document.getElementById('fgr-ms-chart-empty');
		if (!svg) {
			return;
		}

		var trend = period && period.trend ? period.trend : [];

		if (trend.length < 2) {
			svg.hidden = true;
			if (empty) {
				empty.hidden = false;
			}
			return;
		}
		svg.hidden = false;
		if (empty) {
			empty.hidden = true;
		}

		while (svg.firstChild) {
			svg.removeChild(svg.firstChild);
		}

		// In echten Pixel-Koordinaten der aktuellen Breite zeichnen (statt
		// preserveAspectRatio="none" zu strecken) - sonst wird Text verzerrt
		// und die Linie sieht je nach Fensterbreite "kaputt" aus.
		var width = svg.clientWidth || 600;
		var height = 200;
		svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);

		var padLeft = 40;
		var padRight = 10;
		var padTop = 10;
		var padBottom = 24;
		var plotWidth = width - padLeft - padRight;
		var plotHeight = height - padTop - padBottom;

		var values = trend.map(function (p) { return p.visits; });
		var max = Math.max.apply(null, values.concat([1]));
		var stepX = plotWidth / Math.max(trend.length - 1, 1);

		function xAt(i) { return padLeft + i * stepX; }
		function yAt(v) { return padTop + plotHeight - (v / max) * plotHeight; }

		// Y-Achse: 0 und Maximalwert als Gitterlinie + Beschriftung.
		[0, max].forEach(function (v) {
			var y = yAt(v);
			var line = document.createElementNS(SVG_NS, 'line');
			line.setAttribute('x1', padLeft);
			line.setAttribute('x2', width - padRight);
			line.setAttribute('y1', y);
			line.setAttribute('y2', y);
			line.setAttribute('stroke', '#e2e4e7');
			svg.appendChild(line);

			var label = document.createElementNS(SVG_NS, 'text');
			label.setAttribute('x', padLeft - 6);
			label.setAttribute('y', y + 4);
			label.setAttribute('text-anchor', 'end');
			label.setAttribute('font-size', '11');
			label.setAttribute('fill', '#646970');
			label.textContent = v;
			svg.appendChild(label);
		});

		// X-Achse: nur eine Handvoll Datumsbeschriftungen, sonst wird es voll.
		var labelEvery = Math.ceil(trend.length / 6);
		trend.forEach(function (p, i) {
			if (i % labelEvery !== 0 && i !== trend.length - 1) {
				return;
			}
			var label = document.createElementNS(SVG_NS, 'text');
			label.setAttribute('x', xAt(i));
			label.setAttribute('y', height - 6);
			label.setAttribute('text-anchor', 'middle');
			label.setAttribute('font-size', '11');
			label.setAttribute('fill', '#646970');
			label.textContent = formatDate(p.date);
			svg.appendChild(label);
		});

		var points = trend.map(function (p, i) {
			return xAt(i) + ',' + yAt(p.visits);
		});

		var polyline = document.createElementNS(SVG_NS, 'polyline');
		polyline.setAttribute('points', points.join(' '));
		polyline.setAttribute('fill', 'none');
		polyline.setAttribute('stroke', '#2271b1');
		polyline.setAttribute('stroke-width', '2');
		polyline.setAttribute('stroke-linejoin', 'round');
		polyline.setAttribute('stroke-linecap', 'round');
		svg.appendChild(polyline);
	}

	function formatDate(iso) {
		// iso ist entweder "YYYY-MM-DD" (Tag) oder "YYYY-MM" (Monat, bei "Jahr"-Ansicht).
		var parts = iso.split('-');
		if (parts.length === 3) {
			return parts[2] + '.' + parts[1] + '.';
		}
		if (parts.length === 2) {
			var months = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
			return months[parseInt(parts[1], 10) - 1] || iso;
		}
		return iso;
	}
})();
