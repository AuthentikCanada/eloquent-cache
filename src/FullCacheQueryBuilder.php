<?php

namespace Authentik\EloquentCache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FullCacheQueryBuilder extends CacheQueryBuilder {
    public function __construct($query, Model $model)
    {
        parent::__construct($query, $model);

        $tagName = $model->getCacheTagName();
        static::$staticCache[$tagName] = [];
    }
}