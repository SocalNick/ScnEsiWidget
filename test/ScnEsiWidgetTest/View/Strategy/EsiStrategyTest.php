<?php

namespace ScnEsiWidgetTest\View\Strategy;

use Mockery,
    Zend\Http\PhpEnvironment\Request,
    Zend\Http\PhpEnvironment\Response,
    Zend\Mvc\MvcEvent,
    Zend\Mvc\Router\RouteMatch,
    Zend\View\ViewEvent;

class EsiStrategyTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var ScnEsiWidget\View\Strategy\EsiStrategy
     */
    protected $strategy;

    /**
     * @var ScnEsiWidget\View\Renderer\EsiRenderer
     */
    protected $renderer;

    public function setUp()
    {
        $this->renderer = new \ScnEsiWidget\View\Renderer\EsiRenderer;
        $this->strategy = new \ScnEsiWidget\View\Strategy\EsiStrategy(
            $this->renderer
        );
    }

    public function tearDown()
    {
        \Mockery::close();
    }

    public function testAttachAttachesEvents()
    {
        $events = Mockery::mock('Zend\EventManager\EventManager');
        $events
            ->shouldReceive('attach')
            ->with(ViewEvent::EVENT_RENDERER, array($this->strategy, 'selectRenderer'), 1)
            ->once();
        $events
            ->shouldReceive('attach')
            ->with(ViewEvent::EVENT_RESPONSE, array($this->strategy, 'injectResponse'), 1)
            ->once();
        $this->strategy->attach($events);
    }

    public function testAttachAttachesEventsWithPriority()
    {
        $events = Mockery::mock('Zend\EventManager\EventManager');
        $events
            ->shouldReceive('attach')
            ->with(
                ViewEvent::EVENT_RENDERER,
                array($this->strategy, 'selectRenderer'),
                100
            )
            ->once();
        $events
            ->shouldReceive('attach')
            ->with(
                ViewEvent::EVENT_RESPONSE,
                array($this->strategy, 'injectResponse'),
                100
            )
            ->once();
        $this->strategy->attach($events, 100);
    }

    public function testAttachAttachesEventsWithNullPriority()
    {
        $events = Mockery::mock('Zend\EventManager\EventManager');
        $events
            ->shouldReceive('attach')
            ->with(
                ViewEvent::EVENT_RENDERER,
                array($this->strategy, 'selectRenderer')
            )
            ->once();
        $events
            ->shouldReceive('attach')
            ->with(
                ViewEvent::EVENT_RESPONSE,
                array($this->strategy, 'injectResponse')
            )
            ->once();
        $this->strategy->attach($events, null);
    }

    public function testDetachDetachesEvents()
    {
        $events = \Mockery::mock('\Zend\EventManager\EventManager[detach]');
        $events->shouldReceive('detach')->andReturn(true)->times(2);

        $this->strategy->attach($events);
        $this->strategy->detach($events);
    }

    public function testSetSurrogateCapability()
    {
        $this->strategy->setSurrogateCapability();

        $surrogateCapability = new \ReflectionProperty(
            'ScnEsiWidget\View\Strategy\EsiStrategy',
            'surrogateCapability'
        );
        $surrogateCapability->setAccessible(true);
        $this->assertTrue($surrogateCapability->getValue($this->strategy));
    }

    public function testSelectRendererNotSurrogateCapable()
    {
        $e = new ViewEvent();
        $e->setRequest(new Request());
        $renderer = $this->strategy->selectRenderer($e);
        $this->assertNull($renderer);
    }

    public function testSelectRenderer()
    {
        $e = new ViewEvent();
        $e->setRequest(new Request());
        $this->strategy->setSurrogateCapability();
        $renderer = $this->strategy->selectRenderer($e);
        $this->assertInstanceOf('ScnEsiWidget\View\Renderer\EsiRenderer', $renderer);
    }

    public function testInjectResponseUnknownRenderer()
    {
        $e = new ViewEvent();
        $response = new Response();
        $e->setResponse($response);
        $e->setResult('foo');
        $this->strategy->injectResponse($e);
        $this->assertEmpty($response->getContent());
    }

    public function testInjectResponseEsiRenderer()
    {
        $e = new ViewEvent();
        $response = new Response();
        $e->setResponse($response);
        $e->setResult('foo');
        $e->setRenderer($this->renderer);
        $this->strategy->injectResponse($e);
        $this->assertEquals('foo', $response->getContent());
        $this->assertEquals("Surrogate-Control: content=\"ESI/1.0\"\r\n", $response->getHeaders()->toString());
    }
}
