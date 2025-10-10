<?php
declare(strict_types=1);

namespace App\Support;

final class SlugHelper
{
    public static function fromString(string $name): string
    {
        $slug = preg_replace('~[^\pL\d]+~u', '-', $name);
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $slug);
        $slug = preg_replace('~[^-\w]+~', '', $slug);
        $slug = trim($slug, '-');
        $slug = preg_replace('~-+~', '-', $slug);
        $slug = strtolower($slug);
        return $slug !== '' ? $slug : 'group';
    }
}
