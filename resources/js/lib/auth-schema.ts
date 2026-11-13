import { z } from 'zod';

/*
 * Zod v4 takes its messages as `error`, not the v3 `message`. Both spellings
 * work at runtime, but `error` is the one v4 documents, so every message here
 * uses it.
 *
 * The chains pipe rather than stack: a pipe stops at the first failure, so an
 * untouched email reads as "Enter your email address" instead of also being
 * told it is not a valid email address. Stacked rules would report both.
 */

/*
 * Email, shared by both forms.
 *
 * Trimmed because an address pasted with spaces round the corner is almost
 * never what was meant, and because the max has to count what is actually sent
 * rather than what was typed.
 */
const emailSchema = z
    .string()
    .trim()
    .min(1, { error: 'Enter your email address' })
    .pipe(z.email({ error: 'Enter a valid email address' }))
    .pipe(
        z.string().max(255, {
            error: 'Keep the email to 255 characters or fewer',
        }),
    );

/*
 * The strength half of Laravel's `Password::defaults()`, which is only
 * configured in production (see AppServiceProvider). `uncompromised` stays
 * server-side: it is a dictionary lookup against a third-party breach list, so
 * there is nothing to mirror here.
 *
 * One message per rule, so a password that misses several of them says so once
 * each rather than as one long sentence.
 */
export const passwordStrength = z
    .string()
    .min(12, { error: 'Use at least 12 characters' })
    .regex(/[a-z]/, {
        error: 'Include an uppercase and a lowercase letter',
    })
    .regex(/[A-Z]/, {
        error: 'Include an uppercase and a lowercase letter',
    })
    .regex(/[0-9]/, { error: 'Include a number' })
    .regex(/[^A-Za-z0-9]/, { error: 'Include a symbol' });

/**
 * Sign In.
 *
 * No strength rule on the password: an old account may well predate the
 * current defaults, and the sign-in form is not where that objection belongs.
 * The server still refuses the wrong password.
 */
export const loginSchema = z.object({
    email: emailSchema,
    password: z.string().trim().min(1, { error: 'Enter your password' }),
    remember: z.boolean(),
});

export type LoginFormInput = z.input<typeof loginSchema>;
export type LoginFormValues = z.output<typeof loginSchema>;

export const loginDefaults = {
    email: '',
    password: '',
    remember: false,
} satisfies LoginFormValues;

/** Every field the sign-in form owns, for mapping server errors onto. */
export const loginFields = ['email', 'password', 'remember'] as const;

/**
 * Sign Up.
 *
 * The names are Fortify's own, kept exactly as they are because Fortify reads
 * the request by name. The password strength only applies in production, where
 * the server enforces it too, so development and test can still register
 * people with a simple password.
 */
export const registerSchema = z
    .object({
        name: z
            .string()
            .trim()
            .min(1, { error: 'Enter your full name' })
            .max(255, {
                error: 'Keep the name to 255 characters or fewer',
            }),
        email: emailSchema,
        password: z
            .string()
            .trim()
            .min(1, { error: 'Enter a password' })
            .pipe(import.meta.env.PROD ? passwordStrength : z.string()),
        password_confirmation: z
            .string()
            .trim()
            .min(1, { error: 'Confirm your password' }),
        // Required, as the server requires it: no account without the terms.
        terms: z.boolean().refine((agreed) => agreed, {
            error: 'Agree to the Terms & Conditions to create an account',
        }),
        // Optional, and off unless ticked: a client who leaves it off gets
        // message notifications on the site only, never by email.
        marketing_emails: z.boolean(),
    })
    /*
     * On the confirmation rather than on the password, because that is where the
     * reader is looking when they read it: the two boxes are the same password
     * and the mistake is in the second one. The path is explicit for the same
     * reason — without it the message would land on the object and be lost.
     */
    .refine((values) => values.password === values.password_confirmation, {
        error: "Passwords don't match",
        path: ['password_confirmation'],
    });

export type RegisterFormInput = z.input<typeof registerSchema>;
export type RegisterFormValues = z.output<typeof registerSchema>;

export const registerDefaults = {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    // Unticked to start with: the reader ticks it themselves, and neither the
    // form nor the server makes an account without it.
    terms: false,
    marketing_emails: false,
} satisfies RegisterFormValues;

/** Every field the sign-up form owns, for mapping server errors onto. */
export const registerFields = [
    'name',
    'email',
    'password',
    'password_confirmation',
    'terms',
    'marketing_emails',
] as const;

/**
 * Hand every server error that names one of the form's own fields to
 * `onFieldError`, and return the messages left over.
 *
 * The leftovers are the reason this exists. Fortify answers most failures on a
 * field the form has, but nothing promises it always will, and a message the
 * page cannot place is a message it must not drop: the caller shows what comes
 * back above the submit button instead.
 */
export const splitServerErrors = <TField extends string>(
    serverErrors: Record<string, string>,
    fields: readonly TField[],
    onFieldError: (field: TField, message: string) => void,
): string[] => {
    const known = new Set<string>(fields);
    const unplaced: string[] = [];

    Object.entries(serverErrors).forEach(([key, message]) => {
        if (known.has(key)) {
            onFieldError(key as TField, message);

            return;
        }

        unplaced.push(message);
    });

    return unplaced;
};
