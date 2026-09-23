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

namespace local_elediaai_sources\sink;

use local_elediaai_sources\api_client;
use local_elediaai_sources\format_matrix;

/**
 * LiteRAG running on the same Moodle site.
 *
 * The route lives at `local/literag/ingest.php` and selects its action through
 * a query parameter. Deriving that from a configured URL was the reason the
 * old client carried a list of URL shapes; here the route is built from
 * wwwroot, so there is nothing left to guess and nothing to mistype. The only
 * thing an administrator supplies is LiteRAG's own ingestion key, which is
 * read from that plugin rather than duplicated here.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class literag_sink implements sink {
    /** @var string The companion plugin's configuration class. */
    private const CONFIG_CLASS = '\local_literag\local\config';

    /** @var api_client|null Injected transport; built per call when null. */
    private ?api_client $client;

    /**
     * Constructor.
     *
     * @param api_client|null $client Injected transport, for tests.
     */
    public function __construct(?api_client $client = null) {
        $this->client = $client;
    }

    #[\Override]
    public static function id(): string {
        return 'literag';
    }

    #[\Override]
    public static function name(): string {
        return get_string('sink_literag', 'local_elediaai_sources');
    }

    /**
     * Usable when the companion plugin is installed and its ingestion key set.
     *
     * There is no endpoint to configure: the route is local and derived from
     * wwwroot. The key is not a second setting either — `ingest.php` compares
     * against LiteRAG's own `ingest_api_key`, so that is the value sent.
     *
     * @return bool True when local_literag is present and holds a key.
     */
    #[\Override]
    public function is_configured(): bool {
        return $this->apikey() !== '';
    }

    /**
     * Probe LiteRAG's health action.
     *
     * The endpoint authenticates every action including `health`, so an empty
     * key would answer 401 rather than reporting the actual cause. Both
     * missing preconditions are therefore reported before the request.
     *
     * @return array Result row as returned by {@see api_client}.
     */
    #[\Override]
    public function healthcheck(): array {
        if (!class_exists(self::CONFIG_CLASS)) {
            return $this->failure(get_string('sink_literag_missing', 'local_elediaai_sources'));
        }
        if ($this->apikey() === '') {
            return $this->failure(get_string('sink_literag_nokey', 'local_elediaai_sources'));
        }
        return $this->client()->get($this->action_url('health'));
    }

    /**
     * LiteRAG indexes without embeddings, so there is no model to report.
     *
     * Its adr02 ("Embeddings-freies RAG in der Moodle-Datenbank") settles
     * retrieval on the database's native full-text search — no vector store,
     * no embedding model, and consequently no setting that could name one.
     * Returning null keeps the per-course state empty instead of recording an
     * invented value that a later comparison would treat as meaningful.
     *
     * @return string|null Always null.
     */
    #[\Override]
    public function embedding_model(): ?string {
        return null;
    }

    /**
     * LiteRAG parses exactly the three core types and nothing else.
     *
     * Its `document_store` rejects any other `content_type` with a 400, and
     * its text extraction knows three cases: plain text, HTML through
     * `content_to_text()`, and PDF through `pdftotext`. There is no parser to
     * announce beyond that, so the list is fixed here rather than asked for
     * over the local route.
     *
     * @return string[] The core MIME types of the support matrix.
     */
    #[\Override]
    public function supported_content_types(): array {
        return format_matrix::core_types();
    }

    #[\Override]
    public function upsert(array $payload): array {
        return $this->client()->post($this->action_url('upsert'), $payload);
    }

    #[\Override]
    public function delete(string $sourceid, string $scope = 'exact'): array {
        return $this->client()->post($this->action_url('delete'), [
            'source_id' => $sourceid,
            'scope' => $scope,
        ]);
    }

    /**
     * Build the URL for one action on the local ingestion route.
     *
     * The action travels as a query parameter rather than as a path segment:
     * `ingest.php` accepts both, but PATH_INFO depends on the server's
     * slasharguments setting, which a query parameter does not.
     *
     * @param string $action One of 'health', 'upsert', 'delete'.
     * @return string Absolute URL.
     */
    private function action_url(string $action): string {
        return (new \moodle_url('/local/literag/ingest.php', ['action' => $action]))->out(false);
    }

    /**
     * LiteRAG's own ingestion key, or '' when unavailable.
     *
     * @return string
     */
    private function apikey(): string {
        if (!class_exists(self::CONFIG_CLASS)) {
            return '';
        }
        return (string) call_user_func([self::CONFIG_CLASS, 'ingest_api_key']);
    }

    /**
     * The transport, carrying the current key.
     *
     * Built per call rather than in the constructor so that a key changed in
     * LiteRAG's settings takes effect without a new sink instance.
     *
     * @return api_client
     */
    private function client(): api_client {
        return $this->client ?? new api_client($this->apikey());
    }

    /**
     * A failed result row for a precondition that never reached the network.
     *
     * @param string $error The reason, already translated.
     * @return array Result row as returned by {@see api_client}.
     */
    private function failure(string $error): array {
        return ['success' => false, 'http_code' => 0, 'response' => '', 'error' => $error];
    }
}
