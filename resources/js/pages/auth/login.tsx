import { zodResolver } from '@hookform/resolvers/zod';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { PasswordField } from '@/components/ui/password-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import AuthSheetLayout from '@/layouts/auth/auth-sheet-layout';
import {
    loginDefaults,
    loginFields,
    loginSchema,
    splitServerErrors,
    type LoginFormInput,
    type LoginFormValues,
} from '@/lib/auth-schema';
import { register as signUp } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
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
        <>
            <Head title="Sign In" />

            {status ? (
                <p className="mb-6 text-center text-[0.9rem] font-medium text-secondary-foreground max-lg:mb-(--auth-block-gap)">
                    {status}
                </p>
            ) : null}

            <form
                noValidate
                onSubmit={handleSubmit(onValid)}
                className="space-y-5 max-lg:space-y-(--auth-field-gap)"
            >
                <Field
                    id="email"
                    type="email"
                    label="Email"
                    placeholder="email@example.com"
                    autoComplete="email"
                    autoFocus
                    tabIndex={1}
                    error={errors.email?.message}
                    {...registerField('email')}
                />

                <PasswordField
                    id="password"
                    type="password"
                    label="Password"
                    placeholder="Password"
                    autoComplete="current-password"
                    tabIndex={2}
                    error={errors.password?.message}
                    {...registerField('password')}
                />

                <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <Controller
                            control={control}
                            name="remember"
                            render={({ field }) => (
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={5}
                                    checked={field.value}
                                    onCheckedChange={(checked) =>
                                        field.onChange(checked === true)
                                    }
                                />
                            )}
                        />
                        <label
                            htmlFor="remember"
                            className="text-sm text-secondary-foreground"
                        >
                            Remember me
                        </label>
                    </div>

                    {canResetPassword ? (
                        <Link
                            href={request()}
                            className="text-[0.8rem] font-semibold text-secondary-foreground transition-opacity hover:opacity-85"
                            tabIndex={4}
                        >
                            Forgot password?
                        </Link>
                    ) : null}
                </div>

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
                    className="mt-6 w-full max-lg:mt-(--auth-block-gap)"
                    tabIndex={3}
                    disabled={isSubmitting}
                    data-test="login-button"
                >
                    {isSubmitting && <Spinner />}
                    Sign In
                </Button>
            </form>

            <p className="mt-8 text-center text-sm max-lg:mt-(--auth-block-gap)">
                Don&rsquo;t have an account?{' '}
                <Link
                    href={signUp()}
                    className="inline-flex items-center gap-1 font-bold underline-offset-4 hover:underline focus-visible:underline"
                    tabIndex={6}
                >
                    Sign Up <ArrowRight className="size-4" aria-hidden />
                </Link>
            </p>
        </>
    );
}

Login.layout = [
    AuthSheetLayout,
    {
        title: 'Sign In',
    },
];
