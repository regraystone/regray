import { useEffect, useState } from 'react';
import DirectClientForm from '@/components/direct-client-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    searchBookingClientAccounts,
    type ClientAccount,
} from '@/lib/staff-clients-api';
import { cn } from '@/lib/utils';

/**
 * Who marketing is booking the appointment for: a client account
 * (`clientId`), or a direct (walk-in) client with no account
 * (`directClientId`), who is never told in the app.
 */
export type BookingClient =
    | { kind: 'account'; id: number; name: string; detail: string }
    | { kind: 'walkIn'; id: number; name: string; detail: string };

const fromAccount = (account: ClientAccount): BookingClient => ({
    kind: 'account',
    id: account.id,
    name: account.name,
    detail: account.email,
});

/**
 * Who staff are booking the appointment for. **Regray account**: found by
 * name or email. **Walk-in**: taken down in a dialog, the same form as the
 * Inquire page's Direct branch, which offers the client already on file
 * rather than a second one. Chosen, it reads as the client's name with a
 * Change button, so the rest of the form is not pushed down by a list.
 */
const BookingClientPicker = ({
    value,
    onChange,
    error,
}: {
    value: BookingClient | null;
    onChange: (client: BookingClient | null) => void;
    error?: string;
}) => {
    const [kind, setKind] = useState<BookingClient['kind']>('account');
    const [search, setSearch] = useState('');
    const [accounts, setAccounts] = useState<ClientAccount[]>([]);
    const [hasSearched, setHasSearched] = useState(false);
    const [isTakingDown, setIsTakingDown] = useState(false);

    useEffect(() => {
        if (value !== null || kind !== 'account') {
            return;
        }

        const timer = window.setTimeout(() => {
            searchBookingClientAccounts(search)
                .then((response) => setAccounts(response.accounts))
                .catch(() => setAccounts([]))
                .finally(() => setHasSearched(true));
        }, 200);

        return () => window.clearTimeout(timer);
    }, [search, value, kind]);

    const errorId = 'booking-client-error';

    return (
        <div data-testid="booking-client-picker">
            <p className="text-sm font-medium text-foreground">
                Client <span aria-hidden>*</span>
            </p>

            {value !== null ? (
                <div className="mt-2 flex items-center justify-between gap-3 rounded-md border border-sidebar-border px-3 py-2">
                    <span className="min-w-0">
                        <span className="block truncate text-sm font-bold">
                            {value.name}
                        </span>
                        <span className="block truncate text-xs text-secondary-foreground">
                            {value.detail}
                        </span>
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => onChange(null)}
                        aria-label={`Change client: ${value.name}`}
                    >
                        Change
                    </Button>
                </div>
            ) : (
                <>
                    <div
                        role="radiogroup"
                        aria-label="Platform or direct client"
                        className="mt-2 inline-flex rounded-md border border-sidebar-border p-0.5"
                    >
                        {(
                            [
                                ['account', 'Platform'],
                                ['walkIn', 'Direct'],
                            ] as const
                        ).map(([option, label]) => (
                            <button
                                key={option}
                                type="button"
                                role="radio"
                                aria-checked={kind === option}
                                onClick={() => setKind(option)}
                                className={cn(
                                    'rounded px-3 py-1 text-xs font-bold transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                    kind === option
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-secondary-foreground hover:text-foreground',
                                )}
                            >
                                {label}
                            </button>
                        ))}
                    </div>

                    {kind === 'account' ? (
                        <>
                            <Input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search by name or email"
                                aria-label="Search client accounts"
                                aria-invalid={error !== undefined}
                                aria-describedby={error ? errorId : undefined}
                                className="mt-2 rounded-none"
                            />
                            {accounts.length === 0 ? (
                                hasSearched ? (
                                    <p className="mt-2 text-sm text-secondary-foreground">
                                        No client account matches. A client with
                                        no account is a direct client.
                                    </p>
                                ) : null
                            ) : (
                                <ul className="mt-2 max-h-56 divide-y divide-sidebar-border overflow-y-auto rounded-md border border-sidebar-border">
                                    {accounts.map((account) => (
                                        <li key={account.id}>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    onChange(
                                                        fromAccount(account),
                                                    )
                                                }
                                                className="block w-full px-3 py-2 text-left transition-opacity hover:opacity-80 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                            >
                                                <span className="block truncate text-sm font-bold">
                                                    {account.name}
                                                </span>
                                                <span className="block truncate text-xs text-secondary-foreground">
                                                    {account.email}
                                                </span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </>
                    ) : (
                        <div className="mt-2 space-y-2">
                            <p className="text-sm text-secondary-foreground">
                                A direct client has no Regray account, so they
                                are not told in the app: let them know yourself.
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsTakingDown(true)}
                                aria-describedby={error ? errorId : undefined}
                            >
                                Take down the direct client
                            </Button>
                        </div>
                    )}
                </>
            )}

            {error ? (
                <p id={errorId} className="mt-1 text-sm text-destructive">
                    {error}
                </p>
            ) : null}

            <Dialog open={isTakingDown} onOpenChange={setIsTakingDown}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                            Direct client
                        </DialogTitle>
                        <DialogDescription>
                            Who the appointment is for, and how to reach them.
                        </DialogDescription>
                    </DialogHeader>
                    <DirectClientForm
                        useFor="Book for them"
                        onUse={(result) => {
                            setIsTakingDown(false);
                            onChange({
                                kind: 'walkIn',
                                id: result.client.id,
                                name: result.client.fullName,
                                detail: 'Direct',
                            });
                        }}
                    />
                </DialogContent>
            </Dialog>
        </div>
    );
};

export default BookingClientPicker;
