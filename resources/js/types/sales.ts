/** An amount as the server sends it: the database's decimal string, "1250.00". */
export type Amount = string;

export type SalesPeriodValue =
    | 'this_month'
    | 'last_3_months'
    | 'this_year'
    | 'all';

export type SalesPageProps = {
    period: SalesPeriodValue;
    periods: { value: SalesPeriodValue; label: string }[];
    /** `all` for the admin and the owner; `assigned` for accounting, whose figures are its own inquiries'. */
    scope: 'all' | 'assigned';
    figures: {
        contractsWon: { count: number; total: Amount };
        cash: {
            payments: Amount;
            paymentsCount: number;
            refunds: Amount;
            refundsCount: number;
            net: Amount;
        };
        balance: { count: number; total: Amount };
        openQuotes: { count: number; total: Amount };
        winRate: { won: number; rejected: number; rate: number | null };
    };
    /** The last 12 months, oldest first, keyed "2026-10". */
    months: { month: string; payments: Amount; refunds: Amount }[];
    byService: { service: string; count: number; total: Amount }[];
    byPaymentStatus: {
        status: string;
        label: string;
        count: number;
        total: Amount;
    }[];
    balances: {
        reference: string;
        client: string;
        service: string;
        contractTotal: Amount;
        paid: Amount;
        balance: Amount;
        paymentStatus: string | null;
        lastPaymentAt: string | null;
        url: string;
    }[];
    transactions: {
        id: number;
        kind: 'payment' | 'refund';
        amount: Amount;
        receivedAt: string;
        reference: string;
        client: string;
        recordedBy: string | null;
        url: string;
    }[];
};
