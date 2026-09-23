@block @block_elediaai_tutor
Feature: eLeDia.ai | Tutor chat block UI
  In order to get help while learning
  As a student
  I need a polished, accessible tutor chat embedded in Moodle

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | student1 | Sam       | Student  | student1@test.com |
      | teacher1 | Tess      | Teacher  | teacher1@test.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following config values are set as admin:
      | sink        | literag | local_elediaai_sources |
      | llm_api_key | behat   | local_literag          |
    And the following config values are set as admin:
      | mcpserviceid | 1 | local_elediaai_chatengine |
      | defaultdisplaymode | embedded                    | block_elediaai_tutor |

  Scenario: The embedded chat shell renders with its composer and welcome message
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I add the "eLeDia.ai | Tutor" block
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    # Der Block rendert bewusst ohne sichtbaren Header; der Name traegt die
    # Region als aria-label (WCAG 1.3.1). Darum hier auf die Region pruefen.
    Then "section[aria-label='eLeDia.ai | Tutor']" "css_element" should exist
    And "form[data-region=composer]" "css_element" should exist
    And "textarea[data-region=input]" "css_element" should exist
    And ".elediaai-chat-log[role=log]" "css_element" should exist

  Scenario: Learners are offered the pedagogical answer styles
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "eLeDia.ai | Tutor" block
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "Explain" in the ".elediaai-chat-styles" "css_element"
    And I should see "Hints only" in the ".elediaai-chat-styles" "css_element"
    And I should see "Quiz me" in the ".elediaai-chat-styles" "css_element"

  Scenario: A missing RAG server URL shows an admin-facing error to managers
    Given the following config values are set as admin:
      | llm_api_key |  | local_literag |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I add the "eLeDia.ai | Tutor" block
    Then I should see "configuration problem" in the ".elediaai-chat-unavailable" "css_element"

  Scenario: LLM-only chat renders the shell when there is no knowledge base
    Given the following config values are set as admin:
      | allowllmonly | 1 | block_elediaai_tutor |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I add the "eLeDia.ai | Tutor" block
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then "form[data-region=composer]" "css_element" should exist
    And ".elediaai-chat-unavailable" "css_element" should not exist

  Scenario: With LLM-only disallowed and no knowledge base the tutor is unavailable
    Given the following config values are set as admin:
      | allowllmonly | 0 | block_elediaai_tutor |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    When I add the "eLeDia.ai | Tutor" block
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "no knowledge base" in the ".elediaai-chat-unavailable" "css_element"

  @javascript
  Scenario: The chat can be opened as a modal and closed again
    Given the following config values are set as admin:
      | defaultdisplaymode | modal | block_elediaai_tutor |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "eLeDia.ai | Tutor" block
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I click on "[data-action=launch]" "css_element"
    Then ".elediaai-chat-modal" "css_element" should be visible
    And "textarea[data-region=input]" "css_element" should be visible
    # Use the header close button: the backdrop (also data-action=close) is overlapped
    # by the first-use consent panel and is not reliably clickable.
    When I click on "button[data-action=close]" "css_element"
    Then ".elediaai-chat-modal" "css_element" should not be visible

  @javascript
  Scenario: The chat can be opened in full-screen mode
    Given the following config values are set as admin:
      | defaultdisplaymode | fullscreen | block_elediaai_tutor |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "eLeDia.ai | Tutor" block
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I click on "[data-action=launch]" "css_element"
    Then ".elediaai-chat-fullscreen" "css_element" should be visible
