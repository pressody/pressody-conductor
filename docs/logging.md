# Logging

Pressody Conductor implements a [PSR-3 Logger Interface](https://www.php-fig.org/psr/psr-3/) for logging messages when `WP_DEBUG` is enabled. The default implementation only logs messages with a [log level](https://www.php-fig.org/psr/psr-3/#5-psrlogloglevel) of `warning` or higher.

Messages are logged in daily logs in the `wp-content/uploads/pressody-conductor-logs/` directory when `WP_DEBUG` is enabled.

The file-based logs are rotated if they exceed 5 Mb (by default; use the `pressody_conductor/log_file_size_limit` filter to change this), and also automatically cleaned if older than 30 days (by default; use the `pressody_conductor/logger_days_to_retain_logs` filter to change this).

## Changing the Log Level

To log more or less information, the log level can be adjusted in the DI container.

```php
<?php
add_action( 'pressody_conductor/compose', function( $plugin, $container ) {
	$container['logger.level'] = 'debug';
}, 10, 2 );
```

_Assigning an empty string or invalid level will prevent messages from being logged, effectively disabling the logger._

## Registering a Custom Logger

The example below demonstrates how to retrieve the Pressody Conductor container and register a new logger to replace the default logger. It uses [Monolog](https://github.com/Seldaek/monolog) to send warning messages through PHP's `error_log()` handler:

```php
<?php
use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * Register the logger before Pressody Conductor is composed.
 */
add_action( 'pressody_conductor/compose', function( $plugin, $container ) {
	$container['logger'] = function() {
		$logger = new Logger( 'pressody-conductor' );
		$logger->pushHandler( new ErrorLogHandler( ErrorLogHandler::OPERATING_SYSTEM, LOGGER::WARNING ) );
		$logger->pushProcessor( new PsrLogMessageProcessor );

		return $logger;
	};
}, 10, 2 );
```

_Monolog should be required with Composer and the autoloader needs to be included before using it in your project._


[Back to Index](index.md)
