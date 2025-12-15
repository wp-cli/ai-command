Feature: Generate AI content

  Background:
    Given a WP install

  Scenario: Check for WordPress AI Client availability
    When I try `wp ai check "Test prompt"`
    Then the return code should be 1
    And STDERR should contain:
      """
      WordPress AI Client is not available
      """

  Scenario: Generate command requires AI Client
    When I try `wp ai generate text "Test prompt"`
    Then the return code should be 1
    And STDERR should contain:
      """
      WordPress AI Client is not available
      """
