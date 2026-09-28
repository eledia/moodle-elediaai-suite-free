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

namespace webservice_elediamcp;

use advanced_testcase;
use ReflectionClass;
use webservice_elediamcp\form\create_token_form;

/**
 * Token issuing: expiry on by default, risks declared (M-21).
 *
 * @package     webservice_elediamcp
 * @copyright   2026 eLeDia GmbH, Berlin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \webservice_elediamcp\form\create_token_form
 */
final class create_token_form_test extends advanced_testcase {
    /**
     * A new token form proposes an expiry 90 days ahead, switched on.
     */
    public function test_expiry_defaults_to_ninety_days(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new create_token_form(null, ['services' => [1 => 'MCP']]);
        $mform = (new ReflectionClass($form))->getProperty('_form')->getValue($form);
        // Selects report their value as a one-element array.
        $value = array_map(
            static fn($part) => is_array($part) ? reset($part) : $part,
            $mform->getElement('validuntil')->getValue()
        );

        $this->assertNotEmpty($value['enabled'], 'Expiry must be switched on by default.');
        $expected = usergetdate(time() + create_token_form::DEFAULT_VALIDITY_DAYS * DAYSECS);
        $this->assertEquals($expected['mday'], $value['day']);
        $this->assertEquals($expected['mon'], $value['month']);
        $this->assertEquals($expected['year'], $value['year']);
        $this->assertSame(90, create_token_form::DEFAULT_VALIDITY_DAYS);
    }

    /**
     * Issuing and using tokens carry the risks of what a token can do.
     */
    public function test_token_capabilities_declare_risks(): void {
        global $CFG;
        $capabilities = [];
        require($CFG->dirroot . '/webservice/elediamcp/db/access.php');

        foreach (['webservice/elediamcp:use', 'webservice/elediamcp:managetokens'] as $name) {
            $mask = $capabilities[$name]['riskbitmask'] ?? 0;
            $this->assertSame(RISK_PERSONAL, $mask & RISK_PERSONAL, $name);
            $this->assertSame(RISK_SPAM, $mask & RISK_SPAM, $name);
            $this->assertSame(RISK_DATALOSS, $mask & RISK_DATALOSS, $name);
            // The operator decided that everyone may issue tokens.
            $this->assertSame(CAP_ALLOW, $capabilities[$name]['archetypes']['user'] ?? null, $name);
        }
    }
}
