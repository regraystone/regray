import type { ButtonHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export default function AuthButton({
    className,
    type = 'submit',
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            type={type}
            className={cn(
                'flex w-full cursor-pointer items-center justify-center gap-2 rounded-none border border-primary bg-primary px-5 py-3 text-sm font-bold tracking-[0.08em] text-primary-foreground uppercase transition-opacity hover:opacity-85 disabled:cursor-not-allowed disabled:opacity-60',
                className,
            )}
            {...props}
        />
    );
}
