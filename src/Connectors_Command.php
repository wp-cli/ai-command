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
	 * Default fields for list output.
	 *
	 * @var string[]
	 */
	protected $default_fields = [
		'name',
		'description',
		'status',
	];

	/**
	 * Lists all available AI connectors.
	 *
	 * ## OPTIONS
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
	 *     # List all connectors
	 *     $ wp connectors list
	 *     +-----------+-----------------------------------------------+---------------+
	 *     | name      | description                                   | status        |
	 *     +-----------+-----------------------------------------------+---------------+
	 *     | Anthropic | Text generation with Claude.                  | not installed |
	 *     | Google    | Text and image generation with Gemini...      | not installed |
	 *     | OpenAI    | Text and image generation with GPT and Dall-E | connected     |
	 *     +-----------+-----------------------------------------------+---------------+
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

			$items[] = array(
				'name'            => $connector['name'],
				'description'     => $connector['description'],
				'status'          => $this->get_connector_status( $connector_id, $connector ),
				'type'            => $connector['type'],
				'auth_method'     => $connector['authentication']['method'],
				'credentials_url' => $connector['authentication']['credentials_url'] ?? '',
				'plugin_slug'     => $plugin_slug,
			);
		}

		$format = $assoc_args['format'] ?? 'table';
		$fields = isset( $assoc_args['fields'] )
			? explode( ',', $assoc_args['fields'] )
			: $this->default_fields;

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
	 *     +-----------------+-----------------------------------------------+
	 *     | Field           | Value                                         |
	 *     +-----------------+-----------------------------------------------+
	 *     | name            | OpenAI                                        |
	 *     | description     | Text and image generation with GPT and Dall-E |
	 *     | status          | connected                                     |
	 *     | credentials_url | https://platform.openai.com/api-keys          |
	 *     | api_key         | ••••••••••••6789                              |
	 *     +-----------------+-----------------------------------------------+
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

		$item = array(
			'name'            => $connector['name'],
			'description'     => $connector['description'],
			'status'          => $this->get_connector_status( $connector_id, $connector ),
			'credentials_url' => $auth['credentials_url'] ?? '',
			'api_key'         => $api_key,
			'type'            => $connector['type'],
			'auth_method'     => $auth['method'],
			'plugin_slug'     => $plugin_slug,
		);

		$format = $assoc_args['format'] ?? 'table';
		$fields = isset( $assoc_args['fields'] )
			? explode( ',', $assoc_args['fields'] )
			: array_keys( $item );

		WP_CLI\Utils\format_items( $format, array( $item ), $fields );
	}

	/**
	 * Returns the status of a connector.
	 *
	 * Possible values: 'connected', 'active', 'installed', 'not installed'.
	 *
	 * @param string  $connector_id The connector ID.
	 * @param mixed[] $connector    Connector settings from _wp_connectors_get_connector_settings().
	 * @return string
	 */
	private function get_connector_status( string $connector_id, array $connector ): string {
		$auth        = is_array( $connector['authentication'] ) ? $connector['authentication'] : array();
		$plugin      = isset( $connector['plugin'] ) && is_array( $connector['plugin'] ) ? $connector['plugin'] : array();
		$plugin_slug = isset( $plugin['slug'] ) && is_string( $plugin['slug'] ) ? $plugin['slug'] : '';
		$method      = isset( $auth['method'] ) && is_string( $auth['method'] ) ? $auth['method'] : '';
		$setting     = isset( $auth['setting_name'] ) && is_string( $auth['setting_name'] ) ? $auth['setting_name'] : '';

		if ( 'api_key' === $method && '' !== $setting ) {
			// The option_* filter registered by WP core masks the value automatically.
			$raw = get_option( $setting, '' );
			if ( is_string( $raw ) && '' !== $raw ) {
				return 'connected';
			}
		}

		if ( ! $plugin_slug ) {
			return 'active';
		}

		if ( $this->is_plugin_active( $plugin_slug ) ) {
			return 'active';
		}

		if ( $this->is_plugin_installed( $plugin_slug ) ) {
			return 'installed';
		}

		return 'not installed';
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
