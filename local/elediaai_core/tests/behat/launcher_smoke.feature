@local @local_elediaai_core
Feature: AI Suite launcher smoke path
  In order to trust the AI Suite launcher end to end
  As a site administrator
  I need to walk the core launcher flow — open the suite, drill into a
  feature card, open its settings cog and return — and confirm the audit
  tab stays admin-only.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | regular  | Regular   | User     |

  Scenario: Admin walks the launcher happy path and returns to the overview
    Given I log in as "admin"
    # Launcher öffnen.
    When I open the AI Suite
    Then I should see "AI Suite"
    And I should see "Translate"
    # Uebersichtskarte anklicken. Der Link sitzt seit dem Wegfall des
    # Pfeilknopfs auf dem Titel und deckt per ::after die ganze Kachel ab --
    # ein Ziel je Karte statt zweier fuer dasselbe Ziel.
    When I click on "AI Translation" "link" in the "article[data-feature-id=translate]" "css_element"
    Then I should see "Translate"
    # Zurueck zur Uebersicht und von dort ueber das Zahnrad der Kachel in die
    # Einstellungen. Das Zahnrad sass frueher im Kopf der Erklaerseite; seit
    # die weg ist, ist das der Weg, den die Suite tatsaechlich anbietet.
    When I open the AI Suite
    And I click on ".lh-ai-suite-card__settings" "css_element" in the "article[data-feature-id=translate]" "css_element"
    Then I should not see "Page not found"
    When I open the AI Suite
    Then I should see "AI Suite"
    And I should see "Translate"

  Scenario: Audit tab is visible to admins
    Given I log in as "admin"
    When I open the AI Suite
    Then I should see "AI Suite"
    And "Audit" "link" should exist in the ".lh-plugin-section-nav" "css_element"

  Scenario: Audit tab is hidden from regular users
    Given I log in as "regular"
    When I open the AI Suite
    Then I should see "AI Suite"
    And I should see "Overview"
    And "Audit" "link" should not exist in the ".lh-plugin-section-nav" "css_element"
