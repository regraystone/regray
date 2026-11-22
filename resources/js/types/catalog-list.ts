import type { DataTableFilters } from '@/components/data-table/data-table';
import type { TableMeta } from '@/lib/data-table';

/** One field of a list's drawer, as its definition (app/Catalog) describes it. */
export type CatalogField = {
    name: string;
    label: string;
    type:
        | 'text'
        | 'slug'
        | 'textarea'
        | 'number'
        | 'select'
        | 'multiselect'
        | 'switch'
        | 'image';
    required?: boolean;
    hint?: string;
    maxLength?: number;
    step?: string;
    /** A select's choices by value; a multiselect's by id. */
    options?: { value?: string; id?: number; label: string }[];
};

/** One list under Products: Stone types, Sizes and the rest. */
export type CatalogListInfo = {
    slug: string;
    title: string;
    /** One item, in a sentence: "stone type". */
    singular: string;
    subtitle: string;
    /** Whether its items have an on/off switch. */
    hasStatus: boolean;
    /** The columns after the item's name. */
    columns: { key: string; header: string }[];
    fields: CatalogField[];
};

/** One item on its list's page. */
export type CatalogItem = {
    id: number;
    name: string;
    thumbnail: string | null;
    cells: Record<string, string>;
    /** Its form's values, by field name. */
    values: Record<string, unknown>;
    /** Null for a list without a switch. */
    isActive: boolean | null;
    actions: string[];
    canToggle: boolean;
};

export type CatalogListPageProps = {
    list: CatalogListInfo;
    items: { data: CatalogItem[]; meta: TableMeta };
    filters: DataTableFilters & { perPage: number; status: string };
    canCreate: boolean;
    limits: { thumbnailMaxKb: number };
};
