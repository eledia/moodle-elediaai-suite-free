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
 * A backend that can answer a chat turn.
 *
 * The engine talks to nothing else. Two implementations exist: LiteRAG
 * running inside this site, and the external ingestion pipeline's RAG service.
 * Both are grounded backends and both can also answer without retrieval —
 * which of the two is asked is not configured here but follows the active sink
 * of local_elediaai_sources, so a question is never put to a service that the
 * course material was never written to.
 *
 * Adapters are plain classes rather than a subplugin type. The contract is cut
 * so that turning them into one later stays mechanical: an id, a name, an
 * availability probe, a capability report and one call.
 *
 * A new backend arrives as a pair — a sink in local_elediaai_sources to write
 * to it, an adapter here to read from it — carrying the same id.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface adapter {
    /**
     * Short identifier, identical to the sink id this backend is written by.
     *
     * The shared id is what lets the resolver map the configured destination
     * onto a reader without a second setting. Keep it stable across releases:
     * it is recorded on every thread.
     *
     * @return string A frankenstyle-safe identifier, e.g. 'literag'.
     */
    public static function id(): string;

    /**
     * Human-readable name for settings pages and error messages.
     *
     * @return string Translated name.
     */
    public static function name(): string;

    /**
     * Whether this backend is installed, configured and usable right now.
     *
     * Answered without a network call: it decides whether a placement shows a
     * chat box or a configuration notice, and that decision is taken on every
     * page load.
     *
     * @return bool True when chat requests can be served.
     */
    public function is_available(): bool;

    /**
     * What this backend supports.
     *
     * @return capabilities The capability report.
     */
    public function capabilities(): capabilities;

    /**
     * Ask the backend whether it is actually working right now.
     *
     * Distinct from is_available(), which only says whether it is configured.
     * A backend answers for its own reachability: an operator dashboard that
     * reaches into a particular backend's client to test it can only ever test
     * the one backend somebody wrote the code for.
     *
     * Implementations may cache; a dashboard refreshing every few seconds must
     * not turn into load on a language model.
     *
     * @return health What it answered.
     */
    public function health(): health;

    /**
     * Call one of the backend's other tools.
     *
     * The documented backend contract has six tools, of which answering a turn
     * is one. The other five — reading a transcript, deleting a conversation,
     * deleting everything about a person, recording a memory opt-in,
     * reclustering questions — are used by one placement each, but they are the
     * backend's, not that placement's. Routing them through here is what keeps
     * a placement from holding its own client and its own credential.
     *
     * @param string $tool Logical tool key: history, delete, deleteuser, memoryoptin or recluster.
     * @param array $arguments Tool arguments, excluding the ones every call carries.
     * @param int $userid The user the call acts for; a callback token is issued for them.
     * @return array The normalised tool result.
     * @throws adapter_exception On transport or protocol failure, or when the backend has no such tool.
     */
    public function call_tool(string $tool, array $arguments, int $userid): array;

    /**
     * Answer one turn.
     *
     * @param chat_request $request The assembled request.
     * @return chat_response The answer.
     * @throws adapter_exception On transport, protocol or configuration failure.
     */
    public function chat(chat_request $request): chat_response;
}
