import { Head, usePage } from '@inertiajs/react';
import {
    LandingBrandWall,
    LandingContact,
    LandingDiscover,
    LandingHero,
    LandingNews,
    LandingOnline,
    LandingShowroom,
} from '@/components/landing/landing-sections';
import PublicLayout from '@/layouts/public-layout';
import type { LandingPageProps } from '@/types/home';

/**
 * The public landing page, for guests only (anyone signed in is sent on to
 * their role home), in the order of the agreed homepage layout: 01 the Regray
 * Group and Discover, 02 Our brands, 03 Regray Online, 04 Showroom, 05 News,
 * 06 Contact, then the footer.
 */
export default function Landing() {
    const { brands } = usePage<LandingPageProps>().props;

    return (
        <PublicLayout>
            <Head title="Architecture of living" />

            <LandingHero />
            <LandingDiscover />
            <LandingBrandWall brands={brands} />
            <LandingOnline />
            <LandingShowroom />
            <LandingNews />
            <LandingContact />
        </PublicLayout>
    );
}
