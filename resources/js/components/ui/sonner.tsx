import { useFlashToast } from '@/hooks/use-flash-toast';
import { useIsMobile } from '@/hooks/use-is-mobile';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

/**
 * Application toast container.
 *
 * Top-center on phones, bottom-right elsewhere. On phones the toast is pushed
 * below the 4rem sticky header so the logo and account menu stay tappable.
 * Sonner lays out and centres the toast itself between its mobile offsets, so
 * nothing here positions it by hand.
 */
function Toaster({ ...props }: ToasterProps) {
    useFlashToast();
    const isMobile = useIsMobile();

    return (
        <Sonner
            theme="light"
            className="toaster group"
            position={isMobile ? 'top-center' : 'bottom-right'}
            mobileOffset={{
                top: 'calc(4rem + env(safe-area-inset-top, 0px) + 0.5rem)',
            }}
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                    '--border-radius': '0px',
                } as React.CSSProperties
            }
            toastOptions={{
                classNames: { toast: 'font-sans' },
                // Sonner's own stylesheet is unlayered, so it beats Tailwind's
                // `shadow-none`; an inline style is the only thing that wins.
                style: { boxShadow: 'none' },
            }}
            {...props}
        />
    );
}

export { Toaster };
