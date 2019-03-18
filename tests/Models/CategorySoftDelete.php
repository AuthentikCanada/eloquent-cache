<?php
namespace Tests\Models;

use Authentik\EloquentCache\Cacheable;
use Illuminate\Database\Eloquent\Model;

class CategorySoftDelete extends Model {
    protected $table = 'category';

    use Cacheable;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    public function isStaticCacheEnabled() {
        return false;
    }

    public function parent() {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function hasOneParent() {
        return $this->hasOne(self::class, 'id', 'parent_id');
    }
}