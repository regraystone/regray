import { router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import { logout } from '@/routes';

/** Asks before logging out, wherever a Log out button is. */
const LogoutDialog = ({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) => {
    const [isLoggingOut, setIsLoggingOut] = useState(false);

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Log out?"
            description="You'll need to log in again to use your account."
            confirmLabel="Log out"
            cancelLabel="Stay logged in"
            processing={isLoggingOut}
            onConfirm={() => {
                setIsLoggingOut(true);
                router.flushAll();
                router.post(
                    logout.url(),
                    {},
                    { onFinish: () => setIsLoggingOut(false) },
                );
            }}
        />
    );
};

export default LogoutDialog;
