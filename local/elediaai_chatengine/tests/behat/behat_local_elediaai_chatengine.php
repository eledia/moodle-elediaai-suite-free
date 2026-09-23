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
 * Steps for the AI chat engine.
 *
 * @package    local_elediaai_chatengine
 * @category   test
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Steps for the AI chat engine.
 *
 * @package    local_elediaai_chatengine
 * @category   test
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_elediaai_chatengine extends behat_base {
    /**
     * Render Markdown through the live renderer and compare each result.
     *
     * The module is driven directly rather than through a real streamed answer:
     * what is under test is the renderer, and a backend that produces a
     * particular half-finished token at a particular moment is not something a
     * test should try to arrange.
     *
     * The cases arrive as a table because the expected HTML carries attribute
     * quotes, which a quoted step argument cannot. `\n` stands for a line break
     * on both sides, since a table cell cannot carry one.
     *
     * @Then the live Markdown renders as follows:
     * @param TableNode $cases Rows of markdown and the expected html.
     * @return void
     * @throws ExpectationException When a rendered result differs.
     */
    public function the_live_markdown_renders_as_follows(TableNode $cases): void {
        foreach ($cases->getHash() as $case) {
            $source = str_replace('\\n', "\n", $case['markdown']);
            $expected = str_replace('\\n', "\n", $case['html']);
            $rendered = $this->render_live_markdown($source);

            if (trim($rendered) !== trim($expected)) {
                throw new ExpectationException(
                    sprintf("For: %s\nRendered: %s\nExpected: %s", $case['markdown'], $rendered, $expected),
                    $this->getSession()
                );
            }
        }
    }

    /**
     * Follow the wish for a new conversation across a panel's turns.
     *
     * The wish is the client half of the fix: a panel that only drops its own
     * pointer is asking the engine to carry on, which is the opposite of what
     * a "new conversation" control means. Two things about it are easy to get
     * wrong and invisible from PHP, so they are pinned here.
     *
     * The wish must survive an attempt. One turn can reach the server twice --
     * a retry, or the streamed attempt falling back to the buffered call -- so
     * a wish consumed when the arguments are assembled would let the second
     * attempt land in the old conversation after all. It is therefore dropped
     * only when a conversation comes back.
     *
     * @Then the chat panel carries the wish for a new conversation
     * @return void
     * @throws ExpectationException When the wish is not carried, or outstays its turn.
     */
    public function the_chat_panel_carries_the_wish_for_a_new_conversation(): void {
        $this->execute_script(
            'window.elediaaiWish = null;' .
            'require(["local_elediaai_chatengine/chat_panel"], function(module) {' .
            '    var panel = Object.create(module.ChatPanel.prototype);' .
            '    panel.component = "local_elediaai_chatengine";' .
            '    panel.instanceid = 0;' .
            '    panel.threadId = 7;' .
            '    panel.pendingNewThread = false;' .
            '    var seen = [];' .
            '    seen.push(panel.turnArgs("Frage").newthread);' .
            '    panel.startNewThread();' .
            '    seen.push(panel.turnArgs("Frage").newthread);' .
            // The same turn assembled twice: a fallback or a retry.
            '    seen.push(panel.turnArgs("Frage").newthread);' .
            '    panel.rememberThread(42);' .
            '    seen.push(panel.turnArgs("Frage").newthread);' .
            '    seen.push(panel.threadId);' .
            '    window.elediaaiWish = seen.join(",");' .
            '});'
        );

        $seen = $this->spin(
            function ($context) {
                $result = $context->evaluate_script('return window.elediaaiWish;');
                if ($result === null) {
                    throw new ExpectationException('The panel has not answered yet.', $context->getSession());
                }
                return $result;
            },
            false,
            10
        );

        // Before the button: carry on. After it: a new conversation, twice over.
        // Once one came back: carry on in it.
        if ((string) $seen !== '0,1,1,0,42') {
            throw new ExpectationException(
                sprintf('The panel answered %s, expected 0,1,1,0,42.', $seen),
                $this->getSession()
            );
        }
    }

    /**
     * Follow where a bubble lands while the typing indicator is showing.
     *
     * `send()` appends the learner's bubble and shows the dots in the same
     * breath, but a bubble goes through Templates and therefore arrives a tick
     * later than the indicator, which is appended synchronously. Appended
     * blindly the question landed *below* the dots that are supposed to answer
     * it - visible on every single turn, and invisible to PHP.
     *
     * The panel is built from its prototype with a real log element, so the
     * ordering is observed on its own rather than through a whole turn.
     *
     * @Then the chat panel keeps the typing indicator below the messages
     * @return void
     * @throws ExpectationException When a bubble lands behind the indicator.
     */
    public function the_chat_panel_keeps_the_typing_indicator_below_the_messages(): void {
        $this->execute_script(
            'window.elediaaiOrder = null;' .
            'require(["local_elediaai_chatengine/chat_panel"], function(module) {' .
            '    var log = document.createElement("div");' .
            '    document.body.appendChild(log);' .
            '    var panel = Object.create(module.ChatPanel.prototype);' .
            '    panel.prefix = "elediaai-chat";' .
            '    panel.config = {};' .
            '    panel.log = log;' .
            '    panel.typingEl = null;' .
            '    panel.scrollToBottom = function() {};' .
            '    panel.prepareAssistantLinks = function() {};' .
            // Exactly the order send() uses: append the bubble, then show the dots.
            '    var appended = panel.appendMessage({isuser: true, text: "Frage", sendername: "Ich"});' .
            '    panel.showTyping();' .
            '    appended.then(function() {' .
            '        window.elediaaiOrder = Array.prototype.map.call(log.children, function(child) {' .
            '            return child.className.indexOf("elediaai-chat-typing") !== -1 ? "typing" : "message";' .
            '        }).join(",");' .
            '        return null;' .
            '    });' .
            '});'
        );

        $order = $this->spin(
            function ($context) {
                $result = $context->evaluate_script('return window.elediaaiOrder;');
                if ($result === null) {
                    throw new ExpectationException('The panel has not rendered yet.', $context->getSession());
                }
                return $result;
            },
            false,
            10
        );

        if ((string) $order !== 'message,typing') {
            throw new ExpectationException(
                sprintf('The log is ordered %s, expected message,typing.', $order),
                $this->getSession()
            );
        }
    }

    /**
     * Check which transport the panel picks for a given endpoint.
     *
     * The panel is built from its prototype and given only what `dispatch()`
     * touches, so the choice is observed on its own rather than through a real
     * turn. It is worth observing: a placement that sends its own arguments and
     * leaves the streaming endpoint at the shared one posts to an address that
     * cannot read them, and that fails only where the backend reports
     * streaming support - invisible everywhere else.
     *
     * @Then the chat panel picks the transport as follows:
     * @param TableNode $cases Rows of streaming, streamurl and the expected transport.
     * @return void
     * @throws ExpectationException When another transport is picked.
     */
    public function the_chat_panel_picks_the_transport_as_follows(TableNode $cases): void {
        foreach ($cases->getHash() as $case) {
            $this->execute_script(
                'window.elediaaiTransport = null;' .
                'require(["local_elediaai_chatengine/chat_panel"], function(module) {' .
                '    var panel = Object.create(module.ChatPanel.prototype);' .
                '    panel.config = {streaming: ' . ((int) $case['streaming'] ? 'true' : 'false') . '};' .
                '    panel.streamUrl = function() { return ' . json_encode($case['streamurl']) . '; };' .
                '    panel.setBusy = function() {};' .
                '    panel.showTyping = function() {};' .
                '    panel.setStatus = function() {};' .
                '    panel.dispatchStreaming = function() { window.elediaaiTransport = "streamed"; };' .
                '    panel.dispatchBuffered = function() { window.elediaaiTransport = "buffered"; };' .
                '    panel.dispatch("Frage");' .
                '});'
            );

            $picked = $this->spin(
                function ($context) {
                    $result = $context->evaluate_script('return window.elediaaiTransport;');
                    if ($result === null) {
                        throw new ExpectationException('The panel has not chosen yet.', $context->getSession());
                    }
                    return $result;
                },
                false,
                10
            );

            if ((string) $picked !== $case['transport']) {
                throw new ExpectationException(
                    sprintf(
                        'With streaming=%s and endpoint "%s" the panel picked %s, not %s.',
                        $case['streaming'],
                        $case['streamurl'],
                        $picked,
                        $case['transport']
                    ),
                    $this->getSession()
                );
            }
        }
    }

    /**
     * Run one string through the live renderer in the browser.
     *
     * @param string $source The Markdown to render.
     * @return string The HTML the renderer built.
     */
    private function render_live_markdown(string $source): string {
        $this->execute_script(
            'window.elediaaiMarkdownLive = null;' .
            'require(["local_elediaai_chatengine/markdown_live"], function(renderer) {' .
            '    var target = document.createElement("div");' .
            '    renderer.renderInto(target, ' . json_encode($source) . ');' .
            '    window.elediaaiMarkdownLive = target.innerHTML;' .
            '});'
        );

        return (string) $this->spin(
            function ($context) {
                $result = $context->evaluate_script('return window.elediaaiMarkdownLive;');
                if ($result === null) {
                    throw new ExpectationException('The renderer has not answered yet.', $context->getSession());
                }
                return $result;
            },
            false,
            10
        );
    }
}
