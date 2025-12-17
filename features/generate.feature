Feature: Generate AI content

  Background:
    Given a WP install
    And a wp-content/mu-plugins/mock-provider.php file:
      """
      <?php

      use WordPress\AiClient\AiClient;
      use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
      use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
      use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
      use WordPress\AiClient\Providers\DTO\ProviderMetadata;
      use WordPress\AiClient\Providers\Contracts\ProviderInterface;
      use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
      use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
      use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;

      class WP_CLI_Mock_Model implements ModelInterface {
        private $id;
        private $config;

        public function __construct( $id ) {
          $this->id     = $id;
          $this->config = new ModelConfig();
        }

        public function metadata(): ModelMetadata {
          return new ModelMetadata( $this->id, 'Mock Model', array(), array() );
        }

        public function providerMetadata(): ProviderMetadata {
          return Mock_Provider::metadata();
        }

        public function setConfig( ModelConfig $config ): void {
          $this->config = $config;
        }

        public function getConfig(): ModelConfig {
          return $this->config;
        }
      }

      class WP_CLI_Mock_Provider {
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
              return array();
            }

            public function hasModelMetadata( string $modelId ): bool {
              return true;
            }

            public function getModelMetadata( string $modelId ): ModelMetadata {
              return new ModelMetadata( $modelId, 'WP-CLI Mock Model', array(), array() );
            }
          };
        }
      }

      add_action(
        'init',
        static function () {
          AiClient::defaultRegistry()->registerProvider( WP_CLI_Mock_Provider::class );
        }
      );
      """

  Scenario: Check for WordPress AI Client availability
    When I try `wp ai check "Test prompt"`
    Then the return code should be 1
    And STDERR should contain:
      """
      WordPress AI Client is not available
      """

  Scenario: Generate command requires AI Client
    When I try `wp ai generate text "Test prompt"`
    Then the return code should be 1
    And STDERR should contain:
      """
      WordPress AI Client is not available
      """

  Scenario: Status command requires AI Client
    When I try `wp ai status`
    Then the return code should be 1
    And STDERR should contain:
      """
      WordPress AI Client is not available
      """

  Scenario: Generate command validates model format
    When I try `wp ai generate text "Test prompt" --model=invalidformat`
    Then the return code should be 1

  Scenario: Generate command validates temperature range
    When I try `wp ai generate text "Test prompt" --temperature=3.0`
    Then the return code should be 1
    And STDERR should contain:
      """
      Temperature must be between 0.0 and 2.0
      """

  Scenario: Generate command validates max-tokens
    When I try `wp ai generate text "Test prompt" --max-tokens=-5`
    Then the return code should be 1
    And STDERR should contain:
      """
      Max tokens must be a positive integer
      """

