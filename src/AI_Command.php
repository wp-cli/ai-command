<?php

namespace WP_CLI\AI;

use WP_CLI;
use WP_CLI_Command;
use WordPress\AI_Client\AI_Client;
use WordPress\AiClient\Results\DTO\TokenUsage;

/**
 * Interacts with the WordPress AI Client for text and image generation.
 *
 * ## EXAMPLES
 *
 *     # Check AI capabilities status
 *     $ wp ai status
 *     +------------------+-----------+
 *     | Capability       | Supported |
 *     +------------------+-----------+
 *     | Text Generation  | Yes       |
 *     | Image Generation | No        |
 *     +------------------+-----------+
 *
 *     # Generate text from a prompt
 *     $ wp ai generate text "Write a haiku about WordPress"
 *     Success: Generated text:
 *     Open source and free
 *     Empowering creators
 *     WordPress shines bright
 *
 *     # Generate an image from a prompt
 *     $ wp ai generate image "A futuristic WordPress logo" --output=logo.png
 *     Success: Image saved to logo.png
 *
 *     # Check if a prompt is supported
 *     $ wp ai check "Summarize this text"
 *     Success: Text generation is supported for this prompt.
 *
 * @when after_wp_load
 */
class AI_Command extends WP_CLI_Command {

	/**
	 * Maximum binary image size in bytes (50MB).
	 */
	const MAX_IMAGE_SIZE_BYTES = 52428800; // 50 * 1024 * 1024

	/**
	 * Dummy prompt used for capability checking.
	 *
	 * This constant provides a consistent prompt value when checking AI capabilities.
	 * The specific content doesn't affect capability detection, which is based on
	 * configured providers and their available features.
	 */
	const CAPABILITY_CHECK_PROMPT = 'capability-check';

	/**
	 * System directories that should be protected from file writes.
	 */
	const FORBIDDEN_PATHS = array(
		// Unix/Linux system directories
		'/etc',
		'/bin',
		'/usr/bin',
		'/sbin',
		'/usr/sbin',
		'/boot',
		'/sys',
		'/proc',
		// Windows system directories (case-insensitive)
		'C:\\Windows',
		'C:\\Program Files',
		'C:\\Program Files (x86)',
	);

	/**
	 * Maximum size for base64-encoded image data.
	 * Base64 encoding increases size by ~33%, so 50MB binary = ~67MB base64.
	 * Using 70MB as safe upper bound.
	 */
	const MAX_IMAGE_SIZE_BASE64 = 70000000;

	/**
	 * Generates AI content.
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : Type of content to generate.
	 * ---
	 * options:
	 *   - text
	 *   - image
	 * ---
	 *
	 * <prompt>
	 * : The prompt to send to the AI.
	 *
	 * [--model=<models>]
	 * : Comma-separated list of models in order of preference. Format: "provider,model" (e.g., "openai,gpt-4" or "openai,gpt-4,anthropic,claude-3").
	 *
	 * [--provider=<provider>]
	 * : Specific AI provider to use (e.g., "openai", "anthropic", "google").
	 *
	 * [--temperature=<temperature>]
	 * : Temperature for generation, typically between 0.0 and 1.0. Lower is more deterministic.
	 *
	 * [--top-p=<top-p>]
	 * : Top-p (nucleus sampling) parameter. Value between 0.0 and 1.0.
	 *
	 * [--top-k=<top-k>]
	 * : Top-k sampling parameter. Positive integer.
	 *
	 * [--max-tokens=<tokens>]
	 * : Maximum number of tokens to generate.
	 *
	 * [--system-instruction=<instruction>]
	 * : System instruction to guide the AI's behavior.
	 *
	 * [--output=<file>]
	 * : For image generation, path to save the generated image.
	 *
	 * [--format=<format>]
	 * : Output format for text.
	 * ---
	 * default: text
	 * options:
	 *   - text
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate text
	 *     $ wp ai generate text "Explain WordPress in one sentence"
	 *
	 *     # Generate text with specific settings
	 *     $ wp ai generate text "List 3 WordPress features" --temperature=0.1 --max-tokens=100
	 *
	 *     # Generate with top-p and top-k sampling
	 *     $ wp ai generate text "Write a story" --top-p=0.9 --top-k=40
	 *
	 *     # Generate with model preferences
	 *     $ wp ai generate text "Write a haiku" --model=openai,gpt-4,anthropic,claude-3
	 *
	 *     # Generate with system instruction
	 *     $ wp ai generate text "Explain AI" --system-instruction="Explain as if to a 5-year-old"
	 *
	 *     # Generate image
	 *     $ wp ai generate image "A minimalist WordPress logo" --output=wp-logo.png
	 *
	 * @param array{0: string, 1: string} $args Positional arguments.
	 * @param array{model: string, provider: string, temperature: float, 'top-p': float, 'top-k': int, 'max-tokens': int, 'system-instruction': string, output: string, format: string} $assoc_args Associative arguments.
	 * @return void
	 */
	public function generate( $args, $assoc_args ) {
		$this->initialize_ai_client();

		list( $type, $prompt ) = $args;

		try {
			$builder = AI_Client::prompt( $prompt );

			if ( isset( $assoc_args['provider'] ) ) {
				$builder = $builder->using_provider( $assoc_args['provider'] );
			}

			if ( isset( $assoc_args['model'] ) ) {
				// Models should be in pairs: provider:model,provider:model,...
				// Convert to array of [provider, model] pairs.
				$model_preferences = explode( ',', $assoc_args['model'] );
				foreach ( $model_preferences as $key => $value ) {
					$value = explode( ':', $value );

					$entries[ $key ] = $value;

					if ( count( $value ) !== 2 ) {
						WP_CLI::error( 'Model must be in format "provider:model" pairs (e.g., "openai:gpt-4" or "openai:gpt-4,anthropic:claude-3").' );
					}
				}

				$builder = $builder->using_model_preference( ...$model_preferences );
			}

			if ( isset( $assoc_args['temperature'] ) ) {
				$builder = $builder->using_temperature( (float) $assoc_args['temperature'] );
			}

			if ( isset( $assoc_args['top-p'] ) ) {
				$top_p = (float) $assoc_args['top-p'];
				if ( $top_p < 0.0 || $top_p > 1.0 ) {
					WP_CLI::error( 'Top-p must be between 0.0 and 1.0.' );
				}
				$builder = $builder->using_top_p( $top_p );
			}

			if ( isset( $assoc_args['top-k'] ) ) {
				$top_k = (int) $assoc_args['top-k'];
				if ( $top_k <= 0 ) {
					WP_CLI::error( 'Top-k must be a positive integer.' );
				}
				$builder = $builder->using_top_k( $top_k );
			}

			if ( isset( $assoc_args['max-tokens'] ) ) {
				$max_tokens = (int) $assoc_args['max-tokens'];
				if ( $max_tokens <= 0 ) {
					WP_CLI::error( 'Max tokens must be a positive integer.' );
				}
				$builder = $builder->using_max_tokens( $max_tokens );
			}

			if ( isset( $assoc_args['system-instruction'] ) ) {
				$builder = $builder->using_system_instruction( $assoc_args['system-instruction'] );
			}

			if ( 'text' === $type ) {
				$this->generate_text( $builder, $assoc_args );
			} elseif ( 'image' === $type ) {
				$this->generate_image( $builder, $assoc_args );
			}
		} catch ( \Exception $e ) {
			WP_CLI::error( 'AI generation failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Checks if a prompt is supported for generation.
	 *
	 * ## OPTIONS
	 *
	 * <prompt>
	 * : The prompt to check.
	 *
	 * [--type=<type>]
	 * : Type to check.
	 * ---
	 * options:
	 *   - text
	 *   - image
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Check if text generation is supported
	 *     $ wp ai check "Write a poem"
	 *
	 *     # Check if image generation is supported
	 *     $ wp ai check "A sunset" --type=image
	 *
	 * @param array{0: string}    $args       Positional arguments.
	 * @param array{type: string} $assoc_args Associative arguments.
	 * @return void
	 */
	public function check( $args, $assoc_args ) {
		$this->initialize_ai_client();

		list( $prompt ) = $args;
		$type           = $assoc_args['type'] ?? 'text';

		try {
			$builder = AI_Client::prompt( $prompt );

			if ( 'text' === $type ) {
				$supported = $builder->is_supported_for_text_generation();
				if ( $supported ) {
					WP_CLI::success( 'Text generation is supported for this prompt.' );
				} else {
					WP_CLI::error( 'Text generation is not supported. Make sure AI provider credentials are configured.' );
				}
			} elseif ( 'image' === $type ) {
				$supported = $builder->is_supported_for_image_generation();
				if ( $supported ) {
					WP_CLI::success( 'Image generation is supported for this prompt.' );
				} else {
					WP_CLI::error( 'Image generation is not supported. Make sure AI provider credentials are configured.' );
				}
			} else {
				WP_CLI::error( 'Invalid type. Must be "text" or "image".' );
			}
		} catch ( \Exception $e ) {
			WP_CLI::error( 'Check failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Checks which AI capabilities are currently supported.
	 *
	 * Checks the environment and credentials to determine which AI operations
	 * are available. Displays a table showing supported capabilities.
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
	 *     # Check AI status
	 *     $ wp ai status
	 *     +------------------+-----------+
	 *     | Capability       | Supported |
	 *     +------------------+-----------+
	 *     | Text Generation  | Yes       |
	 *     | Image Generation | No        |
	 *     +------------------+-----------+
	 *
	 * @param string[]              $args       Positional arguments. Unused.
	 * @param array{format: string} $assoc_args Associative arguments.
	 * @return void
	 */
	public function status( $args, $assoc_args ) {
		$this->initialize_ai_client();

		try {
			// Create a builder to check capabilities (using constant for consistency)
			$builder = AI_Client::prompt( self::CAPABILITY_CHECK_PROMPT );

			// Check each capability
			$capabilities = array(
				array(
					'capability' => 'Text Generation',
					'supported'  => $builder->is_supported_for_text_generation() ? 'Yes' : 'No',
				),
				array(
					'capability' => 'Image Generation',
					'supported'  => $builder->is_supported_for_image_generation() ? 'Yes' : 'No',
				),
			);

			$format = $assoc_args['format'] ?? 'table';
			WP_CLI\Utils\format_items( $format, $capabilities, array( 'capability', 'supported' ) );
		} catch ( \Exception $e ) {
			WP_CLI::error( 'Status check failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Generates text from the prompt builder.
	 *
	 * @param \WordPress\AI_Client\Builders\Prompt_Builder $builder     The prompt builder.
	 * @param array{format: string}                        $assoc_args Associative arguments.
	 * @return void
	 */
	private function generate_text( $builder, $assoc_args ) {
		$format = $assoc_args['format'] ?? 'text';

		// Check if supported
		if ( ! $builder->is_supported_for_text_generation() ) {
			WP_CLI::error( 'Text generation is not supported. Make sure AI provider credentials are configured.' );
		}

		$text = $builder->generate_text_result();

		if ( 'json' === $format ) {
			$json = json_encode( array( 'text' => $text->toText() ) );
			if ( false === $json ) {
				WP_CLI::error( 'Failed to encode text as JSON: ' . json_last_error_msg() );
			}
			WP_CLI::line( $json );
		} else {
			WP_CLI::line( $text->toText() );
		}

		$token_usage = $text->getTokenUsage()->toArray();

		WP_CLI::debug(
			sprintf(
				"Summary:\nModel used: %s (%s)\nToken usage:\nInput tokens: %s\nOutput tokens: %s\nTotal: %s\n",
				$text->getModelMetadata()->getName(),
				$text->getProviderMetadata()->getName(),
				$token_usage[ TokenUsage::KEY_PROMPT_TOKENS ],
				$token_usage[ TokenUsage::KEY_COMPLETION_TOKENS ],
				$token_usage[ TokenUsage::KEY_TOTAL_TOKENS ],
			),
			'ai'
		);
	}

	/**
	 * Generates an image from the prompt builder.
	 *
	 * @param \WordPress\AI_Client\Builders\Prompt_Builder $builder    The prompt builder.
	 * @param array{output: string}                        $assoc_args Associative arguments.
	 * @return void
	 */
	private function generate_image( $builder, $assoc_args ) {
		// Check if supported
		if ( ! $builder->is_supported_for_image_generation() ) {
			WP_CLI::error( 'Image generation is not supported. Make sure AI provider credentials are configured.' );
		}

		$image_file = $builder->generate_image();

		if ( isset( $assoc_args['output'] ) ) {
			$output_path = $assoc_args['output'];

			// Resolve the full real path
			$parent_dir = dirname( $output_path );

			// Check if parent directory exists
			if ( ! file_exists( $parent_dir ) || ! is_dir( $parent_dir ) ) {
				WP_CLI::error( 'Invalid output directory. Directory does not exist: ' . $parent_dir );
			}

			// Resolve the real path to prevent traversal attacks
			$real_parent_dir = realpath( $parent_dir );
			if ( false === $real_parent_dir ) {
				WP_CLI::error( 'Cannot resolve output directory path.' );
			}

			// Reconstruct the output path with the resolved parent directory
			$safe_output_path = $real_parent_dir . DIRECTORY_SEPARATOR . basename( $output_path );

			// Prevent writing to sensitive system directories
			foreach ( self::FORBIDDEN_PATHS as $forbidden ) {
				// Use case-sensitive check for Unix paths (start with /), case-insensitive for Windows (contain :\)
				$is_windows_path = ( false !== strpos( $forbidden, ':\\' ) );
				$matches         = $is_windows_path
					? ( 0 === stripos( $real_parent_dir, $forbidden ) )
					: ( 0 === strpos( $real_parent_dir, $forbidden ) );

				if ( $matches ) {
					WP_CLI::error( 'Cannot write to system directory: ' . $safe_output_path );
				}
			}

			// Get the image content from data URI
			$data_uri = $image_file->getDataUri();

			// Extract base64 data from data URI
			$data_parts = $data_uri ? explode( ',', $data_uri, 2 ) : [];
			if ( count( $data_parts ) !== 2 ) {
				WP_CLI::error( 'Invalid image data received.' );
			}

			// Validate and decode base64 data
			$base64_data = $data_parts[1];

			// Check reasonable size limit
			if ( strlen( $base64_data ) > self::MAX_IMAGE_SIZE_BASE64 ) {
				WP_CLI::error( 'Image data exceeds maximum size limit (50MB).' );
			}

			// Try strict base64 decode - this validates format
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$image_data = base64_decode( $base64_data, true );
			if ( false === $image_data ) {
				WP_CLI::error( 'Invalid base64 image data format.' );
			}

			// Save to file
			$result = file_put_contents( $safe_output_path, $image_data );
			if ( false === $result ) {
				WP_CLI::error( 'Failed to save image to ' . $safe_output_path );
			}

			WP_CLI::success( 'Image saved to ' . $safe_output_path );
		} else {
			// Output data URI
			WP_CLI::success( 'Image generated (data URI):' );
			WP_CLI::line( (string) $image_file->getDataUri() );
		}
	}

	/**
	 * Ensures WordPress AI Client is available.
	 *
	 * @return void
	 */
	private function initialize_ai_client() {
		\WordPress\AI_Client\AI_Client::init();

		add_filter(
			'user_has_cap',
			static function ( array $allcaps ) {
				$allcaps[ \WordPress\AI_Client\Capabilities\Capabilities_Manager::PROMPT_AI_CAPABILITY ] = true;

				return $allcaps;
			}
		);

		WP_CLI::do_hook( 'ai_client_init' );
	}
}
