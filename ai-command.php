<?php

namespace WP_CLI\AI;

use WP_CLI;

if ( ! class_exists( '\WP_CLI' ) ) {
	return;
}

$wpcli_ai_autoloader = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $wpcli_ai_autoloader ) ) {
	require_once $wpcli_ai_autoloader;
}

WP_CLI::add_command( 'ai', AI_Command::class );
WP_CLI::add_command( 'ai credentials', Credentials_Command::class );
