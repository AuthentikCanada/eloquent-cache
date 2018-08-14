<?php

namespace Authentik\EloquentCache;

use Illuminate\Support\Facades\Cache;

trait Cacheable {
    public $cacheTTL = 0; 
    public $cacheTagName = '';
    public $cacheBusting = true;


	public static function boot() {
		if (empty($this->cacheTagName)) {
			$this->cacheTagName = strtolower((new \ReflectionClass($this))->getShortName());
		}

		static::saved(function ($model) {
			$model->refresh();
		});

		static::deleted(function ($model) {
			$model->refresh();
		});

		parent::boot();
	}


	/**
     * Create a new Cache query builder for the model.
     */
    public function newEloquentBuilder($query)
    {
        return new CacheQueryBuilder($query, $this);
    }


    public function refresh() {
    	$keyName = $this->getKeyName();

    	Cache::tags($this->cacheTagName)->forget($this->{$keyName});

    	return parent::refresh();
    }
}