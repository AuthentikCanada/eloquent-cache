<?php
namespace Tests\Models;

use Authentik\EloquentCache\Cacheable;
use Illuminate\Database\Eloquent\Model;

class Category extends Model {
	protected $table = 'category';

	public function isStaticCacheEnabled() {
        return false;
    }

    use Cacheable;

    public function parent() {
        return $this->belongsTo(self::class, 'parent_id');
    }
}