Feature: Manage AI provider credentials

  Background:
    Given a WP install

  Scenario: List credentials when none exist
    When I run `wp ai credentials list`
    Then STDOUT should contain:
      """
      No credentials found.
      """

  Scenario: Set and list credentials
    When I run `wp ai credentials set openai --api-key=sk-test123456789`
    Then STDOUT should contain:
      """
      Success: Credentials for provider "openai" have been saved.
      """

    When I run `wp ai credentials list --format=json`
    Then STDOUT should be JSON containing:
      """
      [{"provider":"openai","api_key":"sk-*********6789"}]
      """

  Scenario: Get specific provider credentials
    When I run `wp ai credentials set anthropic --api-key=sk-ant-api-key-123`
    Then STDOUT should contain:
      """
      Success: Credentials for provider "anthropic" have been saved.
      """

    When I run `wp ai credentials get anthropic --format=json`
    Then STDOUT should contain:
      """
      "provider":"anthropic"
      """
    And STDOUT should contain:
      """
      "api_key":"sk-**********-123"
      """

  Scenario: Delete provider credentials
    When I run `wp ai credentials set google --api-key=test-google-key`
    Then STDOUT should contain:
      """
      Success: Credentials for provider "google" have been saved.
      """

    When I run `wp ai credentials delete google`
    Then STDOUT should contain:
      """
      Success: Credentials for provider "google" have been deleted.
      """

    When I try `wp ai credentials get google`
    Then STDERR should contain:
      """
      Error: Credentials for provider "google" not found.
      """
    And the return code should be 1

  Scenario: Error when getting non-existent credentials
    When I try `wp ai credentials get nonexistent`
    Then STDERR should contain:
      """
      Error: Credentials for provider "nonexistent" not found.
      """
    And the return code should be 1

  Scenario: Error when setting credentials without api-key
    When I try `wp ai credentials set openai`
    Then STDERR should contain:
      """
      missing --api-key parameter
      """
    And the return code should be 1

  Scenario: List multiple credentials in table format
    When I run `wp ai credentials set openai --api-key=sk-openai123`
    And I run `wp ai credentials set anthropic --api-key=sk-ant-api-456`
    And I run `wp ai credentials list`
    Then STDOUT should be a table containing rows:
      | provider  | api_key        |
      | openai    | sk-*****i123   |
      | anthropic | sk-*******-456 |

  Scenario: Update existing credentials
    When I run `wp ai credentials set openai --api-key=old-key-123`
    Then STDOUT should contain:
      """
      Success: Credentials for provider "openai" have been saved.
      """

    When I run `wp ai credentials set openai --api-key=new-key-456`
    Then STDOUT should contain:
      """
      Success: Credentials for provider "openai" have been saved.
      """

    When I run `wp ai credentials get openai --format=json`
    Then STDOUT should contain:
      """
      "api_key":"new****-456"
      """
