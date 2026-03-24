const chartData = window.salesChartData || { labels: [], values: [] };

const context = document.getElementById('salesChart');

if (context) {
    new Chart(context, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    label: 'Sales',
                    data: chartData.values,
                    backgroundColor: '#4c57eb',
                    borderRadius: 6,
                    barThickness: 20,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const value = context.parsed.y || 0;
                            return ' Tsh ' + new Intl.NumberFormat().format(value);
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#9aa2b8',
                        font: { family: 'Poppins', size: 11 },
                    },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#9aa2b8',
                        font: { family: 'Poppins', size: 11 },
                        callback: function (value) {
                            return value.toLocaleString();
                        },
                    },
                    grid: {
                        color: '#edf0f7',
                    },
                    border: { display: false },
                },
            },
        },
    });
}
