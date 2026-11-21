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

    // The landing page wraps itself in the public frame (PublicLayout).
    if (component === 'landing') {
        return children;
    }

    return <GuestLayout>{children}</GuestLayout>;
}
