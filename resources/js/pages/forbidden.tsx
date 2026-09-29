import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

const Forbidden = () => (
    <>
        <Head title="No access" />

        <div className="mx-auto w-full max-w-6xl">
            <div className="flex min-h-[50vh] flex-col items-center justify-center gap-3 border border-sidebar-border bg-background px-6 py-16 text-center">
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
