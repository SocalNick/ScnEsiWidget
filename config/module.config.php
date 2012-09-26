<?php
return array(
    'controller_plugins' => array(
        'factories' => array(
            'esiWidget' => function ($sm) {
                $moduleOptions = $sm->getServiceLocator()->get('ScnEsiWidget-ModuleOptions');
                $plugin = new ScnEsiWidget\Mvc\Controller\Plugin\EsiWidget();
                $plugin->setOptions($moduleOptions);

                return $plugin;
            },
        ),
    ),
    'view_manager' => array(
        'strategies' => array(
            'ScnEsiWidget\View\Strategy\EsiStrategy',
        ),
    ),
    'scn-esi-widget' => array(
        'controllerRoutes' => array(
            'application/default',
        ),
        'actionRoutes' => array(
            'application/default',
        ),
    ),
);
