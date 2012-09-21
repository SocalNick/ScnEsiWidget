<?php
return array(
    'controller_plugins' => array(
        'invokables' => array(
            'esiWidget' => 'ScnEsiWidget\Mvc\Controller\Plugin\EsiWidget',
        ),
    ),
    'view_manager' => array(
        'strategies' => array(
            'ScnEsiWidget\View\Strategy\EsiStrategy',
        ),
    ),
);
