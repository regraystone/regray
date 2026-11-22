import { Head, Link } from '@inertiajs/react';
import {
    PageIntro,
    SectionTitle,
    WidePhoto,
    body,
    frame,
    label,
    outlineButton,
    solidButton,
    textLink,
} from '@/components/public/company-ui';
import {
    CONTACT,
    SHOWROOM,
    SHOWROOM_MAP,
    SHOWROOM_MAP_EMBED,
} from '@/components/public/public-links';
import PublicLayout from '@/layouts/public-layout';
import { index as inboxIndex } from '@/routes/inbox';
import { book } from '@/routes/profile/schedule';
import { show as showcaseShow } from '@/routes/stone';
import type { LandingShowcase } from '@/types/home';

/**
 * The showroom: an overview, a gallery of the showroom and the spaces Regray
 * has finished, where it is (with a map, the address, hours and phone numbers)
 * and how to book a visit.
 */
export default function CompanyShowroom({
    showcases,
}: {
    showcases: LandingShowcase[];
}) {
    return (
        <PublicLayout>
            <Head title="Showroom" />

            <PageIntro eyebrow="Showroom" title="See the stone in person">
                <p>
                    Visit our showroom in Makati to see and touch real stone
                    slabs, from quartzite and marble to backlit onyx, and talk
                    to our team about your home.
                </p>
            </PageIntro>

            <WidePhoto
                src="/images/regray-showroom.jpg"
                alt="The Regray showroom's glass front in Makati, with stone slabs on display inside"
                position="center 60%"
            />

            <section
                aria-label="Visiting the showroom"
                className="mx-auto grid max-w-5xl grid-cols-1 gap-10 px-5 py-[clamp(3.5rem,7vw,6rem)] text-center md:grid-cols-3"
            >
                {[
                    {
                        title: 'Browse the slabs',
                        text: 'See each slab’s colour, veining and finish up close, and compare stones side by side.',
                    },
                    {
                        title: 'Talk it through',
                        text: 'Our team helps you choose the stone, the finish and the service for your project.',
                    },
                    {
                        title: 'Get a quotation',
                        text: 'When you are ready, we measure your space and send you a quotation.',
                    },
                ].map((point) => (
                    <div
                        key={point.title}
                        className="flex flex-col gap-3 border-t border-foreground pt-5"
                    >
                        <h2 className="font-display text-[clamp(1.1875rem,1.4vw,1.3125rem)]">
                            {point.title}
                        </h2>
                        <p className={`text-muted-foreground ${body}`}>
                            {point.text}
                        </p>
                    </div>
                ))}
            </section>

            {showcases.length > 0 ? (
                <section
                    id="gallery"
                    aria-labelledby="showroom-gallery"
                    className={`scroll-mt-24 pb-[clamp(5rem,9vw,8rem)] ${frame}`}
                >
                    <SectionTitle id="showroom-gallery" title="Gallery">
                        The stones you see in the showroom, finished in spaces
                        across the Philippines.
                    </SectionTitle>
                    <ul className="grid grid-cols-1 gap-4 min-[700px]:gap-7 sm:grid-cols-2 lg:grid-cols-3">
                        {showcases.map((showcase) => (
                            <li key={showcase.slug}>
                                <Link
                                    href={showcaseShow.url({
                                        slug: showcase.slug,
                                    })}
                                    className="group flex flex-col gap-3"
                                >
                                    <div className="aspect-4/5 overflow-hidden rounded-md bg-muted">
                                        <img
                                            src={showcase.thumbnail}
                                            alt=""
                                            loading="lazy"
                                            className="size-full object-cover transition-transform duration-1600 ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.02] motion-reduce:transition-none"
                                        />
                                    </div>
                                    <span className="flex justify-between gap-4 border-t border-border pt-3 text-[1.0625rem]">
                                        <span>{showcase.name}</span>
                                        <span className="text-right text-muted-foreground">
                                            {showcase.location} ·{' '}
                                            {showcase.completedYear}
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}

            <section
                id="location"
                aria-labelledby="showroom-location"
                className={`grid scroll-mt-24 grid-cols-1 gap-x-12 gap-y-10 pb-[clamp(5rem,9vw,8rem)] lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] ${frame}`}
            >
                <div className="flex flex-col gap-6 self-center">
                    <h2
                        id="showroom-location"
                        className="font-display text-[clamp(1.5rem,2vw,1.875rem)] font-light tracking-[0.02em] uppercase"
                    >
                        Location & directions
                    </h2>
                    <dl className="flex flex-col gap-5 text-[1.0625rem] leading-relaxed">
                        <div>
                            <dt className={`${label} text-muted-foreground`}>
                                Address
                            </dt>
                            <dd>{SHOWROOM.address}</dd>
                        </div>
                        <div>
                            <dt className={`${label} text-muted-foreground`}>
                                Hours
                            </dt>
                            <dd>{SHOWROOM.hours}</dd>
                        </div>
                        <div>
                            <dt className={`${label} text-muted-foreground`}>
                                Call us
                            </dt>
                            <dd className="flex flex-wrap gap-x-4">
                                {CONTACT.phones.map((phone) => (
                                    <a
                                        key={phone.href}
                                        href={phone.href}
                                        className="underline-offset-4 hover:underline"
                                    >
                                        {phone.label}
                                    </a>
                                ))}
                            </dd>
                        </div>
                    </dl>
                    <a
                        href={SHOWROOM_MAP}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={`self-start ${textLink}`}
                    >
                        Get directions ↗
                    </a>
                </div>
                <div className="aspect-4/3 overflow-hidden rounded-md bg-muted lg:aspect-auto lg:min-h-105">
                    <iframe
                        title="Map of the Regray showroom in Makati"
                        src={SHOWROOM_MAP_EMBED}
                        loading="lazy"
                        referrerPolicy="no-referrer-when-downgrade"
                        className="size-full border-0"
                    />
                </div>
            </section>

            <section
                id="book"
                aria-labelledby="showroom-book"
                className="flex scroll-mt-24 flex-col items-center gap-6 px-5 pb-[clamp(5rem,9vw,8rem)] text-center"
            >
                <h2
                    id="showroom-book"
                    className="font-display text-[clamp(1.625rem,2.2vw,2.125rem)] font-light"
                >
                    Book a visit
                </h2>
                <p className={`max-w-150 ${body}`}>
                    Choose a time to visit the showroom, or ask us to come and
                    measure your space.
                </p>
                <div className="mt-2 flex flex-wrap justify-center gap-4">
                    <Link href={book.url()} className={solidButton}>
                        Book a visit
                    </Link>
                    <Link href={inboxIndex.url()} className={outlineButton}>
                        Ask the team first
                    </Link>
                </div>
            </section>
        </PublicLayout>
    );
}
