<?php

namespace Authentik\EloquentCache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CacheQueryBuilder extends Builder {
    public static $staticCache = [];

    public function __construct($query, Model $model)
    {
        parent::__construct($query);

        $tagName = $model->getCacheTagName();
        if ($model->isStaticCacheEnabled() && !isset(self::$staticCache[$tagName])) {
            self::$staticCache[$tagName] = [];
        }
    }

    public function saveStaticCache($instance) {
        if ($instance->isStaticCacheEnabled()) {
            $tagName = $instance->getCacheTagName();
            $keyName = $instance->getKeyName();

            self::$staticCache[$tagName][$instance->{$keyName}] = $instance;
        }
    }

    public function get($columns = ['*']) {
        if (count($columns) != 1 || $columns[0] != '*') {
            return parent::get($columns);
        }

        if (!$this->isBasicQuery()) {
            $results = parent::get($columns);

            // @todo: cache the results
            
            return $results;
        }
        
        $w = $this->getQuery()->wheres[0];
        $results = new Collection(null);


        if ($w['type'] == 'Basic' && $w['operator'] == '=') {

            $keyValue = $w['value'];
            $instance = $this->getCachedInstance($keyValue);

            if (!$instance) {
                if (is_null($instance = $this->getInstance($keyValue))) {
                    return $results;
                }

                $instance->cache();
                $this->saveStaticCache($instance);
            }

            $results->push($instance);
            return $results;

        } else if ($w['type'] == 'In') {

            $notFound = [];

            foreach ($w['values'] as $keyValue) {
                if (!is_null($instance = $this->getCachedInstance($keyValue))) {
                    $results->push($instance);
                } else {
                    $notFound[] = $keyValue;
                }
            }

            // search for the not-cached model instances in the DB
            if (!empty($notFound)) {

                $this->query->wheres = [];
                $this->query->bindings['where'] = [];
                $this->query->whereIn($w['column'], $notFound);

                $notFoundInstances = parent::get($columns);
                $notFoundInstances->each(function ($instance) {
                    $instance->cache();
                    $this->saveStaticCache($instance);
                });

                if (!$notFoundInstances->isEmpty()) {
                    $results = $results->merge($notFoundInstances);
                }
            }

            return $results;
        }

        return parent::get($columns);
    }


    /*
     * Figures out if the current query results can be cached.
     *
     * - FROM `table` WHERE key = x
     * - FROM `table` WHERE key IN (x1, x2)
     */
    protected function isBasicQuery() {
        $query = $this->getQuery();

        if (is_null($query->wheres) || count($query->wheres) != 1) {
            return false;
        }

        $w = $query->wheres[0];

        $model = $this->getModel();
        $table = $model->getTable();
        $keyName = $model->getKeyName();

        return in_array($w['type'], ['Basic', 'In']) && ($w['column'] == $keyName || $w['column'] == $table.'.'.$keyName);
    }


    /*
     * Retrieves a model instance from the cache, by ID.
     */
    protected function getCachedInstance($keyValue) {
        $model = $this->getModel();

        $tagName = $model->getCacheTagName();

        if ($model->isStaticCacheEnabled()) {
            $keyName = $model->getKeyName();

            if (isset(self::$staticCache[$tagName][$model->{$keyName}])) {
                return self::$staticCache[$tagName][$model->{$keyName}];
            }
        }

        $cached = Cache::tags($model->getCacheTagName())->get($keyValue);

        if ($cached) {
            $instance = $model->newInstance([], true);
            $instance = $instance->forceFill($cached);

            return $instance;
        }
        
        return null;
    }


    /*
     * Fetch a model instance by ID.
     */
    protected function getInstance($keyValue) {
        if ($keyValue == 0) {
            return null;
        }
        $results = parent::get(['*']);
        if ($results->count() == 0) {
            return null;
        }
        return $results->first();
    }
}