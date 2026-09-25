import {
    Children,
    forwardRef,
    useCallback,
    useEffect,
    useImperativeHandle,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import { cn } from '@/lib/utils';

const SCROLL_FRACTION = 0.8;

const usePrefersReducedMotion = () => {
    const [prefersReducedMotion, setPrefersReducedMotion] = useState(false);

    useEffect(() => {
        const query = window.matchMedia('(prefers-reduced-motion: reduce)');
        const handleChange = () => setPrefersReducedMotion(query.matches);

        setPrefersReducedMotion(query.matches);
        query.addEventListener('change', handleChange);

        return () => query.removeEventListener('change', handleChange);
    }, []);

    return prefersReducedMotion;
};

export type CarouselHandle = {
    scrollPrev: () => void;
    scrollNext: () => void;
    scrollTo: (index: number) => void;
};

export type CarouselProps = {
    ariaLabel: string;
    children: ReactNode;
    className?: string;
    controls?: ReactNode;
    dots?: boolean;
    dotsClassName?: string;
    onActiveChange?: (index: number) => void;
    viewportClassName?: string;
};

const Carousel = forwardRef<CarouselHandle, CarouselProps>(
    (
        {
            ariaLabel,
            children,
            className,
            controls,
            dots = false,
            dotsClassName,
            onActiveChange,
            viewportClassName,
        },
        ref,
    ) => {
        const viewportRef = useRef<HTMLDivElement>(null);
        const prefersReducedMotion = usePrefersReducedMotion();
        const [activeDot, setActiveDot] = useState(0);

        const slideCount = useMemo(
            () => Children.toArray(children).length,
            [children],
        );

        const scrollBehavior: ScrollBehavior = prefersReducedMotion
            ? 'auto'
            : 'smooth';

        const updateActiveDot = useCallback(() => {
            const element = viewportRef.current;

            if (!element || slideCount <= 1) {
                setActiveDot(0);
                onActiveChange?.(0);
                return;
            }

            const maxScroll = element.scrollWidth - element.clientWidth;
            const index =
                maxScroll <= 0
                    ? 0
                    : Math.round(
                          (element.scrollLeft / maxScroll) * (slideCount - 1),
                      );

            setActiveDot(index);
            onActiveChange?.(index);
        }, [onActiveChange, slideCount]);

        const scrollTo = useCallback(
            (index: number) => {
                const element = viewportRef.current;

                if (!element || element.scrollWidth <= element.clientWidth) {
                    return;
                }

                const maxScroll = element.scrollWidth - element.clientWidth;
                const left =
                    slideCount > 1 ? (maxScroll * index) / (slideCount - 1) : 0;

                element.scrollTo({ left, behavior: scrollBehavior });
            },
            [slideCount, scrollBehavior],
        );

        const scrollPrev = useCallback(() => {
            const element = viewportRef.current;

            if (!element) {
                return;
            }

            element.scrollBy({
                left: -element.clientWidth * SCROLL_FRACTION,
                behavior: scrollBehavior,
            });
        }, [scrollBehavior]);

        const scrollNext = useCallback(() => {
            const element = viewportRef.current;

            if (!element) {
                return;
            }

            element.scrollBy({
                left: element.clientWidth * SCROLL_FRACTION,
                behavior: scrollBehavior,
            });
        }, [scrollBehavior]);

        useEffect(() => {
            updateActiveDot();
        }, [updateActiveDot]);

        useImperativeHandle(ref, () => ({ scrollPrev, scrollNext, scrollTo }), [
            scrollPrev,
            scrollNext,
            scrollTo,
        ]);

        return (
            <div className={className}>
                <div
                    ref={viewportRef}
                    role="region"
                    aria-label={ariaLabel}
                    tabIndex={0}
                    onScroll={updateActiveDot}
                    onKeyDown={(event) => {
                        if (event.key === 'ArrowLeft') {
                            event.preventDefault();
                            scrollPrev();
                        }

                        if (event.key === 'ArrowRight') {
                            event.preventDefault();
                            scrollNext();
                        }
                    }}
                    className={cn(
                        'flex snap-x overflow-x-auto overscroll-x-contain',
                        '[scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
                        viewportClassName,
                    )}
                >
                    {children}
                </div>
                {controls}
                {dots && slideCount > 1 && (
                    <div
                        role="tablist"
                        aria-label={`${ariaLabel} pagination`}
                        className={cn(
                            'flex items-center justify-center gap-2',
                            dotsClassName,
                        )}
                    >
                        {Array.from({ length: slideCount }, (_, index) => (
                            <button
                                key={index}
                                type="button"
                                role="tab"
                                aria-selected={activeDot === index}
                                aria-label={`${ariaLabel}, slide ${index + 1} of ${slideCount}`}
                                onClick={() => scrollTo(index)}
                                className={cn(
                                    'size-2 rounded-full transition-opacity hover:opacity-70',
                                    activeDot === index
                                        ? 'bg-primary'
                                        : 'bg-on-image/50',
                                )}
                            />
                        ))}
                    </div>
                )}
            </div>
        );
    },
);

Carousel.displayName = 'Carousel';

export default Carousel;
