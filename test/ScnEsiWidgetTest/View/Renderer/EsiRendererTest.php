<?php

namespace ScnEsiWidgetTest\View\Renderer;

use ScnEsiWidget\View\Renderer\EsiRenderer;

class EsiRendererTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ScnEsiWidget\View\Renderer\EsiRenderer
     */
    protected $renderer;

    public function setUp()
    {
        $this->renderer = new EsiRenderer;
    }

    public function tearDown()
    {
        \Mockery::close();
    }

    public function testSetters()
    {
        $routeName = new \ReflectionProperty('ScnEsiWidget\View\Renderer\EsiRenderer', '__routeName');
        $routeParams = new \ReflectionProperty('ScnEsiWidget\View\Renderer\EsiRenderer', '__routeParams');
        $hasParent = new \ReflectionProperty('ScnEsiWidget\View\Renderer\EsiRenderer', '__hasParent');
        $routeName->setAccessible(true);
        $routeParams->setAccessible(true);
        $hasParent->setAccessible(true);

        // defaults
        $this->assertNull($routeName->getValue($this->renderer));
        $this->assertEmpty($routeParams->getValue($this->renderer));
        $this->assertNull($hasParent->getValue($this->renderer));

        // setters
        $this->renderer->setRouteName('foo');
        $this->renderer->setRouteParams(array('foo'=>'bar'));
        $this->renderer->sethas_parent(true);

        $this->assertEquals('foo', $routeName->getValue($this->renderer));
        $this->assertArrayHasKey('foo', $routeParams->getValue($this->renderer));
        $this->assertTrue($hasParent->getValue($this->renderer));
    }

    public function testRenderDefault()
    {
        $renderer = \Mockery::mock($this->renderer);
        $renderer->shouldReceive('url')->never();
        $renderer->resolver()->addPath(dirname(__DIR__) . '/_templates');
        $test = $renderer->render('test.phtml');
    }

    public function testRenderEmpty()
    {
        $renderer = \Mockery::mock($this->renderer);
        $renderer->shouldReceive('url')->never();
        $renderer->resolver()->addPath(dirname(__DIR__) . '/_templates');
        $viewModel = new \Zend\View\Model\ViewModel();
        $viewModel->setTemplate('empty.phtml');
        $viewModel->setOption('routeName', 'foo');
        $viewModel->setOption('has_parent', true);
        $test = $renderer->render('empty.phtml');
        $this->assertEmpty($test);
    }

    public function testRender()
    {
        $renderer = \Mockery::mock('ScnEsiWidget\View\Renderer\EsiRenderer[url]');
        $renderer
            ->shouldReceive('url')
            ->with('foo', array())
            ->once()
            ->andReturn('bar');
        $renderer->resolver()->addPath(dirname(__DIR__) . '/_templates');
        $viewModel = new \Zend\View\Model\ViewModel();
        $viewModel->setTemplate('test.phtml');
        $viewModel->setOption('routeName', 'foo');
        $viewModel->setOption('has_parent', true);
        $test = $renderer->render($viewModel);
        $this->assertEquals("<esi:include src=\"bar\" onerror=\"continue\" />\n", $test);
    }
}
