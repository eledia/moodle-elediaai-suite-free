// This file is part of Moodle - http://moodle.org/

/**
 * Wrap the Moodle admin settings form in the plugin shell.
 *
 * @module     block_elediaai_tutor/settings_shell
 * @copyright  2026 eLeDia GmbH, Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['block_elediaai_tutor/icons'], function(Icons) {
    const icon = function(name) {
        return Icons.create(name);
    };

    const isFormActionRow = function(node) {
        if (!node.classList) {
            return false;
        }
        return node.classList.contains('form-buttons') ||
            node.classList.contains('settingsformbuttons') ||
            Boolean(node.querySelector && node.querySelector('input[type=submit], button[type=submit]'));
    };

    const hideDuplicateHeadings = function(form, pluginTitle) {
        const region = document.getElementById('region-main') || form.parentNode;
        Array.prototype.slice.call(region.querySelectorAll('h1, h2')).forEach(function(heading) {
            if (!heading.closest('.lh-plugin-header') &&
                    !heading.closest('.eat-settings-hub') &&
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

    const annotateSettings = function(fieldset, majorTitles) {
        let currentMajor = '';
        let currentTopic = '';
        let lastHeading = null;

        Array.prototype.slice.call(fieldset.children).forEach(function(node) {
            if (isFormActionRow(node)) {
                return;
            }
            if (node.matches && node.matches('h3.main')) {
                const title = node.textContent.trim();
                lastHeading = node;
                if (majorTitles[title]) {
                    currentMajor = majorTitles[title];
                    currentTopic = '';
                    node.dataset.eatMajorHeading = '1';
                } else {
                    currentTopic = title;
                    node.dataset.eatTopicHeading = '1';
                }
            }
            if (node.classList && node.classList.contains('formsettingheading')) {
                const helpText = node.textContent.trim().replace(/\s+/g, ' ');
                if (helpText && lastHeading) {
                    lastHeading.title = helpText;
                    lastHeading.setAttribute('aria-label', lastHeading.textContent.trim() + '. ' + helpText);
                }
                node.dataset.eatInfoHidden = '1';
            }
            if (currentMajor) {
                node.dataset.eatMajor = currentMajor;
                if (currentTopic) {
                    node.dataset.eatTopic = currentTopic;
                }
            }
        });
    };

    const createBackNav = function(hub, label) {
        const backnav = document.createElement('div');
        backnav.className = 'eat-settings-backnav';
        const backbutton = document.createElement('button');
        backbutton.type = 'button';
        backbutton.className = 'eat-settings-backnav__button';
        backbutton.appendChild(icon('arrow-left'));
        const text = document.createElement('span');
        text.textContent = label;
        backbutton.appendChild(text);
        backbutton.addEventListener('click', function() {
            hub.scrollIntoView({behavior: 'smooth', block: 'start'});
        });
        backnav.appendChild(backbutton);
        return backnav;
    };

    const addBackNav = function(group, hub, label) {
        if (group.nextElementSibling && group.nextElementSibling.classList.contains('eat-settings-backnav')) {
            return;
        }
        const backnav = createBackNav(hub, label);
        if (group.dataset.eatMajor) {
            backnav.dataset.eatMajor = group.dataset.eatMajor;
        }
        if (group.dataset.eatTopic) {
            backnav.dataset.eatTopic = group.dataset.eatTopic;
        }
        group.insertAdjacentElement('afterend', backnav);
    };

    const removeEmptyGroups = function(fieldset) {
        fieldset.querySelectorAll('.eat-settings-group').forEach(function(group) {
            if (!group.querySelector('.form-item')) {
                if (group.nextElementSibling && group.nextElementSibling.classList.contains('eat-settings-backnav')) {
                    group.nextElementSibling.remove();
                }
                group.remove();
            }
        });
    };

    const ensureGroupBacknavs = function(fieldset, hub, backLabel) {
        fieldset.querySelectorAll('.eat-settings-group').forEach(function(group) {
            if (!group.nextElementSibling || !group.nextElementSibling.classList.contains('eat-settings-backnav')) {
                addBackNav(group, hub, backLabel);
            }
        });
    };

    const moveNodeIntoGroup = function(node, group) {
        group.appendChild(node);
    };

    const regroupSettings = function(fieldset, hub, backLabel) {
        let currentGroup = null;

        Array.prototype.slice.call(fieldset.children).forEach(function(node) {
            if (isFormActionRow(node) || !node.dataset || !node.dataset.eatMajor) {
                currentGroup = null;
                return;
            }
            if (node.dataset.eatMajorHeading === '1') {
                currentGroup = null;
                return;
            }
            if (node.dataset.eatTopicHeading === '1') {
                currentGroup = document.createElement('section');
                currentGroup.className = 'eat-settings-group';
                currentGroup.dataset.eatMajor = node.dataset.eatMajor;
                currentGroup.dataset.eatTopic = node.dataset.eatTopic || node.textContent.trim();
                fieldset.insertBefore(currentGroup, node.nextSibling);
                addBackNav(currentGroup, hub, backLabel);
                return;
            }
            if (!currentGroup && node.classList && node.classList.contains('form-item')) {
                currentGroup = document.createElement('section');
                currentGroup.className = 'eat-settings-group eat-settings-group--untitled';
                currentGroup.dataset.eatMajor = node.dataset.eatMajor;
                fieldset.insertBefore(currentGroup, node);
                addBackNav(currentGroup, hub, backLabel);
            }
            if (currentGroup) {
                moveNodeIntoGroup(node, currentGroup);
            }
        });
        removeEmptyGroups(fieldset);
        ensureGroupBacknavs(fieldset, hub, backLabel);
    };

    const isExposeItem = function(item) {
        const shortname = item.querySelector('.form-shortname');
        return shortname && /(^|\s|\|)expose_/.test(shortname.textContent);
    };

    const compactExposeItem = function(item, exposeLabel) {
        const label = item.querySelector('.form-label label');
        if (label) {
            label.textContent = exposeLabel;
            label.closest('.form-label').hidden = true;
        }
        const shortname = item.querySelector('.form-shortname');
        if (shortname) {
            shortname.hidden = true;
        }
        const formText = item.querySelector('.form-text');
        if (formText) {
            formText.hidden = true;
        }
        const setting = item.querySelector('.form-setting');
        const checkbox = setting ? setting.querySelector('input[type="checkbox"]') : null;
        if (setting && checkbox && !setting.querySelector('.eat-settings-expose-label')) {
            const inlineLabel = document.createElement('span');
            inlineLabel.className = 'eat-settings-expose-label';
            inlineLabel.textContent = exposeLabel;
            checkbox.insertAdjacentElement('afterend', inlineLabel);
        }
    };

    const mergeExposeItems = function(fieldset, exposeLabel) {
        fieldset.querySelectorAll('.eat-settings-group').forEach(function(group) {
            let previousSetting = null;
            Array.prototype.slice.call(group.querySelectorAll(':scope > .form-item')).forEach(function(item) {
                if (isExposeItem(item) && previousSetting) {
                    compactExposeItem(item, exposeLabel);
                    item.classList.add('eat-settings-expose-inline');
                    previousSetting.classList.add('eat-settings-item--has-expose');
                    previousSetting.appendChild(item);
                    return;
                }
                previousSetting = item;
            });
        });
    };

    const addCancelButton = function(fieldset, config) {
        if (!config.cancelUrl || !config.cancelLabel) {
            return;
        }
        const submit = fieldset.querySelector('input[type=submit], button[type=submit]');
        const actionRow = submit ? submit.closest('.form-buttons, .settingsformbuttons, .row, div') : null;
        if (!submit || !actionRow || actionRow.querySelector('.eat-settings-cancel')) {
            return;
        }
        const cancel = document.createElement('a');
        cancel.className = 'btn btn-secondary eat-settings-cancel';
        cancel.href = config.cancelUrl;
        cancel.textContent = config.cancelLabel;
        submit.insertAdjacentElement('beforebegin', cancel);
    };

    const hubCard = function(card) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'eat-settings-hub-card';
        button.dataset.eatTarget = card.key;
        button.title = card.body;
        button.setAttribute('aria-label', card.title + '. ' + card.body);

        const iconWrap = document.createElement('span');
        iconWrap.className = 'eat-settings-hub-card__icon';
        iconWrap.appendChild(icon(card.icon));

        const text = document.createElement('span');
        text.className = 'eat-settings-hub-card__text';
        const title = document.createElement('span');
        title.className = 'eat-settings-hub-card__title';
        title.textContent = card.title;
        text.appendChild(title);

        button.appendChild(iconWrap);
        button.appendChild(text);
        return button;
    };

    const buildHub = function(config) {
        const hub = document.createElement('section');
        hub.className = 'eat-settings-hub';
        hub.title = config.hubDesc;

        const head = document.createElement('div');
        head.className = 'eat-settings-hub__head';
        const title = document.createElement('h2');
        title.textContent = config.hubTitle;
        head.appendChild(title);

        const grid = document.createElement('div');
        grid.className = 'eat-settings-hub__grid';
        config.sectionCards.forEach(function(card) {
            grid.appendChild(hubCard(card));
        });

        const topics = document.createElement('nav');
        topics.className = 'eat-settings-topics';
        topics.setAttribute('aria-label', config.topicsLabel);

        hub.appendChild(head);
        hub.appendChild(grid);
        hub.appendChild(topics);
        return {hub: hub, grid: grid, topics: topics};
    };

    const findHashTarget = function() {
        const hash = (window.location.hash || '').replace(/^#/, '');
        if (!hash || hash.indexOf('settings-') === 0) {
            return null;
        }
        return document.getElementById(hash);
    };

    const activeKeyFromHash = function(defaultKey) {
        const hash = (window.location.hash || '').replace(/^#/, '');
        if (hash.indexOf('settings-') === 0) {
            return hash.replace('settings-', '') || defaultKey;
        }
        const target = findHashTarget();
        const owner = target ? target.closest('[data-eat-major]') : null;
        return owner && owner.dataset.eatMajor ? owner.dataset.eatMajor : defaultKey;
    };

    const scrollToHashTarget = function() {
        const target = findHashTarget();
        const row = target ? target.closest('.form-item, .eat-settings-group, h3.main') : null;
        if (!row) {
            return;
        }
        window.setTimeout(function() {
            row.scrollIntoView({behavior: 'smooth', block: 'center'});
            row.classList.add('eat-settings-target-highlight');
            window.setTimeout(function() {
                row.classList.remove('eat-settings-target-highlight');
            }, 1400);
        }, 80);
    };

    const setActive = function(key, form, fieldset, grid, topics) {
        const activeKey = key || 'design';
        form.dataset.eatActiveSettings = activeKey;

        grid.querySelectorAll('.eat-settings-hub-card').forEach(function(card) {
            const active = card.dataset.eatTarget === activeKey;
            card.classList.toggle('eat-settings-hub-card--active', active);
            if (active) {
                card.setAttribute('aria-current', 'page');
            } else {
                card.removeAttribute('aria-current');
            }
        });

        Array.prototype.slice.call(fieldset.children).forEach(function(node) {
            if (node.dataset && node.dataset.eatMajor) {
                node.hidden = node.dataset.eatMajor !== activeKey;
            }
        });

        topics.innerHTML = '';
        const seen = {};
        Array.prototype.slice.call(
            fieldset.querySelectorAll('[data-eat-major="' + activeKey + '"][data-eat-topic-heading="1"]')
        ).forEach(function(heading) {
            const label = heading.textContent.trim();
            if (!label || seen[label]) {
                return;
            }
            seen[label] = true;
            const topic = document.createElement('button');
            topic.type = 'button';
            topic.className = 'eat-settings-topic';
            const topicText = document.createElement('span');
            topicText.className = 'eat-settings-topic__title';
            topicText.textContent = label;
            topic.appendChild(topicText);
            topic.addEventListener('click', function() {
                topics.querySelectorAll('.eat-settings-topic').forEach(function(item) {
                    item.classList.remove('eat-settings-topic--active');
                    item.removeAttribute('aria-current');
                });
                topic.classList.add('eat-settings-topic--active');
                topic.setAttribute('aria-current', 'location');
                heading.scrollIntoView({behavior: 'smooth', block: 'start'});
            });
            topics.appendChild(topic);
        });
        topics.hidden = topics.children.length === 0;
        if (!topics.hidden && topics.firstElementChild) {
            topics.firstElementChild.classList.add('eat-settings-topic--active');
            topics.firstElementChild.setAttribute('aria-current', 'location');
        }

        if ((window.location.hash || '').indexOf('#settings-') === 0 &&
                window.location.hash !== '#settings-' + activeKey) {
            history.replaceState(null, '', '#settings-' + activeKey);
        }
    };

    const init = function(config) {
        const form = document.getElementById('adminsettings');
        if (!form || form.dataset.eatShellWrapped === '1') {
            document.body.classList.add('eat-admin-settings-ready');
            document.body.classList.remove('eat-admin-settings-pending');
            return;
        }
        if (!config || (!config.inShell && !config.headerHtml)) {
            document.body.classList.add('eat-admin-settings-ready');
            document.body.classList.remove('eat-admin-settings-pending');
            return;
        }
        form.dataset.eatShellWrapped = '1';
        document.body.classList.add('path-block-elediaai_tutor', 'eat-admin-settings-shell-page');

        let content = form.parentNode;
        if (!config.inShell) {
            const shell = document.createElement('div');
            shell.className = 'lh-plugin-shell eat-admin-settings-shell';
            shell.innerHTML = config.headerHtml;

            content = document.createElement('div');
            content.className = 'lh-plugin-content-area eat-admin-settings-content';
            form.parentNode.insertBefore(shell, form);
            shell.appendChild(content);
            content.appendChild(form);
        } else if (content && content.classList) {
            content.classList.add('eat-admin-settings-content');
            const shell = content.closest('.lh-plugin-shell');
            if (shell) {
                shell.classList.add('eat-admin-settings-shell');
            }
        }

        const fieldset = form.querySelector('fieldset');
        if (!fieldset) {
            document.body.classList.add('eat-admin-settings-ready');
            document.body.classList.remove('eat-admin-settings-pending');
            return;
        }

        hideDuplicateHeadings(form, config.pluginTitle);

        const majorTitles = {};
        config.sectionCards.forEach(function(card) {
            majorTitles[card.title] = card.key;
        });
        annotateSettings(fieldset, majorTitles);

        const hubdata = buildHub(config);
        content.insertBefore(hubdata.hub, form);
        regroupSettings(fieldset, hubdata.hub, config.backLabel);
        mergeExposeItems(fieldset, config.exposeLabel);
        addCancelButton(fieldset, config);

        hubdata.grid.addEventListener('click', function(event) {
            const card = event.target.closest('.eat-settings-hub-card');
            if (card) {
                setActive(card.dataset.eatTarget, form, fieldset, hubdata.grid, hubdata.topics);
            }
        });

        const initial = activeKeyFromHash('design');
        setActive(initial || 'design', form, fieldset, hubdata.grid, hubdata.topics);
        scrollToHashTarget();
        document.body.classList.add('eat-admin-settings-ready');
        document.body.classList.remove('eat-admin-settings-pending');
    };

    return {
        init: init
    };
});
