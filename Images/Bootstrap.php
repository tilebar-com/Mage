<?php

namespace Mage\Mage\Images;

use Mage\Mage\Mage;
use Mage\Mage\Logger\Log;
use Mage\Mage\AWS\S333;

class Bootstrap
{
    private $testMode = false;
    public $fallbackURL;
    private $isTest;
    private $config;
    private $s3Sync = false;
    public $logFile = 'get-php-resize.txt';

    public const IMAGE_FOLDER = '/pub/media/catalog/product/';

    public function __construct()
    {
        $resizeConfig = include BP . '/app/etc/resize-config.php';

        if (
            !isset($resizeConfig['fallbackURL']) &&
            !isset($resizeConfig['test']) &&
            !isset($resizeConfig['salt'])
        ) {
            // return execution to Magento
            return false;
        }

        $this->fallbackUrl =  $resizeConfig['fallbackURL'];
        $this->testMode = $resizeConfig['test'];
        $this->salt = $resizeConfig['salt'];

        $logfile = 'get-php-resize.txt';

        // Logic dynamic Image Resizing 
        $matches = $this->testRouter();

        Log::log('Resize Prosess Started', $logfile);

        if (isset($resizeConfig['s3']) && $resizeConfig['s3'] !== false) {
            Log::log('Resize S3 sync enabled', $logfile);
            $this->s3Sync = $resizeConfig['s3'];
        }

        if (!isset($matches[0][1])) {
            return false;
        }

        $hash = $matches[0][1];
        $url = $matches[0][2];

        $url = explode('?', $url)[0];

        $resizeQuery = $this->base64($hash);

        Log::log('Resize Query: ' . $resizeQuery, $logfile);

        $baseProductMediaDirrectory = BP . self::IMAGE_FOLDER;

        parse_str($resizeQuery, $parameters);

        if (!isset($parameters['t'])) {
            $parameters['t'] = 'j';
        }
        if (!isset($parameters['q'])) {
            $parameters['q'] = 95;
        }

        if ($parameters['q'] > 100) {
            $parameters['q'] = 100;
        }

        if (!isset($parameters['w']) || !isset($parameters['h'])) {
            Log::log('Wrong Parameters no H or W', $logfile);
            http_response_code(404);
            die();
        }

        $fullOriginalFilePath = $baseProductMediaDirrectory . $url;
        $fullResizedFilePath = BP . '/pub/' . explode('?', $_SERVER['REQUEST_URI'])[0];

        Log::log('File Path :' . $fullResizedFilePath, $logfile);
        if ($this->isTest) {
            Log::log('Test mode enabled', $logfile);
            // Replacing note base 64 parameter string to base 64 encoded
            ksort($parameters);
            $queryFull = http_build_query($parameters);
            $fullResizedFilePath = $this->normalizeUrlForS3(str_replace($hash, base64_encode($queryFull), $fullResizedFilePath));
            Log::log('File Path after test Mode modification: ' . $fullResizedFilePath, $logfile);
        }

        Log::log('Parameters after Ksort: ' . http_build_query($parameters), $logfile);

        $bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
        $bootstrap;
        $fs = \Mage::get(\Magento\Framework\Filesystem::class);
        $writer = $fs->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
        $fullResizedFilePath = 'media/' . $this->normalizeUrlForS3($fullResizedFilePath);

        if (!is_file(BP . '/pub/' . $fullResizedFilePath)) {
            Log::log('File does not exist in file system: ' . BP . '/pub/' . $fullResizedFilePath, $logfile);
        }

        //If driver is S3 this operations will be slow... 
        if ($writer->getDriver()->isExists($fullResizedFilePath)) {

            // when this is a test mode nginx can't return file becouse path is different and that file path doesn't not exis 
            Log::log('File Path exists: ' . $fullResizedFilePath, $logfile);
            header('Test-Image-Cache: HIT');
            if ($parameters['t'] === 'j') {
                header('Content-Type: image/jpeg');
            } else {
                header('Content-Type: image/webp');
            }

            $file = $writer->getDriver()->fileGetContents($fullResizedFilePath);
            //Output image data to the user 
            echo $file;
            Log::log('Return file Body and EXIT/die() ', $logfile);
            die();
        }

        try {
            Log::log('File Path Doesn\'t exists: ' . $fullResizedFilePath, $logfile);

            //check if it is test server and do we need to fetch original image from the remote as a fallback 
            if ($this->testMode) {
                $testFile = $this->testServer($fullOriginalFilePath, $url, $baseProductMediaDirrectory, $resizeConfig['fallbackURL'], $writer, $logfile);
                if ($testFile === false) {
                    Log::log('File Frite issu!! Stupid magento driver: ' . $fullResizedFilePath, $logfile);
                }
            }

            $newMediaUrl = $this->resizeLogic($url, $parameters, $logfile, $writer);

            if ($this->isTest) {
                header('New-Url: ' . $newMediaUrl['path']);
                Log::log('It was Test Mode: ', $logfile);
            }
            if ($newMediaUrl['status'] === 404) {
                http_response_code(404);
                Log::log('ERROR!!!!! File resizing Error : ' . $newMediaUrl['error'], $logfile);
                die();
            }

            header('Just-Generated: true');
            if ($this->isTest) $hash = base64_encode($hash);
            header('Static-Hash: ' . $hash);

            //Resized image saved in the file system not a magento Driver. It is a cache basically 
            Log::log("Get file from cached file: " . $newMediaUrl['path'], $logfile);
            $file = file_get_contents($newMediaUrl['path']); // $writer->getDriver()->fileGetContents($newMediaUrl['path']);

            if ($this->s3Sync == true) {
                Log::log('Syncyng wiht S3 progressive', $logfile);
                try {
                    $s3 = new S333();
                    $s3->connect();
                    $s3->upload($newMediaUrl['path'], $newMediaUrl['path'], false, 'public-read');
                    header('Synced-To-S3: true');
                } catch (\Exception $e) {
                    Log::log('Syncyng wiht S3 error' .  $e->getMessage(), $logfile);
                    return false;
                }
            } else {
                $driverClass = get_class($writer->getDriver());
                Log::log('Syncing with Magento Driver - ' . $driverClass, $logfile);
                $dirname = $this->normalizeUrlForS3(pathinfo($newMediaUrl['path'], PATHINFO_DIRNAME));
                $writer->getDriver()->createDirectory('media/' . $dirname);

                ///var/www/html/vendor/magento/framework/Api/ImageProcessor.php

                Log::log('Write To storage Path - ' . $this->normalizeUrlForS3($newMediaUrl['path']), $logfile);
                $fileTest = $writer->writeFile(
                    $this->normalizeUrlForS3($newMediaUrl['path']),
                    $file
                );

                if ($fileTest === false) {
                    Log::log('File Write issues!!! Magento system error: ' .  $newMediaUrl['path'], $logfile);
                }

                if ($driverClass !== 'Magento\Framework\Filesystem\Driver\File') {
                    Log::log('Remove local if driver is not File System', $logfile);
                    unlink($newMediaUrl['path']);
                }
            }

            if ($parameters['t'] === 'j') {
                header('Content-Type: image/jpeg');
            } else {
                header('Content-Type: image/webp');
            }
            Log::log('Done!! Returning image Body to the User and die', $logfile);
            echo $file;

            //fastcgi_finish_request — Flushes all response data to the client
            //This function flushes all response data to the client and finishes the request. 
            //This allows for time consuming tasks to be performed without leaving the connection 
            //to the client open.
            //#ToDO: production doesn't support this
            //\fastcgi_finish_request();

            die();
        } catch (\Exception $e) {
            Log::log('ERROR!! Resizing Boostrap issue: ' . $e->getMessage(), $logfile);
            http_response_code(404);
            die();
        }
    }

    public function base64($hash)
    {

        $result = base64_decode($hash);
        if ($this->testMode) {

            if (strpos($hash, 'w=') !== false && strpos($hash, 'h=') !== false) {
                $this->isTest = true;
                return $hash;
            }
        }
        return $result;
    }

    // Test if we should Process this request 
    public function testRouter()
    {
        $re = '/.*\/resize\/([^\/]*)\/(.*)/m';
        $str = @$_SERVER['REQUEST_URI'];
        preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);
        return $matches;
    }

    public function resizeLogic($url, $parameters, $logfile, $writer)
    {
        $url = '/' . $url;
        Log::log('Resized-url : ' . $url, $logfile);

        $bigFileName = BP . '/pub/media/catalog/product' .  $url;
        Log::log('Big file name : ' .  $bigFileName, $logfile);
        Log::log('Get Real Path: ' .   $writer->getDriver()->getRealPath($bigFileName), $logfile);
        $bigFileName = $this->normalizeUrlForS3($bigFileName);
        $fileForResizeContent = $writer->getDriver()->fileGetContents('media/' . $bigFileName);

        //echo $fileForResizeContent; die();

        $saveCacheFileName = BP . '/pub/media/' . $bigFileName;
        mkdir(dirname($saveCacheFileName), 0777, true);
        Log::log('Big file CACHE name : ' .  $saveCacheFileName, $logfile);
        $statusFile = file_put_contents($saveCacheFileName, $fileForResizeContent);
        if (!$statusFile) {
            Log::log('Erroro write to file ', $logfile);
        }

        ///save file just in case locally for resize with S3 it has issue it doesn't have local file 
        $newMediaUrl = \Mage::imageBetterResize($url, $parameters['w'], $parameters['h'], $parameters['t'], $parameters['q']);

        Log::log('Resized-new path : ' . $newMediaUrl['path'], $logfile);

        return  $newMediaUrl;
    }

    public function testServer($fullOriginalFilePath, $url, $baseProductMediaDirrectory, $fallbackURL, $writer, $logfile)
    {
        if (
            !$writer->getDriver()->isExists($fullOriginalFilePath) &&
            (strstr($_SERVER['HTTP_HOST'], '127.0.0') || strstr($_SERVER['HTTP_HOST'], 'dev'))
        ) {
            $productionMediaUrl = $fallbackURL;
            Log::log("Trying fetch image from the Fallback URL : " . $this->fallbackURL, $logfile);
            $imageFileContent = file_get_contents($productionMediaUrl . $url);
            Log::log("Original Image fetched form the remote: " . $productionMediaUrl . $url, $logfile);
            $path = explode('/', $url);
            $url = array_pop($path);

            if (isset($path[0]) && isset($path[1])) {
                $createDirrectory = $baseProductMediaDirrectory . '/' . $path[0] . '/' . $path[1];
                $createDirrectory =  $this->normalizeUrlForS3($createDirrectory);
                Log::log("Write fetched original folder: " . $createDirrectory, $logfile);
                //Log::log('Get Real Path: ' .   $writer->getDriver()->getObjectUrl( $createDirrectory), $logfile);
                if (!$writer->getDriver()->isExists($createDirrectory)) {
                    $writer->getDriver()->createDirectory('media/' . $createDirrectory);
                }

                $writeTofile =  $createDirrectory . '/' . $url;
                Log::log("Write fetched original file: " . $writeTofile, $logfile);
                Log::log('Get Real Path: ' .   $writer->getDriver()->getRealPath($writeTofile), $logfile);

                $filestatus = $writer->writeFile(
                    $writeTofile,
                    $imageFileContent
                );

                if ($filestatus === false) {
                    return false;
                }
                return true;
            }
        }
    }

    // Url shuld have relative path to the folder
    public function normalizeUrlForS3($url)
    {
        return ltrim(str_replace(BP . '/pub/media/', '', str_replace('//', '/', $url)), '/');
    }
}
