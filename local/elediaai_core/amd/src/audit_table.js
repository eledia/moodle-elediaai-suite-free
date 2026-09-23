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
 * Gives every cell of a suite data table the name of its column.
 *
 * On a narrow screen the audit tables stop being tables and become one
 * stacked block per row -- otherwise ten columns cannot be shown without
 * pushing the page sideways, and a table you have to scroll horizontally is
 * a table you read wrong. A stacked row needs the column name beside each
 * value, which a table header alone cannot provide once the header is gone.
 *
 * The names are copied from the table's own `thead`, not listed here: the
 * audit reports show between six and ten columns depending on the site's
 * settings, so anything written down here would be wrong on most sites.
 *
 * Without JavaScript the tables stay tables and keep their header -- the
 * stacked layout is an improvement on narrow screens, not a precondition
 * for reading them.
 *
 * @module     local_elediaai_core/audit_table
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {eventTypes} from 'core_table/local/dynamic/events';

/** @var {String} Every data table of the suite that opts in. */
const TABLE = '.eai-datatable table';

/**
 * Copy the header texts onto the cells below them.
 *
 * @param {HTMLTableElement} table
 */
const label = (table) => {
    const heads = Array.from(table.querySelectorAll('thead th, thead td'))
        .map((cell) => cell.textContent.trim().replace(/\s+/g, ' '));
    if (!heads.length) {
        return;
    }

    table.querySelectorAll('tbody tr').forEach((row) => {
        Array.from(row.children).forEach((cell, index) => {
            const name = heads[index];
            // Eine Spalte ohne Ueberschrift ist meist die Auswahlspalte oder
            // eine reine Symbolspalte. Ihr einen leeren Namen zu geben waere
            // eine leere Zeile im gestapelten Block.
            if (name) {
                cell.setAttribute('data-eai-col', name);
            }
        });
    });
};

/**
 * Label every audit table on the page.
 */
const labelAll = () => document.querySelectorAll(TABLE).forEach(label);

/**
 * Entry point.
 */
export const init = () => {
    labelAll();

    // Reportbuilder tauscht den Tabellenkoerper per AJAX aus -- beim
    // Blaettern, Sortieren und Filtern. Danach stehen die Zellen ohne Namen
    // da, und der gestapelte Block auf dem Telefon waere eine Liste von
    // Werten ohne Bezeichnung.
    document.addEventListener(eventTypes.tableContentRefreshed, labelAll);
};
