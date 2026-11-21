import { zodResolver } from '@hookform/resolvers/zod';
import { router, usePage } from '@inertiajs/react';
import { Pencil, UserPlus } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import ConfirmDialog from '@/components/confirm-dialog';
import DataTable, {
    type DataTableColumn,
    type DataTableFilterOption,
    type DataTableFilters,
} from '@/components/data-table/data-table';
import RowActions from '@/components/data-table/row-actions';
import StatusToggle from '@/components/data-table/status-toggle';
import FormDrawer from '@/components/form-drawer';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import type { TableMeta } from '@/lib/data-table';
import { formatDate } from '@/lib/format-date';
import {
    archive as archiveAccount,
    status as accountStatus,
    store as storeAccount,
    update as updateAccount,
} from '@/routes/accounts';

/** One staff account as a row of the Accounts page, as `AccountResource` sends it. */
type AccountRow = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    roleLabel: string;
    isActive: boolean;
    createdAt: string | null;
    emailVerified: boolean;
    twoFactorEnabled: boolean;
    googleEmail: string | null;
    /** The actions this reader has on this row, decided by the server. */
    actions: string[];
    canToggleStatus: boolean;
};

type RoleOption = { value: string; label: string };

type AccountsPageProps = {
    accounts: { data: AccountRow[]; meta: TableMeta };
    filters: DataTableFilters & { status: 'all' | 'active' | 'inactive' };
    /** The staff roles an account may be given: never the admin or client. */
    assignableRoles: RoleOption[];
    /** The temporary password a new account starts with; shown, never sent. */
    defaultPassword: string;
    canCreate: boolean;
};

const ONLY = ['accounts', 'filters'];

const STATUS_OPTIONS: DataTableFilterOption[] = [
    { value: 'all', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const accountSchema = z.object({
    name: z.string().trim().min(1, 'Enter their full name.').max(255),
    email: z
        .string()
        .trim()
        .min(1, 'Enter their email.')
        .email('Enter a valid email address.')
        .max(255),
    role: z.string().min(1, 'Choose a role.'),
});

type AccountValues = z.infer<typeof accountSchema>;

type Drawer =
    | { mode: 'add' }
    | { mode: 'view'; account: AccountRow }
    | { mode: 'edit'; account: AccountRow };

/** A label and its value in the View drawer. */
const Detail = ({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) => (
    <div>
        <p className="mb-1 text-xs font-bold tracking-[0.05em] text-secondary-foreground uppercase">
            {label}
        </p>
        <div className="text-sm break-words">{children}</div>
    </div>
);

/**
 * The admin's Accounts page: the staff accounts the admin manages (never an
 * admin or a client), newest first, searched by name or email and filtered by
 * status.
 *
 * Add, Edit and View open in the drawer; Archive asks first. Each row's switch
 * turns the account on or off. What a row offers comes from the server
 * (`actions`, `canToggleStatus`): no one turns off or archives themselves or
 * the last active admin. A new account is a staff one, on the temporary
 * password, which it must change on its first sign-in.
 */
const Accounts = () => {
    const { props } = usePage<AccountsPageProps>();
    const getInitials = useInitials();
    const [drawer, setDrawer] = useState<Drawer | null>(null);
    const [archiving, setArchiving] = useState<AccountRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const form = useForm<AccountValues>({
        resolver: zodResolver(accountSchema),
        mode: 'onTouched',
        defaultValues: { name: '', email: '', role: '' },
    });
    const { register, handleSubmit, setError, reset, control, formState } =
        form;

    // The form starts from the account being edited, or empty to add one.
    useEffect(() => {
        if (drawer?.mode === 'edit') {
            reset({
                name: drawer.account.name,
                email: drawer.account.email,
                role: drawer.account.role ?? '',
            });
        } else if (drawer?.mode === 'add') {
            reset({ name: '', email: '', role: '' });
        }
    }, [drawer, reset]);

    const close = () => setDrawer(null);

    const showErrors = (errors: Record<string, string>) => {
        for (const field of ['name', 'email', 'role'] as const) {
            if (errors[field]) {
                setError(field, { type: 'server', message: errors[field] });
            }
        }

        if (errors.account) {
            toast.error(errors.account);
        }
    };

    const save = handleSubmit((values) => {
        const options = {
            only: ONLY,
            preserveScroll: true,
            preserveState: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: close,
            onError: showErrors,
        };

        if (drawer?.mode === 'edit') {
            router.put(updateAccount.url(drawer.account.id), values, options);
        } else {
            // Name, email and role only: the password is the server's own.
            router.post(storeAccount.url(), values, options);
        }
    });

    const archive = () => {
        if (archiving === null) {
            return;
        }

        router.post(
            archiveAccount.url(archiving.id),
            {},
            {
                only: ONLY,
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setArchiving(null),
                onError: (errors) => {
                    setArchiving(null);
                    toast.error(
                        errors.account ??
                            'The account could not be archived. Try again.',
                    );
                },
            },
        );
    };

    const columns: DataTableColumn<AccountRow>[] = [
        {
            key: 'name',
            header: 'Full name',
            cell: (row) => (
                <span className="flex items-center gap-3">
                    <Avatar className="size-8">
                        <AvatarFallback className="text-xs font-bold">
                            {getInitials(row.name)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="font-medium">{row.name}</span>
                </span>
            ),
        },
        {
            key: 'email',
            header: 'Email',
            cell: (row) => (
                <span className="block max-w-56 truncate" title={row.email}>
                    {row.email}
                </span>
            ),
        },
        {
            key: 'role',
            header: 'Role',
            cell: (row) => row.roleLabel,
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => (
                <StatusToggle
                    checked={row.isActive}
                    disabled={!row.canToggleStatus}
                    label={`${row.isActive ? 'Deactivate' : 'Activate'} ${row.name}`}
                    onToggle={(active, visit) =>
                        router.patch(
                            accountStatus.url(row.id),
                            { active },
                            {
                                only: ONLY,
                                preserveScroll: true,
                                preserveState: true,
                                onError: visit.onError,
                                onFinish: visit.onFinish,
                            },
                        )
                    }
                />
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            className: 'text-right',
            cell: (row) => (
                <RowActions
                    actions={row.actions}
                    subject={row.name}
                    maxInline={3}
                    handlers={{
                        view: () => setDrawer({ mode: 'view', account: row }),
                        edit: () => setDrawer({ mode: 'edit', account: row }),
                        archive: () => setArchiving(row),
                    }}
                />
            ),
        },
    ];

    const editing = drawer?.mode === 'edit' ? drawer.account : null;
    const viewing = drawer?.mode === 'view' ? drawer.account : null;

    return (
        <div className="w-full min-w-0">
            {props.canCreate ? (
                <PageHeader
                    variant="action"
                    title="Accounts"
                    subtitle="Manage who can sign in to Regray"
                    action={
                        <Button
                            type="button"
                            onClick={() => setDrawer({ mode: 'add' })}
                        >
                            <UserPlus aria-hidden />
                            Add account
                        </Button>
                    }
                />
            ) : (
                <PageHeader
                    title="Accounts"
                    subtitle="Manage who can sign in to Regray"
                />
            )}

            <DataTable
                rows={props.accounts.data}
                meta={props.accounts.meta}
                filters={props.filters}
                columns={columns}
                rowKey={(row) => row.id}
                only={ONLY}
                searchPlaceholder="Search by name or email"
                emptyTitle="No accounts yet"
                emptyDescription="Add a staff account to give someone access to Regray."
                filteredEmptyTitle="No accounts match that search"
                filteredEmptyDescription="Try another name or email, or a different status."
                statusOptions={STATUS_OPTIONS}
            />

            {/* View: read-only, with Edit to switch the same drawer over. */}
            <FormDrawer
                open={viewing !== null}
                onOpenChange={(open) => (open ? null : close())}
                title={viewing?.name ?? 'Account'}
                description={viewing?.email}
                footer={
                    viewing?.actions.includes('edit') ? (
                        <Button
                            type="button"
                            onClick={() =>
                                viewing &&
                                setDrawer({ mode: 'edit', account: viewing })
                            }
                        >
                            <Pencil aria-hidden />
                            Edit
                        </Button>
                    ) : undefined
                }
            >
                {viewing !== null ? (
                    <div
                        className="grid grid-cols-1 gap-5 sm:grid-cols-2"
                        data-testid="account-details"
                    >
                        <Detail label="Full name">{viewing.name}</Detail>
                        <Detail label="Email">{viewing.email}</Detail>
                        <Detail label="Role">{viewing.roleLabel}</Detail>
                        <Detail label="Status">
                            <StatusBadge active={viewing.isActive} />
                        </Detail>
                        <Detail label="Date joined">
                            {viewing.createdAt
                                ? formatDate(viewing.createdAt)
                                : '—'}
                        </Detail>
                        <Detail label="Email verified">
                            {viewing.emailVerified ? 'Yes' : 'Not yet'}
                        </Detail>
                        <Detail label="Two-factor authentication">
                            {viewing.twoFactorEnabled ? 'On' : 'Off'}
                        </Detail>
                        <Detail label="Google account">
                            {viewing.googleEmail ?? 'Not connected'}
                        </Detail>
                    </div>
                ) : null}
            </FormDrawer>

            {/* Add and Edit: one form. */}
            <FormDrawer
                open={drawer?.mode === 'add' || editing !== null}
                onOpenChange={(open) => (open ? null : close())}
                title={editing ? `Edit ${editing.name}` : 'Add account'}
                description={
                    editing
                        ? 'Change their name, email or role.'
                        : 'A staff account, on the temporary password below.'
                }
                submitLabel={editing ? 'Save changes' : 'Add account'}
                onSubmit={save}
                processing={processing}
            >
                <div className="space-y-5">
                    <FormField
                        id="account-name"
                        label="Full name"
                        required
                        error={formState.errors.name?.message}
                    >
                        {({ describedBy, invalid }) => (
                            <Input
                                id="account-name"
                                autoComplete="off"
                                className="mt-2 rounded-none"
                                aria-describedby={describedBy}
                                aria-invalid={invalid}
                                {...register('name')}
                            />
                        )}
                    </FormField>

                    <FormField
                        id="account-email"
                        label="Email"
                        required
                        error={formState.errors.email?.message}
                        hint={
                            editing
                                ? 'A new email has to be verified again.'
                                : undefined
                        }
                    >
                        {({ describedBy, invalid }) => (
                            <Input
                                id="account-email"
                                type="email"
                                autoComplete="off"
                                className="mt-2 rounded-none"
                                aria-describedby={describedBy}
                                aria-invalid={invalid}
                                {...register('email')}
                            />
                        )}
                    </FormField>

                    <FormField
                        id="account-role"
                        label="Role"
                        required
                        error={formState.errors.role?.message}
                    >
                        {({ describedBy, invalid }) => (
                            <Controller
                                control={control}
                                name="role"
                                render={({ field }) => (
                                    <Select
                                        value={field.value}
                                        onValueChange={field.onChange}
                                    >
                                        <SelectTrigger
                                            id="account-role"
                                            className="mt-2 w-full rounded-none"
                                            aria-describedby={describedBy}
                                            aria-invalid={invalid}
                                            onBlur={field.onBlur}
                                        >
                                            <SelectValue placeholder="Choose a role" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {props.assignableRoles.map(
                                                (role) => (
                                                    <SelectItem
                                                        key={role.value}
                                                        value={role.value}
                                                    >
                                                        {role.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                )}
                            />
                        )}
                    </FormField>

                    {editing === null ? (
                        <FormField
                            id="account-default-password"
                            label="Default password"
                            hint="Share this with the staff member. They'll be asked to set their own password the first time they sign in."
                        >
                            {({ describedBy }) => (
                                // Shown only: it is not a form field, so it is never sent.
                                <Input
                                    id="account-default-password"
                                    value={props.defaultPassword}
                                    readOnly
                                    disabled
                                    className="mt-2 rounded-none font-mono"
                                    aria-describedby={describedBy}
                                />
                            )}
                        </FormField>
                    ) : null}
                </div>
            </FormDrawer>

            <ConfirmDialog
                open={archiving !== null}
                onOpenChange={(open) => (open ? null : setArchiving(null))}
                title={`Archive ${archiving?.name ?? 'this account'}?`}
                description="They'll lose access to Regray at once and drop off this list. Their history stays."
                confirmLabel="Archive account"
                cancelLabel="Keep account"
                onConfirm={archive}
                destructive
                processing={processing}
            />
        </div>
    );
};

export default Accounts;
