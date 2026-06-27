Feature: Generate AI content

  Background:
    Given a WP install
    And a wp-content/mu-plugins/mock-provider.php file:
      """
      <?php

      use WordPress\AiClient\AiClient;
      use WordPress\AiClient\Files\DTO\File;
      use WordPress\AiClient\Files\Enums\FileTypeEnum;
      use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
      use WordPress\AiClient\Messages\DTO\MessagePart;
      use WordPress\AiClient\Messages\DTO\ModelMessage;
      use WordPress\AiClient\Messages\Enums\ModalityEnum;
      use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
      use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
      use WordPress\AiClient\Providers\Contracts\ProviderInterface;
      use WordPress\AiClient\Providers\DTO\ProviderMetadata;
      use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
      use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
      use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
      use WordPress\AiClient\Providers\Models\ImageGeneration\Contracts\ImageGenerationModelInterface;
      use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
      use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
      use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
      use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
      use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
      use WordPress\AiClient\Results\DTO\Candidate;
      use WordPress\AiClient\Results\DTO\GenerativeAiResult;
      use WordPress\AiClient\Results\DTO\TokenUsage;
      use WordPress\AiClient\Results\Enums\FinishReasonEnum;

      if ( ! interface_exists( 'WordPress\AiClient\Providers\Models\Contracts\ModelInterface' ) ) {
        return;
      }

      class WP_CLI_Mock_Model implements ModelInterface, TextGenerationModelInterface, ImageGenerationModelInterface {
        private $id;
        private $config;

        public function __construct( $id ) {
          $this->id     = $id;
          $this->config = new ModelConfig();
        }

        public function metadata(): ModelMetadata {
          return new ModelMetadata(
            $this->id,
            'WP-CLI Mock Model',
            // Supported capabilities.
            [
              CapabilityEnum::textGeneration(),
              CapabilityEnum::imageGeneration(),
            ],
            // Supported options.
            [
              new SupportedOption(OptionEnum::candidateCount()),
              new SupportedOption(OptionEnum::outputMimeType(), ['image/png']),
              new SupportedOption(OptionEnum::outputFileType(), [FileTypeEnum::inline()]),
              new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
              new SupportedOption(
                OptionEnum::outputModalities(),
                [
                    [ModalityEnum::text()],
                    [ModalityEnum::image()],
                    [ModalityEnum::text(), ModalityEnum::image()],
                ]
              ),
              new SupportedOption(OptionEnum::candidateCount()),
              new SupportedOption(OptionEnum::outputMimeType(), ['image/png']),
              new SupportedOption(OptionEnum::outputFileType(), [FileTypeEnum::inline(), FileTypeEnum::remote()]),
              new SupportedOption(OptionEnum::outputMediaOrientation(), [
                MediaOrientationEnum::square(),
                MediaOrientationEnum::landscape(),
                MediaOrientationEnum::portrait(),
              ]),
              new SupportedOption(OptionEnum::outputMediaAspectRatio(), ['1:1', '7:4', '4:7']),
              new SupportedOption(OptionEnum::customOptions()),
            ]
          );
        }

        public function providerMetadata(): ProviderMetadata {
          return WP_CLI_Mock_Provider::metadata();
        }

        public function setConfig( ModelConfig $config ): void {
          $this->config = $config;
        }

        public function getConfig(): ModelConfig {
          return $this->config;
        }

        public function generateTextResult(array $prompt): GenerativeAiResult {
          // throw new RuntimeException('No candidates were generated');

          $modelMessage = new ModelMessage([
              new MessagePart('This is mock-generated text')
          ]);
          $candidate = new Candidate(
              $modelMessage,
              FinishReasonEnum::stop(),
              42
          );
          $tokenUsage = new TokenUsage( 10, 42, 52 );
          return new GenerativeAiResult(
              'result_123',
              [ $candidate ],
              $tokenUsage,
              $this->providerMetadata(),
              $this->metadata(),
              [ 'provider' => 'wp-cli-mock-provider' ]
          );
        }

        public function streamGenerateTextResult(array $prompt): Generator {
            yield from [];
        }

        public function generateImageResult(array $prompt): GenerativeAiResult {
          // throw new RuntimeException('No candidates were generated');

          $modelMessage = new ModelMessage( [
              new MessagePart(
                // A base64-encoded 1x1 black PNG.
                new File(
                  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
                  'image/png'
                )
              )
          ] );
          $candidate = new Candidate(
              $modelMessage,
              FinishReasonEnum::stop(),
              42
          );
          $tokenUsage = new TokenUsage(10, 42, 52);
          return new GenerativeAiResult(
              'result_123',
              [ $candidate ],
              $tokenUsage,
              $this->providerMetadata(),
              $this->metadata(),
              [ 'provider' => 'wp-cli-mock-provider' ]
          );
        }

      }

      class WP_CLI_Mock_Provider implements ProviderInterface {
        public static function metadata(): ProviderMetadata {
          return new ProviderMetadata( 'wp-cli-mock-provider', 'WP-CLI Mock Provider', ProviderTypeEnum::cloud() );
        }

        public static function model( string $modelId, ?ModelConfig $modelConfig = null ): ModelInterface {
          return new WP_CLI_Mock_Model( $modelId );
        }

        public static function availability(): ProviderAvailabilityInterface {
          return new class() implements ProviderAvailabilityInterface {
            public function isConfigured(): bool {
              return true;
            }
          };
        }

        public static function modelMetadataDirectory(): ModelMetadataDirectoryInterface {
          return new class() implements ModelMetadataDirectoryInterface {
            public function listModelMetadata(): array {
              return [
                ( new WP_CLI_Mock_Model( 'wp-cli-mock-model' ) )->metadata()
              ];
            }

            public function hasModelMetadata( string $modelId ): bool {
              return true;
            }

            public function getModelMetadata( string $modelId ): ModelMetadata {
              return self::model()->metadata();
            }
          };
        }
      }

      WP_CLI::add_wp_hook(
        'init',
        static function () {
          AiClient::defaultRegistry()->registerProvider( WP_CLI_Mock_Provider::class );
        }
      );
      """

  @less-than-wp-7.0
  Scenario: Command not available on WP < 7.0
    Given a WP install

    When I try `wp ai generate text "Test prompt" --model=invalidformat`
    Then STDERR should contain:
      """
      Requires WordPress 7.0 or greater.
      """
    And the return code should be 1

  @require-wp-7.0
  Scenario: Generate command validates model format
    When I try `wp ai generate text "Test prompt" --model=invalidformat`
    Then the return code should be 1

  @require-wp-7.0
  Scenario: Generate command validates max-tokens
    When I try `wp ai generate text "Test prompt" --max-tokens=-5`
    Then the return code should be 1
    And STDERR should contain:
      """
      Max tokens must be a positive integer
      """

  @require-wp-7.0
  Scenario: Generate command validates top-p range
    When I try `wp ai generate text "Test prompt" --top-p=1.5`
    Then the return code should be 1
    And STDERR should contain:
      """
      Top-p must be between 0.0 and 1.0
      """

  @require-wp-7.0
  Scenario: Generate command validates top-k positive
    When I try `wp ai generate text "Test prompt" --top-k=-10`
    Then the return code should be 1
    And STDERR should contain:
      """
      Top-k must be a positive integer
      """

  @require-wp-7.0
  Scenario: Generate fails when AI is disabled
    Given a wp-content/mu-plugins/disable-ai.php file:
      """
      <?php
      add_filter( 'wp_supports_ai', '__return_false' );
      """

    When I try `wp ai generate text "Test prompt"`
    Then the return code should be 1
    And STDERR should contain:
      """
      AI features are not supported in this environment.
      """

  @require-wp-7.0
  Scenario: Generates text using mock provider
    When I run `wp ai status`
    Then STDOUT should be a table containing rows:
      | capability       | supported |
      | Text Generation  | Yes       |
      | Image Generation | Yes       |

  @require-wp-7.0
  Scenario: Generates text using mock provider
    When I run `wp ai generate text "Test prompt"`
    Then STDOUT should be:
      """
      This is mock-generated text
      """

  @require-wp-7.0
  Scenario: Generates image using mock provider
    When I run `wp ai generate image "Test prompt"`
    Then STDOUT should be:
      """
      data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=
      """
