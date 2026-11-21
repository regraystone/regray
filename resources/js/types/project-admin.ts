import type { DataTableFilters } from '@/components/data-table/data-table';
import type { TableMeta } from '@/lib/data-table';

/** An uploaded image's own name and size, to show; null for a committed one. */
export type ProjectImageFile = {
    originalName: string;
    sizeBytes: number | null;
};

/** One photo of a project's gallery, in its order. */
export type ProjectPhoto = {
    id: number;
    url: string;
    /** Its file's own name without the extension, its alt text; null for a committed one. */
    name: string | null;
    file: ProjectImageFile | null;
};

/** A brand, stone or category as the form offers it and a project lists it. */
export type ProjectOption = { id: number; name: string };

/** One project as a card on the admin's Projects page, as `AdminProjectResource` sends it. */
export type ProjectRow = {
    id: number;
    slug: string;
    name: string;
    location: string;
    completedYear: number;
    overview: string;
    thumbnail: string;
    thumbnailFile: ProjectImageFile | null;
    brand: ProjectOption | null;
    stones: ProjectOption[];
    categories: ProjectOption[];
    images: ProjectPhoto[];
    /** Published on Regray; a draft has no page. */
    isPublished: boolean;
    /** When it was first published, kept when it goes back to a draft. */
    publishedAt: string | null;
    createdAt: string;
    updatedAt: string;
    /** The project's public page, or null while it is a draft. */
    publicUrl: string | null;
    /** The actions this reader has on this project, decided by the server. */
    actions: string[];
};

/** The limits the form checks, from config/projects.php. */
export type ProjectLimits = {
    thumbnailMaxKb: number;
    photoMaxKb: number;
    galleryMax: number;
    overviewMax: number;
};

export type ProjectsPageProps = {
    projects: { data: ProjectRow[]; meta: TableMeta };
    filters: DataTableFilters;
    canCreate: boolean;
    limits: ProjectLimits;
    /** The names Regray keeps for its own pages; no project may take one. */
    reservedSlugs: string[];
    /** A project's public page, with `__slug__` where its slug goes. */
    publicProjectUrl: string;
    brandOptions: ProjectOption[];
    /** The active stones, each with its brand's name to tell alike names apart. */
    stoneOptions: (ProjectOption & { brand: string })[];
    categoryOptions: ProjectOption[];
};
