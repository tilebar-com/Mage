<?php

namespace Mage\Mage\Images;

use Mage\Mage\Logger\Log;

trait Resize
{
    public static $catalogProductFolder = '/catalog/product/';

    public $logile = 'get-php-resize.txt';

    static function resizeProductImage(\Magento\Catalog\Model\Product $product, string $type = 'category_page_list', int $width = null, int $height = null)
    {
        $imageHelper = self::get(\Magento\Catalog\Helper\Image::class);
        $image = $imageHelper->init($product, $type);

        if ($width !== null && $height !== null) {
            $image->resize($width, $height);
        }
        return $image->getUrl();
    }

    static function imageResizeUrl($url, $width, $height, $type = 'j', $quality = 95, $customPath = false)
    {
        if ($customPath === false) {
            $mediaUrl = self::getMediaURL();
        } else {
            $mediaUrl = $customPath;
        }

        if (!in_array($type, ['j', 'p'])) {
            throw new \Exception("Image Type Resize Error");
        }
        $parameters = [
            'h' => $height,
            'w' => $width,
            't' => $type,
            'q' => $quality
        ];

        ksort($parameters);
        $base64 = /*base64_encode(*/ base64_encode(http_build_query($parameters))/*)*/;

        $url = str_replace('catalog/product', '', $url);

        if (strpos($url, 'cache')) {
            $parts = explode('/', $url);
            $count = count($parts);
            $url = $parts[$count - 3] . '/' . $parts[$count - 2] . '/' . $parts[$count - 1];
        }
        $baseWeb = $mediaUrl . self::$catalogProductFolder . '/resize/' . $base64 . '/' . $url;

        return str_replace('//', '/', $baseWeb);
    }

    static function imageBetterResize($filePath, $width = 800, $height = 800, $type = 'j', $quality = 95)
    {
        $mediaUrl = self::getMediaURL();

        $filePath =  str_replace('//', '/', $filePath);
        $baseWeb = $mediaUrl . self::$catalogProductFolder;
        $mediaPath = BP . '/pub/media/' . self::$catalogProductFolder;
        $imagePath = str_replace('//', '/', $mediaPath . $filePath);

        //echo  $imagePath;

        include_once(__DIR__ . '/../tools/php-image-resize/lib/ImageResize.php');
        include_once(__DIR__ . '/../tools/php-image-resize/lib/ImageResizeException.php');

        $parameters = [
            'h' => $height,
            'w' => $width,
            't' => $type,
            'q' => $quality
        ];

        if (!defined('IMAGETYPE_WEBP')) {
            define('IMAGETYPE_WEBP', 18);
        }
        $imageType = IMAGETYPE_JPEG;
        if ($type === 'p' && function_exists('imagewebp')) {
            $imageType = IMAGETYPE_WEBP;
        } else {
            header("WEB_P: No support");
        }

        try {

            ksort($parameters);
            $base64 = /*base64_encode(*/ base64_encode(http_build_query($parameters))/*)*/;
            $newDirretory = str_replace('//', '/', $mediaPath . 'resize/' . $base64 . '/');

            Log::log($filePath, "get-php-resize.txt");
            $path = explode('/', trim($filePath, '/'));

            $newDirretoryCreate =  str_replace('//', '/', $newDirretory . '/' . @$path[0] . '/' . @$path[1]);
            $newPath = $newDirretory;

            Log::log("Image Resize Create dirrectory: " . $newDirretoryCreate, "get-php-resize.txt");

            if (!file_exists($newDirretoryCreate)) {
                try {
                    //If folder doesn't exist Image resizing doesn't work
                    mkdir($newDirretoryCreate, 0777, true);
                } catch (\Throwable $e) {
                    throw new \Exception("Create dirrectory error during resizing process : " . $e->getMessage());
                }
            }

            $image = new \Gumlet\ImageResize($imagePath, $imageType);
            $image->quality_jpg = $quality;
            $image->quality_webp = $quality;
            //To resize an image to best fit a given set of dimensions (keeping aspet ratio):
            $image->resizeToBestFit($height, $width);
            //resize($width, $height, $allow_enlarge);
            //$image->resize($height, $width, true);
            //$image->resizeToWidth(300);
            //$image->resizeToLongSide(500);
            //$image->resizeToShortSide(300);
            //$image->resizeToBestFit(500, 300);

            //var/www/html/pub/media/catalog/product//resize/YUQwMk1EQW1kejA0TURBPQ==/test.jpeg
            
           
            $newFilePath = str_replace('//', '/', $newDirretory . $filePath);
            //echo  $newFilePath;
            Log::log("Image Resize Create File: " . $newFilePath, "get-php-resize.txt");
            $image->save(
                $newFilePath,
                $imageType
            );

            $newImageUrl = $baseWeb . $newPath;
            //echo $newImageUrl;
            return ['url' => $newImageUrl, 'path' => $newFilePath, 'status' => 200];
        } catch (\Exception $e) {
            echo "Save Error: " . $e->getMessage();
            $storeConfig = self::get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
            $placeholder = $storeConfig->getValue('catalog/placeholder/image_placeholder');
            return ['url' => $placeholder, 'status' => 404, 'error' => $e->getMessage()];
        }
    }
}
