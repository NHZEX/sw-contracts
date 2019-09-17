<?php

namespace think\tests;

use Mockery as mock;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use unzxin\zswCore\Event;
use unzxin\zswCore\Tests\Material\TestListener;
use unzxin\zswCore\Tests\Material\TestListenerSubscribe;

/**
 * Class EventTest
 * @package think\tests
 */
class EventTest extends TestCase
{
    /** @var ContainerInterface|MockInterface */
    protected $app;

    /** @var Event|MockInterface */
    protected $event;

    protected function tearDown(): void
    {
        mock::close();
    }

    protected function setUp(): void
    {
        $this->app = mock::mock(ContainerInterface::class)->makePartial();

        $this->app->shouldReceive('make')->with(ContainerInterface::class)->andReturn($this->app);
        $this->app->shouldReceive('has')->andReturnFalse();
        // $this->app->shouldReceive('get')->with('config')->andReturn($this->config);

        $this->event = new Event($this->app);
    }

    public function testBasic()
    {
        $this->event->bind(['foo' => 'baz']);

        $this->event->listen('foo', function ($bar) {
            $this->assertEquals('bar', $bar);
        });

        $this->assertTrue($this->event->hasListener('foo'));

        $this->event->trigger('baz', 'bar');

        $this->event->remove('foo');

        $this->assertFalse($this->event->hasListener('foo'));
    }

    public function testOnceEvent()
    {
        $this->event->listen('AppInit', function ($bar) {
            $this->assertEquals('bar', $bar);
            return 'foo';
        });

        $this->assertEquals('foo', $this->event->trigger('AppInit', 'bar', true));
        $this->assertEquals(['foo'], $this->event->trigger('AppInit', 'bar'));
    }

    public function testClassListener()
    {
        $listener = mock::mock("overload:SomeListener", TestListener::class);

        $listener->shouldReceive('handle')->andReturnTrue();

        $this->event->listen('some', "SomeListener");

        $this->assertTrue($this->event->until('some'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testSubscribe()
    {
        $listener = mock::mock("overload:SomeListener", TestListenerSubscribe::class);

        $listener->shouldReceive('subscribe')->andReturnUsing(function (Event $event) use ($listener) {

            $listener->shouldReceive('onBar')->once()->andReturnFalse();

            $event->listenEvents(['SomeListener::onBar' => [[$listener, 'onBar']]]);
        });

        $this->event->subscribe('SomeListener');

        $this->assertTrue($this->event->hasListener('SomeListener::onBar'));

        $this->event->trigger('SomeListener::onBar');
    }

    public function testObserve()
    {
        $listener = mock::mock("overload:SomeListener", TestListener::class);

        $listener->shouldReceive('onBar')->once();

        $this->event->observe('SomeListener', ['bar', 'foo']);

        $this->assertTrue($this->event->hasListener('bar'));

        $this->event->trigger('bar');
    }

    public function testAutoObserve()
    {
        $listener = mock::mock("overload:SomeListener", TestListener::class);

        $listener->shouldReceive('onBar')->once();

        $this->app->shouldReceive('make')->with('SomeListener')->andReturn($listener);

        $this->event->observe('SomeListener');

        $this->assertTrue($this->event->hasListener('bar'));

        $this->event->trigger('bar');
    }

    public function testWithoutEvent()
    {
        $this->event->withEvent(false);

        $this->event->listen('SomeListener', TestListener::class);

        $this->assertFalse($this->event->hasListener('SomeListener'));
    }

    public function testSwooleEvent()
    {
        $this->event->onSwooleStart(function () {
            $this->assertEquals([1, 2, 3], func_get_args());
            return true;
        });

        $this->assertTrue($this->event->trigSwooleStart([1, 2, 3], true));
    }
}
