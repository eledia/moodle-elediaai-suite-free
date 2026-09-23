// This file is part of Moodle - http://moodle.org/

/**
 * Instant toggles, per-section bulk actions, reset-to-default and filtering
 * for the per-course activity selection page.
 *
 * Optimistic UI: switches flip immediately, decisions are stored via the
 * plugin's external functions, and on failure the UI is rolled back and the
 * error surfaced. State changes are mirrored into an aria-live region.
 *
 * @module     local_elediaai_sources/activity_toggle
 * @copyright  2026 Christopher Reimann, eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/str'], function(Ajax, Notification, Str) {
    var SELECTORS = {
        region: '[data-region="elediaai-activities"]',
        toggle: 'input[data-region="toggle"]',
        status: '[data-region="status"]',
        row: '[data-region="row"]',
        name: '[data-region="name"]',
        excludedbadge: '[data-region="excludedbadge"]',
        reset: '[data-region="reset"]',
        bulk: '[data-region="bulk"]',
        filter: '[data-region="filter"]'
    };

    /**
     * Announce a message in the aria-live region.
     *
     * @param {Element} region The page region.
     * @param {String} message The message.
     */
    var announce = function(region, message) {
        var status = region.querySelector(SELECTORS.status);
        if (status) {
            status.textContent = message;
        }
    };

    /**
     * Reflect a row's stored decision in badge and reset affordance.
     *
     * @param {Element} row The activity row.
     * @param {Boolean} excluded Whether the activity is now explicitly excluded.
     * @param {Boolean} explicit Whether an explicit decision exists.
     */
    var updateRow = function(row, excluded, explicit) {
        var badge = row.querySelector(SELECTORS.excludedbadge);
        if (badge) {
            badge.hidden = !excluded;
            badge.classList.toggle('hidden', !excluded);
        }
        var reset = row.querySelector(SELECTORS.reset);
        if (reset) {
            reset.hidden = !explicit;
            reset.classList.toggle('hidden', !explicit);
        }
    };

    /**
     * Announce the per-activity result string.
     *
     * @param {Element} region The page region.
     * @param {String} key The string key.
     * @param {String} name The activity name.
     */
    var announceString = function(region, key, name) {
        Str.get_string(key, 'local_elediaai_sources', name).then(function(message) {
            announce(region, message);
            return null;
        }).catch(function() {
            return null;
        });
    };

    /**
     * Store one toggle change.
     *
     * @param {Element} region The page region.
     * @param {Element} toggle The changed checkbox.
     */
    var save = function(region, toggle) {
        var row = toggle.closest(SELECTORS.row);
        var nameel = row ? row.querySelector(SELECTORS.name) : null;
        var name = nameel ? nameel.textContent.trim() : '';
        var included = toggle.checked;

        toggle.disabled = true;

        Ajax.call([{
            methodname: 'local_elediaai_sources_set_activity_selection',
            args: {
                cmid: parseInt(toggle.getAttribute('data-cmid'), 10),
                state: included ? 'included' : 'excluded'
            }
        }])[0].then(function() {
            toggle.disabled = false;
            if (row) {
                updateRow(row, !included, true);
            }
            announceString(region, 'activities_saved', name);
            return null;
        }).catch(function(error) {
            // Roll the optimistic flip back before surfacing the error.
            toggle.checked = !included;
            toggle.disabled = false;
            announceString(region, 'activities_savefailed', name);
            Notification.exception(error);
        });
    };

    /**
     * Reset one activity to the site default.
     *
     * @param {Element} region The page region.
     * @param {Element} button The reset button.
     */
    var reset = function(region, button) {
        var row = button.closest(SELECTORS.row);
        var nameel = row ? row.querySelector(SELECTORS.name) : null;
        var name = nameel ? nameel.textContent.trim() : '';
        var modedefault = region.getAttribute('data-modedefault') === '1';

        button.disabled = true;

        Ajax.call([{
            methodname: 'local_elediaai_sources_set_activity_selection',
            args: {
                cmid: parseInt(button.getAttribute('data-cmid'), 10),
                state: 'default'
            }
        }])[0].then(function() {
            button.disabled = false;
            if (row) {
                var toggle = row.querySelector(SELECTORS.toggle);
                if (toggle) {
                    toggle.checked = modedefault;
                }
                updateRow(row, false, false);
            }
            announceString(region, 'activities_saved', name);
            return null;
        }).catch(function(error) {
            button.disabled = false;
            announceString(region, 'activities_savefailed', name);
            Notification.exception(error);
        });
    };

    /**
     * Apply one state to every supported activity of a section.
     *
     * @param {Element} region The page region.
     * @param {Element} button The bulk button.
     */
    var bulk = function(region, button) {
        var state = button.getAttribute('data-state');
        var cmids = button.getAttribute('data-cmids').split(',')
            .map(function(id) {
                return parseInt(id, 10);
            })
            .filter(function(id) {
                return !isNaN(id);
            });
        if (!cmids.length) {
            return;
        }

        button.disabled = true;

        Ajax.call([{
            methodname: 'local_elediaai_sources_set_activity_selection_bulk',
            args: {
                courseid: parseInt(region.getAttribute('data-courseid'), 10),
                cmids: cmids,
                state: state
            }
        }])[0].then(function() {
            button.disabled = false;
            cmids.forEach(function(cmid) {
                var row = region.querySelector(SELECTORS.row + '[data-cmid="' + cmid + '"]');
                if (!row) {
                    return;
                }
                var toggle = row.querySelector(SELECTORS.toggle);
                if (toggle) {
                    toggle.checked = state === 'included';
                }
                updateRow(row, state === 'excluded', true);
            });
            announceString(region, 'activities_bulk_saved', String(cmids.length));
            return null;
        }).catch(function(error) {
            button.disabled = false;
            Notification.exception(error);
        });
    };

    /**
     * Hide rows whose activity name does not match the filter.
     *
     * @param {Element} region The page region.
     * @param {String} needle The lowercased filter text.
     */
    var filter = function(region, needle) {
        region.querySelectorAll(SELECTORS.row).forEach(function(row) {
            var nameel = row.querySelector(SELECTORS.name);
            var haystack = nameel ? nameel.textContent.toLowerCase() : '';
            row.hidden = needle !== '' && haystack.indexOf(needle) === -1;
        });
    };

    return {
        /**
         * Wire up every control on the page.
         */
        init: function() {
            var region = document.querySelector(SELECTORS.region);
            if (!region || region.dataset.lesTogglesWired === '1') {
                return;
            }
            region.dataset.lesTogglesWired = '1';

            region.addEventListener('change', function(e) {
                var toggle = e.target.closest(SELECTORS.toggle);
                if (toggle && !toggle.disabled) {
                    save(region, toggle);
                }
            });

            region.addEventListener('click', function(e) {
                var resetbutton = e.target.closest(SELECTORS.reset);
                if (resetbutton && !resetbutton.disabled) {
                    reset(region, resetbutton);
                    return;
                }
                var bulkbutton = e.target.closest(SELECTORS.bulk);
                if (bulkbutton && !bulkbutton.disabled) {
                    bulk(region, bulkbutton);
                }
            });

            region.addEventListener('input', function(e) {
                var box = e.target.closest(SELECTORS.filter);
                if (box) {
                    filter(region, box.value.trim().toLowerCase());
                }
            });
        }
    };
});
