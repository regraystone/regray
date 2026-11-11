import { useEffect, useState } from 'react';
import StaffPicker from '@/components/conversations/staff-picker';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    addGroupMembers,
    ApiError,
    searchAddableMembers,
    type Person,
} from '@/lib/message-api';
import type { ConversationSummary } from '@/types/message';

type Addable = { accounting: Person[]; logistics: Person[] };

const NOBODY: Addable = { accounting: [], logistics: [] };

/**
 * A sales member adds one accounting and one logistics person to an inquiry's
 * group chat, together, which assigns them to the inquiry. The server offers
 * only people not in the group yet, nobody for a place already taken, and
 * refuses anything but exactly one of each.
 */
const AddMembersDialog = ({
    open,
    conversationId,
    onOpenChange,
    onAdded,
}: {
    open: boolean;
    conversationId: number;
    onOpenChange: (open: boolean) => void;
    onAdded: (conversation: ConversationSummary) => void;
}) => {
    const [search, setSearch] = useState('');
    const [people, setPeople] = useState<Addable>(NOBODY);
    const [accountingId, setAccountingId] = useState<number | null>(null);
    const [logisticsId, setLogisticsId] = useState<number | null>(null);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    useEffect(() => {
        if (!open) {
            setSearch('');
            setAccountingId(null);
            setLogisticsId(null);
            setErrors({});

            return;
        }

        const timer = window.setTimeout(() => {
            searchAddableMembers(conversationId, search)
                .then(setPeople)
                .catch(() => setPeople(NOBODY));
        }, 200);

        return () => window.clearTimeout(timer);
    }, [open, search, conversationId]);

    const submit = async () => {
        if (accountingId === null || logisticsId === null) {
            return;
        }

        setSaving(true);
        setErrors({});

        try {
            const { conversation } = await addGroupMembers(conversationId, {
                accountingId,
                logisticsId,
            });
            onAdded(conversation);
            onOpenChange(false);
        } catch (failure) {
            setErrors(
                failure instanceof ApiError
                    ? failure.errors
                    : { accountingId: ['They could not be added.'] },
            );
        } finally {
            setSaving(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                        Add accounting and logistics
                    </DialogTitle>
                    <DialogDescription>
                        Choose one person from each, who handle payment and
                        delivery for this inquiry.
                    </DialogDescription>
                </DialogHeader>
                <Input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search accounting and logistics"
                    aria-label="Search accounting and logistics"
                    className="rounded-none"
                />
                <StaffPicker
                    name="add-accounting"
                    legend="Accounting"
                    people={people.accounting}
                    selected={accountingId}
                    onSelect={setAccountingId}
                    empty="Nobody from accounting to add."
                    error={errors.accountingId?.[0] ?? null}
                />
                <StaffPicker
                    name="add-logistics"
                    legend="Logistics"
                    people={people.logistics}
                    selected={logisticsId}
                    onSelect={setLogisticsId}
                    empty="Nobody from logistics to add."
                    error={errors.logisticsId?.[0] ?? null}
                />
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={saving}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        onClick={() => void submit()}
                        disabled={
                            saving ||
                            accountingId === null ||
                            logisticsId === null
                        }
                    >
                        {saving ? (
                            <Spinner className="motion-reduce:animate-none" />
                        ) : null}
                        Add
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default AddMembersDialog;
