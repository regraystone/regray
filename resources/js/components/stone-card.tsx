import { Link } from '@inertiajs/react';
import { Heart, Star } from 'lucide-react';
import type { FeedItem } from '@/data/constant';
import { show as homeShow } from '@/routes/home';

const StoneCard = ({
    item,
    aspect,
    saved,
    onToggleSave,
}: {
    item: FeedItem;
    aspect: string;
    saved: boolean;
    onToggleSave: () => void;
}) => (
    <Link href={homeShow.url({ stone: item.id })} className="block">
        <figure className="stone-card cursor-pointer border border-sidebar-border bg-background">
            <div
                className={`stone-card-media relative overflow-hidden bg-background ${aspect}`}
            >
                <img
                    src={item.image}
                    alt={item.name}
                    loading="lazy"
                    className="absolute inset-0 size-full object-cover"
                />
                <div className="image-tint absolute inset-0" aria-hidden />
                <span className="absolute bottom-2.5 left-2.5 flex max-w-[calc(100%-1.25rem)] items-center justify-center gap-1.5 bg-background py-1 pr-2.5 pl-2">
                    {item.recommended && (
                        <Star
                            className="size-4 shrink-0 fill-foreground text-foreground"
                            aria-label="Highly recommended"
                        />
                    )}
                    <span className="min-w-0 truncate text-[0.65rem] font-bold tracking-[0.05em] text-foreground uppercase sm:text-[0.8rem] sm:tracking-[0.06em]">
                        {item.name}
                    </span>
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
                    className="absolute top-2.5 right-2.5 grid size-10 cursor-pointer place-items-center transition-opacity hover:opacity-60"
                >
                    <Heart
                        className={`size-6 ${
                            saved
                                ? 'fill-on-image text-on-image'
                                : 'text-on-image'
                        }`}
                        aria-hidden
                    />
                </button>
            </div>
        </figure>
    </Link>
);

export default StoneCard;
