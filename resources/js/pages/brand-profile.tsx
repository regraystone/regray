import { router, usePage } from '@inertiajs/react';
import { ArrowLeft, ExternalLink } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { index as homeIndex } from '@/routes/home';
import type { BrandProfile as BrandProfileData } from '@/types/home';

type BrandProfileProps = {
    brand: { data: BrandProfileData };
};

/*
 * A brand's page, laid out like the stone profile: a full-width hero (its
 * thumbnail, or a neutral background without one) with the logo and
 * tagline, then the description and a "Coming soon" collection.
 */
const BrandProfile = ({ brand: { data: brand } }: BrandProfileProps) => {
    const pageProps = usePage<{ auth: { user: unknown } }>().props;
    const isSignedIn = pageProps.auth?.user != null;

    const goBack = () => {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            router.visit(isSignedIn ? homeIndex.url() : '/');
        }
    };

    return (
        <div className="w-full">
            <section
                className={`relative flex min-h-[280px] items-center overflow-hidden sm:min-h-[400px] ${
                    brand.thumbnail
                        ? 'text-on-image'
                        : 'bg-muted text-foreground'
                }`}
            >
                {brand.thumbnail ? (
                    <>
                        <img
                            src={brand.thumbnail}
                            alt=""
                            aria-hidden
                            className="absolute inset-0 size-full object-cover"
                        />
                        <div
                            className="image-tint absolute inset-0"
                            aria-hidden
                        />
                    </>
                ) : null}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={goBack}
                    aria-label="Back"
                    className="group absolute top-4 left-4 z-20 h-11 w-11 cursor-pointer justify-center gap-2.5 border-white/25 bg-black/55 px-0 tracking-[0.12em] text-white backdrop-blur-md transition-colors duration-200 hover:border-white/40 hover:bg-black/75 hover:opacity-100 focus-visible:ring-white/60 sm:top-6 sm:left-6 sm:w-auto sm:px-4 lg:left-8"
                >
                    <ArrowLeft
                        className="size-4 transition-transform duration-200 group-hover:-translate-x-0.5"
                        aria-hidden
                    />
                    <span className="hidden sm:inline">Back</span>
                </Button>
                <div className="relative z-10 mx-auto flex w-full max-w-4xl flex-col items-center px-5 py-16 text-center sm:py-24">
                    <img
                        src={brand.logo}
                        alt=""
                        aria-hidden
                        className="h-12 w-auto max-w-full object-contain sm:h-16"
                    />
                    {brand.tagline ? (
                        <p className="mt-4 text-base sm:text-lg">
                            {brand.tagline}
                        </p>
                    ) : null}
                </div>
            </section>

            {brand.description || brand.websiteUrl ? (
                <section className="flex flex-col items-center gap-6 py-12 sm:py-16">
                    {brand.description ? (
                        <p className="mx-auto max-w-4xl text-center text-sm leading-relaxed text-secondary-foreground sm:text-xl">
                            {brand.description}
                        </p>
                    ) : null}
                    {brand.websiteUrl ? (
                        <Button asChild variant="outline">
                            <a
                                href={brand.websiteUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Visit website
                                <ExternalLink aria-hidden />
                                <span className="sr-only">
                                    (opens in a new tab)
                                </span>
                            </a>
                        </Button>
                    ) : null}
                </section>
            ) : null}

            <section
                aria-labelledby="brand-collection-heading"
                className="pb-12"
            >
                <div className="mb-6 border-b border-sidebar-border pb-4">
                    <h2
                        id="brand-collection-heading"
                        className="text-2xl font-bold tracking-[-0.04em] uppercase sm:text-3xl"
                    >
                        Collection
                    </h2>
                </div>
                <EmptyState
                    title="Coming soon"
                    description={`The ${brand.name} collection is on its way. Check back soon.`}
                />
            </section>
        </div>
    );
};

export default BrandProfile;
