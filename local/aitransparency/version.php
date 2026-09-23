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
 * Plugin version metadata for local_aitransparency.
 *
 * Lean, product-logic-free plugin that records provenance for AI-generated
 * output and exposes the marking primitives the AI suite plugins use to
 * satisfy Art. 50 EU AI Act (transparency of synthetic content). See
 * docs/scope-matrix.md and docs/00-master.md for the work-package plan.
 *
 * @package    local_aitransparency
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_aitransparency';
$plugin->version   = 2026092300;
$plugin->release   = '1.0.0';
$plugin->requires  = 2024100700; // Moodle 4.5+.
$plugin->maturity  = MATURITY_STABLE;
// Die Design-Token der Suite (--eai-*, --font-size-*) stehen in
// local_elediaai_core; dieses Plugin liest sie ohne Rueckfallwert. Ohne Core
// gaebe es keine Skala, also ist die Abhaengigkeit echt und nicht kosmetisch
// (adr05, task22).
$plugin->dependencies = [
    'local_elediaai_core' => 2026090806,
];
