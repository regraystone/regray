import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { login } from '@/routes';
import { show as brandsShow } from '@/routes/brands';
import { show as dashboardBrandShow } from '@/routes/dashboard/brands';
import { index as inboxIndex } from '@/routes/inbox';
import type { BrandCardData, HomePageProps } from '@/types/home';

const hero = '/images/regray-hero.jpg';

/**
 * The share of each logo image's height its main lettering takes up, ignoring
 * sub-lines like "Gruppo Lube" and the badge around "Lube", keyed by brand
 * slug. The tile sizes each logo by it, so the brand names read at one
 * height and wide wordmarks like Ascale stay narrow. Regray Stone and Faer are
 * long wordmarks, so they are set above their true share (0.67 and 0.99) to
 * keep them about as wide as the others rather than letter for letter. A brand
 * not listed here gets the default.
 */
const BRAND_NAME_SHARE: Record<string, number> = {
    'regray-stone': 0.94,
    cucine: 0.46,
    ascale: 1,
    faer: 1.45,
    reform: 0.7,
    'res-italia': 0.59,
};
const DEFAULT_NAME_SHARE = 0.6;

/** The hero's height, which every brand tile matches. */
const HERO_HEIGHT = 'min-h-[320px] sm:min-h-[560px] lg:min-h-[680px]';

const Hero = () => (
    <section
        className={`relative flex items-center overflow-hidden text-on-image ${HERO_HEIGHT}`}
    >
        <img
            src={hero}
            alt=""
            aria-hidden
            className="absolute inset-0 size-full object-cover"
        />
        <div className="image-tint absolute inset-0" aria-hidden />
        <div className="relative z-10 mx-auto w-full max-w-6xl px-5 py-10 sm:py-20">
            <p className="eyebrow text-on-image/85 uppercase">
                Stone &amp; furniture, all in one place
            </p>
            <h1 className="hero-title mt-5 max-w-3xl text-on-image uppercase">
                Every brand.
                <br />
                <span className="text-on-image/70">One platform.</span>
            </h1>
            <p className="mt-6 hidden max-w-xl text-base leading-relaxed text-on-image/90 sm:block sm:text-lg">
                Explore a curated catalog of natural stone products and finished
                projects from leading studios — saved, compared, and requested
                in a single workspace.
            </p>
        </div>
    </section>
);

const BrandTile = ({ brand }: { brand: BrandCardData }) => {
    const pageProps = usePage<{ auth: { user: unknown } }>().props;
    const isSignedIn = pageProps.auth?.user != null;
    const nameShare = BRAND_NAME_SHARE[brand.slug] ?? DEFAULT_NAME_SHARE;

    return (
        <Link
            href={
                isSignedIn
                    ? dashboardBrandShow.url({ brand: brand.slug })
                    : brandsShow.url({ brand: brand.slug })
            }
            className={`relative flex flex-col justify-end overflow-hidden rounded-none p-4 transition-opacity [--brand-name-height:1.125rem] hover:opacity-85 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:p-6 sm:[--brand-name-height:1.5rem] lg:[--brand-name-height:2rem] ${HERO_HEIGHT} ${
                brand.thumbnail ? 'text-on-image' : 'bg-muted text-foreground'
            }`}
        >
            {brand.thumbnail ? (
                <>
                    <img
                        src={brand.thumbnail}
                        alt=""
                        aria-hidden
                        className="absolute inset-0 size-full object-cover brightness-90"
                    />
                    <div className="image-tint absolute inset-0" aria-hidden />
                </>
            ) : null}
            <div className="relative z-10 flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
                <img
                    src={brand.logo}
                    alt={brand.name}
                    loading="lazy"
                    style={{
                        height: `calc(var(--brand-name-height) / ${nameShare})`,
                    }}
                    className="w-auto max-w-full shrink-0 self-start object-contain object-left sm:self-center"
                />
                {brand.tagline ? (
                    <p
                        className={`max-w-md text-sm leading-relaxed sm:max-w-2xl sm:text-base ${brand.thumbnail ? 'text-on-image/90' : 'text-secondary-foreground'}`}
                    >
                        {brand.tagline}
                    </p>
                ) : null}
            </div>
        </Link>
    );
};

const Brands = ({ brands }: { brands: BrandCardData[] }) =>
    brands.length === 0 ? null : (
        <ul aria-label="Our brands" className="grid grid-cols-1 gap-6">
            {brands.map((brand) => (
                <li key={brand.slug}>
                    <BrandTile brand={brand} />
                </li>
            ))}
        </ul>
    );

export default function HomeCatalogue() {
    const permissions = usePermissions();
    const pageProps = usePage<HomePageProps>().props;
    const canMessage = permissions.can('messages.view');

    return (
        <div className="w-full">
            <Hero />
            <section
                id="brands"
                aria-labelledby="brands-heading"
                className="mt-8 py-6 sm:px-5 md:mt-12 lg:py-8"
            >
                <div className="mb-6 border-b border-sidebar-border pb-4">
                    <h2
                        id="brands-heading"
                        className="min-w-0 text-2xl font-bold tracking-[-0.04em] uppercase sm:text-3xl"
                    >
                        Our Brands
                    </h2>
                </div>

                <Brands brands={pageProps.brands ?? []} />
            </section>

            <Button
                asChild
                size="lg"
                className="fixed right-[max(1rem,env(safe-area-inset-right,0px))] bottom-[max(1rem,env(safe-area-inset-bottom,0px))] z-40 shadow-lg"
            >
                <Link href={canMessage ? inboxIndex.url() : login.url()}>
                    Message us
                </Link>
            </Button>
        </div>
    );
}
