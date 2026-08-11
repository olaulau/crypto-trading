document.addEventListener('DOMContentLoaded', () => {
	
	// Points clés (hors série principale)
	const keyPoints = 
	[
		{
			x: new Date(2025, 11, 2, 12),
			y: 2000,
			label: 'Événement A'
		},
		{
			x: new Date(2025, 11, 3, 18),
			y: 3000,
			label: 'Événement B'
		}
	];

	
	// set up the chart
	chartCanvas = document.getElementById('chart');
	chart = new Chart (chartCanvas, {
		type: 'line',
		data: {
			datasets:
			[
				{
					label: 'Prix test',
					data: data,
					borderWidth: 2,

					pointRadius: 0, // ❌ pas de points
					pointHoverRadius: 0, // ❌ même au survol

					tension: 0.3, // ✅ lissage (0 → lignes droites)
					cubicInterpolationMode: 'monotone' // ✅ lissage propre (finance-friendly)
				},
				{
					label: 'Points clés',
					data: keyPoints,
					type: 'scatter', // important pour avoir seulement des points
					pointRadius: 6, // taille des points
					pointBackgroundColor: 'red',
					showLine: false, // pas de ligne
				}
			]
		},
		options: {
			animation: {
				duration: 200
			},
			scales: {
				x: {
					type: 'time',
					time: {
						unit: 'day', // jour, mois, année, etc.
						tooltipFormat: 'PP' // format du tooltip
					},
					ticks: {
						font: {
							size: 14, // taille en pixels (par défaut ~12)
							family: 'Arial, sans-serif',
							weight: 'normal'
						},
						autoSkip: true, // ajustement automatique
						maxRotation: 90,
						minRotation: 90
					}
				}
			},
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				tooltip: {
					enabled: true, // ✅ par défaut
					mode: 'nearest', // point le plus proche du curseur
					intersect: false, // montre tooltip même si pas exactement sur un point
					callbacks: {
						label: function (context) {
							if (context.dataset.label === 'Points clés') {
								return context.raw.label + ' : ' + context.parsed.y;
							} else {
								return context.parsed.y;
							}
						}
					}
				},
				zoom: {
					pan: {
						enabled: true,
						mode: 'x', // scroll horizontal
						threshold: 5
					},
					zoom: {
						wheel: {
							enabled: true,
						},
						pinch: {
							enabled: true
						},
						mode: 'x',
						onZoomStart: ({ chart, event, point }) => {
							statsMode = 'viewport';
							drawSelectionRect();
						},
						onZoomComplete: ({ chart }) => {
							updateStats(chart);
						}
					}
				}
			}
		}
	});
	
	
	
	
	// overlay
	const overlay = document.getElementById('overlay');
	const overlayCtx = overlay.getContext('2d');
	overlay.width = chartCanvas.clientWidth;
	overlay.height = chartCanvas.clientHeight;


	////// zoom & pan & select /////
	let startX = 0;
	let isZooming = false;
	let isPanning = false;
	let isSelecting = false;
	let selectionStart = null;
	let selectionRange = null;

	chartCanvas.addEventListener ('mousedown', e => {
		if (e.shiftKey) {
			isSelecting = true;
			selectionStart = {
				x: e.offsetX,
				y: e.offsetY
			};
			overlayCtx.clearRect(0, 0, overlay.width, overlay.height); /////////////
		}
		else {
			isPanning = true;
			startX = e.clientX;
		}
	});

	chartCanvas.addEventListener ('mousemove', e => {
		/* --- SÉLECTION --- */
		if (isSelecting) {
			// Position actuelle de la souris
			const currentX = e.offsetX;
			const currentY = e.offsetY;

			// Calcul des coordonnées du rectangle
			const x = Math.min(selectionStart.x, currentX);
			const y = Math.min(selectionStart.y, currentY);
			const w = Math.abs(currentX - selectionStart.x);
			const h = Math.abs(currentY - selectionStart.y);

			// Nettoyage de l'overlay avant de dessiner
			overlayCtx.clearRect(0, 0, overlay.width, overlay.height);

			// Style du rectangle
			overlayCtx.fillStyle = 'rgba(0,123,255,0.15)';
			overlayCtx.strokeStyle = 'rgba(0,123,255,0.8)';

			// Dessin du rectangle
			overlayCtx.fillRect(x, y, w, h);
			overlayCtx.strokeRect(x, y, w, h); /////////////////

			// On ne continue pas vers le pan
			return;
		}

		/* --- PAN --- */
		if (isPanning && !isZooming) {
			const deltaX = e.clientX - startX;
			startX = e.clientX;

			// Pan via l'instance chart.js
			chart.pan({ x: deltaX, y: 0 });

			statsMode = 'viewport';
			updateStats (chart);
			overlayCtx.clearRect(0, 0, overlay.width, overlay.height);
		}
	});

	chartCanvas.addEventListener ('mouseup', e => {
		if (isSelecting) {
			// Fin de la sélection
			const x1 = selectionStart.x;
			const y1 = selectionStart.y;
			const x2 = e.offsetX;
			const y2 = e.offsetY;

			selectionRange = {
				xMin: Math.min(x1, x2),
				yMin: Math.min(y1, y2),
				xMax: Math.max(x1, x2),
				yMax: Math.max(y1, y2)
			};
			statsMode = 'selection';
			isSelecting = false;
			drawSelectionRect ();
			updateStats (chart);
		}
		else {
			// fin pan
			goViewport ();
		}
		
		isPanning = false;
		isZooming = false;
	});

	chartCanvas.addEventListener('dblclick', () => {
		goViewport ();
	});
	
	
	
	///// stats & selection /////
	let statsMode = 'viewport'; // 'viewport' | 'selection'
	updateStats(chart);

	
	function getViewportRange (chart) {
		const scale = chart.scales.x;
		return {
			min: scale.min,
			max: scale.max
		};
	}
	
	function getSelectionRange (chart) {
		return {
			min: chart.scales.x.getValueForPixel(selectionRange.xMin),
			max: chart.scales.x.getValueForPixel(selectionRange.xMax),
		};
	}

	function computeStats (chart, range) {
		const data = chart.data.datasets[0].data;
		const slice = data.filter(p =>
			p.x >= range.min && p.x <= range.max
		);
		if (slice.length < 2) return null;

		const values = slice.map(p => p.y);
		const min = Math.min (...values);
		const max = Math.max (...values);
		const first = slice [0].y;
		const last = slice [slice.length - 1].y;
		return {
			min,
			max,
			amplitude: max - min,
			amplitudePct: ((max - min) / min) * 100,
			start: first,
			end: last,
			pct: ((last - first) / first) * 100,
			points: slice.length
		};
	}
	
	function updateStats (chart) {
		const range = (statsMode === 'selection') ?
			getSelectionRange (chart) :
			getViewportRange (chart);
		const stats = computeStats(chart, range);
		if (!stats) {
			goViewport ();
			return;
		}
		renderStats(stats, statsMode);
	}

	function renderStats (stats, mode) {
		document.getElementById('stats-mode').textContent = mode;
		document.getElementById('stats-points').textContent = stats.points;
		document.getElementById('stats-min').textContent = stats.min.toFixed(2);
		document.getElementById('stats-max').textContent = stats.max.toFixed(2);
		document.getElementById('stats-start').textContent = stats.start.toFixed(2);
		document.getElementById('stats-end').textContent = stats.end.toFixed(2);
		document.getElementById('stats-amp-pct').textContent = stats.amplitudePct.toFixed(2);

		const pctEl = document.getElementById('stats-pct');
		pctEl.textContent = stats.pct.toFixed(2);
		pctEl.style.color = stats.pct >= 0 ? 'green' : 'red';
	}

	function drawSelectionRect () {
		// effacer overlay
		overlayCtx.clearRect(0, 0, overlay.width, overlay.height);
		if (statsMode !== 'selection' || !selectionRange) return;
		
		const { xMin, xMax, yMin, yMax } = selectionRange;
		const w = xMax - xMin;
		const h = yMax - yMin;

		overlayCtx.fillStyle = 'rgba(0,123,255,0.15)';
		overlayCtx.strokeStyle = 'rgba(0,123,255,0.8)';
		overlayCtx.fillRect(xMin, yMin, w, h);
		overlayCtx.strokeRect(xMin, yMin, w, h);
	}
	
	function goViewport () {
		statsMode = 'viewport';
		drawSelectionRect();
		updateStats(chart);
	}
	
});
