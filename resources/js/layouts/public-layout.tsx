import { Link } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import PublicFooter from '@/components/public/public-footer';
import PublicHeader from '@/components/public/public-header';
import { index as inboxIndex } from '@/routes/inbox';

/**
 * The public site's frame: the mega-menu header, the page, the black footer
 * and the Message us button. The landing page, the company pages (Our
 * brands, Showroom, About, Contact) and, for guests, the catalogue's brand,
 * project, stone and furniture pages sit in it.
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    // Links to a section of the same page glide to it.
    useEffect(() => {
        const root = document.documentElement;

        root.classList.add('motion-safe:scroll-smooth');

        return () => root.classList.remove('motion-safe:scroll-smooth');
    }, []);

    return (
        <div className="min-h-screen bg-background font-light text-foreground">
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
                href={inboxIndex.url()}
                className="fixed right-[max(1rem,env(safe-area-inset-right,0px))] bottom-[max(1rem,env(safe-area-inset-bottom,0px))] z-30 flex items-center gap-2.5 rounded-full bg-foreground px-5 py-3.5 text-[0.8125rem] font-semibold tracking-[0.04em] text-background uppercase shadow-[0_8px_24px_rgb(0_0_0/0.18)] transition-colors hover:bg-black"
            >
                <span
                    aria-hidden
                    className="size-1.75 rounded-full bg-[#8fbf8a]"
                />
                Message us
            </Link>
        </div>
    );
}
