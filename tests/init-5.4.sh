#!/bin/bash

cp composer.json composer-backup.json

composer require "illuminate/cache:5.4.*" --no-update -q
composer require "illuminate/database:5.4.*" --no-update -q
composer require "illuminate/support:5.4.*" --no-update -q
composer require "orchestra/database:3.4.*" --no-update -q
composer require "orchestra/testbench:3.4.*" --no-update -q
composer require "phpunit/phpunit:6.0.*" --no-update -q
composer require "php-coveralls/php-coveralls:2.1.*" --no-update -q
composer update -q
composer dump-autoload

mv -f composer-backup.json composer.json
