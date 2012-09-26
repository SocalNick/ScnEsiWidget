<?php

namespace ScnEsiWidget\Options;

use Zend\Stdlib\AbstractOptions;

class ModuleOptions extends AbstractOptions
{
    protected $controllerRoutes = array();

    protected $actionRoutes = array();

    public function setControllerRoutes(array $controllerRoutes)
    {
        $this->controllerRoutes = $controllerRoutes;
        return $this;
    }

    public function getControllerRoutes()
    {
        return $this->controllerRoutes;
    }

    public function setActionRoutes(array $actionRoutes)
    {
        $this->actionRoutes = $actionRoutes;
        return $this;
    }

    public function getActionRoutes()
    {
        return $this->actionRoutes;
    }
}
