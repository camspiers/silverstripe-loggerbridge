# SilverStripe Logger Bridge

[![Build Status](https://travis-ci.org/camspiers/silverstripe-loggerbridge.png?branch=master)](https://travis-ci.org/camspiers/silverstripe-loggerbridge) [![Latest Stable Version](https://poser.pugx.org/camspiers/silverstripe-loggerbridge/v/stable.png)](https://packagist.org/packages/camspiers/silverstripe-loggerbridge) [![Code Coverage](https://scrutinizer-ci.com/g/camspiers/silverstripe-loggerbridge/badges/coverage.png?s=08a49538c62d2da0c1fc8520e495f6c2577f9ddc)](https://scrutinizer-ci.com/g/camspiers/silverstripe-loggerbridge/)

Provides easy usage of `PSR-3` loggers (like [monolog](https://github.com/Seldaek/monolog)) in SilverStripe.

## Installation (composer required)

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
				class: Camspiers\LoggerBridge\LoggerBridge
				constructor:
					0: '%$Monolog'

### Changing the way errors display in development

By default Logger Bridge uses `Whoops` for the display of errors and exceptions. You can change to using SilverStripe
error display by adding the following to your yml:

	Injector:
		LoggerBridge:
			properties:
				ErrorReporter: '%$LoggerBridgeDebugErrorReporter'

## Advanced-style setup

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
    class: Camspiers\LoggerBridge\LoggerBridge
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

### Attaching the logger as early as possible

SilverStripe currently doesn't provide any way to replace the default `Debug` error handlers prior to the
database connection etc. But the following patch will use the Logger Bridge as early as possible.

To apply the patch, run the following from the `framework` directory of a `3.1.x-dev` install.
 
	patch -p1 < framework.patch

`framework.patch`

```diff
diff --git a/core/Core.php b/core/Core.php
index bc3f583..4c9f59e 100644
--- a/core/Core.php
+++ b/core/Core.php
@@ -131,7 +131,7 @@ if(Director::isLive()) {
 /**
  * Load error handlers
  */
-Debug::loadErrorHandlers();
+Injector::inst()->get('LoggerBridge')->registerGlobalHandlers();
 
 
 ///////////////////////////////////////////////////////////////////////////////
```

## Unit testing

Logger Bridge has good unit test converage. To run the unit tests:

    $ composer install --dev --prefer-dist
    $ phpunit
    
---
##License

SilverStripe Logger Bridge is released under the [MIT license](http://camspiers.mit-license.org/)
