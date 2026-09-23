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
 * The chat panel behaviour every placement shares.
 *
 * Extracted from the tutor block, which was the only surface that had all of
 * it: composing and sending, streamed and buffered answers, message bubbles
 * with citations and confirmation cards, the enlarged overlay with its focus
 * trap, and the transcript's live-region handling.
 *
 * What stayed behind in the tutor are the things only the tutor has — consent,
 * privacy dialog, long-term memory, answer-style chips, starter pills. A
 * placement adds those by extending {@see ChatPanel} and implementing the two
 * hooks {@see ChatPanel#bindPlacement} and {@see ChatPanel#handlePlacementAction},
 * rather than by keeping a second copy of the parts above.
 *
 * Every CSS hook carries a placement-supplied prefix, so a surface keeps its
 * own styling and its own Behat selectors while sharing the behaviour.
 *
 * @module     local_elediaai_chatengine/chat_panel
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import {get_strings as getStrings} from 'core/str';
import {renderInto as renderLiveMarkdown} from 'local_elediaai_chatengine/markdown_live';
import {notifyFilterContentUpdated} from 'core_filters/events';
import {typeset as typesetMaths} from 'filter_mathjaxloader/loader';

/** @var {object} Cached localised strings. */
const strings = {};

/** @var {Array} Strings to preload: [property, key]. */
const STRING_DEFS = [
    ['thinking', 'thinking'],
    ['failed', 'failed'],
    ['copy', 'copy'],
    ['copied', 'copied'],
    ['retry', 'retry'],
    ['newstarted', 'newstarted'],
    ['resumed', 'resumed'],
    ['toolong', 'toolong'],
    ['you', 'senderyou'],
    ['sources', 'sources'],
    ['clearlabel', 'clearconversation'],
    ['clearconfirm', 'clearconfirm'],
    ['cleared', 'cleared'],
    ['clearfailed', 'error_clearfailed'],
];

/** @var {string} Focusable selector for the focus trap. */
const FOCUSABLE = [
    'a[href]', 'button:not([disabled])', 'textarea:not([disabled])',
    'input:not([disabled])', '[tabindex]:not([tabindex="-1"])'
].join(',');

/* eslint-disable max-len */
/** @var {object} The two inline icons the expand button swaps between. */
const EXPAND_ICONS = {
    expand: '<path d="m21 21-6-6m6 6v-4.8m0 4.8h-4.8"/><path d="M3 16.2V21m0 0h4.8M3 21l6-6"/><path d="M21 7.8V3m0 0h-4.8M21 3l-6 6"/><path d="M3 7.8V3m0 0h4.8M3 3l6 6"/>',
    compress: '<path d="m14 10 7-7"/><path d="M20 10h-6V4"/><path d="m3 21 7-7"/><path d="M4 14h6v6"/>',
};
/* eslint-enable max-len */

/**
 * One chat panel.
 */
export class ChatPanel {
    /**
     * @param {HTMLElement} root The panel root element.
     * @param {object} config Non-secret configuration from PHP.
     */
    constructor(root, config) {
        this.root = root;
        this.config = config;
        this.component = root.dataset.component || config.component || '';
        this.instanceid = parseInt(root.dataset.instanceid || config.instanceid || 0, 10);
        this.prefix = config.prefix || 'elediaai-chat';

        this.threadId = 0;
        this.pendingNewThread = false;
        this.busy = false;
        this.lastUserMessage = '';
        this.previousFocus = null;
        this.historyLoaded = false;
        this.expanded = false;
        this.expandHome = null;
        this.expandPlaceholder = null;

        this.panel = root.querySelector('[data-region="panel"]');
        this.window = root.querySelector('.' + this.prefix + '-window') || this.panel;
        this.log = root.querySelector('[data-region="log"]');
        this.input = root.querySelector('[data-region="input"]');
        this.status = root.querySelector('[data-region="status"]');
        this.error = root.querySelector('[data-region="error"]');
        this.composer = root.querySelector('[data-region="composer"]');
        this.backdrop = root.querySelector('.' + this.prefix + '-backdrop');
        this.expandButton = root.querySelector('[data-action="expand"]');

        this.bind();
        this.bindPlacement();
        this.restoreThread();
    }

    /**
     * Bind the controls every placement has.
     *
     * Listeners live on the panel rather than the root because the overlay
     * modes portal the panel to <body> — out of the root — so root-level
     * delegation would stop firing the moment the panel moves.
     *
     * @return {void}
     */
    bind() {
        this.panel.addEventListener('click', (e) => {
            const actionEl = e.target.closest('[data-action]');
            if (!actionEl || !this.panel.contains(actionEl)) {
                return;
            }
            this.handleAction(actionEl.getAttribute('data-action'), actionEl, e);
        });

        if (this.composer) {
            this.composer.addEventListener('submit', (e) => {
                e.preventDefault();
                this.send();
            });
        }

        if (this.input) {
            this.input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.send();
                }
            });
            this.input.addEventListener('input', () => this.autoGrow());
        }

        // Escape leaves the enlarged view, Tab stays inside it.
        // Bound to the document, not to the panel: while the chat is modal the
        // keyboard belongs to it wherever focus happens to be. Bound to the
        // panel, Escape did nothing once focus had slipped outside - which is
        // exactly the situation Escape is for.
        this.onModalKeydown = (e) => {
            if (!this.expanded || this.isHidden()) {
                return;
            }
            if (e.key === 'Escape') {
                this.shrink();
            } else if (e.key === 'Tab') {
                this.trapFocus(e);
            }
        };
        document.addEventListener('keydown', this.onModalKeydown);
    }

    /**
     * Bind the controls only this placement has.
     *
     * @return {void}
     */
    bindPlacement() {
        // Nothing by default.
    }

    /**
     * Route a delegated action.
     *
     * @param {string} action The data-action value.
     * @param {HTMLElement} el The clicked element.
     * @param {Event} e The originating event.
     * @return {void}
     */
    handleAction(action, el, e) {
        switch (action) {
            case 'send': this.send(); break;
            case 'expand': this.toggleExpanded(); break;
            case 'clear': this.clearConversation(el); break;
            case 'copy': this.copyAnswer(el); break;
            case 'retry': this.retry(el); break;
            case 'confirm-resume': this.resolveConfirmation(el, 'confirm'); break;
            case 'confirm-decline': this.resolveConfirmation(el, 'decline'); break;
            default: this.handlePlacementAction(action, el, e); break;
        }
    }

    /**
     * Route an action this placement added.
     *
     * @param {string} action The data-action value.
     * @param {HTMLElement} el The clicked element.
     * @param {Event} e The originating event.
     * @return {void}
     */
    /* eslint-disable-next-line no-unused-vars */
    handlePlacementAction(action, el, e) {
        // Nothing by default; a placement overrides this for the actions it adds.
    }

    /**
     * @return {boolean} Whether the panel is currently hidden.
     */
    isHidden() {
        return this.panel.hasAttribute('hidden');
    }

    /**
     * Toggle the larger centered chat view.
     *
     * @return {void}
     */
    toggleExpanded() {
        if (this.expanded) {
            this.shrink();
        } else {
            this.expand();
        }
    }

    /**
     * Move the panel into a centered, larger viewport overlay.
     *
     * @return {void}
     */
    expand() {
        this.previousFocus = document.activeElement;
        if (!this.expandPlaceholder && this.panel.parentNode !== document.body) {
            this.expandHome = {parent: this.panel.parentNode, next: this.panel.nextSibling};
            this.expandPlaceholder = document.createComment(this.prefix + '-expanded-home');
            this.expandHome.parent.insertBefore(this.expandPlaceholder, this.panel);
            document.body.appendChild(this.panel);
        }
        this.panel.classList.add(this.prefix + '-expanded');
        this.panel.removeAttribute('hidden');
        if (this.backdrop) {
            this.backdrop.removeAttribute('hidden');
        }
        document.body.classList.add(this.prefix + '-noscroll', this.prefix + '-expanded-open');
        this.expanded = true;
        this.makeBackgroundInert();
        this.updateExpandButton();
        this.updateDialogRole();
        window.setTimeout(() => this.input && this.input.focus(), 50);
    }

    /**
     * Restore the panel to its previous size and DOM position.
     *
     * @return {void}
     */
    shrink() {
        this.panel.classList.remove(this.prefix + '-expanded');
        if (this.backdrop) {
            this.backdrop.setAttribute('hidden', 'hidden');
        }
        document.body.classList.remove(this.prefix + '-noscroll', this.prefix + '-expanded-open');
        if (this.expandHome && this.expandHome.parent) {
            this.expandHome.parent.insertBefore(this.panel, this.expandHome.next);
        }
        if (this.expandPlaceholder && this.expandPlaceholder.parentNode) {
            this.expandPlaceholder.parentNode.removeChild(this.expandPlaceholder);
        }
        this.expandHome = null;
        this.expandPlaceholder = null;
        this.expanded = false;
        this.releaseBackgroundInert();
        this.updateExpandButton();
        this.updateDialogRole();
        if (this.previousFocus && typeof this.previousFocus.focus === 'function') {
            this.previousFocus.focus();
        }
    }

    /**
     * Reflect the current modality in the panel's ARIA semantics.
     *
     * While the panel overlays the page it is announced as a modal dialog;
     * inline it stays a region. Announcing an inline panel as a dialog would
     * tell a screen-reader user the page behind it is inert when it is not.
     *
     * @return {void}
     */
    updateDialogRole() {
        if (this.expanded) {
            this.panel.setAttribute('role', 'dialog');
            this.panel.setAttribute('aria-modal', 'true');
            return;
        }
        this.panel.setAttribute('role', 'region');
        this.panel.removeAttribute('aria-modal');
    }

    /**
     * Keep the expand button icon and accessibility state in sync.
     *
     * @return {void}
     */
    /**
     * Mark everything outside the expanded panel inert.
     *
     * A backdrop hides the page from sight, not from a screen reader or from
     * the tab order, and trapping Tab only covers the keyboard. Inert is what
     * actually takes the branch out of both trees. Only the branches this
     * method set are released again, so an inert region that was already there
     * for another reason survives the chat being opened and closed.
     *
     * @returns {void}
     */
    makeBackgroundInert() {
        this.inerted = [];
        let current = this.panel;
        while (current && current !== document.body && current.parentElement) {
            const parent = current.parentElement;
            const branch = current;
            Array.prototype.forEach.call(parent.children, (sibling) => {
                if (sibling === branch || sibling === this.backdrop || sibling.hasAttribute('inert')) {
                    return;
                }
                sibling.setAttribute('inert', '');
                this.inerted.push(sibling);
            });
            current = parent;
        }
    }

    /**
     * Undo makeBackgroundInert.
     *
     * @returns {void}
     */
    releaseBackgroundInert() {
        (this.inerted || []).forEach((node) => node.removeAttribute('inert'));
        this.inerted = [];
    }

    updateExpandButton() {
        if (!this.expandButton) {
            return;
        }
        const label = this.expanded
            ? this.expandButton.dataset.labelCollapse
            : this.expandButton.dataset.labelExpand;
        this.expandButton.setAttribute('aria-pressed', this.expanded ? 'true' : 'false');
        // aria-expanded is the property that actually describes this button:
        // it opens and closes a region. aria-pressed stays because the tutor's
        // own launcher declares it and that markup is fixed.
        this.expandButton.setAttribute('aria-expanded', this.expanded ? 'true' : 'false');
        if (label) {
            this.expandButton.setAttribute('aria-label', label);
            this.expandButton.setAttribute('title', label);
        }
        const icon = this.expandButton.querySelector('svg');
        if (icon) {
            icon.innerHTML = this.expanded ? EXPAND_ICONS.compress : EXPAND_ICONS.expand;
        }
    }

    /**
     * Keep keyboard focus inside the enlarged overlay.
     *
     * @param {KeyboardEvent} e The tab event.
     * @return {void}
     */
    trapFocus(e) {
        const nodes = Array.from(this.window.querySelectorAll(FOCUSABLE))
            .filter((n) => n.offsetParent !== null);
        if (!nodes.length) {
            return;
        }
        const first = nodes[0];
        const last = nodes[nodes.length - 1];
        // Focus that is outside the window altogether gets pulled back in. A
        // trap that only wraps at the ends leaves anything that landed outside
        // - a click on the page behind, a programmatic focus - stranded there
        // while the chat still claims to be modal.
        if (!this.window.contains(document.activeElement)) {
            e.preventDefault();
            (e.shiftKey ? last : first).focus();
            return;
        }
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    /**
     * Resize the textarea to fit its content, bounded by CSS max-height.
     *
     * @return {void}
     */
    autoGrow() {
        if (!this.input) {
            return;
        }
        this.input.style.height = 'auto';
        this.input.style.height = Math.min(this.input.scrollHeight, 160) + 'px';
    }

    /**
     * Record that a request is or is not in flight.
     *
     * The send button says so itself rather than only looking different: a
     * request on its way is not something to read off a spinner. aria-disabled
     * rather than disabled, so the button keeps its place in the tab order.
     *
     * @param {boolean} busy Whether a request is in flight.
     * @return {void}
     */
    setBusy(busy) {
        this.busy = busy;
        const send = this.panel.querySelector('[data-action="send"]');
        if (!send) {
            return;
        }
        if (busy) {
            send.setAttribute('aria-disabled', 'true');
        } else {
            send.removeAttribute('aria-disabled');
        }
    }

    /**
     * Whether the composer may send right now.
     *
     * A placement that gates sending — behind a consent step, for instance —
     * overrides this instead of reimplementing send().
     *
     * @return {boolean}
     */
    canSend() {
        return !this.busy;
    }

    /**
     * Send the current composer message.
     *
     * @return {void}
     */
    send() {
        if (!this.canSend()) {
            return;
        }
        const message = (this.input.value || '').trim();
        if (!message) {
            return;
        }
        if (this.config.maxlength && message.length > this.config.maxlength) {
            this.setStatus(strings.toolong);
            return;
        }
        this.lastUserMessage = message;
        this.input.value = '';
        this.autoGrow();
        this.clearError();
        this.appendUser(message);
        this.dispatch(message);
    }

    /**
     * The web service functions this panel talks to.
     *
     * The shared endpoints by default. A placement whose own endpoint carries
     * something the shared one knows nothing about — a guest session, a
     * submission lifecycle — points at its own here instead of reimplementing
     * the panel around it.
     *
     * @return {object} Method names keyed by send, history and clear.
     */
    methods() {
        return {
            send: 'local_elediaai_chatengine_send_message',
            history: 'local_elediaai_chatengine_load_history',
            clear: 'local_elediaai_chatengine_clear_conversation',
        };
    }

    /**
     * The endpoint that streams a turn.
     *
     * The shared one by default, and overridden for the same reason as
     * methods(): a placement whose endpoint carries something the shared one
     * knows nothing about points at its own.
     *
     * **Whoever overrides turnArgs() has to answer this too.** The shared
     * endpoint is addressed by component and instance; a placement that sends
     * something else and leaves this alone posts arguments the endpoint cannot
     * read. That failure is invisible on a backend which reports no streaming
     * support, and appears only where one does — which is how it survived a
     * release. Return the empty string to say this surface has no streaming
     * endpoint; the buffered path is then used, as it is everywhere streaming
     * is switched off.
     *
     * @return {string} Absolute URL of the streaming endpoint, or '' for none.
     */
    streamUrl() {
        return M.cfg.wwwroot + '/local/elediaai_chatengine/stream.php';
    }

    /**
     * The arguments every turn carries, whichever transport is used.
     *
     * A placement that adds backend hints overrides this and merges its own.
     *
     * @param {string} message The user message.
     * @return {object} Web service arguments.
     */
    turnArgs(message) {
        return {
            component: this.component,
            instanceid: this.instanceid,
            message: message,
            threadid: this.threadId || 0,
            newthread: this.pendingNewThread ? 1 : 0,
        };
    }

    /**
     * Perform the send and render the response.
     *
     * @param {string} message The user message.
     * @param {object} [extra] Additional arguments for the turn.
     * @return {void}
     */
    dispatch(message, extra) {
        this.setBusy(true);
        this.showTyping();
        this.setStatus(strings.thinking);

        // Streaming needs a browser that can read a response body
        // incrementally and a placement that has an endpoint for it; anything
        // else, and any failure before the first fragment, falls back to the
        // buffered call.
        if (this.config.streaming && this.streamUrl() && window.ReadableStream && window.fetch) {
            this.dispatchStreaming(message, extra);
            return;
        }
        this.dispatchBuffered(message, extra);
    }

    /**
     * Send over the streaming endpoint, rendering fragments as they arrive.
     *
     * Falls back to the buffered call when the stream cannot be established at
     * all, so a proxy that swallows server-sent events still yields an answer.
     *
     * @param {string} message The user message.
     * @param {object} [extra] Additional arguments for the turn.
     * @return {void}
     */
    dispatchStreaming(message, extra) {
        const args = Object.assign(this.turnArgs(message), extra || {});
        const params = new URLSearchParams(Object.assign({sesskey: M.cfg.sesskey}, args));

        this.liveBubble = null;
        this.liveOpening = null;
        this.liveFrame = null;
        this.liveTarget = null;
        this.liveTargetText = '';
        let text = '';
        let settled = false;

        const finish = () => {
            this.hideTyping();
            this.setBusy(false);
            this.setStatus('');
        };

        // The streamed bubble is dropped only once its finished replacement is
        // in the log, so the answer never blinks out of view in between.
        const replaceLive = (render) => {
            const streamed = this.liveBubble;
            this.cancelLive();
            this.liveBubble = null;
            this.liveOpening = null;
            return Promise.resolve(render()).then((node) => {
                if (streamed) {
                    streamed.remove();
                }
                return node;
            });
        };

        fetch(this.streamUrl(), {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: params.toString(),
            credentials: 'same-origin'
        }).then((response) => {
            if (!response.ok || !response.body) {
                throw new Error('stream unavailable');
            }
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            const pump = () => reader.read().then(({done, value}) => {
                if (done) {
                    if (!settled) {
                        finish();
                        this.showError(strings.failed);
                        replaceLive(() => this.appendFailure(new Error('stream ended without result')));
                    }
                    return null;
                }
                buffer += decoder.decode(value, {stream: true});

                // Frames are separated by a blank line; a partial one waits in
                // the buffer until its remainder arrives.
                let split = buffer.indexOf('\n\n');
                while (split !== -1) {
                    const frame = buffer.slice(0, split);
                    buffer = buffer.slice(split + 2);
                    const parsed = this.parseFrame(frame);
                    if (parsed) {
                        if (parsed.event === 'token' && parsed.data.delta) {
                            this.hideTyping();
                            text += parsed.data.delta;
                            this.growLiveBubble(text);
                        } else if (parsed.event === 'reset') {
                            text = '';
                            this.growLiveBubble(text);
                        } else if (parsed.event === 'final') {
                            settled = true;
                            const data = parsed.data;
                            finish();
                            this.rememberThread(data.threadid);
                            // The final frame is authoritative: it replaces the
                            // streamed text rather than extending it.
                            replaceLive(() => this.appendAssistant(
                                data.answerhtml,
                                data.sources || [],
                                data.iserror,
                                data.confirmation || null,
                                data.origin || ''
                            ));
                        } else if (parsed.event === 'error') {
                            settled = true;
                            finish();
                            this.showError(parsed.data.message || strings.failed);
                            replaceLive(() => this.appendFailure(new Error(parsed.data.message || '')));
                        }
                    }
                    split = buffer.indexOf('\n\n');
                }
                return pump();
            });

            return pump();
        }).catch(() => {
            if (settled) {
                return;
            }
            // Nothing was rendered yet, so the buffered path can simply retry.
            finish();
            replaceLive(() => null);
            this.dispatchBuffered(message, extra);
        });
    }

    /**
     * Parse one server-sent frame into its event name and decoded payload.
     *
     * @param {string} frame Raw frame without the trailing blank line.
     * @return {object|null} {event, data}, or null when undecodable.
     */
    parseFrame(frame) {
        let event = '';
        const data = [];
        frame.split('\n').forEach((line) => {
            if (line.indexOf('event:') === 0) {
                event = line.slice(6).trim();
            } else if (line.indexOf('data:') === 0) {
                data.push(line.slice(5).replace(/^ /, ''));
            }
        });
        if (!event || !data.length) {
            return null;
        }
        try {
            return {event: event, data: JSON.parse(data.join('\n'))};
        } catch (e) {
            // The terminating frame carries [DONE], which is not JSON.
            return {event: event, data: {}};
        }
    }

    /**
     * Show the answer built so far in the live bubble.
     *
     * Plain text on purpose: the streamed fragments are raw Markdown and are
     * not sanitised yet. The final frame delivers the purified HTML.
     *
     * @param {Element|null} node The live bubble, if it is open.
     * @param {string} text The text accumulated so far.
     * @return {Element|null} The live bubble.
     */
    renderLive(node, text) {
        if (!node) {
            return null;
        }
        const target = node.querySelector('.' + this.prefix + '-markdown') || node;

        // Formatted while it arrives, not only once it is complete: reading a
        // paragraph of raw `**` and `-` is the price of waiting otherwise. Only
        // the shape is applied here and only from a fixed subset — the model's
        // text always enters as text nodes, never as markup, because that is
        // what the server's renderer is for. The final frame replaces this
        // whole bubble with the sanitised version anyway.
        this.liveTarget = target;
        this.liveTargetText = text;
        if (this.liveFrame) {
            return node;
        }
        // One repaint per frame rather than one per token: a fast backend
        // delivers dozens a second, and rebuilding the subtree for each of them
        // costs far more than it shows.
        this.liveFrame = window.requestAnimationFrame(() => {
            this.liveFrame = null;
            renderLiveMarkdown(this.liveTarget, this.liveTargetText);
            this.scrollToBottom();
        });

        return node;
    }

    /**
     * Drop a repaint that has not run yet.
     *
     * Called before the streamed bubble is handed over: a frame scheduled for a
     * node that is about to be removed would either render into nothing or, if
     * it ran first, briefly show the streamed text on top of the finished
     * answer.
     *
     * @return {void}
     */
    cancelLive() {
        if (this.liveFrame) {
            window.cancelAnimationFrame(this.liveFrame);
            this.liveFrame = null;
        }
        this.liveTarget = null;
        this.liveTargetText = '';
    }

    /**
     * Show the answer received so far, opening the bubble on first use.
     *
     * @param {string} text The text accumulated so far.
     * @return {void}
     */
    growLiveBubble(text) {
        this.liveText = text;
        if (this.liveBubble) {
            this.renderLive(this.liveBubble, this.liveText);
            return;
        }
        if (this.liveOpening) {
            return;
        }
        this.liveOpening = this.openLiveBubble().then((node) => {
            this.liveBubble = node;
            this.renderLive(node, this.liveText);
            return node;
        });
    }

    /**
     * Open an empty assistant bubble to stream into.
     *
     * The ordinary message template is used, so the answer grows inside the
     * same bubble it will finally live in — rather than appearing as loose
     * text that only becomes a bubble once the answer is complete.
     *
     * @return {Promise} Resolves with the bubble node, or null when it failed.
     */
    openLiveBubble() {
        return this.appendMessage({
            isassistant: true,
            html: '',
            sendername: this.config.persona || '',
            islive: true
        }).then((node) => {
            if (node) {
                node.classList.add(this.prefix + '-msg--live');
                node.setAttribute('aria-busy', 'true');
                const target = node.querySelector('.' + this.prefix + '-markdown');
                if (target) {
                    target.setAttribute('aria-live', 'polite');
                }
            }
            return node;
        });
    }

    /**
     * Perform the buffered send and render the complete response.
     *
     * @param {string} message The user message.
     * @param {object} [extra] Additional arguments for the turn.
     * @return {void}
     */
    dispatchBuffered(message, extra) {
        this.setBusy(true);
        this.showTyping();
        this.setStatus(strings.thinking);

        Ajax.call([{
            methodname: this.methods().send,
            args: Object.assign(this.turnArgs(message), extra || {})
        }])[0].then((response) => {
            this.hideTyping();
            this.setBusy(false);
            this.setStatus('');
            this.rememberThread(response.threadid);
            return this.appendAssistant(
                response.answerhtml,
                response.sources || [],
                response.iserror,
                response.confirmation || null,
                response.origin || ''
            );
        }).catch((error) => {
            this.hideTyping();
            this.setBusy(false);
            this.setStatus('');
            this.showError((error && error.message) ? error.message : strings.failed);
            this.appendFailure(error);
            return null;
        });
    }

    /**
     * Start the next turn in a fresh conversation.
     *
     * Only the wish is recorded here; the conversation itself is opened by the
     * turn that follows, so pressing this and then closing the panel leaves no
     * empty thread behind. A placement calls this from whatever control it
     * offers for it.
     *
     * @return {void}
     */
    startNewThread() {
        this.threadId = 0;
        this.saveThreadPointer(0);
        this.historyLoaded = false;
        this.pendingNewThread = true;
    }

    /**
     * Remember which conversation the server recorded the turn in.
     *
     * The wish for a new conversation is dropped here rather than where the
     * turn is assembled: one turn can reach the server twice - a retry, or the
     * streamed attempt falling back to the buffered call - and a wish consumed
     * by the first attempt would let the second one land in the old
     * conversation after all.
     *
     * @param {number} threadid The conversation id.
     * @return {void}
     */
    rememberThread(threadid) {
        const id = parseInt(threadid || 0, 10);
        if (id <= 0) {
            return;
        }
        this.pendingNewThread = false;
        if (id !== this.threadId) {
            this.threadId = id;
            this.saveThreadPointer(id);
            this.historyLoaded = false;
        }
    }

    /**
     * @return {string} The sessionStorage key for the active conversation.
     */
    threadKey() {
        return 'elediaai_chatengine_' + this.component + '_' + this.instanceid;
    }

    /**
     * Remember the active conversation across page loads.
     *
     * @param {number} threadid The conversation id, or 0 to forget it.
     * @return {void}
     */
    saveThreadPointer(threadid) {
        try {
            if (threadid) {
                window.sessionStorage.setItem(this.threadKey(), String(threadid));
            } else {
                window.sessionStorage.removeItem(this.threadKey());
            }
        } catch (e) {
            // Storage unavailable (private mode): the pointer holds for this page only.
        }
    }

    /**
     * Restore the conversation this placement was last used with.
     *
     * @return {void}
     */
    restoreThread() {
        let stored = 0;
        try {
            stored = parseInt(window.sessionStorage.getItem(this.threadKey()) || '0', 10);
        } catch (e) {
            stored = 0;
        }
        if (!stored && !this.config.autoloadhistory) {
            this.raiseLiveRegion();
            return;
        }
        this.threadId = stored || 0;
        this.loadHistory();
    }

    /**
     * Load the stored turns and render them.
     *
     * @param {boolean} [retried] Set on the second attempt, after a stale pointer.
     * @return {void}
     */
    loadHistory(retried) {
        const asked = this.threadId || 0;
        Ajax.call([{
            methodname: this.methods().history,
            args: {component: this.component, instanceid: this.instanceid, threadid: asked}
        }])[0].then((response) => {
            this.historyLoaded = true;
            this.threadId = parseInt(response.threadid || 0, 10);
            if (!this.threadId) {
                this.saveThreadPointer(0);
                // The pointer in session storage outlives the conversation it
                // points at - a cleared chat, a restored site, another browser
                // tab. Asking for a thread that is gone answers "nothing", and
                // showing that as an empty chat hides a conversation that is
                // still there. Ask once more for whatever is current.
                if (asked && !retried) {
                    return this.loadHistory(true);
                }
                return null;
            }
            const rendered = (response.messages || []).reduce(
                // A restored assistant turn goes through the same builder as a
                // fresh one, so the citations and the origin badge are decided
                // in exactly one place. Rendering history through a second,
                // simpler path is what silently dropped both.
                (chain, item) => chain.then(() => (item.role === 'assistant'
                    ? this.appendAssistant(item.html, item.sources || [], false, null, item.origin || '')
                    : this.appendMessage({
                        isuser: true,
                        sendername: strings.you,
                        html: item.html,
                        copylabel: strings.copy,
                        retrylabel: strings.retry
                    }))),
                Promise.resolve()
            );
            return rendered.then(() => {
                this.setStatus(strings.resumed);
                // Der wiederhergestellte Verlauf wird in einem Zug aufgebaut.
                // Jede Blase meldet sich zwar einzeln, aber erst hier steht
                // sicher alles im Dokument -- eine Meldung ueber das gesamte
                // Protokoll faengt auf, was dabei zu frueh kam.
                this.notifyContentUpdated(this.log);
                return null;
            });
        }).then(() => {
            this.raiseLiveRegion();
            return null;
        }).catch((error) => {
            // The transcript still has to start announcing, or the panel would
            // stay silent for a screen reader after a failed restore.
            this.raiseLiveRegion();
            Notification.exception(error);
        });
    }

    /**
     * Start announcing new turns, now that any restored ones are in place.
     *
     * The transcript starts as aria-live="off" so reopening a conversation
     * does not read every past turn out one by one; it becomes polite only
     * once the restored turns have been rendered.
     *
     * @return {void}
     */
    raiseLiveRegion() {
        if (this.log) {
            this.log.setAttribute('aria-live', 'polite');
        }
    }

    /**
     * Append a user bubble.
     *
     * @param {string} text The message text.
     * @return {Promise}
     */
    appendUser(text) {
        return this.appendMessage({isuser: true, text: text, sendername: strings.you});
    }

    /**
     * Append an assistant bubble.
     *
     * @param {string} html Server-sanitised HTML answer.
     * @param {Array} sources Citations.
     * @param {boolean} iserror Whether the backend reported a tool-level error.
     * @param {object|null} confirmation Optional confirmation card payload.
     * @param {string} origin Answer origin: grounded or general.
     * @return {Promise}
     */
    appendAssistant(html, sources, iserror, confirmation, origin) {
        const mappedSources = (sources || []).map((s, i) => ({
            num: i + 1,
            title: s.title,
            url: s.url,
            hasurl: !!s.url,
            snippet: s.snippet
        }));
        const confirm = confirmation && confirmation.required ? {
            yeslabel: confirmation.yeslabel || '',
            nolabel: confirmation.nolabel || '',
            yesmessage: confirmation.yesmessage || '',
            nomessage: confirmation.nomessage || '',
            summary: confirmation.summary || '',
            hassummary: !!(confirmation.summary || ''),
            destructive: !!confirmation.destructive,
            tooltitle: confirmation.tooltitle || '',
            hastooltitle: !!(confirmation.tooltitle || '')
        } : null;
        // Three origins, not two: an answer read out of Moodle through the
        // connector's tools is neither grounded in course material nor the
        // model talking to itself, and it carries its own badge.
        const ismcp = origin === 'mcp';
        const grounded = origin === 'grounded' || (!origin && mappedSources.length > 0);
        const visibleSources = grounded ? mappedSources : [];
        // A turn whose origin was never recorded — stored before the engine
        // kept it — gets no badge at all. The ungrounded badge is a claim, and
        // an old answer built from Moodle's own data would be labelled as the
        // model's own knowledge. Saying nothing is the only honest option;
        // citations still identify a grounded turn on their own.
        const originknown = !!origin || mappedSources.length > 0;

        return this.appendMessage({
            isassistant: true,
            sendername: this.config.persona || '',
            html: html,
            failed: !!iserror,
            hasconfirmation: !!confirm,
            confirmation: confirm,
            sources: visibleSources,
            hassources: visibleSources.length > 0,
            showmcpbadge: !iserror && ismcp,
            showgrounding: !iserror && !ismcp && originknown && this.config.showgrounding !== false,
            grounded: grounded,
            copylabel: strings.copy,
            retrylabel: strings.retry
        });
    }

    /**
     * Append a failed-answer bubble with a retry control.
     *
     * @param {object} error The error.
     * @return {Promise}
     */
    appendFailure(error) {
        const message = (error && error.message) ? error.message : strings.failed;
        return this.appendMessage({
            isassistant: true,
            sendername: this.config.persona || '',
            failuretext: message,
            failed: true,
            copylabel: strings.copy,
            retrylabel: strings.retry
        });
    }

    /**
     * Tell Moodle that content appeared, so the filters can act on it.
     *
     * Der MathJax-Loader haengt an diesem Ereignis und sucht in den gemeldeten
     * Knoten nach `.filter_mathjaxloader_equation`. Der Renderer setzt diese
     * Huelle um jede Formel, also findet der Loader hier etwas und setzt es.
     *
     * Der Weg ueber das Ereignis ist Moodles eigener: Wir sprechen MathJax
     * nicht selbst an, sondern melden nur, dass sich etwas geaendert hat. Ist
     * der Filter nicht aktiv, hoert schlicht niemand zu.
     *
     * @param {Element} node The bubble just added to the log.
     * @return {void}
     */
    notifyContentUpdated(node) {
        if (!node) {
            return;
        }
        notifyFilterContentUpdated([node]);

        // Und den MathJax-Loader direkt bitten. Das Ereignis allein genuegt
        // nicht: Im Browser nachgemessen blieben sieben Formeln ungesetzt,
        // bis typeset() aufgerufen wurde. Der Aufruf ist idempotent -- was
        // bereits gesetzt ist, bleibt es.
        //
        // Nur wenn window.MathJax gesetzt ist: configure() des Filters legt
        // es an. Fehlt es, ist der Filter auf dieser Website nicht aktiv, und
        // MathJax soll dann auch nicht nachgeladen werden.
        if (window.MathJax) {
            try {
                typesetMaths();
            } catch (e) {
                // Eine nicht gesetzte Formel darf den Chat nicht anhalten.
                return;
            }
        }
    }

    /**
     * Render and append a bubble.
     *
     * @param {object} context Template context.
     * @return {Promise}
     */
    appendMessage(context) {
        context.prefix = this.prefix;
        if (context.avatarurl === undefined) {
            context.avatarurl = this.config.avatarurl || '';
        }
        return Templates.render('local_elediaai_chatengine/message', context).then((html) => {
            const fragment = document.createElement('div');
            fragment.innerHTML = html.trim();
            // Select the wrapper explicitly rather than taking the first child,
            // so a render stays correct even if stray nodes precede it.
            const node = fragment.querySelector('[data-region="message"]') || fragment.firstElementChild;
            if (!node) {
                return null;
            }
            this.prepareAssistantLinks(node);
            // Never behind the typing indicator. A bubble is rendered through
            // Templates, so it arrives a tick later than the caller that asked
            // for it - and `send()` shows the dots in the same breath. Appended
            // blindly, the learner's own question landed *below* the dots that
            // are supposed to answer it. The indicator stays last in the log
            // instead, whichever render finishes when; with no indicator
            // showing, insertBefore(node, null) appends exactly as before.
            const typing = (this.typingEl && this.typingEl.parentNode === this.log) ? this.typingEl : null;
            this.log.insertBefore(node, typing);
            // Erst jetzt melden, nicht vorher: Der MathJax-Loader sucht im
            // Dokument. Ein Knoten, der noch im losgeloesten Fragment haengt,
            // wird nicht gesetzt -- beim Neuladen fiel deshalb der ganze
            // wiederhergestellte Verlauf durch.
            this.notifyContentUpdated(node);
            this.scrollToBottom();
            return node;
        }).catch(Notification.exception);
    }

    /**
     * Prepare links inside assistant messages.
     *
     * A Moodle page navigation destroys a floating chat panel. Links that
     * clearly point at a different course therefore open in a new tab; links
     * within the current course keep Moodle's normal same-tab behaviour.
     *
     * @param {HTMLElement} node Rendered message wrapper.
     * @return {void}
     */
    prepareAssistantLinks(node) {
        node.querySelectorAll('.' + this.prefix + '-markdown a[href]').forEach((link) => {
            const targetcourseid = this.courseIdFromLink(link);
            if (targetcourseid === null || targetcourseid === (this.config.courseid || 0)) {
                link.removeAttribute('target');
                link.removeAttribute('rel');
                return;
            }
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer');
        });
    }

    /**
     * Extract a course id from a Moodle URL when it states one.
     *
     * @param {HTMLAnchorElement} link Link to inspect.
     * @return {number|null} Course id, or null when the URL does not say.
     */
    courseIdFromLink(link) {
        const raw = link.getAttribute('href') || '';
        if (raw === '' || raw.charAt(0) === '#') {
            return null;
        }
        let url = null;
        try {
            url = new URL(raw, window.location.href);
        } catch (e) {
            return null;
        }
        const pathname = url.pathname || '';
        if (pathname.indexOf('/course/view.php') !== -1) {
            const id = parseInt(url.searchParams.get('id') || '0', 10);
            return id > 0 ? id : null;
        }
        const courseid = parseInt(url.searchParams.get('courseid') || '0', 10);
        return courseid > 0 ? courseid : null;
    }

    /**
     * Resolve a pending write action with an explicit decision.
     *
     * The decision travels as a flag rather than as matched yes/no text, and
     * the card's buttons are disabled to prevent a double submit.
     *
     * @param {HTMLElement} el The clicked card button.
     * @param {string} decision 'confirm' or 'decline'.
     * @return {void}
     */
    resolveConfirmation(el, decision) {
        if (!this.canSend()) {
            return;
        }
        const card = el.closest('[data-region="confirmation-actions"]');
        if (card) {
            card.querySelectorAll('button').forEach((button) => {
                button.disabled = true;
            });
        }
        const label = el.getAttribute('data-message') || el.textContent.trim();
        this.appendMessage({isuser: true, text: label, sendername: strings.you});
        this.dispatch(label, {pendingdecision: decision});
    }

    /**
     * Resend the last message after a failure.
     *
     * @param {HTMLElement} el The retry button.
     * @return {void}
     */
    retry(el) {
        if (!this.canSend() || !this.lastUserMessage) {
            return;
        }
        const bubble = el.closest('[data-region="message"]');
        if (bubble) {
            bubble.remove();
        }
        this.clearError();
        this.dispatch(this.lastUserMessage);
    }

    /**
     * Show the typing indicator.
     *
     * @return {void}
     */
    showTyping() {
        if (this.typingEl) {
            return;
        }
        this.typingEl = document.createElement('div');
        this.typingEl.className = this.prefix + '-message ' + this.prefix + '-assistant ' + this.prefix + '-typing';
        this.typingEl.setAttribute('aria-hidden', 'true');
        const bubble = document.createElement('div');
        bubble.className = this.prefix + '-bubble';
        for (let i = 0; i < 3; i++) {
            const dot = document.createElement('span');
            dot.className = this.prefix + '-dot';
            bubble.appendChild(dot);
        }
        this.typingEl.appendChild(bubble);
        this.log.appendChild(this.typingEl);
        this.scrollToBottom();
    }

    /**
     * Remove the typing indicator.
     *
     * @return {void}
     */
    hideTyping() {
        if (this.typingEl) {
            this.typingEl.remove();
            this.typingEl = null;
        }
    }

    /**
     * Copy the answer text of a bubble to the clipboard.
     *
     * @param {HTMLElement} el The copy button.
     * @return {void}
     */
    copyAnswer(el) {
        const bubble = el.closest('[data-region="message"]');
        const md = bubble ? bubble.querySelector('.' + this.prefix + '-markdown') : null;
        const text = md ? md.innerText : '';
        if (!text || !navigator.clipboard) {
            return;
        }
        navigator.clipboard.writeText(text).then(() => {
            this.setStatus(strings.copied);
            return null;
        }).catch(() => {
            // Clipboard refused (permission or insecure context): silently skip,
            // the answer is still selectable by hand.
        });
    }

    /**
     * Ask before dropping the conversation.
     *
     * Deleting a conversation cannot be undone. The activity this engine was
     * extracted from guaranteed a confirmation and the extraction lost it; a
     * destructive action one click away is not a detail to drop in a refactor.
     *
     * @param {HTMLElement} [trigger] The button that asked, to give focus back to.
     * @return {void}
     */
    clearConversation(trigger) {
        ModalSaveCancel.create({
            title: strings.clearlabel,
            body: strings.clearconfirm,
            show: true,
            removeOnClose: true
        }).then((modal) => {
            modal.setSaveButtonText(strings.clearlabel);
            modal.getRoot().on(ModalEvents.save, () => this.doClearConversation());
            // Whether they confirmed or not, the keyboard goes back where it
            // was rather than to the top of the page.
            modal.getRoot().on(ModalEvents.hidden, () => {
                if (trigger && typeof trigger.focus === 'function') {
                    trigger.focus();
                }
            });
            return modal;
        }).catch(Notification.exception);
    }

    /**
     * Drop the conversation, the confirmation having been given.
     *
     * @return {void}
     */
    doClearConversation() {
        Ajax.call([{
            methodname: this.methods().clear,
            args: {component: this.component, instanceid: this.instanceid, threadid: this.threadId || 0}
        }])[0].then(() => {
            this.resetLog();
            this.threadId = 0;
            this.saveThreadPointer(0);
            this.historyLoaded = false;
            this.clearError();
            this.setStatus(strings.cleared);
            if (this.input) {
                this.input.focus();
            }
            return null;
        }).catch(() => {
            // What is on screen still matches what is stored, so it stays put.
            // Emptying the log over a conversation that is still there would be
            // a lie about what just happened.
            this.showError(strings.clearfailed);
        });
    }

    /**
     * Remove every rendered message from the log.
     *
     * @return {void}
     */
    resetLog() {
        this.log.querySelectorAll('[data-region="message"]').forEach((m) => {
            if (!m.classList.contains(this.prefix + '-welcome')) {
                m.remove();
            }
        });
    }

    /**
     * Announce a failure in the assertive error region.
     *
     * Separate from the polite status region on purpose: a failed answer is
     * not a status change, and waiting for a pause to announce it would let a
     * user keep typing into a chat that is not working.
     *
     * @param {string} text The message.
     * @return {void}
     */
    showError(text) {
        if (!this.error) {
            this.setStatus(text);
            return;
        }
        // Emptied, not hidden: the hidden attribute takes the region out of the
        // accessibility tree, and the next error would then be written into
        // somewhere a screen reader is not listening. An :empty rule collapses
        // the box instead.
        this.error.textContent = text || '';
    }

    /**
     * Clear the error region.
     *
     * @return {void}
     */
    clearError() {
        this.showError('');
    }

    /**
     * Update the polite status region.
     *
     * @param {string} text Status text.
     * @return {void}
     */
    setStatus(text) {
        if (this.status) {
            this.status.textContent = text || '';
        }
    }

    /**
     * Scroll the log to the latest message.
     *
     * @return {void}
     */
    scrollToBottom() {
        const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const behavior = reduce ? 'auto' : 'smooth';
        // A page-scrolling layout has no inner scrollbar, so the newest entry
        // is brought into view through the page instead.
        if (this.config.pagescroll && this.log.scrollHeight <= this.log.clientHeight + 1) {
            const last = this.log.lastElementChild;
            if (last && last.scrollIntoView) {
                last.scrollIntoView({block: 'end', behavior: behavior});
            }
            return;
        }
        try {
            this.log.scrollTo({top: this.log.scrollHeight, behavior: behavior});
        } catch (e) {
            this.log.scrollTop = this.log.scrollHeight;
        }
    }
}

/**
 * Read the configuration island a placement rendered.
 *
 * The configuration travels in the DOM rather than through js_call_amd, whose
 * argument string Moodle truncates past 1024 characters — a limit a persona
 * plus a set of labels reaches easily.
 *
 * @param {HTMLElement} root The panel root.
 * @return {object} The configuration, or an empty object.
 */
export const readConfig = (root) => {
    const island = root.querySelector('[data-region="chatengine-config"]');
    if (!island) {
        return {};
    }
    try {
        return JSON.parse(island.textContent || '{}');
    } catch (e) {
        return {};
    }
};

/**
 * Load the shared strings, then build a panel.
 *
 * @param {string} uniqid The panel root element id.
 * @param {Function} [factory] Builds the controller; defaults to ChatPanel.
 * @return {void}
 */
export const init = (uniqid, factory) => {
    const root = document.getElementById(uniqid);
    if (!root || root.dataset.initialised) {
        return;
    }
    root.dataset.initialised = '1';

    const config = readConfig(root);
    config.uniqid = uniqid;

    // Nothing here may throw out of init(): Moodle marks the AMD call pending
    // and only clears it once this returns. An exception escaping would leave
    // the page permanently "not ready" - which is a puzzling way to report a
    // typo in a string key.
    try {
        const requests = STRING_DEFS.map(([, key]) => ({key: key, component: 'local_elediaai_chatengine'}));
        getStrings(requests).then((loaded) => {
            STRING_DEFS.forEach(([prop], index) => {
                strings[prop] = loaded[index];
            });
            const build = factory || ((element, cfg) => new ChatPanel(element, cfg));
            build(root, config);
            return null;
        }).catch(Notification.exception);
    } catch (error) {
        Notification.exception(error);
    }
};

export {strings};
