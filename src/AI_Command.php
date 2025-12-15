<?php

namespace WP_CLI\AI;

use WP_CLI;
use WP_CLI_Command;
use WordPress\AI_Client\AI_Client;

/**
 * Interacts with the WordPress AI Client for text and image generation.
 *
 * ## EXAMPLES
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
	 * : Type of content to generate. Options: text, image
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
	 * : Temperature for generation (0.0-2.0). Lower is more deterministic.
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
	 * : Output format for text. Options: text, json. Default: text
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate text
	 *     $ wp ai generate text "Explain WordPress in one sentence"
	 *
	 *     # Generate text with specific settings
	 *     $ wp ai generate text "List 3 WordPress features" --temperature=0.1 --max-tokens=100
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
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function generate( $args, $assoc_args ) {
		if ( ! class_exists( '\WordPress\AI_Client\AI_Client' ) ) {
			WP_CLI::error( 'WordPress AI Client is not available. Please install wordpress/wp-ai-client.' );
		}

		list( $type, $prompt ) = $args;

		$type = strtolower( $type );

		if ( ! in_array( $type, array( 'text', 'image' ), true ) ) {
			WP_CLI::error( 'Invalid type. Must be "text" or "image".' );
		}

		try {
			$builder = AI_Client::prompt( $prompt );

			// Apply provider if specified
			if ( isset( $assoc_args['provider'] ) ) {
				$builder = $builder->using_provider( $assoc_args['provider'] );
			}

			// Apply model preferences if specified
			if ( isset( $assoc_args['model'] ) ) {
				$model_parts = explode( ',', $assoc_args['model'] );

				// Models should be in pairs: provider,model,provider,model,...
				if ( count( $model_parts ) % 2 !== 0 ) {
					WP_CLI::error( 'Model must be in format "provider,model" pairs (e.g., "openai,gpt-4" or "openai,gpt-4,anthropic,claude-3").' );
				}

				// Convert flat array to array of [provider, model] pairs
				$model_preferences = array();
				$parts_count       = count( $model_parts );
				for ( $i = 0; $i < $parts_count; $i += 2 ) {
					$model_preferences[] = array( $model_parts[ $i ], $model_parts[ $i + 1 ] );
				}

				// Pass all preferences to using_model_preference using spread operator
				$builder = $builder->using_model_preference( ...$model_preferences );
			}

			// Apply temperature if specified
			if ( isset( $assoc_args['temperature'] ) ) {
				$temperature = (float) $assoc_args['temperature'];
				if ( $temperature < 0.0 || $temperature > 2.0 ) {
					WP_CLI::error( 'Temperature must be between 0.0 and 2.0.' );
				}
				$builder = $builder->using_temperature( $temperature );
			}

			// Apply max tokens if specified
			if ( isset( $assoc_args['max-tokens'] ) ) {
				$max_tokens = (int) $assoc_args['max-tokens'];
				if ( $max_tokens <= 0 ) {
					WP_CLI::error( 'Max tokens must be a positive integer.' );
				}
				$builder = $builder->using_max_tokens( $max_tokens );
			}

			// Apply system instruction if specified
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
	 * : Type to check. Options: text, image. Default: text
	 *
	 * ## EXAMPLES
	 *
	 *     # Check if text generation is supported
	 *     $ wp ai check "Write a poem"
	 *
	 *     # Check if image generation is supported
	 *     $ wp ai check "A sunset" --type=image
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function check( $args, $assoc_args ) {
		if ( ! class_exists( '\WordPress\AI_Client\AI_Client' ) ) {
			WP_CLI::error( 'WordPress AI Client is not available. Please install wordpress/wp-ai-client.' );
		}

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
	 * Generates text from the prompt builder.
	 *
	 * @param \WordPress\AI_Client\Builders\Prompt_Builder $builder     The prompt builder.
	 * @param array                                        $assoc_args Associative arguments.
	 * @return void
	 */
	private function generate_text( $builder, $assoc_args ) {
		$format = $assoc_args['format'] ?? 'text';

		// Check if supported
		if ( ! $builder->is_supported_for_text_generation() ) {
			WP_CLI::error( 'Text generation is not supported. Make sure AI provider credentials are configured.' );
		}

		$text = $builder->generate_text();

		if ( 'json' === $format ) {
			$json = json_encode( array( 'text' => $text ) );
			if ( false === $json ) {
				WP_CLI::error( 'Failed to encode text as JSON: ' . json_last_error_msg() );
			}
			WP_CLI::line( $json );
		} else {
			WP_CLI::success( 'Generated text:' );
			WP_CLI::line( $text );
		}
	}

	/**
	 * Generates an image from the prompt builder.
	 *
	 * @param \WordPress\AI_Client\Builders\Prompt_Builder $builder     The prompt builder.
	 * @param array                                        $assoc_args Associative arguments.
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
			$forbidden_paths = array(
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
			foreach ( $forbidden_paths as $forbidden ) {
				// Case-insensitive comparison for Windows paths
				if ( 0 === stripos( $real_parent_dir, $forbidden ) ) {
					WP_CLI::error( 'Cannot write to system directory: ' . $safe_output_path );
				}
			}

			// Get the image content from data URI
			$data_uri = $image_file->getDataUri();

			// Extract base64 data from data URI
			$data_parts = explode( ',', $data_uri, 2 );
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
			WP_CLI::line( $image_file->getDataUri() );
		}
	}
}
