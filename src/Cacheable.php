<?php

namespace Authentik\EloquentCache;

use Illuminate\Support\Facades\Cache;

trait Cacheable {
    public function getCacheTTL() {
        return 0;
    }


    public function getCacheTagName() {
        return strtolower((new \ReflectionClass($this))->getShortName());
    }

    public function isCacheEnabled() {
        return true;
    }


    public function isCacheBustingEnabled() {
        return true;
    }

    /**
     * @codeCoverageIgnore
     */
    public function isStaticCacheEnabled() {
        return true;
    }


    public static function boot() {
        static::saved(function ($model) {
            if ($model->isCacheEnabled() && $model->isCacheBustingEnabled()) {
                self::flush($model);
            }
        });

        static::deleted(function ($model) {
            if ($model->isCacheEnabled() && $model->isCacheBustingEnabled()) {
                self::flush($model);
            }
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


    public function cache() {
        if (!$this->isCacheEnabled()) {
            return;
        }

        $keyName = $this->getKeyName();
        $keyValue = $this->getCurrentConnection()->getName().'_'.$this->{$keyName};

        $tagName = $this->getCacheTagName();

        if ($this->getCacheTTL() > 0) {

            Cache::tags($tagName)
                ->remember($keyValue, $this->getCacheTTL(), function () {
                    return $this->attributesCacheToArray();
                });

        } else {

            Cache::tags($tagName)
                ->rememberForever($keyValue, function () {
                    return $this->attributesCacheToArray();
                });
        }

        if ($this->isStaticCacheEnabled()) {
            CacheQueryBuilder::$staticCache[$tagName][$keyValue] = $this;
        }
    }


    protected function attributesCacheToArray()
    {
        $attributes = $this->attributesToArray();

        $appends = $this->getArrayableAppends();

        foreach (array_keys($appends) as $key) {
            unset($attributes[$key]);
        }

        return $attributes;
    }


    public function refresh() {
        self::flush($this);

        return parent::refresh();
    }


    public static function flush($model = null) {
        $deleteAll = is_null($model);

        $model = $model ?? new static;
        if (!$model->isCacheEnabled()) {
            return;
        }

        $tagName = $model->getCacheTagName();

        if ($deleteAll) {
            Cache::tags($tagName)->flush();

            if ($model->isStaticCacheEnabled()) {
                CacheQueryBuilder::$staticCache[$tagName] = [];
            }

        } else {

            $keyName = $model->getKeyName();
            $keyValue = $model->getCurrentConnection()->getName().'_'.$model->{$keyName};


            Cache::tags($tagName)->forget($keyValue);

            if ($model->isStaticCacheEnabled()) {
                if (isset(CacheQueryBuilder::$staticCache[$tagName][$keyValue])) {
                    unset(CacheQueryBuilder::$staticCache[$tagName][$keyValue]);
                }
            }
        }
    }
}