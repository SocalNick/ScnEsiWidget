<?php

namespace ScnEsiWidget\Mvc\Controller\Plugin;

use ScnEsiWidget\Options\ModuleOptions;
use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\EventManager;
use Zend\Http\Request;
use Zend\Http\PhpEnvironment\Response as HttpResponse;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Exception;
use Zend\Mvc\InjectApplicationEventInterface;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\RouteMatch;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ServiceManagerAwareInterface;
use Zend\Stdlib\Parameters;
use Zend\View\Model\ViewModel;

class EsiWidget extends AbstractPlugin implements ServiceManagerAwareInterface
{
    /**
     * @var ModuleOptions
     */
    protected $options;

    /**
     * @var bool
     */
    protected $surrogateCapability = false;

    /**
     * @var EventInterface
     */
    protected $event;

    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * @var ServiceLocatorInterface
     */
    protected $locator;

    /**
     * Retrieve service manager instance
     *
     * @return ServiceManager
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }

    /**
     * Set service manager instance
     *
     * @param ServiceManager $serviceManager
     * @return EsiWidget
     */
    public function setServiceManager(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;

        return $this;
    }

    /**
     * set options
     *
     * @param ModuleOptions $options
     * @return EsiWidget
     */
    public function setOptions(ModuleOptions $options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * get options
     *
     * @return ModuleOptions
     */
    public function getOptions()
    {
        if (!$this->options instanceof ModuleOptions) {
            $this->setOptions($this->getServiceManager()->get('ScnEsiWidget-ModuleOptions'));
        }

        return $this->options;
    }

    /**
     *
     * @param bool $surrogateCapability
     * @return \ScnEsiWidget\Mvc\Controller\Plugin\EsiWidget
     */
    public function setSurrogateCapability($surrogateCapability = true)
    {
        $this->surrogateCapability = (bool) $surrogateCapability;

        return $this;
    }

    /**
     * Adds the result of a forward()->dispatch() to the passed in ViewModel as a child
     * Route name and params are added to the ViewModel to be used later when rendering ESI tag
     *
     * @param  ViewModel       $viewModel
     * @param  string          $routeName
     * @param  string          $controller
     * @param  string          $action
     * @param  string          $captureTo
     * @param  array           $routeParams
     * @param  array           $params
     * @return mixed|ViewModel
     */
    public function addToViewModel(ViewModel $viewModel, $request, $captureTo = null)
    {
        $event = clone($this->getEvent());
        $locator = $this->getLocator();

        // Set the request in the MvcEvent for this widget
        if (is_string($request)) {
            $uri = $request;
            $request = new Request();
            $request->setUri($uri);
            $request->setQuery(new Parameters($request->getUri()->getQueryAsArray()));
        }

        if (!$request instanceof Request) {
            throw new Exception\DomainException(
                'EsiWidget::addToViewModel expects Zend\Http\Request object or string as 2nd param'
            );
        }

        $event->setRequest($request);
        $result = $this->getEventManager()->trigger(MvcEvent::EVENT_ROUTE, $event);

        $routeMatch = $event->getRouteMatch();
        if (!$routeMatch instanceof RouteMatch) {
            // might not want to throw exception, just return view model with error
            throw new Exception\DomainException('Could not find a route for specified request');
        }

        // Set the response in the MvcEvent to a new empty response
        $event->setResponse(new HttpResponse());

        if ($this->surrogateCapability) {
            // Trigger an event announcing "ESI Mode Start"
            $result = $this->getEventManager()->trigger('esi-mode.start', $this);
        }

        // Use the standard ZF2 forward plugin to dispatch
        $return = $this->getController()->forward()->dispatch(
            $routeMatch->getParam('controller'),
            $routeMatch->getParams()
        );

        if ($this->surrogateCapability) {
            // Trigger an event announcing "ESI Mode Stop"
            $result = $this->getEventManager()->trigger('esi-mode.stop', $this);
        }

        if ($return instanceof ViewModel) {
            $routeName = $routeMatch->getMatchedRouteName();
            $routeParams = $routeMatch->getParams();

            // Use the original :controller as the route param
            if (isset($routeParams[ModuleRouteListener::ORIGINAL_CONTROLLER])) {
                $routeParams['controller'] = $routeParams[ModuleRouteListener::ORIGINAL_CONTROLLER];
                unset($routeParams[ModuleRouteListener::ORIGINAL_CONTROLLER]);
            }

            // The routes that need the :controller parameter are configurable
            // If the route isn't in the list of Controller Routes, remove the param
            if (!in_array($routeName, $this->getOptions()->getControllerRoutes())) {
                unset($routeParams['controller']);
            }

            // The routes that need the :action parameter are configurable
            // If the route isn't in the list of Action Routes, remove the param
            if (!in_array($routeName, $this->getOptions()->getActionRoutes())) {
                unset($routeParams['action']);
            }

            $return->setOption('RouteName', $routeName);
            $return->setOption('RouteParams', $routeParams);
            $return->setTerminal(false);
            $viewModel->addChild($return, $captureTo);
        }

        return $return;
    }

    /**
     * Get the locator
     *
     * @return ServiceLocatorInterface
     * @throws Exception\DomainException if unable to find locator
     */
    protected function getLocator()
    {
        if ($this->locator) {
            return $this->locator;
        }

        $controller = $this->getController();

        if (!$controller instanceof ServiceLocatorAwareInterface) {
            throw new Exception\DomainException(
                'EsiWidget plugin requires controller implements ServiceLocatorAwareInterface'
            );
        }
        $locator = $controller->getServiceLocator();
        if (!$locator instanceof ServiceLocatorInterface) {
            throw new Exception\DomainException('Forward plugin requires controller composes Locator');
        }
        $this->locator = $locator;

        return $this->locator;
    }

    /**
     * Get the event
     *
     * @return MvcEvent
     * @throws Exception\DomainException if unable to find event
     */
    protected function getEvent()
    {
        if ($this->event) {
            return $this->event;
        }

        $controller = $this->getController();
        if (!$controller instanceof InjectApplicationEventInterface) {
            throw new Exception\DomainException(
                'EsiWidget plugin requires a controller that implements InjectApplicationEvent'
            );
        }

        $event = $controller->getEvent();
        if (!$event instanceof MvcEvent) {
            $params = $event->getParams();
            $event  = new MvcEvent();
            $event->setParams($params);
        }
        $this->event = $event;

        return $this->event;
    }

    /**
     * Set the event manager instance used by this context
     *
     * @param  EventManagerInterface $events
     * @return Application
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $this->events = $events;

        return $this;
    }

    /**
     * Retrieve the event manager
     *
     * Lazy-loads an EventManager instance if none registered.
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (!$this->events instanceof EventManagerInterface) {
            $this->setEventManager(new EventManager(array(__CLASS__, get_class($this))));
        }

        return $this->events;
    }
}
