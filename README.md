ScnEsiWidget
============

Enables ZF2 apps to output ESI tags for a widgetized and highly cacheable application.

[![Build Status](https://travis-ci.org/SocalNick/ScnEsiWidget.png?branch=master)](https://travis-ci.org/SocalNick/ScnEsiWidget)

Requirements
------------
* [Zend Framework 2](https://github.com/zendframework/zf2) (2.*)

Features
--------
* ESI Widgets are added to an action via EsiWidget Controller Plugin
* EsiStrategy detects Surrogate Capability to choose EsiRenderer
* EsiRenderer renders child view models as ESI tags
* Falls back to PHPRenderer w/o Surrogate Capability i.e. works in dev!

Installation
------------
It is recommended to add this module to your Zend Framework 2 application using Composer. After cloning [ZendSkeletonApplication](https://github.com/zendframework/ZendSkeletonApplication), add "socalnick/scn-esi-widget" to list of requirements, then run php composer.phar install/update. Your composer.json should look something like this:
```
{
    "name": "zendframework/skeleton-application",
    "description": "Skeleton Application for ZF2",
    "license": "BSD-3-Clause",
    "keywords": [
        "framework",
        "zf2"
    ],
    "homepage": "http://framework.zend.com/",
    "require": {
        "php": ">=5.3.3",
        "zendframework/zendframework": "2.*",
        "socalnick/scn-esi-widget": "1.*"
    }
}
```

Next add the required modules to config/application.config.php:
```php
<?php
return array(
    'modules' => array(
        'Application',
        'ScnEsiWidget',
    ),
    'module_listener_options' => array(
        'config_glob_paths'    => array(
            'config/autoload/{,*.}{global,local}.php',
        ),
        'module_paths' => array(
            './module',
            './vendor',
        ),
    ),
);
```

Varnish
-------

### Installation

Varnish can be installed on any modern Linux distribution: https://www.varnish-cache.org/docs/3.0/installation/ It is also available via [Homebrew](http://mxcl.github.com/homebrew/) on Mac OSX for development by running *brew install varnish*

### Configuration

This is the most basic Varnish configuration for a development environment. It sets the backend host / port, sets a request header indicating Surrogate Capability, and looks for the response Surrogate Control header to initiate ESI handling. Before running Varnish in a production environment, I highly encourage you to learn more about it at https://www.varnish-cache.org/docs

```
backend default {
    .host = "127.0.0.1";
    .port = "10088";
}

sub vcl_recv {
    # Set a header announcing Surrogate Capability to the origin
    # ScnEsiWidget sees this header and emits ESI tag for widgets
    set req.http.Surrogate-Capability = "varnish=ESI/1.0";
}

sub vcl_fetch {
    # Unset the Surrogate Control header and do ESI
    if (beresp.http.Surrogate-Control ~ "ESI/1.0") {
        unset beresp.http.Surrogate-Control;
        set beresp.do_esi = true;
    }
}
```

Usage
-----

### Call ESI Widget Controller Plugin

```php
public function esiAction()
{
    $viewModel = new ViewModel();
    $this->esiWidget()->addToViewModel($viewModel, '/application/index/recent-tweets', 'recentTweets');

    $headers = $this->getResponse()->getHeaders();
    $cacheControl = new \Zend\Http\Header\CacheControl();
    $cacheControl->addDirective('s-maxage', '60');
    $headers->addHeader($cacheControl);

    return $viewModel;
}
```

### Echo ESI Widget in View Script

```
<div><?php echo $this->recentTweets ?></div>
```

### Make the ESI Widget Action

```php
public function recentTweetsAction()
{
    $headers = $this->getResponse()->getHeaders();
    $cacheControl = new \Zend\Http\Header\CacheControl();
    $cacheControl->addDirective('s-maxage', '10');
    $headers->addHeader($cacheControl);

    $viewModel = new ViewModel();
    $viewModel->setTerminal(true);

    return $viewModel;
}
```

### Make a View Script for ESI Widget Action

```
<ul>
    <li><?php echo date('h:i:s')?> @SocalNick: This is a recent tweet!</li>
    <li><?php echo date('h:i:s', time() - 10)?> @SocalNick: This is a slightly less recent tweet!</li>
</ul>
```

