<?php

namespace Mage\Mage\AWS;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\ObjectUploader;
use Aws\S3\MultipartUploader;
use Aws\S3\MultipartUploadException;
use Mage\Mage\Logger\Log;

require dirname(__DIR__) . '/../../../../app/bootstrap.php';

class S333
{
    public $s3;
    private $config;
    public $logfile = 's3-integration.txt';

    public function __construct()
    {
    }

    public function connect()
    {
        $this->config = include BP . "/app/etc/aws-s3-config.php";
 
        if (!isset($this->config['region']) ||
            !isset($this->config['version']) || 
            !isset($this->config['credentials'])){
                throw new \Exception("Configuration file");
            }
        $this->s3 = new \Aws\S3\S3Client($this->config);
        Log::log('Connected to S3', $this->logfile);
    }

    public function upload($file, $name = '', $stream = false, $acl = 'private')
    {
        // Example from AWS used: https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/s3-multipart-upload.html
        if ($stream === false) {
            $source = fopen($file, 'rb');
        } else {
            $source = $file;
        }

        if ($name === '') {
            $name = $file;
        }

        $name = trim(str_replace('//', '/', str_replace(BP, '/', $name)), '/');
        Log::log('Will be saved at ' . $name, $this->logfile);

        $uploader = new ObjectUploader(
            $this->s3,
            'magento2-s3-sdfdsf',
            $name,
            $source,
            $acl
        );

        do {
            try {
                $result = $uploader->upload();
                if ($result["@metadata"]["statusCode"] === '200') {
                   // print('<p>File successfully uploaded to ' . $result["ObjectURL"] . '.</p>');
                }
                Log::log('S3 Uploaded Sucesfully: ' . $result['@metadata']['effectiveUri'], $this->logfile);
                //var_dump($result['@metadata']['effectiveUri']);
                return $result['@metadata']['statusCode'];
            } catch (MultipartUploadException $e) {
                rewind($source);
                $uploader = new MultipartUploader($this->s3, $source, [
                    'state' => $e->getState(),
                ]);
                Log::log('S3 Error Upload: ' . $e->getMessage(), $this->logfile);

                //throw new \Exception("To S3 error: " . $e->getMessage());
                $result = 'error';
            }
        } while (!isset($result));

        if ($stream === false) {
            fclose($source);
        }
        return $result;
    }

    public static function S3Upload($filePath, $fileName, $stream = false, $acl = 'private'){
        Log::log('Fast S3 Uploader: ' . $fileName, self::$logfile);
        $s3 = new S333();
        $s3->connect();
        $s3->upload($filePath, $fileName, $stream, $acl);
    }
}

//$s3 = new S333();
//var_dump($s3->upload(__DIR__ . '/S3.php'));