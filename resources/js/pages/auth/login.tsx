import { zodResolver } from '@hookform/resolvers/zod';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import {
    AuthDivider,
    AuthFootnote,
    AuthNotice,
    authCheckbox,
    authLink,
    authPrimaryButton,
    authQuietLink,
} from '@/components/auth/auth-ui';
import GoogleButton from '@/components/auth/google-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { PasswordField } from '@/components/ui/password-field';
import { Spinner } from '@/components/ui/spinner';
import AuthBrandLayout from '@/layouts/auth/auth-brand-layout';
import {
    loginDefaults,
    loginFields,
    loginSchema,
    splitServerErrors,
    type LoginFormInput,
    type LoginFormValues,
    type SocialNotice as SocialNoticeData,
} from '@/lib/auth-schema';
import { register as signUp } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
    socialNotice?: SocialNoticeData | null;
};

export default function Login({
    status,
    canResetPassword,
    socialNotice,
}: Props) {
    /*
     * A server answer about something the form has no box for, such as a
     * throttle notice, has nowhere to go next to a field. Held here and shown
     * above the button rather than thrown away.
     */
    const [formError, setFormError] = useState<string[]>([]);

    const {
        control,
        register: registerField,
        handleSubmit,
        setError,
        resetField,
        formState: { errors, isSubmitting },
    } = useForm<LoginFormInput, unknown, LoginFormValues>({
        resolver: zodResolver(loginSchema),
        // Nothing is judged until the reader has actually tried to sign in,
        // then every keystroke is judged as it is fixed.
        mode: 'onSubmit',
        reValidateMode: 'onChange',
        defaultValues: loginDefaults,
    });

    const onValid = (values: LoginFormValues) =>
        new Promise<void>((resolve) => {
            router.post(store.url(), values, {
                onSuccess: () => {
                    resetField('password');
                },
                onError: (serverErrors) => {
                    const unplaced = splitServerErrors(
                        serverErrors,
                        loginFields,
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
            <Head title="Sign In" />

            {socialNotice ? (
                <AuthNotice
                    alert={socialNotice.type === 'error'}
                    data-test="social-notice"
                >
                    {socialNotice.message}
                </AuthNotice>
            ) : null}

            {status ? <AuthNotice tone="success">{status}</AuthNotice> : null}

            <form
                noValidate
                onSubmit={handleSubmit(onValid)}
                className="flex flex-col gap-7"
            >
                <Field
                    id="email"
                    type="email"
                    variant="line"
                    label="Email"
                    placeholder="you@example.com"
                    autoComplete="email"
                    autoFocus
                    tabIndex={1}
                    error={errors.email?.message}
                    {...registerField('email')}
                />

                <PasswordField
                    id="password"
                    type="password"
                    variant="line"
                    label="Password"
                    autoComplete="current-password"
                    tabIndex={2}
                    hint={
                        canResetPassword ? (
                            <Link
                                href={request()}
                                className={authQuietLink}
                                tabIndex={4}
                            >
                                Forgot password?
                            </Link>
                        ) : null
                    }
                    error={errors.password?.message}
                    {...registerField('password')}
                />

                <div className="flex items-center gap-3">
                    <Controller
                        control={control}
                        name="remember"
                        render={({ field }) => (
                            <Checkbox
                                id="remember"
                                name="remember"
                                tabIndex={5}
                                className={`${authCheckbox} mt-0`}
                                checked={field.value}
                                onCheckedChange={(checked) =>
                                    field.onChange(checked === true)
                                }
                            />
                        )}
                    />
                    <label
                        htmlFor="remember"
                        className="cursor-pointer text-[0.9375rem] text-ink-soft"
                    >
                        Remember me
                    </label>
                </div>

                {formError.length > 0 ? (
                    <AuthNotice alert>{formError.join(' ')}</AuthNotice>
                ) : null}

                <div className="flex flex-col gap-3.5">
                    <Button
                        type="submit"
                        className={authPrimaryButton}
                        tabIndex={3}
                        disabled={isSubmitting}
                        data-test="login-button"
                    >
                        {isSubmitting && <Spinner />}
                        Sign in
                    </Button>
                    <AuthDivider />
                    <GoogleButton />
                </div>
            </form>

            <AuthFootnote>
                Don&rsquo;t have an account?{' '}
                <Link href={signUp()} className={authLink} tabIndex={6}>
                    Sign up
                </Link>
            </AuthFootnote>
        </div>
    );
}

Login.layout = [
    AuthBrandLayout,
    {
        eyebrow: 'Welcome back',
        title: 'Sign in',
        description:
            'Pick up your saved stones, inquiries and messages where you left them.',
    },
];
