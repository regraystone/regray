import { zodResolver } from '@hookform/resolvers/zod';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { PasswordField } from '@/components/ui/password-field';
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
import { toast } from 'sonner';

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
                <p className="mb-6 text-center text-[0.9rem] font-medium text-secondary-foreground max-md:mb-(--auth-block-gap)">
                    {status}
                </p>
            ) : null}

            <form
                noValidate
                onSubmit={handleSubmit(onValid)}
                className="space-y-5 max-md:space-y-(--auth-field-gap)"
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
                    className="mt-1 w-full max-md:mt-(--auth-block-gap)"
                    tabIndex={3}
                    disabled={isSubmitting}
                    data-test="login-button"
                >
                    {isSubmitting && <Spinner />}
                    Sign In
                </Button>
            </form>

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

            <Button
                type="button"
                variant="outline"
                className="w-full gap-2.5 tracking-normal"
                onClick={() => toast.info('This feature is coming soon.')}
            >
                <svg
                    className="size-4 shrink-0"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                        fill="#4285F4"
                    />
                    <path
                        d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                        fill="#34A853"
                    />
                    <path
                        d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"
                        fill="#FBBC05"
                    />
                    <path
                        d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"
                        fill="#EA4335"
                    />
                </svg>
                Continue with Google
            </Button>

            <p className="mt-8 text-center text-sm max-md:mt-(--auth-block-gap)">
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
