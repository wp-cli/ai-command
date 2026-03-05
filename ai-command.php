<?php

namespace WP_CLI\AI;

use WP_CLI;
use WP_CLI\Utils;

if ( ! class_exists( '\WP_CLI' ) ) {
	return;
}

$wpcli_ai_autoloader = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $wpcli_ai_autoloader ) ) {
	require_once $wpcli_ai_autoloader;
}

$wpcli_ai_before_invoke = static function () {
	if ( Utils\wp_version_compare( '7.0-beta1', '<' ) ) {
		WP_CLI::error( 'Requires WordPress 7.0 or greater.' );
	}
};

WP_CLI::add_command( 'ai', AI_Command::class, [ 'before_invoke' => $wpcli_ai_before_invoke ] );
WP_CLI::add_command( 'connectors', Connectors_Command::class, [ 'before_invoke' => $wpcli_ai_before_invoke ] );
