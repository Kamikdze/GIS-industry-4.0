async function drawHistoryCharts(universityName) {
  const panel = document.getElementById('history-panel');
  panel.innerHTML = `<h3>Динамика: ${universityName}</h3>`;
  panel.style.display = 'block';

  const years = [2015, 2016, 2017, 2018, 2019, 2020, 2021, 2022, 2023];
  const datasets = await Promise.all(
    years.map(year =>
      fetch(`parsed_all_data/universities_parsed_data${year}.json`)
        .then(res => res.ok ? res.json() : [])
        .catch(() => [])
    )
  );

  const indicators = [
    "Средний балл ЕГЭ студентов, принятых по результатам ЕГЭ на обучение по очной форме по программам бакалавриата и специалитета с оплатой стоимости затрат на обучение физическими и юридическими лицами",
    "Общая численность студентов, обучающихся по программам бакалавриата, специалитета, магистратуры",
    "Доходы образовательной организации из всех источников в расчете на численность студентов (приведенный контингент)"
  ];

  const chartContainer = document.createElement('div');
  chartContainer.style.display = 'grid';
  chartContainer.style.gridTemplateColumns = '1fr 1fr';
  chartContainer.style.gap = '30px';

  for (const indicator of indicators) {
    const values = years.map((year, idx) => {
      const uni = datasets[idx].find(u => u.name === universityName);
      const all = (uni?.napde_data || []).concat(uni?.analis_dop_data || []);
      const entry = all.find(d => d.Описание === indicator);
      const val = entry?.Значение?.replace(',', '.').replace(/\s/g, '');
      const num = parseFloat(val);
      return isNaN(num) ? null : num;
    });

    const canvas = document.createElement('canvas');
    canvas.style.height = '250px';
    chartContainer.appendChild(canvas);

    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: years,
        datasets: [{
          label: indicator,
          data: values,
          backgroundColor: '#42a5f5'
        }]
      },
      options: {
        plugins: {
          legend: { display: false },
          title: { display: true, text: indicator }
        },
        scales: {
          y: { beginAtZero: false }
        }
      }
    });
  }

  panel.appendChild(chartContainer);
}
