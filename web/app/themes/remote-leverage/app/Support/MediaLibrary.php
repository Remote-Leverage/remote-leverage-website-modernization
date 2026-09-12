<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolves a media-library attachment by its filename.
 *
 * Ported components reference images that were sideloaded from production, and
 * the uploads path they land in is environment-specific (the year/month folder
 * differs per install), so templates look them up by name instead of hardcoding
 * a URL that would only be right on one machine.
 */
class MediaLibrary
{
    /** @var array<string, string> */
    private static array $cache = [];

    public static function url(string $filename): string
    {
        if (isset(self::$cache[$filename])) {
            return self::$cache[$filename];
        }

        global $wpdb;

        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s
             ORDER BY post_id DESC LIMIT 1",
            '%'.$wpdb->esc_like($filename),
        ));

        return self::$cache[$filename] = $id ? (string) wp_get_attachment_url((int) $id) : '';
    }
}
