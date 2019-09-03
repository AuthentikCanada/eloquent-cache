#!/bin/bash

cp composer.json composer-backup.json

composer require "phpunit/phpunit:8.3.*" --no-update -q
composer require "orchestra/database:4.x-dev" --no-update -q
composer require "orchestra/testbench:4.0.*" --no-update -q
composer require "illuminate/cache:6.0.*" --no-update -q
composer require "illuminate/database:6.0.*" --no-update -q
composer require "illuminate/support:6.0.*" --no-update -q
composer require "php-coveralls/php-coveralls:2.1.*" --no-update -q
composer require "mockery/mockery:1.2.*" --no-update -q
composer update
composer dump-autoload

mv -f composer-backup.json composer.json
