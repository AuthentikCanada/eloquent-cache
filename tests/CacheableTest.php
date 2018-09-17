<?php
namespace Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Cache;
use Tests\Models\{Category, CustomCategory, Product};
use Authentik\EloquentCache\CacheQueryBuilder;
use Orchestra\Database\ConsoleServiceProvider;
use Illuminate\Database\Eloquent\Model;

class CacheableTest extends TestCase
{
    public function setUp() {
        parent::setUp();

        $this->withFactories(__DIR__ . '/database/factories');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        factory(Category::class, 20)->create();

        $GLOBALS['cache_busting'] = true;
    }

    protected function getPackageProviders($app)
    {
        return [
            ConsoleServiceProvider::class,
        ];
    }
    

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    protected function getCachedInstance(Model $model, $id) {
        $builder = $model->newQueryWithoutScopes();

        return $this->invokeMethod($builder, 'getCachedInstance', [$id]);
    }

    public function testCache() {
        $instance = $model = Category::first();
        $builder = $instance->newQueryWithoutScopes();

        $this->assertInstanceOf(\Authentik\EloquentCache\CacheQueryBuilder::class, $builder);
        $this->assertNotNull($this->getCachedInstance($model, $instance->id));
        $this->assertArrayNotHasKey('category', CacheQueryBuilder::$staticCache);

        $cachedInstance = $this->getCachedInstance($model, $instance->id);
        $this->assertTrue($cachedInstance->is($instance));
        $this->assertEquals($instance->toArray(), $cachedInstance->toArray());

        $this->assertTrue($cachedInstance->exists);

        // Just for code coverage purposes
        Category::find([1, 2]);
    }


    public function testFlush() {
        $ids = [1, 5];

        $instances = Category::find($ids);
        $model = $instances->first();

        foreach ($ids as $id) {
            $this->assertNotNull($this->getCachedInstance($model, $id));
        }

        Category::flush();

        foreach ($ids as $id) {
            $this->assertNull($this->getCachedInstance($model, $id));
        }
    }


    public function testCacheBusting() {
        $instances = [
            Category::inRandomOrder()->first(),
            CustomCategory::inRandomOrder()->first()
        ];

        foreach ($instances as $instance) {
            $model = $instance;

            $instance->cache();
            $this->assertNotNull($this->getCachedInstance($model, $instance->id));


            $instance->name .= '-suffix';
            $instance->save();
            $this->assertNull($this->getCachedInstance($model, $instance->id));


            $instance->cache();
            $this->assertNotNull($this->getCachedInstance($model, $instance->id));


            $instance->delete();
            $this->assertNull($this->getCachedInstance($model, $instance->id));
        }
    }


    public function testCustomTagNameAndTTL() {
        $ids = [3, 6];

        $instances = CustomCategory::whereIn('id', $ids)->get();
        $model = $instances->first();

        $this->assertArrayHasKey('custom_category', CacheQueryBuilder::$staticCache);

        foreach ($ids as $id) {
            $this->assertNotNull($this->getCachedInstance($model, $id));
            $this->assertArrayHasKey($id, CacheQueryBuilder::$staticCache['custom_category']);
        }

        CustomCategory::flush($instances->where('id', $ids[0])->first());

        $this->assertNull($this->getCachedInstance($model, $ids[0]));   
        $this->assertArrayNotHasKey($ids[0], CacheQueryBuilder::$staticCache['custom_category']);

        $this->assertNotNull($this->getCachedInstance($model, $ids[1]));   
        $this->assertArrayHasKey($ids[1], CacheQueryBuilder::$staticCache['custom_category']);



        CustomCategory::flush();

        foreach ($ids as $id) {
            $this->assertNull($this->getCachedInstance($model, $id));   
            $this->assertArrayNotHasKey($id, CacheQueryBuilder::$staticCache['custom_category']);
        }
    }


    public function testNoCacheBusting() {
        $GLOBALS['cache_busting'] = false;

        $instance = $model = CustomCategory::inRandomOrder()->first();

        $instance->cache();
        $this->assertNotNull($this->getCachedInstance($model, $instance->id));


        $instance->name .= '-suffix';
        $instance->save();
        $this->assertNotNull($this->getCachedInstance($model, $instance->id));


        CustomCategory::flush($instance);
        $this->assertNull($this->getCachedInstance($model, $instance->id));

        $instance->cache();
        $this->assertNotNull($this->getCachedInstance($model, $instance->id));

        $instance->delete();
        $this->assertNotNull($this->getCachedInstance($model, $instance->id));
    }

    public function testNonExistingInstances() {
        $instance = $model = Category::find(2)->refresh();
        $this->assertNotNull($this->getCachedInstance($model, 2));


        Category::find(2);
        $this->assertNotNull($this->getCachedInstance($model, 2));

        $this->assertNull(Category::find(99));
        $this->assertNull($this->getCachedInstance($model, 99));

        Category::flush();

        $instances = Category::find([2, 0, 99]);
        $this->assertEquals(1, $instances->count());
        $this->assertEquals(2, $instances->first()->id);

        $this->assertNotNull($this->getCachedInstance($model, 2));
        $this->assertNull($this->getCachedInstance($model, 0));
        $this->assertNull($this->getCachedInstance($model, 99));
    }

    public function testRelation() {
        $instance = $model = Category::first();

        $instance->parent_id = 20;
        $instance = $instance->parent;

        $this->assertNotNull($this->getCachedInstance($model, $instance->id));

        $cachedInstance = $this->getCachedInstance($model, $instance->id);

        $this->assertInstanceOf(Category::class, $instance);
        $this->assertInstanceOf(Category::class, $cachedInstance);

        $this->assertEquals(20, $instance->id);
        $this->assertEquals(20, $cachedInstance->id);
    }

    public function testEagerLoading() {
        $parentId = 20;

        $instance = $model = Category::find(1);

        $this->assertNotNull($this->getCachedInstance($model, $instance->id));

        $instance->parent_id = $parentId;
        $instance->save();

        $this->assertNotEquals($instance->parent_id, $instance->id);

        $this->assertNull($this->getCachedInstance($model, $instance->id));

        $instance = Category::with('parent')->find(1);

        $this->assertNotNull($this->getCachedInstance($model, $instance->id));
        $this->assertNotNull($this->getCachedInstance($model, $parentId));

        $cachedInstance = $this->getCachedInstance($model, $instance->id);

        $this->assertTrue($cachedInstance->is($instance));
    }

    public function testComplicatedQueries() {
        $model = Category::first();

        $this->assertNotNull($this->getCachedInstance($model, 1));

        Category::flush();


        Category::where('id', '<', 3)->get();

        $this->assertNotNull($this->getCachedInstance($model, 1));
        $this->assertNotNull($this->getCachedInstance($model, 2));
        $this->assertNull($this->getCachedInstance($model, 3));

        Category::flush();


        factory(Product::class)->make(['category_id' => 10])->save();
        factory(Product::class)->make(['category_id' => 20])->save();

        Category::addSelect([
            'category.*'
        ])
        ->join('product', 'product.category_id', 'category.id')
        ->get();

        $this->assertNotNull($this->getCachedInstance($model, 10));
        $this->assertNotNull($this->getCachedInstance($model, 20));

        Category::flush();


        Category::addSelect([
            'category.id',
            'product.*',
        ])
        ->join('product', 'product.category_id', 'category.id')
        ->get();

        $this->assertNull($this->getCachedInstance($model, 10));
        $this->assertNull($this->getCachedInstance($model, 20));
    }
}