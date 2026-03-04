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
	 * The option name prefix where credentials are stored.
	 */
	const OPTION_PREFIX = 'connectors_ai_';

	/**
	 * The option name suffix where credentials are stored.
	 */
	const OPTION_SUFFIX = '_api_key';

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
				'api_key'  => $this->mask_api_key( $api_key ?? '' ),
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
	 * @param array{0: string}      $args       Positional arguments.
	 * @param array{format: string} $assoc_args Associative arguments.
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
			'api_key'  => $this->mask_api_key( $credentials[ $provider ] ?? '' ),
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
		$option_name = self::OPTION_PREFIX . $provider . self::OPTION_SUFFIX;

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

		$credentials = $this->get_all_credentials();

		if ( ! isset( $credentials[ $provider ] ) ) {
			WP_CLI::error( sprintf( 'Credentials for provider "%s" not found.', $provider ) );
		}

		delete_option( self::OPTION_PREFIX . $provider . self::OPTION_SUFFIX );

		WP_CLI::success( sprintf( 'Credentials for provider "%s" have been deleted.', $provider ) );
	}

	/**
	 * Gets all credentials from the database.
	 *
	 * @return array<string, string>
	 */
	private function get_all_credentials() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::OPTION_PREFIX ) . '%' . $wpdb->esc_like( self::OPTION_SUFFIX )
			),
			ARRAY_A
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		$credentials   = array();
		$prefix_length = strlen( self::OPTION_PREFIX );
		$suffix_length = strlen( self::OPTION_SUFFIX );

		foreach ( $results as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			/** @var array<string, string> $row */
			$option_name = $row['option_name'];
			if ( strlen( $option_name ) <= $prefix_length + $suffix_length ) {
				continue;
			}
			$provider                 = substr( $option_name, $prefix_length, -$suffix_length );
			$credentials[ $provider ] = $row['option_value'];
		}

		ksort( $credentials );

		return $credentials;
	}

	/**
	 * Masks an API key for display purposes.
	 *
	 * Uses the same logic as WordPress core's `_wp_connectors_mask_api_key()`.
	 *
	 * @param string $api_key The API key to mask.
	 * @return string
	 */
	private function mask_api_key( $api_key ) {
		if ( function_exists( '_wp_connectors_mask_api_key' ) ) {
			return _wp_connectors_mask_api_key( $api_key );
		}

		if ( strlen( $api_key ) <= 4 ) {
			return $api_key;
		}

		return str_repeat( "\u{2022}", min( strlen( $api_key ) - 4, 16 ) ) . substr( $api_key, -4 );
	}
}
