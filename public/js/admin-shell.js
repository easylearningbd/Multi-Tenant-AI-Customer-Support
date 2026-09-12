(function () {
    'use strict';

    const desktopBreakpoint = 1200;

    function syncResponsiveSidebar() {
        document.body.setAttribute(
            'data-sidebar-size',
            window.innerWidth >= desktopBreakpoint ? 'default' : 'collapsed'
        );
    }

    syncResponsiveSidebar();
    window.addEventListener('resize', syncResponsiveSidebar);
}());
