import { zodResolver } from '@hookform/resolvers/zod';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import GoogleController from '@/actions/App/Http/Controllers/Auth/GoogleController';
import { AuthNotice, authPrimaryButton } from '@/components/auth/auth-ui';
import ConsentFields from '@/components/auth/consent-fields';
import TermsDialog from '@/components/terms-dialog';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import AuthBrandLayout from '@/layouts/auth/auth-brand-layout';
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
        <div className="flex flex-col gap-7">
            <Head title="Finish signing up" />

            <div className="flex flex-col gap-1.5 border-y border-line py-5">
                <span className="text-[0.9375rem] font-semibold text-ink-soft">
                    Signing up with Google as
                </span>
                <span className="text-lg break-all">{email}</span>
            </div>

            <form
                noValidate
                onSubmit={handleSubmit(onValid)}
                className="flex flex-col gap-6.5"
            >
                <Field
                    id="name"
                    type="text"
                    variant="line"
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
                    <AuthNotice alert>{formError.join(' ')}</AuthNotice>
                ) : null}

                <Button
                    type="submit"
                    className={authPrimaryButton}
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
        </div>
    );
}

GoogleFinish.layout = [
    AuthBrandLayout,
    {
        eyebrow: 'Join Regray',
        title: 'Finish signing up',
        description:
            'Google has told us who you are. Check your name and agree to the terms to create your account.',
    },
];
