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
 * A colour-picker form element for the eLeDia.ai Tutor edit forms.
 *
 * Renders the same markup as the admin colour-picker setting (a text field —
 * type or paste a hex — beside an `.admin_colourpicker` swatch) and initialises
 * it with Moodle's core `M.util.init_colour_picker`. The init is queued from
 * toHtml(), i.e. during the form's render(), so it is captured by
 * core_form_dynamic_form and runs when the block config opens in a modal — as
 * well as on a full page.
 *
 * Deliberately NOT templatable, so the renderer uses toHtml() verbatim.
 *
 * @package     block_elediaai_tutor
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once('HTML/QuickForm/text.php');

/**
 * Colour-picker element (text input + Moodle's native colour picker).
 */
class MoodleQuickForm_eatcolour extends HTML_QuickForm_text {
    /** @var string Help button HTML, if any. */
    public $_helpbutton = ''; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

    /**
     * Constructor.
     *
     * @param string|null $elementname Element name.
     * @param string|null $elementlabel Element label.
     * @param mixed $attributes Attributes (string or array).
     */
    public function __construct($elementname = null, $elementlabel = null, $attributes = null) {
        parent::__construct($elementname, $elementlabel, $attributes);
        $this->_type = 'eatcolour';
    }

    /**
     * Render the field markup and queue the colour picker.
     *
     * @return string
     */
    public function toHtml() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        global $PAGE, $OUTPUT;

        $id = $this->getAttribute('id');
        if (empty($id)) {
            $id = 'id_' . $this->getName();
            $this->updateAttributes(['id' => $id]);
        }
        $class = trim((string) ($this->getAttribute('class') ?? '') . ' form-control text-ltr');
        $this->updateAttributes(['size' => 12, 'class' => $class]);

        // The exact picker the admin colour setting uses (core YUI util).
        $PAGE->requires->js_init_call('M.util.init_colour_picker', [$id, null]);

        $loading = $OUTPUT->pix_icon('i/loading', '', 'moodle', ['class' => 'loadingicon']);
        return html_writer::div(
            html_writer::div($loading, 'admin_colourpicker clearfix') . parent::toHtml(),
            'form-colourpicker'
        );
    }

    /**
     * Use the standard element row template.
     *
     * @return string
     */
    public function getElementTemplateType() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        return $this->_flagFrozen ? 'static' : 'default';
    }

    /**
     * Help-button HTML for the renderer.
     *
     * @return string
     */
    public function getHelpButton() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        return $this->_helpbutton;
    }

    /**
     * Accept (and ignore) the legacy help-button setter for compatibility.
     *
     * @param mixed $args Ignored.
     * @param string $function Ignored.
     * @return void
     */
    public function setHelpButton($args, $function = 'helpbutton') { // phpcs:ignore moodle.NamingConventions.ValidFunctionName
    }
}
