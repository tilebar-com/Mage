<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Mage_Mage', __DIR__);

include('Mage.php');
include('Images/Bootstrap.php');
//die("TEst MAgento less ");
