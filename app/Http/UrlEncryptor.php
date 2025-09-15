<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Crypt;

class UrlEncryptor
{
    public static function encodePath($path)
    {
        // Compress the string to reduce length
        $compressed = gzcompress($path);
        $encrypted = Crypt::encryptString($compressed);
        // Use base64url encoding to make URLs safer
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($encrypted));
    }

    public static function decodePath($encodedPath)
    {
        try {
            // Decode base64url
            $encodedPath = str_replace(['-', '_'], ['+', '/'], $encodedPath);
            $encrypted = base64_decode($encodedPath);
            $compressed = Crypt::decryptString($encrypted);
            return gzuncompress($compressed);
        } catch (\Exception $e) {
            abort(404, 'Invalid URL');
        }
    }
}