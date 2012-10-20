<?php

namespace ScnEsiWidget;

use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;

class Module implements AutoloaderProviderInterface, ConfigProviderInterface
{
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getServiceConfig()
    {
        return array(
            'factories' => array(
                'ScnEsiWidget-ModuleOptions' => function ($sm) {
                    $config = $sm->get('Configuration');

                    return new Options\ModuleOptions(
                        isset($config['scn-esi-widget']) ? $config['scn-esi-widget'] : array()
                    );
                },
                'ScnEsiWidget\View\Renderer\EsiRenderer' => function ($sm) {
                    $renderer =  new View\Renderer\EsiRenderer();
                    $renderer->setHelperPluginManager($sm->get('ViewHelperManager'));
                    $renderer->setResolver($sm->get('ViewResolver'));

                    return $renderer;
                },
                'ScnEsiWidget\View\Strategy\EsiStrategy' => function ($sm) {
                    return new View\Strategy\EsiStrategy($sm->get('ScnEsiWidget\View\Renderer\EsiRenderer'));
                },
            ),
        );
    }

    public function getControllerPluginConfig()
    {
        return array(
            'factories' => array(
                'esiWidget' => function ($sm) {
                    $moduleOptions = $sm->getServiceLocator()->get('ScnEsiWidget-ModuleOptions');
                    $plugin = new Mvc\Controller\Plugin\EsiWidget();
                    $plugin->setOptions($moduleOptions);

                    return $plugin;
                },
            ),
        );
    }

    public function onBootstrap(MvcEvent $e)
    {
        $app = $e->getApplication();
        $serviceManager = $app->getServiceManager();

        $headers = $app->getRequest()->getHeaders();
        $surrogateCapability = false;
        if (
                $headers->has('surrogate-capability')
                && false !== strpos($headers->get('surrogate-capability')->getFieldValue(), 'ESI/1.0')
        ) {
            $surrogateCapability = true;
        }

        $controllerPluginBroker = $serviceManager->get('ControllerPluginManager');
        $esiWidgetPlugin = $controllerPluginBroker->get('esiWidget');
        $esiWidgetPlugin->getEventManager()->attach($serviceManager->get('RouteListener'));

        //TODO: Can this be obtained from SM?
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($esiWidgetPlugin->getEventManager());

        if ($surrogateCapability) {
            $esiWidgetPlugin->setSurrogateCapability(true);
            $esiViewStrategy = $serviceManager->get('ScnEsiWidget\View\Strategy\EsiStrategy');
            $esiViewStrategy->setSurrogateCapability(true);
        }
    }
}

