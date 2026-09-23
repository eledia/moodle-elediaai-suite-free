@block @block_elediaai_tutor
Feature: Standalone tutor chat page
  In order to use the tutor from the Moodle App or as a focused full page
  As a learner
  I need the chat available on its own page outside any block

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Sam       | Student  | student1@test.com |
    And the following config values are set as admin:
      | sink        | literag | local_elediaai_sources |
      | llm_api_key | behat   | local_literag          |
      | mcpserviceid | 1 | local_elediaai_chatengine |

  @javascript
  Scenario: Open the standalone chat page for global chat
    Given I log in as "student1"
    When I visit "/blocks/elediaai_tutor/view.php"
    Then "[data-region=consent]" "css_element" should exist
    And I should see "Before you use the tutor for the first time"

  @javascript
  Scenario: Global chat page respects the site toggle
    Given the following config values are set as admin:
      | enableglobalchat | 0 | block_elediaai_tutor |
    And I log in as "student1"
    When I visit "/blocks/elediaai_tutor/view.php"
    Then I should see "Global chat is disabled on this site."

  @javascript
  Scenario: Prompt starters from the site setting appear under the welcome message
    Given the following config values are set as admin:
      | promptstarters | What's due this week? | block_elediaai_tutor |
    And I log in as "student1"
    When I visit "/blocks/elediaai_tutor/view.php"
    Then "What's due this week?" "button" should exist

  @javascript
  Scenario: The AI home initialises tutor interactions
    Given the following config values are set as admin:
      | allowllmonly | 1 | block_elediaai_tutor |
    And I log in as "student1"
    When I visit "/blocks/elediaai_tutor/home.php"
    Then ".elediaai-chat-root[data-initialised=\"1\"]" "css_element" should exist
    And "textarea[data-region=input]" "css_element" should be visible
