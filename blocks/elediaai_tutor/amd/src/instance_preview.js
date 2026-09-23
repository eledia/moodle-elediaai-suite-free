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
 * Re-theme the per-instance settings live preview in real time.
 *
 * The tutor's appearance is driven entirely by `--eac-*` CSS custom properties on the
 * widget root (server side: branding::resolve() -> branding::css_variables() ->
 * inline style). This module mirrors that client-side: as each design field changes it
 * sets the matching CSS variable on the (inert) preview widget, so the operator sees the
 * result instantly without saving. A handful of text fields (welcome, persona, footer)
 * are also reflected live. Image uploads and the launcher label are not live (the file
 * is not saved yet / the node is absent in embedded mode) — they appear after Save.
 *
 * Degrades gracefully: if the preview widget is not present (e.g. an "unavailable"
 * notice rendered instead), init() is a no-op.
 *
 * @module     block_elediaai_tutor/instance_preview
 * @copyright  2026 eLeDia GmbH, Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['block_elediaai_tutor/icons'], function(Icons) {
    const ROOT_SELECTOR = '.eat-instance-preview .elediaai-chat-root';

    /**
     * Port of branding::darken(): darken a hex colour by a fraction (0..1).
     *
     * @param {String} hex A #rgb or #rrggbb colour.
     * @param {Number} fraction Amount to darken.
     * @return {String} A #rrggbb colour.
     */
    const darken = function(hex, fraction) {
        let h = (hex || '').replace(/^#/, '');
        if (h.length === 3) {
            h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        }
        if (h.length < 6) {
            return '#' + h;
        }
        const parts = [0, 2, 4].map(function(i) {
            const channel = parseInt(h.substring(i, i + 2), 16);
            const value = Math.max(0, Math.min(255, Math.round(channel * (1 - fraction))));
            return ('0' + value.toString(16)).slice(-2);
        });
        return '#' + parts.join('');
    };

    const isColour = function(value) {
        return /^#[0-9a-fA-F]{3,8}$/.test(value);
    };

    /**
     * Reject values that could break out of an inline style (mirrors the spirit of
     * branding::sanitise_css_value); the server still sanitises what is saved.
     *
     * @param {String} value Candidate CSS value.
     * @return {String} The value, or '' if unsafe.
     */
    const safeCss = function(value) {
        return /[;{}<>\\]/.test(value) ? '' : value;
    };

    /**
     * Initialise the live preview.
     *
     * @param {Object} config tokenMap (key -> --eac-token), fieldTypes (key -> type),
     *        defaults ({welcome, persona, poweredby}).
     */
    const init = function(config) {
        config = config || {};
        const tokenMap = config.tokenMap || {};
        const fieldTypes = config.fieldTypes || {};
        const defaults = config.defaults || {};

        const root = document.querySelector(ROOT_SELECTOR);
        if (!root) {
            return;
        }
        const panel = root.querySelector('.elediaai-chat-panel');
        const targets = panel ? [root, panel] : [root];

        // Which registry key drives --eac-accent-dark, so we can tell whether the
        // operator set it explicitly before deriving it from the accent colour.
        let accentDarkKey = null;
        Object.keys(tokenMap).forEach(function(key) {
            if (tokenMap[key] === '--eac-accent-dark') {
                accentDarkKey = key;
            }
        });

        const setVar = function(name, value) {
            targets.forEach(function(el) {
                if (value === null) {
                    el.style.removeProperty(name);
                } else {
                    el.style.setProperty(name, value);
                }
            });
        };

        const applyToken = function(key) {
            const token = tokenMap[key];
            const field = document.getElementById('id_config_' + key);
            if (!token || !field) {
                return;
            }
            const raw = (field.value || '').trim();
            if (raw === '') {
                setVar(token, null);
            } else if ((fieldTypes[key] || '') === 'colour') {
                if (isColour(raw)) {
                    setVar(token, raw);
                }
            } else {
                const clean = safeCss(raw);
                if (clean !== '') {
                    setVar(token, clean);
                }
            }

            // Derived accent-hover shade (mirrors branding::resolve step 2): only when
            // the operator has not set --eac-accent-dark explicitly.
            if (token === '--eac-accent') {
                const darkField = accentDarkKey
                    ? document.getElementById('id_config_' + accentDarkKey) : null;
                const darkSet = darkField && (darkField.value || '').trim() !== '';
                if (!darkSet) {
                    setVar('--eac-accent-dark', (raw !== '' && isColour(raw)) ? darken(raw, 0.12) : null);
                }
            }
        };

        // Batch rapid events (colour scrubbing fires many) onto one animation frame.
        let scheduled = false;
        const pending = {};
        const flush = function() {
            scheduled = false;
            const keys = Object.keys(pending);
            keys.forEach(function(key) {
                delete pending[key];
                applyToken(key);
            });
        };
        const schedule = function(key) {
            pending[key] = true;
            if (!scheduled) {
                scheduled = true;
                if (window.requestAnimationFrame) {
                    window.requestAnimationFrame(flush);
                } else {
                    window.setTimeout(flush, 50);
                }
            }
        };

        // Wire every token field for instant CSS re-theming.
        Object.keys(tokenMap).forEach(function(key) {
            const field = document.getElementById('id_config_' + key);
            if (!field) {
                return;
            }
            const apply = function() {
                schedule(key);
            };
            field.addEventListener('input', apply);
            field.addEventListener('change', apply);
            // The YUI colour picker sets the input value programmatically (no input
            // event fires), so also watch the swatch for pointer activity.
            if ((fieldTypes[key] || '') === 'colour') {
                const wrap = field.closest('.form-colourpicker');
                if (wrap) {
                    wrap.addEventListener('mousemove', apply);
                    wrap.addEventListener('mouseup', apply);
                    wrap.addEventListener('click', apply);
                }
            }
        });

        // Simple text fields: reflected live where the node exists; skip otherwise.
        const setText = function(selector, text) {
            const node = root.querySelector(selector);
            if (node) {
                node.textContent = text;
            }
        };
        const bindText = function(key, handler) {
            const field = document.getElementById('id_config_' + key);
            if (!field) {
                return;
            }
            const run = function() {
                handler(field.value || '');
            };
            field.addEventListener('input', run);
            field.addEventListener('change', run);
        };

        bindText('welcomemessage', function(value) {
            setText(
                '.elediaai-chat-welcome .elediaai-chat-bubble',
                value.trim() === '' ? (defaults.welcome || '') : value
            );
        });
        bindText('persona', function(value) {
            const name = value.trim() === '' ? (defaults.persona || '') : value;
            setText('.elediaai-chat-title', name);
            // Update every assistant sender label (welcome + sample answers), but not the
            // learner's "You" label.
            root.querySelectorAll('.elediaai-chat-assistant .elediaai-chat-sender').forEach(function(node) {
                node.textContent = name;
            });
        });

        // Footer (mode + text). The node only exists when the saved config showed a
        // footer; if hidden initially it appears after Save (graceful skip).
        const applyFooter = function() {
            const node = root.querySelector('.elediaai-chat-footer');
            if (!node) {
                return;
            }
            const modeField = document.getElementById('id_config_footermode');
            const textField = document.getElementById('id_config_footertext');
            const mode = modeField ? (modeField.value || '') : '';
            if (mode === 'none') {
                node.hidden = true;
                return;
            }
            node.hidden = false;
            if (mode === 'custom') {
                const text = textField ? (textField.value || '').trim() : '';
                node.textContent = text !== '' ? text : (defaults.poweredby || '');
            } else {
                node.textContent = defaults.poweredby || '';
            }
        };
        ['footermode', 'footertext'].forEach(function(key) {
            const field = document.getElementById('id_config_' + key);
            if (field) {
                field.addEventListener('input', applyFooter);
                field.addEventListener('change', applyFooter);
            }
        });

        wireFloatingPanel();
    };

    /**
     * Make the floating preview panel minimisable and draggable by its title bar, so it
     * overlays the page without disturbing the form and can be parked out of the way.
     */
    function wireFloatingPanel() {
        const panel = document.querySelector('.eat-preview-float');
        if (!panel) {
            return;
        }
        const bar = panel.querySelector('.eat-preview-float__bar');
        const toggle = panel.querySelector('[data-action="eat-preview-toggle"]');

        if (toggle) {
            toggle.addEventListener('click', function() {
                const collapsed = panel.classList.toggle('eat-preview-float--collapsed');
                const icon = toggle.querySelector('svg');
                if (icon) {
                    icon.replaceWith(Icons.create(collapsed ? 'window-maximize' : 'window-minimize'));
                }
            });
        }

        if (!bar) {
            return;
        }
        let dragging = false;
        let originX = 0;
        let originY = 0;
        let baseLeft = 0;
        let baseTop = 0;

        const onMove = function(event) {
            if (!dragging) {
                return;
            }
            const rect = panel.getBoundingClientRect();
            const maxLeft = Math.max(4, window.innerWidth - rect.width - 4);
            const maxTop = Math.max(4, window.innerHeight - rect.height - 4);
            const left = Math.min(maxLeft, Math.max(4, baseLeft + (event.clientX - originX)));
            const top = Math.min(maxTop, Math.max(4, baseTop + (event.clientY - originY)));
            panel.style.left = left + 'px';
            panel.style.top = top + 'px';
            panel.style.right = 'auto';
            panel.style.bottom = 'auto';
        };
        const onUp = function() {
            dragging = false;
            document.removeEventListener('pointermove', onMove);
            document.removeEventListener('pointerup', onUp);
        };
        bar.addEventListener('pointerdown', function(event) {
            if (event.target.closest('[data-action="eat-preview-toggle"]')) {
                return; // Let the minimise button work without starting a drag.
            }
            const rect = panel.getBoundingClientRect();
            baseLeft = rect.left;
            baseTop = rect.top;
            originX = event.clientX;
            originY = event.clientY;
            dragging = true;
            document.addEventListener('pointermove', onMove);
            document.addEventListener('pointerup', onUp);
            event.preventDefault();
        });
    }

    return {
        init: init
    };
});
