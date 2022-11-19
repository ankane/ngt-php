<?php

namespace Ngt;

class Vendor
{
    public const VERSION = '1.14.6';

    public const PLATFORMS = [
        'x86_64-linux' => [
            'file' => 'ngt-{{version}}-x86_64-linux',
            'checksum' => '91f42b6b9e2362f9aa43bdc1a3052a8dc8a762af4d4111cdd5b2eebb533a4b16',
            'lib' => 'libngt.so'
        ],
        'aarch64-linux' => [
            'file' => 'ngt-{{version}}-aarch64-linux',
            'checksum' => '61d110674e665246b64a6af26416bb455a6a0b51969a159b942d469e101e2e58',
            'lib' => 'libngt.so'
        ],
        'x86_64-darwin' => [
            'file' => 'ngt-{{version}}-x86_64-darwin',
            'checksum' => 'd5bdbb3d0d2bb2677d97de831ccd6026e5aa26e5d1397246df7ee0a79f66ddf6',
            'lib' => 'libngt.dylib'
        ],
        'arm64-darwin' => [
            'file' => 'ngt-{{version}}-aarch64-darwin',
            'checksum' => '80bec42f3779590dd03f14015d916d69bae1d825d494f60886f90990da53bc85',
            'lib' => 'libngt.dylib'
        ]
    ];

    public static function check($event = null)
    {
        $dest = self::defaultLib();
        if (file_exists($dest)) {
            echo "✔ NGT found\n";
            return;
        }

        $dir = self::libDir();
        if (!file_exists($dir)) {
            mkdir($dir);
        }

        echo "Downloading NGT...\n";

        $file = self::platform('file');
        $ext = 'zip';
        $url = self::withVersion("https://github.com/ankane/ml-builds/releases/download/ngt-{{version}}/$file.$ext");
        $contents = file_get_contents($url);

        $checksum = hash('sha256', $contents);
        if ($checksum != self::platform('checksum')) {
            throw new Exception("Bad checksum: $checksum");
        }

        $tempDest = tempnam(sys_get_temp_dir(), 'onnxruntime') . '.' . $ext;
        file_put_contents($tempDest, $contents);

        $archive = new \PharData($tempDest);
        if ($ext != 'zip') {
            $archive = $archive->decompress();
        }
        $archive->extractTo(self::libDir());

        echo "✔ Success\n";
    }

    public static function defaultLib()
    {
        return self::libDir() . '/' . self::libFile();
    }

    private static function libDir()
    {
        return __DIR__ . '/../lib';
    }

    private static function libFile()
    {
        return self::platform('lib');
    }

    private static function platform($key)
    {
        return self::PLATFORMS[self::platformKey()][$key];
    }

    private static function platformKey()
    {
        if (PHP_OS_FAMILY == 'Windows') {
            return 'x64-windows';
        } elseif (PHP_OS_FAMILY == 'Darwin') {
            if (php_uname('m') == 'x86_64') {
                return 'x86_64-darwin';
            } else {
                return 'arm64-darwin';
            }
        } else {
            if (php_uname('m') == 'x86_64') {
                return 'x86_64-linux';
            } else {
                return 'aarch64-linux';
            }
        }
    }

    private static function withVersion($str)
    {
        return str_replace('{{version}}', self::VERSION, $str);
    }
}
