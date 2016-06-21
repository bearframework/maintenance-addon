<?php

/*
 * Maintenance addon for Bear Framework
 * https://github.com/bearframework/maintenance-addon
 * Copyright (c) 2016 Ivo Petkov
 * Free to use under the MIT license.
 */

use \BearFramework\Maintenance;

$context->classes->add(Maintenance::class, 'src/Maintenance.php');
$context->classes->add(Maintenance\Addons::class, 'src/Maintenance/Addons.php');
$context->classes->add(Maintenance\Framework::class, 'src/Maintenance/Framework.php');
$context->classes->add(Maintenance\Utilities::class, 'src/Maintenance/Utilities.php');

$app->container->set('maintenance', Maintenance::class);

