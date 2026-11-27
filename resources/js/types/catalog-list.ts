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
        | 'tags'
        | 'switch'
        | 'image';
    required?: boolean;
    hint?: string;
    /** A text's length; each tag's, for tags. */
    maxLength?: number;
    /** How many tags it takes. */
    maxItems?: number;
    step?: string;
    /** A select's choices by value; a multiselect's by id. */
    options?: { value?: string; id?: number; label: string }[];
    /** Shown only while another field has this value, or one of these. */
    showWhen?: { field: string; equals?: string; in?: string[] };
    /**
     * A multiselect whose items are added on another list's page: a link
     * there, which brings the admin back with the new one picked.
     */
    addLink?: { list: string; label: string };
};

/** What a list's page calls its switch. */
export type CatalogStatusWords = {
    header: string;
    on: string;
    off: string;
    turnOn: string;
    turnOff: string;
};

/** One list under Products (Stone types, Sizes and the rest) or Services (Service types, Questions). */
export type CatalogListInfo = {
    slug: string;
    section: 'products' | 'services';
    title: string;
    /** One item, in a sentence: "stone type". */
    singular: string;
    subtitle: string;
    /** Whether its items have an on/off switch. */
    hasStatus: boolean;
    statusWords: CatalogStatusWords;
    /** What the Archive question says happens, and when it is refused. */
    archiveDescription: string;
    /** What the empty list suggests. */
    emptyDescription: string;
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
    /** The fields its edit doesn't offer, such as a question's kind of answer. */
    fixed: string[];
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
