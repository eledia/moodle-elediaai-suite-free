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
 * Suite-wide chapters the core plugin contributes to the guide.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\elediaai_guide;

use local_elediaai_core\output\help_document;
use local_elediaai_guide\content\audience;
use local_elediaai_guide\content\guide_provider as guide_provider_contract;
use local_elediaai_guide\content\topic;

/**
 * The one chapter that is about all features at once.
 *
 * Every other chapter belongs to the plugin it describes. This one cannot:
 * it answers "whose work is in here", and that answer is only complete when
 * somebody looks across all of them. Core holds the registry, so core does
 * the looking -- it aggregates what the plugins declared rather than knowing
 * anything about them.
 *
 * The guide is only a soft dependency: without it the interface is absent,
 * the registry that would load this file does not exist either, and
 * get_topics() degrades to an empty result.
 */
final class guide_provider implements guide_provider_contract {
    /** @var string The language pack these texts live in. */
    private const LANG = 'local_elediaai_core';

    #[\Override]
    public static function get_topics(): array {
        if (!interface_exists(guide_provider_contract::class)) {
            return [];
        }

        // Kein Sammelkapitel zur Herkunft mehr. Die Urheberangaben sind damit
        // nicht verschwunden: sie stehen weiterhin auf der Seite jeder
        // einzelnen Funktion, dort wo die Funktion beschrieben wird und die
        // Angabe hingehoert. Ein zweiter Ort dafuer war eine Liste, die
        // niemand suchte.
        return self::suite_topics();
    }

    /**
     * The chapters about the suite as a whole.
     *
     * They lived in the guide's bundled texts until core got a provider of
     * its own -- and a provider replaces the bundled text for its component,
     * so leaving them there would have deleted them. Word for word the same
     * chapters, in the same sections and the same order; only the owner
     * changed, and it changed to the plugin they were always about.
     *
     * @return topic[]
     */
    private static function suite_topics(): array {
        $topics = [
            self::topic('suite_what', topic::SECTION_START, 'magic', 10),
            self::topic('suite_where', topic::SECTION_START, 'compass', 20),
            self::topic(
                'suite_teacher',
                topic::SECTION_USING,
                'chalkboard-user',
                30,
                [audience::TEACHER, audience::ADMIN]
            ),
            self::topic('suite_privacy', topic::SECTION_TRUST, 'shield', 40),
            // Die Antwort auf Art. 50 Abs. 1 in Alltagssprache, an dem Ort, an
            // dem Lernende und Lehrkraefte sie tatsaechlich lesen. Sie steht
            // neben dem Datenschutzkapitel und nicht darin: das erklaert, wohin
            // der Text geht, dieses, was davon liegenbleibt und wie lange.
            self::topic('suite_logs', topic::SECTION_TRUST, 'clock-rotate-left', 45),
            self::topic('suite_limits', topic::SECTION_TRUST, 'tachometer', 50),
            self::topic(
                'suite_admin',
                topic::SECTION_ADMIN,
                'sliders',
                60,
                [audience::ADMIN],
                'moodle/site:config'
            ),
            // Nur wo jemand Plugins baut. Drei Riegel, jeder beantwortet eine
            // andere Frage: die Einstellung, ob die Website die Seite ueberhaupt
            // anbietet; die Berechtigung, wer sie oeffnen darf; die Zielgruppe,
            // in wessen Handbuch das Kapitel steht. Faellt einer weg, steht ein
            // Verweis auf eine Seite im Panel, die es nicht gibt.
            self::topic(
                'suite_developer',
                topic::SECTION_DEVELOPER,
                // Not 'code': that name is not in the shared sprite, and
                // lucide_icon renders nothing rather than complaining, so this
                // chapter sat in a list of icons without one.
                'code-fork',
                70,
                [audience::ADMIN],
                'moodle/site:config',
                'local_elediaai_core/developerdocs'
            ),
        ];

        // Die sieben Vertraege, je einer ein Kapitel. Hinter denselben drei
        // Riegeln wie das Kapitel darueber: ohne die Einstellung bietet die
        // Website sie nicht an, ohne die Berechtigung darf sie niemand oeffnen,
        // und die Zielgruppe entscheidet, in wessen Handbuch sie stehen.
        //
        // Der Koerper kommt aus 03-dev-doc.md statt aus der Sprachdatei. Ein
        // Vertrag, der zweimal geschrieben steht, ist nach der ersten Aenderung
        // zwei verschiedene Vertraege; die Datei bleibt die eine Quelle.
        foreach (self::contract_chapters() as $index => $eintrag) {
            [$id, $ueberschrift, $icon] = $eintrag;
            $koerper = self::contract_body($ueberschrift);
            if ($koerper === '') {
                // Lieber kein Kapitel als ein leeres: die Ueberschrift kann sich
                // in der Datei geaendert haben, und ein leeres Kapitel sieht aus
                // wie ein Fehler der Website statt wie einer im Dokument.
                debugging(
                    'Der Vertragsabschnitt "' . $ueberschrift . '" fehlt in 03-dev-doc.md.',
                    DEBUG_DEVELOPER
                );
                continue;
            }
            $topics[] = new topic(
                id: $id,
                component: 'local_elediaai_core',
                title: get_string('topic_' . $id . '_title', self::LANG),
                summary: get_string('topic_' . $id . '_summary', self::LANG),
                body: $koerper,
                audiences: [audience::ADMIN],
                icon: $icon,
                order: 71 + $index,
                capability: 'moodle/site:config',
                section: topic::SECTION_DEVELOPER,
                configflag: 'local_elediaai_core/developerdocs',
            );
        }

        return $topics;
    }

    /**
     * Welche Vertraege ein eigenes Kapitel bekommen.
     *
     * Je Eintrag: die Kapitel-Kennung, die Ueberschrift in `03-dev-doc.md`
     * und ein Symbol aus dem gemeinsamen Sprite.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private static function contract_chapters(): array {
        return [
            ['contract_overview', 'Die Vertraege der Suite', 'diagram-project'],
            ['contract_register', '1. Anmelden — `feature_provider`', 'plug'],
            ['contract_explain', '2. Erklaeren — `guide_provider`', 'book'],
            ['contract_health', '3. Zustand melden — `health_provider`', 'check-circle'],
            ['contract_chat', '4. Chat anbieten — `chatengine\\placement`', 'comments'],
            ['contract_premium', '5. Premium freischalten — `policy_provider`', 'key'],
            ['contract_quota', '6. KI aufrufen — `quota_aware_ai_manager`', 'bolt'],
            ['contract_shell', '7. Aussehen — die gemeinsame Seitenhuelle', 'window-maximize'],
        ];
    }

    /**
     * Der Text eines Vertrags, gelesen aus dem Entwicklerdokument.
     *
     * Drei Sonderfaelle, alle gemessen und nicht vermutet:
     *
     * - Die Uebersicht ist der Vorspann des Abschnitts mit seiner Tabelle,
     *   ohne die sieben Unterabschnitte -- die stehen ja daneben.
     * - Vertrag 1 sind in der Datei zwei Zeilen, die nach unten verweisen.
     *   Ein Kapitel, das nur "siehe weiter unten" sagt, ist keines; es bekommt
     *   den Abschnitt dazu, auf den es verweist.
     * - Alles andere ist der Unterabschnitt, wie er dasteht.
     *
     * @param string $ueberschrift Ueberschrift in `03-dev-doc.md`.
     * @return string Markdown, leer wenn der Abschnitt fehlt.
     */
    private static function contract_body(string $ueberschrift): string {
        $verzeichnis = \core_component::get_component_directory('local_elediaai_core');
        if ($verzeichnis === null) {
            return '';
        }
        $doc = $verzeichnis . '/docs/03-dev-doc.md';

        if ($ueberschrift === 'Die Vertraege der Suite') {
            return self::with_language_note(help_document::section($doc, $ueberschrift, 2, true));
        }

        $text = help_document::section($doc, $ueberschrift, 3);
        if ($text === '') {
            return '';
        }

        if (str_starts_with($ueberschrift, '1. Anmelden')) {
            $mechanik = help_document::section($doc, 'Feature-Discovery (Registry-Contract)', 2);
            if ($mechanik !== '') {
                $text .= "\n\n" . $mechanik;
            }
        }

        return self::with_language_note($text);
    }

    /**
     * Haengt den Hinweis an, in welcher Sprache der Vertragstext vorliegt.
     *
     * Die Kapiteltexte der Suite sind sonst zweisprachig. Diese sind es nicht,
     * weil sie aus einem Dokument kommen, das in einer Sprache gepflegt wird.
     * Das ehrlich zu sagen ist besser, als eine Uebersetzung zu versprechen,
     * die es nicht gibt -- oder eine zweite Kopie zu fuehren, die driftet.
     *
     * @param string $markdown
     * @return string
     */
    private static function with_language_note(string $markdown): string {
        if ($markdown === '') {
            return '';
        }

        return $markdown . "\n\n*" . get_string('contract_language_note_de', self::LANG) . "*";
    }

    /**
     * One chapter, read from this plugin's language pack.
     *
     * @param string $id Chapter id; also the language string prefix.
     * @param string $section Handbook section.
     * @param string $icon Icon name from the shared sprite.
     * @param int $order Sort weight.
     * @param string[] $audiences Audience keys, empty for everyone.
     * @param string|null $capability Capability a reader must hold, or null.
     * @param string|null $configflag Site setting the chapter hangs on.
     * @return topic
     */
    private static function topic(
        string $id,
        string $section,
        string $icon,
        int $order,
        array $audiences = [],
        ?string $capability = null,
        ?string $configflag = null
    ): topic {
        return new topic(
            id: $id,
            component: 'local_elediaai_core',
            title: get_string('topic_' . $id . '_title', self::LANG),
            summary: get_string('topic_' . $id . '_summary', self::LANG),
            body: get_string('topic_' . $id . '_body', self::LANG),
            audiences: $audiences,
            icon: $icon,
            order: $order,
            capability: $capability,
            section: $section,
            configflag: $configflag,
        );
    }

    #[\Override]
    public static function get_announcements(): array {
        return [];
    }
}
