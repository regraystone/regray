import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

export default function Register() {
    return (
        <>
            <Head title="Sign Up" />

            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="space-y-5"
            >
                {({ processing, errors }) => (
                    <>
                        <Field
                            id="name"
                            type="text"
                            name="name"
                            label="Full Name"
                            placeholder="Full name"
                            autoComplete="name"
                            autoFocus
                            tabIndex={1}
                            error={errors.name}
                        />

                        <Field
                            id="email"
                            type="email"
                            name="email"
                            label="Email"
                            placeholder="email@example.com"
                            autoComplete="email"
                            tabIndex={2}
                            error={errors.email}
                        />

                        <Field
                            id="password"
                            type="password"
                            name="password"
                            label="Password"
                            placeholder="Password"
                            autoComplete="new-password"
                            tabIndex={3}
                            error={errors.password}
                        />

                        <Field
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            label="Confirm Password"
                            placeholder="Confirm password"
                            autoComplete="new-password"
                            tabIndex={4}
                            error={errors.password_confirmation}
                        />

                        <Button
                            type="submit"
                            className="mt-6 w-full"
                            tabIndex={5}
                            disabled={processing}
                            data-test="register-user-button"
                        >
                            {processing && <Spinner />}
                            Sign Up
                        </Button>
                    </>
                )}
            </Form>

            <p className="mt-8 text-center text-sm">
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

Register.layout = {
    title: 'Sign Up',
};
