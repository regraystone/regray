import type { InputHTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type AuthFieldProps = InputHTMLAttributes<HTMLInputElement> & {
    id: string;
    label: string;
    error?: string;
    hint?: ReactNode;
};

const inputClasses =
    'mt-2 w-full rounded-none border border-border bg-background px-4 py-3 text-[0.98rem] text-foreground outline-none transition-colors focus:border-2 focus:border-primary';

export default function AuthField({
    id,
    label,
    error,
    hint,
    className,
    ...props
}: AuthFieldProps) {
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
            <input id={id} className={cn(inputClasses, className)} {...props} />
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
