<?php
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

declare(strict_types=1);

namespace local_elediaai_chatengine\output;

use local_elediaai_chatengine\backend_resolver;
use local_elediaai_chatengine\local\connection;
use local_elediaai_chatengine\local\design;
use local_elediaai_chatengine\local\persona;
use local_elediaai_chatengine\placement\registry;
use renderer_base;
use templatable;

/**
 * Builds the chat shell a placement renders.
 *
 * Here so that a placement does not have to know which strings the shell needs,
 * which configuration the AMD module reads, or how the configuration reaches it
 * without being truncated. A placement supplies what is genuinely its own — the
 * component, the instance, the persona, its CSS prefix — and gets a panel.
 *
 * When no backend is usable this renders a configuration notice instead of a
 * composer. A chat box that accepts a question nobody will answer is worse than
 * an honest empty state, and administrators get the sentence that says what to
 * do about it while learners do not.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chat_panel implements templatable {
    /**
     * @var string The CSS prefix every chat surface renders under.
     *
     * One prefix, so one set of rules styles every surface and a design change
     * lands everywhere at once. A per-placement prefix made the surfaces look
     * alike only for as long as somebody kept them alike by hand.
     */
    public const CSS_PREFIX = 'elediaai-chat';

    /**
     * Constructor.
     *
     * @param string $component The placement's frankenstyle component.
     * @param int $instanceid The placement's own instance id, or 0 for site-wide.
     * @param \context $context The context the panel lives in.
     * @param persona $persona The persona whose name labels assistant turns.
     * @param string $prefix CSS class prefix for this placement.
     * @param array $config Extra configuration for the AMD module.
     * @param string $headerhtml Optional placement markup above the transcript.
     * @param string $footerhtml Optional placement markup below the composer.
     * @param string $logcontent Optional turns rendered server-side into the transcript.
     * @param int $designid Design picked on the instance; -1 inherits the site design.
     * @param array $designoverrides Token values the placement resolved for itself; these win.
     * @param bool $showheader Whether to paint the identity header.
     * @param string $avatarurl The assistant's mark; the engine's own is used when empty.
     */
    public function __construct(
        /** @var string The placement's component. */
        protected string $component,
        /** @var int The placement's instance id. */
        protected int $instanceid,
        /** @var \context The context. */
        protected \context $context,
        /** @var persona The persona. */
        protected persona $persona = new persona(),
        /** @var string CSS class prefix. */
        protected string $prefix = self::CSS_PREFIX,
        /** @var array Extra AMD configuration. */
        protected array $config = [],
        /** @var string Markup above the transcript. */
        protected string $headerhtml = '',
        /** @var string Markup below the composer. */
        protected string $footerhtml = '',
        /** @var string Turns rendered server-side into the transcript. */
        protected string $logcontent = '',
        /** @var int Design picked on the instance; -1 inherits the site design. */
        protected int $designid = -1,
        /** @var array Token values the placement resolved for itself. */
        protected array $designoverrides = [],
        /** @var bool Whether to paint the identity header. */
        protected bool $showheader = true,
        /** @var string The assistant's mark; the engine's own is used when empty. */
        protected string $avatarurl = '',
    ) {
    }

    /**
     * Whether a backend can answer here at all.
     *
     * @return bool
     */
    public function is_usable(): bool {
        return backend_resolver::is_available();
    }

    /**
     * The unique element id of this panel.
     *
     * @return string
     */
    public function uniqid(): string {
        return 'chatengine-' . $this->component . '-' . $this->instanceid . '-' . random_string(6);
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $component = 'local_elediaai_chatengine';
        $uniqid = $this->uniqid();

        self::mathjax_bereitstellen($this->context);

        // Streaming is offered only when the site allows it and the backend
        // that will actually answer reports it; promising it otherwise leaves
        // the interface waiting for fragments that never arrive.
        $adapter = backend_resolver::active();
        $streaming = $adapter !== null
            && connection::streaming_enabled()
            && $adapter->capabilities()->supportsstreaming;

        $coursecontext = $this->context->get_course_context(false);
        $courseid = $coursecontext !== false ? (int) $coursecontext->instanceid : 0;

        // The avatar the header wears is the same one every assistant turn
        // wears, so the two cannot drift apart. A placement that brands itself
        // passes its own; everything else gets the suite's mark rather than an
        // <img> with an empty src.
        $avatarurl = $this->avatarurl !== ''
            ? $this->avatarurl
            : $output->image_url('logo', $component)->out(false);

        $config = array_merge([
            'avatarurl' => $avatarurl,
            'component' => $this->component,
            'instanceid' => $this->instanceid,
            'prefix' => $this->prefix,
            'persona' => $this->persona->name,
            'maxlength' => connection::max_message_length(),
            'streaming' => $streaming,
            'courseid' => $courseid,
            'autoloadhistory' => $this->logcontent === '',
        ], $this->config);

        return [
            'prefix' => $this->prefix,
            'uniqid' => $uniqid,
            'component' => $this->component,
            'instanceid' => $this->instanceid,
            // Encoded for embedding in a <script type="application/json"> island:
            // js_call_amd truncates its argument string past 1024 characters, and
            // a persona plus a set of labels reaches that easily.
            //
            // JSON_HEX_TAG matters here: a persona or a label is free text, and
            // a "</script>" in it would end the island early and turn the rest
            // of the configuration into markup.
            'configjson' => json_encode(
                $config,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ),
            'transcriptlabel' => get_string('transcriptlabel', $component),
            'placeholder' => get_string('messageplaceholder', $component),
            'sendlabel' => get_string('send', $component),
            'sendaria' => get_string('send', $component),
            // Everything that renders this template sits in the page. The
            // tutor brings its own launcher and its own markup.
            'inline' => true,
            'showheader' => $this->showheader,
            'personaname' => $this->persona->name !== ''
                ? $this->persona->name
                : get_string('assistantname', $component),
            'presencelabel' => get_string('online', $component),
            'avatarurl' => $avatarurl,
            'monogram' => self::monogram(
                $this->persona->name !== ''
                    ? $this->persona->name
                    : get_string('assistantname', $component)
            ),
            // Asked of the placement rather than assumed: a surface that has
            // taken this conversation as work handed in must not offer to
            // throw it away. The endpoint refuses either way; this keeps the
            // panel from promising something it will then be denied.
            'showclear' => $this->may_clear(),
            'clearlabel' => get_string('clearconversation', $component),
            'showexpand' => true,
            'expandlabel' => get_string('expandlabel', $component),
            'collapselabel' => get_string('collapselabel', $component),
            'headerhtml' => $this->headerhtml,
            'footerhtml' => $this->footerhtml,
            'logcontent' => $this->logcontent,
            // Inline rather than in a stylesheet, so two differently designed
            // surfaces can sit on one page.
            'designvars' => design::css_variables(
                design::resolve($this->designid, $this->designoverrides)
            ),
        ];
    }

    /**
     * Load MathJax on this page, even though nothing on it needs typesetting yet.
     *
     * Der Filter laedt MathJax, waehrend er Text durchsieht. Eine Chatseite
     * bringt beim Aufbau aber keinen Text mit -- die Antworten kommen erst
     * spaeter aus dem Modell. MathJax war deshalb nie geladen, und die
     * Formeln blieben als `\(R_\text{ges}\)` stehen, obwohl der Renderer die
     * Trennzeichen inzwischen heil durchlaesst.
     *
     * `setup_page_for_filters()` ruft die `setup()`-Methode jedes im Kontext
     * aktiven Filters auf. Fuer MathJax ist das genau der Weg, auf dem Moodle
     * die Bibliothek sonst auch anfordert. Ist der Filter nicht aktiv,
     * passiert nichts -- dann bleibt es beim lesbaren Quelltext.
     *
     * @param \context $context Kontext, in dem der Chat steht.
     * @return void
     */
    protected static function mathjax_bereitstellen(\context $context): void {
        global $PAGE;

        if (!$PAGE instanceof \moodle_page) {
            return;
        }
        // Nur einmal je Seite: mehrere Chatflaechen sollen die Bibliothek nicht
        // mehrfach anfordern.
        static $erledigt = false;
        if ($erledigt) {
            return;
        }
        $erledigt = true;

        try {
            \filter_manager::instance()->setup_page_for_filters($PAGE, $context);
        } catch (\Throwable $e) {
            // Eine fehlende Formeldarstellung darf die Seite nicht verhindern.
            debugging('MathJax konnte nicht bereitgestellt werden: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * The single letter that stands in for a missing avatar.
     *
     * Multibyte-aware, because a persona may well be named in a script whose
     * first character is not one byte long.
     *
     * @param string $name The name to take the letter from.
     * @return string One upper-case character, or the empty string.
     */
    protected static function monogram(string $name): string {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        return \core_text::strtoupper(\core_text::substr($name, 0, 1));
    }

    /**
     * The configuration notice shown when no backend can answer.
     *
     * @param renderer_base $output The renderer.
     * @return string HTML.
     */
    public function render_unavailable(renderer_base $output): string {
        $component = 'local_elediaai_chatengine';
        $text = get_string('status_nobackend_help', $component);
        if (has_capability('moodle/site:config', $this->context)) {
            $text = get_string('status_nobackend_admin', $component);
        }

        return $output->notification(
            get_string('status_nobackend', $component) . ' ' . $text,
            \core\output\notification::NOTIFY_INFO,
            false
        );
    }

    /**
     * Whether this reader may still discard the conversation shown here.
     *
     * A placement that cannot be resolved -- a panel rendered for a component
     * that is not one -- keeps the button: the endpoint is the rule, and
     * hiding a control on a guess would take a working one away.
     *
     * @return bool
     */
    protected function may_clear(): bool {
        global $USER;

        $placement = registry::get($this->component);
        if ($placement === null) {
            return true;
        }

        return $placement->may_clear($this->instanceid, (int) $USER->id);
    }
}
