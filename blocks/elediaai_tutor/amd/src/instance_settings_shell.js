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
 * Wrap the per-instance tutor moodleform in the LernHive settings hub (cards),
 * mirroring the admin settings experience. Each form header group (a fieldset)
 * is mapped to a major section card (Design / Conversation / Technical); picking
 * a card reveals that section's fieldsets and lists their legends as topics.
 *
 * Degrades gracefully: if the form or sections are not found it leaves the plain
 * moodleform untouched, so the page is never broken by this enhancement.
 *
 * @module     block_elediaai_tutor/instance_settings_shell
 * @copyright  2026 eLeDia GmbH, Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['block_elediaai_tutor/icons'], function(Icons) {
    const GROUP_RE = /^id_(quicksettings|insgroup_)/;

    const icon = function(name) {
        return Icons.create(name || 'cog');
    };

    const hubCard = function(card) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'eat-settings-hub-card';
        button.dataset.eatTarget = card.key;
        if (card.body) {
            button.title = card.body;
        }
        button.setAttribute('aria-label', card.title + (card.body ? '. ' + card.body : ''));

        const iconwrap = document.createElement('span');
        iconwrap.className = 'eat-settings-hub-card__icon';
        iconwrap.appendChild(icon(card.icon));

        const text = document.createElement('span');
        text.className = 'eat-settings-hub-card__text';
        const title = document.createElement('span');
        title.className = 'eat-settings-hub-card__title';
        title.textContent = card.title;
        text.appendChild(title);

        button.appendChild(iconwrap);
        button.appendChild(text);
        return button;
    };

    /**
     * Resolve the major section key for one form fieldset.
     *
     * @param {HTMLElement} fieldset The form group fieldset.
     * @param {Object} groupMajors Map of header name => major key.
     * @param {String} fallback Default major when unmapped.
     * @return {String}
     */
    const majorFor = function(fieldset, groupMajors, fallback) {
        const name = (fieldset.id || '').replace(/^id_/, '');
        return groupMajors[name] || fallback;
    };

    const init = function(config) {
        config = config || {};
        const cards = config.sectionCards || [];
        const groupMajors = config.groupMajors || {};
        if (!cards.length) {
            return;
        }

        const shell = document.querySelector('.eat-instance-shell');
        // Exclude the live preview's own composer <form> (in the floating panel) so we
        // only ever enhance the actual settings moodleform.
        const form = shell ? shell.querySelector('form:not(.elediaai-chat-composer)') : null;
        if (!form) {
            return;
        }

        // Only the named header groups are managed; action buttons and anything
        // else stay visible at all times (so Save is always reachable).
        const fieldsets = Array.prototype.slice.call(form.querySelectorAll('fieldset'))
            .filter(function(fs) {
                return GROUP_RE.test(fs.id || '');
            });
        if (!fieldsets.length) {
            return;
        }

        const fallbackMajor = cards[cards.length - 1].key;
        const present = {};
        fieldsets.forEach(function(fs) {
            const major = majorFor(fs, groupMajors, fallbackMajor);
            fs.dataset.eatMajor = major;
            present[major] = true;
        });
        const visiblecards = cards.filter(function(c) {
            return present[c.key];
        });
        if (visiblecards.length < 2) {
            // Only one section present — a hub adds no value; leave the form as is.
            return;
        }

        // Turn each managed section into a bordered card (matching the admin
        // .eat-settings-group surface) instead of a Moodle collapsible.
        fieldsets.forEach(function(fs) {
            fs.classList.add('eat-settings-group');
        });

        // Tag the whole Save/Cancel button group (moodleform renders it as a single
        // group wrapper that holds BOTH buttons in nested rows) so CSS can float it as
        // an always-visible action bar. Targeting the group — not an inner button row —
        // keeps Save and Cancel together.
        const actions = form.querySelector('[data-groupname="buttonar"], #fgroup_id_buttonar');
        if (actions) {
            actions.classList.add('eat-instance-actions');
        }

        // The Save/Cancel row needs no special handling: moodleform's
        // add_action_buttons() calls closeHeaderBefore('buttonar'), so it is
        // rendered after (outside) the header fieldsets and is never one of the
        // managed sections we toggle — it stays visible in every area.

        const hub = document.createElement('section');
        hub.className = 'eat-settings-hub eat-instance-hub';

        const head = document.createElement('div');
        head.className = 'eat-settings-hub__head';
        if (config.hubTitle) {
            const h = document.createElement('h2');
            h.textContent = config.hubTitle;
            head.appendChild(h);
        }
        if (config.hubDesc) {
            const p = document.createElement('p');
            p.textContent = config.hubDesc;
            head.appendChild(p);
        }

        const grid = document.createElement('div');
        grid.className = 'eat-settings-hub__grid';
        visiblecards.forEach(function(c) {
            grid.appendChild(hubCard(c));
        });

        const topics = document.createElement('nav');
        topics.className = 'eat-settings-topics';
        if (config.topicsLabel) {
            topics.setAttribute('aria-label', config.topicsLabel);
        }

        hub.appendChild(head);
        hub.appendChild(grid);
        hub.appendChild(topics);
        form.insertBefore(hub, form.firstChild);

        const setActive = function(key) {
            grid.querySelectorAll('.eat-settings-hub-card').forEach(function(card) {
                const on = card.dataset.eatTarget === key;
                card.classList.toggle('eat-settings-hub-card--active', on);
                if (on) {
                    card.setAttribute('aria-current', 'page');
                } else {
                    card.removeAttribute('aria-current');
                }
            });

            fieldsets.forEach(function(fs) {
                fs.hidden = fs.dataset.eatMajor !== key;
            });

            topics.innerHTML = '';
            fieldsets.filter(function(fs) {
                return fs.dataset.eatMajor === key;
            }).forEach(function(fs) {
                const legend = fs.querySelector('legend');
                const label = legend ? legend.textContent.trim() : '';
                if (!label) {
                    return;
                }
                const topic = document.createElement('button');
                topic.type = 'button';
                topic.className = 'eat-settings-topic';
                const span = document.createElement('span');
                span.className = 'eat-settings-topic__title';
                span.textContent = label;
                topic.appendChild(span);
                topic.addEventListener('click', function() {
                    fs.scrollIntoView({behavior: 'smooth', block: 'start'});
                });
                topics.appendChild(topic);
            });
            topics.hidden = topics.children.length === 0;
        };

        grid.addEventListener('click', function(event) {
            const card = event.target.closest('.eat-settings-hub-card');
            if (card) {
                setActive(card.dataset.eatTarget);
            }
        });

        setActive(visiblecards[0].key);
    };

    return {
        init: init
    };
});
