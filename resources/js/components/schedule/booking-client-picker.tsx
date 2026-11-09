import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    searchBookingClientAccounts,
    type ClientAccount,
} from '@/lib/staff-clients-api';

/**
 * Who staff are booking the appointment for: a client account, found by name
 * or email. Chosen, it reads as the client's name with a Change button, so the
 * rest of the form is not pushed down by a list.
 */
const BookingClientPicker = ({
    value,
    onChange,
    error,
}: {
    value: ClientAccount | null;
    onChange: (account: ClientAccount | null) => void;
    error?: string;
}) => {
    const [search, setSearch] = useState('');
    const [accounts, setAccounts] = useState<ClientAccount[]>([]);
    const [hasSearched, setHasSearched] = useState(false);

    useEffect(() => {
        if (value !== null) {
            return;
        }

        const timer = window.setTimeout(() => {
            searchBookingClientAccounts(search)
                .then((response) => setAccounts(response.accounts))
                .catch(() => setAccounts([]))
                .finally(() => setHasSearched(true));
        }, 200);

        return () => window.clearTimeout(timer);
    }, [search, value]);

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
                            {value.email}
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
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search by name or email"
                        aria-label="Search client accounts"
                        aria-invalid={error !== undefined}
                        aria-describedby={error ? errorId : undefined}
                        className="mt-2 rounded-none"
                    />
                    {accounts.length === 0 ? (
                        hasSearched ? (
                            <p className="mt-2 text-sm text-secondary-foreground">
                                No client account matches. Only clients with a
                                Regray account can be booked here.
                            </p>
                        ) : null
                    ) : (
                        <ul className="mt-2 max-h-56 divide-y divide-sidebar-border overflow-y-auto rounded-md border border-sidebar-border">
                            {accounts.map((account) => (
                                <li key={account.id}>
                                    <button
                                        type="button"
                                        onClick={() => onChange(account)}
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
            )}

            {error ? (
                <p id={errorId} className="mt-1 text-sm text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
};

export default BookingClientPicker;
