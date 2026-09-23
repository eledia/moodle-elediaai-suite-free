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
 * Sync the eledia.ai "Wissensbasis" course from the suite catalog.
 *
 * Builds / updates a single course with one section per suite plugin (plus an
 * intro section), reading the canonical catalog supplied by the deployment
 * process. Idempotent: managed page resources carry the
 * idnumber "eledia-ai-<slug>" and are recreated on each run; manually added
 * content is left untouched. Optionally opens guest access and triggers a
 * local_elediaai_sources reindex so the website chat can retrieve from it.
 *
 * Run from the repository root through Docker Compose, e.g. on demo:
 *   docker compose ... exec -T moodle php \
 *     public/local/elediaai_core/cli/sync_knowledge_course.php \
 *     --catalog=- --guest --reindex < content/suite-catalog.json
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// The config path is resolved dynamically for Moodle 4.5 and 5.x layouts.
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
define('CLI_SCRIPT', true);

$configfile = __DIR__ . '/../../../config.php';
if (!is_readable($configfile)) {
    // Moodle 5.1+ stores plugins below public/, while config.php remains in
    // the installation root. Older Moodle releases use the flatter layout.
    $configfile = __DIR__ . '/../../../../config.php';
}
require($configfile);
// phpcs:enable moodle.Files.MoodleInternal.MoodleInternalGlobalState
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/resourcelib.php');
require_once($CFG->libdir . '/enrollib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'idnumber'  => 'eledia-ai-knowledge',
        'shortname' => 'eledia-ai-wissensbasis',
        'fullname'  => 'eledia.ai — Wissensbasis',
        'category'  => 1,
        'catalog'   => '',
        'guest'     => false,
        'reindex'   => false,
        'help'      => false,
    ],
    [
        'h' => 'help',
    ]
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['help']) {
    cli_writeln(<<<'EOT'
Sync the eledia.ai knowledge-base course from an external suite catalog.

Creates (or updates) one course, one section per suite plugin plus an intro
section. Managed page resources use the idnumber "eledia-ai-<slug>" and are
recreated on every run; other course content is preserved.

Options:
  --idnumber=STRING   Course idnumber used to find/create the course.
                      Default: eledia-ai-knowledge
  --shortname=STRING  Shortname when the course is created. Default:
                      eledia-ai-wissensbasis
  --fullname=STRING   Fullname when the course is created.
  --category=INT      Category id for a newly created course. Default: 1
  --catalog=PATH      Required catalog JSON. Use "-" to read it from STDIN.
  --guest             Enable guest access (no password) and make the course
                      visible — so its pages are a public demo and the website
                      chat's citations link into a live Moodle.
  --reindex           Trigger a local_elediaai_sources reindex of the course after
                      syncing (no-op if local_elediaai_sources is absent).
  -h, --help          Show this help.

Example:
  php public/local/elediaai_core/cli/sync_knowledge_course.php \
    --catalog=- --guest --reindex < content/suite-catalog.json
EOT);
    exit(0);
}

// Run as admin so created activities/events have a sane author.
$USER = get_admin();

// ---------------------------------------------------------------------------
// Load the canonical catalog without storing it in the Moodle plugin/webroot.
// ---------------------------------------------------------------------------
$catalogsource = trim((string) $options['catalog']);
if ($catalogsource === '') {
    cli_error('Missing required --catalog=PATH option (use --catalog=- for STDIN).');
}
if ($catalogsource === '-') {
    $cataloglabel = 'STDIN';
    $catalogjson = stream_get_contents(STDIN);
} else {
    $cataloglabel = $catalogsource;
    if (!is_readable($catalogsource)) {
        cli_error("Catalog not found or unreadable: {$catalogsource}");
    }
    $catalogjson = file_get_contents($catalogsource);
}
if (!is_string($catalogjson) || trim($catalogjson) === '') {
    cli_error("Catalog is empty or unreadable: {$cataloglabel}");
}
$catalog = json_decode($catalogjson, true);
if (!is_array($catalog) || $catalog === []) {
    cli_error("Catalog is empty or invalid JSON: {$cataloglabel}");
}

// ---------------------------------------------------------------------------
// Find or create the course.
// ---------------------------------------------------------------------------
$idnumber = (string) $options['idnumber'];
$course = $DB->get_record('course', ['idnumber' => $idnumber]);
if (!$course) {
    if (!$DB->record_exists('course_categories', ['id' => (int) $options['category']])) {
        cli_error('Category id ' . (int) $options['category'] . ' does not exist.');
    }
    $data = new stdClass();
    $data->category = (int) $options['category'];
    $data->fullname = (string) $options['fullname'];
    $data->shortname = (string) $options['shortname'];
    $data->idnumber = $idnumber;
    $data->format = 'topics';
    $data->numsections = count($catalog);
    $data->visible = 1;
    $data->summary = 'Automatisch gepflegter Wissensbasis-Kurs der eledia.ai Suite. '
        . 'Ein Abschnitt pro Plugin; Quelle ist der externe Suite-Katalog.';
    $data->summaryformat = FORMAT_HTML;
    $course = create_course($data);
    cli_writeln("Created course #{$course->id} ({$idnumber}).");
} else {
    cli_writeln("Using existing course #{$course->id} ({$idnumber}).");
}

// Section 0 = intro, sections 1..N = plugins. Make sure they all exist.
$sectioncount = count($catalog);
course_create_sections_if_missing($course, range(0, $sectioncount));

$pagemodule = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);

// ---------------------------------------------------------------------------
// Intro section (0).
// ---------------------------------------------------------------------------
sync_section(
    $course,
    0,
    'Über die eledia.ai Suite',
    '<p>Die eledia.ai Suite bündelt KI-Funktionen nativ in Moodle. '
        . 'Jeder Abschnitt beschreibt ein Plugin der Suite. Diese Inhalte werden '
        . 'automatisch aus dem Produktkatalog erzeugt und speisen zugleich die '
        . 'Website und den KI-Assistenten auf eledia.ai.</p>'
);
sync_page(
    $course,
    0,
    $pagemodule,
    'eledia-ai-suite-intro',
    'eledia.ai — Überblick',
    '<p>Die Suite ist datensouverän (frei wählbares Sprachmodell, auch lokal), '
        . 'bündelt KI-Werkzeuge unter einer Governance und ist als echte '
        . 'Moodle-Plugins integriert — viele davon Open Source.</p>'
);

// ---------------------------------------------------------------------------
// One section + page per plugin.
// ---------------------------------------------------------------------------
$sectionnum = 0;
foreach ($catalog as $plugin) {
    $sectionnum++;
    $slug = (string) ($plugin['slug'] ?? '');
    $title = (string) ($plugin['title'] ?? $slug);
    if ($slug === '') {
        cli_writeln('  ! skipping catalog entry without slug');
        continue;
    }

    $summary = '<p>' . htmlspecialchars((string) ($plugin['intro'] ?? ''), ENT_QUOTES) . '</p>'
        . '<p><em>' . htmlspecialchars((string) ($plugin['audience'] ?? ''), ENT_QUOTES) . '</em>'
        . ' · Status: ' . htmlspecialchars((string) ($plugin['status'] ?? ''), ENT_QUOTES)
        . ' · <code>' . htmlspecialchars((string) ($plugin['component'] ?? ''), ENT_QUOTES) . '</code></p>';
    sync_section($course, $sectionnum, $title, $summary);

    sync_page(
        $course,
        $sectionnum,
        $pagemodule,
        'eledia-ai-' . $slug,
        $title,
        build_plugin_html($plugin)
    );
    cli_writeln("  section {$sectionnum}: {$title}");
}

rebuild_course_cache($course->id, true);

// ---------------------------------------------------------------------------
// Optional: guest access (public demo + citation targets for the chat).
// ---------------------------------------------------------------------------
if ($options['guest']) {
    enable_guest_access($course);
    cli_writeln('Guest access enabled and course set visible.');
}

// ---------------------------------------------------------------------------
// Optional: trigger an AI Sources reindex so the chat can retrieve from it.
// ---------------------------------------------------------------------------
if ($options['reindex']) {
    if (class_exists('\\local_elediaai_sources\\ingestion_manager')) {
        if (class_exists('\\local_elediaai_sources\\course_state')) {
            \local_elediaai_sources\course_state::set_ingested($course->id, true);
        }
        $result = (new \local_elediaai_sources\ingestion_manager())->reindex_course($course->id);
        cli_writeln('Reindex triggered: ' . json_encode($result));
    } else {
        cli_writeln('local_elediaai_sources not installed — skipping reindex.');
    }
}

cli_writeln('Done.');
exit(0);

// ===========================================================================
// Helpers.
// ===========================================================================

/**
 * Set a section's name and summary (idempotent).
 *
 * @param stdClass $course Course record.
 * @param int $sectionnum Section number (0-based), not course_sections.id.
 * @param string $name Section name.
 * @param string $summaryhtml Section summary as HTML.
 * @return void
 */
function sync_section(stdClass $course, int $sectionnum, string $name, string $summaryhtml): void {
    global $DB;
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum]);
    if (!$section) {
        return;
    }
    course_update_section($course, $section, (object) [
        'name' => $name,
        'summary' => $summaryhtml,
        'summaryformat' => FORMAT_HTML,
    ]);
}

/**
 * Ensure a managed mod_page with a stable idnumber exists in a section.
 *
 * Deletes any previous page carrying the same idnumber, then recreates it, so
 * the content always matches the catalog. Manually added activities (without
 * the "eledia-ai-*" idnumber) are never touched.
 *
 * @param stdClass $course Course record.
 * @param int $sectionnum Section number (0-based).
 * @param stdClass $pagemodule The {modules} record for "page".
 * @param string $cmidnumber Stable idnumber, e.g. "eledia-ai-ki-chat".
 * @param string $name Page (activity) name.
 * @param string $html Page body as HTML.
 * @return void
 */
function sync_page(
    stdClass $course,
    int $sectionnum,
    stdClass $pagemodule,
    string $cmidnumber,
    string $name,
    string $html
): void {
    global $DB;

    $existing = $DB->get_record('course_modules', ['course' => $course->id, 'idnumber' => $cmidnumber]);
    if ($existing) {
        course_delete_module((int) $existing->id);
    }

    $cleanhtml = clean_text($html, FORMAT_HTML);

    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'page';
    $moduleinfo->module = (int) $pagemodule->id;
    $moduleinfo->course = (int) $course->id;
    $moduleinfo->section = $sectionnum;
    $moduleinfo->visible = 1;
    $moduleinfo->visibleoncoursepage = 1;
    $moduleinfo->cmidnumber = $cmidnumber;
    $moduleinfo->name = core_text::substr($name, 0, 254);
    $moduleinfo->introeditor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
    $moduleinfo->content = $cleanhtml;
    $moduleinfo->contentformat = FORMAT_HTML;
    $moduleinfo->page = ['text' => $cleanhtml, 'format' => FORMAT_HTML, 'itemid' => 0];
    $moduleinfo->display = RESOURCELIB_DISPLAY_OPEN;
    $moduleinfo->printintro = 0;
    $moduleinfo->printlastmodified = 1;
    $moduleinfo->popupheight = 450;
    $moduleinfo->popupwidth = 620;

    add_moduleinfo($moduleinfo, $course);
}

/**
 * Build the page body for one plugin from its catalog entry.
 *
 * @param array $plugin Catalog entry.
 * @return string HTML.
 */
function build_plugin_html(array $plugin): string {
    $esc = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
    $html = '<p>' . $esc($plugin['intro'] ?? '') . '</p>';

    if (!empty($plugin['teacher'])) {
        $html .= '<h4>Für Lehrpersonen</h4><p>' . $esc($plugin['teacher']) . '</p>';
    }
    if (!empty($plugin['decision'])) {
        $html .= '<h4>Für Entscheider</h4><p>' . $esc($plugin['decision']) . '</p>';
    }
    if (!empty($plugin['features']) && is_array($plugin['features'])) {
        $html .= '<h4>Funktionen</h4><ul>';
        foreach ($plugin['features'] as $feature) {
            $html .= '<li>' . $esc($feature) . '</li>';
        }
        $html .= '</ul>';
    }
    if (!empty($plugin['useCases']) && is_array($plugin['useCases'])) {
        $html .= '<h4>Typische Einsatzszenarien</h4><ul>';
        foreach ($plugin['useCases'] as $usecase) {
            $html .= '<li>' . $esc($usecase) . '</li>';
        }
        $html .= '</ul>';
    }
    $html .= '<p><small>Komponente: <code>' . $esc($plugin['component'] ?? '') . '</code> · '
        . 'Status: ' . $esc($plugin['status'] ?? '') . '</small></p>';
    return $html;
}

/**
 * Enable password-free guest access and make the course visible.
 *
 * @param stdClass $course Course record.
 * @return void
 */
function enable_guest_access(stdClass $course): void {
    global $DB;

    if (!$course->visible) {
        course_change_visibility($course->id, true);
    }

    $plugin = enrol_get_plugin('guest');
    if (!$plugin) {
        return;
    }
    $instances = enrol_get_instances($course->id, false);
    $guest = null;
    foreach ($instances as $instance) {
        if ($instance->enrol === 'guest') {
            $guest = $instance;
            break;
        }
    }
    if (!$guest) {
        $instanceid = $plugin->add_instance($course, ['status' => ENROL_INSTANCE_ENABLED, 'password' => '']);
        $guest = $DB->get_record('enrol', ['id' => $instanceid]);
    }
    if ($guest && (int) $guest->status !== ENROL_INSTANCE_ENABLED) {
        $plugin->update_status($guest, ENROL_INSTANCE_ENABLED);
    }
}
