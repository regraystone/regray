import { Eye, EyeOff } from 'lucide-react';
import { useState, type ComponentProps, type ReactNode, type Ref } from 'react';
import { cn } from '@/lib/utils';

type PasswordFieldProps = ComponentProps<'input'> & {
    label: string;
    error?: string;
    hint?: ReactNode;
    ref?: Ref<HTMLInputElement>;
};

const inputClasses =
    'mt-2 w-full rounded-none border border-border bg-background px-4 py-3 text-[0.98rem] text-foreground outline-none transition-colors focus:border-2 focus:border-primary';

export function PasswordField({
    id,
    label,
    error,
    hint,
    className,
    ref,
    ...props
}: PasswordFieldProps) {
    const [showPassword, setShowPassword] = useState(false);

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
            <div className="relative">
                <input
                    id={id}
                    ref={ref}
                    className={cn(inputClasses, 'pr-12', className)}
                    {...props}
                    type={showPassword ? 'text' : 'password'}
                />
                <button
                    type="button"
                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                    aria-pressed={showPassword}
                    onClick={() => setShowPassword((visible) => !visible)}
                    className="absolute top-8 right-0 flex -translate-y-1/2 items-center pr-4 text-secondary-foreground transition-colors hover:text-foreground focus-visible:text-foreground"
                >
                    {showPassword ? (
                        <EyeOff className="size-5 shrink-0" aria-hidden />
                    ) : (
                        <Eye className="size-5 shrink-0" aria-hidden />
                    )}
                </button>
            </div>
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
