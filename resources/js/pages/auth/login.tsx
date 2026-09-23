import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import AuthButton from '@/components/auth/auth-button';
import AuthField from '@/components/auth/auth-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Sign In" />

            {status ? (
                <p className="mb-6 text-center text-[0.9rem] font-medium text-secondary-foreground">
                    {status}
                </p>
            ) : null}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="space-y-5"
            >
                {({ processing, errors }) => (
                    <>
                        <AuthField
                            id="email"
                            type="email"
                            name="email"
                            label="Email"
                            placeholder="email@example.com"
                            autoComplete="email"
                            autoFocus
                            tabIndex={1}
                            error={errors.email}
                        />

                        <AuthField
                            id="password"
                            type="password"
                            name="password"
                            label="Password"
                            placeholder="Password"
                            autoComplete="current-password"
                            tabIndex={2}
                            error={errors.password}
                            hint={
                                canResetPassword ? (
                                    <Link
                                        href={request()}
                                        className="text-[0.8rem] font-semibold text-secondary-foreground transition-opacity hover:opacity-85"
                                        tabIndex={4}
                                    >
                                        Forgot password?
                                    </Link>
                                ) : undefined
                            }
                        />

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="remember"
                                name="remember"
                                tabIndex={5}
                            />
                            <label
                                htmlFor="remember"
                                className="text-xs tracking-[0.16em] text-secondary-foreground"
                            >
                                Remember me
                            </label>
                        </div>

                        <AuthButton
                            type="submit"
                            className="mt-6"
                            tabIndex={3}
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            Sign In
                        </AuthButton>
                    </>
                )}
            </Form>

            <p className="mt-8 text-center text-sm">
                Don&rsquo;t have an account?{' '}
                <Link
                    href={register()}
                    className="inline-flex items-center gap-1 font-bold underline-offset-4 hover:underline focus-visible:underline"
                    tabIndex={6}
                >
                    Sign Up <ArrowRight className="size-4" aria-hidden />
                </Link>
            </p>
        </>
    );
}

Login.layout = {
    title: 'Sign In',
};
