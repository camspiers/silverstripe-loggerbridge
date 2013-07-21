# SilverStripe Logger Bridge

Provides easy usage of `PSR-3` loggers (like [Monolog](https://github.com/Seldaek/monolog)) in SilverStripe.

## Installation (with composer)

	$ composer require camspiers/silverstripe-loggerbridge:dev-master

## Usage

1. Create a config file in your `mysite`, e.g. "mysite/_config/logging.yml"
2. Set up a `PSR-3` logger service and add to the `LoggerBridge` constructor

		Injector:
			Monolog:
				class: Monolog\Logger
				constructor:
					0: App
					1:
						- '%$StreamHandler'
			StreamHandler:
				class: Monolog\Handler\StreamHandler
				constructor:
					0: '../../error.log'
			LoggerBridge:
				class: LoggerBridge
				constructor:
					0: '%$Monolog'


## Unit testing

    $ composer install --dev
    $ vendor/bin/phpunit