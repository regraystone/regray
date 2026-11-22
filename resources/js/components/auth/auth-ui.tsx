import type { ReactNode } from 'react';

/**
 * The auth pages' own pieces, on the paper of the auth layout: the large
 * buttons, the "or" divider, the notices and the links between pages.
 */

/** The main button: a tall black block. Pass it to Button's `className`. */
export const authPrimaryButton =
    'h-14 w-full rounded-md border-ink bg-ink text-base font-semibold tracking-normal normal-case text-paper hover:bg-black hover:opacity-100';

/** The second way through (Continue with Google, Cancel): outlined, filling on hover. */
export const authOutlineButton =
    'h-14 w-full rounded-md border-ink bg-transparent text-base font-semibold tracking-normal normal-case text-ink hover:bg-ink hover:text-paper hover:opacity-100';

/** An underlined link in running text. */
export const authLink =
    'border-b border-ink text-ink transition-colors hover:border-ink-muted hover:text-ink-muted';

/** A quieter link beside a field ("Forgot password?"). */
export const authQuietLink =
    'border-b border-field-line text-[0.9375rem] text-ink-soft transition-colors hover:text-ink';

/** The tick box: a little larger than the workspace's, ink when ticked. Pass it to Checkbox's `className`. */
export const authCheckbox =
    'mt-0.5 size-4.5 border-ink-muted data-[state=checked]:border-ink data-[state=checked]:bg-ink data-[state=checked]:text-paper';

/** The rule with "or" in it, between the form's button and Google's. */
export const AuthDivider = () => (
    <div
        role="separator"
        className="flex items-center gap-4 text-[0.9375rem] text-ink-muted"
    >
        <span aria-hidden className="h-px flex-1 bg-line" />
        or
        <span aria-hidden className="h-px flex-1 bg-line" />
    </div>
);

/**
 * A message above the form: `success` for something that went through (a
 * link sent, a password reset), `neutral` for anything else. Errors are an
 * alert, everything else a status.
 */
export const AuthNotice = ({
    tone = 'neutral',
    alert = false,
    children,
    'data-test': dataTest,
}: {
    tone?: 'success' | 'neutral';
    alert?: boolean;
    children: ReactNode;
    'data-test'?: string;
}) => (
    <p
        role={alert ? 'alert' : 'status'}
        data-test={dataTest}
        className={`rounded-md border px-4 py-3.5 text-[0.9375rem] leading-normal ${
            tone === 'success'
                ? 'border-success-line bg-success text-success-text'
                : 'border-line bg-paper-deep text-ink-soft'
        }`}
    >
        {children}
    </p>
);

/** The line at the foot of a form that leads to another auth page. */
export const AuthFootnote = ({ children }: { children: ReactNode }) => (
    <p className="text-[1.0625rem] text-ink-soft">{children}</p>
);
