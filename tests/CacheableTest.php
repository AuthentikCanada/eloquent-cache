<?php
namespace Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Cache;
use Tests\Models\{Category, CustomCategory};
use Authentik\EloquentCache\CacheQueryBuilder;
use Orchestra\Database\ConsoleServiceProvider;

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


    public function testCache() {
        $instance = Category::inRandomOrder()->first();
        $builder = $instance->newQueryWithoutScopes();

        $this->assertInstanceOf(\Authentik\EloquentCache\CacheQueryBuilder::class, $builder);
        $this->assertNull($this->invokeMethod($builder, 'getCachedInstance', [$instance->id]));


        // Cache the instance
        $instance->cache();
        $this->assertNotNull($this->invokeMethod($builder, 'getCachedInstance', [$instance->id]));
        $this->assertArrayNotHasKey('category', CacheQueryBuilder::$staticCache);

        $cachedInstance = $this->invokeMethod($builder, 'getCachedInstance', [$instance->id]);
        $this->assertTrue($cachedInstance->is($instance));
        $this->assertEquals($instance->toArray(), $cachedInstance->toArray());

        $this->assertTrue($cachedInstance->exists);
    }


    public function testFlush() {
        $ids = [1, 5];

        $instances = Category::find($ids);
        $builder = $instances->first()->newQueryWithoutScopes();

        foreach ($ids as $id) {
            $this->assertNotNull($this->invokeMethod($builder, 'getCachedInstance', [$id]));
        }

        Category::flush();

        foreach ($ids as $id) {
            $this->assertNull($this->invokeMethod($builder, 'getCachedInstance', [$id]));
        }
    }


    public function testCacheBusting() {
        $instances = [
            Category::inRandomOrder()->first(),
            CustomCategory::inRandomOrder()->first()
        ];

        foreach ($instances as $instance) {
            $builder = $instance->newQueryWithoutScopes();

            $instance->cache();
            $this->assertNotNull($this->invokeMethod($builder, 'getCachedInstance', [$instance->id]));


            $instance->name .= '-suffix';
            $instance->save();
            $this->assertNull($this->invokeMethod($builder, 'getCachedInstance', [$instance->id]));


            $instance->cache();
            $this->assertNotNull($this->invokeMethod($builder, 'getCachedInstance', [$instance->id]));


            $instance->delete();
            $this->assertNull($this->invokeMethod($builder, 'getCachedInstance', [$instance->id]));
        }
    }


    public function testCustomTagNameAndTTL() {
        $ids = [3, 6];

        $instances = CustomCategory::whereIn('id', $ids)->get();
        $builder = $instances->first()->newQueryWithoutScopes();

        $this->assertArrayHasKey('custom_category', CacheQueryBuilder::$staticCache);

        foreach ($ids as $id) {
            $this->assertNotNull($this->invokeMethod($builder, 'getCachedInstance', [$id]));
            $this->assertArrayHasKey($id, CacheQueryBuilder::$staticCache['custom_category']);
        }

        CustomCategory::flush($instances->firstWhere('id', $ids[0]));

        $this->assertNull($this->invokeMethod($builder, 'getCachedInstance', [$ids[0]]));    
        $this->assertArrayNotHasKey($ids[0], CacheQueryBuilder::$staticCache['custom_category']);

        $this->assertNotNull($this->invokeMethod($builder, 'getCachedInstance', [$ids[1]]));    
        $this->assertArrayHasKey($ids[1], CacheQueryBuilder::$staticCache['custom_category']);



        CustomCategory::flush();

        foreach ($ids as $id) {
            $this->assertNull($this->invokeMethod($builder, 'getCachedInstance', [$id]));    
            $this->assertArrayNotHasKey($id, CacheQueryBuilder::$staticCache['custom_category']);
        }
    }


    public function testNoCacheBusting() {
        $GLOBALS['cache_busting'] = false;

        $instance = CustomCategory::inRandomOrder()->first();
        $builder = $instance->newQueryWithoutScopes();

        $instance->cache();
        $this->assertNotNull($this->invokeMethod($builder, 'getCachedInstance', [$instance->id]));


        $instance->name .= '-suffix';
        $instance->save();
        $this->assertNotNull($this->invokeMethod($builder, 'getCachedInstance', [$instance->id]));


        CustomCategory::flush($instance);
        $this->assertNull($this->invokeMethod($builder, 'getCachedInstance', [$instance->id]));

        $instance->cache();
        $this->assertNotNull($this->invokeMethod($builder, 'getCachedInstance', [$instance->id]));

        $instance->delete();
        $this->assertNotNull($this->invokeMethod($builder, 'getCachedInstance', [$instance->id]));
    }

    public function testNonExistingInstances() {
        $instance = Category::find(2)->refresh();
        $builder = $instance->newQueryWithoutScopes();
        $this->assertNotNull($this->invokeMethod($builder, 'getCachedInstance', [2]));


        Category::find(2);
        $this->assertNotNull($this->invokeMethod($builder, 'getCachedInstance', [2]));

        $instance = Category::find(99);
        $this->assertNull($instance);
        $this->assertNull($this->invokeMethod($builder, 'getCachedInstance', [99]));

        Category::flush();

        $instances = Category::find([2, 0, 99]);
        $this->assertEquals(1, $instances->count());
        $this->assertEquals(2, $instances->first()->id);

        $this->assertNotNull($this->invokeMethod($builder, 'getCachedInstance', [2]));
        $this->assertNull($this->invokeMethod($builder, 'getCachedInstance', [0]));
        $this->assertNull($this->invokeMethod($builder, 'getCachedInstance', [99]));
    }

    public function testRelation() {
        $instance = Category::first();
        $builder = $instance->newQueryWithoutScopes();

        $instance->parent_id = 20;
        $instance = $instance->parent;

        $this->assertNotNull($this->invokeMethod($builder, 'getCachedInstance', [$instance->id]));

        $cachedInstance = $this->invokeMethod($builder, 'getCachedInstance', [$instance->id]);

        $this->assertInstanceOf(Category::class, $instance);
        $this->assertInstanceOf(Category::class, $cachedInstance);

        $this->assertEquals(20, $instance->id);
        $this->assertEquals(20, $cachedInstance->id);
    }
}