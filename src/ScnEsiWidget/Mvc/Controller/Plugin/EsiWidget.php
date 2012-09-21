<?php

namespace ScnEsiWidget\Mvc\Controller\Plugin;

use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\EventManager;
use Zend\Http\Request;
use Zend\Http\PhpEnvironment\Response as HttpResponse;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Exception;
use Zend\Mvc\InjectApplicationEventInterface;
use Zend\Mvc\LocatorAwareInterface;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\RouteMatch;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Stdlib\Parameters;
use Zend\View\Model\ViewModel;

class EsiWidget extends AbstractPlugin
{

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
        $sharedEvents = $locator->get('EventManager')->getSharedManager();

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

        // Remove the InjectViewModelListener from 'dispatch'
        $listeners = $sharedEvents->getListeners('Zend\Stdlib\DispatchableInterface', MvcEvent::EVENT_DISPATCH);
        $injectViewModelListener = false;
        foreach ($listeners as $listener) {
            $callback = $listener->getCallback();
            if (is_array($callback)) {
                if (isset($callback[0])) {
                    if ($callback[0] instanceof \Zend\Mvc\View\Http\InjectViewModelListener) {
                        $injectViewModelListener = $callback[0];
                        $sharedEvents->detach('Zend\Stdlib\DispatchableInterface', $listener);
                    }
                }
            }
        }

        // Temporarily replace the response object in the event
        $response = $event->getResponse();
        $event->setResponse(new HttpResponse());

        if ($this->surrogateCapability) {
            // Trigger an event announcing "ESI Mode Start"
            $result = $this->getEventManager()->trigger('esi-mode.start', $this);
        }

        $return = $this->getController()->forward()->dispatch(
            $routeMatch->getParam('controller'),
            $routeMatch->getParams()
        );

        if ($this->surrogateCapability) {
            // Trigger an event announcing "ESI Mode Stop"
            $result = $this->getEventManager()->trigger('esi-mode.stop', $this);
        }

        // Set the original response back to the event
        $event->setResponse($response);

        // Add the InjectViewModelListener back to 'dispatch'
        if ($injectViewModelListener) {
            $sharedEvents->attach(
                'Zend\Stdlib\DispatchableInterface',
                MvcEvent::EVENT_DISPATCH,
                array($injectViewModelListener, 'injectViewModel'),
                -100
            );
        }

        if ($return instanceof ViewModel) {

            //TODO: Make this whole block smarter

            $routeName = $routeMatch->getMatchedRouteName();
            $routeParams = $routeMatch->getParams();

            // valid-renderers is not required to assemble urls for esi
            unset($routeParams['valid-renderers']);

            if (isset($routeParams[ModuleRouteListener::ORIGINAL_CONTROLLER])) {
                $routeParams['controller'] = $routeParams[ModuleRouteListener::ORIGINAL_CONTROLLER];
                unset($routeParams[ModuleRouteListener::ORIGINAL_CONTROLLER]);
            }

            // The 'default' route is the only one that has controller / action
            // Otherwise remove them
            if ('application/default' != $routeName) {
                unset($routeParams['controller'], $routeParams['action']);
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
