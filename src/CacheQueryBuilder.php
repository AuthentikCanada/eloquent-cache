<?php

namespace Authentik\EloquentCache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CacheQueryBuilder extends Builder {
	protected $model;

	public function __construct($query, Model $model) {
		$this->model = $model;

		parent::__construct($query);
	}


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
            $model = $this->getCachedInstance($keyValue);

            if (!$model) {
                $model = $this->getInstance();

                if (!$model) {
                    return $results;
                }

                Cache::tags($this->model->cacheTagName)->remember($keyValue, $this->model->cacheTTL, $model->toArray());
            }

            $results->push($model);

            return $results;

        } else if ($w['type'] == 'In') {

            $notFound = [];

            foreach ($where['values'] as $keyValue) {
                $model = $this->getCachedInstance($keyValue);

                if (is_null($model)) {
                    $notFound[] = $keyValue;
                } else {
                	$results->push($model);
                }
            }

            // Search for the missing models in the DB
            if (!empty($notFound)) {

                $this->query->wheres = [];
                $this->query->bindings['where'] = [];
                $this->query->whereIn($where['column'], $notFound);

                $dbResults = parent::get($columns);

                if (!$dbResults->isEmpty()) {
                    $results = $results->merge($dbResults);
                }

                foreach ($dbResults as $r) {
                    Cache::tags($this->model->cacheTagName)->remember($keyValue, $this->model->cacheTTL, $r->toArray());
                }
            }

            return $results;
        }

        return parent::get($columns);
	}


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

            Cache::tags($this->model->cacheTagName)->forget($keyValue);

        } else if ($w['type'] == 'In') {

            foreach ($w['values'] as $keyValue) {
                Cache::tags($this->model->cacheTagName)->forget($keyValue);
            }
        }
    }


    /*
     * Figures out if the current query results can be cached.
     *
     * - FROM `table` WHERE key = x
     * - FROM `table` WHERE key IN (x1, x2)
     */
    protected function isBasicQuery($query) {
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
        $cached = Cache::tags($this->model->cacheTagName)->get($keyValue);

        if ($cached) {
            $model = $this->getModel()->newInstance([], true);
            $cached = $model->forceFill($cached);
        }
        
        return $cached;
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