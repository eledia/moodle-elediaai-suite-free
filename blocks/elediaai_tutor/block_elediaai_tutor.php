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

use block_elediaai_tutor\local\security;
use block_elediaai_tutor\local\widget;

/**
 * eLeDia.ai Tutor block.
 *
 * Renders a polished, Moodle-native chatbot shell and wires it to the block's
 * authenticated AJAX endpoints. All RAG/Tutor traffic and secret handling happen
 * server-side; the browser only ever talks to Moodle.
 *
 * @package     block_elediaai_tutor
 * @author      Christopher Reimann <christopher.reimann@eledia.de>
 * @copyright   2026 eLeDia GmbH, Berlin
 * @link        https://eledia.de
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_elediaai_tutor extends block_base {
    /**
     * Initialise the block.
     *
     * @return void
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_elediaai_tutor');
    }

    /**
     * This block has a global settings page.
     *
     * @return bool
     */
    public function has_config(): bool {
        return true;
    }

    /**
     * Allow the block on all page types.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return ['all' => true];
    }

    /**
     * Only one instance per page makes sense.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * Hide Moodle's block title chrome.
     *
     * The widget renders its own polished header (avatar, persona, presence and
     * actions), so the surrounding block title bar would only duplicate it.
     *
     * @return bool
     */
    public function hide_header(): bool {
        return true;
    }

    /**
     * Apply the per-instance title from configuration.
     *
     * @return void
     */
    public function specialization(): void {
        if (!empty($this->config->title)) {
            $this->title = format_string($this->config->title);
        } else {
            $this->title = get_string('pluginname', 'block_elediaai_tutor');
        }
    }

    /**
     * Build the block content.
     *
     * @return stdClass|null
     */
    public function get_content(): ?stdClass {
        global $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        $context = $this->context;
        if ($context === null || !isloggedin() || isguestuser()) {
            $this->content->text = '';
            return $this->content;
        }

        // The block contributes its per-instance configuration as a registry-key =>
        // value map (the stored config keys already match the registry keys). It is
        // assembled up front because config_error() is mode-aware and needs the
        // resolved course id and the instance ragmode to decide whether grounded
        // mode (and therefore the MCP connector) is required.
        $courseid = $this->resolve_course_id();
        $instance = (array) ($this->config ?? new stdClass());
        $instance['instanceid'] = (int) $this->instance->id;
        // On the Dashboard (/my/) the widget may render its hero variant; the
        // registry toggle 'dashboardenabled' decides whether it actually does.
        $instance['dashboard'] = $this->page->pagetype === 'my-index';

        // Surface configuration problems to those who can fix them; everyone else
        // gets a friendly unavailable notice instead of a broken widget.
        $canmanage = has_capability('block/elediaai_tutor:manage', $context);
        $configerror = widget::config_error($courseid, $instance);
        if ($configerror !== null) {
            $this->content->text = $OUTPUT->render_from_template('block_elediaai_tutor/unavailable', [
                'isadmin' => $canmanage,
                'message' => $canmanage ? $configerror : get_string('unavailable_user', 'block_elediaai_tutor'),
            ]);
            return $this->content;
        }

        if (!has_capability('block/elediaai_tutor:use', $context)) {
            $this->content->text = '';
            return $this->content;
        }

        // The shared widget builder assembles the shell + AMD init; the widget
        // resolves every setting instance-over-site through the registry. The
        // standalone page (view.php, App embedding) renders the same widget.
        $this->content->text = widget::render($context, $courseid, $instance);

        // Teachers reach the question-analytics report via the course
        // navigation; see block_elediaai_tutor_extend_navigation_course().

        return $this->content;
    }

    /**
     * Persist instance config, saving the per-instance logo/avatar uploads from
     * their draft areas into the block context (mirrors block_html).
     *
     * @param stdClass $data Submitted config.
     * @param bool $nolongerused Unused.
     * @return void
     */
    public function instance_config_save($data, $nolongerused = false): void {
        if ($this->context) {
            foreach (
                [
                'logo' => \block_elediaai_tutor\local\branding::INSTANCE_LOGO_FILEAREA,
                'avatar' => \block_elediaai_tutor\local\branding::INSTANCE_AVATAR_FILEAREA,
                ] as $field => $filearea
            ) {
                if (!empty($data->$field)) {
                    file_save_draft_area_files(
                        (int) $data->$field,
                        $this->context->id,
                        'block_elediaai_tutor',
                        $filearea,
                        0,
                        ['maxfiles' => 1, 'subdirs' => 0]
                    );
                }
                // The draft id is not stored in config (the files live in the area).
                unset($data->$field);
            }
        }
        // The knowledge base comes back from its autocomplete as an array of
        // option keys; stored is the one string the registry and the engine
        // read. Normalised here rather than in the form, so a value that
        // arrives from anywhere else is stored the same way.
        foreach (\block_elediaai_tutor\local\registry::all() as $key => $entry) {
            if ($entry['type'] !== 'coursescope' || !isset($data->$key)) {
                continue;
            }
            $data->$key = is_array($data->$key)
                ? \local_elediaai_chatengine\local\knowledge_scope::from_selection($data->$key)->as_string()
                : (string) $data->$key;
        }

        parent::instance_config_save($data, $nolongerused);
    }

    /**
     * Resolve which course id (if any) to pass to the RAG server.
     *
     * The course this page belongs to, when the instance opts into course
     * context. A page that belongs to no course -- the dashboard -- returns 0
     * and the turn is answered from the person's enrolments.
     *
     * **The site front page counts as its course** (operator decision
     * 20.09.2026). It is one in Moodle, it has a context and can hold
     * activities, and a tutor placed there is placed in it. It used to be
     * excluded here, which made that one block behave like the dashboard --
     * a special case nobody could see in the interface. Note what follows:
     * the site course is never taken into the knowledge sources, so a tutor
     * there has no material of its own and needs a knowledge base to answer
     * from anything.
     *
     * A "fixed course id" setting used to override all of this. It was removed
     * on 20.09.2026, unused on every instance: it named a course **without
     * checking anybody's enrolment**, which is exactly the rule the knowledge
     * base was built to keep.
     *
     * @return int
     */
    private function resolve_course_id(): int {
        $passcontext = (int) $this->get_instance_config('passcoursecontext', 1) === 1;
        if ($passcontext && security::course_chat_enabled() && !empty($this->page->course->id)) {
            return (int) $this->page->course->id;
        }
        return 0;
    }

    /**
     * Read an instance config value with a default.
     *
     * @param string $name Config key.
     * @param mixed $default Default value.
     * @return mixed
     */
    private function get_instance_config(string $name, mixed $default): mixed {
        if (isset($this->config->$name) && $this->config->$name !== '') {
            return $this->config->$name;
        }
        return $default;
    }

    /**
     * Add the block's distinguishing CSS class to the container attributes.
     *
     * @return array
     */
    public function html_attributes(): array {
        $attributes = parent::html_attributes();
        $attributes['class'] .= ' block_elediaai_tutor';
        return $attributes;
    }
}
