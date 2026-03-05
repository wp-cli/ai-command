<?php

namespace WP_CLI\AI;

use WP_CLI;
use WP_CLI_Command;

/**
 * Manages AI provider credentials.
 *
 * ## EXAMPLES
 *
 *     # List all stored AI provider credentials
 *     $ wp ai credentials list
 *
 *     # Get credentials for a specific provider
 *     $ wp ai credentials get openai
 *
 *     # Set credentials for a provider
 *     $ wp ai credentials set openai --api-key=sk-...
 *
 *     # Delete credentials for a provider
 *     $ wp ai credentials delete openai
 */
class Credentials_Command extends WP_CLI_Command {

	/**
	 * Lists all stored AI provider credentials.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List all credentials
	 *     $ wp ai credentials list
	 *     +----------+----------+
	 *     | provider | api_key  |
	 *     +----------+----------+
	 *     | openai   | ••••••• |
	 *     +----------+----------+
	 *
	 * @subcommand list
	 * @when after_wp_load
	 *
	 * @param string[]              $args       Positional arguments. Unused.
	 * @param array{format: string} $assoc_args Associative arguments.
	 * @return void
	 */
	public function list_( $args, $assoc_args ) {
		$credentials = $this->get_all_credentials();

		if ( empty( $credentials ) ) {
			WP_CLI::log( 'No credentials found.' );
			return;
		}

		$items = array();
		foreach ( $credentials as $provider => $api_key ) {
			$items[] = array(
				'provider' => $provider,
				'api_key'  => $api_key,
			);
		}

		$format = $assoc_args['format'] ?? 'table';
		WP_CLI\Utils\format_items( $format, $items, array( 'provider', 'api_key' ) );
	}

	/**
	 * Gets credentials for a specific AI provider.
	 *
	 * ## OPTIONS
	 *
	 * <provider>
	 * : The AI provider name (e.g., openai, anthropic, google).
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: json
	 * options:
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Get OpenAI credentials
	 *     $ wp ai credentials get openai
	 *     {"provider":"openai","api_key":"••••••••••••6789"}
	 *
	 * @when after_wp_load
	 *
	 * @param array{0: string}      $args       Positional arguments.
	 * @param array{format: string} $assoc_args Associative arguments.
	 * @return void
	 */
	public function get( $args, $assoc_args ) {
		list( $provider ) = $args;

		$option_name = $this->get_connector_setting_name( $provider );
		$raw_key     = get_option( $option_name, '' );
		$api_key     = is_string( $raw_key ) ? $raw_key : '';

		if ( '' === $api_key ) {
			WP_CLI::error( sprintf( 'Credentials for provider "%s" not found.', $provider ) );
		}

		$data = array(
			'provider' => $provider,
			'api_key'  => $api_key,
		);

		$format = $assoc_args['format'] ?? 'json';

		if ( 'json' === $format ) {
			WP_CLI::line( (string) json_encode( $data ) );
		} else {
			// For yaml and other formats
			foreach ( $data as $key => $value ) {
				WP_CLI::line( "$key: $value" );
			}
		}
	}

	/**
	 * Sets or updates credentials for an AI provider.
	 *
	 * ## OPTIONS
	 *
	 * <provider>
	 * : The AI provider name (e.g., openai, anthropic, google).
	 *
	 * --api-key=<api-key>
	 * : The API key for the provider.
	 *
	 * ## EXAMPLES
	 *
	 *     # Set OpenAI credentials
	 *     $ wp ai credentials set openai --api-key=sk-...
	 *     Success: Credentials for provider "openai" have been saved.
	 *
	 * @when after_wp_load
	 *
	 * @param array{0: string}         $args       Positional arguments.
	 * @param array{'api-key': string} $assoc_args Associative array of associative arguments.
	 * @return void
	 */
	public function set( $args, $assoc_args ) {
		list( $provider ) = $args;

		$api_key     = $assoc_args['api-key'];
		$option_name = $this->get_connector_setting_name( $provider );

		// Remove any sanitize callback to bypass provider-side validation (e.g., live API checks).
		remove_all_filters( "sanitize_option_{$option_name}" );
		update_option( $option_name, $api_key, false );

		WP_CLI::success( sprintf( 'Credentials for provider "%s" have been saved.', $provider ) );
	}

	/**
	 * Deletes credentials for an AI provider.
	 *
	 * ## OPTIONS
	 *
	 * <provider>
	 * : The AI provider name (e.g., openai, anthropic, google).
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete OpenAI credentials
	 *     $ wp ai credentials delete openai
	 *     Success: Credentials for provider "openai" have been deleted.
	 *
	 * @when after_wp_load
	 *
	 * @param array{0: string} $args       Positional arguments.
	 * @param array<mixed>     $assoc_args Associative arguments. Unused.
	 * @return void
	 */
	public function delete( $args, $assoc_args ) {
		list( $provider ) = $args;

		$option_name = $this->get_connector_setting_name( $provider );
		$raw_key     = get_option( $option_name, '' );
		$api_key     = is_string( $raw_key ) ? $raw_key : '';

		if ( '' === $api_key ) {
			WP_CLI::error( sprintf( 'Credentials for provider "%s" not found.', $provider ) );
		}

		delete_option( $option_name );

		WP_CLI::success( sprintf( 'Credentials for provider "%s" have been deleted.', $provider ) );
	}

	/**
	 * Gets the option name for a provider's API key from the connector registry.
	 *
	 * @param string $provider The connector/provider ID.
	 * @return string The option name.
	 */
	private function get_connector_setting_name( string $provider ): string {
		if ( ! function_exists( '_wp_connectors_get_connector_settings' ) ) {
			WP_CLI::error( 'Requires WordPress 7.0 or greater.' );
		}

		$settings = _wp_connectors_get_connector_settings();

		if ( ! isset( $settings[ $provider ] ) ) {
			WP_CLI::error( sprintf( 'Provider "%s" is not a supported AI connector.', $provider ) );
		}

		$setting_name = $this->get_api_key_setting_name( $settings[ $provider ]['authentication'] ?? [] );

		if ( null === $setting_name ) {
			WP_CLI::error( sprintf( 'Provider "%s" does not support API key authentication.', $provider ) );
		}

		return $setting_name;
	}

	/**
	 * Returns the option/setting name if the given authentication config is an API key type, or null otherwise.
	 *
	 * @param mixed $auth Authentication config from the connector registry.
	 * @return string|null
	 */
	private function get_api_key_setting_name( $auth ): ?string {
		if ( ! is_array( $auth ) || ! isset( $auth['method'] ) || 'api_key' !== $auth['method'] || empty( $auth['setting_name'] ) ) {
			return null;
		}

		return (string) $auth['setting_name'];
	}

	/**
	 * Gets all credentials from the database.
	 *
	 * @return array<string, string>
	 */
	private function get_all_credentials() {
		if ( ! function_exists( '_wp_connectors_get_connector_settings' ) ) {
			return array();
		}

		$credentials = array();

		foreach ( _wp_connectors_get_connector_settings() as $connector_id => $connector_data ) {
			$setting_name = $this->get_api_key_setting_name( $connector_data['authentication'] ?? [] );

			if ( null === $setting_name ) {
				continue;
			}

			$raw   = get_option( $setting_name, '' );
			$value = is_string( $raw ) ? $raw : '';
			if ( '' !== $value ) {
				$credentials[ $connector_id ] = $value;
			}
		}

		ksort( $credentials );

		return $credentials;
	}
}
