<?php
return array(
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
