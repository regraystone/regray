import { router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import { logOut } from '@/lib/logout';
import { login } from '@/routes';

/**
 * Asks before switching account. There is one account per session, so
 * switching is logging out, the same request as Log out, and then going
 * straight to the sign-in page rather than to where a logout lands.
 */
const SwitchAccountDialog = ({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) => {
    const [isSwitching, setIsSwitching] = useState(false);

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Switch account?"
            description="You'll be logged out of this account and taken to the sign-in page."
            confirmLabel="Switch account"
            cancelLabel="Stay signed in"
            processing={isSwitching}
            onConfirm={() => {
                setIsSwitching(true);
                logOut({
                    onSuccess: () =>
                        router.visit(login.url(), { replace: true }),
                    onFinish: () => setIsSwitching(false),
                });
            }}
        />
    );
};

export default SwitchAccountDialog;
