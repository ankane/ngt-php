<?php

namespace Ngt;

class Vendor
{
    public const VERSION = '2.6.0';

    public const PLATFORMS = [
        'x86_64-linux' => [
            'file' => 'ngt-{{version}}-x86_64-linux',
            'checksum' => '9b083b370d69bc22b5f701b51f3c0bcafedb7853930f352687262aa58ae29c98',
            'lib' => 'libngt.so'
        ],
        'aarch64-linux' => [
            'file' => 'ngt-{{version}}-aarch64-linux',
            'checksum' => '294b6f4b61e8df8b4a874cfae23ee60db38119560e1974d722dd0074acdb37f9',
            'lib' => 'libngt.so'
        ],
        'x86_64-darwin' => [
            'file' => 'ngt-{{version}}-x86_64-darwin',
            'checksum' => 'b3b04ce5d115529ca2891d6af29537026a9b54db7d4fdf0e292d2674f3b931b6',
            'lib' => 'libngt.dylib'
        ],
        'arm64-darwin' => [
            'file' => 'ngt-{{version}}-aarch64-darwin',
            'checksum' => '320431e7a8afee6ae67e0f1d83e33cd3a4249e575ee22e18dd70bced6a4e9062',
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
