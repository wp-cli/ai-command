<?php

namespace WP_CLI\AI;

use WP_CLI;
use WP_CLI_Command;

/**
 * Lists and retrieves information about AI connectors.
 *
 * ## EXAMPLES
 *
 *     # List all available connectors
 *     $ wp connectors list
 *
 *     # Get details about a specific connector
 *     $ wp connectors get openai
 */
class Connectors_Command extends WP_CLI_Command {

	/**
	 * Lists all available AI connectors.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Comma-separated list of fields to include in the output.
	 * ---
	 * default: name,type,auth_method,plugin_slug,installed,active
	 * ---
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
	 *     # List all connectors
	 *     $ wp connectors list
	 *     +----------+-------------+-------------+------------------------+-----------+--------+
	 *     | name     | type        | auth_method | plugin_slug            | installed | active |
	 *     +----------+-------------+-------------+------------------------+-----------+--------+
	 *     | Anthropic | ai_provider | api_key    | ai-provider-for-anthropic | No     | No     |
	 *     +----------+-------------+-------------+------------------------+-----------+--------+
	 *
	 * @subcommand list
	 * @when after_wp_load
	 *
	 * @param string[]                              $args       Positional arguments. Unused.
	 * @param array{fields: string, format: string} $assoc_args Associative arguments.
	 * @return void
	 */
	public function list_( $args, $assoc_args ) {
		if ( ! function_exists( '_wp_connectors_get_connector_settings' ) ) {
			WP_CLI::error( 'Requires WordPress 7.0 or greater.' );
		}

		$connectors = _wp_connectors_get_connector_settings();

		$items = array();
		foreach ( $connectors as $connector_id => $connector ) {
			$plugin_slug = isset( $connector['plugin']['slug'] ) ? (string) $connector['plugin']['slug'] : '';
			$installed   = '';
			$active      = '';

			if ( $plugin_slug ) {
				$installed = $this->is_plugin_installed( $plugin_slug ) ? 'Yes' : 'No';
				$active    = $this->is_plugin_active( $plugin_slug ) ? 'Yes' : 'No';
			}

			$items[] = array(
				'name'            => $connector['name'],
				'description'     => $connector['description'],
				'type'            => $connector['type'],
				'auth_method'     => $connector['authentication']['method'],
				'credentials_url' => $connector['authentication']['credentials_url'] ?? '',
				'plugin_slug'     => $plugin_slug,
				'installed'       => $installed,
				'active'          => $active,
			);
		}

		$format = $assoc_args['format'] ?? 'table';
		$fields = isset( $assoc_args['fields'] )
			? explode( ',', $assoc_args['fields'] )
			: array( 'name', 'type', 'auth_method', 'plugin_slug', 'installed', 'active' );

		WP_CLI\Utils\format_items( $format, $items, $fields );
	}

	/**
	 * Gets details about a specific AI connector.
	 *
	 * ## OPTIONS
	 *
	 * <connector>
	 * : The connector ID (e.g., openai, anthropic, google).
	 *
	 * [--fields=<fields>]
	 * : Comma-separated list of fields to include in the output.
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
	 *     # Get details for the OpenAI connector
	 *     $ wp connectors get openai
	 *     +------------------+-------------------------------+
	 *     | Field            | Value                         |
	 *     +------------------+-------------------------------+
	 *     | name             | OpenAI                        |
	 *     | description      | Text and image generation ... |
	 *     +------------------+-------------------------------+
	 *
	 * @when after_wp_load
	 *
	 * @param array{0: string}                      $args       Positional arguments.
	 * @param array{fields: string, format: string} $assoc_args Associative arguments.
	 * @return void
	 */
	public function get( $args, $assoc_args ) {
		if ( ! function_exists( '_wp_connectors_get_connector_settings' ) ) {
			WP_CLI::error( 'Requires WordPress 7.0 or greater.' );
		}

		list( $connector_id ) = $args;

		$connectors = _wp_connectors_get_connector_settings();

		if ( ! isset( $connectors[ $connector_id ] ) ) {
			WP_CLI::error( sprintf( 'Connector "%s" not found.', $connector_id ) );
		}

		$connector   = $connectors[ $connector_id ];
		$auth        = $connector['authentication'];
		$plugin_slug = isset( $connector['plugin']['slug'] ) ? (string) $connector['plugin']['slug'] : '';

		$api_key = '';
		if ( 'api_key' === $auth['method'] && ! empty( $auth['setting_name'] ) ) {
			// The option_* filter registered by WP core masks the value automatically.
			$raw     = get_option( $auth['setting_name'], '' );
			$api_key = is_string( $raw ) ? $raw : '';
		}

		$installed = '';
		$active    = '';
		if ( $plugin_slug ) {
			$installed = $this->is_plugin_installed( $plugin_slug ) ? 'Yes' : 'No';
			$active    = $this->is_plugin_active( $plugin_slug ) ? 'Yes' : 'No';
		}

		$item = array(
			'name'            => $connector['name'],
			'description'     => $connector['description'],
			'type'            => $connector['type'],
			'auth_method'     => $auth['method'],
			'credentials_url' => $auth['credentials_url'] ?? '',
			'plugin_slug'     => $plugin_slug,
			'installed'       => $installed,
			'active'          => $active,
			'api_key'         => $api_key,
		);

		$format = $assoc_args['format'] ?? 'table';
		$fields = isset( $assoc_args['fields'] )
			? explode( ',', $assoc_args['fields'] )
			: array_keys( $item );

		WP_CLI\Utils\format_items( $format, array( $item ), $fields );
	}

	/**
	 * Checks whether a plugin is installed, given its slug.
	 *
	 * @param string $slug The WordPress.org plugin slug.
	 * @return bool
	 */
	private function is_plugin_installed( string $slug ): bool {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( array_keys( get_plugins() ) as $plugin_file ) {
			if ( strpos( $plugin_file, $slug . '/' ) === 0 || $plugin_file === $slug . '.php' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether a plugin is active, given its slug.
	 *
	 * @param string $slug The WordPress.org plugin slug.
	 * @return bool
	 */
	private function is_plugin_active( string $slug ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );
		foreach ( $active_plugins as $plugin_file ) {
			if ( ! is_string( $plugin_file ) ) {
				continue;
			}
			if ( strpos( $plugin_file, $slug . '/' ) === 0 || $plugin_file === $slug . '.php' ) {
				return true;
			}
		}

		return false;
	}
}
