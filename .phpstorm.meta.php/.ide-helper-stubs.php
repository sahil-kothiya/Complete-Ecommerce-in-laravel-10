<?php
/**
 * IDE Helper Stubs for Optional PHP Extensions
 *
 * This file provides function signatures for optional PHP extensions
 * to prevent IDE warnings when these extensions are not installed.
 *
 * These functions are only used when the respective extensions are loaded.
 */

if (!function_exists('msgpack_pack')) {
    /**
     * @param mixed $value
     * @return string|false
     */
    function msgpack_pack($value) {}
}

if (!function_exists('msgpack_unpack')) {
    /**
     * @param string $str
     * @return mixed
     */
    function msgpack_unpack($str) {}
}

if (!function_exists('igbinary_serialize')) {
    /**
     * @param mixed $value
     * @return string|false
     */
    function igbinary_serialize($value) {}
}

if (!function_exists('igbinary_unserialize')) {
    /**
     * @param string $str
     * @return mixed
     */
    function igbinary_unserialize($str) {}
}

if (!function_exists('lz4_compress')) {
    /**
     * @param string $data
     * @param int $level
     * @return string|false
     */
    function lz4_compress($data, $level = 0) {}
}

if (!function_exists('zstd_compress')) {
    /**
     * @param string $data
     * @param int $level
     * @return string|false
     */
    function zstd_compress($data, $level = 3) {}
}
