<?php

namespace WP_CLI\AI\Tests\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;

/**
 * Local FeatureContext providing custom step definitions for alt-text tests.
 */
class FeatureContext implements Context {

	/**
	 * Create a file with base64-encoded content.
	 *
	 * @Given a file :path with base64 content:
	 */
	public function given_a_file_with_base64_content( string $path, PyStringNode $content ): void {
		$decoded = base64_decode( trim( $content->getRaw() ) );
		if ( false === $decoded ) {
			throw new \RuntimeException( "Failed to decode base64 content for file: $path" );
		}
		file_put_contents( $path, $decoded );
	}
}
