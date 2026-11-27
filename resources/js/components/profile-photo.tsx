import { router } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import { useRef, useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    destroy as removePhoto,
    update as updatePhoto,
} from '@/routes/profile/photo';

/** The photo types the server takes (config/profile.php), offered first in the picker. */
const PHOTO_TYPES = 'image/jpeg,image/png,image/webp';

/**
 * The avatar at the top of the Profile page: the user's photo, or their
 * initials. A client may add, change or remove a photo from it (a small
 * camera badge says so); staff have no profile to keep one yet, so theirs is
 * the plain avatar. A photo is sent as soon as it is picked, and a refusal
 * (wrong type, too large) is said under the avatar.
 */
export default function ProfilePhoto({
    photo,
    initials,
    editable,
}: {
    photo: string | null;
    initials: string;
    editable: boolean;
}) {
    const input = useRef<HTMLInputElement | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const avatar = (
        <Avatar className="size-20 shrink-0 sm:size-22">
            {photo ? (
                <AvatarImage
                    src={photo}
                    alt="Your profile photo"
                    className="object-cover"
                />
            ) : null}
            <AvatarFallback className="bg-primary text-2xl font-normal text-primary-foreground">
                {initials}
            </AvatarFallback>
        </Avatar>
    );

    if (!editable) {
        return avatar;
    }

    const send = (file: File) => {
        setError(null);
        router.post(
            updatePhoto.url(),
            { photo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setBusy(true),
                onError: (errors) =>
                    setError(errors.photo ?? 'The photo could not be saved.'),
                onFinish: () => {
                    setBusy(false);

                    if (input.current) {
                        input.current.value = '';
                    }
                },
            },
        );
    };

    const remove = () => {
        setError(null);
        router.delete(removePhoto.url(), {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <div className="flex shrink-0 flex-col items-center gap-1.5">
            <input
                ref={input}
                type="file"
                accept={PHOTO_TYPES}
                className="sr-only"
                tabIndex={-1}
                aria-hidden
                data-testid="profile-photo-input"
                onChange={(event) => {
                    const file = event.target.files?.[0];

                    if (file) {
                        send(file);
                    }
                }}
            />
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        disabled={busy}
                        className="relative cursor-pointer rounded-full transition-opacity focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:opacity-60"
                        aria-label={
                            photo
                                ? 'Change your profile photo'
                                : 'Add a profile photo'
                        }
                        data-testid="profile-photo-button"
                    >
                        {avatar}
                        <span
                            aria-hidden
                            className="absolute right-0 bottom-0 grid size-7 place-items-center rounded-full border-2 border-background bg-foreground text-background"
                        >
                            <Camera className="size-3.5" />
                        </span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-48">
                    <DropdownMenuItem onSelect={() => input.current?.click()}>
                        {photo ? 'Change photo' : 'Add photo'}
                    </DropdownMenuItem>
                    {photo ? (
                        <DropdownMenuItem onSelect={remove}>
                            Remove photo
                        </DropdownMenuItem>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>
            {error ? (
                <p
                    role="alert"
                    className="max-w-40 text-center text-xs text-destructive"
                >
                    {error}
                </p>
            ) : null}
        </div>
    );
}
