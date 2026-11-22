import PublicLayout from '@/layouts/public-layout';

/**
 * The catalogue's brand, showcase, stone and furniture pages, for a guest:
 * the public site's frame (the mega-menu header and the black footer), with
 * the page in a padded column.
 */
export default function GuestLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return (
        <PublicLayout>
            <div className="px-4 py-6 sm:px-8 lg:py-8">{children}</div>
        </PublicLayout>
    );
}
