import { OTPInputContext, REGEXP_ONLY_DIGITS } from 'input-otp';
import { useContext, type ComponentProps, type Ref } from 'react';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { cn } from '@/lib/utils';

type OtpFieldProps = Omit<
    ComponentProps<'input'>,
    'defaultValue' | 'maxLength' | 'onChange' | 'value'
> & {
    label: string;
    error?: string;
    maxLength?: number;
    value: string;
    onChange: (value: string) => void;
    ref?: Ref<HTMLInputElement>;
};

/**
 * The `Label` primitive arrives with its own stock sizing, so the overrides
 * below are what make the label read exactly like the one on `Field` instead
 * of like the rest of the starter kit. `leading-4` is the line height `text-xs`
 * already gives the `Field` label, spelled out so the primitive's own
 * `leading-none` cannot quietly shorten it.
 */
const labelClasses =
    'block text-xs leading-4 font-bold tracking-[0.16em] text-secondary-foreground uppercase';

/**
 * Separate square cells rather than one joined pill, sized by the flex row so
 * the whole code always fits the narrowest column the auth layout allows.
 * `first:`/`last:` are here because the primitive's rounded ends live on the
 * same variant and tailwind-merge cannot drop them for a bare `rounded-none`.
 */
const slotClasses =
    'h-12 min-w-0 flex-1 rounded-none border border-border bg-background text-lg font-bold shadow-none ring-0 first:rounded-none last:rounded-none';

const groupClasses = 'mt-2 w-full gap-1.5 sm:gap-2';

/**
 * The active cell is the one holding the caret, and the primitive merges its
 * own active ring in before the classes it is handed, so a caller can switch
 * that ring off but cannot restyle the active state itself. Reading the same
 * public context the primitive reads is what keeps the 2px primary border
 * conditional without editing a component the 2FA setup dialog also uses.
 */
function OtpSlot({ index, className }: { index: number; className: string }) {
    const { slots } = useContext(OTPInputContext);

    return (
        <InputOTPSlot
            index={index}
            className={cn(
                className,
                // `first:border-2` cancels the primitive's own `first:border-l`,
                // which is narrow enough to show as a thinner left edge.
                slots[index].isActive &&
                    'border-2 border-primary first:border-2',
            )}
        />
    );
}

export function OtpField({
    id,
    label,
    error,
    value,
    onChange,
    maxLength = OTP_MAX_LENGTH,
    className,
    ref,
    ...props
}: OtpFieldProps) {
    /*
     * The error is read out loud, so the input has to both be marked as refused
     * and point at the words explaining why. Doing it from `error` rather than
     * at every call site means a form cannot forget.
     */
    const errorId = id ? `${id}-error` : undefined;

    return (
        <div>
            <Label htmlFor={id} className={labelClasses}>
                {label}
            </Label>

            <InputOTP
                ref={ref}
                id={id}
                className={className}
                value={value}
                onChange={onChange}
                maxLength={maxLength}
                inputMode="numeric"
                pattern={REGEXP_ONLY_DIGITS}
                autoComplete="one-time-code"
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? errorId : undefined}
                {...props}
            >
                <InputOTPGroup className={groupClasses}>
                    {Array.from({ length: maxLength }, (_, index) => (
                        <OtpSlot key={index} index={index} className={slotClasses} />
                    ))}
                </InputOTPGroup>
            </InputOTP>

            {error ? (
                <p
                    id={errorId}
                    role="alert"
                    className="mt-2 text-[0.8rem] font-semibold text-secondary-foreground"
                >
                    {error}
                </p>
            ) : null}
        </div>
    );
}
