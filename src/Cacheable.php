<?php

namespace Authentik\EloquentCache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\SoftDeletes;

trait Cacheable {
    public function getCacheTTL() {
        return 0;
    }


    public function getCacheTagName() {
        return strtolower((new \ReflectionClass($this))->getShortName());
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

    public function useFullCache() {
        return false;
    }


    public static function boot() {
        static::saved(function ($model) {
            if ($model->isCacheBustingEnabled()) {
                self::flush($model);
            }
        });

        static::deleted(function ($model) {
            if ($model->isCacheBustingEnabled()) {
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
        if ($this->useFullCache()) {
            return new FullCacheQueryBuilder($query, $this);
        }

        return new PartialCacheQueryBuilder($query, $this);
    }

    public static function cacheAll()
    {
        static::query()
            ->when(in_array(SoftDeletes::class, array_keys(class_uses(static::class))), function ($query) {
                return $query->withTrashed();
            })
            ->withoutGlobalScopes()
            ->get()
            ->each->cache();
    }


    public function cache() {
        $keyName = $this->getKeyName();
        $keyValue = $this->{$keyName};

        $tagName = $this->getCacheTagName();

        if (!$this->useFullCache()) {
            if ($this->getCacheTTL() > 0) {

                Cache::tags($tagName)
                    ->remember($keyValue, $this->getCacheTTL(), function () {
                        return $this->attributesToArray();
                    });

            } else {

                Cache::tags($tagName)
                    ->rememberForever($keyValue, function () {
                        return $this->attributesToArray();
                    });
            }
        }

        if ($this->isStaticCacheEnabled()) {
            PartialCacheQueryBuilder::$staticCache[$tagName][$keyValue] = $this;
        }
    }


    public function refresh() {
        self::flush($this);

        return parent::refresh();
    }


    public static function flush($model = null) {
        // todo: handle full cache ...
        
        if (is_null($model)) {
            $model = new static;
            $tagName = $model->getCacheTagName();

            Cache::tags($tagName)->flush();

            if ($model->isStaticCacheEnabled()) {
                PartialCacheQueryBuilder::$staticCache[$tagName] = [];
            }

        } else {
            $tagName = $model->getCacheTagName();

            $keyName = $model->getKeyName();
            $keyValue = $model->{$keyName};

            Cache::tags($tagName)->forget($keyValue);

            if ($model->isStaticCacheEnabled()) {
                if (isset(PartialCacheQueryBuilder::$staticCache[$tagName][$keyValue])) {
                    unset(PartialCacheQueryBuilder::$staticCache[$tagName][$keyValue]);
                }
            }
        }
    }
}