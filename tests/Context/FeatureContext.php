<?php

namespace WP_CLI\AI\Tests\Context;

use Behat\Gherkin\Node\PyStringNode;
use RuntimeException;

/**
 * Features context for AI command tests.
 */
class FeatureContext extends \WP_CLI\Tests\Context\FeatureContext {

	/**
	 * Creates a file with base64-encoded content.
	 *
	 * @Given a file /tmp/test-image.png with base64 content:
	 */
	public function given_a_file_tmp_test_image_png_with_base64_content( PyStringNode $content ) {
		$decoded = base64_decode( trim( $content->getRaw() ) );
		if ( false === $decoded ) {
			throw new RuntimeException( 'Failed to decode base64 content for file: /tmp/test-image.png' );
		}
		file_put_contents( '/tmp/test-image.png', $decoded );
	}
}
