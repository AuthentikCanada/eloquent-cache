#!/bin/bash

cp composer.json composer-backup.json

composer require "php:>=7.3.0" --no-update -q
composer require "phpunit/phpunit:9.3.*" --no-update -q
composer require "orchestra/database:6.*" --no-update -q
composer require "orchestra/testbench:6.0.*" --no-update -q
composer require "laravel/legacy-factories:^1.0.4" --no-update -q
composer require "illuminate/cache:8.0.*" --no-update -q
composer require "illuminate/database:8.0.*" --no-update -q
composer require "illuminate/support:8.0.*" --no-update -q
composer require "php-coveralls/php-coveralls:2.4.*" --no-update -q
composer require "mockery/mockery:1.3.1" --no-update -q
composer update
composer dump-autoload

mv -f composer-backup.json composer.json
