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

declare(strict_types=1);

namespace local_elediaai_chatengine\placement;

/**
 * Opt-in: the person's message is framed as their request.
 *
 * A placement that implements this marker gets
 * {@see \local_elediaai_chatengine\local\prompt_safety::frame_user_request()}
 * instead of the default untrusted frame. The message then counts as the
 * person's request, which a tutor with tools has to act on, and still cannot
 * change the assistant's instructions, role or policies.
 *
 * It is a separate marker rather than a method on {@see placement} so that a
 * placement which says nothing keeps the default frame word for word. Chat
 * activities run their own system prompts on the agent side and must not
 * change behaviour because another surface changed its framing.
 *
 * @package    local_elediaai_chatengine
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface user_request_framing {
}
