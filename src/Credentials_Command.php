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
	 * The option name where credentials are stored.
	 */
	const OPTION_NAME = 'wp_ai_client_provider_credentials';

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
	 *     | openai   | sk-***** |
	 *     +----------+----------+
	 *
	 * @subcommand list
	 * @when after_wp_load
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 * @return void
	 */
	public function list_( $args, $assoc_args ) {
		$credentials = $this->get_all_credentials();

		if ( empty( $credentials ) ) {
			WP_CLI::log( 'No credentials found.' );
			return;
		}

		$items = array();
		foreach ( $credentials as $provider => $data ) {
			$items[] = array(
				'provider' => $provider,
				'api_key'  => $this->mask_api_key( $data['api_key'] ?? '' ),
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
	 *     {"provider":"openai","api_key":"sk-*****"}
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 * @return void
	 */
	public function get( $args, $assoc_args ) {
		list( $provider ) = $args;

		$credentials = $this->get_all_credentials();

		if ( ! isset( $credentials[ $provider ] ) ) {
			WP_CLI::error( sprintf( 'Credentials for provider "%s" not found.', $provider ) );
		}

		$data = array(
			'provider' => $provider,
			'api_key'  => $this->mask_api_key( $credentials[ $provider ]['api_key'] ?? '' ),
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
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 * @return void
	 */
	public function set( $args, $assoc_args ) {
		list( $provider ) = $args;

		if ( empty( $assoc_args['api-key'] ) ) {
			WP_CLI::error( 'The --api-key parameter is required.' );
		}

		$api_key     = $assoc_args['api-key'];
		$credentials = $this->get_all_credentials();

		$credentials[ $provider ] = array(
			'api_key' => $api_key,
		);

		$this->save_all_credentials( $credentials );

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
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 * @return void
	 */
	public function delete( $args, $assoc_args ) {
		list( $provider ) = $args;

		$credentials = $this->get_all_credentials();

		if ( ! isset( $credentials[ $provider ] ) ) {
			WP_CLI::error( sprintf( 'Credentials for provider "%s" not found.', $provider ) );
		}

		unset( $credentials[ $provider ] );
		$this->save_all_credentials( $credentials );

		WP_CLI::success( sprintf( 'Credentials for provider "%s" have been deleted.', $provider ) );
	}

	/**
	 * Gets all credentials from the database.
	 *
	 * @return array
	 */
	private function get_all_credentials() {
		$credentials = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $credentials ) ) {
			return array();
		}

		return $credentials;
	}

	/**
	 * Saves all credentials to the database.
	 *
	 * @param array $credentials The credentials to save.
	 * @return bool
	 */
	private function save_all_credentials( $credentials ) {
		if ( empty( $credentials ) ) {
			return delete_option( self::OPTION_NAME );
		}

		return update_option( self::OPTION_NAME, $credentials, false );
	}

	/**
	 * Masks an API key for display purposes.
	 *
	 * @param string $api_key The API key to mask.
	 * @return string
	 */
	private function mask_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return '';
		}

		$length = strlen( $api_key );

		if ( $length <= 8 ) {
			return str_repeat( '*', $length );
		}

		// Show first 3 and last 4 characters
		return substr( $api_key, 0, 3 ) . str_repeat( '*', $length - 7 ) . substr( $api_key, -4 );
	}
}
