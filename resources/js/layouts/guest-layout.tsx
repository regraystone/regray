import { Link } from '@inertiajs/react';
import Logo from '@/components/logo';
import { Button } from '@/components/ui/button';

export default function GuestLayout({ children }: { children: React.ReactNode }) {
    return (
        <div className="min-h-screen bg-background">
            <header className="sticky top-0 z-50 border-b border-border bg-background">
                <nav
                    className="flex h-16 items-center justify-between px-4 sm:px-5 lg:px-8"
                    aria-label="Primary"
                >
                    <Link href="/" className="focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none rounded">
                        <Logo />
                    </Link>
                    <Button asChild size="lg">
                        <Link href="/login">Login</Link>
                    </Button>
                </nav>
            </header>
            {children}
        </div>
    );
}
