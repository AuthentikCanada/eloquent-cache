# Eloquent Cache

> Easily cache your Laravel's Eloquent models.

## Installation

Install via [composer](https://getcomposer.org/) :

`composer require authentik/eloquent-cache`

## Usage

Use the `Cacheable` trait in the models you want to cache.

You can optionally define another TTL or tag name.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Authentik\EloquentCache\Cacheable;

class Category extends Model
{
    use Cacheable;

    // in minutes (default: 0 => no ttl)
    public $cacheTTL = 60; 

    // default => the lowercase name of the model
    public $cacheTagName = 'cat';

    // Cache busting will automatically invalidate the cache when model instances are updated or deleted.
    // default => true
    public $cacheBusting = false;
}
```

To invalidate the cache for a model instance, use the `refresh` method.

```php
Category::find([1, 2, 3])->refresh();
```