import { useEffect, useState, type MouseEvent } from 'react';
import { ArrowDown } from 'lucide-react';
import SectionHeading from '@/components/section-heading';
import StoneCard from '@/components/stone-card';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { products, type FeedItem } from '@/data/constant';

const cucineLogo = '/images/brands/cucine_logo.webp';
const ascaleLogo = '/images/brands/ascale_logo.webp';
const faerLogo = '/images/brands/faer_logo.webp';
const reformLogo = '/images/brands/reform_logo.webp';
const resLogo = '/images/brands/res_logo.webp';
const hero = '/images/regray-hero.jpg';

type Filter = 'latest' | 'recommended';

const brandLogos = [
    { src: cucineLogo, alt: 'Cucine' },
    { src: ascaleLogo, alt: 'Ascale' },
    { src: faerLogo, alt: 'Faer' },
    { src: reformLogo, alt: 'Reform' },
    { src: resLogo, alt: 'Res' },
];

const BrandCarousel = () => (
    <section
        aria-label="Featured in"
        className="mt-2 border-y border-sidebar-border bg-background py-10"
    >
        <div className="brand-marquee">
            <div className="logo-track">
                {[brandLogos, brandLogos].map((group, groupIndex) => (
                    <div
                        key={`group-${groupIndex}`}
                        className="logo-group"
                        aria-hidden={groupIndex > 0}
                    >
                        {group.map((brand, index) => (
                            <img
                                key={`${brand.alt}-${index}`}
                                src={brand.src}
                                alt={groupIndex === 0 ? brand.alt : ''}
                                className="h-16 shrink-0 object-contain grayscale"
                            />
                        ))}
                    </div>
                ))}
            </div>
        </div>
    </section>
);

const handleBrowseClick = (e: MouseEvent<HTMLAnchorElement>) => {
    e.preventDefault();
    document.getElementById('products')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
};

const Hero = () => (
    <section className="relative flex min-h-[280px] items-center overflow-hidden text-on-image sm:min-h-[480px]">
        <img
            src={hero}
            alt=""
            aria-hidden
            className="absolute inset-0 size-full object-cover"
        />
        <div className="image-tint absolute inset-0" aria-hidden />
        <div className="relative z-10 mx-auto w-full max-w-6xl px-5 py-10 sm:py-20">
            <p className="eyebrow text-on-image/85">
                Stone &amp; furniture, all in one place
            </p>
            <h1 className="hero-title mt-5 max-w-3xl text-on-image">
                Every brand.
                <br />
                <span className="text-on-image/70">One platform.</span>
            </h1>
            <p className="mt-6 hidden max-w-xl text-base leading-relaxed text-on-image/90 sm:block sm:text-lg">
                Explore a curated catalog of natural stone products and finished
                projects from leading studios — saved, compared, and requested
                in a single workspace.
            </p>
            <Button asChild className="mt-7 sm:mt-9">
                <a href="#products" onClick={handleBrowseClick}>
                    Browse More <ArrowDown className="size-4" aria-hidden />
                </a>
            </Button>
        </div>
    </section>
);

const HomeSkeleton = () => (
    <div className="mx-auto w-full max-w-6xl">
        <section className="relative flex min-h-[280px] items-center overflow-hidden sm:min-h-[480px]">
            <Skeleton className="absolute inset-0 rounded-none" />
        </section>

        <section className="mt-2 border-y border-sidebar-border bg-background py-10">
            <div className="flex items-center justify-center gap-8 sm:gap-14">
                {Array.from({ length: 5 }, (_, index) => (
                    <Skeleton key={index} className="h-16 w-24 sm:w-32" />
                ))}
            </div>
        </section>

        <section className="md:12 mt-8">
            <div className="mb-6 border-b border-sidebar-border pb-4">
                <div className="flex items-end justify-between gap-4">
                    <Skeleton className="h-8 w-40" />
                    <div className="hidden items-center gap-5 sm:flex">
                        <Skeleton className="h-5 w-16" />
                        <Skeleton className="h-5 w-24" />
                    </div>
                </div>
                <div className="mt-4 flex items-center gap-2.5 sm:hidden">
                    <Skeleton className="h-8 w-20" />
                    <Skeleton className="h-8 w-28" />
                </div>
            </div>
            <div className="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3">
                {Array.from({ length: 6 }, (_, index) => (
                    <div
                        key={index}
                        className="border border-sidebar-border bg-background"
                    >
                        <Skeleton className="aspect-[4/3] w-full rounded-none" />
                    </div>
                ))}
            </div>
        </section>
    </div>
);

const Home = () => {
    const [filter, setFilter] = useState<Filter>('latest');
    const [savedIds, setSavedIds] = useState<Set<string>>(new Set());
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        const timer = window.setTimeout(() => setIsLoading(false), 700);

        return () => window.clearTimeout(timer);
    }, []);

    if (isLoading) {
        return <HomeSkeleton />;
    }

    const toggleSave = (id: string) => {
        setSavedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const visibleItems: FeedItem[] = (() => {
        if (filter === 'recommended') {
            return products.filter((item) => item.recommended);
        }
        return products;
    })();

    return (
        <div className="mx-auto w-full max-w-6xl">
            <Hero />
            <BrandCarousel />
            <section
                id="products"
                aria-labelledby="products-heading"
                className="md:12 mt-8"
            >
                <SectionHeading
                    title="Our Stones"
                    tabs={[
                        { value: 'latest', label: 'Latest' },
                        { value: 'recommended', label: 'Recommended' },
                    ]}
                    active={filter}
                    onTabChange={setFilter}
                />
                <div className="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3">
                    {visibleItems.map((item) => (
                        <StoneCard
                            key={item.id}
                            item={item}
                            aspect="aspect-[4/3]"
                            saved={savedIds.has(item.id)}
                            onToggleSave={() => toggleSave(item.id)}
                        />
                    ))}
                </div>
            </section>
        </div>
    );
};

export default Home;
