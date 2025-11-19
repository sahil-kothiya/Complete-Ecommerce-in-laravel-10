<?php
/**
 * IDE Helper Stubs for Optional PHP Extensions
 *
 * This file provides function signatures for optional PHP extensions
 * to prevent IDE warnings when these extensions are not installed.
 *
 * These functions are only used when the respective extensions are loaded.
 * DO NOT include this file in production code.
 */

if (!function_exists('msgpack_pack')) {
    /**
     * Pack data into MessagePack format
     * @param mixed $value
     * @return string|false
     */
    function msgpack_pack($value) {
        return '';
    }
}

if (!function_exists('msgpack_unpack')) {
    /**
     * Unpack MessagePack data
     * @param string $str
     * @return mixed
     */
    function msgpack_unpack($str) {
        return null;
    }
}

if (!function_exists('igbinary_serialize')) {
    /**
     * Serialize data using Igbinary
     * @param mixed $value
     * @return string|false
     */
    function igbinary_serialize($value) {
        return '';
    }
}

if (!function_exists('igbinary_unserialize')) {
    /**
     * Unserialize Igbinary data
     * @param string $str
     * @return mixed
     */
    function igbinary_unserialize($str) {
        return null;
    }
}

if (!function_exists('lz4_compress')) {
    /**
     * Compress data using LZ4
     * @param string $data
     * @param int $level
     * @return string|false
     */
    function lz4_compress($data, $level = 0) {
        return '';
    }
}

if (!function_exists('zstd_compress')) {
    /**
     * Compress data using Zstandard
     * @param string $data
     * @param int $level
     * @return string|false
     */
    function zstd_compress($data, $level = 3) {
        return '';
    }
}
