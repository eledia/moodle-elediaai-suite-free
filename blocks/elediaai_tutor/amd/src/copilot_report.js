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
 * Teacher-Copilot section on the question analytics report.
 *
 * Wires the "generate analysis" button to the copilot external function and
 * renders the returned (server-sanitised) HTML into the result region.
 *
 * @module     block_elediaai_tutor/copilot_report
 * @copyright  2026 eLeDia GmbH, Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {getString} from 'core/str';

export default {
    /**
     * Initialise the copilot card.
     *
     * @param {number} courseid The course being analysed.
     */
    init: function(courseid) {
        const region = document.querySelector('[data-region="copilot"]');
        if (!region || region.dataset.initialised) {
            return;
        }
        region.dataset.initialised = '1';

        const button = region.querySelector('[data-action="copilot-generate"]');
        const status = region.querySelector('[data-region="copilot-status"]');
        const result = region.querySelector('[data-region="copilot-result"]');
        if (!button || !status || !result) {
            return;
        }

        button.addEventListener('click', async() => {
            button.disabled = true;
            result.hidden = true;
            status.textContent = await getString('copilot_working', 'block_elediaai_tutor');

            Ajax.call([{
                methodname: 'block_elediaai_tutor_generate_copilot_analysis',
                args: {courseid: courseid},
            }])[0].then((response) => {
                status.textContent = '';
                result.innerHTML = response.analysishtml;
                result.hidden = false;
                button.disabled = false;
                return null;
            }).catch(async(error) => {
                status.textContent = (error && error.message)
                    || await getString('copilot_failed', 'block_elediaai_tutor');
                button.disabled = false;
            });
        });
    },
};
