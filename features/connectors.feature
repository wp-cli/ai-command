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
  Scenario: List connectors returns built-in providers with descriptions
    When I run `wp connectors list --format=json`
    Then STDOUT should contain:
      """
      "name":"OpenAI"
      """
    And STDOUT should contain:
      """
      "description":"Text and image generation with GPT and Dall-E."
      """
    And STDOUT should contain:
      """
      "name":"Anthropic"
      """
    And STDOUT should contain:
      """
      "description":"Text generation with Claude."
      """
    And STDOUT should contain:
      """
      "name":"Google"
      """

  @require-wp-7.0
  Scenario: List connectors shows not-installed status when plugins are absent
    When I run `wp connectors list --format=json`
    Then STDOUT should contain:
      """
      "name":"OpenAI"
      """
    And STDOUT should contain:
      """
      "status":"not installed"
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
  Scenario: List connectors supports --status filter
    When I run `wp connectors list --status="not installed" --format=json`
    Then STDOUT should contain:
      """
      "name":"OpenAI"
      """
    And STDOUT should contain:
      """
      "name":"Anthropic"
      """

  @require-wp-7.0
  Scenario: Get a specific connector shows key-value layout
    When I run `wp connectors get openai`
    Then STDOUT should contain:
      """
      name
      """
    And STDOUT should contain:
      """
      OpenAI
      """
    And STDOUT should contain:
      """
      status
      """

  @require-wp-7.0
  Scenario: Get a specific connector in JSON format
    When I run `wp connectors get openai --format=json`
    Then STDOUT should be JSON containing:
      """
      {"name":"OpenAI","description":"Text and image generation with GPT and Dall-E.","status":"not installed","credentials_url":"https://platform.openai.com/api-keys","api_key":""}
      """

  # TODO: Depends on https://core.trac.wordpress.org/ticket/64819.
  @require-wp-7.0 @broken
  Scenario: Get a connector shows masked API key and connected status when set
    Given these installed and active plugins:
      """
      ai-provider-for-openai
      """
    When I run `wp option update connectors_ai_openai_api_key sk-test123456789`
    Then STDOUT should contain:
      """
      Success
      """

    When I run `wp connectors get openai --format=json`
    Then STDOUT should contain:
      """
      "status": "connected"
      """
    And STDOUT should contain:
      """
      "api_key": "\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u20226789"
      """

  @require-wp-7.0
  Scenario: Get connector with fields filter including hidden fields
    When I run `wp connectors get openai --fields=name,auth_method,type,plugin_slug --format=json`
    Then STDOUT should be JSON containing:
      """
      {"name":"OpenAI","auth_method":"api_key","type":"ai_provider","plugin_slug":"ai-provider-for-openai"}
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
    Then STDOUT should contain:
      """
      "name":"OpenAI"
      """
    And STDOUT should contain:
      """
      "status":"active"
      """

    When I run `wp connectors get openai --format=json`
    Then STDOUT should be JSON containing:
      """
      {"name":"OpenAI","status":"active"}
      """

  @require-wp-7.0
  Scenario: Filter by active status returns only active connectors
    Given these installed and active plugins:
      """
      ai-provider-for-openai
      """

    When I run `wp connectors list --status=active --format=json`
    Then STDOUT should contain:
      """
      "name":"OpenAI"
      """
    And STDOUT should not contain:
      """
      "name":"Anthropic"
      """

  # Plugin requires PHP 7.4.
  @require-wp-7.0 @require-php-7.4
  Scenario: Community plugin shows up in connectors list
    When I run `wp plugin install https://github.com/soderlind/ai-provider-for-azure-openai/archive/refs/heads/main.zip --activate`
    And I run `wp connectors list --format=json`
    Then STDOUT should contain:
      """
      "status":"active"
      """
    And STDOUT should contain:
      """
      Azure
      """

