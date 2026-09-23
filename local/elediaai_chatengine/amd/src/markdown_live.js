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
 * Markdown for an answer that is still arriving.
 *
 * The finished answer is rendered and sanitised on the server and replaces
 * whatever was shown while it streamed. This module covers only the time in
 * between — where waiting for the last token means reading raw `**` and `-`
 * for the length of a paragraph.
 *
 * **It never turns the model's text into HTML.** Element names come from this
 * file and nowhere else; every part of the answer enters the page as a text
 * node. That is not a matter of taste. Assistant output is untrusted, and the
 * reason the server renders it through HTMLPurifier is precisely that it must
 * never reach `innerHTML`. A partial renderer taking the shortcut here would
 * open the hole the rest of the pipeline exists to close.
 *
 * Deliberately a subset, and deliberately the same subset Moodle's own
 * Markdown produces for the final answer, so the swap at the end is invisible:
 * paragraphs, headings, lists (nested included), block quotes, fenced and
 * inline code, bold and italic. Checked against `format_text(FORMAT_MARKDOWN)`
 * rather than assumed — `1)` is not a list there and `snake_case_name` is not
 * emphasis, and this file follows both.
 *
 * Links stay as their literal source. An `href` assembled from half-arrived
 * model output is the one element worth refusing, and the final frame delivers
 * them properly a moment later.
 *
 * Half-written markup stays literal: a `**` whose partner has not arrived is
 * text, not an unterminated element, so a word does not flicker into bold and
 * back out as the next tokens land.
 *
 * @module     local_elediaai_chatengine/markdown_live
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var {number} How deep emphasis may nest before the rest is left as text. */
const MAX_DEPTH = 4;

/** @var {RegExp} A fence line, with its optional language. */
const FENCE = /^\s{0,3}```(\S*)\s*$/;

/** @var {RegExp} A heading line. */
const HEADING = /^(#{1,6})\s+(.*)$/;

/** @var {RegExp} An unordered list item, with its indent. */
const BULLET = /^(\s*)[-*+]\s+(.*)$/;

/**
 * An ordered list item, with its indent.
 *
 * Only `1.`, not `1)`: Moodle's Markdown leaves the bracket form a plain
 * paragraph, and a list that appears while streaming and vanishes at the end
 * is worse than no list at all.
 *
 * @var {RegExp}
 */
const NUMBER = /^(\s*)\d{1,9}\.\s+(.*)$/;

/** @var {RegExp} A block quote line. */
const QUOTE = /^\s{0,3}>\s?(.*)$/;

/**
 * The inline spans, in the order they are tried.
 *
 * Code first: whatever sits between backticks is text, however it looks, so it
 * has to be claimed before emphasis can see it.
 *
 * `wordstart` marks the rules that may not begin inside a word. Underscores are
 * the reason: `snake_case_name` is an identifier, not emphasis, and Moodle's
 * Markdown agrees. Asterisks carry no such restriction, there as here.
 *
 * @var {Array<{re: RegExp, tag: string, raw: boolean, wordstart: boolean}>}
 */
const INLINE = [
    {re: /`([^`\n]+)`/y, tag: 'code', raw: true, wordstart: false},
    {re: /\*\*(\S[\s\S]*?)\*\*/y, tag: 'strong', raw: false, wordstart: false},
    {re: /__(\S[\s\S]*?)__/y, tag: 'strong', raw: false, wordstart: true},
    {re: /\*(\S[\s\S]*?)\*/y, tag: 'em', raw: false, wordstart: false},
    {re: /_(\S[\s\S]*?)_/y, tag: 'em', raw: false, wordstart: true},
];

/**
 * Turn a run of text into inline nodes.
 *
 * @param {string} text The text, without any block marker.
 * @param {number} depth Current nesting depth.
 * @return {Node[]} Text nodes and inline elements, in order.
 */
const inline = (text, depth = 0) => {
    const out = [];
    let plain = '';
    let index = 0;

    const flush = () => {
        if (plain !== '') {
            out.push(document.createTextNode(plain));
            plain = '';
        }
    };

    while (index < text.length) {
        let hit = null;
        if (depth < MAX_DEPTH) {
            const previous = index === 0 ? '' : text.charAt(index - 1);
            const insideword = /\w/.test(previous);
            for (let i = 0; i < INLINE.length; i++) {
                const rule = INLINE[i];
                if (rule.wordstart && insideword) {
                    continue;
                }
                // Sticky, so the scan stays linear: the pattern is anchored at
                // the cursor instead of the string being sliced each step.
                rule.re.lastIndex = index;
                const match = rule.re.exec(text);
                if (match) {
                    hit = {match: match, rule: rule};
                    break;
                }
            }
        }

        if (hit === null) {
            plain += text.charAt(index);
            index += 1;
            continue;
        }

        flush();
        const element = document.createElement(hit.rule.tag);
        if (hit.rule.raw) {
            element.textContent = hit.match[1];
        } else {
            inline(hit.match[1], depth + 1).forEach((child) => element.appendChild(child));
        }
        out.push(element);
        index += hit.match[0].length;
    }

    flush();
    return out;
};

/**
 * Append a paragraph built from the collected lines, if there are any.
 *
 * @param {Node} parent Where the paragraph goes.
 * @param {string[]} lines The lines collected so far; emptied.
 * @return {void}
 */
const flushParagraph = (parent, lines) => {
    if (!lines.length) {
        return;
    }
    const paragraph = document.createElement('p');
    inline(lines.join('\n')).forEach((node) => paragraph.appendChild(node));
    parent.appendChild(paragraph);
    lines.length = 0;
};

/**
 * The list an item at this indent belongs in, opening one where needed.
 *
 * A deeper indent nests inside the item above it, the way Moodle's Markdown
 * nests it; a shallower one closes back out. Same indent but a different kind
 * of marker starts a new list beside the old one.
 *
 * @param {string} tag 'UL' or 'OL'.
 * @param {number} indent The item's indent in characters.
 * @param {Node} root Where a top-level list goes.
 * @param {Array<{indent: number, el: Element}>} stack The open lists, outermost first.
 * @return {Element} The list to append the item to.
 */
const listFor = (tag, indent, root, stack) => {
    while (stack.length && indent < stack[stack.length - 1].indent) {
        stack.pop();
    }

    const top = stack.length ? stack[stack.length - 1] : null;
    if (top && indent === top.indent) {
        if (top.el.tagName === tag) {
            return top.el;
        }
        // Same level, other marker: a sibling list, not a nested one.
        stack.pop();
    }

    const outer = stack.length ? stack[stack.length - 1] : null;
    const parent = outer ? (outer.el.lastElementChild || outer.el) : root;
    const list = document.createElement(tag.toLowerCase());
    parent.appendChild(list);
    stack.push({indent: indent, el: list});

    return list;
};

/**
 * Render Markdown that may still be incomplete.
 *
 * @param {string} text The answer as far as it has arrived.
 * @return {DocumentFragment} The rendered nodes.
 */
export const render = (text) => {
    const fragment = document.createDocumentFragment();
    const source = (text === null || text === undefined) ? '' : String(text);
    const paragraph = [];
    const lists = [];
    let quote = null;
    let code = null;

    source.split('\n').forEach((line) => {
        // Inside a fence everything is code until the closing fence, including
        // lines that would otherwise read as headings or list items. An
        // unclosed fence keeps collecting: the answer is still arriving, and
        // showing the code as code is what it will end up being.
        if (code !== null) {
            if (FENCE.test(line)) {
                code = null;
                return;
            }
            code.textContent += line + '\n';
            return;
        }

        const fence = FENCE.exec(line);
        if (fence) {
            flushParagraph(fragment, paragraph);
            lists.length = 0;
            quote = null;
            const pre = document.createElement('pre');
            code = document.createElement('code');
            if (fence[1]) {
                // The language travels as a class, the way the server's
                // renderer emits it, so the same rules style it.
                code.className = fence[1].replace(/[^\w-]/g, '');
            }
            pre.appendChild(code);
            fragment.appendChild(pre);
            return;
        }

        if (line.trim() === '') {
            flushParagraph(fragment, paragraph);
            lists.length = 0;
            quote = null;
            return;
        }

        const heading = HEADING.exec(line);
        if (heading) {
            flushParagraph(fragment, paragraph);
            lists.length = 0;
            quote = null;
            const element = document.createElement('h' + heading[1].length);
            inline(heading[2]).forEach((node) => element.appendChild(node));
            fragment.appendChild(element);
            return;
        }

        const bullet = BULLET.exec(line);
        const numbered = bullet ? null : NUMBER.exec(line);
        if (bullet || numbered) {
            const item = bullet || numbered;
            flushParagraph(fragment, paragraph);
            quote = null;
            const list = listFor(bullet ? 'UL' : 'OL', item[1].length, fragment, lists);
            const element = document.createElement('li');
            inline(item[2]).forEach((node) => element.appendChild(node));
            list.appendChild(element);
            return;
        }

        const quoted = QUOTE.exec(line);
        if (quoted) {
            flushParagraph(fragment, paragraph);
            lists.length = 0;
            if (quote === null) {
                quote = document.createElement('blockquote');
                fragment.appendChild(quote);
            }
            const element = document.createElement('p');
            inline(quoted[1]).forEach((node) => element.appendChild(node));
            quote.appendChild(element);
            return;
        }

        lists.length = 0;
        quote = null;
        paragraph.push(line);
    });

    flushParagraph(fragment, paragraph);
    return fragment;
};

/**
 * Replace a node's content with the answer as far as it has arrived.
 *
 * @param {Element} node Where the answer is shown.
 * @param {string} text The answer so far.
 * @return {void}
 */
export const renderInto = (node, text) => {
    if (!node) {
        return;
    }
    node.textContent = '';
    node.appendChild(render(text));
};
