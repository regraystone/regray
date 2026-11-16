import { zodResolver } from '@hookform/resolvers/zod';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import {
    AuthDivider,
    AuthFootnote,
    AuthNotice,
    authLink,
    authPrimaryButton,
} from '@/components/auth/auth-ui';
import ConsentFields from '@/components/auth/consent-fields';
import GoogleButton from '@/components/auth/google-button';
import TermsDialog from '@/components/terms-dialog';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { PasswordField } from '@/components/ui/password-field';
import { Spinner } from '@/components/ui/spinner';
import AuthBrandLayout from '@/layouts/auth/auth-brand-layout';
import {
    passwordStrength,
    registerDefaults,
    registerFields,
    registerSchema,
    splitServerErrors,
    type RegisterFormInput,
    type RegisterFormValues,
    type SocialNotice as SocialNoticeData,
} from '@/lib/auth-schema';
import { login } from '@/routes';
import { store } from '@/routes/register';

export default function Register({
    termsVersion,
    socialNotice,
}: {
    termsVersion: string;
    socialNotice?: SocialNoticeData | null;
}) {
    /*
     * A server answer about something the form has no box for, such as a
     * throttle notice, has nowhere to go next to a field. Held here and shown
     * above the button rather than thrown away.
     */
    const [formError, setFormError] = useState<string[]>([]);

    const [termsOpen, setTermsOpen] = useState(false);

    const {
        setValue,
        watch,
        register: registerField,
        handleSubmit,
        setError,
        resetField,
        formState: { errors, isSubmitted, isSubmitting },
    } = useForm<RegisterFormInput, unknown, RegisterFormValues>({
        resolver: zodResolver(registerSchema),
        // Nothing is judged until the reader has actually tried to sign up,
        // then every keystroke is judged as it is fixed.
        mode: 'onSubmit',
        reValidateMode: 'onChange',
        defaultValues: registerDefaults,
    });

    const meetsPasswordRules = passwordStrength.safeParse(
        (watch('password') || '').trim(),
    ).success;

    const onValid = (values: RegisterFormValues) =>
        new Promise<void>((resolve) => {
            router.post(store.url(), values, {
                onSuccess: () => {
                    resetField('password');
                    resetField('password_confirmation');
                },
                onError: (serverErrors) => {
                    const unplaced = splitServerErrors(
                        serverErrors,
                        registerFields,
                        (field, message) => {
                            setError(field, { type: 'server', message });
                        },
                    );

                    setFormError(unplaced);
                },
                // Resolving here is what lets `isSubmitting` mean "the
                // server is still deciding" instead of "the values were
                // handed over".
                onFinish: () => resolve(),
            });
        });

    return (
        <div className="flex flex-col gap-7">
            <Head title="Sign Up" />

            {socialNotice ? (
                <AuthNotice
                    alert={socialNotice.type === 'error'}
                    data-test="social-notice"
                >
                    {socialNotice.message}
                </AuthNotice>
            ) : null}

            <form
                noValidate
                /*
                 * Was `disableWhileProcessing` on Inertia's `<Form>`: while the
                 * server is deciding, the fields are not worth typing into.
                 */
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

                <Field
                    id="email"
                    type="email"
                    variant="line"
                    label="Email"
                    placeholder="you@example.com"
                    autoComplete="email"
                    tabIndex={2}
                    error={errors.email?.message}
                    {...registerField('email')}
                />

                <div className="flex flex-col gap-3">
                    {/* Side by side when there is room, stacked below. */}
                    <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,11.25rem),1fr))] gap-x-6 gap-y-6.5">
                        <PasswordField
                            id="password"
                            type="password"
                            variant="line"
                            label="Password"
                            autoComplete="new-password"
                            tabIndex={3}
                            error={errors.password?.message}
                            {...registerField('password')}
                        />

                        <PasswordField
                            id="password_confirmation"
                            type="password"
                            variant="line"
                            label="Confirm password"
                            autoComplete="new-password"
                            tabIndex={4}
                            error={errors.password_confirmation?.message}
                            {...registerField('password_confirmation')}
                        />
                    </div>

                    {/* Turns green once the password meets every rule. */}
                    <p
                        className={`text-[0.8125rem] transition-colors ${
                            meetsPasswordRules
                                ? 'text-success-text'
                                : 'text-ink-muted'
                        }`}
                    >
                        Use 12+ characters, mixed case, a number and a symbol.
                    </p>
                </div>

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
                    tabIndex={5}
                />

                {formError.length > 0 ? (
                    <AuthNotice alert>{formError.join(' ')}</AuthNotice>
                ) : null}

                <div className="flex flex-col gap-3.5">
                    <Button
                        type="submit"
                        className={authPrimaryButton}
                        tabIndex={7}
                        disabled={isSubmitting}
                        data-test="register-button"
                    >
                        {isSubmitting && <Spinner />}
                        Sign up
                    </Button>
                    <AuthDivider />
                    <GoogleButton />
                </div>
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

            <AuthFootnote>
                Already have an account?{' '}
                <Link href={login()} className={authLink} tabIndex={8}>
                    Sign in
                </Link>
            </AuthFootnote>
        </div>
    );
}

Register.layout = [
    AuthBrandLayout,
    {
        eyebrow: 'Join Regray',
        title: 'Sign up',
        description:
            'Save stones and furniture, send inquiries, and follow each one until it is delivered.',
    },
];
