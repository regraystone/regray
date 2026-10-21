import { Link } from '@inertiajs/react';
import { show as homeShow } from '@/routes/home';
import type { ProjectCardData } from '@/types/home';

interface ProjectCardProps {
    project: ProjectCardData;
    priority?: boolean;
}

export default function ProjectCard({
    project,
    priority = false,
}: ProjectCardProps) {
    return (
        <Link href={homeShow.url({ slug: project.slug })} preserveScroll>
            <article className="stone-card overflow-hidden rounded-md border border-sidebar-border bg-background">
                <div className="stone-card-media relative aspect-[4/3] overflow-hidden bg-background">
                    <img
                        src={project.thumbnail}
                        alt={project.name}
                        loading={priority ? 'eager' : 'lazy'}
                        className="absolute inset-0 size-full object-cover"
                    />
                </div>
                <div className="px-3 py-3">
                    <span className="block truncate text-sm font-bold text-foreground">
                        {project.name}
                    </span>
                </div>
            </article>
        </Link>
    );
}
