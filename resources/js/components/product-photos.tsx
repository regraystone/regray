import { Maximize2 } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import ImageLightbox, { type LightboxImage } from '@/components/image-lightbox';

export type ProductPhoto = {
    src: string;
    /** The caption the photo carries; the gallery's own alt text if any. */
    name: string;
};

/**
 * The thumbnail first, then the gallery's other photos, each once: a gallery
 * usually starts with the thumbnail itself.
 */
export const productPhotos = (
    thumbnail: string | null,
    gallery: ProductPhoto[],
    name: string,
): ProductPhoto[] => {
    const photos = thumbnail ? [{ src: thumbnail, name }] : [];

    for (const image of gallery) {
        if (!photos.some((photo) => photo.src === image.src)) {
            photos.push(image);
        }
    }

    return photos;
};

/**
 * A product's photos, as its page shows them: one large, which opens larger
 * on a click, and, when there are two or more, all of them in a row below to
 * pick which is large. It sticks beside the details from a tablet up, so the
 * photo stays in view while they scroll.
 *
 * Give it a `key` per product: Inertia keeps the page mounted from one
 * product to the next, and a new product starts on its first photo.
 */
const ProductPhotos = ({
    name,
    alt,
    photos,
    frameClassName,
    placeholder,
}: {
    /** The product's name, for the "See … larger" label. */
    name: string;
    /** The large photo's alt text, describing the product. */
    alt: string;
    photos: ProductPhoto[];
    /** The large photo's shape and width. */
    frameClassName: string;
    /** What shows when the product has no photo at all. */
    placeholder?: ReactNode;
}) => {
    const [shown, setShown] = useState(0);
    const [lightbox, setLightbox] = useState<LightboxImage | null>(null);
    const index = Math.min(shown, photos.length - 1);
    const photo = photos[index];
    const photoAlt = index === 0 ? alt : `${alt}, photo ${index + 1}`;

    return (
        <div className="flex flex-col gap-3 md:sticky md:top-24">
            {photo ? (
                <button
                    type="button"
                    onClick={() =>
                        setLightbox({ src: photo.src, alt: photoAlt })
                    }
                    aria-label={`See ${name} larger`}
                    className={`group relative mx-auto block w-full cursor-zoom-in overflow-hidden bg-muted md:max-w-none ${frameClassName}`}
                >
                    <img
                        src={photo.src}
                        alt={photoAlt}
                        className="size-full object-cover transition-transform duration-500 group-hover:scale-[1.02] motion-reduce:transition-none"
                    />
                    <span className="absolute right-3 bottom-3 flex size-9 items-center justify-center bg-background/90 text-foreground opacity-80 transition-opacity group-hover:opacity-100">
                        <Maximize2 aria-hidden className="size-4" />
                    </span>
                </button>
            ) : (
                <div
                    className={`mx-auto flex w-full items-center justify-center overflow-hidden bg-muted/50 md:max-w-none ${frameClassName}`}
                >
                    {placeholder}
                </div>
            )}
            {photos.length > 1 ? (
                <div
                    className="grid grid-cols-5 gap-2"
                    data-testid="product-photos"
                >
                    {photos.map((image, position) => (
                        <button
                            key={image.src}
                            type="button"
                            onClick={() => setShown(position)}
                            aria-label={`Show photo ${position + 1}`}
                            aria-pressed={position === index}
                            className={`aspect-square cursor-pointer overflow-hidden border ${position === index ? 'border-foreground' : 'border-border'}`}
                        >
                            <img
                                src={image.src}
                                alt=""
                                loading="lazy"
                                className="size-full object-cover"
                            />
                        </button>
                    ))}
                </div>
            ) : null}

            <ImageLightbox image={lightbox} onClose={() => setLightbox(null)} />
        </div>
    );
};

export default ProductPhotos;
