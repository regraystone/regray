import type { ComponentProps, ReactNode, Ref } from 'react';
import { cn } from '@/lib/utils';

type FieldProps = ComponentProps<'input'> & {
    label: string;
    error?: string;
    hint?: ReactNode;
    ref?: Ref<HTMLInputElement>;
};

const inputClasses =
    'mt-2 w-full rounded-none border border-border bg-background px-4 py-3 text-base sm:text-[0.98rem] text-foreground outline-none transition-colors focus:border-2 focus:border-primary';

export function Field({
    id,
    label,
    error,
    hint,
    className,
    ref,
    ...props
}: FieldProps) {
    /*
     * The error is read out loud, so the input has to both be marked as refused
     * and point at the words explaining why. Doing it from `error` rather than
     * at every call site means a form cannot forget.
     */
    const errorId = id ? `${id}-error` : undefined;

    return (
        <div>
            <div className="flex items-center justify-between gap-2">
                <label
                    htmlFor={id}
                    className="block text-xs font-bold tracking-[0.16em] text-secondary-foreground uppercase"
                >
                    {label}
                </label>
                {hint}
            </div>
            <input
                id={id}
                ref={ref}
                className={cn(inputClasses, className)}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? errorId : undefined}
                {...props}
            />
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