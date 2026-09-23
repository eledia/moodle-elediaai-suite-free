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
 * Filters the suite dashboard where it stands, without fetching the page again.
 *
 * The page works without this module: the form submits, the server narrows and
 * renders the same view. What the module adds is that the narrowing happens
 * while you type, and that the address bar keeps up, so a filtered view is
 * still something you can bookmark or send to a colleague.
 *
 * Every tile is in the document already — the page ships all of them and hides
 * what the current filter excludes — so widening the filter needs no round
 * trip either.
 *
 * @module     local_elediaai_core/feature_filter
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';

const SELECTORS = {
    form: '[data-region="feature-search-form"]',
    search: '[data-region="feature-search"]',
    submit: '[data-region="feature-search-submit"]',
    audience: '[data-region="feature-audience"]',
    card: '[data-feature-id]',
    section: '[data-region="feature-section"]',
    empty: '[data-region="feature-empty"]',
    count: '[data-region="feature-count"]',
};

/** @var {number} How long typing rests before the address bar is rewritten. */
const URL_QUIET_MS = 400;

/**
 * Whether one tile passes the current filter.
 *
 * The same rule as `registry::narrow()`: every word of the query must occur
 * somewhere in the tile's searchable text, and the audience must match when one
 * is chosen. Substring, not word-start — someone typing "übersetz" should find
 * "Übersetzung".
 *
 * @param {HTMLElement} card The tile.
 * @param {string[]} words The query, lowercased and split.
 * @param {string} audience The chosen audience, or the empty string.
 * @returns {boolean} Whether it stays.
 */
const matches = (card, words, audience) => {
    if (audience !== '' && card.dataset.audience !== audience) {
        return false;
    }
    const haystack = card.dataset.search || '';
    return words.every((word) => haystack.indexOf(word) !== -1);
};

/**
 * Split a query the way the server does.
 *
 * @param {string} query The raw input.
 * @returns {string[]} The words.
 */
const wordsOf = (query) => query.toLowerCase().trim().split(/\s+/).filter((word) => word !== '');

/**
 * The address this view would have if it had been requested from the server.
 *
 * Rewritten rather than pushed: typing is not a place you want to walk back
 * through one letter at a time with the browser's back button.
 *
 * @param {string} query The search text.
 * @param {string} audience The chosen audience.
 * @returns {void}
 */
const syncAddress = (query, audience) => {
    const url = new URL(window.location.href);
    url.searchParams.delete('q');
    url.searchParams.delete('audience');
    if (query.trim() !== '') {
        url.searchParams.set('q', query.trim());
    }
    if (audience !== '') {
        url.searchParams.set('audience', audience);
    }
    window.history.replaceState({}, '', url.toString());
};

/**
 * Show what matches, hide the rest, and say how many there are.
 *
 * @param {HTMLElement} root The page.
 * @param {string} query The search text.
 * @param {string} audience The chosen audience.
 * @returns {void}
 */
const apply = (root, query, audience) => {
    const words = wordsOf(query);
    let shown = 0;

    root.querySelectorAll(SELECTORS.card).forEach((card) => {
        const keep = matches(card, words, audience);
        card.hidden = !keep;
        if (keep) {
            shown++;
        }
    });

    // A section with nothing left in it folds up, heading and all.
    root.querySelectorAll(SELECTORS.section).forEach((section) => {
        section.hidden = section.querySelector(`${SELECTORS.card}:not([hidden])`) === null;
    });

    const empty = root.querySelector(SELECTORS.empty);
    if (empty) {
        empty.hidden = shown > 0;
    }

    root.querySelectorAll(SELECTORS.audience).forEach((link) => {
        const current = (link.dataset.audience || '') === audience;
        link.classList.toggle('lh-ai-suite-legend__item--current', current);
        if (current) {
            link.setAttribute('aria-current', 'true');
        } else {
            link.removeAttribute('aria-current');
        }
    });

    const counter = root.querySelector(SELECTORS.count);
    if (counter) {
        getString('dashboard_matchcount', 'local_elediaai_core', shown)
            .then((text) => {
                counter.textContent = text;
                return text;
            })
            .catch(() => {
                // A missing announcement is not worth breaking the filter over.
            });
    }
};

/**
 * Wire the controls up.
 *
 * @returns {void}
 */
export const init = () => {
    const form = document.querySelector(SELECTORS.form);
    const search = document.querySelector(SELECTORS.search);
    if (!form || !search) {
        return;
    }
    const root = document.body;
    let audience = new URL(window.location.href).searchParams.get('audience') || '';
    let timer = null;

    // From here the page filters itself, so the button has nothing left to do.
    const submit = form.querySelector(SELECTORS.submit);
    if (submit) {
        submit.remove();
    }

    // Enter would otherwise reload the page and land on the same view.
    form.addEventListener('submit', (event) => {
        event.preventDefault();
    });

    search.addEventListener('input', () => {
        apply(root, search.value, audience);
        window.clearTimeout(timer);
        timer = window.setTimeout(() => syncAddress(search.value, audience), URL_QUIET_MS);
    });

    root.querySelectorAll(SELECTORS.audience).forEach((link) => {
        link.addEventListener('click', (event) => {
            // Ctrl/cmd-click and middle-click still open the filtered view in a
            // new tab, which is why the links keep their real addresses.
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
                return;
            }
            event.preventDefault();
            audience = link.dataset.audience || '';
            apply(root, search.value, audience);
            syncAddress(search.value, audience);
        });
    });

    const showall = root.querySelector('[data-region="feature-showall"]');
    if (showall) {
        showall.addEventListener('click', (event) => {
            event.preventDefault();
            search.value = '';
            audience = '';
            apply(root, '', '');
            syncAddress('', '');
            search.focus();
        });
    }
};
