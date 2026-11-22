import { Archive, Eye, Pencil } from 'lucide-react';
import type { ReactNode } from 'react';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

/** What a card's cover shows: its image, a logo over it, and the letters to fall back on. */
export type AdminCardCoverData = {
    image: string | null;
    logo?: string | null;
    initials: string;
};

/**
 * A card's cover: its image, or its logo (else its initials) on a muted
 * ground when it has none, with the logo on its own, no chip, in the center
 * of the image (shown once when there is no image, as the placeholder already
 * has it there). The cards and the View drawers of the admin's Brands and
 * Showcase pages open with it.
 */
export const AdminCardCover = ({
    cover,
    className,
}: {
    cover: AdminCardCoverData;
    className?: string;
}) => (
    <div className={cn('relative', className)}>
        <div className="aspect-[16/9] w-full overflow-hidden bg-muted">
            {cover.image ? (
                <img
                    src={cover.image}
                    alt=""
                    loading="lazy"
                    className="size-full object-cover"
                />
            ) : (
                <div className="flex size-full items-center justify-center">
                    {cover.logo ? (
                        <img
                            src={cover.logo}
                            alt=""
                            loading="lazy"
                            className="max-h-[40%] max-w-[50%] object-contain opacity-80"
                        />
                    ) : (
                        <span className="text-3xl font-bold tracking-[-0.03em] text-secondary-foreground">
                            {cover.initials}
                        </span>
                    )}
                </div>
            )}
        </div>
        {cover.logo && cover.image ? (
            <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                <img
                    src={cover.logo}
                    alt=""
                    loading="lazy"
                    className="h-auto max-h-12 w-auto max-w-[35%] object-contain drop-shadow-[0_1px_3px_rgba(0,0,0,0.45)]"
                />
            </div>
        ) : null}
    </div>
);

/** One of a card's footer actions, named with its subject for a screen reader. */
const CardAction = ({
    icon,
    label,
    subject,
    onClick,
    destructive = false,
    className,
}: {
    icon: ReactNode;
    label: string;
    subject: string;
    onClick: () => void;
    destructive?: boolean;
    className?: string;
}) => (
    <Button
        type="button"
        variant="ghost"
        size="sm"
        onClick={onClick}
        className={cn(
            'px-2.5',
            destructive &&
                'text-destructive hover:bg-destructive/10 hover:opacity-100',
            className,
        )}
    >
        {icon}
        {label}
        <span className="sr-only"> {subject}</span>
    </Button>
);

/**
 * A record on an admin page as a catalogue tile (a brand, a showcase): its
 * cover, title and subtitle, its status and a short count, then the actions
 * the server allows (View, Edit, and Archive on the right).
 *
 * The title is the card's link to View, with the focus ring; the cover opens
 * View too, for the mouse, without a second stop for the keyboard.
 */
const AdminCard = ({
    title,
    subtitle,
    subtitlePlaceholder,
    cover,
    status,
    meta,
    actions,
    onView,
    onEdit,
    onArchive,
    testId = 'admin-card',
}: {
    title: string;
    subtitle: string | null;
    /** Shown, lighter, when there is no subtitle. */
    subtitlePlaceholder: string;
    cover: AdminCardCoverData;
    status: { active: boolean; labels: [string, string] };
    meta: ReactNode;
    /** What this reader may do: `view`, `edit`, `archive`, from the server. */
    actions: string[];
    onView: () => void;
    onEdit: () => void;
    onArchive: () => void;
    testId?: string;
}) => {
    const can = (action: string) => actions.includes(action);

    return (
        <article
            className="flex min-w-0 flex-col border border-sidebar-border bg-background"
            data-testid={testId}
        >
            <div
                className="cursor-pointer"
                onClick={onView}
                aria-hidden
                data-testid={`${testId}-cover`}
            >
                <AdminCardCover cover={cover} />
            </div>

            <div className="flex flex-1 flex-col gap-3 p-4">
                <div className="min-w-0">
                    <h2 className="text-lg leading-tight font-bold tracking-[-0.03em]">
                        <button
                            type="button"
                            onClick={onView}
                            title={title}
                            className="block max-w-full truncate text-left underline-offset-4 hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            {title}
                        </button>
                    </h2>
                    <p
                        className={cn(
                            'mt-1 line-clamp-2 text-sm',
                            subtitle
                                ? 'text-secondary-foreground'
                                : 'text-secondary-foreground/60 italic',
                        )}
                    >
                        {subtitle || subtitlePlaceholder}
                    </p>
                </div>

                <div className="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-secondary-foreground">
                    <StatusBadge
                        active={status.active}
                        labels={status.labels}
                    />
                    <span>{meta}</span>
                </div>
            </div>

            <div className="flex items-center gap-1 border-t border-sidebar-border px-2 py-2">
                {can('view') ? (
                    <CardAction
                        icon={<Eye aria-hidden />}
                        label="View"
                        subject={title}
                        onClick={onView}
                    />
                ) : null}
                {can('edit') ? (
                    <CardAction
                        icon={<Pencil aria-hidden />}
                        label="Edit"
                        subject={title}
                        onClick={onEdit}
                    />
                ) : null}
                {can('archive') ? (
                    <CardAction
                        icon={<Archive aria-hidden />}
                        label="Archive"
                        subject={title}
                        onClick={onArchive}
                        destructive
                        className="ml-auto"
                    />
                ) : null}
            </div>
        </article>
    );
};

/** A card's shape while the list reloads. */
export const AdminCardSkeleton = ({
    testId = 'admin-card',
}: {
    testId?: string;
}) => (
    <div
        className="flex flex-col border border-sidebar-border"
        data-testid={`${testId}-skeleton`}
    >
        <Skeleton className="aspect-[16/9] w-full rounded-none" />
        <div className="space-y-2 p-4">
            <Skeleton className="h-5 w-1/2" />
            <Skeleton className="h-4 w-4/5" />
            <Skeleton className="mt-3 h-4 w-1/3" />
        </div>
        <div className="flex gap-2 border-t border-sidebar-border px-3 py-3">
            <Skeleton className="h-6 w-16" />
            <Skeleton className="h-6 w-16" />
        </div>
    </div>
);

export default AdminCard;
