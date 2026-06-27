<?php

namespace Tests\Context;

use Behat\Step\Given;
use Behat\Gherkin\Node\PyStringNode;
use RuntimeException;

/**
 * Features context.
 */
class FeatureContext extends \WP_CLI\Tests\Context\FeatureContext {

	#[Given('a file /tmp/test-image.png with base64 content:')]
	public function aFileTmpTestImagepngWithBase64Content(PyStringNode $string): void
	{
		$decoded = base64_decode(trim($string->getRaw()));
		if (false === $decoded) {
			throw new RuntimeException('Failed to decode base64 content for file: /tmp/test-image.png');
		}
		file_put_contents('/tmp/test-image.png', $decoded);
	}
}
