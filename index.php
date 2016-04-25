<?php

/*
 * Maintenance addon for Bear Framework
 * https://github.com/bearframework/maintenance-addon
 * Copyright (c) 2016 Ivo Petkov
 * Free to use under the MIT license.
 */

use \BearFramework\Maintenance;

$context->classes->add(Maintenance::class, 'src/Maintenance.php');

$app->container->set('maintenance', function() use ($context) {
    return new Maintenance($context->options);
});

