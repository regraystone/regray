import { Link } from '@inertiajs/react';
import { show as homeShow } from '@/routes/home';
import type { ShowcaseCardData } from '@/types/home';

interface ShowcaseCardProps {
    showcase: ShowcaseCardData;
    priority?: boolean;
}

export default function ShowcaseCard({
    showcase,
    priority = false,
}: ShowcaseCardProps) {
    return (
        <Link href={homeShow.url({ slug: showcase.slug })} preserveScroll>
            <article className="stone-card overflow-hidden rounded-md border border-sidebar-border bg-background">
                <div className="stone-card-media relative aspect-[4/3] overflow-hidden bg-background">
                    <img
                        src={showcase.thumbnail}
                        alt={showcase.name}
                        loading={priority ? 'eager' : 'lazy'}
                        className="absolute inset-0 size-full object-cover"
                    />
                </div>
                <div className="px-3 py-3">
                    <span className="block truncate text-sm font-bold text-foreground">
                        {showcase.name}
                    </span>
                </div>
            </article>
        </Link>
    );
}
