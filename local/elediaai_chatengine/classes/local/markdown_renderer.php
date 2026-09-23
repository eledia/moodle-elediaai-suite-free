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

namespace local_elediaai_chatengine\local;

/**
 * Renders assistant Markdown to safe HTML.
 *
 * Assistant output is untrusted (it originates from an LLM/RAG pipeline), so it
 * is converted from Markdown and then run through Moodle's HTML purifier via
 * {@see format_text()}. The result is sanitised server-side and is safe to
 * inject into the DOM, so the client never has to trust raw model output.
 *
 * @package    local_elediaai_chatengine
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class markdown_renderer {
    /**
     * Convert assistant Markdown into sanitised HTML.
     *
     * @param string $markdown Raw assistant Markdown.
     * @param \context $context Context used for filtering/cleaning.
     * @return string Safe HTML.
     */
    public static function render(string $markdown, \context $context): string {
        if (trim($markdown) === '') {
            return '';
        }

        // Mathematik zuerst aus dem Weg raeumen: Markdown liest den Backslash
        // vor einem Satzzeichen als Maskierung, aus `\\(` wird `(`. Damit sind
        // die Trennzeichen weg, bevor MathJax sie sehen koennte -- im Chat stand
        // dann `(R_\\text{ges} = 1000\\,\\Omega)` als nackter Text.
        $mathe = [];
        $geschuetzt = self::mathematik_sichern(
            self::autolink(self::listen_absetzen($markdown)),
            $mathe
        );

        // FORMAT_MARKDOWN parses Markdown then HTMLPurifier cleans the result.
        // filter => false avoids running content filters (e.g. multimedia) on
        // model output; clean (the default) strips dangerous markup.
        $html = format_text($geschuetzt, FORMAT_MARKDOWN, [
            'context' => $context,
            'filter' => false,
            'para' => true,
        ]);

        return self::mathematik_einsetzen($html, $mathe);
    }

    /**
     * Put a blank line in front of a list that follows a paragraph.
     *
     * Moodles `FORMAT_MARKDOWN` ist klassisches Markdown, und dort muss vor
     * einer Liste eine Leerzeile stehen. Fehlt sie, liest der Parser die
     * Punkte als Fortsetzung des Absatzes und haengt sie in einer Zeile
     * aneinander -- aus
     *
     *     Bitte nenne mir:
     *     - Kurstitel
     *     - optional: Kurzname
     *
     * wird „Bitte nenne mir: - Kurstitel - optional: Kurzname". Genau so
     * gemeldet in AI-79.
     *
     * Der Grund fuer die Verwirrung: waehrend des Streamings setzt der Browser
     * denselben Text nach CommonMark, und CommonMark *erlaubt* einer Liste,
     * einen Absatz zu unterbrechen. Die Formatierung stimmt also, bis die
     * fertige Antwort vom Server kommt -- und kippt dann.
     *
     * Hier wird deshalb CommonMarks Regel nachgezogen, nicht mehr: eine
     * Aufzaehlung darf unterbrechen, eine Nummerierung nur, wenn sie bei 1
     * beginnt. Das bewahrt den Satz „Das war am\n3. Oktober" davor, zur Liste
     * zu werden.
     *
     * Nicht angefasst: Code (eingezaeunt oder eingerueckt) und Zeilen, die
     * ohnehin schon in einer Liste stehen -- dort wuerde eine Leerzeile aus
     * einer dichten Liste eine lockere machen.
     *
     * Nur Listen: Ueberschriften, Zitate, Tabellen, Codebloecke und
     * Trennlinien unterbrechen einen Absatz auch klassisch schon, nachgemessen
     * am 20.09.2026.
     *
     * @param string $markdown Raw assistant Markdown.
     * @return string
     */
    private static function listen_absetzen(string $markdown): string {
        $zeilen = explode("\n", $markdown);
        $raus = [];
        $imblock = false;
        $inliste = false;

        foreach ($zeilen as $zeile) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $zeile)) {
                $imblock = !$imblock;
                $raus[] = $zeile;
                continue;
            }
            if ($imblock) {
                $raus[] = $zeile;
                continue;
            }

            // CommonMarks Bedingung: Aufzaehlung mit Inhalt, Nummerierung nur
            // ab 1. Hoechstens drei Leerzeichen Einzug -- ab vier ist es Code.
            $istliste = (bool) preg_match('/^\s{0,3}(?:[-*+]|1[.)])\s+\S/', $zeile);
            $istlistenzeile = (bool) preg_match('/^\s{0,3}(?:[-*+]|\d{1,9}[.)])\s+/', $zeile);
            $leer = trim($zeile) === '';
            $eingerueckt = (bool) preg_match('/^\s+\S/', $zeile);
            $vorherige = $raus === [] ? '' : $raus[count($raus) - 1];

            if ($istliste && !$inliste && trim($vorherige) !== '') {
                $raus[] = '';
            }

            $raus[] = $zeile;

            if ($istlistenzeile) {
                $inliste = true;
            } else if (!$leer && !$eingerueckt) {
                // Ein Absatz am linken Rand beendet die Liste.
                $inliste = false;
            }
        }

        return implode("\n", $raus);
    }

    /**
     * Replace maths spans with placeholders Markdown will not touch.
     *
     * Erfasst werden die drei Schreibweisen, die MathJax in Moodle kennt:
     * `\\( ... \\)` und `\\[ ... \\]` sowie `$$ ... $$`. Einzelne Dollarzeichen
     * bleiben absichtlich unangetastet -- sie stehen viel oefter fuer einen
     * Geldbetrag als fuer eine Formel, und ein falsch erkannter Bereich waere
     * schlimmer als ein nicht gesetzter.
     *
     * Code bleibt unberuehrt: Wer eine Formel als Beispiel in einen Code-Block
     * schreibt, will sie als Zeichen sehen, nicht gesetzt.
     *
     * @param string $markdown Raw assistant Markdown.
     * @param array $mathe Wird gefuellt: Platzhalter => Originaltext.
     * @return string Markdown mit Platzhaltern an den Formelstellen.
     */
    private static function mathematik_sichern(string $markdown, array &$mathe): string {
        $muster = '~(?<!\\\\)\\\\\\((?:[^\\\\]|\\\\(?!\\)))*?\\\\\\)'      // \( ... \)
            . '|(?<!\\\\)\\\\\\[(?:[^\\\\]|\\\\(?!\\]))*?\\\\\\]'          // \[ ... \]
            . '|\$\$(?:[^$]|\$(?!\$))+?\$\$~s';                          // $$ ... $$

        $zeilen = explode("\n", $markdown);
        $imblock = false;
        foreach ($zeilen as $nummer => $zeile) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $zeile)) {
                $imblock = !$imblock;
                continue;
            }
            if ($imblock) {
                continue;
            }
            // Inline-Code ebenfalls auslassen -- gleiche Aufteilung wie autolink_line().
            $teile = preg_split('/(`+[^`]*`+)/', $zeile, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($teile as $i => $teil) {
                if ($teil === '' || $teil[0] === '`') {
                    continue;
                }
                $teile[$i] = (string) preg_replace_callback($muster, static function (array $treffer) use (&$mathe): string {
                    // Der Platzhalter darf durch Markdown und HTMLPurifier
                    // unveraendert hindurchgehen: nur Buchstaben und Ziffern.
                    $schluessel = 'ELEDIAAIMATH' . count($mathe) . 'X';
                    $mathe[$schluessel] = $treffer[0];
                    return $schluessel;
                }, $teil);
            }
            $zeilen[$nummer] = implode('', $teile);
        }

        return implode("\n", $zeilen);
    }

    /**
     * Put the maths back, escaped for HTML.
     *
     * Der Originaltext ging nicht durch HTMLPurifier, deshalb wird er hier
     * maskiert. MathJax liest die Entities korrekt; `<` in einer Ungleichung
     * bleibt damit ein Kleinerzeichen und wird nicht zum Tag-Anfang.
     *
     * Die Huelle traegt die Klasse, nach der der MathJax-Loader sucht.
     *
     * @param string $html Gereinigtes HTML mit Platzhaltern.
     * @param array $mathe Platzhalter => Originaltext.
     * @return string
     */
    private static function mathematik_einsetzen(string $html, array $mathe): string {
        if ($mathe === []) {
            return $html;
        }
        $ersatz = [];
        foreach ($mathe as $schluessel => $original) {
            // Die Klasse ist kein Schmuck: filter_mathjaxloader/loader sucht beim
            // Nachladen ausschliesslich nach `.filter_mathjaxloader_equation` und
            // ueberspringt alles andere. Ohne die Huelle blieb die Formel als
            // Quelltext stehen, obwohl MathJax laengst geladen war.
            $ersatz[$schluessel] = \html_writer::tag(
                'span',
                htmlspecialchars($original, ENT_NOQUOTES, 'UTF-8'),
                ['class' => 'filter_mathjaxloader_equation']
            );
        }

        return strtr($html, $ersatz);
    }

    /**
     * Turn a bare URL into one Markdown will link.
     *
     * Das Modell schreibt Adressen oft nackt hin -- "Link:
     * https://.../view.php?id=17" -- statt in Klammern. GitHub macht daraus
     * einen Verweis, Moodles Markdown nicht: PHP Markdown Extra verlinkt nur
     * die spitzen Klammern und die `[Text](Adresse)`-Form. Im Chat stand
     * deshalb eine Adresse zum Abtippen.
     *
     * Der naheliegende Weg waere `filter_urltolink` gewesen. Der ist hier
     * versperrt, und mit Absicht: `render()` schaltet die Filter ab, damit
     * kein Modell ueber eine Adresse Medien in die Seite holt. Diese
     * Umschrift setzt nur spitze Klammern -- die Adresse geht danach durch
     * denselben Markdown-Parser und denselben HTMLPurifier wie zuvor, und nur
     * `http`/`https` werden ueberhaupt angefasst.
     *
     * Nicht angefasst wird, was schon verlinkt ist, und nichts in Code:
     * Code-Bloecke und Code-Abschnitte bleiben Zeichen fuer Zeichen stehen.
     *
     * @param string $markdown Raw assistant Markdown.
     * @return string The same Markdown, with bare URLs in angle brackets.
     */
    private static function autolink(string $markdown): string {
        $zeilen = explode("\n", $markdown);
        $imblock = false;
        foreach ($zeilen as $nummer => $zeile) {
            // Zeilenweise, weil Markdown-Zaeune zeilenweise sind.
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $zeile)) {
                $imblock = !$imblock;
                continue;
            }
            if ($imblock) {
                continue;
            }
            $zeilen[$nummer] = self::autolink_line($zeile);
        }

        return implode("\n", $zeilen);
    }

    /**
     * One line, with its inline code left alone.
     *
     * @param string $zeile A single line of Markdown, outside any code block.
     * @return string
     */
    private static function autolink_line(string $zeile): string {
        $teile = preg_split('/(`+[^`]*`+)/', $zeile, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($teile as $nummer => $teil) {
            if ($teil === '' || $teil[0] === '`') {
                continue;
            }
            // Nicht hinter `<` (steht schon in spitzen Klammern), nicht hinter
            // `](` (ist schon ein Markdown-Verweis) und nicht mitten in einem
            // Wort. Satzzeichen am Ende gehoeren dem Satz, nicht der Adresse.
            $teile[$nummer] = (string) preg_replace(
                '~(?<!<)(?<!\]\()(?<![\w/])(https?://[^\s<>"\'`()\[\]]*[^\s<>"\'`()\[\].,;:!?])~',
                '<$1>',
                $teil
            );
        }

        return implode('', $teile);
    }
}
