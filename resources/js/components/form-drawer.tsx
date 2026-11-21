import { useRef, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetBody,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';

/** The fields the drawer focuses first when it opens. */
const FIRST_FIELD =
    'input:not([disabled]):not([readonly]):not([type="hidden"]), textarea:not([disabled]):not([readonly]), select:not([disabled]), button[role="combobox"]:not([disabled])';

/**
 * The drawer every list page adds, edits and views a row in. Anything that
 * removes a row asks in ConfirmDialog instead.
 *
 * With `onSubmit`, the body and footer are one form, and the footer is Cancel
 * and `submitLabel`; a page may pass its own `footer` instead, such as View's
 * Edit. It opens on its first field (or its close button when it has none).
 * While `processing`, nothing closes it: not the X, Cancel, Escape or the
 * overlay, so a save can't be walked away from halfway.
 */
const FormDrawer = ({
    open,
    onOpenChange,
    title,
    description,
    children,
    footer,
    submitLabel = 'Save',
    cancelLabel = 'Cancel',
    onSubmit,
    processing = false,
    size = 'md',
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    children: ReactNode;
    footer?: ReactNode;
    submitLabel?: string;
    cancelLabel?: string;
    onSubmit?: () => void;
    processing?: boolean;
    size?: 'md' | 'lg';
}) => {
    const bodyRef = useRef<HTMLDivElement>(null);

    const shownFooter =
        footer ??
        (onSubmit ? (
            <>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => onOpenChange(false)}
                    disabled={processing}
                >
                    {cancelLabel}
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing ? (
                        <Spinner className="motion-reduce:animate-none" />
                    ) : null}
                    {submitLabel}
                </Button>
            </>
        ) : null);

    const inner = (
        <>
            <SheetBody ref={bodyRef}>{children}</SheetBody>
            {shownFooter ? <SheetFooter>{shownFooter}</SheetFooter> : null}
        </>
    );

    return (
        <Sheet
            open={open}
            onOpenChange={(next) => {
                if (!processing) {
                    onOpenChange(next);
                }
            }}
        >
            <SheetContent
                size={size}
                closeDisabled={processing}
                onOpenAutoFocus={(event) => {
                    const first =
                        bodyRef.current?.querySelector<HTMLElement>(
                            FIRST_FIELD,
                        );

                    if (first) {
                        event.preventDefault();
                        first.focus();
                    }
                }}
            >
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                    {description ? (
                        <SheetDescription>{description}</SheetDescription>
                    ) : (
                        <SheetDescription className="sr-only">
                            {title}
                        </SheetDescription>
                    )}
                </SheetHeader>
                {onSubmit ? (
                    <form
                        noValidate
                        className="flex min-h-0 flex-1 flex-col"
                        onSubmit={(event) => {
                            event.preventDefault();

                            if (!processing) {
                                onSubmit();
                            }
                        }}
                    >
                        {inner}
                    </form>
                ) : (
                    inner
                )}
            </SheetContent>
        </Sheet>
    );
};

export default FormDrawer;
