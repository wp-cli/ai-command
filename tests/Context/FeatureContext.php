<?php

namespace WP_CLI\Tests\Context;

use Behat\Gherkin\Node\PyStringNode;

/**
 * Local FeatureContext extending vendor base to add custom step definitions.
 */
class FeatureContext extends \WP_CLI\Tests\Context\FeatureContext {

	/**
	 * Create a file with base64-encoded content.
	 *
	 * @Given a file :path with base64 content:
	 */
	public function given_a_file_with_base64_content( $path, PyStringNode $content ): void {
		$decoded = base64_decode( trim( $content->getRaw() ) );
		if ( false === $decoded ) {
			throw new \RuntimeException( "Failed to decode base64 content for file: $path" );
		}
		file_put_contents( $path, $decoded );
	}
}
