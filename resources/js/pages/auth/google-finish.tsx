import { zodResolver } from '@hookform/resolvers/zod';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import GoogleController from '@/actions/App/Http/Controllers/Auth/GoogleController';
import ConsentFields from '@/components/auth/consent-fields';
import TermsDialog from '@/components/terms-dialog';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import AuthSheetLayout from '@/layouts/auth/auth-sheet-layout';
import {
    googleFinishFields,
    googleFinishSchema,
    splitServerErrors,
    type GoogleFinishFormInput,
    type GoogleFinishFormValues,
} from '@/lib/auth-schema';

/**
 * The one step between Google and a new account: Google has said who the
 * visitor is, and the account is only made once they agree to the terms, as
 * on Sign Up.
 */
export default function GoogleFinish({
    name,
    email,
    termsVersion,
}: {
    /** From Google; the reader may change it. */
    name: string;
    /** From Google, verified there: not editable here. */
    email: string;
    termsVersion: string;
}) {
    const [formError, setFormError] = useState<string[]>([]);
    const [termsOpen, setTermsOpen] = useState(false);

    const {
        watch,
        setValue,
        register: registerField,
        handleSubmit,
        setError,
        formState: { errors, isSubmitted, isSubmitting },
    } = useForm<GoogleFinishFormInput, unknown, GoogleFinishFormValues>({
        resolver: zodResolver(googleFinishSchema),
        mode: 'onSubmit',
        reValidateMode: 'onChange',
        defaultValues: { name, terms: false, marketing_emails: false },
    });

    const onValid = (values: GoogleFinishFormValues) =>
        new Promise<void>((resolve) => {
            router.post(GoogleController.finish.url(), values, {
                onError: (serverErrors) => {
                    setFormError(
                        splitServerErrors(
                            serverErrors,
                            googleFinishFields,
                            (field, message) => {
                                setError(field, { type: 'server', message });
                            },
                        ),
                    );
                },
                onFinish: () => resolve(),
            });
        });

    return (
        <>
            <Head title="Finish signing up" />

            <p className="mb-5 text-sm text-secondary-foreground">
                You're signing up with Google as{' '}
                <span className="font-semibold text-foreground">{email}</span>.
            </p>

            <form
                noValidate
                onSubmit={handleSubmit(onValid)}
                className="space-y-5 max-md:space-y-(--auth-field-gap)"
            >
                <Field
                    id="name"
                    type="text"
                    label="Name"
                    placeholder="Jane Doe"
                    autoComplete="name"
                    autoFocus
                    tabIndex={1}
                    error={errors.name?.message}
                    {...registerField('name')}
                />

                <ConsentFields
                    terms={watch('terms')}
                    onTermsChange={(agreed) =>
                        setValue('terms', agreed, {
                            shouldValidate: isSubmitted,
                        })
                    }
                    termsError={errors.terms?.message}
                    onOpenTerms={() => setTermsOpen(true)}
                    marketingEmails={watch('marketing_emails')}
                    onMarketingEmailsChange={(wanted) =>
                        setValue('marketing_emails', wanted)
                    }
                    tabIndex={2}
                />

                {formError.length > 0 ? (
                    <p
                        role="alert"
                        className="text-[0.8rem] font-semibold text-secondary-foreground"
                    >
                        {formError.join(' ')}
                    </p>
                ) : null}

                <Button
                    type="submit"
                    className="mt-1 w-full max-md:mt-(--auth-block-gap)"
                    tabIndex={4}
                    disabled={isSubmitting}
                    data-test="google-finish-button"
                >
                    {isSubmitting && <Spinner />}
                    Create account
                </Button>
            </form>

            <TermsDialog
                open={termsOpen}
                onOpenChange={setTermsOpen}
                version={termsVersion}
                onAgree={() => {
                    setValue('terms', true, { shouldValidate: isSubmitted });
                    setTermsOpen(false);
                }}
            />
        </>
    );
}

GoogleFinish.layout = [
    AuthSheetLayout,
    {
        title: 'Finish signing up',
    },
];
