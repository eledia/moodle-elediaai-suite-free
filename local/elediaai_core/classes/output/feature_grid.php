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
 * Shared feature-card grid renderer for the AI Suite dashboards.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_elediaai_core\output;

use html_writer;
use local_elediaai_core\feature\descriptor;
use local_elediaai_core\feature\registry;
use local_elediaai_core\output\lucide_icon;
use moodle_url;

/**
 * Renders feature descriptors as the `lh-plugin-card` grid used by the
 * suite-wide Overview and feature-registry surfaces.
 */
class feature_grid {
    /** @var string Drawn when a feature names an icon the sprite does not have. */
    private const ICON_FALLBACK = 'cube';

    /**
     * CSS for the suite-wide feature-card grid and audience legend.
     *
     * @return string Raw CSS (without a wrapping <style> tag).
     */
    public static function styles(): string {
        return '
.lh-ai-suite-legend {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    margin: 0 0 1rem;
}
.lh-ai-suite-legend__item {
    align-items: center;
    background: var(--eai-surface);
    border: 1px solid var(--lh-border);
    border-radius: var(--eai-radius-pill);
    color: var(--lh-muted);
    display: inline-flex;
    font-size: var(--font-size-small);
    font-weight: 700;
    gap: .4rem;
    line-height: 1.2;
    padding: .35rem .7rem;
    text-decoration: none;
    transition: border-color 120ms ease, color 120ms ease;
}
.lh-ai-suite-legend__item:hover,
.lh-ai-suite-legend__item:focus-visible {
    border-color: var(--eai-accent);
    color: var(--eai-accent);
    text-decoration: none;
}
/* Die gewaehlte Gruppe ist nicht nur farbig, sondern auch gefuellt: eine
   Farbe allein waere fuer alle, die sie nicht unterscheiden koennen, kein
   Unterschied.

   Die Fuellung ist --eai-accent-strong und nicht --eai-accent: die
   Beschriftung ist 14px fett, also Fliesstext, und Weiss auf --eai-accent
   haelt nur 3,01:1. Gemessen am 06.09.2026 -- mit dem tieferen Ton sind es
   4,52:1. */
.lh-ai-suite-legend__item--current {
    background: var(--eai-accent-strong);
    border-color: var(--eai-accent-strong);
    color: var(--eai-accent-ink);
}
.lh-ai-suite-legend__item--current:hover,
.lh-ai-suite-legend__item--current:focus-visible {
    background: var(--eai-accent-strong-hover);
    border-color: var(--eai-accent-strong-hover);
    color: var(--eai-accent-ink);
}
.lh-ai-suite-controls {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    margin: 0 0 .75rem;
}
.lh-ai-suite-controls__label {
    font-size: var(--font-size-small);
    font-weight: 700;
    margin: 0;
}
.lh-ai-suite-controls__input {
    background: var(--eai-surface);
    border: 1px solid var(--lh-border);
    border-radius: var(--eai-radius);
    color: var(--eai-fg);
    flex: 1 1 18rem;
    max-width: 32rem;
    padding: .4rem .7rem;
}
/* Der Knopf steht unmittelbar neben dem Eingabefeld. Gemessen am 06.09.2026:
   unter Boost traegt er Bootstraps 8px, das Feld die 10px der Suite -- zwei
   Radien nebeneinander sieht man. Unter theme_elediaai stimmten sie schon
   ueberein; diese Regel macht das themenunabhaengig. */
.lh-ai-suite-controls .btn {
    border-radius: var(--eai-radius);
}
.lh-ai-suite-controls__input:focus-visible {
    border-color: var(--eai-accent);
    outline: 2px solid var(--eai-accent);
    outline-offset: 1px;
}
/* Uniform, calm icon tiles (round, soft grey, slate glyph) — the audience is
   carried by the per-card audience label, so the
   grid reads clean instead of a patchwork of pastel colours. */
.lh-plugin-card__icon {
    align-items: center;
    background: var(--eai-bg);
    border-radius: 50%;
    color: var(--eai-fg-dim);
    display: inline-flex;
    height: 3rem;
    justify-content: center;
    width: 3rem;
}
.lh-plugin-card__icon .lucide {
    height: 1.5rem;
    width: 1.5rem;
}
/* Die Kachel-Illustrationen.
   Die Zeichnungen bringen keine Farben mit -- sie tragen Klassen, und die
   werden hier bedient. Deshalb folgt dasselbe Bild der Palette von
   theme_elediaai, bleibt unter Boost lesbar und funktioniert im dunklen
   Farbmodus, ohne dass jemand eine zweite Fassung zeichnet. Das geht nur,
   weil render_image() die SVG inline stellt: in einem <img> waere sie ein
   eigenes Dokument und saehe weder currentColor noch die --eai-Token. */
.eai-ill {
    display: block;
    height: 100%;
    max-width: 100%;
    width: 100%;
}
.eai-ill__line,
.eai-ill__hair,
.eai-ill__track,
.eai-ill__check,
.eai-ill__fill-stroke {
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.eai-ill__line {
    stroke: currentColor;
    stroke-width: 2;
    opacity: .5;
}
.eai-ill__hair {
    stroke: currentColor;
    stroke-width: 2;
    opacity: .26;
}
.eai-ill__track {
    stroke: currentColor;
    stroke-width: 2;
    opacity: .3;
    stroke-dasharray: 5 4;
}
.eai-ill__wash {
    fill: currentColor;
    opacity: .05;
    stroke: none;
}
.eai-ill__fill {
    fill: var(--eai-accent);
    stroke: none;
}
.eai-ill__fill-stroke {
    stroke: var(--eai-accent);
    stroke-width: 2.5;
}
/* Der Haken und die Zeilen, die auf der Akzentflaeche liegen: sie muessen
   gegen den Akzent stehen, nicht gegen den Kachelgrund. */
.eai-ill__check {
    stroke: var(--eai-accent-ink);
    stroke-width: 2.2;
}
.eai-ill__on-fill {
    fill: none;
    stroke: var(--eai-accent-ink);
    stroke-width: 2;
    stroke-linecap: round;
    opacity: .85;
}
/* Feste Hoehe statt Seitenverhaeltnis: bei 16:7 wuchs die Flaeche auf einer
   breiten Kachel auf 174px und nahm mehr Raum ein als der Text, den sie
   begleitet. Die Zeichnung passt sich ein, statt gestreckt zu werden. */
.lh-ai-suite-card__image {
    align-items: center;
    /* Aus currentColor gemischt, nicht als fester Schwarzschleier: so dreht
       sich die Flaeche mit, wenn die Karte hell oder dunkel wird, statt auf
       dunklem Grund unsichtbar zu sein. Die erste Zeile ist der Rueckfall
       fuer Browser ohne color-mix. */
    background: rgba(0, 0, 0, .035);
    background: color-mix(in srgb, currentColor 5%, transparent);
    border-radius: var(--eai-radius);
    display: flex;
    height: 6.5rem;
    justify-content: center;
    margin: 0 0 .85rem;
    overflow: hidden;
    padding: .5rem .75rem;
    transition: background-color .15s ease;
}
/* Das Symbol steht in derselben Flaeche wie eine Zeichnung, nur groesser als
   die alte Kachel -- es soll die Flaeche fuellen, nicht darin verloren gehen. */
.lh-ai-suite-card__placeholder {
    align-items: center;
    background: var(--eai-accent-wash);
    border-radius: 50%;
    color: var(--eai-accent-text);
    display: inline-flex;
    height: 3.4rem;
    justify-content: center;
    width: 3.4rem;
}
.lh-ai-suite-card__placeholder .lucide {
    height: 1.7rem;
    width: 1.7rem;
}
.lh-ai-suite-card__audience {
    color: var(--lh-muted);
    font-size: var(--font-size-small);
    font-weight: 700;
    letter-spacing: .04em;
    line-height: 1.2;
    margin-top: .15rem;
    text-transform: uppercase;
}
.lh-ai-suite-card .lh-plugin-card__actions {
    align-items: center;
    display: flex;
    justify-content: space-between;
    margin-top: auto;
    min-height: 48px;
    width: 100%;
}
/* Die ganze Kachel ist der Link.
   Der Titel traegt ihn -- damit hat er einen echten Namen -- und ein ::after
   legt sich ueber die Karte, so dass jede Stelle klickt, auch das Bild. Der
   fruehere Pfeilknopf war ein zweites Ziel fuer dasselbe Ziel: doppelt in der
   Tabreihenfolge, und daneben eine grosse Flaeche, die anklickbar aussah und
   nichts tat. */
.lh-ai-suite-card {
    position: relative;
}
.lh-ai-suite-card__link {
    color: inherit;
    text-decoration: none;
}
.lh-ai-suite-card__link::after {
    content: "";
    inset: 0;
    position: absolute;
}
.lh-ai-suite-card__link:hover {
    color: var(--eai-accent-text);
    text-decoration: none;
}
/* Der Fokusring gehoert um die Karte, nicht um die Ueberschrift: geklickt
   wird die Karte, also muss man am Ring sehen, was man trifft. */
.lh-ai-suite-card:has(.lh-ai-suite-card__link:focus-visible) {
    outline: 2px solid var(--eai-accent);
    outline-offset: 2px;
}
.lh-ai-suite-card__link:focus-visible {
    outline: none;
}
.lh-ai-suite-card:hover {
    border-color: var(--eai-accent);
}
.lh-ai-suite-card:hover .lh-ai-suite-card__image {
    background: var(--eai-accent-wash);
}
/* Das Zahnrad ist ein eigenes Ziel und muss ueber der Klickflaeche liegen. */
.lh-ai-suite-card__settings {
    position: relative;
    z-index: 1;
}
/* Eine Symbolaktion in unserer Karte -- ohne Farbregel erbte sie die
   Linkfarbe des Wirtsthemes und stand unter Boost blau in einer orangen
   Karte. Der Rahmen des Kunden gehoert ihm; die Flaeche darin uns. */
.lh-ai-suite-card__settings {
    color: var(--eai-accent);
    flex: 0 0 auto;
}
.lh-ai-suite-card__settings:hover,
.lh-ai-suite-card__settings:focus-visible {
    color: var(--eai-accent-hover);
}
.lh-ai-suite-section__title {
    color: var(--eai-fg);
    font-size: var(--font-size-h2);
    margin: 2rem 0 .25rem;
}
.lh-ai-suite-grid + .lh-ai-suite-section__title {
    border-top: 1px solid var(--eai-line);
    padding-top: 2rem;
}
.lh-ai-suite-section__intro {
    color: var(--eai-muted);
    margin: 0 0 1.25rem;
    max-width: 60ch;
}
.lh-ai-suite-card__where {
    align-items: start;
    color: var(--eai-muted);
    display: flex;
    font-size: var(--font-size-small);
    gap: .4rem;
    margin: 0 0 .25rem;
}
.lh-ai-suite-card__where svg {
    flex: none;
    height: 1em;
    margin-top: .15em;
    width: 1em;
}
';
    }

    /**
     * Render the grid.
     *
     * @param \local_elediaai_core\feature\descriptor[] $features Features to display, keyed by id.
     * @param array $options {
     *     showaudiencebadge?: bool  Show the per-card audience label (default true).
     *     showsettingsgear?: bool   Show the settings gear-link for users with
     *                               moodle/site:config (default true).
     * }
     * @return string HTML.
     */
    public static function render(array $features, array $options = []): string {
        $showaudiencebadge = $options['showaudiencebadge'] ?? true;
        $showsettingsgear = $options['showsettingsgear'] ?? true;
        $grouped = $options['groupbykind'] ?? true;
        // Ids that pass the current filter. Everything else is rendered but
        // hidden: the page ships every tile so the browser can widen the
        // filter again without a round trip, while a reader without
        // JavaScript -- or someone opening a bookmarked, filtered link --
        // still sees exactly the selection the address asks for.
        $matching = $options['matching'] ?? null;
        $cansiteconfig = $showsettingsgear
            && has_capability('moodle/site:config', \core\context\system::instance());

        if (!$grouped) {
            return self::render_grid($features, $showaudiencebadge, $cansiteconfig, $matching);
        }

        // Two sections, because there are two sorts of thing here and pretending
        // otherwise is what made the page look arbitrary. Everything in the first
        // opens; nothing in the second can, so those cards say where to find the
        // thing instead of offering a door that is not there.
        $bykind = [descriptor::KIND_PAGE => [], descriptor::KIND_IN_COURSE => []];
        foreach ($features as $id => $feature) {
            $bykind[registry::kind($id)][$id] = $feature;
        }

        $html = '';
        foreach ($bykind as $kind => $group) {
            if (empty($group)) {
                continue;
            }
            // A section whose every tile is filtered away folds up with them,
            // heading and all -- otherwise the page keeps a promise ("tools you
            // open") under which nothing stands.
            $empty = $matching !== null
                && array_intersect(array_keys($group), $matching) === [];
            $html .= html_writer::start_tag('section', [
                'class' => 'lh-ai-suite-section',
                'data-region' => 'feature-section',
                'hidden' => $empty ? 'hidden' : null,
            ]);
            $html .= html_writer::tag(
                'h2',
                get_string('dashboard_kind_' . $kind, 'local_elediaai_core'),
                ['class' => 'lh-ai-suite-section__title']
            );
            $html .= html_writer::tag(
                'p',
                get_string('dashboard_kind_' . $kind . '_intro', 'local_elediaai_core'),
                ['class' => 'lh-ai-suite-section__intro']
            );
            $html .= self::render_grid($group, $showaudiencebadge, $cansiteconfig, $matching);
            $html .= html_writer::end_tag('section');
        }

        return $html;
    }

    /**
     * One grid of cards, without a heading of its own.
     *
     * @param \local_elediaai_core\feature\descriptor[] $features
     * @param bool $showaudiencebadge
     * @param bool $cansiteconfig
     * @return string HTML.
     */
    private static function render_grid(
        array $features,
        bool $showaudiencebadge,
        bool $cansiteconfig,
        ?array $matching = null
    ): string {
        $html = html_writer::start_tag('div', [
            'class' => 'lh-plugin-grid lh-plugin-grid--cols-3 lh-ai-suite-grid',
            'role' => 'list',
        ]);

        foreach ($features as $id => $feature) {
            $hidden = $matching !== null && !in_array($id, $matching, true);
            $html .= self::render_card($feature, $showaudiencebadge, $cansiteconfig, $hidden);
        }

        $html .= html_writer::end_tag('div');

        return $html;
    }

    /**
     * The search box and the audience filter, above the grid.
     *
     * The colour legend used to sit here and do nothing but explain. It is
     * the same four groups a reader wants to filter by, so the legend *is*
     * the filter now: one control instead of a key next to a control.
     *
     * Server-side, over GET. Twenty-two tiles would filter fine in the
     * browser, but a link that survives being bookmarked and works without
     * JavaScript is worth more than the animation -- and the guide's handbook
     * search next door already works exactly this way.
     *
     * @param moodle_url $baseurl Page the form and the pills submit to.
     * @param string $query Current search phrase.
     * @param string $audience Currently selected audience, '' for all.
     * @return string HTML.
     */
    public static function render_controls(moodle_url $baseurl, string $query = '', string $audience = ''): string {
        $html = html_writer::start_tag('form', [
            'class' => 'lh-ai-suite-controls',
            'method' => 'get',
            'action' => $baseurl->out_omit_querystring(),
            'role' => 'search',
            'data-region' => 'feature-search-form',
        ]);

        // Die Zielgruppe reist als verstecktes Feld mit, damit eine Suche die
        // gewaehlte Gruppe nicht stillschweigend aufhebt.
        if ($audience !== '') {
            $html .= html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'audience',
                'value' => $audience,
            ]);
        }

        $html .= html_writer::tag(
            'label',
            get_string('dashboard_search_label', 'local_elediaai_core'),
            ['for' => 'lh-ai-suite-q', 'class' => 'lh-ai-suite-controls__label']
        );
        $html .= html_writer::empty_tag('input', [
            'type' => 'search',
            'id' => 'lh-ai-suite-q',
            'name' => 'q',
            'value' => $query,
            'class' => 'lh-ai-suite-controls__input',
            'placeholder' => get_string('dashboard_search_placeholder', 'local_elediaai_core'),
            'data-region' => 'feature-search',
            'autocomplete' => 'off',
        ]);
        // The button is the no-JavaScript path and nothing else. Where the
        // module runs it removes it: a control that only repeats what typing
        // has already done invites the reader to wonder what it does
        // differently.
        $html .= html_writer::tag(
            'button',
            get_string('dashboard_search_button', 'local_elediaai_core'),
            ['type' => 'submit', 'class' => 'btn btn-secondary', 'data-region' => 'feature-search-submit']
        );
        $html .= html_writer::end_tag('form');

        $html .= self::render_audience_filter($baseurl, $query, $audience);

        return $html;
    }

    /**
     * The four audience groups as filter links, plus "all".
     *
     * @param moodle_url $baseurl
     * @param string $query Kept, so filtering does not drop the search.
     * @param string $audience Currently selected audience, '' for all.
     * @return string HTML.
     */
    /**
     * Die vier Zielgruppen als Pillen, plus "Alle".
     *
     * Ohne Farbpunkte, und das ist eine Aenderung gegenueber der Legende, aus
     * der diese Reihe hervorging. Eine Legende braucht einen Schluessel: der
     * Punkt zeigte, welche Farbe anderswo welche Gruppe meint. Ein Filter
     * braucht ihn nicht -- die Pille traegt den Namen der Gruppe im Klartext.
     *
     * Gemessen am 06.09.2026 hielten die vier Punkte 1,05:1 bis 1,15:1 gegen
     * die weisse Pille. Sichtbar waren sie damit nicht, und ihre Farben kamen
     * auf der Seite kein zweites Mal vor -- zwei von ihnen waren sogar die
     * Wash-Toene von Akzent und Erfolg, also Rollen, die etwas anderes
     * bedeuten. Vier Farben, die niemand sieht und die nichts benennen,
     * sind kein Schluessel.
     *
     * @param moodle_url $baseurl Seite, auf die die Pillen zeigen.
     * @param string $query Aktuelle Suche, damit sie beim Filtern nicht faellt.
     * @param string $audience Aktuell gewaehlte Gruppe.
     * @return string
     */
    private static function render_audience_filter(moodle_url $baseurl, string $query, string $audience): string {
        $html = html_writer::start_tag('nav', [
            'class' => 'lh-ai-suite-legend',
            'aria-label' => get_string('feature_audience_legend', 'local_elediaai_core'),
        ]);

        $items = array_merge([''], [
            registry::AUDIENCE_ADMIN,
            registry::AUDIENCE_TEACHER,
            registry::AUDIENCE_DESIGNER,
            registry::AUDIENCE_STUDENT,
        ]);

        foreach ($items as $key) {
            $params = [];
            if ($query !== '') {
                $params['q'] = $query;
            }
            if ($key !== '') {
                $params['audience'] = $key;
            }
            $url = new moodle_url($baseurl->out_omit_querystring(), $params);

            $label = $key === ''
                ? get_string('dashboard_audience_all', 'local_elediaai_core')
                : get_string('feature_audience_' . $key, 'local_elediaai_core');
            $classes = 'lh-ai-suite-legend__item';
            $attributes = ['class' => $classes];
            if ($key === $audience) {
                $attributes['class'] = $classes . ' lh-ai-suite-legend__item--current';
                $attributes['aria-current'] = 'true';
            }
            $attributes['data-region'] = 'feature-audience';
            $attributes['data-audience'] = $key;
            $html .= html_writer::link($url, html_writer::span(s($label)), $attributes);
        }

        $html .= html_writer::end_tag('nav');
        return $html;
    }

    /**
     * The handbook, opened with one component's chapters on top.
     *
     * Falls back to the dashboard itself when the guide is absent: a card that
     * leads nowhere is worse than a card that leads back where you came from.
     *
     * @param string $component Frankenstyle component of the feature.
     * @return moodle_url
     */
    private static function handbook_url(string $component): moodle_url {
        if (\core_component::get_component_directory('local_elediaai_guide') === null) {
            return new moodle_url('/local/elediaai_core/index.php');
        }
        return new moodle_url('/local/elediaai_guide/index.php', ['component' => $component]);
    }

    /**
     * The feature's illustration, inlined, or nothing when it ships none.
     *
     * Inlined rather than served through an `<img>` tag, and that is not a
     * preference. An SVG loaded via `<img>` is an isolated document: it sees
     * neither `currentColor` nor the page's custom properties, so a drawing
     * referenced that way cannot follow the theme. Measured, not assumed --
     * the same file renders black in an `<img>` and in the page's colour when
     * inlined. Inlining is what lets one drawing work on eLeDia.ai's palette
     * and on Boost's, in light and in dark.
     *
     * Decorative on purpose: `aria-hidden` and no title, because the card
     * carries the feature's name as a heading right beneath it. A screen
     * reader announcing the name twice is worse than not describing a picture
     * that only sets a mood.
     *
     * @param descriptor $feature The feature.
     * @return string
     */
    /**
     * The quiet mark on a feature that does not call itself finished.
     *
     * Only the unfinished ones carry it. Sixteen of twenty tiles do, which
     * makes it closer to a texture than to a signal -- so it is deliberately
     * low-contrast rather than alarm-coloured, and the four released tools
     * stand out by having nothing.
     *
     * The state is the plugin's own `$plugin->maturity`; nothing is declared
     * twice, and a plugin that arrives later brings its answer with it.
     *
     * @param descriptor $feature
     * @return string
     */
    private static function render_maturity(descriptor $feature): string {
        if (registry::is_released($feature->component)) {
            return '';
        }

        $maturity = registry::maturity($feature->component);
        $key = $maturity !== null && $maturity < MATURITY_BETA
            ? 'feature_maturity_alpha'
            : 'feature_maturity_beta';

        return html_writer::tag('span', s(get_string($key, 'local_elediaai_core')), [
            'class' => 'lh-ai-suite-card__maturity',
            'title' => get_string($key . '_help', 'local_elediaai_core'),
        ]);
    }

    /**
     * The feature's illustration, inlined, or nothing when it ships none.
     *
     * Inlined rather than served through an `<img>` tag, and that is not a
     * preference. An SVG loaded via `<img>` is an isolated document: it sees
     * neither `currentColor` nor the page's custom properties, so a drawing
     * referenced that way cannot follow the theme. Measured, not assumed --
     * the same file renders black in an `<img>` and in the page's colour when
     * inlined. Inlining is what lets one drawing work on eLeDia.ai's palette
     * and on Boost's, in light and in dark.
     *
     * Decorative on purpose: `aria-hidden` and no title, because the card
     * carries the feature's name as a heading right beneath it. A screen
     * reader announcing the name twice is worse than not describing a picture
     * that only sets a mood.
     *
     * @param descriptor $feature The feature.
     * @return string
     */
    private static function render_image(descriptor $feature): string {
        if ($feature->imagename === null) {
            return self::render_image_fallback($feature);
        }

        // Only a plain file name, and only from the providing component's own
        // pix directory. The value comes from a provider rather than from a
        // request, but a descriptor is the one place a plugin outside this
        // repository writes into, and a name is cheaper to check than to trust.
        if (!preg_match('/^[a-z0-9_-]+$/', $feature->imagename)) {
            debugging(
                'AI feature ' . $feature->id . ' asks for the illustration "'
                    . $feature->imagename . '", which is not a plain file name.',
                DEBUG_DEVELOPER
            );
            return self::render_image_fallback($feature);
        }

        $dir = \core_component::get_component_directory($feature->component);
        if ($dir === null) {
            return self::render_image_fallback($feature);
        }

        $path = $dir . '/pix/' . $feature->imagename . '.svg';
        if (!is_readable($path)) {
            debugging(
                'AI feature ' . $feature->id . ' names the illustration "'
                    . $feature->imagename . '", which is not readable at ' . $path . '.',
                DEBUG_DEVELOPER
            );
            return '';
        }

        $svg = file_get_contents($path);
        if ($svg === false || !str_contains($svg, '<svg')) {
            return '';
        }

        // The drawings ship with this suite and are reviewed with it; what is
        // stripped here is the one thing a stray editor export adds that has
        // no business inside a page.
        $svg = preg_replace('/<\?xml[^>]*\?>/', '', $svg);
        $svg = preg_replace('/<!DOCTYPE[^>]*>/', '', $svg);

        return html_writer::div(
            trim((string) $svg),
            'lh-ai-suite-card__image',
            ['aria-hidden' => 'true']
        );
    }

    /**
     * The same area, filled with the feature's icon, for a feature with no drawing.
     *
     * Without this a card that ships no illustration has a different shape
     * from its neighbours -- title at the top, a hole where the picture is on
     * every other card. One tile in that state makes the whole grid look
     * unfinished, and the reader blames the feature rather than the missing
     * file.
     *
     * @param descriptor $feature The feature.
     * @return string
     */
    private static function render_image_fallback(descriptor $feature): string {
        return html_writer::div(
            html_writer::div(
                self::render_icon($feature),
                'lh-ai-suite-card__placeholder ' . registry::icon_modifier($feature->id)
            ),
            'lh-ai-suite-card__image lh-ai-suite-card__image--placeholder',
            ['aria-hidden' => 'true']
        );
    }

    /**
     * The feature's icon, never an empty tile.
     *
     * `lucide_icon::render()` answers an unknown name with the empty string,
     * which used to paint a bare grey circle on the dashboard -- twice, as it
     * happens, because two plugins named icons the sprite does not carry. A
     * placeholder is a poor icon; an empty circle is a bug that nobody reports
     * because it looks deliberate.
     *
     * @param descriptor $feature The feature.
     * @return string
     */
    private static function render_icon(descriptor $feature): string {
        $svg = lucide_icon::render($feature->icon);
        if ($svg !== '') {
            return $svg;
        }

        debugging(
            'AI feature ' . $feature->id . ' (' . $feature->component . ') asks for the icon "'
                . $feature->icon . '", which the shared sprite does not contain.',
            DEBUG_DEVELOPER
        );
        return lucide_icon::render(self::ICON_FALLBACK);
    }

    /**
     * One feature card.
     *
     * @param descriptor $feature The feature to draw.
     * @param bool $showaudiencebadge Whether to print the audience label.
     * @param bool $cansiteconfig Whether the visitor may open settings.
     * @return string
     */
    private static function render_card(
        descriptor $feature,
        bool $showaudiencebadge,
        bool $cansiteconfig,
        bool $hidden = false
    ): string {
        $cardclasses = 'lh-plugin-card lh-ai-suite-card';
        // Installed, but not part of the licence (#29): shown, dimmed like a
        // placeholder, and leading to the handbook instead of into a page
        // that would turn the visitor away.
        $locked = registry::is_locked($feature);
        if ($feature->comingsoon || $feature->installrequired || $locked) {
            $cardclasses .= ' lh-plugin-card--locked';
        }

        // Wer keine eigene Seite hat, fuehrt ins Handbuch -- zu den Kapiteln
        // dieser Komponente. Bis hierher stand eine Erklaerseite im Kern, die
        // dieselben Angaben ein zweites Mal ausgab; das Handbuch schreibt das
        // Plugin selbst, und das Panel daneben zeigt es ohnehin schon.
        $primaryurl = $feature->installrequired && $feature->installurl !== null
            ? $feature->installurl
            : ($feature->launchurl ?? self::handbook_url($feature->component));
        if ($locked) {
            $primaryurl = self::handbook_url($feature->component);
        }

        $card = html_writer::start_tag('article', [
            'class' => $cardclasses,
            'role' => 'listitem',
            'data-feature-id' => s($feature->id),
            // The two values the browser filters on. They carry exactly what
            // registry::narrow() reads, so the live filter and the server-side
            // one cannot drift into two different answers.
            'data-search' => registry::search_haystack($feature),
            'data-audience' => registry::audience($feature->id),
            'hidden' => $hidden ? 'hidden' : null,
        ]);
        $card .= self::render_image($feature);
        $card .= html_writer::start_tag('div', ['class' => 'lh-plugin-card__top']);
        $card .= html_writer::start_tag('div', ['class' => 'lh-plugin-card__meta']);
        // Die Zeile erscheint nur, wenn sie etwas sagt.
        //
        // Bis zum 06.09.2026 trug sie jede Kachel. Nachgezaehlt auf der
        // gerenderten Seite: 21 Kacheln, 21 Zeilen, zwei verschiedene Werte --
        // 14 mal "Verfuegbar" und 7 mal "Im Kurs", und beide jeweils konstant
        // innerhalb ihres Abschnitts. Die Seite ist nach genau dieser Grenze
        // geteilt und die Ueberschriften sagen es bereits ("Werkzeuge, die Sie
        // oeffnen" / "In Ihren Kursen verfuegbar"); wo etwas zu finden ist,
        // steht ausserdem im Nutzungshinweis darunter. Eine Angabe, die auf
        // allen Nachbarn dieselbe ist, unterscheidet nichts -- sie kostet nur
        // eine Zeile ueber jedem Titel.
        //
        // "Installierbar" und "In Vorbereitung" bleiben: die stehen auf
        // wenigen Kacheln und genau das ist ihr Wert.
        $kicker = null;
        if ($locked) {
            $kicker = get_string('feature_status_locked', 'local_elediaai_core');
        } else if ($feature->installrequired) {
            $kicker = get_string('feature_status_install', 'local_elediaai_core');
        } else if ($feature->comingsoon) {
            $kicker = get_string('feature_status_coming', 'local_elediaai_core');
        }
        if ($kicker !== null) {
            $card .= html_writer::tag(
                'div',
                s($kicker),
                ['class' => 'lh-plugin-card__kicker lh-eyebrow lh-eyebrow--upper']
            );
        }
        // Ein einziger Link je Karte, benannt nach dem Feature, und ein
        // ::after darueber, das die ganze Kachel zur Klickflaeche macht. Der
        // fruehere Pfeilknopf war ein zweites Ziel fuer dasselbe Ziel: doppelt
        // in der Tabreihenfolge, und daneben eine grosse Flaeche, die
        // anklickbar aussah und nichts tat. Das Zahnrad bleibt ein eigener
        // Link und liegt per z-index darueber.
        $card .= html_writer::tag(
            'h2',
            html_writer::link($primaryurl, s($feature->name), ['class' => 'lh-ai-suite-card__link']),
            ['class' => 'lh-plugin-card__title']
        );
        if ($showaudiencebadge) {
            $card .= html_writer::tag('div', s(registry::audience_label($feature->id)), [
                'class' => 'lh-ai-suite-card__audience',
            ]);
        }
        $card .= self::render_maturity($feature);
        $card .= html_writer::end_tag('div');
        $card .= html_writer::end_tag('div');

        $card .= html_writer::tag('p', s($feature->description), ['class' => 'lh-plugin-card__body']);
        if ($locked) {
            $card .= html_writer::tag(
                'p',
                lucide_icon::render('lock') . html_writer::span(s(get_string('feature_locked_hint', 'local_elediaai_core'))),
                ['class' => 'lh-ai-suite-card__where']
            );
        }

        // The answer on the card, not one click away: the question a course
        // capability raises is "where do I find this", and it is short enough
        // to answer here.
        if ($feature->usagehint !== null && trim($feature->usagehint) !== '') {
            $card .= html_writer::tag(
                'p',
                lucide_icon::render('compass') . html_writer::span(s($feature->usagehint)),
                ['class' => 'lh-ai-suite-card__where']
            );
        }

        $actions = html_writer::start_tag('div', ['class' => 'lh-plugin-card__actions']);
        if ($cansiteconfig && $feature->configurl !== null) {
            $actions .= html_writer::link(
                $feature->configurl,
                lucide_icon::render('gear')
                    . html_writer::tag('span', s(get_string('feature_settings', 'local_elediaai_core')), [
                        'class' => 'accesshide',
                    ]),
                [
                    'class' => 'lh-btn-action lh-ai-suite-card__settings',
                    'aria-label' => get_string('feature_settings', 'local_elediaai_core'),
                    'title' => get_string('feature_settings', 'local_elediaai_core'),
                ]
            );
        }
        $actions .= html_writer::end_tag('div');

        $card .= $actions;
        $card .= html_writer::end_tag('article');

        return $card;
    }
}
