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
 * Unit tests for the destinations and their resolver.
 *
 * @package    local_elediaai_sources
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_sources\sink\sink_manager
 * @covers     \local_elediaai_sources\sink\ingestion_api_sink
 * @covers     \local_elediaai_sources\sink\literag_sink
 */
final class sink_test extends \advanced_testcase {
    /**
     * A recording transport, so the URLs and payloads a sink builds are
     * observable without a network.
     *
     * @return api_client Anonymous recording client.
     */
    private function recorder(): api_client {
        return new class ('') extends api_client {
            /** @var array<int, array> Every call, as ['method', 'url', 'payload']. */
            public array $calls = [];

            /**
             * Record a POST instead of sending it.
             *
             * @param string $url The request URL.
             * @param array $payload The JSON payload.
             * @return array Successful result row.
             */
            public function post(string $url, array $payload): array {
                $this->calls[] = ['method' => 'post', 'url' => $url, 'payload' => $payload];
                return ['success' => true, 'http_code' => 200, 'response' => '', 'error' => ''];
            }

            /**
             * Record a GET instead of sending it.
             *
             * @param string $url The request URL.
             * @return array Successful result row.
             */
            public function get(string $url): array {
                $this->calls[] = ['method' => 'get', 'url' => $url, 'payload' => []];
                return ['success' => true, 'http_code' => 200, 'response' => '', 'error' => ''];
            }
        };
    }

    /**
     * The ingestion API sink builds the documented action URLs from the base
     * URL — nothing is derived from another action URL any more.
     */
    public function test_ingestion_api_builds_documented_action_urls(): void {
        $this->resetAfterTest();

        set_config('sink_ingestionapi_baseurl', 'http://rag-service:8001/', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', 'secret', 'local_elediaai_sources');

        $recorder = $this->recorder();
        $sink = new ingestion_api_sink($recorder);

        $sink->upsert(['source_id' => 'x']);
        $sink->delete('x', 'prefix');
        $sink->healthcheck();

        $this->assertSame('http://rag-service:8001/documents/upsert', $recorder->calls[0]['url']);
        $this->assertSame('http://rag-service:8001/documents/delete', $recorder->calls[1]['url']);
        $this->assertSame('http://rag-service:8001/health', $recorder->calls[2]['url']);
        $this->assertSame('get', $recorder->calls[2]['method']);
    }

    /**
     * The delete payload carries source_id and scope as the contract defines.
     */
    public function test_delete_payload_matches_the_contract(): void {
        $this->resetAfterTest();

        set_config('sink_ingestionapi_baseurl', 'http://rag-service:8001', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', 'secret', 'local_elediaai_sources');

        $recorder = $this->recorder();
        (new ingestion_api_sink($recorder))->delete('tenant:course1:cmid2', 'prefix');

        $this->assertSame(
            ['source_id' => 'tenant:course1:cmid2', 'scope' => 'prefix'],
            $recorder->calls[0]['payload']
        );
    }

    /**
     * Without a base URL or a key, the sink reports itself unconfigured and
     * the health probe names the reason instead of hitting the network.
     */
    public function test_ingestion_api_reports_missing_configuration(): void {
        $this->resetAfterTest();

        set_config('sink_ingestionapi_baseurl', '', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', '', 'local_elediaai_sources');

        $recorder = $this->recorder();
        $sink = new ingestion_api_sink($recorder);

        $this->assertFalse($sink->is_configured());
        $health = $sink->healthcheck();
        $this->assertFalse($health['success']);
        $this->assertSame([], $recorder->calls);
    }

    /**
     * The LiteRAG route is built from wwwroot and passes the action as a query
     * parameter, so it does not depend on the server's slasharguments setting.
     */
    public function test_literag_builds_local_route(): void {
        global $CFG;
        $this->resetAfterTest();

        $recorder = $this->recorder();
        (new literag_sink($recorder))->upsert(['source_id' => 'x']);

        $this->assertSame(
            $CFG->wwwroot . '/local/literag/ingest.php?action=upsert',
            $recorder->calls[0]['url']
        );
    }

    /**
     * LiteRAG indexes without embeddings (its adr02), so there is no model to
     * report and none may be invented.
     */
    public function test_literag_reports_no_embedding_model(): void {
        $this->resetAfterTest();

        $this->assertNull((new literag_sink($this->recorder()))->embedding_model());
    }

    /**
     * The external pipeline exposes no embedding model over v1.2 either.
     */
    public function test_ingestion_api_reports_no_embedding_model(): void {
        $this->resetAfterTest();

        $this->assertNull((new ingestion_api_sink($this->recorder()))->embedding_model());
    }

    /**
     * The manager resolves the configured id into that one sink.
     */
    public function test_manager_resolves_configured_sink(): void {
        $this->resetAfterTest();

        set_config('sink', 'literag', 'local_elediaai_sources');
        $this->assertSame('literag', sink_manager::active_id());
        $this->assertInstanceOf(literag_sink::class, sink_manager::active());

        set_config('sink', 'ingestionapi', 'local_elediaai_sources');
        $this->assertSame('ingestionapi', sink_manager::active_id());
        $this->assertInstanceOf(ingestion_api_sink::class, sink_manager::active());
    }

    /**
     * An unset or unknown value falls back to the default rather than leaving
     * the plugin without a destination.
     */
    public function test_manager_falls_back_to_the_default(): void {
        $this->resetAfterTest();

        unset_config('sink', 'local_elediaai_sources');
        $this->assertSame(sink_manager::DEFAULT_SINK, sink_manager::active_id());

        set_config('sink', 'oerweave-not-built-yet', 'local_elediaai_sources');
        $this->assertSame(sink_manager::DEFAULT_SINK, sink_manager::active_id());
    }

    /**
     * Every offered option resolves to a sink, so the settings menu cannot
     * present a choice the resolver would reject.
     */
    public function test_manager_menu_matches_resolvable_sinks(): void {
        $this->resetAfterTest();

        foreach (array_keys(sink_manager::menu()) as $id) {
            set_config('sink', $id, 'local_elediaai_sources');
            $this->assertSame($id, sink_manager::active_id());
            $this->assertInstanceOf(sink::class, sink_manager::active());
        }
    }

    /**
     * A transport answering the health endpoint with a fixed body.
     *
     * @param string $body The JSON the service would return.
     * @return api_client Anonymous client.
     */
    private function health_answering(string $body): api_client {
        return new class ('', $body) extends api_client {
            /**
             * Constructor.
             *
             * @param string $key The API key (unused).
             * @param string $body The canned response body.
             */
            public function __construct(
                string $key,
                /** @var string The canned response body. */
                private string $body
            ) {
                parent::__construct($key);
            }

            /**
             * Answer with the canned body instead of asking a service.
             *
             * @param string $url The request URL.
             * @return array Successful result row.
             */
            public function get(string $url): array {
                return ['success' => true, 'http_code' => 200, 'response' => $this->body, 'error' => ''];
            }
        };
    }

    /**
     * A service that announces DOCX on /health (specification v1.3) has it
     * taken up — without a Moodle release, which is the point of announcing.
     */
    public function test_ingestion_api_takes_up_announced_formats(): void {
        $this->resetAfterTest();

        set_config('sink_ingestionapi_baseurl', 'http://rag-service:8001', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', 'secret', 'local_elediaai_sources');
        \cache::make('local_elediaai_sources', 'servicecapabilities')->purge();

        $body = json_encode([
            'status' => 'healthy',
            'supported_content_types' => [
                'text/plain',
                'text/html',
                'application/pdf',
                format_matrix::DOCX,
                'TEXT/MARKDOWN; charset=utf-8',
            ],
        ]);

        $types = (new ingestion_api_sink($this->health_answering($body)))->supported_content_types();

        $this->assertContains(format_matrix::DOCX, $types);
        $this->assertContains('text/markdown', $types, 'Announced types are normalised.');
        foreach (format_matrix::core_types() as $core) {
            $this->assertContains($core, $types);
        }
    }

    /**
     * A v1.2 service knows no such field. It must keep working and must not
     * be sent anything beyond the three types it could always parse.
     */
    public function test_ingestion_api_falls_back_when_nothing_is_announced(): void {
        $this->resetAfterTest();

        set_config('sink_ingestionapi_baseurl', 'http://rag-service:8001', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', 'secret', 'local_elediaai_sources');
        $cache = \cache::make('local_elediaai_sources', 'servicecapabilities');

        $cache->purge();
        $old = (new ingestion_api_sink($this->health_answering('{"status": "healthy"}')))->supported_content_types();
        $this->assertSame(format_matrix::core_types(), $old);

        // The same holds for a body that is not JSON at all (a proxy error page).
        $cache->purge();
        $broken = (new ingestion_api_sink($this->health_answering('<html>502</html>')))->supported_content_types();
        $this->assertSame(format_matrix::core_types(), $broken);
    }

    /**
     * An unconfigured destination is not asked over the network at all.
     */
    public function test_ingestion_api_announces_core_without_configuration(): void {
        $this->resetAfterTest();

        set_config('sink_ingestionapi_baseurl', '', 'local_elediaai_sources');
        set_config('sink_ingestionapi_apikey', '', 'local_elediaai_sources');

        $recorder = $this->recorder();
        $types = (new ingestion_api_sink($recorder))->supported_content_types();

        $this->assertSame(format_matrix::core_types(), $types);
        $this->assertSame([], $recorder->calls);
    }

    /**
     * LiteRAG's announcement matches what its document store actually accepts.
     */
    public function test_literag_announces_what_it_can_parse(): void {
        $this->resetAfterTest();

        $this->assertSame(format_matrix::core_types(), (new literag_sink())->supported_content_types());
    }
}
