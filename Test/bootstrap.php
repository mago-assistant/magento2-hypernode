<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

/*
 * Autoload through the module's own vendor/ (standalone checkout), the Magento install it is
 * mounted into as a path repository (extensions/<name>), or the one it is installed in (vendor/).
 */
$autoloaders = array_filter(
    [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../../vendor/autoload.php',
        __DIR__ . '/../../../../vendor/autoload.php',
    ],
    'is_file'
);

if ($autoloaders === []) {
    throw new \RuntimeException(
        'No composer autoloader found. Run `composer update` in the module, or run the suite from a Magento install.'
    );
}

require reset($autoloaders);
