@block @block_elediaai_tutor
Feature: eLeDia.ai | Tutor plugin shell
  In order to administer the tutor consistently
  As a site administrator
  I need the tutor dashboard, library and preview pages to stay inside the plugin shell

  Background:
    Given the following config values are set as admin:
      | sink        | literag | local_elediaai_sources |
      | llm_api_key | behat   | local_literag          |
      | mcpserviceid | 1 | local_elediaai_chatengine |
    And I log in as "admin"

  # Die Uebersichtsseite dieses Blocks ist weg. Sie fragte vier fremde Plugins
  # nach ihrem Zustand und fuehrte einen Einrichtungsassistenten -- den Zustand
  # melden die Plugins jetzt selbst an den Kern, den Assistenten braucht
  # niemand. Geprueft wird der Weg, der an ihre Stelle getreten ist.
  Scenario: The infrastructure bar leads to the suite health report
    When I visit "/blocks/elediaai_tutor/operator_settings.php"
    Then I should see "eLeDia.ai | Tutor"
    And I should see "LiteRAG" in the ".lh-plugin-section-nav" "css_element"
    And I should see "MCP" in the ".lh-plugin-section-nav" "css_element"
    And "#block-region-side-pre" "css_element" should not exist

  Scenario: The health report says who reported what
    When I visit "/local/elediaai_core/health.php"
    Then I should see "How the suite is doing"
    And I should see "local_elediaai_chatengine"
    And "#theme_boost-drawers-blocks" "css_element" should not exist

  Scenario: Operator settings open inside the plugin shell without Moodle block regions
    When I visit "/blocks/elediaai_tutor/operator_settings.php"
    Then I should see "eLeDia.ai | Tutor"
    And I should see "Settings" in the ".lh-plugin-section-nav" "css_element"
    And I should see "Design"
    And I should see "Conversation & display"
    And I should see "Technical settings"
    And "#block-region-side-pre" "css_element" should not exist
    And "#theme_boost-drawers-blocks" "css_element" should not exist

  Scenario: Help opens from the plugin-owned help page
    When I visit "/blocks/elediaai_tutor/help.php"
    Then I should see "eLeDia.ai | Tutor"
    And I should see "Help"
    And I should see "User and administration documentation"
    And "#block-region-side-pre" "css_element" should not exist
    And "#theme_boost-drawers-blocks" "css_element" should not exist

  Scenario: Tutor library opens in the plugin shell with action icons
    When I visit "/blocks/elediaai_tutor/manage_tutors.php"
    Then I should see "eLeDia.ai | Tutor"
    And I should see "Tutors" in the ".eat-section-title" "css_element"
    And I should see "Tutors" in the ".lh-plugin-section-nav" "css_element"
    And ".eat-section-actions .lh-icon-action[aria-label='Create tutor']" "css_element" should exist
    And ".eat-section-actions .lh-icon-action[aria-label='Import']" "css_element" should exist
    And "#block-region-side-pre" "css_element" should not exist
    And "#theme_boost-drawers-blocks" "css_element" should not exist

  @javascript
  Scenario: Preview opens in the plugin shell without Moodle block regions
    When I visit "/blocks/elediaai_tutor/view.php"
    Then I should see "eLeDia.ai | Tutor"
    And I should see "Preview" in the ".lh-plugin-section-nav" "css_element"
    And "#block-region-side-pre" "css_element" should not exist
    And "#theme_boost-drawers-blocks" "css_element" should not exist
