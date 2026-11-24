import { Head, Link } from '@inertiajs/react';
import { frame } from '@/components/public/company-ui';
import PublicLayout from '@/layouts/public-layout';
import { show as showcaseShow } from '@/routes/stone';
import type { LandingShowcase } from '@/types/home';

/** A finished project as news: its photo, the year and the place, and its name. */
const NewsItem = ({ story }: { story: LandingShowcase }) => (
    <Link
        href={showcaseShow.url({ slug: story.slug })}
        className="group flex flex-col gap-5"
        data-testid="news-item"
    >
        <div className="aspect-3/2 overflow-hidden rounded-md bg-[#d8d4cd]">
            <img
                src={story.thumbnail}
                alt=""
                loading="lazy"
                className="size-full object-cover transition-transform duration-1600 ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.02] motion-reduce:transition-none"
            />
        </div>
        <div className="flex flex-col gap-2">
            <p className="text-[0.875rem] tracking-[0.04em] text-muted-foreground uppercase">
                {story.completedYear} · {story.location}
            </p>
            <h2 className="font-display text-[clamp(1.25rem,1.7vw,1.5rem)] leading-snug font-light text-pretty underline-offset-4 group-hover:underline">
                {story.name}
            </h2>
        </div>
    </Link>
);

/**
 * News, kept clean and minimal: a title, one line, and the latest finished
 * projects, two to a row from a tablet (one on a phone), each opening its
 * page. The system holds no other news, so none is made up here.
 */
export default function CompanyNews({
    stories,
}: {
    stories: LandingShowcase[];
}) {
    return (
        <PublicLayout>
            <Head title="News" />

            <section
                aria-labelledby="news-title"
                className={`flex flex-col gap-4 pt-[clamp(3.5rem,7vw,6rem)] pb-[clamp(2.5rem,5vw,4rem)] ${frame}`}
            >
                <h1
                    id="news-title"
                    className="font-display text-[clamp(2.25rem,4vw,3.5rem)] leading-none font-light tracking-[0.02em] uppercase"
                >
                    News
                </h1>
                <p className="max-w-150 text-[clamp(1.0625rem,1.3vw,1.1875rem)] leading-relaxed text-muted-foreground">
                    Homes and spaces we have recently finished across the
                    Philippines.
                </p>
            </section>

            <section
                aria-label="Recently finished"
                className={`border-t border-border pt-[clamp(2.5rem,5vw,4rem)] pb-[clamp(5rem,9vw,8rem)] ${frame}`}
            >
                {stories.length > 0 ? (
                    <ul className="grid grid-cols-1 gap-x-[clamp(1.5rem,3vw,3rem)] gap-y-[clamp(3rem,6vw,5rem)] sm:grid-cols-2">
                        {stories.map((story) => (
                            <li key={story.slug}>
                                <NewsItem story={story} />
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-[1.0625rem] text-muted-foreground">
                        No news yet. Come back soon.
                    </p>
                )}
            </section>
        </PublicLayout>
    );
}
