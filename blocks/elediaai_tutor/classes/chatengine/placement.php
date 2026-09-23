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

namespace block_elediaai_tutor\chatengine;

use block_elediaai_tutor\external\helper;
use block_elediaai_tutor\local\branding;
use block_elediaai_tutor\local\chat_mode;
use block_elediaai_tutor\local\consent;
use block_elediaai_tutor\local\ltm;
use block_elediaai_tutor\local\registry;
use block_elediaai_tutor\local\security;
use local_elediaai_chatengine\local\connection;
use local_elediaai_chatengine\local\mode;
use local_elediaai_chatengine\local\persona;
use local_elediaai_chatengine\local\system_prompt;
use local_elediaai_chatengine\placement\placement as placement_contract;

/**
 * The tutor block as a chat engine placement.
 *
 * The instance id here is the **course** a conversation belongs to, 0 for the
 * site-wide chat. That is the scope the tutor has always kept its
 * conversations under, and keeping it means a learner's course chat and their
 * global chat stay separate threads — which they would not if the block
 * instance were the key, since one block can serve both.
 *
 * Everything this class answers is the block's own: which course scope is
 * being addressed, whether this person may chat there, the persona a teacher
 * configured, and whether the course has material to ground an answer in. The
 * conversation, the backend and the safety framing belong to the engine.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placement implements placement_contract {
    /** @var \context|null The surface this request's turn was sent from. */
    private static ?\context $surface = null;

    #[\Override]
    public static function component(): string {
        return 'block_elediaai_tutor';
    }

    /**
     * Name the surface a turn was sent from, for the rest of this request.
     *
     * The tutor is configured per block instance, but a conversation belongs to
     * the **course** — the standalone page answers for any course through
     * `view.php?courseid=`, so there is no block instance to key a thread on.
     * One instance id cannot be both, which is why the surface travels beside
     * it rather than inside it. Without one, a turn falls back to the site
     * defaults, which is what the shared endpoint has always produced.
     *
     * The context is client-named and therefore proven here before it is
     * believed: {@see helper::resolve_scoped_context()} refuses a context that
     * does not belong to this scope, and the capability is required on the
     * instance itself, so a prohibition set on one block still bites when the
     * conversation is keyed on the course.
     *
     * Static because the engine builds its own placement — the caller has no
     * instance to hand this to. Request-scoped, and callers clear it in a
     * `finally`, so nothing leaks into a second turn.
     *
     * @param int $contextid The context the client says it is chatting from.
     * @param int $courseid The authoritative course id, or 0 for global chat.
     * @return \context The proven surface.
     * @throws \moodle_exception When the context does not belong to this scope.
     */
    public static function use_surface(int $contextid, int $courseid): \context {
        $context = helper::resolve_scoped_context($contextid, $courseid);
        require_capability('block/elediaai_tutor:use', $context);
        self::$surface = $context;

        return $context;
    }

    /**
     * Forget the surface again.
     *
     * @return void
     */
    public static function forget_surface(): void {
        self::$surface = null;
    }

    /**
     * The instance configuration a turn in this scope runs with.
     *
     * The surface when one was proven, otherwise the scope's own context —
     * which is a course or the system and therefore carries no instance
     * configuration, so the site defaults apply.
     *
     * @param int $instanceid The course id, or 0.
     * @return \stdClass The instance configuration, possibly empty.
     */
    private function instance_config(int $instanceid): \stdClass {
        return helper::block_config(self::$surface ?? $this->context($instanceid));
    }

    /**
     * The context a conversation in this scope happens in.
     *
     * @param int $instanceid The course id, or 0 for the site-wide chat.
     * @return \context The course or system context.
     */
    #[\Override]
    public function context(int $instanceid): \context {
        return $instanceid > 0
            ? \context_course::instance($instanceid)
            : \context_system::instance();
    }

    #[\Override]
    public function courseid(int $instanceid): int {
        return max(0, $instanceid);
    }

    /**
     * Apply every gate the tutor has always applied to a turn.
     *
     * The order matters and is the old one: the scope has to be enabled at all,
     * the person has to be logged in and enrolled where they claim to be, and
     * only then does the capability decide. A capability check on a scope the
     * site has switched off would answer the wrong question.
     *
     * @param int $instanceid The course id, or 0 for the site-wide chat.
     * @param int $userid The acting user id.
     * @return void
     * @throws \moodle_exception When any gate rejects the turn.
     */
    #[\Override]
    public function require_access(int $instanceid, int $userid): void {
        require_login();

        $courseid = $this->courseid($instanceid);
        if ($courseid > 0) {
            if (!security::course_chat_enabled()) {
                throw new \moodle_exception('error_course_chat_disabled', 'block_elediaai_tutor');
            }
        } else if (!security::global_chat_enabled()) {
            throw new \moodle_exception('error_global_chat_disabled', 'block_elediaai_tutor');
        }
        helper::require_scope_login($courseid);

        $context = $this->context($instanceid);
        require_capability('block/elediaai_tutor:use', $context);
        // A fixed-course block may sit outside the scoped course's context tree,
        // so the capability is re-checked in that course: a CAP_PROHIBIT there
        // must not be bypassable through a block placed somewhere else.
        helper::require_course_capability('block/elediaai_tutor:use', $courseid);
    }

    /**
     * No message leaves this site before the guidelines were acknowledged.
     *
     * Documented acknowledgement, enforced server-side rather than by the
     * interface that shows the checkbox. Deliberately not part of
     * require_access(): the panel has to be able to load an empty transcript
     * in order to show the consent form at all.
     *
     * @param int $instanceid The course id, or 0.
     * @param int $userid The acting user id.
     * @return void
     * @throws \moodle_exception When the guidelines were not acknowledged.
     */
    #[\Override]
    public function require_send(int $instanceid, int $userid): void {
        consent::require_consent($userid);
    }

    #[\Override]
    public function persona(int $instanceid): persona {
        $fields = branding::persona((array) $this->instance_config($instanceid));

        return new persona(
            name: (string) ($fields['name'] ?? ''),
            role: (string) ($fields['role'] ?? ''),
            tone: (string) ($fields['tone'] ?? ''),
            audience: (string) ($fields['audience'] ?? ''),
            instructions: (string) ($fields['instructions'] ?? ''),
        );
    }

    /**
     * The tutor is the surface the base prompt was written for.
     *
     * This is also what a server assumes when the argument is absent, so the
     * block's turns reach an older server exactly as they did before.
     *
     * @return string
     */
    #[\Override]
    public function system_prompt_id(): string {
        return system_prompt::TUTOR;
    }

    /**
     * Grounded unless this scope has no indexed material to ground in.
     *
     * The site gate and the course's ingestion state decide together, exactly
     * as before. A scope where neither grounding nor model-only answering is
     * permitted is refused rather than answered some other way.
     *
     * @param int $instanceid The course id, or 0 for the site-wide chat.
     * @return string A mode constant.
     * @throws \moodle_exception When the tutor cannot answer in this scope at all.
     */
    #[\Override]
    public function mode(int $instanceid): string {
        $blockconfig = $this->instance_config($instanceid);
        $resolved = chat_mode::resolve($this->courseid($instanceid), $blockconfig);

        if ($resolved === chat_mode::MODE_UNAVAILABLE) {
            throw new \moodle_exception('llmonly_unavailable', 'block_elediaai_tutor');
        }

        return $resolved === chat_mode::MODE_LLMONLY ? mode::UNGROUNDED : mode::GROUNDED;
    }

    /**
     * The knowledge base configured for this block, site value or instance.
     *
     * `registry::effective()` does the inheriting: an instance that chose
     * nothing falls back to the site setting, which is what an empty selection
     * means in that form -- a multiple select has no third state for "use the
     * site value", and "nothing at all" is not a useful wish.
     *
     * @param int $instanceid The course id, or 0.
     * @return string The stored selection, or ''.
     */
    #[\Override]
    public function knowledge_scope(int $instanceid): string {
        return (string) registry::effective('coursescope', (array) $this->instance_config($instanceid));
    }

    /**
     * The backend may act in Moodle on this learner's behalf.
     *
     * The tutor is the surface where that is the point: looking something up,
     * opening an activity, reporting a deadline.
     *
     * @param int $instanceid The course id, or 0.
     * @return bool Always true.
     */
    #[\Override]
    public function allow_tools(int $instanceid): bool {
        return true;
    }

    /**
     * A tutor conversation is the learner's own scratch paper.
     *
     * Nothing is ever handed in here, so there is nothing to protect from
     * being thrown away.
     *
     * @param int $instanceid The course id, or 0.
     * @param int $userid The person asking.
     * @return bool Always true.
     */
    #[\Override]
    public function may_clear(int $instanceid, int $userid): bool {
        return true;
    }

    /**
     * The instance's own daily budget, when a teacher set one.
     *
     * The stored convention is unchanged: an unset value or -1 means "use the
     * site setting", 0 means unlimited on this instance, anything above is a
     * count of messages per day.
     *
     * @param int $instanceid The course id, or 0.
     * @return int|null Messages per day, or null for the site setting.
     */
    #[\Override]
    public function daily_limit(int $instanceid): ?int {
        $blockconfig = $this->instance_config($instanceid);
        if (!isset($blockconfig->dailylimit) || (int) $blockconfig->dailylimit < 0) {
            return null;
        }

        return (int) $blockconfig->dailylimit;
    }

    /**
     * The hints the tutor adds to a turn.
     *
     * The style chips are the learner's, so the panel sends the one they
     * picked. It is still resolved here rather than believed: a teacher who
     * locked the style on this instance decides it, whatever the client asked
     * for, and an unknown style falls back to the instance default instead of
     * travelling on. The memory flag is only sent when the administrator has
     * declared the backend memory-capable, so a backend without memory never
     * receives a field it does not know.
     *
     * @param int $instanceid The course id, or 0.
     * @param int $userid The acting user id.
     * @param array $clienthints Hint name => value, as the panel asked for it.
     * @return array Hint name => value.
     */
    #[\Override]
    public function options(int $instanceid, int $userid, array $clienthints = []): array {
        $blockconfig = $this->instance_config($instanceid);
        $requested = (string) ($clienthints['answerstyle'] ?? '');

        $options = ['answerstyle' => self::effective_answer_style($blockconfig, $requested)];
        // Memory capability is declared by naming the opt-in tool. That setting
        // moved to the engine with the tool catalogue, and this call was left
        // pointing at the block's removed one - so every turn died here before
        // it could reach a backend.
        if (connection::tool_name('memoryoptin') !== '') {
            $options['ltmenabled'] = ltm::is_enabled($userid);
        }

        return $options;
    }

    /**
     * The answer style a turn actually runs with.
     *
     * @param \stdClass $blockconfig The block instance configuration.
     * @param string $requested The style the client asked for, or ''.
     * @return string One of explain, hint or quiz.
     */
    public static function effective_answer_style(\stdClass $blockconfig, string $requested): string {
        $styles = ['explain', 'hint', 'quiz'];

        $default = (string) ($blockconfig->answerstyle ?? 'explain');
        if (!in_array($default, $styles, true)) {
            $default = 'explain';
        }

        $allowchange = !isset($blockconfig->allowstylechange) || (int) $blockconfig->allowstylechange === 1;
        if ($allowchange && in_array($requested, $styles, true)) {
            return $requested;
        }

        return $default;
    }
}
