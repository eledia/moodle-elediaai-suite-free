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
 * Fills in what only the browser knows: the resolved value of every design
 * token and the stylesheet it came from.
 *
 * The server can say what is written in `styles.css`. It cannot say what won
 * once the theme, ten plugin stylesheets and Moodle's own aggregation have all
 * had their turn - and that is the question this page exists to answer.
 *
 * @module     local_elediaai_core/developer
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';

/**
 * Every custom-property declaration in the page, in cascade order.
 *
 * @returns {Array<{sheet: string, name: string, value: string}>}
 */
const declarations = () => {
    const found = [];

    const walk = (rules, sheetname) => {
        for (const rule of rules) {
            // Erst die Regel selbst, dann ihre Kinder - und beides, nicht
            // eines statt des anderen: seit CSS-Nesting traegt *jede*
            // CSSStyleRule eine (meist leere) cssRules-Liste. Wer daran
            // erkennen will, ob eine Regel ein Gruppenblock ist, ueberspringt
            // damit jede gewoehnliche Regel und findet gar nichts.
            if (rule.style && rule.selectorText && rule.selectorText.indexOf(':root') !== -1) {
                for (const name of rule.style) {
                    if (name.startsWith('--')) {
                        found.push({
                            sheet: sheetname,
                            name: name,
                            value: rule.style.getPropertyValue(name).trim(),
                            // Die Suite kennt genau zwei Staerken: :root und
                            // das :root:root des Themes, das die zehn spaeter
                            // geladenen Plugin-Stylesheets ueberstimmen muss.
                            // Sie zu zaehlen ist naeher an der Wahrheit als
                            // die blosse Reihenfolge.
                            weight: (rule.selectorText.match(/:root/g) || []).length,
                        });
                    }
                }
            }
            if (rule.cssRules && rule.cssRules.length) {
                // @media, @supports und verschachtelte Regeln gehoeren zum
                // selben Stylesheet und behalten dessen Platz in der Kaskade.
                walk(rule.cssRules, sheetname);
            }
        }
    };

    for (const sheet of document.styleSheets) {
        let rules;
        try {
            rules = sheet.cssRules;
        } catch (e) {
            // A stylesheet from another origin refuses to be read. Nothing to
            // do about it, and nothing lost: ours are all same-origin.
            continue;
        }
        walk(rules, sheet.href || 'inline');
    }

    return found;
};

/**
 * A short, readable name for the stylesheet a value came from.
 *
 * @param {string} href
 * @param {object} labels Translated labels.
 * @returns {string}
 */
const nameSheet = (href, labels) => {
    if (href.indexOf('/local/elediaai_core/styles.css') !== -1) {
        return labels.core;
    }
    if (href.indexOf('/theme/') !== -1) {
        return labels.theme;
    }
    if (href === 'inline') {
        return 'inline';
    }
    return href.split('/').slice(-2).join('/').split('?')[0];
};

export const init = async() => {
    const root = document.documentElement;
    const computed = getComputedStyle(root);
    const labels = {
        core: await getString('developer_origin_core', 'local_elediaai_core'),
        theme: await getString('developer_origin_theme', 'local_elediaai_core'),
        unknown: await getString('developer_origin_unknown', 'local_elediaai_core'),
    };
    const all = declarations();

    for (const cell of document.querySelectorAll('[data-eai-token]')) {
        const token = cell.dataset.eaiToken;
        const value = computed.getPropertyValue(token).trim();

        if (cell.classList.contains('lh-dev__measure')) {
            // Für die Skalenproben ist nicht der Token-Wert die Antwort,
            // sondern was er in diesem Browser an Pixeln ergibt.
            const sample = cell.closest('.lh-dev__sample');
            const text = sample && sample.querySelector('.lh-dev__sampletext');
            cell.textContent = text ? value + ' = ' + getComputedStyle(text).fontSize : value;
            continue;
        }
        cell.textContent = value || '—';
    }

    // Ein Farbwert sagt einem Menschen wenig, ein Farbfleck alles. Welche
    // Token Farben sind, entscheidet der aufgeloeste Wert und keine Liste:
    // ein abgeleitetes Token steht als "var(--x)" in der Datei und ist
    // trotzdem eine Farbe.
    for (const swatch of document.querySelectorAll('[data-eai-swatch]')) {
        const value = computed.getPropertyValue(swatch.dataset.eaiSwatch).trim();
        if (/^(#|rgb|hsl)/i.test(value)) {
            swatch.style.background = value;
            swatch.hidden = false;
        }
    }

    for (const cell of document.querySelectorAll('[data-eai-origin]')) {
        const token = cell.dataset.eaiOrigin;
        const value = computed.getPropertyValue(token).trim();

        // Gewonnen hat die staerkste Deklaration, bei Gleichstand die
        // spaeteste. Der Wert allein taugt nicht als Kennzeichen: ein Token,
        // das auf ein anderes zeigt, steht als "var(--x)" in der Datei und
        // als aufgeloester Wert im Browser -- die beiden zu vergleichen
        // erklaerte jedes abgeleitete Token fuer unauffindbar.
        const candidates = all.filter((d) => d.name === token);
        let winner = null;
        candidates.forEach((d, i) => {
            if (!winner || d.weight > winner.weight || (d.weight === winner.weight && i > winner.i)) {
                winner = {...d, i: i};
            }
        });

        if (!winner) {
            cell.textContent = labels.unknown;
        } else if (winner.value !== value) {
            // Steht dort "var(--x)", ist die Herkunft die Datei und der Wert
            // kommt von woanders. Beides zu zeigen ist ehrlicher als eines.
            cell.textContent = nameSheet(winner.sheet, labels) + ' (' + winner.value + ')';
        } else {
            cell.textContent = nameSheet(winner.sheet, labels);
        }
        cell.classList.add('lh-dev__origin');
    }
};
