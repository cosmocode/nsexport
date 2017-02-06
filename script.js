/**
 * check if the dl is ready
 *
 * @param key file key
 */
jQuery(function handleDOMReady() {
    'use strict';

    let $form = jQuery('form.plugin_nsexport__form');
    if ($form.length === 0) return;
    $form = $form.first();

    const INTERVAL_MAX = 10000;
    const INTERVAL_STEP = 1000;
    let intervalId = null;
    let interval = 500;

    window.nsexport_check = function nsexportCheck(key) {
        const url = DOKU_BASE + 'lib/exe/ajax.php';
        const data = {
            call: 'nsexport_check',
            key: key,
        };

        jQuery.post(url, data).done(function handleCheckResult(response) {
            clearInterval(intervalId);

            if (response === '1') {
                // download is ready - get it
                const $throb = jQuery('#plugin_nsexport__throbber');
                $throb.replaceWith(LANG.plugins.nsexport.done);
                window.location = DOKU_BASE + 'lib/plugins/nsexport/export.php?key=' + key;
                return false;
            }

            if (interval < INTERVAL_MAX) {
                interval += INTERVAL_STEP;
            }
            intervalId = setInterval(window.nsexport_check, interval, response);

            // download not ready - wait
            return false;
        });
    };

    function startExport() {
        const data = {
            call: 'nsexport_start',
            pages: [],
        };

        $form.find('[name="export[]"]:checked').each(function extractPageID(index, element) {
            data.pages.push(element.value);
        });

        const url = DOKU_BASE + 'lib/exe/ajax.php';

        jQuery.post(url, data).done(
            function packagingStarted(response) {
                if (response === '') {
                    return;
                }
                // start waiting for dl
                intervalId = setInterval(window.nsexport_check, interval, response);
            }
        );

        const $msg = jQuery('<div>').addClass('level1').html('<p>' + LANG.plugins.nsexport.loading
            + '<img id="plugin_nsexport__throbber" src="' + DOKU_BASE + 'lib/images/throbber.gif" alt="…"></p>');

        $form.replaceWith($msg);
    }

    if ($form.hasClass('plugin_nsexport__started')) {
        // Autostart
        startExport();
        return;
    }

    $form.submit(function handleFormSubmit(e) {
        $form.removeClass().addClass('plugin_nsexport__started');

        startExport();

        e.preventDefault();
        e.stopPropagation();
        return false;
    });
});
