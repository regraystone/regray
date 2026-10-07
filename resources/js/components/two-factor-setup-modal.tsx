import { Form } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { Check, Copy, ScanLine, ShieldCheck } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { cn } from '@/lib/utils';
import { confirm } from '@/routes/two-factor';

const STEPS = ['Scan', 'Verify'] as const;

/**
 * The step rail, the square blocks and the border-only pieces below are what
 * make this dialog read as Regray rather than as a stock modal, so every step
 * in it is built from the app's monochrome tokens and nothing rounded.
 */
const otpSlotClasses =
    'h-14 w-11 rounded-none border-input bg-background text-xl font-bold shadow-none ring-primary first:rounded-none last:rounded-none sm:h-16 sm:w-14';

/**
 * The square mark that opens each step. The outline keeps the setup step
 * quiet because the QR code is already the focus there; the filled block gives
 * the verification step its single point of contrast.
 */
function StepMark({ filled = false }: { filled?: boolean }) {
    const Icon = filled ? ShieldCheck : ScanLine;

    return (
        <span
            aria-hidden="true"
            className={cn(
                'mx-auto grid size-12 shrink-0 place-items-center border',
                filled
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-border bg-secondary text-foreground',
            )}
        >
            <Icon className="size-5" />
        </span>
    );
}

function StepRail({ currentStep }: { currentStep: number }) {
    return (
        <div aria-hidden="true" className="flex gap-1">
            {STEPS.map((step, index) => (
                <span
                    key={step}
                    className={cn(
                        'h-1 flex-1',
                        index <= currentStep ? 'bg-primary' : 'bg-border',
                    )}
                />
            ))}
        </div>
    );
}

function TwoFactorSetupStep({
    qrCodeSvg,
    manualSetupKey,
    buttonText,
    onNextStep,
    errors,
}: {
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    buttonText: string;
    onNextStep: () => void;
    errors: string[];
}) {
    const [copiedText, copy] = useClipboard();
    const IconComponent = copiedText === manualSetupKey ? Check : Copy;

    if (errors?.length) {
        return <AlertError errors={errors} />;
    }

    return (
        <div className="w-full space-y-4">
            <div className="mx-auto w-full max-w-56 border border-border bg-white p-3">
                {qrCodeSvg ? (
                    <div
                        className="aspect-square w-full [&_svg]:size-full"
                        dangerouslySetInnerHTML={{
                            __html: qrCodeSvg,
                        }}
                    />
                ) : (
                    <div className="grid aspect-square w-full place-items-center">
                        <Spinner />
                    </div>
                )}
            </div>

            <Button type="button" className="w-full" onClick={onNextStep}>
                {buttonText}
            </Button>

            <div className="relative flex items-center justify-center">
                <span
                    aria-hidden="true"
                    className="absolute inset-x-0 top-1/2 h-px bg-border"
                />
                <span className="relative bg-background px-3 text-[0.65rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                    or enter the key
                </span>
            </div>

            <div className="flex items-stretch border border-border">
                {!manualSetupKey ? (
                    <div className="grid w-full place-items-center bg-secondary p-3">
                        <Spinner />
                    </div>
                ) : (
                    <>
                        <input
                            type="text"
                            readOnly
                            value={manualSetupKey}
                            aria-label="Two-factor setup key"
                            className="min-w-0 flex-1 bg-background px-4 py-3 font-mono text-sm tracking-[0.15em] text-foreground outline-none"
                        />
                        <button
                            type="button"
                            aria-label="Copy setup key"
                            onClick={() => copy(manualSetupKey)}
                            className="border-l border-border px-4 transition-colors hover:bg-secondary"
                        >
                            <IconComponent className="size-4" />
                        </button>
                    </>
                )}
            </div>
        </div>
    );
}

function TwoFactorVerificationStep({
    onClose,
    onBack,
}: {
    onClose: () => void;
    onBack: () => void;
}) {
    const [code, setCode] = useState<string>('');
    const pinInputContainerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setTimeout(() => {
            pinInputContainerRef.current?.querySelector('input')?.focus();
        }, 0);
    }, []);

    /*
     * The slots render from `code`, so a rejected code has to clear that state
     * alongside the field. Left to `resetOnError` alone the digits would stay
     * on screen while the input behind them was already empty.
     */
    return (
        <Form
            {...confirm.form()}
            onSuccess={() => onClose()}
            onError={() => setCode('')}
            resetOnError
            resetOnSuccess
            className="w-full"
        >
            {({
                processing,
                errors,
            }: {
                processing: boolean;
                errors?: { confirmTwoFactorAuthentication?: { code?: string } };
            }) => (
                <div className="w-full space-y-6">
                    <div
                        ref={pinInputContainerRef}
                        className="flex w-full flex-col items-center gap-3"
                    >
                        <InputOTP
                            id="otp"
                            name="code"
                            aria-label="Authentication code"
                            maxLength={OTP_MAX_LENGTH}
                            value={code}
                            onChange={setCode}
                            disabled={processing}
                            pattern={REGEXP_ONLY_DIGITS}
                            autoFocus
                        >
                            <InputOTPGroup>
                                {Array.from(
                                    { length: OTP_MAX_LENGTH },
                                    (_, index) => (
                                        <InputOTPSlot
                                            key={index}
                                            index={index}
                                            className={otpSlotClasses}
                                        />
                                    ),
                                )}
                            </InputOTPGroup>
                        </InputOTP>

                        <InputError
                            role="alert"
                            className="text-center"
                            message={
                                errors?.confirmTwoFactorAuthentication?.code
                            }
                        />

                        <p className="text-center text-[0.8rem] text-secondary-foreground">
                            Your code refreshes every 30 seconds.
                        </p>
                    </div>

                    <div className="flex gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            className="flex-1"
                            onClick={onBack}
                            disabled={processing}
                        >
                            Back
                        </Button>
                        <Button
                            type="submit"
                            className="flex-1"
                            disabled={
                                processing || code.length < OTP_MAX_LENGTH
                            }
                        >
                            {processing && <Spinner />}
                            Confirm
                        </Button>
                    </div>
                </div>
            )}
        </Form>
    );
}

type Props = {
    isOpen: boolean;
    onClose: () => void;
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    clearSetupData: () => void;
    fetchSetupData: () => Promise<void>;
    errors: string[];
};

export default function TwoFactorSetupModal({
    isOpen,
    onClose,
    requiresConfirmation,
    twoFactorEnabled,
    qrCodeSvg,
    manualSetupKey,
    clearSetupData,
    fetchSetupData,
    errors,
}: Props) {
    const [showVerificationStep, setShowVerificationStep] =
        useState<boolean>(false);

    const modalConfig = useMemo<{
        eyebrow: string;
        title: string;
        description: string;
        buttonText: string;
    }>(() => {
        if (twoFactorEnabled) {
            return {
                eyebrow: 'Two-factor authentication',
                title: 'Two-factor authentication is on',
                description:
                    'Every sign-in will now ask for a code from your authenticator app. Keep your recovery codes somewhere safe.',
                buttonText: 'Close',
            };
        }

        if (showVerificationStep) {
            return {
                eyebrow: `Step 2 of ${STEPS.length} · ${STEPS[1]}`,
                title: 'Verify authentication code',
                description:
                    'Enter the current 6-digit code from your authenticator app to finish linking it.',
                buttonText: 'Continue',
            };
        }

        return {
            eyebrow: `Step 1 of ${STEPS.length} · ${STEPS[0]}`,
            title: 'Enable two-factor authentication',
            description:
                'Scan the QR code with your authenticator app, or enter the setup key by hand.',
            buttonText: 'Continue',
        };
    }, [twoFactorEnabled, showVerificationStep]);

    const resetModalState = useCallback(() => {
        if (twoFactorEnabled) {
            clearSetupData();
        }

        setShowVerificationStep(false);
    }, [clearSetupData, twoFactorEnabled]);

    const handleClose = useCallback(() => {
        resetModalState();
        onClose();
    }, [onClose, resetModalState]);

    const handleModalNextStep = useCallback(() => {
        if (requiresConfirmation) {
            setShowVerificationStep(true);

            return;
        }

        clearSetupData();
        handleClose();
    }, [requiresConfirmation, clearSetupData, handleClose]);

    const fetchSetupDataRef = useRef(fetchSetupData);

    useEffect(() => {
        fetchSetupDataRef.current = fetchSetupData;
    }, [fetchSetupData]);

    useEffect(() => {
        if (isOpen && !qrCodeSvg) {
            void fetchSetupDataRef.current();
        }
    }, [isOpen, qrCodeSvg]);

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && handleClose()}>
            <DialogContent className="gap-0 overflow-hidden rounded-none border-border px-5 sm:max-w-md sm:px-6">
                {requiresConfirmation && !twoFactorEnabled ? (
                    <StepRail currentStep={showVerificationStep ? 1 : 0} />
                ) : null}

                {/* The top padding keeps the close button clear of the mark. */}
                <div className="max-h-[90dvh] space-y-5 overflow-y-auto overscroll-contain pt-9 sm:max-h-[calc(100dvh-2rem)]">
                    <DialogHeader className="gap-3 text-center sm:text-center">
                        <StepMark filled={showVerificationStep} />

                        <p className="eyebrow">{modalConfig.eyebrow}</p>

                        <DialogTitle className="text-2xl leading-tight font-bold tracking-[-0.04em] text-balance">
                            {modalConfig.title}
                        </DialogTitle>

                        <DialogDescription className="text-sm leading-relaxed text-secondary-foreground">
                            {modalConfig.description}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col items-center">
                        {showVerificationStep ? (
                            <TwoFactorVerificationStep
                                onClose={handleClose}
                                onBack={() => setShowVerificationStep(false)}
                            />
                        ) : (
                            <TwoFactorSetupStep
                                qrCodeSvg={qrCodeSvg}
                                manualSetupKey={manualSetupKey}
                                buttonText={modalConfig.buttonText}
                                onNextStep={handleModalNextStep}
                                errors={errors}
                            />
                        )}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
