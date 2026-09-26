import { Link } from '@inertiajs/react';
import { Heart, Star } from 'lucide-react';
import type { FeedItem } from '@/data/constant';
import { show as homeShow } from '@/routes/home';

const StoneCard = ({
    item,
    aspect,
    saved,
    onToggleSave,
    href,
}: {
    item: FeedItem;
    aspect: string;
    saved: boolean;
    onToggleSave: () => void;
    href?: string;
}) => (
    <Link href={href ?? homeShow.url({ stone: item.id })} className="block">
        <article className="stone-card border border-sidebar-border bg-background">
            <div
                className={`stone-card-media relative overflow-hidden bg-background ${aspect}`}
            >
                <img
                    src={item.image}
                    alt={item.name}
                    loading="lazy"
                    className="absolute inset-0 size-full object-cover"
                />
                {item.recommended && (
                    <span className="absolute top-2.5 left-2.5 flex items-center gap-1 bg-black px-2 py-1 text-[0.65rem] font-bold tracking-[0.05em] text-white">
                        <Star
                            className="size-3 shrink-0 fill-white text-white"
                            aria-hidden
                        />
                        Highly recommended
                    </span>
                )}
            </div>
            <div className="flex items-center justify-between gap-2 p-3">
                <span className="min-w-0 truncate text-sm font-bold text-foreground">
                    {item.name}
                </span>
                <button
                    type="button"
                    onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        onToggleSave();
                    }}
                    aria-pressed={saved}
                    aria-label={
                        saved
                            ? `Remove ${item.name} from saved`
                            : `Save ${item.name}`
                    }
                    className="grid size-9 shrink-0 cursor-pointer place-items-center transition-opacity hover:opacity-60"
                >
                    <Heart
                        className={`size-5 ${
                            saved
                                ? 'fill-primary text-primary'
                                : 'text-secondary-foreground'
                        }`}
                        aria-hidden
                    />
                </button>
            </div>
        </article>
    </Link>
);

export default StoneCard;
