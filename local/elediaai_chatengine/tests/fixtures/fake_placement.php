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

namespace local_elediaai_chatengine\tests\fixtures;

use local_elediaai_chatengine\local\mode;
use local_elediaai_chatengine\local\persona;
use local_elediaai_chatengine\local\system_prompt;
use local_elediaai_chatengine\placement\placement;

/**
 * A placement that grants access and answers with whatever the test configured.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_placement implements placement {
    /** @var bool Whether require_access() was reached. */
    public bool $accesschecked = false;

    /** @var array The client hints the last options() call was offered. */
    public array $clienthints = [];

    /**
     * Constructor.
     *
     * @param \context $context The context to report.
     * @param string $mode The configured answer mode.
     * @param persona $persona The configured persona.
     * @param bool $allowtools Whether callbacks are permitted.
     * @param array $options Backend hints to add.
     * @param bool $denyaccess Whether require_access() should refuse.
     * @param int|null $dailylimit Instance-level daily budget, or null for the site setting.
     * @param string $systempromptid The base prompt this surface claims.
     * @param bool $mayclear Whether the reader may still discard the conversation.
     */
    public function __construct(
        /** @var \context The context this placement claims to live in. */
        private \context $context,
        /** @var string The answer mode to report. */
        private string $mode = mode::GROUNDED,
        /** @var persona The persona to report. */
        private persona $persona = new persona(),
        /** @var bool Whether the backend may call back into Moodle. */
        private bool $allowtools = true,
        /** @var array Extra hints to pass on. */
        private array $options = [],
        /** @var bool Whether require_access() should refuse. */
        private bool $denyaccess = false,
        /** @var int|null A daily message budget, or null for the site setting. */
        private ?int $dailylimit = null,
        /** @var string The base prompt to report. */
        private string $systempromptid = system_prompt::TUTOR,
        // Last on purpose: several tests still pass positional arguments, and
        // a parameter inserted higher up silently rebinds them.
        /** @var bool Whether the reader may still discard the conversation. */
        private bool $mayclear = true,
        /** @var string The configured knowledge base, as a stored selection. */
        private string $knowledgescope = '',
    ) {
    }

    #[\Override]
    public static function component(): string {
        return 'local_elediaai_chatengine';
    }

    #[\Override]
    public function context(int $instanceid): \context {
        return $this->context;
    }

    #[\Override]
    public function courseid(int $instanceid): int {
        return 0;
    }

    #[\Override]
    public function require_access(int $instanceid, int $userid): void {
        $this->accesschecked = true;
        if ($this->denyaccess) {
            throw new \moodle_exception('nopermissions', 'error', '', 'chat');
        }
    }

    /**
     * Nothing beyond access is required to send here.
     *
     * @param int $instanceid The instance id.
     * @param int $userid The acting user id.
     * @return void
     */
    #[\Override]
    public function require_send(int $instanceid, int $userid): void {
    }

    #[\Override]
    public function persona(int $instanceid): persona {
        return $this->persona;
    }

    #[\Override]
    public function system_prompt_id(): string {
        return $this->systempromptid;
    }

    #[\Override]
    public function mode(int $instanceid): string {
        return $this->mode;
    }

    #[\Override]
    public function knowledge_scope(int $instanceid): string {
        return $this->knowledgescope;
    }

    #[\Override]
    public function allow_tools(int $instanceid): bool {
        return $this->allowtools;
    }

    #[\Override]
    public function may_clear(int $instanceid, int $userid): bool {
        return $this->mayclear;
    }

    #[\Override]
    public function daily_limit(int $instanceid): ?int {
        return $this->dailylimit;
    }

    /**
     * The configured hints, after recording what the client asked for.
     *
     * @param int $instanceid The instance id.
     * @param int $userid The acting user id.
     * @param array $clienthints Hint name => value, as the client asked for it.
     * @return array The hints this placement adds.
     */
    #[\Override]
    public function options(int $instanceid, int $userid, array $clienthints = []): array {
        $this->clienthints = $clienthints;

        return $this->options;
    }
}
