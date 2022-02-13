<?php

namespace Mage\Mage\Images;

use Mage\Mage\Mage;
use Magento\Framework\App\Filesystem\DirectoryList;

trait Img
{
    static function extractMainImage($imageUrl)
    {
        $file = basename($imageUrl);
        $qpos = strpos($file, '?');
        if ($qpos !== false)
            $file = substr($file, 0, $qpos);
        $resultURL = $file[0] . '/' . $file[1] . '/' . $file;
        return $resultURL;
    }

    static function getResizeImage($image, $width = 100, $height = 100)
    {
        $image = static::extractMainImage($image);
        $cacheKey = http_build_query([$width, $height]);
        $cacheHash = md5($cacheKey);
        $fileSystem = \Mage::get(\Magento\Framework\Filesystem::class);
        $imageFactory = \Mage::get(\Magento\Framework\Image\AdapterFactory::class);

        $absolutePath =  $fileSystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath('catalog/produt') . $image;
        if (!$fileSystem->getDriver()->isExists($absolutePath)) {
            return false;
        }

        $imageResized = $fileSystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath('catalog/produt/resize/' . $cacheHash . '/') . $image;
        if (!$fileSystem->getDriver()->isExists($imageResized)) {
            // Only resize image if not already exists.
            //create image factory...
            $imageResize = $imageFactory->create();
            $imageResize->open($absolutePath);
            $imageResize->constrainOnly(TRUE);
            $imageResize->keepTransparency(TRUE);
            $imageResize->keepFrame(FALSE);
            $imageResize->keepAspectRatio(TRUE);
            $imageResize->resize($width, $height);
            //destination folder                
            $destination = $imageResized;
            //save image      
            $imageResize->save($destination);
        }

        $storeManager = \Mage::get(\Magento\Store\Model\StoreManagerInterface::class);
        $resizedURL = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/produt/resize/' . $cacheHash . '/' . $image;
        return $resizedURL;
    }

    public static function test()
    {
        $result = static::extractMainImage('asfasdfasd/sadfasdfas/435342453245/D/F/dsd.jpg');
        if ($result !== 'd/s/dsd.jpg') {
            echo "Error\n";
            echo $result . "\n";
        } else echo "OK\n";
        $result = static::extractMainImage('asfasdfasd/sadfasdfas/435342453245/D/F/dsd.jpg?dfsdfsd');
        if ($result !== 'd/s/dsd.jpg') {
            echo "Error\n";
            echo $result . "\n";
        } else echo "OK\n";
        $result = static::extractMainImage('asfasdfasd///sadfasdfas/435342453245/D/F///dsd.jpg?dfsdfsd&sfdsf');
        if ($result !== 'd/s/dsd.jpg') {
            echo "Error\n";
            echo $result . "\n";
        } else echo "OK\n";
    }
}
