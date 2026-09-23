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
 * Cache definitions for the AI chat engine.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // The plain user-scoped callback token between requests. The secret never
    // reaches the database: it lives only here, keyed per user and service, for
    // at most the configured token lifetime. Configure as Redis/Memcached in
    // production so a cluster shares one token per user.
    'usertoken' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 3600,
    ],
    // Per-user sliding-window counters for outgoing chat requests.
    'ratelimit' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 3700,
    ],
    // A configured knowledge base, resolved to plain course ids: the category
    // subtrees expanded and the single courses folded in. Person-independent
    // on purpose -- the intersection with the enrolments happens after this
    // and is never cached, because it differs per learner and changes the
    // moment somebody is enrolled. Keyed by a hash of the stored selection,
    // so two surfaces with the same knowledge base share one entry.
    'coursescope' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 900,
    ],
    // What a backend last answered when asked whether it works. Short-lived:
    // an operator dashboard that refreshes must not turn into load on a
    // language model, but a corrected setting has to show up quickly.
    'backendhealth' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 60,
    ],
];
