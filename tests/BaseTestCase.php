<?php
namespace Tests;

use Orchestra\Testbench\TestCase;
use Orchestra\Database\ConsoleServiceProvider;
use Illuminate\Database\Eloquent\Model;

class BaseTestCase extends TestCase
{
    public function setUp() {
        parent::setUp();

        $this->withFactories(__DIR__ . '/database/factories');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
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
}