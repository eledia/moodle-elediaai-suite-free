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

namespace local_elediaai_chatengine\adapter;

/**
 * The backend answered, and its answer was a refusal written for the learner.
 *
 * The agent reports a business error (rag_server_spec, error catalogue) as an
 * MCP tool result with `isError` and a `structuredContent.error` block: a
 * machine-readable code, whether retrying can help, and an `answer` meant for
 * display. Until #39 every one of them reached the learner as "the knowledge
 * service is not answering" -- also "this site is not registered" and "the
 * quota is spent", where retrying can never succeed.
 *
 * What is shown is the backend's own sentence: the agent knows the reason and
 * receives the learner's language. What is decided is decided on the code,
 * never on the text, because the text may change.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backend_refusal extends adapter_exception {
    /** @var string The conversation the backend was asked to continue no longer exists. */
    public const CONVERSATION_NOT_FOUND = 'conversation_not_found';

    /** @var int Longest backend message shown, in characters. */
    private const MAX_MESSAGE = 500;

    /** @var string The backend's error code. */
    private string $backendcode;

    /** @var bool Whether the same request may succeed later. */
    private bool $retryable;

    /** @var int Seconds to wait before retrying, 0 when unknown. */
    private int $retryafter;

    /**
     * Constructor.
     *
     * @param string $backendcode The backend's `error.code`.
     * @param string $message The backend's message for the learner; may be empty.
     * @param bool $retryable Whether a retry can help.
     * @param int $retryafter Seconds to wait before a retry, 0 when unknown.
     * @param int $httpstatus The HTTP status the refusal came with.
     */
    public function __construct(
        string $backendcode,
        string $message,
        bool $retryable,
        int $retryafter = 0,
        int $httpstatus = 0
    ) {
        $this->backendcode = clean_param($backendcode, PARAM_ALPHANUMEXT);
        $this->retryable = $retryable;
        $this->retryafter = max(0, $retryafter);

        // Plain text, bounded. It comes from outside this site and is put into
        // the chat window; markup in it has no business there.
        $message = trim(clean_param($message, PARAM_TEXT));
        if (\core_text::strlen($message) > self::MAX_MESSAGE) {
            $message = \core_text::substr($message, 0, self::MAX_MESSAGE - 1) . '…';
        }
        if ($message === '') {
            $message = get_string('error_backend_unavailable', 'local_elediaai_chatengine');
        }

        parent::__construct(
            $retryable ? 'error_backend_busy' : 'error_backend_refused',
            'backend refusal: ' . $this->backendcode . ' (http ' . $httpstatus . ')',
            $httpstatus,
            $message
        );
    }

    /**
     * Read a refusal out of a JSON-RPC result, if it is one.
     *
     * Only the documented shape counts: `isError` plus a `structuredContent`
     * block with an `error.code`. A plain tool error without that block stays
     * what it was -- an answer flagged as failed -- so servers built before the
     * catalogue keep behaving as they did.
     *
     * @param array $result The JSON-RPC `result` member.
     * @param int $httpstatus The HTTP status of the response.
     * @param int $retryafterheader Seconds from a `Retry-After` header, 0 when absent.
     * @return self|null The refusal, or null when the result is none.
     */
    public static function from_result(array $result, int $httpstatus = 0, int $retryafterheader = 0): ?self {
        if (empty($result['isError'])) {
            return null;
        }
        $structured = is_array($result['structuredContent'] ?? null) ? $result['structuredContent'] : [];
        $error = is_array($structured['error'] ?? null) ? $structured['error'] : null;
        if ($error === null || !isset($error['code']) || !is_string($error['code']) || $error['code'] === '') {
            return null;
        }

        $message = is_string($structured['answer'] ?? null) ? $structured['answer'] : '';
        if ($message === '') {
            foreach ($result['content'] ?? [] as $part) {
                if (is_array($part) && ($part['type'] ?? '') === 'text' && is_string($part['text'] ?? null)) {
                    $message = $part['text'];
                    break;
                }
            }
        }

        $retryafter = is_numeric($error['retry_after'] ?? null) ? (int) $error['retry_after'] : 0;
        $status = is_numeric($error['http_status'] ?? null) ? (int) $error['http_status'] : $httpstatus;

        return new self(
            $error['code'],
            $message,
            !empty($error['retryable']),
            max($retryafter, $retryafterheader),
            $status
        );
    }

    /**
     * The backend's error code.
     *
     * @return string
     */
    public function backend_code(): string {
        return $this->backendcode;
    }

    /**
     * Whether the same request may succeed when sent again.
     *
     * @return bool
     */
    public function is_retryable(): bool {
        return $this->retryable;
    }

    /**
     * Seconds to wait before a retry.
     *
     * @return int 0 when the backend did not say.
     */
    public function retry_after(): int {
        return $this->retryafter;
    }

    /**
     * A refusal is never cured by a fresh credential.
     *
     * The backend authenticated the request and then said no; even a 403
     * (`tenant_not_found`) is about this site, not about the token.
     *
     * @return bool Always false.
     */
    #[\Override]
    public function is_auth_failure(): bool {
        return false;
    }
}
