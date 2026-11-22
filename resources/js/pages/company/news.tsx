import { Head } from '@inertiajs/react';
import {
    PageIntro,
    body,
    frame,
    solidButton,
} from '@/components/public/company-ui';
import { StoryCard } from '@/components/landing/landing-sections';
import { JOURNAL, SOCIALS } from '@/components/public/public-links';
import PublicLayout from '@/layouts/public-layout';
import type { LandingShowcase } from '@/types/home';

/**
 * News: the latest finished showcases, each told as a story and opening its
 * page, then where Regray publishes the rest, its journal and its social
 * accounts. The system holds no other news, so none is made up here.
 */
export default function CompanyNews({
    stories,
}: {
    stories: LandingShowcase[];
}) {
    return (
        <PublicLayout>
            <Head title="News" />

            <PageIntro eyebrow="News" title="The latest from Regray">
                <p>
                    Homes and spaces we have recently finished across the
                    Philippines.
                </p>
            </PageIntro>

            {stories.length > 0 ? (
                <section
                    aria-label="Latest showcases"
                    className={`pb-[clamp(5rem,9vw,8rem)] ${frame}`}
                >
                    <ul className="grid grid-cols-1 gap-x-4 gap-y-12 min-[700px]:gap-x-7 sm:grid-cols-2 lg:grid-cols-3">
                        {stories.map((story) => (
                            <li key={story.slug}>
                                <StoryCard story={story} />
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}

            <section
                aria-labelledby="news-more"
                className="flex flex-col items-center gap-6 border-t border-border px-5 py-[clamp(4.5rem,8vw,7rem)] text-center"
            >
                <h2
                    id="news-more"
                    className="font-display text-[clamp(1.5rem,2vw,1.875rem)] font-light tracking-[0.02em] uppercase"
                >
                    More from Regray
                </h2>
                <p className={`max-w-150 ${body}`}>
                    Read about our stones and showcases in the Regray Journal, or
                    follow us for what is new each week.
                </p>
                <a
                    href={JOURNAL}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={`mt-2 ${solidButton}`}
                >
                    Read the Regray Journal ↗
                </a>
                <ul className="mt-2 flex flex-wrap justify-center gap-x-6 gap-y-2 text-[1.0625rem]">
                    {SOCIALS.map((social) => (
                        <li key={social.label}>
                            <a
                                href={social.href}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="underline-offset-4 hover:underline"
                            >
                                {social.label}
                            </a>
                        </li>
                    ))}
                </ul>
            </section>
        </PublicLayout>
    );
}
