import { zodResolver } from '@hookform/resolvers/zod';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import ConsentFields from '@/components/auth/consent-fields';
import TermsDialog from '@/components/terms-dialog';
import GoogleButton from '@/components/auth/google-button';
import SocialNotice from '@/components/auth/social-notice';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { PasswordField } from '@/components/ui/password-field';
import { Spinner } from '@/components/ui/spinner';
import AuthSheetLayout from '@/layouts/auth/auth-sheet-layout';
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
        <>
            <Head title="Sign Up" />

            <SocialNotice notice={socialNotice} />

            <form
                noValidate
                /*
                 * Was `disableWhileProcessing` on Inertia's `<Form>`: while the
                 * server is deciding, the fields are not worth typing into.
                 */
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

                <Field
                    id="email"
                    type="email"
                    label="Email"
                    placeholder="email@example.com"
                    autoComplete="email"
                    tabIndex={2}
                    error={errors.email?.message}
                    {...registerField('email')}
                />

                <div className="space-y-1.5">
                    {/* Side by side from md up, stacked below. */}
                    <div className="space-y-5 max-md:space-y-(--auth-field-gap) md:grid md:grid-cols-2 md:gap-4 md:space-y-0">
                        <PasswordField
                            id="password"
                            type="password"
                            label="Password"
                            placeholder="Password"
                            autoComplete="new-password"
                            tabIndex={3}
                            error={errors.password?.message}
                            {...registerField('password')}
                        />

                        <PasswordField
                            id="password_confirmation"
                            type="password"
                            label="Confirm Password"
                            placeholder="Confirm password"
                            autoComplete="new-password"
                            tabIndex={4}
                            error={errors.password_confirmation?.message}
                            {...registerField('password_confirmation')}
                        />
                    </div>

                    <p
                        className={
                            meetsPasswordRules
                                ? 'text-xs font-medium text-green-600 dark:text-green-400'
                                : 'text-xs font-medium text-red-600 dark:text-red-400'
                        }
                    >
                        Use 12+ characters, mixed case, a number and a symbol
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
                    tabIndex={7}
                    disabled={isSubmitting}
                    data-test="register-button"
                >
                    {isSubmitting && <Spinner />}
                    Sign Up
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

            <div className="relative my-5">
                <div className="absolute inset-0 flex items-center">
                    <span className="w-full border-t border-border" />
                </div>
                <div className="relative flex justify-center text-xs uppercase">
                    <span className="bg-background px-2 text-secondary-foreground">
                        Or
                    </span>
                </div>
            </div>

            <GoogleButton />

            <p className="mt-8 text-center text-sm max-md:mt-(--auth-block-gap)">
                Already have an account?{' '}
                <Link
                    href={login()}
                    className="inline-flex items-center gap-1 font-bold underline-offset-4 hover:underline focus-visible:underline"
                    tabIndex={8}
                >
                    Sign In <ArrowRight className="size-4" aria-hidden />
                </Link>
            </p>
        </>
    );
}

Register.layout = [
    AuthSheetLayout,
    {
        title: 'Sign Up',
    },
];
