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

namespace local_elediaai_core;

use core\output\notification;
use local_elediaai_core\health\check;
use local_elediaai_core\health\registry;
use moodle_url;

/**
 * Summary and links of the health page.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_core\health\registry
 */
final class health_page_test extends \advanced_testcase {
    /**
     * Build a check with the given status.
     *
     * @param string $id Check id.
     * @param string $status One of the check::STATUS_* constants.
     * @return check
     */
    private function check(string $id, string $status): check {
        return new check(id: $id, component: 'local_test', label: $id, status: $status);
    }

    /**
     * Der Befund aus dem Vortest: eine Warnung, drei „nicht eingerichtet".
     *
     * Die Zusammenfassung sagte „1 thing(s) need attention", waehrend darunter
     * drei Zeilen „NOT SET UP" standen. Beide Zahlen muessen genannt werden,
     * jede fuer das, was sie zaehlt.
     */
    public function test_summary_names_faults_and_unconfigured_separately(): void {
        $checks = [
            $this->check('a', check::STATUS_WARNING),
            $this->check('b', check::STATUS_UNCONFIGURED),
            $this->check('c', check::STATUS_UNCONFIGURED),
            $this->check('d', check::STATUS_UNCONFIGURED),
            $this->check('e', check::STATUS_OK),
        ];

        [$message, $type] = registry::summary($checks);

        $this->assertSame(
            get_string('health_summary_attention_unconfigured', 'local_elediaai_core', (object) [
                'attention' => 1,
                'unconfigured' => 3,
            ]),
            $message
        );
        $this->assertSame(notification::NOTIFY_WARNING, $type);
    }

    /**
     * Nur „nicht eingerichtet" ist eine Aufgabe, kein Alarm -- aber es wird gesagt.
     */
    public function test_summary_for_unconfigured_only_is_informational(): void {
        [$message, $type] = registry::summary([
            $this->check('a', check::STATUS_UNCONFIGURED),
            $this->check('b', check::STATUS_OK),
        ]);

        $this->assertSame(get_string('health_summary_unconfigured', 'local_elediaai_core', 1), $message);
        $this->assertSame(notification::NOTIFY_INFO, $type);
    }

    /**
     * Fehler ohne Unerledigtes, und der ruhige Fall.
     */
    public function test_summary_for_faults_only_and_for_all_quiet(): void {
        [$message, $type] = registry::summary([
            $this->check('a', check::STATUS_ERROR),
            $this->check('b', check::STATUS_DISABLED),
        ]);
        $this->assertSame(get_string('health_summary_attention', 'local_elediaai_core', 1), $message);
        $this->assertSame(notification::NOTIFY_WARNING, $type);

        [$message, $type] = registry::summary([
            $this->check('a', check::STATUS_OK),
            $this->check('b', check::STATUS_DISABLED),
        ]);
        $this->assertSame(get_string('health_summary_quiet', 'local_elediaai_core', 2), $message);
        $this->assertSame(notification::NOTIFY_SUCCESS, $type);
    }

    /**
     * Eine Zeile, die ihr eigenes Ziel nennt, behaelt es.
     */
    public function test_a_check_keeps_its_own_action_url(): void {
        $own = new moodle_url('/local/elediaai_core/index.php');
        $check = new check(
            id: 'x',
            component: 'local_elediaai_core',
            label: 'X',
            status: check::STATUS_UNCONFIGURED,
            actionurl: $own,
        );

        $this->assertSame($own, registry::action_url($check));
    }

    /**
     * „Chat backend" hatte keinen Einstellungslink; jetzt fuehrt die Zeile
     * auf die Einstellungsseite ihres Plugins.
     */
    public function test_a_check_without_url_links_to_its_component_settings(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        if (!\core_component::get_component_directory('local_elediaai_chatengine')) {
            $this->markTestSkipped('local_elediaai_chatengine is not installed.');
        }

        $check = new check(
            id: 'backend',
            component: 'local_elediaai_chatengine',
            label: 'Chat backend',
            status: check::STATUS_UNCONFIGURED,
        );

        $url = registry::action_url($check);
        $this->assertNotNull($url);
        $this->assertStringEndsWith('/admin/settings.php', $url->get_path(false));
        $this->assertSame('local_elediaai_chatengine_settings', $url->get_param('section'));
    }

    /**
     * Ohne Einstellungsseite kein Link -- lieber keiner als einer ins Leere.
     */
    public function test_no_settings_page_means_no_link(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertNull(registry::settings_url('local_doesnotexist'));
    }
}
