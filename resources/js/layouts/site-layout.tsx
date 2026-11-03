import { usePage } from '@inertiajs/react';
import GuestLayout from '@/layouts/guest-layout';
import WorkspaceLayout from '@/layouts/workspace-layout';
import type { PageProps } from '@/types/page';

export default function SiteLayout({ children }: { children: React.ReactNode }) {
    const { props, component } = usePage<PageProps & { component: string }>();
    const isSignedIn = props.auth.user !== null;

    if (isSignedIn) {
        return <WorkspaceLayout>{children}</WorkspaceLayout>;
    }

    const isLanding = component === 'landing';

    return (
        <GuestLayout>
            <div className={isLanding ? '' : 'px-4 sm:px-5 lg:px-8 py-6 lg:py-8'}>
                {children}
            </div>
        </GuestLayout>
    );
}
