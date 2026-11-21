import type { ReactNode } from 'react';
import PageHeader from '@/components/page-header';

/**
 * The older name for PageHeader, kept for the pages that still call it: a
 * title, an optional description, and an optional action.
 */
const PageHeading = ({
    title,
    description,
    action,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
}) =>
    action ? (
        <PageHeader
            variant="action"
            title={title}
            subtitle={description}
            action={action}
        />
    ) : (
        <PageHeader title={title} subtitle={description} />
    );

export default PageHeading;
