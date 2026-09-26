import { useState, type MouseEvent } from 'react';
import { ArrowDown } from 'lucide-react';
import SectionHeading from '@/components/section-heading';
import StoneCard from '@/components/stone-card';
import { Button } from '@/components/ui/button';
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
                    title="Stones"
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
