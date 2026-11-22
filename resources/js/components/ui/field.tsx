import type { ComponentProps, ReactNode, Ref } from 'react';
import { cn } from '@/lib/utils';

type FieldProps = ComponentProps<'input'> & {
    label: string;
    error?: string;
    hint?: ReactNode;
    /**
     * `boxed`, the workspace's bordered box (the default), or `line`, the auth
     * pages' single underline on the paper.
     */
    variant?: FieldVariant;
    ref?: Ref<HTMLInputElement>;
};

export type FieldVariant = 'boxed' | 'line';

/** The label, the input and the error under it, in each variant. */
export const fieldStyles: Record<
    FieldVariant,
    { row: string; label: string; input: string; error: string }
> = {
    boxed: {
        row: 'flex items-center justify-between gap-2',
        label: 'block text-xs font-bold tracking-[0.16em] text-secondary-foreground uppercase',
        input: 'mt-2 w-full rounded-none border border-border bg-background px-4 py-3 text-base sm:text-[0.98rem] text-foreground outline-none transition-colors focus:border-2 focus:border-primary',
        error: 'mt-2 text-[0.8rem] font-semibold text-secondary-foreground',
    },
    line: {
        row: 'flex items-baseline justify-between gap-3',
        label: 'block text-[0.9375rem] font-semibold text-ink',
        input: 'mt-2.5 h-12 w-full rounded-none border-0 border-b border-field-line bg-transparent px-0 text-[1.0625rem] text-ink outline-none transition-colors placeholder:text-ink-faint focus:border-ink read-only:border-line read-only:text-ink-muted aria-invalid:border-ink [&:-webkit-autofill]:shadow-[inset_0_0_0_40px_var(--paper)]',
        error: 'mt-2 text-[0.9375rem] font-medium text-ink-soft',
    },
};

export function Field({
    id,
    label,
    error,
    hint,
    variant = 'boxed',
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
    const styles = fieldStyles[variant];

    return (
        <div>
            <div className={styles.row}>
                <label htmlFor={id} className={styles.label}>
                    {label}
                </label>
                {hint}
            </div>
            <input
                id={id}
                ref={ref}
                className={cn(styles.input, className)}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? errorId : undefined}
                {...props}
            />
            {error ? (
                <p id={errorId} role="alert" className={styles.error}>
                    {error}
                </p>
            ) : null}
        </div>
    );
}
