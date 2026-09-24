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

		setupTooltip();

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

	/**
	 * "Nice" Schrittgröße für Gitterlinien (1/2/5 × 10^n), damit die
	 * Y-Achse runde Werte zeigt statt krummer Zwischenwerte.
	 */
	function niceStep(max, targetSteps) {
		var raw = max / targetSteps;
		var mag = Math.pow(10, Math.floor(Math.log10(raw || 1)));
		var norm = raw / mag;
		var step;
		if (norm < 1.5) step = 1;
		else if (norm < 3) step = 2;
		else if (norm < 7) step = 5;
		else step = 10;
		return step * mag;
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
			svg._fgrScale = null;
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
		var height = 220;
		svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);

		var padLeft = 44;
		var padRight = 10;
		var padTop = 10;
		var padBottom = 24;
		var plotWidth = width - padLeft - padRight;
		var plotHeight = height - padTop - padBottom;

		var values = trend.map(function (p) { return p.visits; });
		var dataMax = Math.max.apply(null, values.concat([1]));
		var step = niceStep(dataMax, 4);
		var axisMax = Math.ceil(dataMax / step) * step || step;
		var stepX = plotWidth / Math.max(trend.length - 1, 1);

		function xAt(i) { return padLeft + i * stepX; }
		function yAt(v) { return padTop + plotHeight - (v / axisMax) * plotHeight; }

		// Y-Achse: mehrere "runde" Gitterlinien statt nur 0 und Maximum.
		for (var gridVal = 0; gridVal <= axisMax; gridVal += step) {
			var y = yAt(gridVal);
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
			label.textContent = Math.round(gridVal);
			svg.appendChild(label);
		}

		// X-Achse: nur eine Handvoll Datumsbeschriftungen, sonst wird es voll.
		var labelEvery = Math.ceil(trend.length / 6);
		trend.forEach(function (p, i) {
			if (i % labelEvery !== 0 && i !== trend.length - 1) {
				return;
			}
			var xLabel = document.createElementNS(SVG_NS, 'text');
			xLabel.setAttribute('x', xAt(i));
			xLabel.setAttribute('y', height - 6);
			xLabel.setAttribute('text-anchor', 'middle');
			xLabel.setAttribute('font-size', '11');
			xLabel.setAttribute('fill', '#646970');
			xLabel.textContent = formatDate(p.date);
			svg.appendChild(xLabel);
		});

		var points = trend.map(function (p, i) {
			return xAt(i) + ',' + yAt(p.visits);
		});

		// Fläche unter der Linie.
		var areaPoints = points.slice();
		areaPoints.push(xAt(trend.length - 1) + ',' + yAt(0));
		areaPoints.push(xAt(0) + ',' + yAt(0));
		var area = document.createElementNS(SVG_NS, 'polygon');
		area.setAttribute('points', areaPoints.join(' '));
		area.setAttribute('fill', 'rgba(34, 113, 177, 0.12)');
		area.setAttribute('stroke', 'none');
		svg.appendChild(area);

		var polyline = document.createElementNS(SVG_NS, 'polyline');
		polyline.setAttribute('points', points.join(' '));
		polyline.setAttribute('fill', 'none');
		polyline.setAttribute('stroke', '#2271b1');
		polyline.setAttribute('stroke-width', '2');
		polyline.setAttribute('stroke-linejoin', 'round');
		polyline.setAttribute('stroke-linecap', 'round');
		svg.appendChild(polyline);

		// Hover-Punkt (unsichtbar bis Mausbewegung, siehe setupTooltip()).
		var hoverDot = document.createElementNS(SVG_NS, 'circle');
		hoverDot.setAttribute('id', 'fgr-ms-hover-dot');
		hoverDot.setAttribute('r', '4');
		hoverDot.setAttribute('fill', '#2271b1');
		hoverDot.setAttribute('stroke', '#fff');
		hoverDot.setAttribute('stroke-width', '1.5');
		hoverDot.style.display = 'none';
		svg.appendChild(hoverDot);

		// Skalen-Infos für den Tooltip-Handler merken (ein einziger, dauerhaft
		// gebundener Listener liest das bei jeder Mausbewegung neu aus).
		svg._fgrScale = { trend: trend, padLeft: padLeft, stepX: stepX, xAt: xAt, yAt: yAt };
	}

	function setupTooltip() {
		var svg = document.getElementById('fgr-ms-chart');
		var tooltip = document.getElementById('fgr-ms-tooltip');
		var wrap = document.getElementById('fgr-ms-chart-wrap-inner');
		if (!svg || !tooltip || !wrap) {
			return;
		}

		svg.addEventListener('mousemove', function (evt) {
			var scale = svg._fgrScale;
			if (!scale) {
				return;
			}

			var rect = svg.getBoundingClientRect();
			var scaleX = svg.viewBox.baseVal.width / rect.width;
			var mouseX = (evt.clientX - rect.left) * scaleX;

			var index = Math.round((mouseX - scale.padLeft) / scale.stepX);
			index = Math.max(0, Math.min(scale.trend.length - 1, index));
			var point = scale.trend[index];

			var dot = document.getElementById('fgr-ms-hover-dot');
			if (dot) {
				dot.setAttribute('cx', scale.xAt(index));
				dot.setAttribute('cy', scale.yAt(point.visits));
				dot.style.display = '';
			}

			var wrapRect = wrap.getBoundingClientRect();
			tooltip.hidden = false;
			tooltip.textContent = formatDate(point.date) + ': ' + point.visits + ' Besuche';
			tooltip.style.left = (evt.clientX - wrapRect.left + 12) + 'px';
			tooltip.style.top = (evt.clientY - wrapRect.top - 28) + 'px';
		});

		svg.addEventListener('mouseleave', function () {
			tooltip.hidden = true;
			var dot = document.getElementById('fgr-ms-hover-dot');
			if (dot) {
				dot.style.display = 'none';
			}
		});
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
