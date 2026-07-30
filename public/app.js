(function () {
  const cfg = window.HABIT_TRACKER;
  if (!cfg) return;

  // --- Checkbox saving: set up first and independently of charts, so a ---
  // --- charting problem can never stop your check-ins from saving.     ---
  let barChart = null;
  let lineChart = null;

  document.querySelectorAll('.habit-checkbox').forEach((box) => {
    box.addEventListener('change', async () => {
      const habitId = box.dataset.habitId;
      const date = box.dataset.date;
      const day = parseInt(date.slice(8, 10), 10);
      box.disabled = true;

      try {
        const res = await fetch('toggle.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ habit_id: habitId, date, csrf_token: cfg.csrfToken }),
        });
        const data = await res.json();

        if (!res.ok || !data.ok) {
          alert(data.error || 'Could not save that change. Please refresh and try again.');
          box.checked = !box.checked;
          return;
        }

        box.checked = data.completed;

        const row = box.closest('tr');
        if (row && data.rowStats) {
          row.querySelector('[data-role="checked"]').textContent = data.rowStats.checked;
          row.querySelector('[data-role="total"]').textContent = data.rowStats.total;
          row.querySelector('[data-role="percent"]').textContent = data.rowStats.percent.toFixed(1) + '%';
        }

        if (data.dayStats) {
          const completedCell = document.querySelector(`[data-role="day-completed"][data-date-index="${day}"]`);
          const percentCell = document.querySelector(`[data-role="day-percent"][data-date-index="${day}"]`);
          if (completedCell) completedCell.textContent = data.dayStats.completed;
          if (percentCell) percentCell.textContent = Math.round(data.dayStats.percent) + '%';

          if (barChart && lineChart) {
            const idx = day - 1;
            barChart.data.datasets[0].data[idx] = data.dayStats.completed;
            lineChart.data.datasets[0].data[idx] = data.dayStats.percent;
            barChart.update();
            lineChart.update();
          }
        }
      } catch (err) {
        alert('Network error — please check your connection and try again.');
        box.checked = !box.checked;
      } finally {
        box.disabled = false;
      }
    });
  });

  // --- Charts: wrapped in try/catch so any problem here (e.g. Chart.js ---
  // --- missing) never breaks the checkbox saving set up above.        ---
  try {
    const labels = Array.from({ length: cfg.daysInMonth }, (_, i) => String(i + 1));
    const completedData = cfg.columnStats.map((c) => c.completed);
    const percentData = cfg.columnStats.map((c) => c.percent);

    barChart = new Chart(document.getElementById('chart-completed'), {
      type: 'bar',
      data: { labels, datasets: [{ label: 'Tasks completed', data: completedData, backgroundColor: '#c9a8a6', borderRadius: 4, maxBarThickness: 22 }] },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, suggestedMax: cfg.totalHabits, ticks: { stepSize: 1 } } } },
    });

    lineChart = new Chart(document.getElementById('chart-percent'), {
      type: 'line',
      data: { labels, datasets: [{ label: '% completed', data: percentData, borderColor: '#5b7fdb', backgroundColor: 'rgba(91,127,219,0.12)', tension: 0.25, pointRadius: 3, fill: true }] },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100, ticks: { callback: (v) => v + '%' } } } },
    });
  } catch (err) {
    console.error('Charts failed to load:', err);
  }
})();