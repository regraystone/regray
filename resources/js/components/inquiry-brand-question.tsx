import { Check, Shuffle } from 'lucide-react';
import type { ReactNode } from 'react';
import { useFormContext, useWatch } from 'react-hook-form';
import { fieldMessage } from '@/components/form-field';
import {
    BRAND_LOGO_HEIGHT,
    DEFAULT_BRAND_LOGO_HEIGHT,
} from '@/components/public/public-links';
import InputError from '@/components/input-error';
import { useInquiryCatalog } from '@/hooks/use-inquiry-catalog';
import { NO_BRAND_PREFERENCE, toggleBrand } from '@/lib/inquiry-flow';
import type {
    InquiryDraftInput,
    InquiryDraftValues,
} from '@/lib/inquiry-schema';
import { cn } from '@/lib/utils';

/** The id the page scrolls to when no brand is chosen. */
export const INQUIRY_BRANDS_ID = 'inquiry-brands';

/**
 * Which brands the client would like: any of the active brands, in their
 * home page order, or No preference, never both. It is what they would like,
 * not a rule: every service stays on offer, and the stones are not limited by
 * it (the stones' own Brand filter is separate).
 *
 * The logos sit on black, so they compare evenly. A brand with no active
 * stone or furniture yet (`available` false) says Unavailable beside its name
 * and cannot be picked; one a saved draft already holds can still be taken
 * off. No preference spans the row under the brands.
 */
const InquiryBrandQuestion = () => {
    const { brands } = useInquiryCatalog();
    const {
        control,
        setValue,
        clearErrors,
        formState: { errors },
    } = useFormContext<InquiryDraftInput, unknown, InquiryDraftValues>();
    const chosen = useWatch({ control, name: 'brands' });
    const noPreference = useWatch({ control, name: 'brandNoPreference' });
    const error = fieldMessage(errors.brands);

    const pick = (brand: string) => {
        const next = toggleBrand(
            { brands: chosen, brandNoPreference: noPreference },
            brand,
        );

        setValue('brands', next.brands, { shouldDirty: true });
        setValue('brandNoPreference', next.brandNoPreference, {
            shouldDirty: true,
        });

        if (next.brands.length > 0 || next.brandNoPreference) {
            clearErrors('brands');
        }
    };

    /*
     * A brand's card: its logo on black, its name under it, and a tick in the
     * corner, filled once picked, when the card also takes a black frame. An
     * unavailable brand is dimmed, says so beside its name, and only answers a
     * tap to take off a pick a saved draft held.
     */
    const card = (
        key: string,
        pressed: boolean,
        panel: ReactNode,
        name: string,
        { available = true, wide = false } = {},
    ) => (
        <button
            key={key}
            type="button"
            aria-pressed={pressed}
            aria-label={available ? name : `${name}, unavailable`}
            disabled={!available && !pressed}
            onClick={() => pick(key)}
            data-test={`brand-${key}`}
            className={cn(
                'relative flex min-w-0 flex-col items-stretch border bg-background p-0 text-foreground transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                wide && 'col-span-full',
                available ? 'cursor-pointer' : 'cursor-not-allowed',
                pressed
                    ? 'border-foreground outline-2 -outline-offset-2 outline-foreground'
                    : available
                      ? 'border-border hover:border-foreground'
                      : 'border-border',
            )}
        >
            <span
                aria-hidden
                className={cn(
                    'absolute top-2.5 right-2.5 z-10 flex size-5.5 items-center justify-center rounded-full border',
                    pressed
                        ? 'border-foreground bg-foreground text-background'
                        : 'border-white/60 bg-white/10',
                )}
            >
                {pressed ? <Check className="size-3" /> : null}
            </span>
            <span
                aria-hidden
                className={cn(
                    'flex items-center justify-center bg-black px-5 py-4',
                    // No preference spans the row, so a lower panel keeps it from looming.
                    wide ? 'h-16' : 'h-24',
                    // Every panel stays black for comparison; only an unavailable logo dims.
                    !available && '[&>*]:opacity-35',
                )}
            >
                {panel}
            </span>
            <span
                className={cn(
                    'flex flex-wrap items-center justify-center gap-x-2 gap-y-0.5 px-3 py-3 text-center text-[0.8125rem] font-semibold break-words',
                    pressed ? 'bg-muted' : 'bg-background',
                )}
            >
                <span className={available ? '' : 'text-muted-foreground'}>
                    {name}
                </span>
                {available ? null : (
                    <span
                        aria-hidden
                        className="text-[0.6875rem] font-semibold tracking-[0.04em] text-muted-foreground uppercase"
                    >
                        Unavailable
                    </span>
                )}
            </span>
        </button>
    );

    return (
        <div id={INQUIRY_BRANDS_ID}>
            <p className="mb-5 text-[0.9375rem] leading-normal text-muted-foreground">
                Choose any you like, or No preference. It does not limit what we
                can do for you.
            </p>

            <div
                role="group"
                aria-label="Brands"
                aria-describedby={
                    error ? `${INQUIRY_BRANDS_ID}-error` : undefined
                }
                className="grid grid-cols-2 gap-3 sm:grid-cols-3"
            >
                {brands.map((brand) =>
                    card(
                        brand.id,
                        chosen.includes(brand.id),
                        <img
                            src={brand.logo}
                            alt=""
                            className="max-h-14 max-w-full object-contain"
                            style={{
                                height: `${Math.round((BRAND_LOGO_HEIGHT[brand.id] ?? DEFAULT_BRAND_LOGO_HEIGHT) * 0.72)}px`,
                            }}
                            loading="lazy"
                        />,
                        brand.name,
                        { available: brand.available },
                    ),
                )}
                {card(
                    NO_BRAND_PREFERENCE,
                    noPreference,
                    <Shuffle
                        className="size-7 text-white"
                        strokeWidth={1.25}
                    />,
                    'No preference',
                    { wide: true },
                )}
            </div>

            <InputError
                id={`${INQUIRY_BRANDS_ID}-error`}
                message={error}
                role="alert"
                className="mt-3"
            />
        </div>
    );
};

export default InquiryBrandQuestion;
