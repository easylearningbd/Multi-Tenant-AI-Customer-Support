(function () {
    'use strict';

    if (document.documentElement.dataset.subscriberDashboardInitialized === 'true') return;
    document.documentElement.dataset.subscriberDashboardInitialized = 'true';

    const body = document.body;
    const sidebar = document.getElementById('subscriber-sidebar');
    const toggles = document.querySelectorAll('[data-subscriber-sidebar-toggle]');
    const closeButtons = document.querySelectorAll('[data-subscriber-sidebar-close]');
    const desktopBreakpoint = 1200;

    function isDesktop() {
        return window.innerWidth >= desktopBreakpoint;
    }

    function setExpanded(expanded) {
        toggles.forEach(function (toggle) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    }

    function closeMobileSidebar() {
        body.classList.remove('nd-sub-sidebar-open');
        setExpanded(false);
    }

    toggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            if (isDesktop()) {
                body.classList.toggle('nd-sub-sidebar-collapsed');
                setExpanded(!body.classList.contains('nd-sub-sidebar-collapsed'));
            } else {
                body.classList.toggle('nd-sub-sidebar-open');
                setExpanded(body.classList.contains('nd-sub-sidebar-open'));
            }
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', closeMobileSidebar);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && body.classList.contains('nd-sub-sidebar-open')) {
            closeMobileSidebar();
            toggles[0]?.focus();
        }
    });

    sidebar?.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (!isDesktop()) closeMobileSidebar();
        });
    });

    window.addEventListener('resize', function () {
        if (isDesktop()) closeMobileSidebar();
    });

    const chartElement = document.getElementById('subscriber-ai-answers-chart');
    const chartDataElement = document.getElementById('subscriber-dashboard-chart-data');

    if (!chartElement || !chartDataElement || typeof window.ApexCharts === 'undefined') return;

    let chartData;
    try {
        chartData = JSON.parse(chartDataElement.textContent || '{}');
    } catch (_error) {
        chartElement.textContent = 'Chart data is unavailable.';
        return;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const chart = new window.ApexCharts(chartElement, {
        chart: { type: 'bar', height: 255, toolbar: { show: false }, animations: { enabled: !reduceMotion }, fontFamily: 'inherit' },
        series: [{ name: 'AI answers', data: chartData.values || [] }],
        colors: ['#18c3ee'],
        fill: { type: 'gradient', gradient: { type: 'vertical', shadeIntensity: 0, gradientToColors: ['#7465ec'], inverseColors: false, opacityFrom: 1, opacityTo: 1, stops: [0, 100] } },
        plotOptions: { bar: { borderRadius: 5, borderRadiusApplication: 'end', columnWidth: '54%' } },
        dataLabels: { enabled: false },
        grid: { borderColor: '#edf0f4', strokeDashArray: 4, xaxis: { lines: { show: false } }, padding: { left: 4, right: 4 } },
        xaxis: { categories: chartData.labels || [], axisBorder: { show: false }, axisTicks: { show: false }, labels: { rotate: 0, hideOverlappingLabels: true, style: { colors: '#93a0b3', fontSize: '10px', fontWeight: 600 } } },
        yaxis: { min: 0, forceNiceScale: true, labels: { show: false } },
        tooltip: { y: { formatter: function (value) { return Math.round(value) + ' AI answers'; } } },
        legend: { show: false }
    });

    chart.render();
}());
