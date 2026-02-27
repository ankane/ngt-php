<?php

namespace Ngt;

class Vendor
{
    public const VERSION = '2.2.4';

    public const PLATFORMS = [
        'x86_64-linux' => [
            'file' => 'ngt-{{version}}-x86_64-linux',
            'checksum' => 'f670bb79bba222679da90d9247ae4ec5d4c40654af52d0a6cdc8b57f71315ccd',
            'lib' => 'libngt.so'
        ],
        'aarch64-linux' => [
            'file' => 'ngt-{{version}}-aarch64-linux',
            'checksum' => '25e0462ffbaf77a349571a2b5fb5eabf30c383c0edc38cd7dda96e590a0a1e1b',
            'lib' => 'libngt.so'
        ],
        'x86_64-darwin' => [
            'file' => 'ngt-{{version}}-x86_64-darwin',
            'checksum' => '1537aacc16d6ba39567713f1e875817c76293175e8f084f67e476ca311a48c2c',
            'lib' => 'libngt.dylib'
        ],
        'arm64-darwin' => [
            'file' => 'ngt-{{version}}-aarch64-darwin',
            'checksum' => '3cb717bca24d8d1707952595b7daa7c1259022eb060dfe5ded02a6ed0cc93cc5',
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
