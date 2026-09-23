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
 * eLeDia.ai Tutor chat client.
 *
 * Owns all chat-panel behaviour: composing and sending messages over Moodle's
 * authenticated AJAX channel, rendering bubbles, streaming/typing state, copy,
 * retry, clear, history, and the docked/modal/fullscreen display modes. No
 * secrets are ever handled here — the browser only ever talks to Moodle, which
 * brokers the RAG/Tutor call server-side.
 *
 * @module     block_elediaai_tutor/chat
 * @copyright  2026 eLeDia GmbH, Berlin
 * @author     Christopher Reimann <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import Modal from 'core/modal';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import {get_strings as getStrings, get_string as getString} from 'core/str';
import {ChatPanel, init as initPanel} from 'local_elediaai_chatengine/chat_panel';

/** @var {object} Cached localised strings. */
let strings = {};

/** @var {Array} Strings to preload: [property, key, component (default block)]. */
const STRING_DEFS = [
    ['thinking', 'thinking'],
    ['failed', 'failed'],
    ['copy', 'copy'],
    ['copied', 'copied'],
    ['retry', 'retry'],
    ['newstarted', 'newstarted'],
    ['delete', 'delete', 'core'],
    ['toolong', 'toolong'],
    ['nohistorytool', 'nohistorytool'],
    ['you', 'senderyou'],
    ['privacytitle', 'privacyguidelines'],
    ['ltmsaved', 'ltm_saved'],
    ['deletetitle', 'deleteall_confirm_title'],
    ['deleteconfirm', 'deleteall_confirm'],
    ['deletebutton', 'deleteall_confirmbutton'],
    ['extdone', 'deleteall_external_done'],
    ['extunsupported', 'deleteall_external_unsupported'],
    ['resumed', 'conversation_resumed'],
    ['deleteonetitle', 'deletecurrent_confirm_title'],
    ['deleteoneconfirm', 'deletecurrent_confirm'],
    ['deleteonebutton', 'deletecurrent_confirmbutton'],
    ['deleteonedone', 'deletecurrent_done'],
];

/**
 * Per-instance controller for one block.
 */
class TutorChat extends ChatPanel {
    /**
     * Set up the tutor's own state and controls.
     *
     * Everything here runs from the engine's constructor, before it restores a
     * conversation — so the consent gate and the answer style are in place
     * before the first turn can be sent.
     *
     * @return {void}
     */
    bindPlacement() {
        this.consented = !!this.config.consented;
        this.ltmEnabled = !!this.config.ltmenabled;
        this.answerStyle = this.config.answerstyle || 'explain';
        this.pendingIntent = '';
        this.historyPanel = this.root.querySelector('[data-region="history"]');

        this.bindTutorControls();
        this.restoreStyle();
    }

    /**
     * Restore the user's last answer-style choice for this block (kept in
     * sessionStorage; the server still enforces the instance lock).
     *
     * @return {void}
     */
    restoreStyle() {
        if (!this.config.allowstylechange) {
            return;
        }
        let stored = null;
        try {
            stored = window.sessionStorage.getItem('elediaai_tutor_style_' + this.config.contextid);
        } catch (e) {
            return;
        }
        if (!stored || ['explain', 'hint', 'quiz'].indexOf(stored) === -1) {
            return;
        }
        this.answerStyle = stored;
        this.panel.querySelectorAll('[data-action="style"]').forEach((chip) => {
            const active = chip.dataset.style === stored;
            chip.classList.toggle('elediaai-chat-stylechip-active', active);
            chip.setAttribute('aria-checked', active ? 'true' : 'false');
        });
    }

    /**
     * Bind the controls only the tutor has.
     *
     * The composer, the delegated panel clicks and the enlarged view's key
     * handling belong to the engine and are bound there. What is left is the
     * launcher, which lives outside the panel, the consent checkbox, and
     * Escape for the overlay display modes the tutor alone has.
     *
     * @return {void}
     */
    bindTutorControls() {
        // The launch button is the only control outside the panel. In the
        // floating style it is portalled to <body> so it stays visible even
        // when the block sits in a collapsed drawer; it carries its brand
        // variables inline, so it stays themed once moved.
        const launch = this.root.querySelector('[data-action="launch"]');
        if (launch) {
            if (this.config.launchfab && launch.parentNode !== document.body) {
                document.body.appendChild(launch);
                this.hideFabBlockShell();
            }
            // Keep a handle: once portalled the button leaves the root, so
            // this.root.querySelector can no longer find it and open/close
            // need it to toggle aria-expanded.
            this.launch = launch;
            launch.addEventListener('click', () => this.open());
        }

        // The first-use consent checkbox arms the accept button.
        const consentCheck = this.panel.querySelector('[data-region="consent-checkbox"]');
        if (consentCheck) {
            consentCheck.addEventListener('change', () => {
                const accept = this.panel.querySelector('[data-action="consent-accept"]');
                if (accept) {
                    accept.disabled = !consentCheck.checked;
                }
            });
        }

        // Escape leaves an overlay. The engine handles the enlarged view; the
        // overlay display modes are the block's own.
        this.panel.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !this.expanded && this.isOverlay() && !this.isHidden()) {
                this.close();
            } else if (e.key === 'Tab' && this.isOverlay() && !this.expanded && !this.isHidden()) {
                this.trapFocus(e);
            }
        });
    }

    /**
     * Hide the empty Moodle block shell when the launcher is a floating button.
     *
     * @return {void}
     */
    hideFabBlockShell() {
        if (this.config.editing) {
            return;
        }
        const shell = this.root.closest('.block_elediaai_tutor, [data-block="elediaai_tutor"]');
        if (shell) {
            shell.classList.add('elediaai-chat-fab-shell-hidden');
            shell.setAttribute('aria-hidden', 'true');
        }
    }

    /**
     * Route the actions the tutor adds to the shared ones.
     *
     * @param {string} action The data-action value.
     * @param {HTMLElement} el The clicked element.
     * @return {void}
     */
    handlePlacementAction(action, el) {
        switch (action) {
            case 'launch': this.open(); break;
            case 'close':
                if (this.expanded) {
                    this.shrink();
                } else {
                    this.close();
                }
                break;
            case 'newconversation': this.newConversation(); break;
            case 'style': this.setStyle(el); break;
            case 'starter': this.useStarter(el); break;
            case 'briefing': this.useBriefing(); break;
            case 'consent-accept': this.giveConsent(el); break;
            case 'privacy': this.openPrivacy(); break;
            case 'history': this.toggleHistory(); break;
            case 'confirm-reply': this.confirmReply(el); break;
            case 'open-conversation': this.openConversation(el); break;
            case 'delete-conversation': this.deleteConversation(el); break;
            case 'delete-current': this.deleteCurrentConversation(); break;
            default: break;
        }
    }

    /**
     * @return {boolean} Whether the panel uses an overlay display mode.
     */
    isOverlay() {
        return this.config.displaymode !== 'embedded';
    }







    /**
     * Open the panel (overlay modes). Portals it to document.body so it cannot
     * be clipped by block-container overflow or z-index, then traps focus.
     *
     * @return {void}
     */
    open() {
        if (!this.isOverlay()) {
            return;
        }
        this.previousFocus = document.activeElement;
        if (this.panel.parentNode !== document.body) {
            document.body.appendChild(this.panel);
        }
        this.panel.classList.add('elediaai-chat-' + this.config.displaymode);
        this.panel.removeAttribute('hidden');
        if (this.backdrop && this.config.displaymode === 'modal') {
            this.backdrop.removeAttribute('hidden');
        }
        document.body.classList.toggle('elediaai-chat-noscroll',
            this.config.displaymode === 'fullscreen' || this.config.displaymode === 'modal');
        if (this.launch) {
            this.launch.setAttribute('aria-expanded', 'true');
        }
        this.updateDialogRole();
        window.setTimeout(() => this.input && this.input.focus(), 50);
    }

    /**
     * Close the panel (overlay modes) and restore focus.
     *
     * @return {void}
     */
    close() {
        if (!this.isOverlay()) {
            return;
        }
        if (this.expanded) {
            this.shrink();
        }
        this.panel.setAttribute('hidden', 'hidden');
        if (this.backdrop) {
            this.backdrop.setAttribute('hidden', 'hidden');
        }
        document.body.classList.remove('elediaai-chat-noscroll');
        this.updateDialogRole();
        const launch = this.launch;
        if (launch) {
            launch.setAttribute('aria-expanded', 'false');
        }
        if (this.previousFocus && typeof this.previousFocus.focus === 'function') {
            this.previousFocus.focus();
        }
    }



    /**
     * The tutor also requires a documented consent before anything is sent.
     *
     * The server enforces this independently; the gate here only keeps the
     * interface from offering something it knows will be refused.
     *
     * @return {boolean} Whether a turn may be sent right now.
     */
    canSend() {
        return !this.busy && this.consented;
    }

    /**
     * Send, and put the starter suggestions away once a conversation begins.
     *
     * @return {void}
     */
    send() {
        this.hideStarters();
        super.send();
    }

    /**
     * Send through the tutor's own endpoint.
     *
     * Only the send: the transcript, the conversation list and deleting one are
     * the same question for every placement and stay with the engine.
     *
     * @return {object} Method names keyed by send, history and clear.
     */
    methods() {
        return Object.assign(super.methods(), {
            send: 'block_elediaai_tutor_send_message',
        });
    }

    /**
     * Stream through the tutor's own endpoint.
     *
     * Both transports have to move together — a tutor that streamed through the
     * shared endpoint would be configured by the site defaults whenever
     * streaming is switched on, and by its own instance when it is off.
     *
     * @return {string} Absolute URL of the tutor's streaming endpoint.
     */
    streamUrl() {
        return M.cfg.wwwroot + '/blocks/elediaai_tutor/stream.php';
    }

    /**
     * Name the surface, and add the tutor's own hints.
     *
     * Not the shared shape: the tutor's endpoint is addressed by the context
     * this panel was rendered in rather than by a placement instance id,
     * because the conversation belongs to the course while the configuration
     * belongs to the block instance. The server proves the pairing.
     *
     * The answer style travels with every turn; the routing intent only when a
     * starter pill set one, and it is consumed by the turn it belongs to.
     *
     * @param {string} message The user message.
     * @return {object} Web service arguments.
     */
    turnArgs(message) {
        const intent = this.pendingIntent || '';
        this.pendingIntent = '';

        return {
            contextid: this.config.contextid,
            courseid: this.config.courseid || 0,
            message: message,
            threadid: this.threadId || 0,
            newthread: this.pendingNewThread ? 1 : 0,
            answerstyle: this.answerStyle || '',
            intent: intent
        };
    }

    /**
     * Send a suggested starter question.
     *
     * @param {HTMLElement} el The clicked starter chip.
     * @return {void}
     */
    useStarter(el) {
        if (this.busy || !this.consented) {
            return;
        }
        // Pills carry the full prompt in data-prompt (the label is short);
        // legacy chips without it send their visible text. An optional
        // data-intent routes the turn server-side (action = Moodle tools only).
        this.input.value = (el.dataset.prompt || el.textContent || '').trim();
        this.pendingIntent = el.dataset.intent || '';
        this.autoGrow();
        this.send();
    }

    /**
     * Send the configured briefing prompt (dashboard hero mode).
     *
     * The prompt travels the normal composer path, so the server treats it
     * like any typed message; the chip region is hidden by send() as usual.
     *
     * @return {void}
     */
    useBriefing() {
        if (this.busy || !this.consented || !this.config.briefingprompt) {
            return;
        }
        this.input.value = this.config.briefingprompt;
        // The briefing is an action prompt: Moodle tools, no KB retrieval.
        this.pendingIntent = 'action';
        this.autoGrow();
        this.send();
    }

    /**
     * Hide the starter chips (a conversation is underway).
     *
     * @return {void}
     */
    hideStarters() {
        // Hero mode: the pills are a permanent quick-action row under the
        // composer and stay available throughout the conversation; only the
        // greeting leaves once the log owns the panel (Claude-style). The
        // classic in-log chips still hide with the first message.
        if (!this.config.dashboardmode) {
            const starters = this.panel.querySelector('[data-region="starters"]');
            if (starters) {
                starters.setAttribute('hidden', 'hidden');
            }
        }
        const hero = this.panel.querySelector('[data-region="hero"]');
        if (hero) {
            hero.setAttribute('hidden', 'hidden');
        }
        this.toggleHeroTools(true);
    }

    /**
     * Show the starter chips again (fresh conversation).
     *
     * @return {void}
     */
    showStarters() {
        const starters = this.panel.querySelector('[data-region="starters"]');
        if (starters) {
            starters.removeAttribute('hidden');
        }
        const hero = this.panel.querySelector('[data-region="hero"]');
        if (hero) {
            hero.removeAttribute('hidden');
        }
        this.toggleHeroTools(false);
    }











    /**
     * Send a structured confirmation reply from a rendered assistant button.
     *
     * @param {HTMLElement} el The clicked confirmation button.
     * @return {void}
     */
    confirmReply(el) {
        const message = el.getAttribute('data-message') || '';
        if (!message || this.busy || !this.consented) {
            return;
        }
        this.input.value = message;
        this.autoGrow();
        this.send();
    }









    /**
     * Start a new conversation.
     *
     * The previous conversation is kept: nothing is deleted here, the next turn
     * simply opens a fresh thread beside it and the old one stays in the
     * history list.
     *
     * Dropping the pointer is not enough to say that. A turn that names no
     * conversation means "carry on where I left off" -- which is what a
     * reopened panel wants and the opposite of what this button asks for -- so
     * the wish travels with the turn and the engine opens the new thread.
     *
     * @return {void}
     */
    newConversation() {
        if (!this.threadId && this.log.querySelectorAll('[data-region="message"]').length === 0) {
            this.input.focus();
            return;
        }
        this.resetLog();
        this.startNewThread();
        this.showStarters();
        this.setStatus(strings.newstarted);
        this.input.focus();
    }

    /**
     * Select an answer style chip.
     *
     * @param {HTMLElement} el The clicked chip.
     * @return {void}
     */
    setStyle(el) {
        const style = el.dataset.style;
        if (!style || !this.config.allowstylechange) {
            return;
        }
        this.answerStyle = style;
        this.panel.querySelectorAll('[data-action="style"]').forEach((chip) => {
            const active = chip === el;
            chip.classList.toggle('elediaai-chat-stylechip-active', active);
            chip.setAttribute('aria-checked', active ? 'true' : 'false');
        });
        try {
            window.sessionStorage.setItem('elediaai_tutor_style_' + this.config.contextid, style);
        } catch (e) {
            // Storage unavailable (private mode): the choice still applies for this page.
        }
        this.input.focus();
    }


    /**
     * Record the user's privacy-guidelines acknowledgement and unlock the chat.
     *
     * The server stores the documented consent (timestamped row + audit event)
     * and enforces the gate independently of this UI.
     *
     * @param {HTMLElement} el The accept button.
     * @return {void}
     */
    giveConsent(el) {
        if (this.consented) {
            return;
        }
        el.disabled = true;
        Ajax.call([{
            methodname: 'block_elediaai_tutor_give_consent',
            args: {contextid: this.config.contextid}
        }])[0].then((response) => {
            if (!response.consented) {
                el.disabled = false;
                return null;
            }
            this.consented = true;
            const region = this.panel.querySelector('[data-region="consent"]');
            if (region) {
                region.setAttribute('hidden', 'hidden');
            }
            if (this.input) {
                this.input.removeAttribute('disabled');
            }
            const sendBtn = this.panel.querySelector('[data-action="send"]');
            if (sendBtn) {
                sendBtn.removeAttribute('disabled');
            }
            this.input.focus();
            return null;
        }).catch((error) => {
            el.disabled = false;
            Notification.exception(error);
        });
    }

    /**
     * Open the privacy guidelines modal (accuracy warning, data flows,
     * long-term memory opt-in and the delete-my-data control).
     *
     * @return {void}
     */
    openPrivacy() {
        Templates.render('block_elediaai_tutor/privacy_info', {
            uniqid: this.config.uniqid,
            ltmenabled: this.ltmEnabled,
            candelete: !!this.config.candelete,
            // Institution-specific guidelines, already formatted/sanitised
            // server-side; replaces the built-in informational sections.
            hascustom: !!this.config.privacyhtml,
            customtext: this.config.privacyhtml || ''
        }).then((html) => Modal.create({
            title: strings.privacytitle,
            body: html,
            large: true,
            show: true,
            removeOnClose: true
        })).then((modal) => {
            this.applyBrand(modal);
            this.bindPrivacyModal(modal);
            return modal;
        }).catch(Notification.exception);
    }

    /**
     * Theme a portalled modal with the widget's brand variables.
     *
     * Core modals are appended to <body>, outside the widget root/panel, so
     * they don't inherit the --eac-* tokens. We set them inline on the modal
     * root and tag it so the themed-modal CSS applies.
     *
     * @param {Object} modal The created modal instance.
     * @return {void}
     */
    applyBrand(modal) {
        const root = modal.getRoot()[0];
        if (!root) {
            return;
        }
        root.classList.add('elediaai-chat-modal');
        if (this.config.brandvars) {
            root.setAttribute('style',
                (root.getAttribute('style') || '') + this.config.brandvars);
        }
    }

    /**
     * Wire up the controls inside the privacy modal.
     *
     * The modal lives outside the chat panel, so it gets its own listeners.
     *
     * @param {Object} modal The created modal instance.
     * @return {void}
     */
    bindPrivacyModal(modal) {
        const root = modal.getRoot()[0];
        const toggle = root.querySelector('[data-region="ltm-toggle"]');
        if (toggle) {
            toggle.addEventListener('change', () => this.saveLtm(toggle, root));
        }
        const deletebtn = root.querySelector('[data-action="delete-all"]');
        if (deletebtn) {
            deletebtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.confirmDeleteAll(root);
            });
        }
    }

    /**
     * Persist the long-term memory opt-in via AJAX.
     *
     * @param {HTMLInputElement} toggle The checkbox.
     * @param {HTMLElement} root The modal root element.
     * @return {void}
     */
    saveLtm(toggle, root) {
        Ajax.call([{
            methodname: 'block_elediaai_tutor_set_ltm',
            args: {contextid: this.config.contextid, enabled: toggle.checked}
        }])[0].then((response) => {
            this.ltmEnabled = !!response.enabled;
            const status = root.querySelector('[data-region="ltm-status"]');
            if (status) {
                status.textContent = strings.ltmsaved;
            }
            return null;
        }).catch((error) => {
            // Revert the checkbox so the UI never lies about the stored state.
            toggle.checked = this.ltmEnabled;
            Notification.exception(error);
        });
    }

    /**
     * Ask for confirmation before erasing all tutor data.
     *
     * @param {HTMLElement} privacyroot The privacy modal root element.
     * @return {void}
     */
    confirmDeleteAll(privacyroot) {
        ModalSaveCancel.create({
            title: strings.deletetitle,
            body: strings.deleteconfirm,
            show: true,
            removeOnClose: true
        }).then((modal) => {
            this.applyBrand(modal);
            modal.setSaveButtonText(strings.deletebutton);
            modal.getRoot().on(ModalEvents.save, () => this.performDeleteAll(privacyroot));
            return modal;
        }).catch(Notification.exception);
    }

    /**
     * Delete all of the user's tutor data and reset the chat UI.
     *
     * @param {HTMLElement} privacyroot The privacy modal root element.
     * @return {void}
     */
    performDeleteAll(privacyroot) {
        Ajax.call([{
            methodname: 'block_elediaai_tutor_delete_my_data',
            args: {contextid: this.config.contextid}
        }])[0].then(async(response) => {
            this.resetLog();
            this.startNewThread();
            this.showStarters();
            if (this.historyPanel) {
                const list = this.historyPanel.querySelector('[data-region="history-list"]');
                const empty = this.historyPanel.querySelector('[data-region="history-empty"]');
                if (list) {
                    list.innerHTML = '';
                }
                if (empty) {
                    empty.hidden = false;
                }
            }
            const status = privacyroot.querySelector('[data-region="delete-status"]');
            if (status) {
                const done = await getString('deleteall_done', 'block_elediaai_tutor', response.localdeleted);
                let externalstatus = strings.extunsupported;
                if (response.externalsupported) {
                    externalstatus = response.externalfailed > 0
                        ? await getString(
                            'deleteall_external_failed',
                            'block_elediaai_tutor',
                            response.externalfailed
                        )
                        : strings.extdone;
                }
                status.textContent = done + ' ' + externalstatus;
            }
            this.ltmEnabled = false;
            const toggle = privacyroot.querySelector('[data-region="ltm-toggle"]');
            if (toggle) {
                toggle.checked = false;
            }
            this.requireConsent();
            return null;
        }).catch(Notification.exception);
    }

    /**
     * Re-arm the documented first-use consent gate after complete deletion.
     *
     * @return {void}
     */
    requireConsent() {
        this.consented = false;
        const region = this.panel.querySelector('[data-region="consent"]');
        if (region) {
            region.removeAttribute('hidden');
            const checkbox = region.querySelector('[data-region="consent-checkbox"]');
            const accept = region.querySelector('[data-action="consent-accept"]');
            if (checkbox) {
                checkbox.checked = false;
            }
            if (accept) {
                accept.disabled = true;
            }
        }
        if (this.input) {
            this.input.disabled = true;
        }
        const sendbtn = this.panel.querySelector('[data-action="send"]');
        if (sendbtn) {
            sendbtn.disabled = true;
        }
    }

    /**
     * Toggle the history panel, loading conversations on first open.
     *
     * @return {void}
     */
    toggleHistory() {
        if (!this.historyPanel) {
            return;
        }
        const willShow = this.historyPanel.hasAttribute('hidden');
        if (willShow) {
            this.historyPanel.removeAttribute('hidden');
            if (!this.historyLoaded) {
                this.loadConversationList();
            }
        } else {
            this.historyPanel.setAttribute('hidden', 'hidden');
        }
    }

    /**
     * Load and render the conversation list.
     *
     * Named apart from the engine's loadHistory(), which replays the turns of
     * one conversation. Two different questions, and calling both "history"
     * was how the old module ended up with one method answering neither.
     *
     * @return {void}
     */
    loadConversationList() {
        const list = this.historyPanel.querySelector('[data-region="history-list"]');
        const empty = this.historyPanel.querySelector('[data-region="history-empty"]');
        Ajax.call([{
            methodname: 'local_elediaai_chatengine_list_conversations',
            args: {component: this.component, instanceid: this.instanceid}
        }])[0].then((response) => {
            this.historyLoaded = true;
            const conversations = (response.conversations || []).map((c) => ({
                id: c.id,
                title: c.title,
                preview: c.preview,
                deletelabel: strings.delete
            }));
            empty.hidden = conversations.length > 0;
            if (!conversations.length) {
                list.innerHTML = '';
                return null;
            }
            return Templates.render('block_elediaai_tutor/conversation_list', {conversations: conversations});
        }).then((html) => {
            if (html) {
                list.innerHTML = html;
            }
            return null;
        }).catch(Notification.exception);
    }

    /**
     * Open a stored conversation and load its messages.
     *
     * @param {HTMLElement} el The clicked item button.
     * @return {void}
     */
    openConversation(el) {
        const item = el.closest('[data-region="conversation"]');
        if (!item) {
            return;
        }
        this.loadConversation(parseInt(item.getAttribute('data-id'), 10)).then(() => {
            if (this.historyPanel) {
                this.historyPanel.setAttribute('hidden', 'hidden');
            }
            this.input.focus();
            return null;
        }).catch(Notification.exception);
    }

    /**
     * Make a conversation the active one and replay its turns.
     *
     * The turns come from this site's own store through the engine, not from
     * the backend: a learner reopening a conversation must not depend on an
     * external service being reachable.
     *
     * @param {number} threadid The conversation id.
     * @return {Promise}
     */
    loadConversation(threadid) {
        return Ajax.call([{
            methodname: 'local_elediaai_chatengine_load_history',
            args: {component: this.component, instanceid: this.instanceid, threadid: threadid}
        }])[0].then((response) => {
            this.threadId = parseInt(response.threadid || 0, 10);
            this.saveThreadPointer(this.threadId);
            this.pendingNewThread = false;
            this.resetLog();
            this.hideStarters();
            const renders = (response.messages || []).map((m) => {
                const isuser = m.role === 'user';
                return this.appendMessage({
                    isuser: isuser,
                    isassistant: !isuser,
                    sendername: isuser ? strings.you : this.config.persona,
                    text: '',
                    html: m.html,
                    showgrounding: !isuser,
                    copylabel: strings.copy,
                    retrylabel: strings.retry
                });
            });
            return Promise.all(renders);
        });
    }

    /**
     * Delete a stored conversation.
     *
     * @param {HTMLElement} el The delete button.
     * @return {void}
     */
    deleteConversation(el) {
        const item = el.closest('[data-region="conversation"]');
        if (!item) {
            return;
        }
        const id = parseInt(item.getAttribute('data-id'), 10);
        Ajax.call([{
            methodname: 'local_elediaai_chatengine_clear_conversation',
            args: {component: this.component, instanceid: this.instanceid, threadid: id}
        }])[0].then(() => {
            if (id === this.threadId) {
                // The conversation the panel was in is gone. Without the wish
                // the next turn would land in whatever conversation is now the
                // most recent - an older one the learner did not reopen.
                this.startNewThread();
            }
            this.dropHistoryItem(id);
            return null;
        }).catch(Notification.exception);
    }

    /**
     * Take one conversation out of the history list, if it is rendered.
     *
     * Shared with the bin in the header, so the empty state is decided in one
     * place: a list that has lost its last entry has to say so, and one that
     * still holds an entry must not. The list may not be rendered at all --
     * nobody has opened the history browser yet -- and then there is nothing
     * here to do.
     *
     * @param {number} threadid The conversation that is gone.
     * @return {void}
     */
    dropHistoryItem(threadid) {
        if (!this.historyPanel) {
            return;
        }
        const item = this.historyPanel.querySelector(
            '[data-region="conversation"][data-id="' + threadid + '"]');
        if (item) {
            item.remove();
        }
        const list = this.historyPanel.querySelector('[data-region="history-list"]');
        const empty = this.historyPanel.querySelector('[data-region="history-empty"]');
        const remaining = list ? list.querySelectorAll('[data-region="conversation"]').length : 0;
        if (list && !remaining) {
            list.innerHTML = '';
            if (empty) {
                empty.hidden = false;
            }
        }
    }

    /**
     * Show or hide the quiet corner in the dashboard footer.
     *
     * It follows the conversation, and it is the counterpart of the hero: the
     * greeting and the shield beneath it leave with the first message, this row
     * arrives with it. The bin has nothing to delete on an empty dashboard, and
     * the shield there is already under the greeting -- showing both at once
     * would put the same control on the page twice.
     *
     * Off the dashboard the template renders no such row and this does nothing.
     *
     * @param {boolean} visible Whether a conversation is underway.
     * @return {void}
     */
    toggleHeroTools(visible) {
        this.panel.querySelectorAll('[data-region="herotools"] button')
            .forEach((btn) => {
                btn.hidden = !visible;
            });
    }

    /**
     * Delete the conversation that is open, and only that one.
     *
     * The dashboard offers no way to begin a second conversation on purpose --
     * there is one and it is the page -- so what it needs is the way to end
     * this one. The other conversations are not touched; for those there is the
     * history browser, and for all of them at once the privacy dialog.
     *
     * Irreversible, so it asks first, in the same shape as the question behind
     * "delete all my data" and one step down in scope. The id is read before
     * the dialog opens: the pointer can move while it stands open, and the
     * conversation that gets deleted has to be the one that was asked about.
     *
     * @return {void}
     */
    deleteCurrentConversation() {
        if (!this.threadId) {
            // Nothing stored under a conversation yet. There can still be a log
            // to clear -- a first turn may be in flight -- but nothing to ask
            // about: what only this page holds is not worth a dialog.
            this.resetLog();
            this.startNewThread();
            this.showStarters();
            this.input.focus();
            return;
        }
        const threadid = this.threadId;
        ModalSaveCancel.create({
            title: strings.deleteonetitle,
            body: strings.deleteoneconfirm,
            show: true,
            removeOnClose: true
        }).then((modal) => {
            this.applyBrand(modal);
            modal.setSaveButtonText(strings.deleteonebutton);
            modal.getRoot().on(ModalEvents.save, () => this.performDeleteCurrent(threadid));
            return modal;
        }).catch(Notification.exception);
    }

    /**
     * Erase one conversation and put the panel back to its opening state.
     *
     * @param {number} threadid The conversation to erase.
     * @return {void}
     */
    performDeleteCurrent(threadid) {
        Ajax.call([{
            methodname: 'local_elediaai_chatengine_clear_conversation',
            args: {component: this.component, instanceid: this.instanceid, threadid: threadid}
        }])[0].then(() => {
            this.dropHistoryItem(threadid);
            this.resetLog();
            // Dropping the pointer is not enough: a turn that names no
            // conversation carries on in the most recent one, and that is now
            // an older conversation nobody asked to reopen.
            this.startNewThread();
            this.showStarters();
            this.setStatus(strings.deleteonedone);
            this.input.focus();
            return null;
        }).catch(Notification.exception);
    }



}

/**
 * Initialise a block instance.
 *
 * The engine loads the shared strings and reads the configuration island, then
 * hands both to the controller it is given. Only the element id travels through
 * js_call_amd, whose argument string Moodle truncates past 1024 characters.
 *
 * This must stay a named export: widget::render() invokes it through
 * $PAGE->requires->js_call_amd(..., 'init').
 *
 * @param {string} uniqid The widget root element id.
 * @return {void}
 */
export const init = (uniqid) => {
    initPanel(uniqid, (root, config) => {
        const requests = STRING_DEFS.map(([, key, component]) => ({
            key: key,
            component: component || 'block_elediaai_tutor'
        }));
        return getStrings(requests).then((loaded) => {
            STRING_DEFS.forEach(([prop], index) => {
                strings[prop] = loaded[index];
            });
            return new TutorChat(root, config);
        }).catch(Notification.exception);
    });
};
