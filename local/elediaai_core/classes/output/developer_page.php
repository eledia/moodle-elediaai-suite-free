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
 * What the developer page knows.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\output;

use core_component;

/**
 * The facts behind `developer.php`, without any rendering.
 *
 * Two of them are read from files at request time rather than written down
 * here, and that is the point of the page: a list of tokens kept by hand
 * drifts away from `styles.css` within a release, and a contract retyped into
 * lang strings drifts away from the document people actually edit. The page
 * is a view of the source, not a second copy of it.
 */
final class developer_page {
    /** @var string The setting that switches the page on. */
    public const SETTING = 'developerdocs';

    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * Whether the page is switched on for this site.
     *
     * The capability is checked separately, in the page itself: this answers
     * "does the site offer it", not "may this person see it".
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return !empty(get_config('local_elediaai_core', self::SETTING));
    }

    /**
     * The token groups declared in the suite's design layer.
     *
     * Parsed out of `styles.css` rather than listed here: the `:root` block is
     * the single source, and a token added there should appear on this page
     * without anyone remembering to add it twice. The group titles come from
     * the section comments in that same block, so the page is grouped the way
     * the stylesheet is written.
     *
     * @return array<int, array{title: string, tokens: string[]}>
     */
    public static function token_groups(): array {
        $css = self::design_layer_css();
        if ($css === '') {
            return [];
        }

        $gruppen = [];
        $aktuell = ['title' => '', 'tokens' => []];

        foreach (explode("\n", $css) as $zeile) {
            // Eine Abschnittsueberschrift ist ein Kommentar mit Strichen:
            // /* --- Flaechen. ---------------- */
            if (preg_match('~/\*\s*-{2,}\s*(.+?)\s*-{2,}\s*\*/~', $zeile, $treffer)) {
                if ($aktuell['tokens'] !== []) {
                    $gruppen[] = $aktuell;
                }
                $aktuell = ['title' => rtrim(trim($treffer[1]), '.'), 'tokens' => []];
                continue;
            }
            if (preg_match('~^\s*(--[a-z0-9-]+)\s*:~i', $zeile, $treffer)) {
                $aktuell['tokens'][] = $treffer[1];
            }
        }
        if ($aktuell['tokens'] !== []) {
            $gruppen[] = $aktuell;
        }

        return $gruppen;
    }

    /**
     * The eight steps of the type scale, in order.
     *
     * @return string[] Token names.
     */
    public static function type_scale(): array {
        foreach (self::token_groups() as $gruppe) {
            $stufen = array_values(array_filter(
                $gruppe['tokens'],
                static fn(string $name): bool => str_starts_with($name, '--font-size-')
            ));
            if ($stufen !== []) {
                return $stufen;
            }
        }
        return [];
    }

    /**
     * What this installation is, for the top of the page.
     *
     * @return array<string, string> Label => value.
     */
    public static function environment(): array {
        global $CFG, $PAGE;

        $suite = [];
        foreach (self::suite_components() as $component) {
            $version = get_config($component, 'version');
            $suite[] = $component . ' ' . ($version === false ? '?' : (string) $version);
        }
        sort($suite);

        return [
            'Moodle' => $CFG->release,
            'PHP' => PHP_VERSION,
            'Theme' => $PAGE->theme->name,
            // Eine Liste und keine Spalte: siebenundzwanzig Zeilen Versionen
            // waeren der halbe erste Bildschirm, und gesucht wird darin ohnehin
            // mit den Augen und nicht Zeile fuer Zeile.
            'Plugins' => $suite === [] ? '—' : implode(' · ', $suite),
        ];
    }

    /**
     * The "Design-System" section of the developer documentation, as Markdown.
     *
     * Read from the file so that the page and the document cannot disagree.
     * The document is written in German; so is what this returns.
     *
     * @return string Markdown, or the empty string when the section is gone.
     */
    public static function contract(): string {
        $pfad = self::plugin_dir() . '/docs/03-dev-doc.md';
        if (!is_readable($pfad)) {
            return '';
        }
        $text = file_get_contents($pfad);
        if ($text === false) {
            return '';
        }
        // Vom Abschnitt bis zur naechsten Ueberschrift derselben Ebene.
        if (!preg_match('~^## Design-System\s*$(.*?)(?=^## )~ms', $text, $treffer)) {
            return '';
        }
        return trim($treffer[1]);
    }

    /**
     * The suite components installed on this site.
     *
     * Everything that declares a dependency on this plugin is a suite plugin -
     * that is what task22 made true, so nothing here needs a list of names.
     *
     * @return string[] Frankenstyle component names.
     */
    private static function suite_components(): array {
        $gefunden = [];
        foreach (core_component::get_plugin_types() as $typ => $verzeichnis) {
            foreach (core_component::get_plugin_list($typ) as $name => $pfad) {
                $datei = $pfad . '/version.php';
                if (!is_readable($datei)) {
                    continue;
                }
                $inhalt = file_get_contents($datei);
                if ($inhalt === false || !str_contains($inhalt, 'local_elediaai_core')) {
                    continue;
                }
                $gefunden[] = $typ . '_' . $name;
            }
        }
        $gefunden[] = 'local_elediaai_core';
        return array_values(array_unique($gefunden));
    }

    /**
     * The `:root` block of the design layer.
     *
     * @return string CSS, or the empty string when the file or block is gone.
     */
    private static function design_layer_css(): string {
        $pfad = self::plugin_dir() . '/styles.css';
        if (!is_readable($pfad)) {
            return '';
        }
        $css = file_get_contents($pfad);
        if ($css === false) {
            return '';
        }
        // Der erste :root-Block ist die Design-Schicht. Die schliessende
        // Klammer am Zeilenanfang beendet ihn -- verschachtelte Selektoren
        // kommen in diesem Block nicht vor.
        if (!preg_match('~^:root\s*\{(.*?)^\}~ms', $css, $treffer)) {
            return '';
        }
        return $treffer[1];
    }

    /**
     * Where this plugin lives.
     *
     * @return string Absolute path.
     */
    private static function plugin_dir(): string {
        return (string) core_component::get_component_directory('local_elediaai_core');
    }
}
