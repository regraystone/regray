import AuthBrandLayout from '@/layouts/auth/auth-brand-layout';

/**
 * The layout app.tsx gives an `auth/` page that sets none of its own (the
 * two-factor challenge): the same one every other auth page uses.
 */
export default function AuthLayout({
    eyebrow = '',
    title = '',
    description = '',
    children,
}: {
    eyebrow?: string;
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <AuthBrandLayout
            eyebrow={eyebrow}
            title={title}
            description={description}
        >
            {children}
        </AuthBrandLayout>
    );
}
