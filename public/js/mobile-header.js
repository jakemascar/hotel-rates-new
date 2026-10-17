/* Narrow opt-in for the default 428px portrait iPhone viewport, not model detection. */
(function () {
    'use strict';
    if (!/iPhone/.test(navigator.userAgent)) { return; }
    var viewport = window.matchMedia('(width: 428px) and (orientation: portrait) and (resolution: 3dppx) and (pointer: coarse)');
    var selector = '.elementor-23001 .header_sticky .elementor-element-80fe68c';
    var pending = false;

    function update() {
        pending = false;
        document.querySelectorAll(selector).forEach(function (row) {
            var ready = row.querySelector('.yay-currency-custom-select__trigger') &&
                row.querySelector('.gtranslate_wrapper > a.gt_switcher-popup > img');
            row.classList.toggle('hr-iphone-header-controls', viewport.matches && !!ready);
        });
    }
    function schedule() {
        if (!pending) {
            pending = true;
            window.requestAnimationFrame(update);
        }
    }
    function start() {
        update();
        // Child-list changes cover delayed switchers and Elementor header replacement.
        // Class changes are deliberately not observed to avoid a feedback loop.
        new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
        if (viewport.addEventListener) { viewport.addEventListener('change', schedule); }
        else { viewport.addListener(schedule); }
        window.addEventListener('pageshow', schedule);
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); }
    else { start(); }
}());
