<?php
namespace Tests\Models;

use Authentik\EloquentCache\Cacheable;
use Illuminate\Database\Eloquent\Model;

class CustomCategory extends Model {
	protected $table = 'category';

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
}