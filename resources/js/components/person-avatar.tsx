import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

/**
 * Someone's round avatar wherever they are listed: their profile photo when
 * they have one (a client, for now), or their initials. Used beside a name on
 * an inquiry's details, so a photo added on the Profile page shows there too.
 */
export default function PersonAvatar({
    name,
    photo = null,
    className,
}: {
    name: string;
    photo?: string | null;
    className?: string;
}) {
    const getInitials = useInitials();

    return (
        <Avatar
            className={cn('size-9 shrink-0 border border-border', className)}
        >
            {photo ? (
                <AvatarImage src={photo} alt="" className="object-cover" />
            ) : null}
            <AvatarFallback className="bg-primary text-xs font-bold text-primary-foreground">
                {getInitials(name) || '?'}
            </AvatarFallback>
        </Avatar>
    );
}
