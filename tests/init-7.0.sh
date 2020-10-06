#!/bin/bash

cp composer.json composer-backup.json

composer require "php:>=7.2.5" --no-update -q
composer require "phpunit/phpunit:8.5.*" --no-update -q
composer require "orchestra/database:5.0.*" --no-update -q
composer require "orchestra/testbench:5.0.*" --no-update -q
composer require "illuminate/cache:7.0.*" --no-update -q
composer require "illuminate/database:7.0.*" --no-update -q
composer require "illuminate/support:7.0.*" --no-update -q
composer require "php-coveralls/php-coveralls:2.2.*" --no-update -q
composer require "mockery/mockery:1.3.1" --no-update -q
composer update
composer dump-autoload

mv -f composer-backup.json composer.json
