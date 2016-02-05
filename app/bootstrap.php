<?php
use Magento\Framework\Autoload\AutoloaderRegistry;
use Magento\Framework\Autoload\ClassLoaderWrapper;
use Magento\Framework\App\State as State;

umask(0);
error_reporting(E_ALL);
date_default_timezone_set('UTC');

/**
 * Shortcut constant for the root directory
 */
define('BP', dirname(__DIR__));

$autoloader = require BP . '/vendor/autoload.php';
AutoloaderRegistry::registerAutoloader(new ClassLoaderWrapper($autoloader));

// Sets default autoload mappings, may be overridden in Bootstrap::create
\Magento\Framework\App\Bootstrap::populateAutoloader(BP, []);

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, [State::PARAM_MODE => State::MODE_DEVELOPER]);

return $bootstrap;
