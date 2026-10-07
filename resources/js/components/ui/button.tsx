import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

const buttonVariants = cva(
    "inline-flex items-center justify-center gap-2 rounded-sm border px-5 py-3 text-sm font-bold tracking-[0.08em] uppercase transition-opacity hover:opacity-85 focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0",
    {
        variants: {
            variant: {
                default: 'border-primary bg-primary text-primary-foreground',
                destructive: 'border-destructive bg-destructive text-white',
                outline: 'border-border bg-background text-foreground',
                secondary: 'border-border bg-transparent text-secondary-foreground',
                ghost: 'border-transparent bg-transparent text-foreground',
                link: 'border-transparent bg-transparent text-primary underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-11 px-5 py-3',
                sm: 'h-9 px-3 py-2 text-xs',
                lg: 'h-12 px-6 py-4 text-base',
                icon: 'h-11 w-11',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

function Button({
    className,
    variant,
    size,
    asChild = false,
    ...props
}: VariantProps<typeof buttonVariants> &
    ComponentProps<'button'> & {
        asChild?: boolean;
    }) {
    const Comp = asChild ? Slot : 'button';

    return (
        <Comp
            data-slot="button"
            className={cn(buttonVariants({ variant, size, className }))}
            {...props}
        />
    );
}

export { Button, buttonVariants };
