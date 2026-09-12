(function () {
    'use strict';

    const dashboard = window.NeuralDeskAdminDashboardCharts;

    if (!dashboard || typeof window.ApexCharts === 'undefined') {
        return;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const styles = getComputedStyle(document.documentElement);
    const textColor = styles.getPropertyValue('--nd-muted').trim() || '#78859a';
    const borderColor = styles.getPropertyValue('--nd-border').trim() || '#e7ebf1';

    function destroyChart(name) {
        if (window[name] && typeof window[name].destroy === 'function') {
            window[name].destroy();
        }
    }

    const revenueElement = document.getElementById('admin-revenue-chart');

    if (revenueElement && dashboard.revenue.hasData) {
        destroyChart('NeuralDeskRevenueChart');

        window.NeuralDeskRevenueChart = new window.ApexCharts(revenueElement, {
            chart: {
                type: 'area',
                height: 465,
                toolbar: { show: false },
                animations: { enabled: !reduceMotion },
                fontFamily: 'inherit',
                zoom: { enabled: false },
            },
            colors: ['#4f94ee', '#5d5fef', '#20c875'],
            series: dashboard.revenue.series,
            xaxis: {
                categories: dashboard.revenue.labels,
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { style: { colors: textColor, fontSize: '11px' } },
            },
            yaxis: {
                labels: { style: { colors: textColor, fontSize: '11px' } },
            },
            grid: {
                borderColor: borderColor,
                strokeDashArray: 0,
                padding: { left: 8, right: 12 },
            },
            dataLabels: { enabled: false },
            legend: { show: false },
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 0.2, opacityFrom: 0.25, opacityTo: 0.02, stops: [0, 90, 100] },
            },
            stroke: { curve: 'smooth', width: [3, 2, 2] },
            markers: { size: 0, hover: { size: 5 } },
            tooltip: { shared: true, intersect: false },
            responsive: [{ breakpoint: 768, options: { chart: { height: 300 } } }],
        });

        window.NeuralDeskRevenueChart.render();
    }

    const paymentHealthElement = document.getElementById('admin-payment-health-chart');

    if (paymentHealthElement && dashboard.paymentHealth.hasData) {
        destroyChart('NeuralDeskPaymentHealthChart');

        window.NeuralDeskPaymentHealthChart = new window.ApexCharts(paymentHealthElement, {
            chart: {
                type: 'donut',
                width: 220,
                height: 220,
                animations: { enabled: !reduceMotion },
                fontFamily: 'inherit',
            },
            series: [Number(dashboard.paymentHealth.paidPercentage || 0), Math.max(0, 100 - Number(dashboard.paymentHealth.paidPercentage || 0))],
            labels: ['Paid', 'Other'],
            colors: ['#20c875', borderColor],
            stroke: { width: 0 },
            dataLabels: { enabled: false },
            legend: { show: false },
            tooltip: { enabled: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '76%',
                        labels: {
                            show: true,
                            name: { show: true, offsetY: 22, color: textColor },
                            value: {
                                show: true,
                                offsetY: -12,
                                fontSize: '28px',
                                fontWeight: 800,
                                color: styles.getPropertyValue('--nd-navy').trim() || '#12182b',
                                formatter: function (value) { return Math.round(value) + '%'; },
                            },
                            total: {
                                show: true,
                                label: 'PAID',
                                color: textColor,
                                formatter: function () { return Math.round(Number(dashboard.paymentHealth.paidPercentage || 0)) + '%'; },
                            },
                        },
                    },
                },
            },
        });

        window.NeuralDeskPaymentHealthChart.render();
    }

    window.addEventListener('pagehide', function () {
        destroyChart('NeuralDeskRevenueChart');
        destroyChart('NeuralDeskPaymentHealthChart');
    }, { once: true });
}());
