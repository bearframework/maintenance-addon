<?php

/*
 * Maintenance addon for Bear Framework
 * https://github.com/bearframework/maintenance-addon
 * Copyright (c) 2016 Ivo Petkov
 * Free to use under the MIT license.
 */

namespace BearFramework;

class Maintenance
{

    /**
     *
     * @var \BearFramework\Maintenance\Addons 
     */
    public $addons = null;

    /**
     *
     * @var \BearFramework\Maintenance\Framework 
     */
    public $framework = null;

    /**
     * 
     */
    function __construct()
    {
        $this->addons = new \BearFramework\Maintenance\Addons();
        $this->framework = new \BearFramework\Maintenance\Framework();
    }




}
