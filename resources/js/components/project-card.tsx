import { Link } from '@inertiajs/react';
import type { Project } from '@/data/projects';
import type { ProjectCardProps } from '@/types/home';

const ProjectCard = ({
    project,
    aspect,
    href,
    priority = false,
}: {
    project: Project | Pick<ProjectCardProps, 'name' | 'thumbnail'>;
    aspect: string;
    href?: string;
    priority?: boolean;
}) => {
    const content = (
        <article className="stone-card border border-sidebar-border bg-background">
            <div
                className={`stone-card-media relative overflow-hidden bg-background ${aspect}`}
            >
                <img
                    src={project.thumbnail}
                    alt={project.name}
                    loading={priority ? 'eager' : 'lazy'}
                    decoding="async"
                    className="absolute inset-0 size-full object-cover"
                />
            </div>
            <span className="block min-w-0 truncate py-4 pr-3 pl-3 text-sm font-bold text-foreground">
                {project.name}
            </span>
        </article>
    );

    if (href) {
        return (
            <Link href={href} className="block">
                {content}
            </Link>
        );
    }

    return content;
};

export default ProjectCard;
