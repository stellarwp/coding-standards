<?php

/**
 * Custom configuration for PHP-CS-Fixer.
 *
 * @link https://cs.symfony.com/
 */

/**
 * First, retrieve the default configuration object.
 *
 * @var \PhpCsFixer\Config $config
 */
$config = require_once __DIR__ . '/vendor/stellarwp/coding-standards/src/php-cs-fixer.php';

/*
 * Want to override the files being checked? Modify the Finder instance:
 *
 *     $config->getFinder()
 *         ->path('my-plugin.php');
 *
 * @link https://symfony.com/doc/current/components/finder.html
 */

/*
 * Need to adjust the rules? Merge your overrides with the defaults!
 *
 *     $config->setRules(array_merge($config->getRules(), [
 *         // Your rules go here.
 *     ]));
 *
 * @link https://cs.symfony.com/doc/rules/index.html
 */

/*
 * IMPORTANT: the $config object must be returned from this file!
 */
return $config;
