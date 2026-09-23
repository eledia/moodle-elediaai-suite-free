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
 * Modal preview for the AI audit table.
 *
 * Each prompt/response cell renders a small eye-icon button plus a
 * `<template>` element that carries the full text (already HTML-escaped
 * server-side via Moodle's `s()`). On click we read the template
 * content and open a Bootstrap modal with the full text in
 * a `<pre>` block — newlines and indentation stay intact.
 *
 * The text never leaves the page after server render; no extra
 * web-service hit per click.
 *
 * @module     local_elediaai_core/audit_preview
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalCancel from 'core/modal_cancel';
import {get_string as getString} from 'core/str';

const SELECTOR = {
    wrapper: '.lh-audit-preview',
    button: '[data-action="lh-audit-preview"]',
    content: '.lh-audit-preview-content',
    failedSuccess: '.lh-audit-success.text-danger',
};

/**
 * Pull the preview payload out of the wrapper around the clicked button.
 *
 * @param {HTMLElement} wrapper
 * @returns {{title: string, body: string}}
 */
const readPayload = (wrapper) => {
    const button = wrapper.querySelector(SELECTOR.button);
    const template = wrapper.querySelector(SELECTOR.content);
    return {
        title: (button && button.getAttribute('data-preview-title')) || '',
        // template.innerHTML returns the HTML-escaped source (s() was applied
        // server-side). We wrap it in a <pre> so whitespace + line breaks survive
        // rendering; the escaped entities decode once when inserted as innerHTML.
        body: template
            ? `<pre class="lh-audit-preview-body mb-0">${template.innerHTML}</pre>`
            : '',
    };
};

/**
 * Open one preview modal for the given payload.
 *
 * @param {{title: string, body: string}} payload
 * @param {HTMLElement} returnElement Focus target when modal closes.
 */
const openModal = async (payload, returnElement) => {
    const closeLabel = await getString('audit_preview_close', 'local_elediaai_core');
    const modal = await ModalCancel.create({
        title: payload.title,
        body: payload.body,
        large: true,
        scrollable: false,
        removeOnClose: true,
        returnElement,
        buttons: {cancel: closeLabel},
        show: true,
    });
    return modal;
};

/**
 * Mark report rows that contain a failed AI action. Reportbuilder can
 * redraw rows after sorting/filtering/pagination, so this runs both on
 * init and after DOM changes.
 *
 * @param {ParentNode} root
 */
const markFailedRows = (root = document) => {
    root.querySelectorAll(SELECTOR.failedSuccess).forEach((marker) => {
        const row = marker.closest('tr');
        if (row) {
            row.classList.add('lh-audit-row--failed');
        }
    });
};

export const init = () => {
    markFailedRows();

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    markFailedRows(node);
                }
            });
        });
    });
    observer.observe(document.body, {childList: true, subtree: true});

    // One delegated handler — the report table re-renders on
    // filter/sort/page, so individual binding would miss new rows.
    document.addEventListener('click', (event) => {
        const button = event.target.closest(SELECTOR.button);
        if (!button) {
            return;
        }
        event.preventDefault();
        const wrapper = button.closest(SELECTOR.wrapper);
        if (!wrapper) {
            return;
        }
        const payload = readPayload(wrapper);
        if (payload.body === '') {
            return;
        }
        openModal(payload, button).catch((e) => {
            // Short-circuit as a statement is what eslint rejects here; the
            // guard itself is right -- a browser without a console must not
            // turn a failed preview into a second error.
            if (window.console) {
                window.console.warn('local_elediaai_core/audit_preview', e);
            }
        });
    });
};
