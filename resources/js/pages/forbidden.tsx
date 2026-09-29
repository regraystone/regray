import { Head, Link } from '@inertiajs/react';
import Logo from '@/components/logo';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

/*
 * Rendered without a layout, so this page brings its own frame: the logo, the
 * message, and one way out. Nothing here points at a page the user may not
 * open, and the button goes to `dashboard`, which redirects to their own
 * landing page.
 */
const Forbidden = () => (
    <>
        <Head title="No access" />

        <div className="flex min-h-screen flex-col items-center justify-center gap-10 bg-background px-5 py-16 text-foreground">
            <Logo />

            <div className="flex w-full max-w-md flex-col items-center gap-3 border border-sidebar-border px-6 py-12 text-center">
                <p className="text-lg font-bold tracking-[-0.03em]">
                    You don&apos;t have access to this page
                </p>

                <p className="max-w-sm text-sm text-secondary-foreground">
                    It isn&apos;t part of what your role can open. If you need
                    it, ask your administrator to update your access.
                </p>

                <div className="mt-1">
                    <Button asChild>
                        <Link href={dashboard()}>Back to my page</Link>
                    </Button>
                </div>
            </div>
        </div>
    </>
);

export default Forbidden;
