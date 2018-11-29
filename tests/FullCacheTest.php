<?php
namespace Tests;

use Tests\Models\CustomCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FullCacheTest extends BaseTestCase
{
    public function setUp() {
        parent::setUp();

        $GLOBALS['cache_busting'] = true;
        $GLOBALS['full_cache'] = true;

        factory(CustomCategory::class, 20)->create();
    }

    public function testCacheAll() {
        $model = new CustomCategory;

        for ($i = 1; $i <= 20; $i++) {
            $this->assertNull($this->getCachedInstance($model, $i));
        }

        CustomCategory::cacheAll();

        for ($i = 1; $i <= 20; $i++) {
            $this->assertNotNull($this->getCachedInstance($model, $i));
        }
    }

    public function testCacheHit() {
        // sql comparisons should be executed in php using the cached collection
    }

    public function testCacheMiss() {
        // if the query contains UNIONS, JOINS, or complex expressions within SELECTS
        // we will just execute the query ...
    }
}