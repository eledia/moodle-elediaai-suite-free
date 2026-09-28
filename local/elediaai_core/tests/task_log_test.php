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

/**
 * What the scheduled tasks write into the cron log.
 *
 * @package    local_elediaai_core
 * @copyright  2026 eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_elediaai_core\task\anonymise_actions
 * @covers     \local_elediaai_core\task\prune_turns
 * @covers     \local_elediaai_core\task\prune_usage
 */
final class task_log_test extends \advanced_testcase {
    /**
     * Die Aufgaben und die Einstellung, die ihre Aufbewahrung steuert.
     *
     * @return array
     */
    public static function task_provider(): array {
        return [
            'anonymise_actions' => [task\anonymise_actions::class, 'action_retentiondays', 'task_anonymise_actions'],
            'prune_turns' => [task\prune_turns::class, 'turn_retentiondays', 'task_prune_turns'],
            'prune_usage' => [task\prune_usage::class, 'usage_retentiondays', 'task_prune_usage'],
        ];
    }

    /**
     * Die Logzeile kommt aus der Sprachdatei, nicht aus dem Quelltext.
     *
     * Vorher stand dort fest verdrahtet Deutsch -- „Personenbezug bei 0
     * Handlung(en) ..." -- auch in englischen Installationen. Geprueft wird
     * gegen den Sprachstring, damit der Test nicht an einer Formulierung
     * haengt, sondern an der Herkunft.
     *
     * @dataProvider task_provider
     * @param string $classname Task class.
     * @param string $setting Retention setting of the task.
     * @param string $stringprefix Language string prefix of the task.
     */
    public function test_log_lines_come_from_language_strings(
        string $classname,
        string $setting,
        string $stringprefix
    ): void {
        $this->resetAfterTest();

        set_config($setting, 0, 'local_elediaai_core');
        $unbegrenzt = 'local_elediaai_core: ' . get_string($stringprefix . '_unlimited', 'local_elediaai_core') . "\n";
        $this->assertSame($unbegrenzt, $this->run_task($classname));

        set_config($setting, 30, 'local_elediaai_core');
        $erledigt = 'local_elediaai_core: ' . get_string(
            $stringprefix . '_done',
            'local_elediaai_core',
            (object) ['count' => 0, 'days' => 30]
        ) . "\n";
        $this->assertSame($erledigt, $this->run_task($classname));
    }

    /**
     * Run a task and return what it printed.
     *
     * @param string $classname Task class.
     * @return string
     */
    private function run_task(string $classname): string {
        ob_start();
        (new $classname())->execute();
        return (string) ob_get_clean();
    }
}
