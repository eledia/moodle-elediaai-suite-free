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

namespace local_literag\local;

/**
 * Database full-text index management.
 *
 * XMLDB cannot declare FULLTEXT (MySQL/MariaDB) or GIN (PostgreSQL) indexes, so
 * they are created with raw, dbfamily-guarded SQL from both {@see xmldb_local_literag_install()}
 * and the upgrade step. The pattern is cribbed from core search_simpledb. When
 * full-text search is unavailable for the running engine, the retriever falls
 * back to portable LIKE matching, so a missing index is never fatal.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schema {
    /**
     * Create the chunk full-text indexes appropriate to the current DB engine.
     *
     * Idempotent and defensive: any failure is downgraded to a debugging notice
     * so an installation on an engine without full-text support still succeeds
     * (the retriever then uses the LIKE fallback).
     *
     * @return void
     */
    public static function create_fulltext_indexes(): void {
        global $DB;

        try {
            switch ($DB->get_dbfamily()) {
                case 'postgres':
                    $DB->execute("CREATE INDEX IF NOT EXISTS {local_literag_chunks_ftc} " .
                        "ON {local_literag_chunks} USING gin(to_tsvector('simple', chunktext))");
                    $DB->execute("CREATE INDEX IF NOT EXISTS {local_literag_chunks_ftt} " .
                        "ON {local_literag_chunks} USING gin(to_tsvector('simple', sourcetitle))");
                    break;
                case 'mysql':
                    if ($DB->is_fulltext_search_supported()) {
                        $DB->execute("CREATE FULLTEXT INDEX {local_literag_chunks_ft} " .
                            "ON {local_literag_chunks} (chunktext, sourcetitle)");
                    }
                    break;
                case 'mssql':
                    if ($DB->is_fulltext_search_supported()) {
                        $catalogname = $DB->get_prefix() . 'local_literag_catalog';
                        if (!$DB->record_exists_sql('SELECT * FROM sys.fulltext_catalogs WHERE name = ?', [$catalogname])) {
                            $DB->execute("CREATE FULLTEXT CATALOG {local_literag_catalog} WITH ACCENT_SENSITIVITY=OFF");
                        }
                        $changetracking = (defined('PHPUNIT_UTIL') && PHPUNIT_UTIL) ? 'MANUAL' : 'AUTO';
                        $pkname = $DB->get_prefix() . 'locallitechun_id_pk';
                        $DB->execute("CREATE FULLTEXT INDEX ON {local_literag_chunks} (chunktext, sourcetitle) " .
                            "KEY INDEX $pkname ON {local_literag_catalog} WITH CHANGE_TRACKING $changetracking");
                    }
                    break;
                default:
                    // SQLite and any other engine: no native full-text; LIKE fallback is used.
                    break;
            }
        } catch (\Throwable $e) {
            debugging('local_literag: could not create full-text index, falling back to LIKE search: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Whether the running DB engine supports the native full-text path.
     *
     * @return bool True when MATCH/to_tsvector/CONTAINS is usable, false to force LIKE.
     */
    public static function fulltext_available(): bool {
        global $DB;
        switch ($DB->get_dbfamily()) {
            case 'postgres':
                return true;
            case 'mysql':
            case 'mssql':
                return $DB->is_fulltext_search_supported();
            default:
                return false;
        }
    }
}
