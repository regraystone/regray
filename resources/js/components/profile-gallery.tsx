import { useRef } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import GalleryCarousel, {
    type GalleryCarouselHandle,
} from '@/components/gallery-carousel';
import {
    LightboxTrigger,
    type LightboxImage,
} from '@/components/image-lightbox';

export type ProfileGalleryImage = {
    src: string;
    /**
     * The caption a stone's image carries. A project's images have none, so
     * the alt text falls back to the image's place in the gallery.
     */
    name?: string;
};

const ProfileGallery = ({
    images,
    name,
    onOpenImage,
}: {
    images: ProfileGalleryImage[];
    name: string;
    onOpenImage: (image: LightboxImage) => void;
}) => {
    const carouselRef = useRef<GalleryCarouselHandle>(null);

    return (
        <GalleryCarousel
            ref={carouselRef}
            ariaLabel={`${name} gallery`}
            dots
            dotsClassName="absolute bottom-3 left-1/2 -translate-x-1/2"
            className="relative bg-background"
            controls={
                images.length > 1 ? (
                    <>
                        <button
                            type="button"
                            onClick={() => carouselRef.current?.scrollPrev()}
                            aria-label="Previous photo"
                            className="absolute inset-y-0 left-2 z-10 my-auto flex size-10 cursor-pointer items-center justify-center text-white/80 transition hover:text-white sm:left-4"
                        >
                            <ChevronLeft
                                className="size-8 drop-shadow-md"
                                aria-hidden
                            />
                        </button>
                        <button
                            type="button"
                            onClick={() => carouselRef.current?.scrollNext()}
                            aria-label="Next photo"
                            className="absolute inset-y-0 right-2 z-10 my-auto flex size-10 cursor-pointer items-center justify-center text-white/80 transition hover:text-white sm:right-4"
                        >
                            <ChevronRight
                                className="size-8 drop-shadow-md"
                                aria-hidden
                            />
                        </button>
                    </>
                ) : undefined
            }
        >
            {images.map((image, index) => {
                const alt = image.name
                    ? `${name} - ${image.name}`
                    : `${name} - Image ${index + 1}`;

                return (
                    <div
                        key={image.src}
                        className="aspect-3/4 w-full shrink-0 snap-center lg:aspect-[16/9]"
                    >
                        <LightboxTrigger
                            onOpen={() => onOpenImage({ src: image.src, alt })}
                        >
                            <img
                                src={image.src}
                                alt={alt}
                                draggable={false}
                                loading={index === 0 ? 'eager' : 'lazy'}
                                className="size-full object-cover"
                            />
                        </LightboxTrigger>
                    </div>
                );
            })}
        </GalleryCarousel>
    );
};

export default ProfileGallery;
