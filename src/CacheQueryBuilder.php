<?php

namespace Authentik\EloquentCache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CacheQueryBuilder extends Builder {
    public static $staticCache = [];
    protected $_query = null;

    public function __construct($query, Model $model)
    {
        parent::__construct($query);

        $tagName = $model->getCacheTagName();
        if ($model->isStaticCacheEnabled() && !isset(self::$staticCache[$tagName])) {
            self::$staticCache[$tagName] = [];
        }
    }

    public function get($columns = ['*']) {
        $this->_query = $this->applyScopes()->getQuery();

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

        list($wheres, $keyCondition, $nullConditions) = $this->getGroupedWhereConditions();
        $results = new EloquentCollection(null);


        if ($keyCondition['type'] == 'Basic' && $keyCondition['operator'] == '=') {

            $keyValue = $keyCondition['value'];
            $instance = $this->getCachedInstance($keyValue, $nullConditions);

            if (!$instance) {
                if (is_null($instance = $this->getInstance($keyValue))) {
                    return $results;
                }

                $instance->cache();
            }

            $results->push($instance);
            return $results;

        } else if ($keyCondition['type'] == 'In') {

            $notFound = [];

            foreach ($keyCondition['values'] as $keyValue) {
                if (!is_null($instance = $this->getCachedInstance($keyValue, $nullConditions))) {
                    $results->push($instance);
                } else {
                    $notFound[] = $keyValue;
                }
            }

            // search for the not-cached model instances in the DB
            if (!empty($notFound)) {

                $this->query->wheres = [];
                $this->query->bindings['where'] = [];
                $this->query->whereIn($keyCondition['column'], $notFound);

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


    protected function getWhereConditions()
    {
        $query = $this->_query;

        if (is_null($query->wheres)) {
            return [];
        }

        $model = $this->getModel();
        $table = $model->getTable();
        $keyName = $model->getKeyName();

        return new Collection(array_map(function($w) use ($table, $keyName) {
            if (!isset($w['column'])) {
                return ['table' => null, 'column' => null];
            }

            $where = [
                'column' => $w['column'],
                'table' => $table,
            ];

            if (strpos($where['column'], '.') !== false) {
                $arr = explode('.', $where['column']);

                $where['table'] = $arr[0];
                $where['column'] = $arr[1];
            }

            $where['type'] = $w['type'];
            $attributes = ['operator', 'value', 'values'];
            foreach ($attributes as $attribute) {
                if (isset($w[$attribute])) {
                    $where[$attribute] = $w[$attribute];
                }
            }

            return $where;
        }, $query->wheres));
    }


    /*
     * Splits the query's where conditions into 3 groups
     *
     * - ALL the wheres
     * - the condition on the primary key (in() or = condition)
     * - IS NULL/IS NOT NULL conditions on the main table's columns
     */
    protected function getGroupedWhereConditions() {
        $query = $this->_query;

        $wheres = $this->getWhereConditions();

        if ($wheres->isEmpty()) {
            return [
                new Collection(null),
                null,
                new Collection(null)
            ];
        }

        $model = $this->getModel();
        $table = $model->getTable();
        $keyName = $model->getKeyName();

        $nullConditions = $wheres->filter(function ($w) use ($table) {
            return
                !is_null($w['column']) &&
                in_array($w['type'], ['Null', 'NotNull']) &&
                $w['table'] == $table;
        })->values();

        $keyCondition = $wheres->filter(function ($w) use ($table, $keyName) {
            return
                !is_null($w['column']) &&
                $w['table'] == $table &&
                $w['column'] == $keyName &&
                ($w['type'] == 'In' || ($w['type'] == 'Basic' && $w['operator'] == '='));
        })->first();

        return [$wheres, $keyCondition, $nullConditions];
    }


    /*
     * Figures out if the current query results can be cached.
     *
     * - FROM `table` WHERE key = x
     * - FROM `table` WHERE key IN (x1, x2)
     * - AND x IS NOT or y IS NOT NULL
     */
    protected function isBasicQuery() {
        list($wheres, $keyCondition, $nullConditions) = $this->getGroupedWhereConditions();

        // Only one condition on the primary key (in() or = condition)
        // Unlimited IS NULL/IS NOT NULL conditions on the main table's columns
        // No extra conditions
        return ($keyCondition && count($nullConditions) + 1 == count($wheres));
    }


    /*
     * Figures out if ONLY all the columns of the main model are selected
     */
    protected function isBasicSelect($columns) {
        $selectedColumns = $this->_query->columns ?: $columns;

        $table = $this->getModel()->getTable();

        return count($selectedColumns) == 1 &&
            in_array($selectedColumns[0], ['*', $table.'.*']);
    }


    protected function filterNullCondition($instance, $nullConditions) {
        if (is_null($nullConditions)) {
            return $instance;
        }

        foreach ($nullConditions as $w) {
            if ($w['type'] == 'Null' && !is_null($instance->{$w['column']})) {
                return null;
            }

            if ($w['type'] == 'NotNull' && is_null($instance->{$w['column']})) {
                return null;
            }
        }

        return $instance;
    }


    /*
     * Retrieves a model instance from the cache, by ID.
     */
    protected function getCachedInstance($keyValue, $nullConditions = null) {
        $model = $this->getModel();

        $tagName = $model->getCacheTagName();

        if ($model->isStaticCacheEnabled()) {
            $keyName = $model->getKeyName();

            if (isset(self::$staticCache[$tagName][$model->{$keyName}])) {
                return $this->filterNullCondition(self::$staticCache[$tagName][$model->{$keyName}], $nullConditions);
            }
        }

        $cached = Cache::tags($model->getCacheTagName())->get($keyValue);

        if ($cached) {
            $instance = $model->newInstance([], true);
            $instance = $instance->forceFill($cached);

            return $this->filterNullCondition($instance, $nullConditions);
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