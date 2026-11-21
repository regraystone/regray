import { toast } from 'sonner';

/**
 * Show a consistent "coming soon" toast for placeholder features.
 */
export const comingSoon = () => {
    toast.info('Coming Soon!', {
        description: 'This feature is currently under development.',
        id: 'coming-soon',
    });
};
