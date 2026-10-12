import { Head, Link } from '@inertiajs/react';
import Logo from '@/components/logo';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

const NotFound = () => (
    <>
        <Head title="Page not found" />
        <div className="flex min-h-screen flex-col items-center justify-center gap-10 bg-background px-5 py-16 text-foreground">
            <Logo />
            <div className="mx-auto w-full max-w-6xl text-center">
                <h1 className="text-2xl font-bold tracking-[-0.04em] sm:text-3xl">
                    Page not found
                </h1>
                <p className="mt-3 text-sm text-secondary-foreground">
                    The page you are looking for does not exist.
                </p>
                <Button asChild className="mt-6">
                    <Link href={dashboard()}>Back to my page</Link>
                </Button>
            </div>
        </div>
    </>
);

export default NotFound;
