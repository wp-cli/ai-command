Feature: Check if AI features are supported

  Background:
    Given a WP install

  @less-than-wp-7.0
  Scenario: Command not available on WP < 7.0
    When I try `wp ai is-supported`
    Then STDERR should contain:
      """
      Requires WordPress 7.0 or greater.
      """
    And the return code should be 1

  @require-wp-7.0
  Scenario: AI is supported by default
    When I run `wp ai is-supported`
    Then STDOUT should contain:
      """
      AI features are supported.
      """
    And the return code should be 0

  @require-wp-7.0
  Scenario: AI is not supported when disabled via filter
    Given a wp-content/mu-plugins/disable-ai.php file:
      """
      <?php
      add_filter( 'wp_supports_ai', '__return_false' );
      """

    When I try `wp ai is-supported`
    Then STDERR should contain:
      """
      AI features are not supported in this environment.
      """
    And the return code should be 1
