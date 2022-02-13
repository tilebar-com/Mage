<?php

require dirname(__DIR__) . '/../../../../app/bootstrap.php';

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);

$magentoBP = '/var/www/html/';
$mediaDirrectory = 'media/catalog/product';
$mediaPath = $magentoBP . 'pub/' . $mediaDirrectory;

\Mage::rset("key",32432432);
\Mage::rget("key");

//die();;

$files = [
    [
        'file' => '/test.jpeg',
        'path' => '/'
    ],
    [
        'file' => '/test2.jpg',
        'path' => '/a/b/'
    ],
    [
        'file' => '/test2.jpg',
        'path' => '/a/b/g/'
    ]
];

foreach ($files as $file) {
    @mkdir($mediaPath . $file['path'], 0777, true);
    copy(__DIR__ . $file['file'], $mediaPath . $file['path'] . $file['file']);
}

//doesn't exists
/*
$files[] = [
    'file' => '/test2-not-exists.jpg',
    'path' => '/a/b/g/'
];
*/

$httpImageFull = "https://thevan.nike.com/media/catalog/product/cache/b37e47aae0a2866eb843969f1cf37369/D/M/DM0718-003-PHCFH001.jpeg";

\Mage::start('Resize Url Generate');
$testFileName = \Mage::imageResizeUrl($httpImageFull, 100, 100, 'j', 95, '/media/');
\Mage::end('Resize Url Generate', true);

if ($testFileName !== "/media/catalog/product/resize/aD0xMDAmdz0xMDAmdD1qJnE9OTU=/D/M/DM0718-003-PHCFH001.jpeg")
{
echo "==>ERROR: Image URL generate issue\n";
}
echo "Tested File URL: " . $httpImageFull . "\n";
echo "Resized File: " . $testFileName . "\n";

$testCases = [
    [
        'parameters' =>
        [
            'w' => 1000,
            'h' => 1000,
            't' => 'j'
        ],
        'result' =>
        [
            'type' => 'image/jpeg'
        ]
    ],
    [
        'parameters' =>
        [
            'w' => 1000,
            'h' => 1000,
            't' => 'p'
        ],
        'result' =>
        [
            'type' => 'image/webp'
        ]
    ],
    [
        'parameters' =>
        [
            'w' => 1000,
            'h' => 1000,
            't' => 'p'
        ],
        'result' =>
        [
            'type' => 'image/jpeg'
        ]
    ]
];

$host = 'http://127.0.0.1/';

foreach ($testCases as $i => $test) {
    echo "Test case " . $i . "\n";
    //var_dump($test);
    $sizeQuery = http_build_query($test['parameters']);
    echo $sizeQuery;
    $base64Size = base64_encode($sizeQuery);
    //base64Size

    foreach ($files as $file) {
        echo "Testing file : " . $file['file'] . "\n";
        $originalFile = $host . $mediaDirrectory . $file['path'] . $file['file'];
        $resizedFile =  $host . $mediaDirrectory . '/resize/' . $base64Size . $file['path'] . $file['file'];

        $fileUrlToTest = $mediaDirrectory . $file['file'];
        \Mage::start('Resize Url Generate');
        $testFileName = \Mage::imageResizeUrl($fileUrlToTest, $test['parameters']['w'], $test['parameters']['h'],  $test['parameters']['t']);
        \Mage::end('Resize Url Generate', true);

        echo "Tested File URL: " . $testFileName . "\n";
        echo "Resized File: " . $resizedFile . "\n";

        $fileOriginal = file_get_contents($originalFile);
        \Mage::start('Resize HTTP Generate');
        $fileResized = file_get_contents($resizedFile);
        \Mage::end('Resize HTTP Generate', true);

        $opriginalSizes = getimagesize($originalFile);
        $resizedSizes = getimagesize($resizedFile);

        if ($resizedSizes === false) {
            echo "\e[31m==>ERROR Not an image file \e[39m\n";
        } else {
            echo "MIME type : " . $resizedSizes['mime'] . "\n";
            if ($test['parameters']['w'] !== $resizedSizes[0]) {
                echo " \e[31m==>ERROR resize incorrect\e[39m\n";
            } else {
                echo "\e[32m=>OK resize correct\e[39m\n";
            }
            if ($resizedSizes['mime'] !== $test['result']['type']) {
                echo "\e[31m==>ERROR mime error\e[39m\n";
            } else {
                echo "\e[32m=>OK mime correct\e[39m\n";
            }
        }
        echo "-------------------------------------\n";
    }
}