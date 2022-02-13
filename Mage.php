<?php

/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

//namespace Mage\Mage;

include 'Logger/Log.php';
include 'Profiler/Profile.php';
include 'Core/Regestry.php';
include 'Images/Resize.php';

use \Mage\Mage\Logger\Log;
use \Mage\Mage\Profiler\Profile;
use \Mage\Mage\Core\Regestry;
use \Mage\Mage\Images\Resize;

use Magento\Framework\App\ObjectManager;

class Mage
{
    use Log;
    use Profile;
    use Regestry;
    use Resize;

    public static $objectManager = null;

    public static $classRegestry = [];

    public static function get(string $className, bool $new = false)
    {
        if ($new === false) {
            if (isset(self::$classRegestry[$className])) {
                return self::$classRegestry[$className];
            }
            if (!isset(self::$objectManager)) {
                self::$objectManager = ObjectManager::getInstance();
            }
            self::$classRegestry[$className] = self::$objectManager->get($className);
            return  self::$classRegestry[$className];
        }

        return self::$objectManager->create($className);
    }

    static function getVersion($key = 'version')
    {
        $composerJson = json_decode(file_get_contents(BP . '/composer.json'));
        return $composerJson[$key];
    }

    static function getMediaURL()
    {
        if (!isset(self::$regestry['media_url'])) {
            $storeManager = Mage::get(\Magento\Store\Model\StoreManagerInterface::class);
            self::$regestry['media_url'] = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        }
        return self::$regestry['media_url'];
    }

    public static function empty(){

    }
}
