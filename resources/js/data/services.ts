export type ServiceOption = {
    id: string;
    name: string;
    /**
     * What the service covers, one line per item. These are the bullet points
     * the picker shows, not a summary, so the wording is the wording.
     */
    features: string[];
    image: string;
    /**
     * True for a service that is a whole project on its own, needing no stone to
     * be chosen or specified first.
     *
     * A survey visit, a dismantling and putting back down, and a final inspection
     * are all work on stone somebody already has — there is nothing to supply, so
     * asking the reader to pick a material for them would be asking for a fact
     * they have not got. Set on the catalogue rather than in a list at the point
     * of use, so that what counts as standalone is one fact about the service
     * rather than a judgement repeated wherever it is read.
     */
    standalone?: boolean;
};

/*
 * The service photos are borrowed from the stone catalogue rather than shot per
 * service: every one of these services is about working stone, so a stone photo
 * is honest imagery for it, and it keeps the set to files the project has
 * already vetted as card-worthy. There is one per stone, so no two services
 * repeat. Swap a path here to use a dedicated service photo.
 */
export const serviceOptions: ServiceOption[] = [
    {
        id: 'natural-stone-supply',
        name: 'Natural Stone Supply',
        features: [
            'Premium natural stone slabs',
            'Marble, Quartzite, Onyx, Granite, Travertine, and other specialty stones',
            'Sourcing of materials based on project requirements',
        ],
        image: '/storage/stones/bac6a5d8d41046a9fe8cb35205cbd34c.png',
    },
    {
        id: 'custom-stone-fabrication',
        name: 'Custom Stone Fabrication',
        features: [
            'Precision cutting and fabrication',
            'Custom dimensions and designs',
            'Edge profiling and finishing',
            'Fabrication for architectural and furniture applications',
        ],
        image: '/storage/stones/4b683a754fea6cc2e9c52669a4c2ca34.png',
    },
    {
        id: 'natural-stone-installation',
        name: 'Natural Stone Installation',
        features: [
            'Accent walls and feature walls',
            'Wall cladding',
            'Countertops and kitchen islands',
            'Flooring and other architectural applications',
            'Nationwide installation services',
        ],
        image: '/storage/stones/20845a043f5abd823694192363b27458.png',
    },
    {
        id: 'custom-stone-furniture',
        name: 'Custom Stone Furniture',
        features: [
            'Dining tables',
            'Center/coffee tables',
            'Side tables',
            'Console tables',
            'Pedestals and other bespoke stone pieces',
            'Made-to-order designs based on client specifications',
        ],
        image: '/storage/stones/a2a72c93b7369e681b72f0e05380df17.png',
    },
    {
        id: 'design-project-consultation',
        name: 'Design & Project Consultation',
        features: [
            'Material selection',
            'Design consultation',
            'Project assessment',
            'Recommendations based on dimensions, application, and design requirements',
        ],
        image: '/storage/stones/32429db4b70ad914e80ab79f031e4263.png',
    },
    {
        id: 'site-ocular-technical-assessment',
        name: 'Site Ocular & Technical Assessment',
        features: [
            'Site visits and project assessment',
            'Measurement and evaluation of site requirements',
            'Technical recommendations for stone applications and installation',
        ],
        image: '/storage/stones/5b7cb3dae550009c37ad0cb518f4d09f.png',
        standalone: true,
    },
    {
        id: 'stone-relocation-dismantling-reinstallation',
        name: 'Stone Relocation, Dismantling & Reinstallation',
        features: [
            'Professional dismantling of existing stone',
            'Safe handling and relocation',
            'Reinstallation while maintaining the integrity of the material',
        ],
        image: '/storage/stones/8f7804fd9673f34737ecd0c1dfca9723.png',
        standalone: true,
    },
    {
        id: 'post-installation-quality-care',
        name: 'Post-Installation Quality Care',
        features: [
            'Final inspection',
            'Surface and finish checking',
            'Alignment assessment',
            'Professional cleaning and polishing before project turnover',
        ],
        image: '/storage/stones/1715fd73a1ddbc8f8ae32158bd35ae4a.png',
        standalone: true,
    },
    {
        id: 'kitchen-cabinetry-solutions',
        name: 'Kitchen & Cabinetry Solutions',
        features: [
            'Custom kitchen cabinetry',
            'Stainless steel kitchen cabinets',
            'Custom storage solutions',
            'Integration of cabinetry with natural stone surfaces',
        ],
        image: '/storage/stones/ef03fcfd4ff0e6063df004f6b897fe7a.png',
    },
];

/**
 * The services that stand as a project of their own.
 *
 * Read off the catalogue's own `standalone` flag rather than written out as ids
 * here, so a service cannot be one thing to the flow and another to whatever
 * else lists them. A set rather than a list because the only question ever asked
 * of it is "is this one of them".
 */
export const STANDALONE_SERVICE_IDS: ReadonlySet<string> = new Set(
    serviceOptions
        .filter((service) => service.standalone === true)
        .map((service) => service.id),
);

/**
 * What a service is called, by its id.
 *
 * Falls back to the id rather than to nothing, because a service dropped from the
 * catalogue still has to be shown as something: the id is the record of what was
 * asked for, and a blank would say the reader had chosen nothing.
 */
export const serviceName = (id: string): string =>
    serviceOptions.find((service) => service.id === id)?.name ?? id;
