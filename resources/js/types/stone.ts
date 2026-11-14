export type StoneProfileImage = {
    src: string;
    name: string;
};

/** One size or thickness a stone comes in; `value` is its label ("20 mm"). */
export type StoneMeasurementOption = {
    value: string;
    label: string;
};

export type StoneCardData = {
    id?: string;
    slug: string;
    name: string;
    thumbnail: string;
    brand: { slug: string; name: string };
    type: { slug: string; name: string };
    /**
     * On the Inquire page only: the slabs the stone comes in (a size, null
     * without set sizes, at a thickness), and their sizes and thicknesses
     * each once, which a stone row lists ahead of Other.
     */
    variants?: Array<{ size: string | null; thickness: string }>;
    sizes?: StoneMeasurementOption[];
    thicknesses?: StoneMeasurementOption[];
    /** How big the pieces run without set sizes: "Diameter 15–40 cm". */
    sizeNote?: string | null;
};

export type StoneProfile = {
    slug: string;
    name: string;
    thumbnail: string;
    bestSeller: boolean;
    brand: { slug: string; name: string };
    type: { slug: string; name: string };
    description: string;
    specifications: {
        origin: string;
        /**
         * The slabs it comes in, one per size in the stone's order, each with
         * the thicknesses it is cut in, thinnest first. A null size is pieces
         * without set sizes, which `sizeNote` describes.
         */
        slabs: Array<{ size: string | null; thicknesses: string[] }>;
        sizeNote: string | null;
    };
    finishes: string[];
    applications: string[];
    care: string[];
    gallery: StoneProfileImage[];
};
