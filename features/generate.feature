Feature: Generate AI content

  Background:
    Given a WP install
    And a wp-content/mu-plugins/mock-provider.php file:
      """
      <?php

      use WordPress\AiClient\AiClient;
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
      use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
      use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
      use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
      use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
      use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
      use WordPress\AiClient\Results\DTO\Candidate;
      use WordPress\AiClient\Results\DTO\GenerativeAiResult;
      use WordPress\AiClient\Results\DTO\TokenUsage;
      use WordPress\AiClient\Results\Enums\FinishReasonEnum;
      use WordPress\AI_Client\API_Credentials\API_Credentials_Manager;

      class WP_CLI_Mock_Model implements ModelInterface, TextGenerationModelInterface {
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
              new SupportedOption(
                OptionEnum::inputModalities(),
                [
                  [ModalityEnum::text()]
                ]
              ),
              new SupportedOption(
                OptionEnum::outputModalities(),
                [
                    [ModalityEnum::text()],
                    [ModalityEnum::text(), ModalityEnum::image()],
                ]
              ),
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
              new MessagePart('Generated content')
          ]);
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

        public function streamGenerateTextResult(array $prompt): Generator {
            yield from [];
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

      WP_CLI::add_hook(
        'ai_client_init',
        static function () {
          AiClient::defaultRegistry()->registerProvider( WP_CLI_Mock_Provider::class );

          ( new API_Credentials_Manager() )->initialize();
        }
      );
      """

  Scenario: Generate command validates model format
    When I try `wp ai generate text "Test prompt" --model=invalidformat`
    Then the return code should be 1

  Scenario: Generate command validates max-tokens
    When I try `wp ai generate text "Test prompt" --max-tokens=-5`
    Then the return code should be 1
    And STDERR should contain:
      """
      Max tokens must be a positive integer
      """

  Scenario: Generate command validates top-p range
    When I try `wp ai generate text "Test prompt" --top-p=1.5`
    Then the return code should be 1
    And STDERR should contain:
      """
      Top-p must be between 0.0 and 1.0
      """

  Scenario: Generate command validates top-k positive
    When I try `wp ai generate text "Test prompt" --top-k=-10`
    Then the return code should be 1
    And STDERR should contain:
      """
      Top-k must be a positive integer
      """
