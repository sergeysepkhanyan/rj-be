<?php

namespace App\Support;

class UploadPathNormalizer
{
    public static function toRelative(string $value, array $roots = ['images']): string
    {
        $v = trim($value);
        if ($v === '') {
            return $v;
        }

        $v = preg_split('/[?#]/', $v, 2)[0];

        if (filter_var($v, FILTER_VALIDATE_URL)) {
            $path = parse_url($v, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                $v = $path;
            }
        }

        $v = ltrim($v, '/');

        foreach ($roots as $root) {
            $root = trim($root, '/');
            $needle = $root . '/';

            $pos = strpos($v, $needle);
            if ($pos !== false) {
                return substr($v, $pos);
            }

            if ($v === $root) {
                return $v;
            }
        }

        return $v;
    }
}


