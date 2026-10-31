import { zodResolver } from '@hookform/resolvers/zod';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { PasswordField } from '@/components/ui/password-field';
import { Spinner } from '@/components/ui/spinner';
import AuthSheetLayout from '@/layouts/auth/auth-sheet-layout';
import {
    registerDefaults,
    registerFields,
    registerSchema,
    splitServerErrors,
    type RegisterFormInput,
    type RegisterFormValues,
} from '@/lib/auth-schema';
import { login } from '@/routes';
import { store } from '@/routes/register';

export default function Register() {
    /*
     * A server answer about something the form has no box for has nowhere to go
     * next to a field. Held here and shown above the button rather than thrown
     * away.
     */
    const [formError, setFormError] = useState<string[]>([]);

    const {
        register: registerField,
        handleSubmit,
        setError,
        resetField,
        formState: { errors, isSubmitting },
    } = useForm<RegisterFormInput, unknown, RegisterFormValues>({
        resolver: zodResolver(registerSchema),
        // Nothing is judged until the reader has actually tried to sign up,
        // then every keystroke is judged as it is fixed.
        mode: 'onSubmit',
        reValidateMode: 'onChange',
        defaultValues: registerDefaults,
    });

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

            <form
                noValidate
                /*
                 * Was `disableWhileProcessing` on Inertia's `<Form>`: while the
                 * server is deciding, the fields are not worth typing into.
                 */
                inert={isSubmitting}
                onSubmit={handleSubmit(onValid)}
                className="space-y-5 max-lg:space-y-(--auth-field-gap)"
            >
                <Field
                    id="name"
                    type="text"
                    label="Full Name"
                    placeholder="Full name"
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
                    tabIndex={5}
                    disabled={isSubmitting}
                    data-test="register-user-button"
                >
                    {isSubmitting && <Spinner />}
                    Sign Up
                </Button>
            </form>

            <p className="mt-8 text-center text-sm max-lg:mt-(--auth-block-gap)">
                Already have an account?{' '}
                <Link
                    href={login()}
                    className="inline-flex items-center gap-1 font-bold underline-offset-4 hover:underline focus-visible:underline"
                    tabIndex={6}
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
