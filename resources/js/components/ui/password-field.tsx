import { Eye, EyeOff } from 'lucide-react';
import { useState, type ComponentProps, type ReactNode, type Ref } from 'react';
import { fieldStyles, type FieldVariant } from '@/components/ui/field';
import { cn } from '@/lib/utils';

type PasswordFieldProps = ComponentProps<'input'> & {
    label: string;
    error?: string;
    hint?: ReactNode;
    /** As Field's: `boxed` (the default) or the auth pages' `line`. */
    variant?: FieldVariant;
    ref?: Ref<HTMLInputElement>;
};

/**
 * A password input with a button that shows what was typed: an eye icon in
 * the boxed variant, the words Show and Hide in the line one.
 */
export function PasswordField({
    id,
    label,
    error,
    hint,
    variant = 'boxed',
    className,
    ref,
    ...props
}: PasswordFieldProps) {
    const [showPassword, setShowPassword] = useState(false);

    /*
     * The error is read out loud, so the input has to both be marked as refused
     * and point at the words explaining why. Doing it from `error` rather than
     * at every call site means a form cannot forget.
     */
    const errorId = id ? `${id}-error` : undefined;
    const styles = fieldStyles[variant];
    const isLine = variant === 'line';

    return (
        <div>
            <div className={styles.row}>
                <label htmlFor={id} className={styles.label}>
                    {label}
                </label>
                {hint}
            </div>
            <div className="relative">
                <input
                    id={id}
                    ref={ref}
                    className={cn(
                        styles.input,
                        isLine ? 'pr-16' : 'pr-12',
                        className,
                    )}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? errorId : undefined}
                    {...props}
                    type={showPassword ? 'text' : 'password'}
                />
                <button
                    type="button"
                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                    aria-pressed={showPassword}
                    onClick={() => setShowPassword((visible) => !visible)}
                    className={
                        isLine
                            ? 'absolute right-0 bottom-0 h-12 min-w-11 cursor-pointer text-right text-[0.9375rem] font-medium text-ink-soft transition-colors hover:text-ink focus-visible:text-ink'
                            : 'absolute top-8 right-0 flex -translate-y-1/2 items-center pr-4 text-secondary-foreground transition-colors hover:text-foreground focus-visible:text-foreground'
                    }
                >
                    {isLine ? (
                        showPassword ? (
                            'Hide'
                        ) : (
                            'Show'
                        )
                    ) : showPassword ? (
                        <EyeOff className="size-5 shrink-0" aria-hidden />
                    ) : (
                        <Eye className="size-5 shrink-0" aria-hidden />
                    )}
                </button>
            </div>
            {error ? (
                <p id={errorId} role="alert" className={styles.error}>
                    {error}
                </p>
            ) : null}
        </div>
    );
}
