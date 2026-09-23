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

namespace block_elediaai_tutor\local;

use local_elediaai_chatengine\local\connection;
use local_elediaai_chatengine\local\token_provider;

/**
 * Builds the chat widget (shell HTML + AMD init) for any host page.
 *
 * The same widget is rendered in two places: inside the block (which passes
 * its per-instance configuration) and on the standalone page
 * blocks/elediaai_tutor/view.php used for Moodle App embedding (which uses the
 * site defaults). Keeping the assembly here means both stay in lockstep and
 * the block class stays thin.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class widget {
    /**
     * Detect a fatal configuration problem, returning an admin-facing message.
     *
     * The connector requirement is mode-aware. A grounded answer calls back into
     * Moodle with a user-scoped MCP token, so grounded mode requires the
     * webservice_elediamcp connector and a configured external service. LLM-only
     * mode never calls back, so it needs neither — only the RAG/Tutor server URL,
     * which every mode talks to. UNAVAILABLE is not a config error (handled by
     * render()'s own branch).
     *
     * @param int $courseid The course the widget is shown in (0 for global chat).
     * @param array $instance Per-instance config (its 'ragmode' is honoured).
     * @return string|null Null when configuration is healthy.
     */
    public static function config_error(int $courseid, array $instance = []): ?string {
        // A usable backend is required in every mode. Which one it is, and
        // whether it is reachable, is the engine's answer now - the block used
        // to keep its own endpoint setting and could disagree with it.
        if (!\local_elediaai_chatengine\backend_resolver::is_available()) {
            return get_string('error_backend_unavailable', 'local_elediaai_chatengine');
        }
        // Only grounded mode calls back into Moodle, so only it requires the
        // connector + a selected external service. (Whether the chosen service
        // actually exists is verified at call time by token_provider.)
        $blockconfig = (object) ['ragmode' => (string) ($instance['ragmode'] ?? chat_mode::MODE_GROUNDED)];
        if (chat_mode::resolve($courseid, $blockconfig) === chat_mode::MODE_GROUNDED) {
            if (!token_provider::is_connector_available()) {
                return get_string('error_connector_missing', 'block_elediaai_tutor');
            }
            if ((int) connection::get('mcpserviceid', 0) <= 0) {
                return get_string('error_service_not_configured', 'block_elediaai_tutor');
            }
        }
        return null;
    }

    /**
     * Whether a course has opted into the tutor.
     *
     * The opt-in signal is the teacher adding the tutor block to the course —
     * the same decision that controls the web UI. The standalone page and the
     * Moodle App course entry honour it, so the tutor never appears in courses
     * whose teachers did not choose it.
     *
     * @param int $courseid The course id.
     * @return bool
     */
    public static function course_has_tutor(int $courseid): bool {
        global $DB;
        $coursecontext = \core\context\course::instance($courseid, IGNORE_MISSING);
        if (!$coursecontext) {
            return false;
        }

        // Anywhere inside the course counts, not only the course page itself: a
        // teacher who places the tutor in an activity has opted that course in
        // just as deliberately. Matching the course context alone left such an
        // instance unable to chat ("notenabledincourse"), because the block
        // hangs off the module context.
        $like = $DB->sql_like('ctx.path', ':path');
        $sql = "SELECT 1
                  FROM {block_instances} bi
                  JOIN {context} ctx ON ctx.id = bi.parentcontextid
                 WHERE bi.blockname = :blockname
                       AND (ctx.id = :coursectx OR {$like})";
        return $DB->record_exists_sql($sql, [
            'blockname' => 'elediaai_tutor',
            'coursectx' => $coursecontext->id,
            'path' => $DB->sql_like_escape($coursecontext->path) . '/%',
        ]);
    }

    /**
     * Render the chat shell and queue its AMD initialisation.
     *
     * The caller is responsible for require_login and the use-capability check;
     * this method only assembles the (non-secret) widget.
     *
     * @param \context $context Context the AJAX calls will run against.
     * @param int $courseid Course id passed to the RAG server, 0 for global chat.
     * @param array $instance Per-instance config as a registry-key => value map
     *                        (plus 'instanceid' and the structural 'ragmode').
     *                        Empty values fall back to the site settings.
     * @return string The widget HTML.
     */
    /**
     * The admin custom CSS, scoped to the widget classes and returned at most
     * once per request (it is global, so emitting it per instance would
     * duplicate identical rules on pages with several blocks).
     *
     * @return string The custom CSS, or '' (also '' on subsequent calls).
     */
    private static function custom_css_once(): string {
        static $emitted = false;
        if ($emitted) {
            return '';
        }
        $css = security::custom_css();
        if ($css === '') {
            return '';
        }
        $emitted = true;
        return $css;
    }

    /**
     * Return a component string when installed, otherwise use a stable fallback.
     *
     * @param string $identifier String identifier.
     * @param string $fallback Fallback text.
     * @return string
     */
    private static function string_or_fallback(string $identifier, string $fallback): string {
        return get_string_manager()->string_exists($identifier, 'block_elediaai_tutor')
            ? get_string($identifier, 'block_elediaai_tutor')
            : $fallback;
    }

    /**
     * Render the tutor widget for a given context.
     *
     * @param \context $context The context the widget is shown in.
     * @param int $courseid Course id for course-scoped chat, or 0 for global.
     * @param array $instance Optional per-instance config overrides.
     * @return string The widget HTML.
     */
    public static function render(\context $context, int $courseid, array $instance = []): string {
        global $OUTPUT, $PAGE, $USER;

        // Inert mode: a non-interactive render for the settings live preview. The
        // chat module is not booted and the composer is disabled, so the preview is
        // visually faithful but never talks to the backend.
        $preview = !empty($instance['preview']);

        // Dashboard hero mode: only when the block flagged a /my/ page AND the
        // registry toggle allows it. The hero replaces the welcome bubble with a
        // prominent greeting and always renders inline, so it forces 'embedded'.
        // The hero is a personal, course-independent surface: it always chats
        // globally, even when the instance is wired to a course (a fixed course
        // id on the dashboard would otherwise fail the course-block gate).
        $dashboard = !empty($instance['dashboard'])
            && (int) registry::effective('dashboardenabled', $instance) === 1;
        if ($dashboard) {
            $courseid = 0;
        }

        // Resolve the answer mode (grounded / LLM-only / unavailable). When the
        // tutor cannot answer here (no knowledge base and LLM-only disallowed),
        // show a friendly notice and skip the chat UI entirely.
        $blockconfig = (object) ['ragmode' => (string) ($instance['ragmode'] ?? chat_mode::MODE_GROUNDED)];
        $mode = chat_mode::resolve($courseid, $blockconfig);
        if ($mode === chat_mode::MODE_UNAVAILABLE) {
            $isadmin = has_capability('moodle/site:config', \core\context\system::instance());
            $message = get_string('llmonly_unavailable', 'block_elediaai_tutor');
            if ($isadmin && chat_mode::course_is_released_not_indexed($courseid)) {
                $message = get_string('course_not_indexed_admin', 'block_elediaai_tutor');
            }
            return $OUTPUT->render_from_template('block_elediaai_tutor/unavailable', [
                'isadmin' => $isadmin,
                'message' => $message,
            ]);
        }

        // Behaviour settings resolved instance-over-site through the registry.
        // The hero keeps the chrome minimal: no history browser (the composer
        // and the chips are the whole surface).
        $displaymode = $dashboard ? 'embedded' : (string) registry::effective('displaymode', $instance);
        $historyenabled = ((int) registry::effective('historyenabled', $instance) === 1)
            && has_capability('block/elediaai_tutor:viewhistory', $context)
            && !$dashboard;
        $welcome = (string) registry::effective('welcomemessage', $instance);
        if (trim($welcome) === '') {
            // On the dashboard the hero greeting carries the opening, so an
            // unset welcome stays empty instead of the built-in default.
            $welcome = $dashboard ? '' : get_string('default_welcome', 'block_elediaai_tutor');
        }
        $persona = (string) registry::effective('persona', $instance);
        if (trim($persona) === '') {
            $persona = get_string('default_persona', 'block_elediaai_tutor');
        }

        // Pedagogical answer style: default plus whether learners may switch.
        // The hero hides the style chips entirely (slim surface); the default
        // style still applies server-side.
        $answerstyle = (string) registry::effective('answerstyle', $instance);
        if (!in_array($answerstyle, ['explain', 'hint', 'quiz'], true)) {
            $answerstyle = 'explain';
        }
        $allowstylechange = (int) registry::effective('allowstylechange', $instance) === 1 && !$dashboard;

        $uniqid = 'elediaai_tutor_' . uniqid();
        $consented = consent::has_consented((int) $USER->id);

        // Resolve institutional branding (per-instance overrides over the site
        // defaults). Two logos: the tutor logo (header + launcher) and the
        // conversation avatar (per-message). Each: instance upload ?: site
        // upload ?: (avatar) the logo ?: the built-in eLeDia mark.
        $brand = branding::resolve($instance);
        $defaultlogo = $OUTPUT->image_url('logo', 'block_elediaai_tutor')->out(false);
        $logourl = branding::instance_file_url($context, branding::INSTANCE_LOGO_FILEAREA)
            ?: branding::site_logo_url() ?: $defaultlogo;
        $avatarurl = branding::instance_file_url($context, branding::INSTANCE_AVATAR_FILEAREA)
            ?: branding::site_avatar_url() ?: $logourl;
        // The block resolves a full design from its own per-instance settings.
        // Those are handed to the engine as overrides rather than replaced by a
        // chat design, so a block branded before designs existed is untouched
        // by them - and a design still shows through wherever the block sets
        // nothing itself.
        $brandstyle = \local_elediaai_chatengine\local\design::css_variables(
            \local_elediaai_chatengine\local\design::resolve(
                (int) ($instance['chatdesign'] ?? -1),
                $brand['tokens'] ?? []
            )
        );

        // Institution-specific privacy guidelines (admin setting). When set, the
        // formatted text replaces the built-in informational sections of the
        // privacy dialogue; filters (e.g. multilang) apply at render time.
        $privacytext = (string) get_config('block_elediaai_tutor', 'privacyguidelinestext');
        $privacyhtml = trim(strip_tags($privacytext)) !== ''
            ? format_text($privacytext, FORMAT_HTML, ['context' => $context])
            : '';

        // Dashboard extras: greeting headline and the briefing chip. Empty
        // settings fall back to built-in lang strings (default_welcome pattern).
        $dashboardgreeting = '';
        $dashboardgreetinghtml = '';
        $briefing = false;
        $briefingprompt = '';
        if ($dashboard) {
            if ((int) registry::effective('dashboardgreetingenabled', $instance) === 1) {
                $dashboardgreeting = trim((string) registry::effective('dashboardgreeting', $instance));
                if ($dashboardgreeting === '') {
                    $dashboardgreeting = get_string('default_dashboardgreeting', 'block_elediaai_tutor');
                }
                // Personal touch: admins may greet by first name ("…, {firstname}?").
                $firstname = (string) ($USER->firstname ?? '');
                // Two forms of the same sentence. The plain one is what an
                // assistive reader and the page title get; the marked-up one
                // lets the design set the name apart from the greeting around
                // it. Both halves are escaped before they are joined, so the
                // marked-up form carries no markup but its own.
                $dashboardgreetinghtml = str_replace(
                    '{firstname}',
                    \html_writer::span(s($firstname), 'elediaai-chat-hero-name'),
                    s($dashboardgreeting)
                );
                $dashboardgreeting = str_replace('{firstname}', $firstname, $dashboardgreeting);
            }
            if ((int) registry::effective('briefingenabled', $instance) === 1) {
                $briefingprompt = trim((string) registry::effective('briefingprompt', $instance));
                if ($briefingprompt === '') {
                    $briefingprompt = get_string('default_briefingprompt', 'block_elediaai_tutor');
                }
                $briefing = $briefingprompt !== '';
            }
        }

        // Prompt starters: instance value, falling back to the site default
        // (resolved by the registry). One per line, capped so the welcome stays tidy.
        // On the dashboard the audience-specific list wins when it is non-empty:
        // managers fall back through the teacher list to the base list, teachers
        // straight to the base list; a fully unconfigured dashboard ships with
        // built-in pills per audience. Selection is cosmetic only — every prompt
        // still runs under the user's real capabilities server-side.
        $starterskey = 'promptstarters';
        $audience = user_audience::STUDENT;
        if ($dashboard) {
            $audience = user_audience::resolve((int) $USER->id);
            if (
                $audience === user_audience::MANAGER
                    && trim((string) registry::effective('promptstarters_manager', $instance)) !== ''
            ) {
                $starterskey = 'promptstarters_manager';
            } else if (
                $audience !== user_audience::STUDENT
                    && trim((string) registry::effective('promptstarters_teacher', $instance)) !== ''
            ) {
                $starterskey = 'promptstarters_teacher';
            }
        }
        $startersraw = (string) registry::effective($starterskey, $instance);
        if ($dashboard && trim($startersraw) === '') {
            $startersraw = get_string('default_promptstarters_' . $audience, 'block_elediaai_tutor');
        }
        $starters = self::parse_starters($startersraw);

        $styles = [];
        foreach (['explain', 'hint', 'quiz'] as $style) {
            $styles[] = [
                'key' => $style,
                'label' => get_string('answerstyle_' . $style, 'block_elediaai_tutor'),
                'active' => $style === $answerstyle,
            ];
        }

        $templatecontext = [
            'uniqid' => $uniqid,
            'instanceid' => (int) ($instance['instanceid'] ?? 0),
            'displaymode' => $displaymode,
            'embedded' => $displaymode === 'embedded',
            'preview' => $preview,
            'persona' => format_string($persona),
            'logourl' => $logourl,
            'avatarurl' => $avatarurl,
            'welcome' => format_text($welcome, FORMAT_MOODLE, ['context' => $context, 'filter' => false]),
            'historyenabled' => $historyenabled,
            'showexpand' => premium::has_feature(premium::FEATURE_CHAT_EXPAND),
            'expandlabel' => self::string_or_fallback('expandchat', 'Enlarge chat'),
            'collapselabel' => self::string_or_fallback('collapsechat', 'Shrink chat'),
            'launchlabel' => $brand['launchlabel'],
            'launcherstyle' => $brand['launcherstyle'],
            'launchfab' => $brand['launcherstyle'] === 'fab',
            'launchcompact' => $brand['launcherstyle'] === 'compact',
            'stylechoice' => $allowstylechange,
            'styles' => $styles,
            'stylelocked' => !$allowstylechange && $answerstyle !== 'explain' && !$dashboard,
            'lockedlabel' => get_string('answerstyle_' . $answerstyle, 'block_elediaai_tutor'),
            'consented' => $consented,
            'starters' => $starters,
            'hasstarters' => !empty($starters),
            'dashboardmode' => $dashboard,
            // The engine scrolls the page instead of an inner box when the
            // layout has no scrollbar of its own.
            'pagescroll' => $dashboard,
            'autoloadhistory' => $historyenabled,
            'dashboardgreeting' => format_string($dashboardgreeting),
            'dashboardgreetinghtml' => $dashboardgreetinghtml,
            'briefing' => $briefing,
            'briefinglabel' => get_string('briefing_button', 'block_elediaai_tutor'),
            'briefingsubtitle' => get_string('briefing_button_sub', 'block_elediaai_tutor'),
            'showchips' => !empty($starters) || $briefing,
            'llmonly' => $mode === chat_mode::MODE_LLMONLY,
            // Branding: CSS-variable overrides applied inline on the root AND the
            // panel. The panel re-declares the --eac-* tokens on itself (it is
            // portalled out of the root in overlay modes), so an inline style is
            // what reliably wins for both elements.
            'brandvars' => $brandstyle,
            // Im Dashboard traegt die Seite selbst eine Fusszeile. Die des Blocks
            // wuerde direkt darueber stehen und dieselbe Rolle doppelt besetzen;
            // in den ueberlagernden Modi gibt es keine andere, dort bleibt sie.
            'showfooter' => $brand['footertext'] !== '' && !$dashboard,
            'footertext' => $brand['footertext'],
            'customcss' => self::custom_css_once(),
        ];

        // Non-secret JS config. This can be large (institution privacy HTML,
        // brand variables), so it is embedded as a JSON data-island in the
        // template rather than passed through js_call_amd, whose argument
        // string Moodle caps at 1024 chars.
        $jsconfig = [
            'uniqid' => $uniqid,
            // The chat engine addresses a placement by component and instance;
            // for the tutor the instance is the course scope, 0 site-wide.
            'component' => 'block_elediaai_tutor',
            'instanceid' => $courseid,
            'prefix' => \local_elediaai_chatengine\output\chat_panel::CSS_PREFIX,
            'contextid' => $context->id,
            'courseid' => $courseid,
            'displaymode' => $displaymode,
            'historyenabled' => $historyenabled,
            'streaming' => connection::streaming_enabled(),
            'maxlength' => connection::max_message_length(),
            'persona' => format_string($persona),
            'avatarurl' => $avatarurl,
            'ltmenabled' => ltm::is_enabled((int) $USER->id),
            'candelete' => has_capability('block/elediaai_tutor:deleteownhistory', $context),
            'answerstyle' => $answerstyle,
            'allowstylechange' => $allowstylechange,
            'consented' => $consented,
            'privacyhtml' => $privacyhtml,
            'ragmode' => $mode,
            'dashboardmode' => $dashboard,
            // The engine scrolls the page instead of an inner box when the
            // layout has no scrollbar of its own.
            'pagescroll' => $dashboard,
            'autoloadhistory' => $historyenabled,
            // Plain text; the briefing chip sends this through the normal
            // composer path, so the server treats it like any typed message.
            'briefingprompt' => $briefing ? $briefingprompt : '',
            // Floating launcher is portalled to <body> by the JS so the block
            // drawer can't hide it.
            'launchfab' => $brand['launcherstyle'] === 'fab',
            'editing' => $PAGE->user_is_editing(),
            // Brand variables so JS-created modals (portalled to <body>) can be
            // themed too — see TutorChat.applyBrand().
            'brandvars' => $brandstyle,
        ];
        // JSON_HEX_TAG keeps any HTML in privacyhtml from closing the <script>.
        $templatecontext['configjson'] = json_encode(
            $jsconfig,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        // Inert settings preview: seed a short, representative conversation so the design
        // tokens that only appear on specific elements — the learner bubble, a grounded
        // answer with sources and code, and an error state — all have something to render
        // against. Built from the same message template the chat JS uses.
        if ($preview) {
            $samples = [
                [
                    'isuser' => true,
                    'sendername' => get_string('senderyou', 'block_elediaai_tutor'),
                    'text' => get_string('preview_learner_msg', 'block_elediaai_tutor'),
                ],
                [
                    'isassistant' => true,
                    'sendername' => format_string($persona),
                    'avatarurl' => $avatarurl,
                    'html' => get_string('preview_bot_msg', 'block_elediaai_tutor'),
                    'showgrounding' => true,
                    'grounded' => true,
                    'hassources' => true,
                    'sources' => [[
                        'num' => 1,
                        'title' => get_string('preview_source_title', 'block_elediaai_tutor'),
                        'hasurl' => false,
                        'snippet' => get_string('preview_source_snippet', 'block_elediaai_tutor'),
                    ]],
                    'copylabel' => get_string('copy', 'block_elediaai_tutor'),
                ],
                [
                    'isassistant' => true,
                    'sendername' => format_string($persona),
                    'avatarurl' => $avatarurl,
                    'failuretext' => get_string('preview_error_msg', 'block_elediaai_tutor'),
                    'failed' => true,
                    'copylabel' => get_string('copy', 'block_elediaai_tutor'),
                    'retrylabel' => get_string('retry', 'block_elediaai_tutor'),
                ],
            ];
            $previewmessages = '';
            foreach ($samples as $sample) {
                // The same bubble the live chat renders. A second copy for the
                // preview would drift, and a preview that lies about the look
                // is worse than none.
                $sample['prefix'] = \local_elediaai_chatengine\output\chat_panel::CSS_PREFIX;
                $previewmessages .= $OUTPUT->render_from_template(
                    'local_elediaai_chatengine/message',
                    $sample
                );
            }
            $templatecontext['previewmessages'] = $previewmessages;
        }

        $html = $OUTPUT->render_from_template('block_elediaai_tutor/launcher', $templatecontext);

        // Pass only the element id; the JS reads the rest from the data-island. The
        // settings live preview is inert — it boots no chat module (the design tokens
        // are re-themed client-side by instance_preview.js instead).
        if (!$preview) {
            $PAGE->requires->js_call_amd('block_elediaai_tutor/chat', 'init', [$uniqid]);
        }

        return $html;
    }

    /**
     * Parse configured prompt starters into chip descriptors.
     *
     * Each non-empty line is one chip. Segments are separated by "|"; before
     * the label an optional intent keyword and/or FontAwesome icon may appear
     * (in any order):
     *  - "action | fa-icon | Label | Prompt" — routing intent, icon, label, prompt;
     *  - "fa-icon | Label | Prompt" — icon, short label, full prompt;
     *  - "Label | Prompt" — short label, full prompt;
     *  - "Prompt" — the prompt doubles as the label (legacy format).
     * Intents: 'action' = Moodle tools only (no knowledge-base retrieval),
     * 'knowledge' = retrieval only, 'auto' = server decides (default).
     *
     * @param string $raw The raw multi-line setting value.
     * @return array<int,array{label: string, prompt: string, hasicon: bool, icon: string, intent: string}>
     */
    private static function parse_starters(string $raw): array {
        $starters = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $icon = '';
            $intent = '';
            while (count($parts) > 1) {
                if ($icon === '' && preg_match('/^fa-[a-z0-9-]+$/', $parts[0])) {
                    $icon = array_shift($parts);
                    continue;
                }
                if ($intent === '' && in_array($parts[0], ['action', 'knowledge', 'auto'], true)) {
                    $intent = array_shift($parts);
                    continue;
                }
                break;
            }
            $label = $parts[0];
            $prompt = count($parts) > 1 ? implode(' | ', array_slice($parts, 1)) : $label;
            if ($label === '' || $prompt === '') {
                continue;
            }
            // A starter may carry a second line, written after "::" in the
            // label: "Create course::Add a new course". The separator is a
            // double colon rather than another "|" because the prompt already
            // owns every "|" after the label, and a fourth positional part
            // could not be told apart from a prompt that contains one.
            $subtitle = '';
            if (str_contains($label, '::')) {
                [$label, $subtitle] = array_map('trim', explode('::', $label, 2));
            }
            if ($label === '') {
                continue;
            }
            $iconsvg = $icon !== '' ? icon::render($icon) : '';
            $starters[] = [
                'label' => format_string($label),
                'subtitle' => $subtitle === '' ? '' : format_string($subtitle),
                'hassubtitle' => $subtitle !== '',
                'prompt' => format_string($prompt),
                'hasicon' => $iconsvg !== '',
                'iconsvg' => $iconsvg,
                'intent' => $intent === 'auto' ? '' : $intent,
            ];
            if (count($starters) >= 6) {
                break;
            }
        }
        return $starters;
    }
}
