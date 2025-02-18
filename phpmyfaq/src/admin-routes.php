<?php

/**
 * phpMyFAQ admin API routes
 *
 * This Source Code Form is subject to the terms of the Mozilla Public License,
 * v. 2.0. If a copy of the MPL was not distributed with this file, You can
 * obtain one at https://mozilla.org/MPL/2.0/.
 *
 * @package   phpMyFAQ
 * @author    Thorsten Rinne <thorsten@phpmyfaq.de>
 * @copyright 2023 phpMyFAQ Team
 * @license   https://www.mozilla.org/MPL/2.0/ Mozilla Public License Version 2.0
 * @link      https://www.phpmyfaq.de
 * @since     2023-07-08
 */

use phpMyFAQ\Controller\Administration\UpdateController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$routes = new RouteCollection();

$routes->add(
    'admin.api.health-check',
    new Route('/health-check', ['_controller' => [UpdateController::class, 'healthCheck'], '_methods' => 'POST'])
);

$routes->add(
    'admin.api.updates',
    new Route('/versions', ['_controller' => [UpdateController::class, 'versions']])
);

$routes->add(
    'admin.api.update-check',
    new Route('/update-check', ['_controller' => [UpdateController::class, 'updateCheck']])
);

$routes->add(
    'admin.api.download-package',
    new Route(
        '/download-package/{versionNumber}',
        [
            '_controller' => [UpdateController::class, 'downloadPackage'],
            '_methods' => 'POST'
        ])
);

$routes->add(
    'admin.api.extract-package',
    new Route(
        '/extract-package',
        [
            '_controller' => [UpdateController::class, 'extractPackage'],
            '_methods' => 'POST'
        ]
    )
);

return $routes;
