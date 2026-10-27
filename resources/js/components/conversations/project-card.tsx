import { Link } from '@inertiajs/react';
import { ArrowRight, Check } from 'lucide-react';
import CardHeader from '@/components/conversations/card-header';
import { Badge } from '@/components/ui/badge';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import {
    PROJECT_STATUS_LABELS,
    paymentStatusText,
    projectHeadline,
    quotationStatusText,
} from '@/lib/thread-cards';
import { show as inquiryShow } from '@/routes/inquiry';
import { show as quoteShow } from '@/routes/quote';
import type {
    ProjectCard as ProjectCardData,
    ProjectStatus,
} from '@/types/message';

/**
 * The project status as a badge, in the inquiry badge's three treatments: a
 * solid badge once work has started, an outline while it has not, and
 * cancelled struck through.
 */
const ProjectStatusBadge = ({ status }: { status: ProjectStatus }) => {
    const variant =
        status === 'ongoing' || status === 'closed'
            ? 'default'
            : status === 'cancelled'
              ? 'secondary'
              : 'outline';

    return (
        <Badge
            variant={variant}
            className="gap-1 rounded-md px-2 py-0.5 text-[0.65rem] font-bold tracking-[0.05em] uppercase"
        >
            {status === 'closed' ? (
                <Check className="size-3" aria-hidden />
            ) : null}
            <span
                className={status === 'cancelled' ? 'line-through' : undefined}
            >
                {PROJECT_STATUS_LABELS[status]}
            </span>
        </Badge>
    );
};

/**
 * A project as a card in a thread, built from the `project_card` message. The
 * inquiry card's layout and size: `max-w-sm` wide, a `h-32` strip, and every
 * line on one line.
 *
 * The headline, badge and status line are the snapshot the card was posted
 * for (the project's, its quotation's and its payment's status), not whatever
 * they are now: a card is a record of a change.
 *
 * TODO(staff-inquiry-scope): like the inquiry card, the details link ends in
 * a 404 for staff until staff access to inquiry details is built.
 */
const ProjectCard = ({ card }: { card: ProjectCardData }) => {
    const { can } = usePermissions();
    const headline = projectHeadline(card.projectStatus);
    const detailsHref = can(permissions.quotationsView)
        ? quoteShow.url({ reference: card.reference })
        : inquiryShow.url({ reference: card.reference });

    return (
        <div
            className="mx-auto w-full max-w-sm space-y-1.5"
            data-testid="project-card"
        >
            <CardHeader headline={headline} occurredAt={card.occurredAt} />

            <Link
                href={detailsHref}
                aria-label={`${headline}: ${card.name}, inquiry ${card.reference}, ${quotationStatusText(card.quotationStatus)}, ${paymentStatusText(card.paymentStatus)}. View details`}
                className="block w-full overflow-hidden rounded-md bg-secondary text-left transition-opacity hover:opacity-85 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <span className="relative block h-32 bg-sidebar-border">
                    <span className="absolute inset-x-0 bottom-0 block min-w-0 space-y-0.5 bg-foreground/60 p-3">
                        <span
                            className="block truncate text-sm font-bold text-background"
                            data-testid="project-card-title"
                        >
                            {card.name}
                        </span>
                        <span
                            className="block truncate text-xs text-background/80"
                            data-testid="project-card-subline"
                        >
                            {card.reference} · {card.location}
                        </span>
                    </span>
                </span>

                <span className="flex items-center justify-between gap-2 p-3 text-xs text-secondary-foreground">
                    <span
                        className="flex min-w-0 items-center gap-1.5 overflow-hidden whitespace-nowrap"
                        data-testid="project-card-status"
                    >
                        <span className="shrink-0">
                            <ProjectStatusBadge status={card.projectStatus} />
                        </span>
                        <span aria-hidden>·</span>
                        <span className="truncate">
                            {quotationStatusText(card.quotationStatus)}
                            {' · '}
                            {paymentStatusText(card.paymentStatus)}
                        </span>
                    </span>

                    <span className="inline-flex shrink-0 items-center gap-1">
                        View
                        <ArrowRight className="size-3" aria-hidden />
                    </span>
                </span>
            </Link>
        </div>
    );
};

export default ProjectCard;
