import { Check } from 'lucide-react';
import type { ReactNode } from 'react';
import { useFormContext, useWatch } from 'react-hook-form';
import { fieldMessage } from '@/components/form-field';
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

    const card = (
        key: string,
        pressed: boolean,
        content: ReactNode,
        label: string,
    ) => (
        <button
            key={key}
            type="button"
            aria-pressed={pressed}
            aria-label={label}
            onClick={() => pick(key)}
            data-test={`brand-${key}`}
            className={cn(
                'relative flex min-h-24 min-w-0 cursor-pointer flex-col items-center justify-center gap-2 border p-3 text-center transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                pressed
                    ? 'border-foreground bg-secondary'
                    : 'border-sidebar-border bg-background hover:border-foreground/40',
            )}
        >
            {pressed ? (
                <span className="absolute top-2 right-2 flex size-5 items-center justify-center rounded-full bg-foreground text-background">
                    <Check aria-hidden className="size-3" />
                </span>
            ) : null}
            {content}
        </button>
    );

    return (
        <div id={INQUIRY_BRANDS_ID}>
            <p className="mb-4 text-sm text-secondary-foreground">
                Choose any you like, or No preference. It does not limit what we
                can do for you.
            </p>

            <div
                role="group"
                aria-label="Brands"
                aria-describedby={
                    error ? `${INQUIRY_BRANDS_ID}-error` : undefined
                }
                className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
            >
                {brands.map((brand) =>
                    card(
                        brand.id,
                        chosen.includes(brand.id),
                        <>
                            <img
                                src={brand.logo}
                                alt=""
                                className="h-10 max-w-full object-contain"
                                loading="lazy"
                            />
                            <span className="text-sm font-bold break-words">
                                {brand.name}
                            </span>
                        </>,
                        brand.name,
                    ),
                )}
                {card(
                    NO_BRAND_PREFERENCE,
                    noPreference,
                    <span className="text-sm font-bold">No preference</span>,
                    'No preference',
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
