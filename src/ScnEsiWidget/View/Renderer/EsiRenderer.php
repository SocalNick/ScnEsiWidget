<?php

namespace ScnEsiWidget\View\Renderer;

use Zend\View\Renderer\PhpRenderer;

class EsiRenderer extends PhpRenderer
{

    /**
     * @var string
     */
    protected $__routeName;

    /**
     * @var array
     */
    protected $__routeParams = array();

    /**
     * @var boolean
     */
    protected $__hasParent;

    /**
     * @param string $routeName
     */
    public function setRouteName($routeName)
    {
        $this->__routeName = $routeName;
    }

    /**
     * @param array|null $routeParams
     */
    public function setRouteParams($routeParams)
    {
        if (is_array($routeParams)) {
            $this->__routeParams = $routeParams;
        }
    }

    /**
     * @param boolean $hasParent
     */
    public function sethas_parent($hasParent)
    {
        $this->__hasParent = (boolean) $hasParent;
    }

    /**
     * Overriding to reset local params each time we render
     *
     * @param string|Model $nameOrModel Either the template to use, or a
     *                                   ViewModel. The ViewModel must have the
     *                                   template as an option in order to be
     *                                   valid.
     * @param  null|array|Traversable Values to use when rendering. If none
     *                                provided, uses those in the composed
     *                                variables container.
     * @return string                    The script output.
     * @throws Exception\DomainException if a ViewModel is passed, but does not
     *                                   contain a template option.
     * @throws Exception\InvalidArgumentException if the values passed are not
     *                                            an array or ArrayAccess object
     */
    public function render($nameOrModel, $values = null)
    {
        $this->__routeName = null;
        $this->__routeParams = array();
        $this->__hasParent = null;
        $return = parent::render($nameOrModel, $values);
        if ($this->__hasParent && $this->__routeName) {
            $url = $this->url($this->__routeName, $this->__routeParams);
            $return = "<esi:include src=\"$url\" onerror=\"continue\" />\n";
        }

        return $return;
    }
}
