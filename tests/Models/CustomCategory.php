<?php
namespace Tests\Models;

use Authentik\EloquentCache\Cacheable;
use Illuminate\Database\Eloquent\Model;

class CustomCategory extends Model {
    protected $table = 'category';

    public function parent() {
        return $this->belongsTo(self::class, 'parent_id');
    }

    use Cacheable;

	public function getCacheTTL() {
        return 5;
    }

    public function getCacheTagName() {
        return 'custom_category';
    }

    public function isCacheBustingEnabled() {
        return $GLOBALS['cache_busting'];
    }

    public function useFullCache() {
        return $GLOBALS['full_cache'];
    }

    public function isStaticCacheEnabled() {
        return true;
    }
}