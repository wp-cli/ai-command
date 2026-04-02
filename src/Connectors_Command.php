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
	 * [--status=<status>]
	 * : Filter connectors by status.
	 * ---
	 * options:
	 *   - connected
	 *   - active
	 *   - installed
	 *   - not installed
	 * ---
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
	 *     # List only connected connectors
	 *     $ wp connectors list --status=connected
	 *
	 * @subcommand list
	 * @when after_wp_load
	 *
	 * @param string[]                                             $args       Positional arguments. Unused.
	 * @param array{status: string, fields: string, format: string} $assoc_args Associative arguments.
	 * @return void
	 */
	public function list_( $args, $assoc_args ) {
		// @phpstan-ignore function.notFound
		$connectors = wp_get_connectors();

		$items = array();
		foreach ( $connectors as $connector_id => $connector ) {
			if ( ! is_array( $connector ) ) {
				continue;
			}

			$items[] = $this->build_connector_item( $connector_id, $connector );
		}

		if ( isset( $assoc_args['status'] ) ) {
			$status_filter = $assoc_args['status'];
			$items         = array_values(
				array_filter(
					$items,
					static function ( array $item ) use ( $status_filter ) {
						return $item['status'] === $status_filter;
					}
				)
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
		list( $connector_id ) = $args;

		// @phpstan-ignore function.notFound
		$connector = wp_get_connector( $connector_id );

		if ( ! $connector ) {
			WP_CLI::error( sprintf( 'Connector "%s" not found.', $connector_id ) );
		}

		$item = $this->build_connector_item( $connector_id, $connector );

		// Retrieve and append the (possibly masked) API key.
		$auth    = is_array( $connector['authentication'] ) ? $connector['authentication'] : array();
		$method  = isset( $auth['method'] ) && is_string( $auth['method'] ) ? $auth['method'] : '';
		$setting = isset( $auth['setting_name'] ) && is_string( $auth['setting_name'] ) ? $auth['setting_name'] : '';

		$api_key = '';
		if ( 'api_key' === $method && '' !== $setting ) {
			// The option_* filter registered by WP core masks the value automatically.
			$raw     = get_option( $setting, '' );
			$api_key = is_string( $raw ) ? $raw : '';
		}

		$item['api_key'] = $api_key;

		$default_fields = array( 'name', 'description', 'status', 'credentials_url', 'api_key' );
		$formatter      = new \WP_CLI\Formatter( $assoc_args, $default_fields );
		$formatter->display_item( $item );
	}

	/**
	 * Builds a flat item array from a connector settings array.
	 *
	 * @param string  $connector_id The connector ID.
	 * @param mixed[] $connector    Connector settings from wp_get_connectors().
	 * @return array{name: string, description: string, status: string, type: string, auth_method: string, credentials_url: string, plugin_slug: string}
	 */
	private function build_connector_item( string $connector_id, array $connector ): array {
		$auth        = is_array( $connector['authentication'] ) ? $connector['authentication'] : array();
		$plugin      = isset( $connector['plugin'] ) && is_array( $connector['plugin'] ) ? $connector['plugin'] : array();
		$plugin_slug = isset( $plugin['slug'] ) && is_string( $plugin['slug'] ) ? $plugin['slug'] : '';

		return array(
			'name'            => $this->scalar_to_string( $connector['name'] ?? '' ),
			'description'     => $this->scalar_to_string( $connector['description'] ?? '' ),
			'status'          => $this->get_connector_status( $connector_id, $connector ),
			'type'            => $this->scalar_to_string( $connector['type'] ?? '' ),
			'auth_method'     => isset( $auth['method'] ) && is_string( $auth['method'] ) ? $auth['method'] : '',
			'credentials_url' => isset( $auth['credentials_url'] ) && is_string( $auth['credentials_url'] ) ? $auth['credentials_url'] : '',
			'plugin_slug'     => $plugin_slug,
		);
	}

	/**
	 * Returns the status of a connector.
	 *
	 * Possible values: 'connected', 'active', 'installed', 'not installed'.
	 *
	 * @param string  $connector_id The connector ID.
	 * @param mixed[] $connector    Connector settings from wp_get_connectors().
	 * @return string
	 */
	private function get_connector_status( string $connector_id, array $connector ): string {
		$auth        = is_array( $connector['authentication'] ) ? $connector['authentication'] : array();
		$plugin      = isset( $connector['plugin'] ) && is_array( $connector['plugin'] ) ? $connector['plugin'] : array();
		$plugin_file = isset( $plugin['file'] ) && is_string( $plugin['file'] ) ? $plugin['file'] : '';
		$method      = isset( $auth['method'] ) && is_string( $auth['method'] ) ? $auth['method'] : '';
		$setting     = isset( $auth['setting_name'] ) && is_string( $auth['setting_name'] ) ? $auth['setting_name'] : '';

		if ( 'api_key' === $method && '' !== $setting ) {
			// The option_* filter registered by WP core masks the value automatically.
			$raw = get_option( $setting, '' );
			if ( is_string( $raw ) && '' !== $raw ) {
				return 'connected';
			}
		}

		if ( ! $plugin_file ) {
			return 'not installed';
		}

		
		if ( is_plugin_active( $plugin_file ) ) {
			return 'active';
		}
		
		$is_installed = file_exists( wp_normalize_path( WP_PLUGIN_DIR . '/' . $file ) );

		if ( $is_installed) {
			return 'installed';
		}

		return 'not installed';
	}

	/**
	 * Casts a scalar value to string, returning an empty string for non-scalars.
	 *
	 * @param mixed $value The value to cast.
	 * @return string
	 */
	private function scalar_to_string( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
