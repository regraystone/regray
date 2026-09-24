import { useState, type MouseEvent } from 'react';
import { ArrowDown } from 'lucide-react';
import FeedCard from '@/components/feed-card';
import StaggeredGrid from '@/components/staggered-grid';
import { Button } from '@/components/ui/button';
import { products, type FeedItem } from '@/data/constant';

const cucineLogo = '/images/brands/cucine_logo.webp';
const ascaleLogo = '/images/brands/ascale_logo.webp';
const faerLogo = '/images/brands/faer_logo.webp';
const reformLogo = '/images/brands/reform_logo.webp';
const resLogo = '/images/brands/res_logo.webp';
const hero = '/images/regray-hero.jpg';

type Filter = 'latest' | 'recommended' | 'liked';

const SectionHeading = ({
    title,
    tabs,
    active,
    onTabChange,
}: {
    title: string;
    tabs: Array<{ value: Filter; label: string }>;
    active: Filter;
    onTabChange: (value: Filter) => void;
}) => (
    <div className="mb-6 flex flex-col gap-4 border-b border-sidebar-border pb-4">
        <div className="flex items-end justify-between gap-4">
            <h2 className="text-2xl font-bold tracking-[-0.04em] sm:text-3xl">
                {title}
            </h2>
            <div
                className="hidden items-center gap-5 text-sm sm:flex"
                role="tablist"
                aria-label={`Filter ${title}`}
            >
                {tabs.map((tab) => {
                    const isActive = active === tab.value;
                    return (
                        <button
                            key={tab.value}
                            type="button"
                            role="tab"
                            aria-selected={isActive}
                            onClick={() => onTabChange(tab.value)}
                            className={`-my-2.5 px-2.5 py-2.5 transition-opacity hover:opacity-70 ${
                                isActive
                                    ? 'font-bold text-foreground underline decoration-foreground underline-offset-4'
                                    : 'text-secondary-foreground'
                            }`}
                        >
                            {tab.label}
                        </button>
                    );
                })}
            </div>
        </div>
        <div
            className="flex items-center gap-2.5 sm:hidden"
            role="tablist"
            aria-label={`Filter ${title}`}
        >
            {tabs.map((tab) => {
                const isActive = active === tab.value;
                return (
                    <Button
                        key={tab.value}
                        type="button"
                        role="tab"
                        aria-selected={isActive}
                        variant={isActive ? 'default' : 'secondary'}
                        size="sm"
                        className="px-3 py-1.5"
                        onClick={() => onTabChange(tab.value)}
                    >
                        {tab.label}
                    </Button>
                );
            })}
        </div>
    </div>
);

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

const Home = () => {
    const [filter, setFilter] = useState<Filter>('latest');
    const [savedIds, setSavedIds] = useState<Set<string>>(new Set());

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
        if (filter === 'liked') {
            return products.filter((item) => savedIds.has(item.id));
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
                className="mt-12 scroll-mt-24"
            >
                <SectionHeading
                    title="Products"
                    tabs={[
                        { value: 'latest', label: 'Latest' },
                        { value: 'recommended', label: 'Recommended' },
                        { value: 'liked', label: 'Liked' },
                    ]}
                    active={filter}
                    onTabChange={setFilter}
                />
                <StaggeredGrid
                    items={visibleItems}
                    emptyState={
                        <p className="py-16 text-center text-sm font-semibold text-secondary-foreground">
                            No liked products yet — tap the heart on a product
                            to save it here.
                        </p>
                    }
                    renderItem={(item, aspect) => (
                        <FeedCard
                            item={item}
                            aspect={aspect}
                            saved={savedIds.has(item.id)}
                            onToggleSave={() => toggleSave(item.id)}
                        />
                    )}
                />
            </section>
        </div>
    );
};

export default Home;
