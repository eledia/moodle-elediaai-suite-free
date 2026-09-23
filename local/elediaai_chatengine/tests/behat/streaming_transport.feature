@local @local_elediaai_chatengine @javascript
Feature: Which transport a chat surface uses
  In order to get an answer at all
  As a learner
  I need my turn sent to an endpoint that can read it

  Background:
    Given I log in as "admin"

  Scenario: A surface without a streaming endpoint takes the buffered path
    # mod_elli addresses its turns by course module, or by share token for a
    # visitor - not by component and instance, which is all the shared endpoint
    # reads. It says so through an empty endpoint instead of posting to an
    # address that cannot answer it.
    Then the chat panel picks the transport as follows:
      | streaming | streamurl                                | transport |
      | 1         | https://example.org/stream.php           | streamed  |
      | 1         |                                          | buffered  |
      | 0         | https://example.org/stream.php           | buffered  |

  Scenario: The wish for a new conversation is carried until a conversation answers
    # Dropping the panel's own pointer says "carry on where I left off", which
    # is what a reopened panel wants and the opposite of what the tutor's plus
    # asks for. The wish has to travel with the turn, survive a second attempt
    # at the same turn, and end when a conversation comes back.
    Then the chat panel carries the wish for a new conversation

  Scenario: The typing indicator stays below the question it is answering
    # A bubble is rendered through Templates and lands a tick after the
    # indicator, which is appended synchronously - so an unguarded append put
    # the learner's own question underneath the dots.
    Then the chat panel keeps the typing indicator below the messages
