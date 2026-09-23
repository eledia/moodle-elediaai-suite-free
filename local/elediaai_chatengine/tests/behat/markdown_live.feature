@local @local_elediaai_chatengine @javascript
Feature: Markdown while an answer is still arriving
  In order to read an answer before it is finished
  As a learner
  I need the shape of the text applied while it streams, not only afterwards

  # The expectations were taken from what Moodle's own Markdown produces for the
  # finished answer, so the live version and the version that replaces it agree.
  # Where they deliberately differ - links, half-written markup - the scenario
  # says so.

  Background:
    Given I log in as "admin"

  Scenario: Finished markup is rendered while it streams
    Then the live Markdown renders as follows:
      | markdown                                        | html                                                                          |
      | Text mit **fett**, *kursiv* und `code`.         | <p>Text mit <strong>fett</strong>, <em>kursiv</em> und <code>code</code>.</p>  |
      | # Ueberschrift                                  | <h1>Ueberschrift</h1>                                                         |
      | - eins\n- zwei                                  | <ul><li>eins</li><li>zwei</li></ul>                                           |
      | 1. erstens\n2. zweitens                         | <ol><li>erstens</li><li>zweitens</li></ol>                                    |
      | > Zitat                                         | <blockquote><p>Zitat</p></blockquote>                                         |
      | ```php\necho 1;\n```                            | <pre><code class="php">echo 1;\n</code></pre>                                 |
      | - eins\n  - eingerueckt\n- zwei                 | <ul><li>eins<ul><li>eingerueckt</li></ul></li><li>zwei</li></ul>              |

  Scenario: Half-written markup stays literal until its partner arrives
    # Otherwise a word flickers into bold and back out as the next tokens land.
    Then the live Markdown renders as follows:
      | markdown                | html                              |
      | Ein **halb angekommener | <p>Ein **halb angekommener</p>    |
      | Ein *halb               | <p>Ein *halb</p>                  |
      | ```\necho 1;            | <pre><code>echo 1;\n</code></pre> |

  Scenario: What Moodle's Markdown leaves alone is left alone here too
    # Checked against format_text(FORMAT_MARKDOWN), not assumed. Guessing got
    # the first two of these wrong.
    Then the live Markdown renders as follows:
      | markdown                        | html                                             |
      | snake_case_name und foo_bar_baz | <p>snake_case_name und foo_bar_baz</p>           |
      | a * b * c                       | <p>a * b * c</p>                                 |
      | 1) erste\n2) zweite             | <p>1) erste\n2) zweite</p>                       |

  Scenario: A link keeps its source until the finished answer replaces it
    # An href assembled from half-arrived model output is the one element worth
    # refusing; the final frame delivers the real link a moment later.
    Then the live Markdown renders as follows:
      | markdown                                | html                                            |
      | Siehe [Kapitel 3](https://example.org). | <p>Siehe [Kapitel 3](https://example.org).</p>  |
