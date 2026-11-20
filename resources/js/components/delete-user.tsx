import { Form, Link } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { mailTo } from '@/components/public/public-links';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import { index as inboxIndex } from '@/routes/inbox';

/**
 * Whether the account can be deleted, and what holds it when it cannot
 * (AccountDeletionService::check()).
 */
export type AccountDeletion = {
    allowed: boolean;
    audience: 'client' | 'staff';
    inquiries: number;
    bookings: number;
    leads: number;
    message: string | null;
};

const plural = (count: number, one: string, many: string) =>
    `${count} ${count === 1 ? one : many}`;

/** What holds the account, as "5 inquiries · 1 booking", or null when nothing is counted. */
const heldBy = (deletion: AccountDeletion): string | null => {
    const parts = [
        deletion.inquiries > 0
            ? plural(deletion.inquiries, 'inquiry', 'inquiries')
            : null,
        deletion.bookings > 0
            ? plural(deletion.bookings, 'booking', 'bookings')
            : null,
        deletion.leads > 0 ? plural(deletion.leads, 'lead', 'leads') : null,
    ].filter((part): part is string => part !== null);

    return parts.length > 0 ? parts.join(' · ') : null;
};

/**
 * Why the account cannot be deleted, in plain words: what was found, that it
 * has to be settled under the deletion policy, and who to ask. A client is
 * offered their Regray Team inbox (or the support email when they have no
 * inbox); staff are told to ask an administrator, who manages their account.
 */
const DeletionUnavailable = ({ deletion }: { deletion: AccountDeletion }) => {
    const { can } = usePermissions();
    const found = heldBy(deletion);

    return (
        <div
            role="status"
            className="space-y-3 rounded-md border border-border bg-muted/50 p-4"
            data-test="account-deletion-unavailable"
        >
            <div className="flex gap-3">
                <CircleAlert
                    className="mt-0.5 size-5 shrink-0 text-foreground"
                    aria-hidden
                />
                <div className="space-y-2 text-sm">
                    <p className="font-semibold">
                        Account deletion is unavailable
                    </p>
                    <p>{deletion.message}</p>
                    {found ? (
                        <p className="text-muted-foreground">
                            Linked to your account: {found}.
                        </p>
                    ) : null}
                    <p className="text-muted-foreground">
                        These records have to be resolved or handled under our
                        account deletion policy before the account can be
                        removed.
                    </p>
                </div>
            </div>

            {deletion.audience === 'client' ? (
                <div className="pl-8">
                    {can(permissions.messagesView) ? (
                        <Button asChild variant="outline" size="sm">
                            <Link href={inboxIndex.url()}>Contact support</Link>
                        </Button>
                    ) : (
                        <Button asChild variant="outline" size="sm">
                            <a href={mailTo('Account deletion')}>
                                Contact support
                            </a>
                        </Button>
                    )}
                </div>
            ) : null}
        </div>
    );
};

export default function DeleteUser({
    deletion,
}: {
    deletion: AccountDeletion;
}) {
    const passwordInput = useRef<HTMLInputElement>(null);

    if (!deletion.allowed) {
        return <DeletionUnavailable deletion={deletion} />;
    }

    return (
        <div className="space-y-4 rounded-md border border-destructive/20 bg-destructive/10 p-4">
            <div className="relative space-y-0.5 text-destructive">
                <p className="font-medium">Warning</p>
                <p className="text-sm">
                    Please proceed with caution, this cannot be undone.
                </p>
            </div>

            <Dialog>
                <DialogTrigger asChild>
                    <Button
                        variant="destructive"
                        data-test="delete-user-button"
                    >
                        Delete account
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>
                        Are you sure you want to delete your account?
                    </DialogTitle>
                    <DialogDescription>
                        Once your account is deleted, all of its resources and
                        data will also be permanently deleted. Please enter your
                        password to confirm you would like to permanently delete
                        your account.
                    </DialogDescription>

                    <Form
                        {...ProfileController.destroy.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        onError={() => passwordInput.current?.focus()}
                        resetOnSuccess
                        className="space-y-6"
                    >
                        {({ resetAndClearErrors, processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="password"
                                        className="sr-only"
                                    >
                                        Password
                                    </Label>

                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        ref={passwordInput}
                                        placeholder="Password"
                                        autoComplete="current-password"
                                    />

                                    <InputError message={errors.password} />
                                </div>

                                {/*
                                 * Should a record appear between opening the page
                                 * and confirming, the server still refuses, and
                                 * says why here.
                                 */}
                                {errors.account ? (
                                    <p
                                        role="alert"
                                        className="rounded-md border border-border bg-muted/50 p-3 text-sm"
                                        data-test="account-deletion-refused"
                                    >
                                        {errors.account}
                                    </p>
                                ) : null}

                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                resetAndClearErrors()
                                            }
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>

                                    <Button
                                        variant="destructive"
                                        disabled={processing}
                                        asChild
                                    >
                                        <button
                                            type="submit"
                                            data-test="confirm-delete-user-button"
                                        >
                                            Delete account
                                        </button>
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
