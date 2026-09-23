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

/**
 * What this plugin contributes to the suite's handbook.
 *
 * One chapter so far: the wire protocol a RAG server must speak to answer for
 * this engine. It lived in the tutor block, which was the first thing to speak
 * it -- but the protocol is not the tutor's. The engine defines it, every
 * placement inherits it, and a second backend would implement it without the
 * tutor being involved at all.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_chatengine\elediaai_guide;

use local_elediaai_core\output\help_document;
use local_elediaai_guide\content\audience;
use local_elediaai_guide\content\guide_provider as guide_provider_contract;
use local_elediaai_guide\content\topic;

/**
 * The chapters this plugin writes for the handbook.
 */
class guide_provider implements guide_provider_contract {
    /** @var string The language pack these texts live in. */
    private const LANG = 'local_elediaai_chatengine';

    #[\Override]
    public static function get_topics(): array {
        if (!interface_exists(guide_provider_contract::class)) {
            return [];
        }

        $body = self::spec_body();
        if ($body === '') {
            return [];
        }

        return [
            // Drei Riegel wie bei den Vertraegen des Kerns: die Einstellung
            // entscheidet, ob die Website das Kapitel anbietet, die
            // Berechtigung, wer es oeffnet, die Zielgruppe, in wessen Handbuch
            // es steht. Wer nur eine Website betreibt, verliert nichts.
            new topic(
                id: 'contract_ragserver',
                component: 'local_elediaai_chatengine',
                title: get_string('topic_contract_ragserver_title', self::LANG),
                summary: get_string('topic_contract_ragserver_summary', self::LANG),
                body: $body,
                audiences: [audience::ADMIN],
                icon: 'server',
                order: 79,
                capability: 'moodle/site:config',
                section: topic::SECTION_DEVELOPER,
                configflag: 'local_elediaai_core/developerdocs',
            ),
        ];
    }

    #[\Override]
    public static function get_announcements(): array {
        return [];
    }

    /**
     * The specification, read from this plugin's own documentation.
     *
     * Read rather than retyped. A wire protocol written down twice is two
     * protocols after the first change, and this one is what third parties
     * build against -- the document is the contract, and the chapter shows it.
     *
     * @return string Markdown, empty when the file is missing.
     */
    private static function spec_body(): string {
        $verzeichnis = \core_component::get_component_directory('local_elediaai_chatengine');
        if ($verzeichnis === null) {
            return '';
        }

        $markdown = help_document::read($verzeichnis . '/docs/rag_server_spec.md');
        if ($markdown === '') {
            return '';
        }

        return $markdown . "\n\n*" . get_string('contract_language_note_en', self::LANG) . "*";
    }
}
