import { ArrowRight } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
import EmptyState from '@/components/empty-state';
import { LeadTallyBadges } from '@/components/lead-status-badge';
import PageHeading from '@/components/page-heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { InquiryLeadOption } from '@/types/inquiry';

/**
 * The staff Inquire flow's lead step, on the Platform branch: which lead the
 * inquiry is for. Staff send an inquiry for a lead's client, never for
 * themselves.
 *
 * The server sends only the leads this reader may use: sales sees leads with
 * an inquiry marketing has won, marketing its own leads. An inquiry sales
 * opens starts sales at once; one marketing opens waits for its decision.
 */
const InquiryLeadPicker = ({
    leads,
    onPick,
    description = 'Choose the lead this inquiry is for',
    emptyDescription = 'An inquiry is opened for a client’s lead. Sales can use leads with a won inquiry; marketing its own leads.',
    action,
}: {
    leads: InquiryLeadOption[];
    onPick: (lead: InquiryLeadOption) => void;
    description?: string;
    emptyDescription?: string;
    action?: ReactNode;
}) => {
    const [search, setSearch] = useState('');

    const shown = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return needle === ''
            ? leads
            : leads.filter((lead) =>
                  `${lead.reference} ${lead.client}`
                      .toLowerCase()
                      .includes(needle),
              );
    }, [leads, search]);

    return (
        <div className="mx-auto w-full max-w-3xl">
            <PageHeading
                title="Inquire"
                description={description}
                action={action}
            />

            {leads.length === 0 ? (
                <EmptyState
                    title="No leads to choose from"
                    description={emptyDescription}
                />
            ) : (
                <>
                    {leads.length > 5 ? (
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search by client or lead"
                            aria-label="Search leads"
                            className="mb-4 rounded-none"
                        />
                    ) : null}
                    <ul
                        className="divide-y divide-sidebar-border rounded-md border border-sidebar-border"
                        data-testid="inquiry-lead-picker"
                    >
                        {shown.map((lead) => (
                            <li key={lead.reference}>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => onPick(lead)}
                                    className="flex h-auto w-full items-center justify-between gap-3 rounded-none px-4 py-3 text-left normal-case"
                                >
                                    <span className="min-w-0">
                                        <span className="block truncate font-semibold">
                                            {lead.client}
                                        </span>
                                        <span className="text-xs font-normal tracking-normal text-secondary-foreground">
                                            {lead.reference}
                                        </span>
                                    </span>
                                    <span className="flex shrink-0 items-center gap-2">
                                        <LeadTallyBadges tally={lead.tally} />
                                        <ArrowRight aria-hidden />
                                    </span>
                                </Button>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </div>
    );
};

export default InquiryLeadPicker;
