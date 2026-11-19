import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    PageIntro,
    frame,
    outlineButton,
    solidButton,
} from '@/components/public/company-ui';
import {
    CONTACT,
    SHOWROOM,
    SHOWROOM_MAP,
    SOCIALS,
    mailTo,
} from '@/components/public/public-links';
import PublicLayout from '@/layouts/public-layout';
import { index as inboxIndex } from '@/routes/inbox';
import { book } from '@/routes/profile/schedule';
import { inquire } from '@/routes/quote';

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
        <h2
            id={`contact-${id}`}
            className="font-display text-[clamp(1.25rem,1.6vw,1.5rem)] font-light"
        >
            {title}
        </h2>
        <p className="text-[0.9375rem] leading-relaxed text-pretty text-muted-foreground">
            {children}
        </p>
        <div className="mt-1 flex flex-wrap gap-3">{actions}</div>
    </section>
);

/**
 * Contact: how to reach the team (phone, email, the showroom, social media)
 * and the way in for each kind of question. A quotation goes through an
 * inquiry in the client's account; the others are an email with its subject
 * filled in.
 */
export default function CompanyContact() {
    return (
        <PublicLayout>
            <Head title="Contact" />

            <PageIntro eyebrow="Contact" title="Connect with the Regray team">
                <p>
                    Call, email or visit us, or send an inquiry from your Regray
                    account and follow it from the first quotation to the final
                    installation.
                </p>
            </PageIntro>

            <section
                id="info"
                aria-label="Contact information"
                className={`scroll-mt-24 pb-[clamp(4rem,8vw,7rem)] ${frame}`}
            >
                <dl className="mx-auto grid max-w-6xl grid-cols-1 gap-8 text-[0.9375rem] leading-relaxed sm:grid-cols-2 lg:grid-cols-4">
                    <div className="flex flex-col gap-2 border-t border-border pt-5">
                        <dt className="text-xs tracking-[0.14em] text-muted-foreground uppercase">
                            Call us
                        </dt>
                        {CONTACT.phones.map((phone) => (
                            <dd key={phone.href}>
                                <a
                                    href={phone.href}
                                    className="underline-offset-4 hover:underline"
                                >
                                    {phone.label}
                                </a>
                            </dd>
                        ))}
                    </div>
                    <div className="flex flex-col gap-2 border-t border-border pt-5">
                        <dt className="text-xs tracking-[0.14em] text-muted-foreground uppercase">
                            Email us
                        </dt>
                        <dd>
                            <a
                                href={`mailto:${CONTACT.email}`}
                                className="underline-offset-4 hover:underline"
                            >
                                {CONTACT.email}
                            </a>
                        </dd>
                    </div>
                    <div className="flex flex-col gap-2 border-t border-border pt-5">
                        <dt className="text-xs tracking-[0.14em] text-muted-foreground uppercase">
                            Visit the showroom
                        </dt>
                        <dd>{SHOWROOM.address}</dd>
                        <dd>{SHOWROOM.hours}</dd>
                        <dd>
                            <a
                                href={SHOWROOM_MAP}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="underline underline-offset-4"
                            >
                                Get directions ↗
                            </a>
                        </dd>
                    </div>
                    <div className="flex flex-col gap-2 border-t border-border pt-5">
                        <dt className="text-xs tracking-[0.14em] text-muted-foreground uppercase">
                            Follow us
                        </dt>
                        <dd className="flex flex-wrap gap-x-4 gap-y-1">
                            {SOCIALS.map((social) => (
                                <a
                                    key={social.label}
                                    href={social.href}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="underline-offset-4 hover:underline"
                                >
                                    {social.label}
                                </a>
                            ))}
                        </dd>
                    </div>
                </dl>
            </section>

            <div
                className={`mx-auto grid max-w-6xl grid-cols-1 gap-x-12 gap-y-12 pb-[clamp(5rem,9vw,8rem)] md:grid-cols-2 ${frame}`}
            >
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
                    Questions about our stones, brands or services. Email us, or
                    message the team from your Regray account.
                </Channel>

                <Channel
                    id="sales"
                    title="Sales & quotations"
                    actions={
                        <>
                            <Link href={inquire.url()} className={solidButton}>
                                Send an inquiry
                            </Link>
                            <Link href={book.url()} className={outlineButton}>
                                Book a visit
                            </Link>
                        </>
                    }
                >
                    Choose a service and your stones and send an inquiry: our
                    team visits and measures when needed, sends your quotation,
                    and keeps you updated by email at every step.
                </Channel>

                <Channel
                    id="trade"
                    title="Architect & designer partnerships"
                    actions={
                        <a
                            href={mailTo('Architect & designer partnership')}
                            className={solidButton}
                        >
                            Email the team
                        </a>
                    }
                >
                    Specifying stone, kitchens, wardrobes or doors for a client?
                    Tell us about your practice and your projects.
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
                    Quarries, suppliers and businesses who would like to work
                    with Regray: email us with what you offer.
                </Channel>
            </div>
        </PublicLayout>
    );
}
