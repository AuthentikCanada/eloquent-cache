<?php
namespace Tests\Models;

use Authentik\EloquentCache\Cacheable;
use Orchestra\Testbench\TestCase;
use Illuminate\Database\Eloquent\Model;

class Category extends Model {
	protected $table = 'category';

	use Cacheable;
}