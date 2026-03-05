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
      [{"name":"OpenAI","description":"Text and image generation with GPT and Dall-E."}]
      """
    And STDOUT should be a JSON array containing:
      """
      [{"name":"Anthropic","description":"Text generation with Claude."}]
      """
    And STDOUT should be a JSON array containing:
      """
      [{"name":"Google","description":"Text and image generation with Gemini and Imagen."}]
      """

  @require-wp-7.0
  Scenario: List connectors shows not-installed status when plugins are absent
    When I run `wp connectors list --format=json`
    Then STDOUT should be a JSON array containing:
      """
      [{"name":"OpenAI","status":"not installed"}]
      """
    And STDOUT should be a JSON array containing:
      """
      [{"name":"Anthropic","status":"not installed"}]
      """
    And STDOUT should be a JSON array containing:
      """
      [{"name":"Google","status":"not installed"}]
      """

  @require-wp-7.0
  Scenario: List connectors in table format shows name, description and status columns
    When I run `wp connectors list`
    Then STDOUT should contain:
      """
      name
      """
    And STDOUT should contain:
      """
      description
      """
    And STDOUT should contain:
      """
      status
      """
    And STDOUT should contain:
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
      [{"name":"OpenAI","description":"Text and image generation with GPT and Dall-E.","status":"not installed","credentials_url":"https://platform.openai.com/api-keys","api_key":""}]
      """

  @require-wp-7.0
  Scenario: Get a connector shows masked API key and connected status when set
    When I run `wp ai credentials set openai --api-key=sk-test123456789`
    Then STDOUT should contain:
      """
      Success: Credentials for provider "openai" have been saved.
      """

    When I run `wp connectors get openai --format=json`
    Then STDOUT should be a JSON array containing:
      """
      [{"name":"OpenAI","status":"connected","api_key":"••••••••••••6789"}]
      """

  @require-wp-7.0
  Scenario: Get connector with fields filter including hidden fields
    When I run `wp connectors get openai --fields=name,auth_method,type,plugin_slug --format=json`
    Then STDOUT should be a JSON array containing:
      """
      [{"name":"OpenAI","auth_method":"api_key","type":"ai_provider","plugin_slug":"ai-provider-for-openai"}]
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
  Scenario: Install OpenAI provider plugin and verify active status
    Given these installed and active plugins:
      """
      ai-provider-for-openai
      """

    When I run `wp connectors list --format=json`
    Then STDOUT should be a JSON array containing:
      """
      [{"name":"OpenAI","status":"active"}]
      """

    When I run `wp connectors get openai --format=json`
    Then STDOUT should be a JSON array containing:
      """
      [{"name":"OpenAI","status":"active"}]
      """

  @require-wp-7.0
  Scenario: Connected status when plugin is active and API key is configured
    Given these installed and active plugins:
      """
      ai-provider-for-openai
      """

    When I run `wp ai credentials set openai --api-key=sk-test123456789`
    And I run `wp connectors list --format=json`
    Then STDOUT should be a JSON array containing:
      """
      [{"name":"OpenAI","status":"connected"}]
      """

  @require-wp-7.0
  Scenario: Community plugin shows up in connectors list
    Given these installed and active plugins:
      """
      ai-provider-for-azure-openai
      """

    When I run `wp connectors list --format=json`
    Then STDOUT should be a JSON array containing:
      """
      [{"status":"active"}]
      """
    And STDOUT should contain:
      """
      Azure
      """
