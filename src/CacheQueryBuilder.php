<?php

namespace Authentik\EloquentCache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CacheQueryBuilder extends Builder {
	public function get($columns = ['*']) {
		if (count($columns) != 1 || $columns[0] != '*') {
            return parent::get($columns);
        }

        if (count($this->eagerLoad) != 0 || !$this->isBasicQuery()) {
            return parent::get($columns);
        }
        
        $w = $this->getQuery()->wheres[0];
        $results = new Collection(null);


        if ($w['type'] == 'Basic' && $w['operator'] == '=') {

            $keyValue = $w['value'];
            $instance = $this->getCachedInstance($keyValue);

            if (!$instance) {
                if (is_null($instance = $this->getInstance())) {
                    return $results;
                }

                $instance->cache();
            }

            $results->push($instance);
            return $results;

        } else if ($w['type'] == 'In') {

            $notFound = [];

            foreach ($where['values'] as $keyValue) {
                $instance = $this->getCachedInstance($keyValue);

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
                $this->query->whereIn($where['column'], $notFound);

                $notFoundInstances = parent::get($columns);
                $notFoundInstances->each->cache();

                if (!$notFoundInstances->isEmpty()) {
                    $results = $results->merge($notFoundInstances);
                }
            }

            return $results;
        }

        return parent::get($columns);
	}

	/*
	public function update(array $values) {
		if ($this->model->cacheBusting) {
            $this->clearCacheBasedOnQuery();
	    }
        
        return parent::update($values);
	}


	public function delete() {
		if ($this->model->cacheBusting) {
            $this->clearCacheBasedOnQuery();
	    }
        
        return parent::delete();
	}


	protected function clearCacheBasedOnQuery() {
		if (!$this->isBasicQuery()) {
			return;
		}

        $w = $this->getQuery()->wheres[0];

        if ($w['type'] == 'Basic' && $w['operator'] == '=') {
            $keyValue = $w['value'];

            Cache::tags($this->model->getCacheTagName())->forget($keyValue);

        } else if ($w['type'] == 'In') {

            foreach ($w['values'] as $keyValue) {
                Cache::tags($this->model->getCacheTagName())->forget($keyValue);
            }
        }
    }
    */


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

    	$where = $query->wheres[0];

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