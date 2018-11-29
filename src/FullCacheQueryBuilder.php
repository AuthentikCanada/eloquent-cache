<?php

namespace Authentik\EloquentCache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FullCacheQueryBuilder extends PartialCacheQueryBuilder {
    public function __construct($query, Model $model)
    {
        parent::__construct($query, $model);

        $tagName = $model->getCacheTagName();
        static::$staticCache[$tagName] = [];
    }

    public function get($columns = ['*']) {

        if (!$this->isBasicQuery()) {
            return parent::get($columns);
        }

        $model = $this->getModel();
        dd($model);
        $tagName = $model->getCacheTagName();
        if (empty(static::$staticCache[$tagName])) {
        	//$model::class::cacheAll();
        }

        return parent::get($columns);
    }

    /*
     * Figures out if the current query results could be retrieved from the cache.
     *
     * - simple select expression
     * - simple where conditions
     * - no JOINs
     * - no UNIONs
     */
    protected function isBasicQuery() {
        return false;
    }
}