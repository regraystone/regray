import { createContext, useContext } from 'react';
import type { InquiryCatalog } from '@/types/inquiry';

const InquiryCatalogContext = createContext<InquiryCatalog | null>(null);

/**
 * Hands the Inquire page's options to every component of the flow.
 *
 * A context rather than props because the services, stones and finishes are read
 * three levels down — the requirements form, the review, the project details —
 * and threading the same six lists through each would say nothing a reader of
 * those components needs.
 */
export const InquiryCatalogProvider = InquiryCatalogContext.Provider;

export function useInquiryCatalog(): InquiryCatalog {
    const catalog = useContext(InquiryCatalogContext);

    if (catalog === null) {
        throw new Error(
            'useInquiryCatalog must be used inside an InquiryCatalogProvider.',
        );
    }

    return catalog;
}
