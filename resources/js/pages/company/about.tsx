import { Head, Link } from '@inertiajs/react';
import {
    PageIntro,
    SectionTitle,
    WidePhoto,
    frame,
    outlineButton,
    solidButton,
} from '@/components/public/company-ui';
import { CONTACT, brandUrl, mailTo } from '@/components/public/public-links';
import PublicLayout from '@/layouts/public-layout';
import { brands as brandsPage, contact } from '@/routes/company';
import type { LandingBrand } from '@/types/home';

type Facts = { brands: number; stones: number; projects: number };

/**
 * About Regray: its story (as regraystone.com tells it), the group of brands,
 * its mission and vision, a few figures (1,000+ spaces installed, from the
 * company; the rest counted from the catalog), and how to partner or work
 * with Regray.
 */
export default function CompanyAbout({
    brands,
    facts,
}: {
    brands: LandingBrand[];
    facts: Facts;
}) {
    const figures = [
        { value: '1,000+', label: 'Spaces installed' },
        { value: String(facts.brands), label: 'Houses in the group' },
        { value: String(facts.stones), label: 'Stones in the collection' },
        { value: String(facts.projects), label: 'Featured projects' },
    ];

    return (
        <PublicLayout>
            <Head title="About Regray" />

            <PageIntro eyebrow="Discover" title="About Regray">
                <p>
                    Regray is a platform for stone and interiors: its own Regray
                    Stone and a group of European design houses, with one
                    showroom, one team and one account for every inquiry, from
                    the first idea to the final installation.
                </p>
            </PageIntro>

            <WidePhoto
                src="/images/regray-slabs.jpg"
                alt="A wall of book-matched quartzite slabs in a restaurant Regray finished"
            />

            <section
                id="story"
                aria-labelledby="about-story"
                className="mx-auto grid max-w-6xl scroll-mt-24 grid-cols-1 gap-x-16 gap-y-8 px-5 py-[clamp(3.5rem,7vw,6rem)] md:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]"
            >
                <h2
                    id="about-story"
                    className="font-display text-[clamp(1.625rem,2.2vw,2.125rem)] font-light"
                >
                    Our story
                </h2>
                <div className="flex flex-col gap-5 text-[1.0625rem] leading-relaxed text-pretty">
                    <p>
                        Regray Stone was established in 2022, and it started
                        because we really love natural stone. We wanted to share
                        that admiration with others, so we began by bringing in
                        beautiful stones like Patagonia Quartzite.
                    </p>
                    <p>
                        We carefully source premium natural stone from trusted
                        quarries, selecting only slabs that meet our standards
                        for strength, consistency and character. From expert
                        fabrication to meticulous installation, every detail is
                        handled with care, so each space keeps its beauty for
                        years.
                    </p>
                    <p>
                        Today Regray brings that same care to a group of
                        European houses in kitchens, wardrobes, doors and
                        sintered stone, and to one platform where clients send
                        an inquiry and follow it all the way to installation:
                        one client, and one stone, at a time.
                    </p>
                </div>
            </section>

            <section
                aria-label="Regray in figures"
                className={`pb-[clamp(5rem,9vw,8rem)] ${frame}`}
            >
                <dl className="mx-auto grid max-w-5xl grid-cols-2 gap-8 lg:grid-cols-4">
                    {figures.map((figure) => (
                        <div
                            key={figure.label}
                            className="flex flex-col items-center gap-2 border-t border-foreground pt-5 text-center"
                        >
                            <dd className="order-first font-display text-[clamp(2rem,3vw,2.75rem)] font-light tabular-nums">
                                {figure.value}
                            </dd>
                            <dt className="text-xs tracking-[0.12em] text-muted-foreground uppercase">
                                {figure.label}
                            </dt>
                        </div>
                    ))}
                </dl>
            </section>

            <section
                id="group"
                aria-labelledby="about-group"
                className={`scroll-mt-24 pb-[clamp(5rem,9vw,8rem)] ${frame}`}
            >
                <SectionTitle id="about-group" title="The Regray Group">
                    Each house keeps its craft and identity; together they share
                    one showroom, one team and one platform.
                </SectionTitle>
                <ul className="mx-auto grid max-w-6xl grid-cols-2 gap-4 min-[700px]:gap-7 sm:grid-cols-3">
                    {brands.map((brand) => (
                        <li key={brand.slug}>
                            <Link
                                href={brandUrl(brand.slug)}
                                className="group relative flex aspect-3/2 items-center justify-center overflow-hidden bg-night"
                            >
                                {brand.thumbnail ? (
                                    <img
                                        src={brand.thumbnail}
                                        alt=""
                                        loading="lazy"
                                        className="absolute inset-0 size-full object-cover opacity-70 transition-[opacity,transform] duration-700 group-hover:scale-[1.03] group-hover:opacity-90 motion-reduce:transition-none"
                                    />
                                ) : null}
                                <img
                                    src={brand.logo}
                                    alt={brand.name}
                                    className="relative h-[clamp(1.5rem,3vw,2.5rem)] max-w-[70%] object-contain"
                                />
                            </Link>
                        </li>
                    ))}
                </ul>
                <div className="mt-10 flex justify-center">
                    <Link href={brandsPage.url()} className={outlineButton}>
                        All brands
                    </Link>
                </div>
            </section>

            <section
                id="vision"
                aria-labelledby="about-vision"
                className="scroll-mt-24 bg-black px-5 py-[clamp(4.5rem,9vw,8rem)] text-white"
            >
                <h2 id="about-vision" className="sr-only">
                    Mission & vision
                </h2>
                <div className="mx-auto grid max-w-6xl grid-cols-1 gap-14 md:grid-cols-2">
                    {[
                        {
                            title: 'Our mission',
                            text: 'To provide our customers with durable, stylish products that combine natural beauty with luxurious design, ensuring lasting quality and customer satisfaction.',
                        },
                        {
                            title: 'Our vision',
                            text: 'To become a leading brand in bespoke natural stone and furniture design, known for elevating spaces with timeless elegance and unmatched craftsmanship.',
                        },
                    ].map((statement) => (
                        <div
                            key={statement.title}
                            className="flex flex-col gap-5 border-t border-white/40 pt-6"
                        >
                            <p className="text-xs tracking-[0.14em] text-white/70 uppercase">
                                {statement.title}
                            </p>
                            <p className="font-display text-[clamp(1.375rem,2vw,1.875rem)] leading-snug font-light text-pretty">
                                {statement.text}
                            </p>
                        </div>
                    ))}
                </div>
            </section>

            <div className="mx-auto grid max-w-6xl grid-cols-1 gap-x-16 gap-y-14 px-5 py-[clamp(4.5rem,9vw,8rem)] md:grid-cols-2">
                <section
                    id="partnerships"
                    aria-labelledby="about-partnerships"
                    className="flex scroll-mt-24 flex-col items-start gap-5"
                >
                    <h2
                        id="about-partnerships"
                        className="font-display text-[clamp(1.5rem,2vw,1.875rem)] font-light"
                    >
                        Partnerships
                    </h2>
                    <p className="text-[1.0625rem] leading-relaxed text-pretty">
                        Architects, interior designers, contractors and
                        suppliers: we would be glad to work with you, from
                        specifying a stone to supplying a whole project.
                    </p>
                    <Link
                        href={`${contact.url()}#trade`}
                        className={solidButton}
                    >
                        Partner with us
                    </Link>
                </section>
                <section
                    id="careers"
                    aria-labelledby="about-careers"
                    className="flex scroll-mt-24 flex-col items-start gap-5"
                >
                    <h2
                        id="about-careers"
                        className="font-display text-[clamp(1.5rem,2vw,1.875rem)] font-light"
                    >
                        Careers
                    </h2>
                    <p className="text-[1.0625rem] leading-relaxed text-pretty">
                        Interested in joining the Regray team? Email us at{' '}
                        <a
                            href={mailTo('Careers at Regray')}
                            className="underline underline-offset-4"
                        >
                            {CONTACT.email}
                        </a>{' '}
                        and tell us about yourself.
                    </p>
                    <a
                        href={mailTo('Careers at Regray')}
                        className={outlineButton}
                    >
                        Email the team
                    </a>
                </section>
            </div>
        </PublicLayout>
    );
}
