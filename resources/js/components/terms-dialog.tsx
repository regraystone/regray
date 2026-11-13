import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/*
 * PLACEHOLDER WORDING: to be replaced with the studio's own Terms & Conditions
 * before launch. When the wording changes, change TERMS_VERSION too
 * (config/legal.php), so each stored agreement says which wording it was.
 */
const sections = [
    {
        heading: 'Your account',
        body: 'You are responsible for the details you give us and for keeping your password to yourself. Tell us straight away if you think someone else has used your account.',
    },
    {
        heading: 'Inquiries and quotations',
        body: 'An inquiry is a request, not an order. Prices, stock and lead times are confirmed in the quotation we send you, and a quotation is only binding once both sides have agreed to it.',
    },
    {
        heading: 'Visits and appointments',
        body: 'Please change or cancel a visit through your account as early as you can, so the time can be offered to someone else.',
    },
    {
        heading: 'Natural stone',
        body: 'Stone is a natural material. Colour, veining and markings vary from slab to slab and from the photos on this site.',
    },
    {
        heading: 'Your information',
        body: 'We use your information to run your account, answer your inquiries and arrange your visits. Promotional emails and announcements are only sent if you ask for them, and you can turn them off at any time from Account & Security in your profile.',
    },
    {
        heading: 'Changes to these terms',
        body: 'We may update these terms. The version you agreed to is kept with your account.',
    },
];

/**
 * The Terms & Conditions, over the page that asks about them rather than on a
 * page of their own, so nothing typed into a form is lost by reading them.
 *
 * With `onAgree` the footer also offers "I agree", for the sign-up form to
 * tick its box with; without it there is only Close.
 */
const TermsDialog = ({
    open,
    onOpenChange,
    version,
    onAgree,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The version a new agreement is stored with (config/legal.php). */
    version: string;
    onAgree?: () => void;
}) => (
    <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="flex max-h-[85vh] flex-col gap-0 p-0">
            <DialogHeader className="border-b border-border p-5 pr-14">
                <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                    Terms &amp; Conditions
                </DialogTitle>
                <DialogDescription>Version {version}</DialogDescription>
            </DialogHeader>

            <div className="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
                {sections.map((section) => (
                    <section key={section.heading} className="space-y-1.5">
                        <h3 className="text-sm font-bold tracking-[-0.02em]">
                            {section.heading}
                        </h3>
                        <p className="text-sm leading-relaxed text-secondary-foreground">
                            {section.body}
                        </p>
                    </section>
                ))}
            </div>

            <DialogFooter className="border-t border-border p-5">
                <Button
                    type="button"
                    variant={onAgree === undefined ? 'default' : 'outline'}
                    onClick={() => onOpenChange(false)}
                >
                    Close
                </Button>
                {onAgree === undefined ? null : (
                    <Button
                        type="button"
                        onClick={onAgree}
                        data-test="terms-agree"
                    >
                        I agree
                    </Button>
                )}
            </DialogFooter>
        </DialogContent>
    </Dialog>
);

export default TermsDialog;
