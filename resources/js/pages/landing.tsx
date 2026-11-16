import { Head, Link, usePage } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import { useEffect } from 'react';
import LandingFooter from '@/components/landing/landing-footer';
import LandingHeader from '@/components/landing/landing-header';
import LandingHero from '@/components/landing/landing-hero';
import {
    LandingBrands,
    LandingIntro,
    LandingProcess,
    LandingProjects,
    LandingServices,
    LandingSiteVisit,
} from '@/components/landing/landing-sections';
import { index as inboxIndex } from '@/routes/inbox';
import type { LandingPageProps } from '@/types/home';

/**
 * The public landing page, for guests only (anyone signed in is sent on to
 * their role home). It draws its own header and footer, not the guest
 * layout's: the header sits over the hero photo.
 */
export default function Landing() {
    const { brands, projects, serviceTypes } =
        usePage<LandingPageProps>().props;

    // The header's links jump to sections on this page: glide to them.
    useEffect(() => {
        const root = document.documentElement;

        root.classList.add('motion-safe:scroll-smooth');

        return () => root.classList.remove('motion-safe:scroll-smooth');
    }, []);

    return (
        <div className="min-h-screen bg-paper text-ink">
            <Head title="Natural stone and bespoke furniture" />

            <a
                href="#main"
                className="sr-only bg-ink px-4.5 py-3 text-[0.8125rem] text-paper focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-60"
            >
                Skip to content
            </a>

            <LandingHeader />

            <main id="main">
                <LandingHero brands={brands} />
                <LandingIntro />
                <LandingBrands brands={brands} />
                <LandingProjects projects={projects} />
                <LandingServices serviceTypes={serviceTypes} />
                <LandingProcess />
                <LandingSiteVisit brands={brands} />
            </main>

            <LandingFooter brands={brands} />

            <Link
                href={inboxIndex.url()}
                className="fixed right-[max(1rem,env(safe-area-inset-right,0px))] bottom-[max(1rem,env(safe-area-inset-bottom,0px))] z-40 flex items-center gap-2.5 bg-ink px-5.5 py-4 text-xs font-medium tracking-[0.16em] text-paper uppercase shadow-[0_8px_24px_rgb(0_0_0/0.18)] transition-colors hover:bg-black"
            >
                <MessageCircle aria-hidden className="size-4" />
                Message us
            </Link>
        </div>
    );
}
