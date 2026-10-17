import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { OtpField } from '@/components/ui/otp-field';
import { Spinner } from '@/components/ui/spinner';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { login } from '@/routes';
import { store } from '@/routes/two-factor/login';

export default function TwoFactorChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');
    /*
     * The recovery box stays uncontrolled so Inertia's `Form` can read it by
     * name, which means the value also has to be mirrored here for the submit
     * button to know whether there is anything worth sending.
     */
    const [recoveryCode, setRecoveryCode] = useState<string>('');

    const authConfigContent = useMemo<{
        title: string;
        description: string;
        toggleText: string;
    }>(() => {
        if (showRecoveryInput) {
            return {
                title: 'Recovery code',
                description:
                    'Please confirm access to your account by entering one of your emergency recovery codes.',
                toggleText: 'Use an authentication code instead',
            };
        }

        return {
            title: 'Authentication code',
            description:
                'Enter the authentication code provided by your authenticator application.',
            toggleText: 'Use a recovery code instead',
        };
    }, [showRecoveryInput]);

    setLayoutProps({
        title: authConfigContent.title,
        description: authConfigContent.description,
    });

    /*
     * `resetOnError` empties the fields in the DOM, but it fires no input event
     * for the values React is holding, so the cells would keep showing the
     * rejected code and the submit button would keep believing there is one.
     */
    const clearValues = (): void => {
        setCode('');
        setRecoveryCode('');
    };

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        clearValues();
    };

    return (
        <>
            <Head title="Two-factor authentication" />

            <Form
                {...store.form()}
                className="space-y-5"
                resetOnError
                resetOnSuccess={!showRecoveryInput}
                onError={clearValues}
            >
                {({ errors, processing, clearErrors }) => (
                    <>
                        {showRecoveryInput ? (
                            <div>
                                <Field
                                    id="recovery_code"
                                    name="recovery_code"
                                    type="text"
                                    label="Recovery code"
                                    placeholder="xxxxxxxxxx-xxxxxxxxxx"
                                    autoComplete="off"
                                    autoFocus
                                    onChange={(event) =>
                                        setRecoveryCode(event.target.value)
                                    }
                                    error={errors.recovery_code}
                                />

                                <p className="mt-2 text-xs text-secondary-foreground">
                                    Enter one of the emergency codes you saved
                                    when you set up two-factor authentication.
                                    Each code works once.
                                </p>
                            </div>
                        ) : (
                            <OtpField
                                id="code"
                                name="code"
                                label="Authentication code"
                                value={code}
                                onChange={setCode}
                                maxLength={OTP_MAX_LENGTH}
                                autoFocus
                                disabled={processing}
                                error={errors.code}
                            />
                        )}

                        <Button
                            type="submit"
                            className="mt-6 w-full"
                            disabled={
                                processing ||
                                (showRecoveryInput
                                    ? recoveryCode.trim() === ''
                                    : code.length !== OTP_MAX_LENGTH)
                            }
                            data-test="two-factor-button"
                        >
                            {processing && <Spinner />}
                            Continue
                        </Button>

                        <p className="mt-8 text-center text-sm">
                            <button
                                type="button"
                                className="inline-flex items-center gap-1 rounded-md font-bold underline-offset-4 hover:underline focus-visible:underline"
                                onClick={() => toggleRecoveryMode(clearErrors)}
                            >
                                {authConfigContent.toggleText}{' '}
                                <ArrowRight className="size-4" aria-hidden />
                            </button>
                        </p>

                        <p className="mt-4 text-center text-sm">
                            {/*
                             * `no-underline` is what makes this read like the
                             * cross links on Sign In and Sign Up; the rest of
                             * `TextLink`'s decoration colour is cancelled with
                             * `decoration-current` so nothing is left over.
                             */}
                            <TextLink
                                href={login()}
                                className="font-bold no-underline decoration-current underline-offset-4 hover:underline focus-visible:underline"
                            >
                                Back to Sign In
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}
