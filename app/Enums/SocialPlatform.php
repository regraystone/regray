<?php

namespace App\Enums;

/**
 * The social networks a brand can link to from its page, each with its own
 * icon. Only these: a link to anything else is refused. Twitter and X are the
 * same network under two names, so either takes a twitter.com or an x.com
 * address, and each shows its own icon.
 */
enum SocialPlatform: string
{
    case Facebook = 'facebook';
    case Twitter = 'twitter';
    case X = 'x';
    case Instagram = 'instagram';
    case TikTok = 'tiktok';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Twitter => 'Twitter',
            self::X => 'X',
            self::Instagram => 'Instagram',
            self::TikTok => 'TikTok',
        };
    }

    /**
     * The sites a link to it may be on, subdomains included (www., m.).
     *
     * @return list<string>
     */
    public function hosts(): array
    {
        return match ($this) {
            self::Facebook => ['facebook.com', 'fb.com'],
            self::Twitter, self::X => ['twitter.com', 'x.com'],
            self::Instagram => ['instagram.com'],
            self::TikTok => ['tiktok.com'],
        };
    }

    /** Whether the address is on one of its sites. */
    public function owns(string $url): bool
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach ($this->hosts() as $site) {
            if ($host === $site || str_ends_with($host, '.'.$site)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
