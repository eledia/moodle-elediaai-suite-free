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

use local_elediaai_chatengine\adapter\adapter;
use local_elediaai_chatengine\adapter\adapter_exception;
use local_elediaai_chatengine\adapter\capabilities;
use local_elediaai_chatengine\adapter\chat_request;
use local_elediaai_chatengine\adapter\chat_response;
use local_elediaai_chatengine\adapter\health;

/**
 * A backend that answers whatever the test told it to, and records the ask.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_adapter implements adapter {
    /** @var chat_request|null The last request received. */
    public ?chat_request $lastrequest = null;

    /** @var int How many times chat() was called. */
    public int $calls = 0;

    /**
     * Constructor.
     *
     * @param chat_response $response What to answer.
     * @param capabilities|null $capabilities What to report, or a permissive default.
     * @param \Throwable[] $failures Exceptions to throw on the first calls, in order.
     */
    public function __construct(
        /** @var chat_response What to answer. */
        public chat_response $response = new chat_response('geantwortet'),
        /** @var capabilities|null What to report, or a permissive default. */
        private ?capabilities $capabilities = null,
        /** @var \Throwable[] Exceptions to throw on the first calls, in order. */
        private array $failures = [],
        /** @var health|null A canned health answer. */
        public ?health $health = null,
    ) {
    }

    #[\Override]
    public static function id(): string {
        return 'literag';
    }

    #[\Override]
    public static function name(): string {
        return 'Fake';
    }

    #[\Override]
    public function is_available(): bool {
        return true;
    }

    #[\Override]
    public function capabilities(): capabilities {
        return $this->capabilities ?? new capabilities(
            supportsungrounded: true,
            supportstools: true,
            supportsstreaming: false,
            stateful: true,
        );
    }

    /**
     * Whatever the test asked it to answer.
     *
     * @return health The canned answer.
     */
    public function health(): health {
        return $this->health ?? new health($this->is_available(), 'fake');
    }

    /** @var array Tool calls received, as [tool, arguments, userid]. */
    public array $toolcalls = [];

    /** @var array The result every tool call returns, unless one is queued. */
    public array $toolresult = ['answer' => '', 'sources' => [], 'iserror' => false];

    /** @var array Results to return in order, then $toolresult; entries may be exceptions. */
    public array $toolqueue = [];

    #[\Override]
    public function call_tool(string $tool, array $arguments, int $userid): array {
        $this->toolcalls[] = [$tool, $arguments, $userid];

        $next = array_shift($this->toolqueue);
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return is_array($next) ? $next : $this->toolresult;
    }

    /**
     * A tool result carrying a JSON payload, as a backend answers structured tools.
     *
     * @param array $payload The structured payload.
     * @return array A tool result.
     */
    public static function json_result(array $payload): array {
        return ['answer' => json_encode($payload), 'sources' => [], 'iserror' => false];
    }

    #[\Override]
    public function chat(chat_request $request): chat_response {
        $this->calls++;
        $this->lastrequest = $request;

        $failure = array_shift($this->failures);
        if ($failure instanceof \Throwable) {
            throw $failure;
        }

        return $this->response;
    }

    /**
     * An adapter that always rejects the credential.
     *
     * @param int $times How many times to fail before answering.
     * @return self
     */
    public static function rejecting_auth(int $times = 1): self {
        $failures = [];
        for ($index = 0; $index < $times; $index++) {
            $failures[] = new adapter_exception('error_backend_unavailable', 'auth', 401);
        }

        return new self(new chat_response('nach dem zweiten Versuch'), null, $failures);
    }
}
