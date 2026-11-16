import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import LandingFooter from '@/components/landing/landing-footer';
import LandingHeader from '@/components/landing/landing-header';
import {
    LandingBrands,
    LandingCatalogs,
    LandingClients,
    LandingCollection,
    LandingHero,
    LandingPlatform,
    LandingProjects,
    LandingShowroom,
} from '@/components/landing/landing-sections';
import { index as inboxIndex } from '@/routes/inbox';
import type { LandingPageProps } from '@/types/home';

/**
 * The public landing page, for guests only (anyone signed in is sent on to
 * their role home): Regray introduced as a platform, the group of brands it
 * brings together, then what a client does on it and where to find it. It
 * draws its own header and footer, not the guest layout's.
 */
export default function Landing() {
    const { brands, projects } = usePage<LandingPageProps>().props;

    // The header's links jump to sections on this page: glide to them.
    useEffect(() => {
        const root = document.documentElement;

        root.classList.add('motion-safe:scroll-smooth');

        return () => root.classList.remove('motion-safe:scroll-smooth');
    }, []);

    return (
        <div className="min-h-screen bg-background font-light text-foreground">
            <Head title="Every brand. One platform." />

            <a
                href="#main"
                className="sr-only bg-foreground px-4.5 py-3 text-sm text-background focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-60"
            >
                Skip to content
            </a>

            <LandingHeader />

            <main id="main">
                <LandingHero />
                <LandingPlatform brands={brands} />
                <LandingBrands brands={brands} />
                <LandingCollection />
                <LandingProjects projects={projects} />
                <LandingClients />
                <LandingCatalogs brands={brands} />
                <LandingShowroom />
            </main>

            <LandingFooter brands={brands} />

            <Link
                href={inboxIndex.url()}
                className="fixed right-[max(1rem,env(safe-area-inset-right,0px))] bottom-[max(1rem,env(safe-area-inset-bottom,0px))] z-40 flex items-center gap-2.5 rounded-full bg-foreground px-5 py-3.5 text-[0.8125rem] font-semibold tracking-[0.04em] text-background uppercase shadow-[0_8px_24px_rgb(0_0_0/0.18)] transition-colors hover:bg-black"
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
