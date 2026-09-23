// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Moves the admin-only eLeDia.ai Tutor launcher into the site navbar.
 *
 * @module     block_elediaai_tutor/admin_launcher
 * @copyright  2026 eLeDia GmbH, Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    /**
     * Ensure the shared stylesheet is present even when this hook runs after
     * Moodle has already printed the document head.
     */
    const ensureStylesheet = () => {
        // Cache-bust on the theme revision, the same way shell.php does for the
        // server-side include. This link is appended to the head *after* the
        // theme's aggregated stylesheet, so it wins the cascade on equal
        // specificity -- and the file is served with only Last-Modified/ETag,
        // no Cache-Control. A browser is therefore free to keep a heuristically
        // cached copy, and that stale copy then overrides the up-to-date rules
        // it was aggregated into. The symptom is a page that is half new: colours
        // from the theme CSS, layout from a stylesheet weeks out of date, and a
        // server that reports everything as current.
        const rev = (window.M && M.cfg && M.cfg.themerev) ? M.cfg.themerev : '';
        const base = M.cfg.wwwroot + '/blocks/elediaai_tutor/styles.css';
        const href = rev ? base + '?rev=' + rev : base;
        // Match on the path, not the full URL: without this a page that already
        // carries the server-side include (with its own rev) would get a second
        // copy of the same file.
        if (document.querySelector('link[href^="' + base + '"]')) {
            return;
        }
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    };

    /**
     * Initialise the launcher placement.
     */
    const init = () => {
        window.setTimeout(() => {
            ensureStylesheet();
            const host = document.getElementById('eat-admin-launcher-host');
            if (!host) {
                return;
            }

            const aiHost = document.getElementById('lh-ai-launcher-host');
            if (aiHost && aiHost.parentNode) {
                aiHost.parentNode.insertBefore(host, aiHost.nextSibling);
            } else {
                // Dock before the whole .usermenu-container, never inside it: a
                // foreign node in the core user-menu region (before .usermenu)
                // leaves the user-menu toggle unresponsive on click (SUI-5).
                const usermenu = document.querySelector('.usermenu');
                const usermenucontainer = usermenu ? usermenu.closest('.usermenu-container') : null;
                const anchor = usermenucontainer || usermenu;
                if (anchor && anchor.parentNode) {
                    anchor.parentNode.insertBefore(host, anchor);
                } else {
                    const navbar = document.querySelector('.navbar .container-fluid, .navbar .container, .navbar');
                    if (navbar) {
                        navbar.appendChild(host);
                    }
                }
            }
            host.hidden = false;
        }, 0);
    };

    return {init};
});
