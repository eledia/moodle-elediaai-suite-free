// This file is part of Moodle - http://moodle.org/

/**
 * Wrap the LiteRAG Moodle admin settings form in the LernHive Plugin Shell.
 *
 * @module     local_literag/settings_shell
 * @copyright  2026 Christopher Reimann, eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    const isFormActionRow = function(node) {
        return Boolean(node.classList && (
            node.classList.contains('form-buttons') ||
            node.classList.contains('settingsformbuttons') ||
            (node.querySelector && node.querySelector('input[type=submit], button[type=submit]'))
        ));
    };

    const hideDuplicateHeadings = function(form, pluginTitle) {
        const region = document.getElementById('region-main') || form.parentNode;
        Array.prototype.slice.call(region.querySelectorAll('h1, h2')).forEach(function(heading) {
            if (!heading.closest('.lh-plugin-header, .elediaai-core-shell__header') &&
                    !heading.closest('#adminsettings')) {
                heading.hidden = true;
            }
        });
        Array.prototype.slice.call(form.querySelectorAll('h1, h2')).forEach(function(heading) {
            if (heading.textContent.trim() === pluginTitle) {
                heading.hidden = true;
            }
        });
    };

    const normalizeTitle = function(value) {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = value || '';
        return textarea.value.trim().replace(/\s+/g, ' ');
    };

    const headingMap = function(sectionCards) {
        const map = {};
        (sectionCards || []).forEach(function(card) {
            map[normalizeTitle(card.title)] = card.key;
        });
        return map;
    };

    const groupSections = function(form, sectionCards) {
        const fieldset = form.querySelector('fieldset');
        if (!fieldset || fieldset.dataset.lrSectionCards === '1') {
            return;
        }
        fieldset.dataset.lrSectionCards = '1';

        const sections = headingMap(sectionCards);
        let card = null;
        let seenHeading = false;
        Array.prototype.slice.call(fieldset.children).forEach(function(node) {
            if (node.matches && node.matches('h3.main')) {
                const key = sections[normalizeTitle(node.textContent)] || '';
                if (key) {
                    node.id = 'settings-' + key;
                }
                card = null;
                seenHeading = true;
                return;
            }
            if (isFormActionRow(node)) {
                card = null;
                return;
            }
            if (!seenHeading) {
                return;
            }
            if (!card) {
                card = document.createElement('section');
                card.className = 'lr-settings-section-card';
                fieldset.insertBefore(card, node);
            }
            card.appendChild(node);
        });
    };

    const scrollToHashTarget = function() {
        const id = (window.location.hash || '').replace('#', '');
        if (!id) {
            return;
        }
        const target = document.getElementById(id);
        if (target) {
            window.setTimeout(function() {
                target.scrollIntoView({block: 'start'});
            }, 0);
        }
    };

    const init = function(config) {
        const form = document.getElementById('adminsettings');
        if (!form || form.dataset.lrShellWrapped === '1') {
            return;
        }
        form.dataset.lrShellWrapped = '1';
        document.body.classList.add('path-local-literag', 'lr-admin-settings-shell-page');
        document.body.classList.remove('drawer-open-right', 'drawer-ease');
        document.body.classList.remove('lr-admin-settings-pending');
        document.body.classList.add('lh-plugin-shell-page', 'lr-admin-settings-ready');

        const page = document.getElementById('page');
        if (page) {
            page.classList.remove('show-drawer-right');
        }

        const shell = document.createElement('div');
        shell.className = 'lh-plugin-shell lr-admin-settings-shell';
        shell.innerHTML = config.headerHtml;

        const content = document.createElement('div');
        content.className = 'lh-plugin-content-area lr-admin-settings-content';
        form.parentNode.insertBefore(shell, form);
        shell.appendChild(content);
        content.appendChild(form);

        hideDuplicateHeadings(form, config.pluginTitle);
        groupSections(form, config.sectionCards);
        scrollToHashTarget();
    };

    return {
        init: init
    };
});
