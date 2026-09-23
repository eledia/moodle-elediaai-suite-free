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
 * Bereitet ein Repository-Dokument fuer Lesende auf.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_elediaai_core\output;

/**
 * Was aus einem `02-user-doc.md` auf einer Hilfeseite ankommen darf.
 *
 * Die Dokumente der Suite sind fuer das Team geschrieben und werden zugleich
 * Nutzenden gezeigt. Drei Dinge daraus gehoeren nicht auf eine Hilfeseite; die
 * ersten beiden sind der Grund, aus dem task25 ueberhaupt entstand:
 *
 * - Der **Meta-Block** am Anfang sagt, was das Dokument fuer das Team ist
 *   ("Quelle der Wahrheit fuer sichtbares Verhalten"). Fuer Lesende ist das
 *   eine Aussage ueber ein Dokument, das sie gar nicht als Dokument sehen.
 * - **Verweise auf Nachbardokumente** (`00-master.md`, `01-features.md`,
 *   `03-dev-doc.md`) sind Sackgassen: die Dateien liegen im Repository, nicht
 *   auf der Website.
 * - Die **Ueberschriftenebenen**: ein Dokument beginnt mit `#`, aber die Seite
 *   traegt bereits eine Ueberschrift. Zwei h1 sind nicht nur semantisch
 *   falsch, sie sehen auch falsch aus -- am 06.09.2026 gemessen war die
 *   Dokumentueberschrift mit 40px *groesser* als der Seitentitel mit 32px,
 *   unter Boost wie unter theme_elediaai. Jede Ebene rutscht deshalb um eine
 *   nach unten.
 *
 * Alles wird beim Rendern gerichtet, nicht in der Datei. Das Dokument bleibt
 * fuer das Team vollstaendig; die Hilfeseite zeigt nur, was sie zeigen kann.
 */
final class help_document {
    /**
     * Static-only.
     */
    private function __construct() {
    }

    /**
     * Liest ein Dokument und gibt es lesefertig zurueck.
     *
     * @param string $pfad Absoluter Pfad zur Markdown-Datei.
     * @return string Markdown, oder die leere Zeichenkette, wenn es sie nicht gibt.
     */
    public static function read(string $pfad): string {
        if (!is_readable($pfad)) {
            return '';
        }
        $text = file_get_contents($pfad);
        return $text === false ? '' : self::clean($text);
    }

    /**
     * Liest einen einzelnen Abschnitt aus einem Dokument.
     *
     * Schneidet von der genannten Ueberschrift bis zur naechsten derselben
     * Ebene. Die Ueberschrift selbst bleibt draussen: das Kapitel, das den
     * Abschnitt zeigt, traegt bereits einen Titel, und zwei Ueberschriften
     * mit demselben Text uebereinander sind eine Doppelung, keine Gliederung.
     *
     * Gedacht fuer Dokumente, die mehrere Vertraege in einer Datei fuehren --
     * `03-dev-doc.md` ist so eines. Wer den ganzen Text will, nimmt
     * {@see self::read()}.
     *
     * @param string $pfad Absoluter Pfad zur Markdown-Datei.
     * @param string $ueberschrift Text der Ueberschrift, ohne die Rauten.
     * @param int $ebene Ebene der Ueberschrift, also die Zahl der Rauten.
     * @param bool $nurvorspann Nur bis zur ersten tieferen Ueberschrift lesen.
     * @return string Markdown des Abschnitts, leer wenn es ihn nicht gibt.
     */
    public static function section(
        string $pfad,
        string $ueberschrift,
        int $ebene = 2,
        bool $nurvorspann = false
    ): string {
        if (!is_readable($pfad)) {
            return '';
        }
        $text = file_get_contents($pfad);
        if ($text === false) {
            return '';
        }

        $ebene = max(1, $ebene);
        $rauten = str_repeat('#', $ebene);
        // Der Abschnitt endet an der naechsten Ueberschrift derselben *oder
        // einer hoeheren* Ebene. Nur auf dieselbe zu pruefen war falsch: der
        // letzte Unterabschnitt lief dann ueber die naechste `##` hinweg bis
        // zur uebernaechsten `###` -- gemessen 75 Zeilen statt 36.
        $muster = '~^' . preg_quote($rauten, '~') . '\s+' . preg_quote($ueberschrift, '~')
            . '\s*$(.*?)(?=^\#{1,' . $ebene . '}\s|\z)~ms';
        if (!preg_match($muster, $text, $treffer)) {
            return '';
        }

        $abschnitt = $treffer[1];
        if ($nurvorspann) {
            // Der Vorspann eines Abschnitts, ohne seine Unterabschnitte. Fuer
            // ein Uebersichtskapitel, dem die Einleitung und die Tabelle
            // genuegen, waehrend jeder Unterabschnitt sein eigenes Kapitel hat.
            $abschnitt = (string) preg_split('~^\#{' . ($ebene + 1) . '}\s~m', $abschnitt, 2)[0];
        }

        return self::clean(trim($abschnitt));
    }

    /**
     * Nimmt heraus, was nicht auf eine Hilfeseite gehoert.
     *
     * @param string $markdown
     * @return string
     */
    public static function clean(string $markdown): string {
        $text = self::drop_meta($markdown);
        $text = self::drop_internal_references($text);
        $text = self::demote_headings($text);

        // Mehr als zwei Leerzeilen entstehen beim Herausnehmen und sagen nichts.
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim((string) $text) . "\n";
    }

    /**
     * Schiebt jede Ueberschrift um eine Ebene nach unten.
     *
     * Die Seite traegt ihre eigene h1; die des Dokuments wird zur h2, und der
     * Rest folgt. Damit bleibt der Seitentitel die groesste Ueberschrift, und
     * die Seite hat wieder genau eine h1.
     *
     * Die Groessen bleiben dem Theme ueberlassen: eine Hilfeseite soll aussehen
     * wie jede andere Inhaltsseite der Website und nicht wie ein Fremdkoerper
     * mit eigener Skala. Genau darum wird hier die Ebene verschoben und keine
     * Schriftgroesse gesetzt.
     *
     * Ebene sechs bleibt sechs -- tiefer geht Markdown nicht.
     *
     * @param string $markdown
     * @return string
     */
    private static function demote_headings(string $markdown): string {
        return (string) preg_replace_callback(
            '~^(\#{1,6})(\s)~m',
            static fn(array $treffer): string => str_repeat('#', min(6, strlen($treffer[1]) + 1)) . $treffer[2],
            $markdown
        );
    }

    /**
     * Entfernt einen fuehrenden "## Meta"-Abschnitt samt seinem Trennstrich.
     *
     * Nur der *fuehrende*: ein "## Meta" mitten im Text waere eine Ueberschrift
     * wie jede andere, und die gehoert dem Dokument.
     *
     * @param string $markdown
     * @return string
     */
    private static function drop_meta(string $markdown): string {
        $muster = '~^(\# [^\n]*\n+)?\#\# Meta\b.*?(?:\n---\n|(?=\n\#\# ))~s';
        return (string) preg_replace_callback($muster, static function (array $treffer): string {
            // Die Hauptueberschrift bleibt, der Meta-Abschnitt faellt.
            return $treffer[1] ?? '';
        }, $markdown, 1);
    }

    /**
     * Entfernt Verweise auf Nachbardokumente des Repositorys.
     *
     * Zwei Formen kommen vor, und sie brauchen verschiedene Schnitte:
     * ein Einschub in Klammern ("(siehe `00-master.md` adr01)") faellt fuer
     * sich; ein ganzer Satz, der auf ein Dokument zeigt, faellt als Satz --
     * bliebe er halb stehen, waere das schlimmer als der Verweis.
     *
     * @param string $markdown
     * @return string
     */
    private static function drop_internal_references(string $markdown): string {
        $dokument = '`\d\d-[a-z0-9-]+\.md`';

        // Klammereinschub, mit oder ohne "siehe".
        $text = preg_replace('~\s*\((?:siehe |vgl\. |see )?' . $dokument . '[^)]*\)~u', '', $markdown);

        // Ganzer Satz, der ein Nachbardokument nennt. Satzgrenze ist ein Punkt
        // mit folgendem Leerraum oder Zeilenende; Semikolon zaehlt mit, weil
        // die Dokumente Verweise gern anhaengen.
        $text = preg_replace(
            '~(?<=^|[.;]\s)[^.;\n]*' . $dokument . '[^.;\n]*[.;]~um',
            '',
            (string) $text
        );

        // Zurueckgebliebener Leerraum vor Satzzeichen und am Zeilenende.
        $text = preg_replace('~[ \t]+([.,;])~u', '$1', (string) $text);
        $text = preg_replace('~[ \t]+$~um', '', (string) $text);

        return (string) $text;
    }
}
