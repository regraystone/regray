import type { SocialPlatform } from '@/types/home';

/** Each network's name as people write it. */
export const SOCIAL_LABELS: Record<SocialPlatform, string> = {
    facebook: 'Facebook',
    twitter: 'Twitter',
    x: 'X',
    instagram: 'Instagram',
    tiktok: 'TikTok',
};

/**
 * A social network's mark, drawn in the current text colour at about 18px,
 * for the five a brand can link to (SocialPlatform on the server).
 */
const SocialIcon = ({
    platform,
    className,
}: {
    platform: SocialPlatform;
    className?: string;
}) => {
    const props = {
        width: 18,
        height: 18,
        viewBox: '0 0 24 24',
        'aria-hidden': true,
        className,
    } as const;

    switch (platform) {
        case 'facebook':
            return (
                <svg {...props} fill="currentColor">
                    <path d="M13.5 21v-7.5h2.6l.4-3h-3V8.6c0-.9.3-1.5 1.5-1.5h1.6V4.4c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.2H7.8v3h2.6V21h3.1z" />
                </svg>
            );
        case 'instagram':
            return (
                <svg
                    {...props}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.7"
                >
                    <rect x="3.5" y="3.5" width="17" height="17" rx="4.5" />
                    <circle cx="12" cy="12" r="4" />
                    <circle
                        cx="17.3"
                        cy="6.7"
                        r=".9"
                        fill="currentColor"
                        stroke="none"
                    />
                </svg>
            );
        case 'twitter':
            return (
                <svg {...props} fill="currentColor">
                    <path d="M21.5 6.2c-.7.3-1.5.5-2.2.6.8-.5 1.4-1.2 1.7-2.1-.8.5-1.6.8-2.5 1A3.9 3.9 0 0 0 11.9 9 11 11 0 0 1 3.9 5a3.9 3.9 0 0 0 1.2 5.2c-.6 0-1.2-.2-1.8-.5 0 1.9 1.4 3.5 3.1 3.9-.6.2-1.2.2-1.8.1.5 1.6 2 2.7 3.7 2.7A7.8 7.8 0 0 1 2.5 18 11 11 0 0 0 8.4 19.7c7.1 0 11-5.9 11-11v-.5c.8-.6 1.5-1.3 2-2z" />
                </svg>
            );
        case 'x':
            return (
                <svg {...props} width={16} height={16} fill="currentColor">
                    <path d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.2-8.3L1.8 3h6.4l4.4 5.8L17.8 3zm-1.1 16.2h1.7L7.3 4.7H5.5l11.2 14.5z" />
                </svg>
            );
        case 'tiktok':
            return (
                <svg {...props} width={17} height={17} fill="currentColor">
                    <path d="M16.6 3h-3.1v12.2a2.8 2.8 0 1 1-2-2.7V9.3a6 6 0 1 0 5.1 5.9V9.1a7.6 7.6 0 0 0 4.4 1.4V7.4a4.4 4.4 0 0 1-4.4-4.4z" />
                </svg>
            );
    }
};

export default SocialIcon;
