# SilverStripe Logger Bridge [![Build Status](https://travis-ci.org/camspiers/silverstripe-loggerbridge.png?branch=master)](https://travis-ci.org/camspiers/silverstripe-loggerbridge)

Provides easy usage of `PSR-3` loggers (like [Monolog](https://github.com/Seldaek/monolog)) in SilverStripe.

The API is currently experimental and as such is at major [version](http://semver.org/) `0`.

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

# Advanced setup

This setup provides the following:

* Logging to a [Sentry](https://getsentry.com/welcome/) server, through a raven client (`composer require raven/raven`)
	* Uses one Sentry project for `live` and one Sentry project for `test` and `dev`
* Logging to a file for error levels `error` and above
* Logging to [Chrome Logger](http://craig.is/writing/chrome-logger) when environment `dev`
	* Errors are displayed in the Chrome console instead of displaying in the webpage
* Logging to [FirePHP](http://www.firephp.org/) when environment `dev`
	* Errors are displayed in the Firebug console instead of displaying in the webpage
* Logging of peak memory usage along with error

```yml
---
Except:
  environment: live
---
Injector:
  Raven:
    class: Raven_Client
    constructor:
      0: http://someraven.url/1
      
  Monolog:
    class: Monolog\Logger
    constructor:
      0: App
      1:
        - '%$RavenHandler'
        - '%$StreamHandler'
        - '%$ChromePHPHandler'
        - '%$FirePHPHandler'
      2:
        - '%$MemoryPeakUsageProcessor'

---
Only:
  environment: live
---
Injector:
  Raven:
    class: Raven_Client
    constructor:
      0: http://someraven.url/1
  Monolog:
    class: Monolog\Logger
    constructor:
      0: App
      1:
        - '%$RavenHandler'
        - '%$StreamHandler'
      2:
        - '%$MemoryPeakUsageProcessor'

---
Name: logging
---
Injector:
  LoggerBridge:
    class: LoggerBridge
    constructor:
      0: '%$Monolog'
      1: false
  RavenHandler:
    class: Monolog\Handler\RavenHandler
    constructor:
      0: '%$Raven'
  StreamHandler:
    class: Monolog\Handler\StreamHandler
    constructor:
      0: '../../error.log'
      1: 400
  ChromePHPHandler:
    class: Monolog\Handler\ChromePHPHandler
  FirePHPHandler:
    class: Monolog\Handler\FirePHPHandler
  MemoryPeakUsageProcessor:
    class: Monolog\Processor\MemoryPeakUsageProcessor
```

## Unit testing

    $ composer install --dev --prefer-dist
    $ vendor/bin/phpunit
    
---
##License

SilverStripe Logger Bridge is released under the [MIT license](http://camspiers.mit-license.org/)