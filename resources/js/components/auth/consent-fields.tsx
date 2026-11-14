import { Checkbox } from '@/components/ui/checkbox';

/**
 * The two boxes every sign-up asks: the Terms & Conditions, required, and
 * promotional emails, optional and off unless ticked. Shared by Sign Up and
 * Finish signing up with Google, so both ask in the same words.
 *
 * Controlled: the form owns both values. `tabIndex` is the first of the two
 * stops it takes, after whatever fields the form puts before it.
 */
const ConsentFields = ({
    terms,
    onTermsChange,
    termsError,
    onOpenTerms,
    marketingEmails,
    onMarketingEmailsChange,
    tabIndex,
}: {
    terms: boolean;
    onTermsChange: (agreed: boolean) => void;
    termsError?: string;
    onOpenTerms: () => void;
    marketingEmails: boolean;
    onMarketingEmailsChange: (wanted: boolean) => void;
    tabIndex: number;
}) => (
    <div className="space-y-3">
        <div>
            <div className="flex items-start gap-2">
                <Checkbox
                    id="terms"
                    name="terms"
                    tabIndex={tabIndex}
                    className="mt-0.5"
                    checked={terms}
                    onCheckedChange={(checked) =>
                        onTermsChange(checked === true)
                    }
                    aria-invalid={termsError ? true : undefined}
                    aria-describedby={termsError ? 'terms-error' : undefined}
                    data-test="register-terms"
                />
                {/*
                 * The button sits beside the label rather than in it, so
                 * opening the terms does not also tick the box.
                 */}
                <p className="text-sm text-secondary-foreground">
                    <label htmlFor="terms">I agree to the</label>{' '}
                    <button
                        type="button"
                        tabIndex={tabIndex}
                        onClick={onOpenTerms}
                        className="font-semibold text-foreground underline underline-offset-4"
                        data-test="register-terms-open"
                    >
                        Terms &amp; Conditions
                    </button>
                </p>
            </div>
            {termsError ? (
                <p
                    id="terms-error"
                    role="alert"
                    className="mt-2 text-[0.8rem] font-semibold text-secondary-foreground"
                >
                    {termsError}
                </p>
            ) : null}
        </div>

        <div className="flex items-start gap-2">
            <Checkbox
                id="marketing_emails"
                name="marketing_emails"
                tabIndex={tabIndex + 1}
                className="mt-0.5"
                checked={marketingEmails}
                onCheckedChange={(checked) =>
                    onMarketingEmailsChange(checked === true)
                }
            />
            <label
                htmlFor="marketing_emails"
                className="text-sm text-secondary-foreground"
            >
                Email me about new messages and updates (optional)
            </label>
        </div>
    </div>
);

export default ConsentFields;
