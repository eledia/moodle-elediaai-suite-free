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

namespace local_elediaai_chatengine\placement;

use local_elediaai_chatengine\local\persona;
use local_elediaai_chatengine\local\system_prompt;

/**
 * What a placement has to answer for the engine to run a turn on its behalf.
 *
 * A placement is a surface — a block, an activity — that shows a chat. It owns
 * where the chat appears, who may use it and what voice it answers in. It does
 * not own the conversation, the backend or the safety framing; those are the
 * engine's, which is what keeps three surfaces from drifting into three
 * different behaviours.
 *
 * Implementations are found by convention at `\<component>\chatengine\placement`,
 * the same shape the suite already uses for `\<component>\elediaai_core\feature_provider`.
 *
 * Access control stays with the placement on purpose. Each surface already has
 * the capability that fits it — mod/elli:chat, block/elediaai_tutor:use — and
 * inventing an engine-wide one would give administrators a second switch that
 * silently disagrees with the first.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface placement {
    /**
     * The frankenstyle component this placement belongs to.
     *
     * Recorded on every thread, and the key the registry resolves.
     *
     * @return string e.g. 'mod_aichat'.
     */
    public static function component(): string;

    /**
     * The context one instance lives in.
     *
     * @param int $instanceid The placement's own instance id, or 0 for site-wide.
     * @return \context The context.
     * @throws \moodle_exception When the instance does not exist.
     */
    public function context(int $instanceid): \context;

    /**
     * The course an instance belongs to.
     *
     * @param int $instanceid The placement's own instance id.
     * @return int The course id, or 0 when site-wide.
     */
    public function courseid(int $instanceid): int;

    /**
     * Let this user chat here, or fail.
     *
     * Called before anything is validated, stored or sent.
     *
     * @param int $instanceid The placement's own instance id.
     * @param int $userid The acting user, or 0 for an unauthenticated visitor.
     * @return void
     * @throws \moodle_exception When this user may not chat here.
     */
    public function require_access(int $instanceid, int $userid): void;

    /**
     * Let this user *send* here, or fail.
     *
     * Separate from {@see require_access()} on purpose. Looking at your own
     * transcript and sending a new message are different acts: the tutor asks
     * for a documented acknowledgement of the privacy guidelines before
     * anything leaves the site, but reading back what is already stored must
     * not be gated by it — otherwise the panel cannot even show the consent
     * form without first demanding consent.
     *
     * @param int $instanceid The placement's own instance id.
     * @param int $userid The acting user, or 0 for an unauthenticated visitor.
     * @return void
     * @throws \moodle_exception When this user may not send right now.
     */
    public function require_send(int $instanceid, int $userid): void;

    /**
     * The voice this instance answers in.
     *
     * @param int $instanceid The placement's own instance id.
     * @return persona The configured persona; an empty one is allowed.
     */
    public function persona(int $instanceid): persona;

    /**
     * Which base prompt this surface is answered inside.
     *
     * The kind of conversation, not the plugin: two activities of the same
     * kind return the same id. The backend owns the prompt text and selects it
     * from this name, so the value must come from the closed vocabulary in
     * {@see system_prompt}; anything else is answered as a tutor.
     *
     * It is deliberately not derived from the component name. The engine would
     * be guessing at a wire contract it does not own, and a placement outside
     * this repository would have no way to say what it is.
     *
     * Fixed per placement today. Should it ever become configurable, it has to
     * join the persona fingerprint that keys a conversation - otherwise a
     * stored thread would silently continue inside a base prompt its earlier
     * turns never saw.
     *
     * @return string A {@see system_prompt} constant.
     */
    public function system_prompt_id(): string;

    /**
     * The answer mode this instance is configured for.
     *
     * The engine still has the last word: a mode the active backend does not
     * report as supported is refused rather than attempted.
     *
     * @param int $instanceid The placement's own instance id.
     * @return string A {@see \local_elediaai_chatengine\local\mode} constant.
     */
    public function mode(int $instanceid): string;

    /**
     * The knowledge base configured for this instance, as a stored selection.
     *
     * Categories and courses somebody chose as the material this surface may
     * answer from, in the form {@see \local_elediaai_chatengine\local\knowledge_scope}
     * reads — an empty string when nothing was chosen, which is the answer a
     * surface without such a setting always gives.
     *
     * It is a **wish, not a permission**. The engine narrows with it and never
     * widens: what a turn is finally answered from is this selection
     * intersected with the person's enrolments and with what is indexed. A
     * surface without a course ignores it altogether and searches the person's
     * enrolments — the start page belongs to the person, not to a course
     * (operator decision 20.09.2026).
     *
     * @param int $instanceid The placement's own instance id.
     * @return string The stored selection, or '' when none is configured.
     */
    public function knowledge_scope(int $instanceid): string;

    /**
     * Whether the backend may call back into Moodle for this instance.
     *
     * False for a surface that should answer from material and model alone —
     * a guest-facing scenario, for instance, where acting in Moodle on the
     * visitor's behalf would have no meaning.
     *
     * @param int $instanceid The placement's own instance id.
     * @return bool
     */
    public function allow_tools(int $instanceid): bool;

    /**
     * Whether this person may still throw their own conversation away here.
     *
     * The panel offers a clear button on every surface, and most of the time
     * that is right: a chat is the reader's own scratch paper. It stops being
     * theirs alone the moment the surface takes it as work handed in. A
     * scenario whose conversation has been submitted answers false, and the
     * transcript the teacher reads -- and grades -- cannot be emptied from
     * under them.
     *
     * Asked per person, not only per instance: whether a conversation was
     * handed in is a fact about one learner, not about the activity.
     *
     * @param int $instanceid The placement's own instance id.
     * @param int $userid The person asking.
     * @return bool
     */
    public function may_clear(int $instanceid, int $userid): bool;

    /**
     * A daily message budget stricter than the site's, if this instance sets one.
     *
     * Null means the site setting applies. Zero means unlimited here, which is
     * how a placement lifts a site-wide cap for one instance — so the value is
     * deliberately nullable rather than defaulting to 0.
     *
     * @param int $instanceid The placement's own instance id.
     * @return int|null Messages per day, 0 for unlimited, or null for the site setting.
     */
    public function daily_limit(int $instanceid): ?int;

    /**
     * Backend hints this instance wants to add to a turn.
     *
     * Anything a backend may honour or ignore: an answer style, a routing
     * intent, a memory opt-in flag. Unknown keys are passed through untouched,
     * so a placement and a backend can agree on a hint the engine knows
     * nothing about.
     *
     * What the client asked for arrives in $clienthints, already checked
     * against {@see \local_elediaai_chatengine\local\hints}. It is a wish,
     * not an instruction: whatever this method returns under the same name
     * wins, which is how an instance that locked a setting keeps it locked
     * against a crafted request. Hints this placement does not answer for stay
     * as the client asked - the engine's own vocabulary, such as the decision
     * on a confirmation card, is nobody's to override.
     *
     * @param int $instanceid The placement's own instance id.
     * @param int $userid The acting user id.
     * @param array $clienthints Hint name => value, as the client asked for it.
     * @return array Hint name => value.
     */
    public function options(int $instanceid, int $userid, array $clienthints = []): array;
}
