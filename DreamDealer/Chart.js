// charts.js
// Requires a <canvas id="salesChart"></canvas> in your HTML

// Wrap in a function so it can be reused
function renderSalesChart(dates, totals) {
    if (!document.getElementById('salesChart')) return;

    const ctx = document.getElementById('salesChart').getContext('2d');

    // Destroy existing chart instance if exists
    if (window.salesChartInstance) {
        window.salesChartInstance.destroy();
    }

    window.salesChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Sales Last 7 Days',
                data: totals,
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                pointRadius: 5,
                pointBackgroundColor: 'rgba(75, 192, 192, 1)',
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: { size: 14 }
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            return '৳ ' + parseFloat(context.raw).toFixed(2);
                        }
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Date',
                        font: { size: 14, weight: 'bold' }
                    },
                    grid: { display: false }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Sales Amount (৳)',
                        font: { size: 14, weight: 'bold' }
                    },
                    beginAtZero: true,
                    grid: { drawBorder: false }
                }
            }
        }
    });
}

// Example usage: pass PHP arrays as JSON
// renderSalesChart(<?php echo json_encode($dates); ?>, <?php echo json_encode($totals); ?>);