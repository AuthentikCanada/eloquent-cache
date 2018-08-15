<?php
namespace Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Cache;
use Tests\Models\Category;

class CacheableTest extends TestCase
{
	public function setUp() {
		parent::setUp();

		$this->withFactories(__DIR__ . '/database/factories');
		$this->loadMigrationsFrom(__DIR__ . '/database/migrations');

		$this->categories = factory(Category::class, 50)->create();
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

		$instance->cache();
		$this->assertNotNull($this->invokeMethod($builder, 'getCachedInstance', [$instance->id]));

		$cachedInstance = $this->invokeMethod($builder, 'getCachedInstance', [$instance->id]);
		$this->assertTrue($cachedInstance->is($instance));
		$this->assertEquals($instance->toArray(), $cachedInstance->toArray());
	}

	/*
	public function testRefresh() {
		
	}

	public function testFlush() {
		
	}
	*/
}