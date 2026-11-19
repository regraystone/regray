import { Head, usePage } from '@inertiajs/react';
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
import PublicLayout from '@/layouts/public-layout';
import type { LandingPageProps } from '@/types/home';

/**
 * The public landing page, for guests only (anyone signed in is sent on to
 * their role home): Regray introduced as a platform, the group of brands it
 * brings together, then what a client does on it and where to find it.
 */
export default function Landing() {
    const { brands, projects } = usePage<LandingPageProps>().props;

    return (
        <PublicLayout>
            <Head title="Every brand. One platform." />

            <LandingHero />
            <LandingPlatform brands={brands} />
            <LandingBrands brands={brands} />
            <LandingCollection />
            <LandingProjects projects={projects} />
            <LandingClients />
            <LandingCatalogs brands={brands} />
            <LandingShowroom />
        </PublicLayout>
    );
}
