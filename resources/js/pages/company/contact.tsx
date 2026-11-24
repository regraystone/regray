import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Mail, Phone } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    body,
    frame,
    label,
    outlineButton,
    solidButton,
} from '@/components/public/company-ui';
import {
    CONTACT,
    SHOWROOM,
    SHOWROOM_MAP,
    SHOWROOM_MAP_EMBED,
    SOCIALS,
    mailTo,
} from '@/components/public/public-links';
import SocialIcon from '@/components/social-icon';
import PublicLayout from '@/layouts/public-layout';
import { index as inboxIndex } from '@/routes/inbox';
import { book } from '@/routes/profile/schedule';
import { inquire } from '@/routes/quote';

/** A section's title. */
const sectionTitle =
    'font-display text-[clamp(1.75rem,2.6vw,2.5rem)] leading-[1.15] font-light text-balance';

/** One way to reach the team: who it is for, and what to do. */
const Channel = ({
    id,
    title,
    children,
    actions,
}: {
    id: string;
    title: string;
    children: ReactNode;
    actions: ReactNode;
}) => (
    <section
        id={id}
        aria-labelledby={`contact-${id}`}
        className="flex scroll-mt-24 flex-col gap-4 border-t border-foreground pt-6"
    >
        <h3
            id={`contact-${id}`}
            className="font-display text-[clamp(1.25rem,1.6vw,1.5rem)] font-light"
        >
            {title}
        </h3>
        <p className={`text-muted-foreground ${body}`}>{children}</p>
        <div className="mt-1 flex flex-wrap gap-3">{actions}</div>
    </section>
);

/** A phone number or the email, as a tile that calls or writes. */
const ContactTile = ({
    href,
    icon,
    children,
}: {
    href: string;
    icon: ReactNode;
    children: ReactNode;
}) => (
    <a
        href={href}
        className="flex min-h-16 items-center justify-center gap-3 border border-border bg-background px-5 py-4 text-[1.0625rem] font-medium transition-colors hover:border-foreground"
    >
        <span aria-hidden className="shrink-0">
            {icon}
        </span>
        <span className="break-all">{children}</span>
    </a>
);

/**
 * Contact, arranged as regraystone.com's is, since every brand is reached
 * through the one Regray team for now: a photo with the title, Speak with our
 * team (a tile for each phone number and the email), Follow us (each social
 * account), the showroom (the map, its hours and address, directions), and
 * then How can we help?, the way in for each kind of question: a quotation
 * goes through an inquiry in the client's account, the others are an email
 * with its subject filled in.
 *
 * The anchors (#info, #general, #sales, #trade, #business) are what the
 * menu's Contact panel links to.
 */
export default function CompanyContact() {
    return (
        <PublicLayout>
            <Head title="Contact" />

            <section aria-labelledby="contact-title" className={frame}>
                <div className="relative h-[min(60svh,34rem)] min-h-80 overflow-hidden rounded-md bg-night text-white">
                    <img
                        src="/images/regray-lounge.jpg"
                        alt=""
                        fetchPriority="high"
                        className="size-full object-cover object-[center_60%]"
                    />
                    <div
                        aria-hidden
                        className="absolute inset-0 bg-[linear-gradient(180deg,rgb(0_0_0/0)_35%,rgb(0_0_0/0.65)_100%)]"
                    />
                    <div className="absolute inset-x-[clamp(1.25rem,4vw,4rem)] bottom-[clamp(1.75rem,5vw,3.5rem)] flex flex-col gap-3">
                        <p className={label}>Regray</p>
                        <h1
                            id="contact-title"
                            className="font-display text-[clamp(2.5rem,5vw,4.5rem)] leading-none font-light"
                        >
                            Contact us
                        </h1>
                    </div>
                </div>
            </section>

            <section
                id="info"
                aria-labelledby="contact-team"
                className={`scroll-mt-24 pt-[clamp(3rem,6vw,5rem)] ${frame}`}
            >
                <div className="flex flex-col items-center gap-[clamp(2rem,4vw,3rem)] rounded-md bg-muted px-[clamp(1.25rem,4vw,3rem)] py-[clamp(3rem,6vw,5rem)] text-center">
                    <div className="flex flex-col items-center gap-4">
                        <h2 id="contact-team" className={sectionTitle}>
                            Speak with our team
                        </h2>
                        <p className={`max-w-170 ${body}`}>
                            Have a question? Our team is ready to guide you,
                            answer your questions and help with your project.
                        </p>
                    </div>
                    <div className="grid w-full max-w-6xl grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1.4fr]">
                        {CONTACT.phones.map((phone) => (
                            <ContactTile
                                key={phone.href}
                                href={phone.href}
                                icon={<Phone className="size-5" />}
                            >
                                {phone.label}
                            </ContactTile>
                        ))}
                        <ContactTile
                            href={`mailto:${CONTACT.email}`}
                            icon={<Mail className="size-5" />}
                        >
                            {CONTACT.email}
                        </ContactTile>
                    </div>
                </div>
            </section>

            <section
                aria-labelledby="contact-follow"
                className={`pt-[clamp(1.5rem,3vw,2.5rem)] ${frame}`}
            >
                <div className="relative overflow-hidden rounded-md bg-night px-[clamp(1.25rem,4vw,3rem)] py-[clamp(3.5rem,7vw,6rem)] text-white">
                    <img
                        src="/images/regray-auth.jpg"
                        alt=""
                        loading="lazy"
                        className="absolute inset-0 size-full object-cover"
                    />
                    <div aria-hidden className="absolute inset-0 bg-black/60" />
                    <div className="relative flex flex-col items-center gap-[clamp(2rem,4vw,3rem)] text-center">
                        <h2
                            id="contact-follow"
                            className={`max-w-170 ${sectionTitle}`}
                        >
                            Follow us and discover the beauty of natural stone
                        </h2>
                        <ul className="grid w-full max-w-160 grid-cols-1 gap-3 min-[420px]:grid-cols-2">
                            {SOCIALS.map((social) => (
                                <li key={social.label}>
                                    <a
                                        href={social.href}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex min-h-14 items-center justify-center gap-3 border border-white/45 bg-black/15 px-5 text-[1.0625rem] font-medium transition-colors hover:border-white hover:bg-white hover:text-black"
                                    >
                                        <SocialIcon platform={social.icon} />
                                        {social.label}
                                        <span className="sr-only">
                                            {' '}
                                            (opens in a new tab)
                                        </span>
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            </section>

            <section
                id="showroom"
                aria-labelledby="contact-showroom"
                className={`scroll-mt-24 pt-[clamp(3rem,6vw,5rem)] ${frame}`}
            >
                <div className="aspect-16/9 overflow-hidden rounded-md bg-muted sm:aspect-21/9">
                    <iframe
                        title="Map of the Regray showroom in Makati"
                        src={SHOWROOM_MAP_EMBED}
                        loading="lazy"
                        referrerPolicy="no-referrer-when-downgrade"
                        className="size-full border-0"
                    />
                </div>
                <div className="mx-auto flex max-w-190 flex-col items-center gap-4 pt-[clamp(2rem,4vw,3rem)] text-center">
                    <h2 id="contact-showroom" className={sectionTitle}>
                        Visit our showroom
                    </h2>
                    <p className={`text-muted-foreground ${body}`}>
                        See and touch our stones, kitchens and furniture in
                        person, at our showroom in Makati.
                    </p>
                    <p className="text-[1.0625rem] font-semibold">
                        {SHOWROOM.hours}
                    </p>
                    <p className={`text-muted-foreground ${body}`}>
                        {SHOWROOM.address}
                    </p>
                    <div className="mt-2 flex w-full flex-col justify-center gap-3 sm:w-auto sm:flex-row">
                        <a
                            href={SHOWROOM_MAP}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={solidButton}
                        >
                            Get directions
                            <ArrowUpRight
                                aria-hidden
                                className="ml-1.5 size-4"
                            />
                            <span className="sr-only">
                                {' '}
                                (opens in a new tab)
                            </span>
                        </a>
                        <Link href={book.url()} className={outlineButton}>
                            Book a visit
                        </Link>
                    </div>
                </div>
            </section>

            <section
                aria-labelledby="contact-help"
                className={`pt-[clamp(4.5rem,8vw,7rem)] pb-[clamp(5rem,9vw,8rem)] ${frame}`}
            >
                <h2
                    id="contact-help"
                    className={`mb-[clamp(2rem,4vw,3rem)] text-center ${sectionTitle}`}
                >
                    How can we help?
                </h2>
                <div className="mx-auto grid max-w-6xl grid-cols-1 gap-x-12 gap-y-12 md:grid-cols-2">
                    <Channel
                        id="general"
                        title="General inquiries"
                        actions={
                            <>
                                <a
                                    href={mailTo('General inquiry')}
                                    className={solidButton}
                                >
                                    Email us
                                </a>
                                <Link
                                    href={inboxIndex.url()}
                                    className={outlineButton}
                                >
                                    Message us
                                </Link>
                            </>
                        }
                    >
                        Questions about our stones, brands or services? Email or
                        message our team.
                    </Channel>

                    <Channel
                        id="sales"
                        title="Sales & quotations"
                        actions={
                            <>
                                <Link
                                    href={inquire.url()}
                                    className={solidButton}
                                >
                                    Request a quotation
                                </Link>
                                <Link
                                    href={book.url()}
                                    className={outlineButton}
                                >
                                    Book a visit
                                </Link>
                            </>
                        }
                    >
                        Tell us what you need. We visit to measure your space,
                        send you a clear price, and keep you updated by email.
                    </Channel>

                    <Channel
                        id="trade"
                        title="Architect & designer partnerships"
                        actions={
                            <a
                                href={mailTo(
                                    'Architect & designer partnership',
                                )}
                                className={solidButton}
                            >
                                Email the team
                            </a>
                        }
                    >
                        Specifying stone, kitchens, wardrobes or doors for a
                        client? Tell us about your practice and your projects.
                    </Channel>

                    <Channel
                        id="business"
                        title="Supplier & business inquiries"
                        actions={
                            <a
                                href={mailTo('Supplier & business inquiry')}
                                className={solidButton}
                            >
                                Email the team
                            </a>
                        }
                    >
                        Quarries, suppliers and businesses who would like to
                        work with Regray: email us with what you offer.
                    </Channel>
                </div>
            </section>
        </PublicLayout>
    );
}
