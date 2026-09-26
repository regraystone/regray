import {
    Children,
    forwardRef,
    useCallback,
    useEffect,
    useImperativeHandle,
    useMemo,
    useRef,
    useState,
    type PointerEvent as ReactPointerEvent,
    type ReactNode,
} from 'react';
import { cn } from '@/lib/utils';

const DRAG_COMMIT_FRACTION = 0.2;
const HORIZONTAL_START_SLOP = 8;
const VERTICAL_START_SLOP = 8;

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

export type GalleryCarouselHandle = {
    scrollPrev: () => void;
    scrollNext: () => void;
    scrollTo: (index: number) => void;
};

export type GalleryCarouselProps = {
    ariaLabel: string;
    children: ReactNode;
    className?: string;
    controls?: ReactNode;
    dots?: boolean;
    dotsClassName?: string;
    viewportClassName?: string;
};

const GalleryCarousel = forwardRef<GalleryCarouselHandle, GalleryCarouselProps>(
    (
        {
            ariaLabel,
            children,
            className,
            controls,
            dots = false,
            dotsClassName,
            viewportClassName,
        },
        ref,
    ) => {
        const viewportRef = useRef<HTMLDivElement>(null);
        const prefersReducedMotion = usePrefersReducedMotion();
        const [activeDot, setActiveDot] = useState(0);
        const [isDragging, setIsDragging] = useState(false);
        const dragState = useRef<{
            pointerId: number;
            startX: number;
            startY: number;
            startScrollLeft: number;
            virtualScrollLeft: number;
            engaged: boolean;
        } | null>(null);
        const pendingScrollRef = useRef<number | null>(null);
        const settleTimerRef = useRef<number | null>(null);
        const targetIndexRef = useRef(0);

        const slideCount = useMemo(
            () => Children.toArray(children).length,
            [children],
        );

        const getSlideWidth = useCallback(() => {
            const element = viewportRef.current;
            const first = element?.firstElementChild;

            if (first) {
                return first.getBoundingClientRect().width;
            }

            return element?.clientWidth ?? 0;
        }, []);

        const scrollBehavior: ScrollBehavior = prefersReducedMotion
            ? 'auto'
            : 'smooth';

        const getCurrentIndex = useCallback(() => {
            const element = viewportRef.current;

            if (!element || slideCount <= 1) {
                return 0;
            }

            const slideWidth = getSlideWidth();

            if (slideWidth <= 0) {
                return 0;
            }

            return Math.max(
                0,
                Math.min(
                    Math.round(element.scrollLeft / slideWidth),
                    slideCount - 1,
                ),
            );
        }, [getSlideWidth, slideCount]);

        const updateActiveDot = useCallback(() => {
            const index = getCurrentIndex();

            setActiveDot(index);

            if (pendingScrollRef.current === null) {
                targetIndexRef.current = index;
            }
        }, [getCurrentIndex]);

        const settleScroll = useCallback(() => {
            const element = viewportRef.current;
            const target = pendingScrollRef.current;

            pendingScrollRef.current = null;

            if (!element || target === null) {
                return;
            }

            if (Math.abs(element.scrollLeft - target) > 4) {
                element.scrollLeft = target;
            }

            updateActiveDot();
        }, [updateActiveDot]);

        const handleScroll = useCallback(() => {
            const element = viewportRef.current;

            updateActiveDot();

            if (pendingScrollRef.current === null || !element) {
                return;
            }

            if (settleTimerRef.current !== null) {
                window.clearTimeout(settleTimerRef.current);
                settleTimerRef.current = null;
            }

            if (Math.abs(element.scrollLeft - pendingScrollRef.current) < 4) {
                pendingScrollRef.current = null;

                return;
            }

            settleTimerRef.current = window.setTimeout(settleScroll, 140);
        }, [settleScroll, updateActiveDot]);

        const scrollTo = useCallback(
            (index: number) => {
                const element = viewportRef.current;

                if (!element || slideCount <= 1) {
                    return;
                }

                const slideWidth = getSlideWidth();

                if (slideWidth <= 0) {
                    return;
                }

                const wrappedIndex =
                    ((index % slideCount) + slideCount) % slideCount;

                targetIndexRef.current = wrappedIndex;
                pendingScrollRef.current = wrappedIndex * slideWidth;

                element.scrollTo({
                    left: wrappedIndex * slideWidth,
                    behavior: scrollBehavior,
                });
            },
            [getSlideWidth, scrollBehavior, slideCount],
        );

        const scrollPrev = useCallback(() => {
            scrollTo(targetIndexRef.current - 1);
        }, [scrollTo]);

        const scrollNext = useCallback(() => {
            scrollTo(targetIndexRef.current + 1);
        }, [scrollTo]);

        const snapBackToCurrent = useCallback(() => {
            scrollTo(getCurrentIndex());
        }, [scrollTo, getCurrentIndex]);

        const endDrag = useCallback(
            (commit: boolean) => {
                const state = dragState.current;

                if (!state) {
                    return;
                }

                dragState.current = null;
                setIsDragging(false);

                const element = viewportRef.current;

                if (!element) {
                    return;
                }

                if (!commit) {
                    snapBackToCurrent();

                    return;
                }

                const dragged = state.virtualScrollLeft - state.startScrollLeft;
                const slideWidth = getSlideWidth();

                if (Math.abs(dragged) >= slideWidth * DRAG_COMMIT_FRACTION) {
                    targetIndexRef.current = Math.round(
                        state.startScrollLeft / slideWidth,
                    );

                    if (dragged > 0) {
                        scrollNext();
                    } else {
                        scrollPrev();
                    }

                    return;
                }

                snapBackToCurrent();
            },
            [getSlideWidth, scrollNext, scrollPrev, snapBackToCurrent],
        );

        const onPointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
            if (event.button !== 0) {
                return;
            }

            pendingScrollRef.current = null;

            if (settleTimerRef.current !== null) {
                window.clearTimeout(settleTimerRef.current);
                settleTimerRef.current = null;
            }

            const element = viewportRef.current;

            if (!element) {
                return;
            }

            dragState.current = {
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                startScrollLeft: element.scrollLeft,
                virtualScrollLeft: element.scrollLeft,
                engaged: false,
            };
        };

        const onPointerMove = (event: ReactPointerEvent<HTMLDivElement>) => {
            const state = dragState.current;

            if (!state || event.pointerId !== state.pointerId) {
                return;
            }

            const element = viewportRef.current;

            if (!element) {
                return;
            }

            if (!state.engaged) {
                const dx = event.clientX - state.startX;
                const dy = event.clientY - state.startY;

                if (
                    Math.abs(dx) < HORIZONTAL_START_SLOP &&
                    Math.abs(dy) < VERTICAL_START_SLOP
                ) {
                    return;
                }

                if (Math.abs(dy) > Math.abs(dx)) {
                    dragState.current = null;

                    return;
                }

                element.setPointerCapture(event.pointerId);
                state.engaged = true;
                setIsDragging(true);
            }

            const virtualScrollLeft =
                state.startScrollLeft - (event.clientX - state.startX);

            element.scrollLeft = virtualScrollLeft;
            state.virtualScrollLeft = virtualScrollLeft;
        };

        const onPointerUp = (event: ReactPointerEvent<HTMLDivElement>) => {
            endDrag(event.pointerId === dragState.current?.pointerId);
        };

        const onPointerCancel = () => {
            endDrag(false);
        };

        useEffect(() => {
            updateActiveDot();
        }, [updateActiveDot]);

        useEffect(
            () => () => {
                if (settleTimerRef.current !== null) {
                    window.clearTimeout(settleTimerRef.current);
                }
            },
            [],
        );

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
                    onScroll={handleScroll}
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
                    onPointerDown={onPointerDown}
                    onPointerMove={onPointerMove}
                    onPointerUp={onPointerUp}
                    onPointerCancel={onPointerCancel}
                    className={cn(
                        'flex touch-pan-y overflow-x-auto overscroll-x-contain select-none',
                        isDragging
                            ? 'cursor-grabbing snap-none'
                            : 'cursor-grab snap-x snap-mandatory',
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
                                    'size-2.5 cursor-pointer rounded-full transition-opacity hover:opacity-70',
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

GalleryCarousel.displayName = 'GalleryCarousel';

export default GalleryCarousel;
