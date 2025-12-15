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

  Scenario: Status command requires AI Client
    When I try `wp ai status`
    Then the return code should be 1
    And STDERR should contain:
      """
      WordPress AI Client is not available
      """

  Scenario: Generate command validates model format
    When I try `wp ai generate text "Test prompt" --model=invalidformat`
    Then the return code should be 1

  Scenario: Generate command validates temperature range
    When I try `wp ai generate text "Test prompt" --temperature=3.0`
    Then the return code should be 1
    And STDERR should contain:
      """
      Temperature must be between 0.0 and 2.0
      """

  Scenario: Generate command validates max-tokens
    When I try `wp ai generate text "Test prompt" --max-tokens=-5`
    Then the return code should be 1
    And STDERR should contain:
      """
      Max tokens must be a positive integer
      """

