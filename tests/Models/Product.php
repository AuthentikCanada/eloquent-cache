<?php
namespace Tests\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model {
	protected $table = 'product';

    public function category() {
        return $this->belongsTo(Category::class, 'category_id');
    }
}