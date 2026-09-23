// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Send this block's "Configure" action to the Plugin Shell instead of Moodle's
 * block-config modal. In Moodle 4.4+ the block "Configure" control opens a
 * dynamic-form modal (core_block/edit, a bubble-phase click listener on
 * `[data-action="editblock"][data-blockform]`). We rewrite our own controls into
 * plain links to the shell and, for controls inserted later via AJAX, intercept the
 * click in the capture phase (which runs before core's listener) and navigate there.
 *
 * @module     block_elediaai_tutor/configure_redirect
 * @copyright  2026 eLeDia GmbH, Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    const SELECTOR = '[data-action="editblock"][data-blockform]';

    /**
     * Whether an edit control belongs to the eLeDia.ai Tutor block.
     *
     * @param {HTMLElement} el The control (or null).
     * @return {Boolean}
     */
    const isOurControl = function(el) {
        return Boolean(el) && (el.getAttribute('data-blockform') || '').indexOf('elediaai_tutor') !== -1;
    };

    /**
     * The per-instance shell URL for a given control. Reflects the specific block
     * (Settings / Tutor / Preview); admins reach the site-wide settings via a link
     * inside that shell.
     *
     * @param {Object} config The init config.
     * @param {String} blockid The block instance id.
     * @return {String|null}
     */
    const targetFor = function(config, blockid) {
        if (!blockid) {
            return null;
        }
        return config.instanceUrl.replace('__ID__', encodeURIComponent(blockid));
    };

    /**
     * Initialise the redirect helper.
     *
     * @param {Object} config instanceUrl (with an __ID__ placeholder for the block id).
     */
    const init = function(config) {
        config = config || {};

        // Rewrite controls already in the DOM into plain links to the shell (so normal,
        // middle and ctrl-click all behave) and drop the modal trigger attribute.
        Array.prototype.forEach.call(document.querySelectorAll(SELECTOR), function(el) {
            if (!isOurControl(el)) {
                return;
            }
            const url = targetFor(config, el.getAttribute('data-blockid'));
            if (!url) {
                return;
            }
            el.removeAttribute('data-action');
            el.setAttribute('href', url);
        });

        // Controls can be (re)inserted via AJAX (block drawer, toggling edit mode). A
        // capture-phase listener runs before core_block/edit's bubble listener, so we
        // navigate to the shell instead of letting the modal open.
        document.addEventListener('click', function(event) {
            const el = event.target.closest ? event.target.closest(SELECTOR) : null;
            if (!isOurControl(el)) {
                return;
            }
            const url = targetFor(config, el.getAttribute('data-blockid'));
            if (!url) {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            window.location.assign(url);
        }, true);
    };

    return {
        init: init
    };
});
