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
        if ($model->isStaticCacheEnabled() && !isset(static::$staticCache[$tagName])) {
            static::$staticCache[$tagName] = [];
        }
    }

    public function get($columns = ['*']) {

        if (!$this->isBasicSelect($columns)) {
            return parent::get($columns);
        }

        if (!$this->isBasicQuery()) {
            $results = parent::get($columns);

            foreach ($results as $result) {
                $result->cache();
            }
            
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
                });

                if (!$notFoundInstances->isEmpty()) {
                    $results = $results->merge($notFoundInstances);
                }
            }

            return $results;
        }

        // @codeCoverageIgnoreStart
        return parent::get($columns);
        // @codeCoverageIgnoreEnd
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

        return isset($w['column']) &&
            ($w['column'] == $keyName || $w['column'] == $table.'.'.$keyName) &&
            ($w['type'] == 'In' || ($w['type'] == 'Basic' && $w['operator'] == '='));
    }


    /*
     * Figures out if ONLY all the columns of the main model are selected
     */
    protected function isBasicSelect($columns) {
        $selectedColumns = $this->getQuery()->columns ?: $columns;

        $table = $this->getModel()->getTable();

        return count($selectedColumns) == 1 &&
            in_array($selectedColumns[0], ['*', $table.'.*']);
    }


    /*
     * Retrieves a model instance from the cache, by ID.
     */
    protected function getCachedInstance($keyValue) {
        $model = $this->getModel();

        $tagName = $model->getCacheTagName();

        if ($model->isStaticCacheEnabled()) {
            $keyName = $model->getKeyName();

            if (isset(static::$staticCache[$tagName][$model->{$keyName}])) {
                return static::$staticCache[$tagName][$model->{$keyName}];
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
        $results = parent::get(['*']);

        return $results->count() > 0 ? $results->first() : null;
    }
}