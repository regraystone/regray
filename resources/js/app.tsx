import { createInertiaApp, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AuthLayout from '@/layouts/auth-layout';
import WorkspaceLayout from '@/layouts/workspace-layout';
import SiteLayout from '@/layouts/site-layout';
import { rateLimitFrom } from '@/lib/rate-limit';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/*
 * A 429 on a visit with no handler of its own (a filter change, a page of
 * infinite scroll, the heart on a stone, the login form) says how long to
 * wait in one toast, rather than in Inertia's dialog of the raw error page.
 * A page that handles it itself returns false from onHttpException, which
 * stops this event from firing.
 */
router.on('httpException', (event) => {
    const limit = rateLimitFrom(event.detail.response);

    if (limit === null) {
        return;
    }

    event.preventDefault();
    toast.error(limit.message, { id: 'rate-limit' });
});

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            // Error pages can render outside the workspace, including for guests.
            case name === 'forbidden':
            case name === 'not-found':
                return null;
            // The company pages draw the public frame themselves (PublicLayout),
            // for guests and signed-in users alike.
            case name.startsWith('company/'):
                return null;
            // Pages that work for both guests and signed-in users use SiteLayout
            // which picks between GuestLayout and WorkspaceLayout based on auth state
            case name === 'landing':
            case name === 'stone-profile':
            case name === 'brand-profile':
            case name === 'brand-company-profile':
            case name === 'furniture-profile':
            case name === 'showcase-profile':
                return SiteLayout;
            default:
                return WorkspaceLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
