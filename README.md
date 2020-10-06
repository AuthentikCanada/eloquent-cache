# Eloquent Cache

> Easily cache your Laravel's Eloquent models.

[![Build Status](https://travis-ci.org/AuthentikCanada/eloquent-cache.svg?branch=master)](https://travis-ci.org/AuthentikCanada/eloquent-cache)
[![Coverage Status](https://coveralls.io/repos/github/AuthentikCanada/eloquent-cache/badge.svg?branch=master)](https://coveralls.io/github/AuthentikCanada/eloquent-cache?branch=master)
[![Latest Stable Version](https://poser.pugx.org/authentik/eloquent-cache/v/stable.svg)](https://packagist.org/packages/authentik/eloquent-cache)
[![Total Downloads](https://poser.pugx.org/authentik/eloquent-cache/downloads.svg)](https://packagist.org/packages/authentik/eloquent-cache)

## Requirements

- PHP >= 7.2

- Laravel 6 / 7 / 8

## Installation

Install via [composer](https://getcomposer.org/) :

`composer require authentik/eloquent-cache`

## How it works

- When Eloquent fetches models, the JSON representations of the model instances are cached.

- Subsequently, when eloquent fetches a model by ID, the cached JSON will be converted back into an instance.

## Usage

Use the `Cacheable` trait in the models you want to cache.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Authentik\EloquentCache\Cacheable;

class Category extends Model
{
    use Cacheable;


    /*
     * You can optionally override the following functions:
     */
    
    // Time To Live in minutes (default value: 0 => no TTL)
    public function getCacheTTL() {
        return 60;
    }

    // default value: the lowercase name of the model
    public function getCacheTagName() {
        return 'cat';
    }

    // Cache busting will automatically invalidate the cache when model instances are updated or deleted.
    // default value: true
    public function isCacheBustingEnabled() {
        return false;
    }

    // Whether or not to keep model instances in a static array cache
    //  (useful to avoid querying the cache store/building instances from json multiple times)
    // default value: true
    public function isStaticCacheEnabled() {
        return false;
    }
}
```

> To manually cache a model instance, use the `cache` method.

```php
Category::find(1)->cache();
```

> To invalidate the cache for a model instance, use the `refresh` or `flush` method.

```php
$refreshedInstance = Category::find(1)->refresh();

// or

Category::flush(Category::find(1));
```

> To invalidate the cache for all instances of a model, use the `flush` method.

```php
Category::flush();
```

## Changelog

[Click Here](CHANGELOG.md)
