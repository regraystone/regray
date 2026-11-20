import { Archive, Eye, Pencil } from 'lucide-react';
import type { ReactNode } from 'react';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { brandInitials, catalogueCount } from '@/lib/brand-admin';
import { cn } from '@/lib/utils';
import type { BrandRow } from '@/types/brand-admin';

/**
 * A brand's cover: its thumbnail, or its logo (else its initials) on a muted
 * ground when it has none, with the logo on its own, no chip, in the center
 * of the thumbnail (shown once when there is no thumbnail, as the placeholder
 * already has it there). The card and the View drawer both open with it.
 */
export const BrandCover = ({
    brand,
    className,
}: {
    brand: Pick<BrandRow, 'name' | 'logo' | 'thumbnail'>;
    className?: string;
}) => (
    <div className={cn('relative', className)}>
        <div className="aspect-[16/9] w-full overflow-hidden bg-muted">
            {brand.thumbnail ? (
                <img
                    src={brand.thumbnail}
                    alt=""
                    loading="lazy"
                    className="size-full object-cover"
                />
            ) : (
                <div className="flex size-full items-center justify-center">
                    {brand.logo ? (
                        <img
                            src={brand.logo}
                            alt=""
                            loading="lazy"
                            className="max-h-[40%] max-w-[50%] object-contain opacity-80"
                        />
                    ) : (
                        <span className="text-3xl font-bold tracking-[-0.03em] text-secondary-foreground">
                            {brandInitials(brand.name)}
                        </span>
                    )}
                </div>
            )}
        </div>
        {brand.logo && brand.thumbnail ? (
            <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
                <img
                    src={brand.logo}
                    alt=""
                    loading="lazy"
                    className="h-auto max-h-12 w-auto max-w-[35%] object-contain drop-shadow-[0_1px_3px_rgba(0,0,0,0.45)]"
                />
            </div>
        ) : null}
    </div>
);

/** One of a card's footer actions, named with the brand for a screen reader. */
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
 * A brand on the admin's Brands page, as a catalogue tile: its cover, name
 * and tagline, whether it is visible and what it has in the catalogue, then
 * the actions the server allows (View, Edit, and Archive on the right).
 *
 * The name is the card's link to View, with the focus ring; the cover opens
 * View too, for the mouse, without a second stop for the keyboard.
 */
const BrandAdminCard = ({
    brand,
    onView,
    onEdit,
    onArchive,
}: {
    brand: BrandRow;
    onView: () => void;
    onEdit: () => void;
    onArchive: () => void;
}) => {
    const can = (action: string) => brand.actions.includes(action);

    return (
        <article
            className="flex min-w-0 flex-col border border-sidebar-border bg-background"
            data-testid="brand-card"
        >
            <div
                className="cursor-pointer"
                onClick={onView}
                aria-hidden
                data-testid="brand-card-cover"
            >
                <BrandCover brand={brand} />
            </div>

            <div className="flex flex-1 flex-col gap-3 p-4">
                <div className="min-w-0">
                    <h2 className="text-lg leading-tight font-bold tracking-[-0.03em]">
                        <button
                            type="button"
                            onClick={onView}
                            title={brand.name}
                            className="block max-w-full truncate text-left underline-offset-4 hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            {brand.name}
                        </button>
                    </h2>
                    <p
                        className={cn(
                            'mt-1 line-clamp-2 text-sm',
                            brand.tagline
                                ? 'text-secondary-foreground'
                                : 'text-secondary-foreground/60 italic',
                        )}
                    >
                        {brand.tagline ?? 'No tagline yet'}
                    </p>
                </div>

                <div className="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-secondary-foreground">
                    <StatusBadge
                        active={brand.isActive}
                        labels={['Visible', 'Hidden']}
                    />
                    <span>
                        {catalogueCount(
                            brand.stonesCount,
                            brand.furnitureCount,
                        )}
                    </span>
                </div>
            </div>

            <div className="flex items-center gap-1 border-t border-sidebar-border px-2 py-2">
                {can('view') ? (
                    <CardAction
                        icon={<Eye aria-hidden />}
                        label="View"
                        subject={brand.name}
                        onClick={onView}
                    />
                ) : null}
                {can('edit') ? (
                    <CardAction
                        icon={<Pencil aria-hidden />}
                        label="Edit"
                        subject={brand.name}
                        onClick={onEdit}
                    />
                ) : null}
                {can('archive') ? (
                    <CardAction
                        icon={<Archive aria-hidden />}
                        label="Archive"
                        subject={brand.name}
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
export const BrandAdminCardSkeleton = () => (
    <div
        className="flex flex-col border border-sidebar-border"
        data-testid="brand-card-skeleton"
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

export default BrandAdminCard;
