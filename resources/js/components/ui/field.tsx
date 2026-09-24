import type { ComponentProps, ReactNode, Ref } from 'react';
import { cn } from '@/lib/utils';

type FieldProps = ComponentProps<'input'> & {
    label: string;
    error?: string;
    hint?: ReactNode;
    ref?: Ref<HTMLInputElement>;
};

const inputClasses =
    'mt-2 w-full rounded-none border border-border bg-background px-4 py-3 text-[0.98rem] text-foreground outline-none transition-colors focus:border-2 focus:border-primary';

export function Field({
    id,
    label,
    error,
    hint,
    className,
    ref,
    ...props
}: FieldProps) {
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
                {...props}
            />
            {error ? (
                <p
                    role="alert"
                    className="mt-2 text-[0.8rem] font-semibold text-secondary-foreground"
                >
                    {error}
                </p>
            ) : null}
        </div>
    );
}