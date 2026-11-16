import { usePage } from '@inertiajs/react';
import GuestLayout from '@/layouts/guest-layout';
import WorkspaceLayout from '@/layouts/workspace-layout';

export default function SiteLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const { props, component } = usePage<{
        auth: { user: unknown };
        component: string;
    }>();
    const isSignedIn = props.auth.user !== null;

    if (isSignedIn) {
        return <WorkspaceLayout>{children}</WorkspaceLayout>;
    }

    // The landing page draws its own header and footer (components/landing).
    if (component === 'landing') {
        return children;
    }

    return (
        <GuestLayout>
            <div className="px-4 py-6 sm:px-5 lg:px-8 lg:py-8">{children}</div>
        </GuestLayout>
    );
}
