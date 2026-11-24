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
 * Refreshes the processing status on the activity page without a reload.
 *
 * The page renders what the database knows. When documents are still being
 * processed, this asks the server once right after loading, and again when
 * the "Refresh status" button is pressed; there is no polling. The server
 * throttles per course, so repeated clicks or many open tabs cost the
 * destination nothing. Rows and head are re-rendered from the same partials
 * the page uses; the result is announced once through the page's live region,
 * and the focus stays where it was.
 *
 * @module     local_elediaai_sources/status_refresh
 * @copyright  2026 Christopher Reimann, eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/str', 'core/templates'], function(Ajax, Notification, Str, Templates) {
    var SELECTORS = {
        region: '[data-region="elediaai-activities"]',
        status: '[data-region="status"]',
        summary: '[data-region="statussummary"]',
        activity: '[data-region="activitystatus"]',
        button: '[data-action="refresh-status"]'
    };

    /**
     * Announce a string in the page's aria-live region.
     *
     * @param {Element} region The page region.
     * @param {String} key The string key.
     * @param {*} param The string parameter.
     */
    var announce = function(region, key, param) {
        Str.get_string(key, 'local_elediaai_sources', param).then(function(message) {
            var live = region.querySelector(SELECTORS.status);
            if (live) {
                live.textContent = message;
            }
            return null;
        }).catch(function() {
            return null;
        });
    };

    /**
     * Swap a node's content for a freshly rendered template.
     *
     * @param {Element} node The node.
     * @param {String} template The template name.
     * @param {Object} context The template context.
     * @return {Promise}
     */
    var rerender = function(node, template, context) {
        return Templates.renderForPromise(template, context).then(function(rendered) {
            Templates.replaceNodeContents(node, rendered.html, rendered.js);
            return null;
        });
    };

    /**
     * Put the returned state into the head and the rows.
     *
     * @param {Element} region The page region.
     * @param {Object} result The answer of the external function.
     * @return {Promise}
     */
    var render = function(region, result) {
        var promises = [];
        var summary = region.querySelector(SELECTORS.summary);
        if (summary) {
            promises.push(rerender(summary, 'local_elediaai_sources/status_summary', result));
        }
        result.activities.forEach(function(activity) {
            var node = region.querySelector(SELECTORS.activity + '[data-cmid="' + activity.cmid + '"]');
            if (node) {
                promises.push(rerender(node, 'local_elediaai_sources/activity_status', activity));
            }
        });
        return Promise.all(promises);
    };

    /**
     * Say what the refresh achieved, once.
     *
     * @param {Element} region The page region.
     * @param {Object} result The answer of the external function.
     */
    var report = function(region, result) {
        if (result.throttled) {
            announce(region, 'activities_refresh_throttled', Math.max(1, result.retryin));
        } else if (result.unavailable) {
            announce(region, 'activities_refresh_unavailable');
        } else {
            announce(region, 'activities_refresh_done');
        }
    };

    /**
     * Hide the button once nothing is in flight, without losing the focus.
     *
     * @param {Element} region The page region.
     * @param {Element|null} button The refresh button.
     * @param {Object} result The answer of the external function.
     */
    var settleButton = function(region, button, result) {
        if (!button) {
            return;
        }
        button.disabled = false;
        if (result.hasinflight) {
            return;
        }
        var hadfocus = document.activeElement === button;
        button.hidden = true;
        button.classList.add('hidden');
        if (hadfocus) {
            var summary = region.querySelector(SELECTORS.summary);
            if (summary) {
                summary.focus();
            }
        }
    };

    /**
     * Ask the server for the current state of the course.
     *
     * @param {Element} region The page region.
     * @param {Number} courseid The course id.
     * @param {Element|null} button The refresh button.
     */
    var refresh = function(region, courseid, button) {
        if (region.dataset.lesStatusRefreshing === '1') {
            return;
        }
        region.dataset.lesStatusRefreshing = '1';
        var summary = region.querySelector(SELECTORS.summary);
        if (summary) {
            summary.setAttribute('aria-busy', 'true');
        }
        if (button) {
            button.disabled = true;
        }
        announce(region, 'activities_refresh_running');

        var done = function() {
            region.dataset.lesStatusRefreshing = '0';
            if (summary) {
                summary.setAttribute('aria-busy', 'false');
            }
        };

        Ajax.call([{
            methodname: 'local_elediaai_sources_refresh_course_status',
            args: {courseid: courseid}
        }])[0].then(function(result) {
            return render(region, result).then(function() {
                report(region, result);
                settleButton(region, button, result);
                done();
                return null;
            });
        }).catch(function(error) {
            if (button) {
                button.disabled = false;
            }
            done();
            Notification.exception(error);
        });
    };

    return {
        /**
         * Wire the button and refresh once if documents are in flight.
         *
         * @param {Number} courseid The course id.
         * @param {Boolean} hasinflight Whether the page shows documents in flight.
         */
        init: function(courseid, hasinflight) {
            var region = document.querySelector(SELECTORS.region);
            if (!region || region.dataset.lesStatusWired === '1') {
                return;
            }
            region.dataset.lesStatusWired = '1';

            var button = region.querySelector(SELECTORS.button);
            if (button) {
                button.addEventListener('click', function() {
                    if (!button.disabled) {
                        refresh(region, courseid, button);
                    }
                });
            }

            if (hasinflight) {
                refresh(region, courseid, button);
            }
        }
    };
});
