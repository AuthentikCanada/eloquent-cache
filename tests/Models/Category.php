<?php
namespace Tests\Models;

use Authentik\EloquentCache\Cacheable;
use Illuminate\Database\Eloquent\Model;

class Category extends Model {
	protected $table = 'category';

	use Cacheable;

	public function isStaticCacheEnabled() {
        return false;
    }

    public function parent() {
        return $this->belongsTo(self::class, 'parent_id');
    }
}