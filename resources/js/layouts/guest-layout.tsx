import { Link } from '@inertiajs/react';
import Logo from '@/components/logo';
import { Button } from '@/components/ui/button';
import { home, login } from '@/routes';

export default function GuestLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return (
        <div className="min-h-screen bg-background">
            <header className="sticky top-0 z-50 border-b border-border bg-background">
                <nav
                    className="flex h-16 items-center justify-between px-4 sm:px-5 lg:px-8"
                    aria-label="Primary"
                >
                    <Logo href={home.url()} />
                    <Button asChild size="lg">
                        <Link href={login.url()}>Login</Link>
                    </Button>
                </nav>
            </header>
            {children}
        </div>
    );
}
