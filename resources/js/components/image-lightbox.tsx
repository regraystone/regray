import * as DialogPrimitive from '@radix-ui/react-dialog';
import {
    useEffect,
    useRef,
    useState,
    type MouseEvent as ReactMouseEvent,
    type PointerEvent as ReactPointerEvent,
    type ReactNode,
} from 'react';
import {
    Dialog,
    DialogClose,
    DialogPortal,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

export type LightboxImage = {
    src: string;
    alt: string;
};

/*
 * The carousels take pointer capture once a swipe passes their own 8px slop,
 * which either moves the click off the slide or leaves it well past this
 * point, so a press that started and ended within a finger's width is a tap.
 */
const TAP_SLOP = 8;

type LightboxTriggerProps = {
    children: ReactNode;
    className?: string;
    onOpen: () => void;
};

const LightboxTrigger = ({
    children,
    className,
    onOpen,
}: LightboxTriggerProps) => {
    const pressedAt = useRef<{ x: number; y: number } | null>(null);

    const handlePointerDown = (event: ReactPointerEvent<HTMLButtonElement>) => {
        if (event.button === 0) {
            pressedAt.current = { x: event.clientX, y: event.clientY };
        }
    };

    const handleClick = (event: ReactMouseEvent<HTMLButtonElement>) => {
        const start = pressedAt.current;
        pressedAt.current = null;

        // `detail` is 0 for Enter, Space and assistive technology, which have
        // no press behind them to compare against, so only a pointer click
        // can be the end of a drag the carousel should have kept.
        const isDrag =
            event.detail > 0 &&
            start !== null &&
            (Math.abs(event.clientX - start.x) >= TAP_SLOP ||
                Math.abs(event.clientY - start.y) >= TAP_SLOP);

        if (!isDrag) {
            onOpen();
        }
    };

    return (
        <button
            type="button"
            className={cn(
                'block size-full cursor-pointer overflow-hidden rounded-md p-0 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none focus-visible:ring-inset',
                className,
            )}
            onPointerDown={handlePointerDown}
            onClick={handleClick}
        >
            {children}
        </button>
    );
};

type ImageLightboxProps = {
    image: LightboxImage | null;
    onClose: () => void;
};

const ImageLightbox = ({ image, onClose }: ImageLightboxProps) => {
    const openerRef = useRef<HTMLElement | null>(null);
    // The dialog stays mounted until its close animation finishes, so the
    // picture has to outlive the prop: an empty dialog for the frame the user
    // just closed on would flash in its place.
    const [lastImage, setLastImage] = useState<LightboxImage | null>(image);
    const shownImage = image ?? lastImage;

    useEffect(() => {
        if (image) {
            setLastImage(image);
        }
    }, [image]);

    if (!shownImage) {
        return null;
    }

    return (
        <Dialog
            open={image !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogPortal>
                <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/80 data-[state=closed]:lightbox-fade-out data-[state=open]:lightbox-fade-in" />
                <DialogPrimitive.Content
                    data-test="image-lightbox"
                    aria-describedby={undefined}
                    onOpenAutoFocus={() => {
                        openerRef.current =
                            document.activeElement instanceof HTMLElement
                                ? document.activeElement
                                : null;
                    }}
                    onCloseAutoFocus={(event) => {
                        // Radix only hands focus back to a DialogTrigger,
                        // which this page does not render, so the element that
                        // opened the lightbox is remembered here instead.
                        event.preventDefault();
                        openerRef.current?.focus();
                    }}
                    onClick={onClose}
                    className="fixed inset-0 z-50 flex cursor-zoom-out items-center justify-center p-4 pt-[max(1rem,env(safe-area-inset-top))] pr-[max(1rem,env(safe-area-inset-right))] pb-[max(1rem,env(safe-area-inset-bottom))] pl-[max(1rem,env(safe-area-inset-left))] data-[state=closed]:lightbox-content-out data-[state=open]:lightbox-content-in"
                >
                    <DialogTitle className="sr-only">
                        {shownImage.alt}
                    </DialogTitle>
                    <img
                        src={shownImage.src}
                        alt={shownImage.alt}
                        decoding="async"
                        draggable={false}
                        className="max-h-[90dvh] max-w-[95vw] rounded-md object-contain"
                    />
                    {/* Every click closes the lightbox, so there is no visible
                        control to show — this stays hidden because screen
                        reader and keyboard users would otherwise have nothing
                        to activate. */}
                    <DialogClose aria-label="Close image" className="sr-only" />
                </DialogPrimitive.Content>
            </DialogPortal>
        </Dialog>
    );
};

export { LightboxTrigger };
export default ImageLightbox;
