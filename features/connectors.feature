Feature: List and get AI connectors

  Background:
    Given a WP install

  @less-than-wp-7.0
  Scenario: Command not available on WP < 7.0
    When I try `wp connectors list`
    Then STDERR should contain:
      """
      Requires WordPress 7.0 or greater.
      """
    And the return code should be 1

  @require-wp-7.0
  Scenario: List connectors returns built-in providers
    When I run `wp connectors list --format=json`
    Then STDOUT should be a JSON array containing:
      """
      [{"name":"OpenAI","type":"ai_provider","auth_method":"api_key","plugin_slug":"ai-provider-for-openai"}]
      """
    And STDOUT should be a JSON array containing:
      """
      [{"name":"Anthropic","type":"ai_provider","auth_method":"api_key","plugin_slug":"ai-provider-for-anthropic"}]
      """
    And STDOUT should be a JSON array containing:
      """
      [{"name":"Google","type":"ai_provider","auth_method":"api_key","plugin_slug":"ai-provider-for-google"}]
      """

  @require-wp-7.0
  Scenario: List connectors shows plugin install status as No when plugins are absent
    When I run `wp connectors list --format=json`
    Then STDOUT should be a JSON array containing:
      """
      [{"name":"OpenAI","installed":"No","active":"No"}]
      """

  @require-wp-7.0
  Scenario: List connectors in table format
    When I run `wp connectors list`
    Then STDOUT should contain:
      """
      OpenAI
      """
    And STDOUT should contain:
      """
      Anthropic
      """
    And STDOUT should contain:
      """
      Google
      """

  @require-wp-7.0
  Scenario: Get a specific connector
    When I run `wp connectors get openai --format=json`
    Then STDOUT should be a JSON array containing:
      """
      [{"name":"OpenAI","type":"ai_provider","auth_method":"api_key","plugin_slug":"ai-provider-for-openai","installed":"No","active":"No","api_key":""}]
      """

  @require-wp-7.0
  Scenario: Get a connector shows masked API key when set
    When I run `wp ai credentials set openai --api-key=sk-test123456789`
    Then STDOUT should contain:
      """
      Success: Credentials for provider "openai" have been saved.
      """

    When I run `wp connectors get openai --format=json`
    Then STDOUT should be a JSON array containing:
      """
      [{"name":"OpenAI","api_key":"••••••••••••6789"}]
      """

  @require-wp-7.0
  Scenario: Get connector with fields filter
    When I run `wp connectors get openai --fields=name,auth_method --format=json`
    Then STDOUT should be a JSON array containing:
      """
      [{"name":"OpenAI","auth_method":"api_key"}]
      """

  @require-wp-7.0
  Scenario: Error on non-existent connector
    When I try `wp connectors get nonexistent`
    Then STDERR should contain:
      """
      Error: Connector "nonexistent" not found.
      """
    And the return code should be 1

  @require-wp-7.0
  Scenario: Install OpenAI provider plugin and verify installed status
    Given these installed and active plugins:
      | plugin                 |
      | ai-provider-for-openai |

    When I run `wp connectors list --format=json`
    Then STDOUT should be a JSON array containing:
      """
      [{"name":"OpenAI","installed":"Yes","active":"Yes"}]
      """

    When I run `wp connectors get openai --format=json`
    Then STDOUT should be a JSON array containing:
      """
      [{"name":"OpenAI","installed":"Yes","active":"Yes"}]
      """

  @require-wp-7.0
  Scenario: Community plugin shows up in connectors list
    Given these installed and active plugins:
      | plugin                          |
      | ai-provider-for-openrouter      |

    When I run `wp connectors list --format=json`
    Then STDOUT should be a JSON array containing:
      """
      [{"type":"ai_provider"}]
      """
    And STDOUT should contain:
      """
      ai-provider-for-openrouter
      """
