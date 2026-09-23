@block @block_elediaai_tutor
Feature: eLeDia.ai | Tutor course context gate
  In order to avoid exposing course context on pages where the tutor is not enabled
  As a site administrator
  I need the course-scoped standalone page to require an active tutor block in the course

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
      | mcpserviceid | 1 | local_elediaai_chatengine |
      | enablecoursechat  | 1                           | block_elediaai_tutor |
      | enableglobalchat  | 1                           | block_elediaai_tutor |

  @javascript
  Scenario: Course-scoped standalone chat is blocked until the tutor block is present
    Given I log in as "student1"
    When I am on the "C1" "block_elediaai_tutor > coursechat" page
    Then I should see "The tutor is not enabled in this course."
    And ".elediaai-chat-page" "css_element" should not exist

  @javascript
  Scenario: Course-scoped standalone chat opens when the tutor block is present
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "eLeDia.ai | Tutor" block
    And I log out
    And I log in as "student1"
    When I am on the "C1" "block_elediaai_tutor > coursechat" page
    Then I should see "Before you use the tutor for the first time"
    And ".elediaai-chat-page" "css_element" should exist

  @javascript
  Scenario: Settings of a tutor block inside an activity open without a coding error
    Given the following "activities" exist:
      | activity | name        | course | idnumber |
      | page     | Test page   | C1     | page1    |
    And I log in as "teacher1"
    And I am on the "Test page" "page activity" page
    And I switch editing mode on
    And I add the "eLeDia.ai | Tutor" block
    # Regression guard: a block inside an activity used to abort here, because the
    # page carried no course while the settings navigation set the activity
    # ("The course you passed to $PAGE->set_cm does not correspond to the $cm").
    When I configure the "eLeDia.ai | Tutor" block
    Then I should not see "Error in code detected"
    And ".lh-plugin-header" "css_element" should exist
