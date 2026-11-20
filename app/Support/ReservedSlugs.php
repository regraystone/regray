<?php

namespace App\Support;

/**
 * The single-segment names Regray keeps for its own pages: the public
 * catch-all (`/{slug}`, a project or a stone's old address) never matches
 * them, and no brand may take one as its slug.
 */
final class ReservedSlugs
{
    public const ALL = [
        'login', 'register', 'logout', 'forgot-password', 'reset-password', 'two-factor-challenge',
        'confirm-password', 'verify-email', 'dashboard', 'brands', 'furniture', 'settings', 'profile',
        'up', 'images', 'build', 'robots.txt', 'favicon.ico', 'sitemap.xml', 'home', 'overview', 'saved',
        'sales', 'quote', 'inquiry', 'leads', 'schedule', 'message', 'stones', 'products', 'services',
        'projects', 'accounts', 'our-brands', 'showroom', 'about', 'news', 'contact',
    ];

    /** The catch-all's pattern: a lower-case slug that is none of these. */
    public static function catchAllPattern(): string
    {
        $reserved = implode('|', array_map(fn (string $slug): string => str_replace('.', '\\.', $slug), self::ALL));

        return "(?!^({$reserved})$)[a-z0-9]([a-z0-9-]*[a-z0-9])?";
    }
}
