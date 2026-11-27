import { Link, usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import PublicFooter from '@/components/public/public-footer';
import PublicHeader from '@/components/public/public-header';
import { register } from '@/routes';
import { index as inboxIndex } from '@/routes/inbox';

/**
 * The public site's frame: the mega-menu header, the page, the dark blue
 * footer and the floating button: for a guest, Start your project, inviting
 * them onto the platform; for someone signed in, Message us. The landing page, the company pages (Our
 * brands, Showroom, About, Contact) and, for guests, the catalogue's brand,
 * showcase, stone and furniture pages sit in it.
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    const isSignedIn =
        usePage<{ auth: { user: unknown } }>().props.auth?.user != null;
    // Links to a section of the same page glide to it.
    useEffect(() => {
        const root = document.documentElement;

        root.classList.add('motion-safe:scroll-smooth');

        return () => root.classList.remove('motion-safe:scroll-smooth');
    }, []);

    // Written for clients of 40 to 60: regular-weight text, and secondary text
    // a darker grey than the workspace's (about 7:1 on white).
    return (
        <div className="min-h-screen bg-background text-foreground [--color-muted-foreground:oklch(0.45_0_0)]">
            <a
                href="#main"
                className="sr-only rounded-md bg-foreground px-4.5 py-3 text-sm text-background focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-60"
            >
                Skip to content
            </a>

            <PublicHeader />

            <main id="main">{children}</main>

            <PublicFooter />

            <Link
                href={isSignedIn ? inboxIndex.url() : register.url()}
                className="fixed right-[max(1rem,env(safe-area-inset-right,0px))] bottom-[max(1rem,env(safe-area-inset-bottom,0px))] z-30 flex min-h-13 items-center gap-2.5 rounded-full bg-foreground px-6 py-3.5 text-base font-semibold text-background shadow-[0_8px_24px_rgb(0_0_0/0.18)] transition-colors hover:bg-black"
                data-testid="floating-invite"
            >
                {isSignedIn ? (
                    <>
                        <span
                            aria-hidden
                            className="size-1.75 rounded-full bg-[#8fbf8a]"
                        />
                        Message us
                    </>
                ) : (
                    'Start your project'
                )}
            </Link>
        </div>
    );
}
