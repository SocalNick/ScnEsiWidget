<?php

namespace ScnEsiWidget;

use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
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
            'invokables' => array(
                'ScnEsiWidget\Mvc\Controller\Plugin\EsiWidget' => 'ScnEsiWidget\Mvc\Controller\Plugin\EsiWidget',
            ),
            'factories' => array(
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

    public function onBootstrap(MvcEvent $e)
    {
        $app = $e->getApplication();
        $serviceManager = $app->getServiceManager();

        $headers = $app->getRequest()->getHeaders();
        $surrogateCapability = true;
        if (
                $headers->has('surrogate-capability')
                && false !== strpos($headers->get('surrogate-capability')->getFieldValue(), 'ESI/1.0')
        ) {
            $surrogateCapability = true;
        }

        if ($surrogateCapability) {
            $controllerPluginBroker = $serviceManager->get('ControllerPluginBroker');
            $esiWidgetPlugin = $controllerPluginBroker->get('esiWidget');
            $esiWidgetPlugin->setSurrogateCapability(true);
            $esiViewStrategy = $serviceManager->get('ScnEsiWidget\View\Strategy\EsiStrategy');
            $esiViewStrategy->setSurrogateCapability(true);
        }
    }
}

