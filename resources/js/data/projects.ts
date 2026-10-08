export type ProjectCategory =
    | 'wall-accents'
    | 'commercial-spaces'
    | 'kitchen-islands'
    | 'dining-tables'
    | 'center-side-tables'
    | 'countertops-backsplash'
    | 'lavatories'
    | 'exterior-walls'
    | 'fixtures';

export type ProjectImage = { id: string; projectId: string; src: string };

export type Project = {
    id: string;
    name: string;
    completedYear: number;
    location: string; // free-form address line shown on the project profile page
    overview: string; // the short "about" text shown on the project profile page
    thumbnail: string;
    gallery: ProjectImage[];
    stones: string[];
    categories: ProjectCategory[];
};

export const PROJECT_CATEGORIES: Array<{
    value: ProjectCategory;
    label: string;
}> = [
    { value: 'wall-accents', label: 'Wall Accents' },
    { value: 'commercial-spaces', label: 'Commercial Spaces' },
    { value: 'kitchen-islands', label: 'Kitchen Islands' },
    { value: 'dining-tables', label: 'Dining Tables' },
    { value: 'center-side-tables', label: 'Center & Side Tables' },
    { value: 'countertops-backsplash', label: 'Countertops & Backsplash' },
    { value: 'lavatories', label: 'Lavatories' },
    { value: 'exterior-walls', label: 'Exterior Walls' },
    { value: 'fixtures', label: 'Fixtures' },
];

export const projects: Project[] = [
    {
        id: 'recidencia-del-hamor',
        name: 'Recidencia Del Hamor',
        completedYear: 2025,
        location: 'Casiguran, Sorsogon',
        overview:
            'Recidencia Del Hamor is a commercial spaces project in Casiguran, Sorsogon, completed in 2025. The project features marble and quartzite, with wall accents that bring out the natural veining and character of each stone. Explore the stones used in this project below.',
        thumbnail: '/storage/projects/9c2e5a1f8b4d7c3e6a9f2c5e8a1d4b7f.jpg',
        gallery: [
            {
                id: 'recidencia-img-2',
                projectId: 'recidencia-del-hamor',
                src: '/storage/projects/2b9e5c3f1a8d6e4c9b2f5a8c3d7e1a6b.jpg',
            },
            {
                id: 'recidencia-img-3',
                projectId: 'recidencia-del-hamor',
                src: '/storage/projects/4d1f9a6c3e8b2f5a7c0e3b8d1f4a7c9e.png',
            },
            {
                id: 'recidencia-img-4',
                projectId: 'recidencia-del-hamor',
                src: '/storage/projects/7a4c8b2f9d1e6c3a5f8b0e2d4c7a9e1f.jpg',
            },
        ],
        stones: ['marble', 'quartzite'],
        categories: ['commercial-spaces', 'wall-accents'],
    },
    {
        id: 'belo-medical-group',
        name: 'Belo Medical Group',
        completedYear: 2026,
        location: 'Proscenium Rockwell, Makati City',
        overview:
            "Belo Medical Group is one of the Philippines' most established names in medical aesthetics, known for its expertise in advanced cosmetic dermatology and plastic surgery. With more than three decades of practice and a growing network of clinics nationwide, the brand combines clinical precision, innovation, and refined patient care to deliver a highly sophisticated approach to modern aesthetics. Completed in 2026 at Proscenium Rockwell, Makati City, this commercial space features marble wall accents.",
        thumbnail: '/storage/projects/3e7b1c4f9a6d2e8c5f1a3b7e9c2d5f8a.png',
        gallery: [
            {
                id: 'belo-img-2',
                projectId: 'belo-medical-group',
                src: '/storage/projects/5a2f8c1e4b7d9f3c6e8a1d4f7b0e3c6a.png',
            },
            {
                id: 'belo-img-3',
                projectId: 'belo-medical-group',
                src: '/storage/projects/1c8e3f5a9b2d7c4e6f1a3d8c0b5e9f2a.png',
            },
            {
                id: 'belo-img-4',
                projectId: 'belo-medical-group',
                src: '/storage/projects/8f4a1c7e3d6b9f2e5a8c1d4e7a0f3b6c.png',
            },
        ],
        stones: ['marble'],
        categories: ['commercial-spaces', 'wall-accents'],
    },
    {
        id: 'ayala-land',
        name: 'Ayala Land',
        completedYear: 2025,
        location: 'Ayala Land Tower 1, Makati City',
        overview:
            'Ayala Land is a commercial spaces project at Ayala Land Tower 1 in Makati City, completed in 2025. The project features quartzite, a durable natural stone well suited to commercial settings. Explore the stone used in this project below.',
        thumbnail: '/storage/projects/6c9a2e5f1b8d3c7a4e0f1b8c5d9e2a7f.jpg',
        gallery: [
            {
                id: 'ayala-img-2',
                projectId: 'ayala-land',
                src: '/storage/projects/2f5c8e1a9b3d6f4c7e0a1d8b5c2e9a3f.png',
            },
            {
                id: 'ayala-img-3',
                projectId: 'ayala-land',
                src: '/storage/projects/7b3f1e6c9a5d2f8b4e1a6c9d0f3a8e5c.png',
            },
            {
                id: 'ayala-img-4',
                projectId: 'ayala-land',
                src: '/storage/projects/4e8f2a6c1d9b5f3a7c0e1b8d4f6a9c2e.png',
            },
        ],
        stones: ['quartzite'],
        categories: ['commercial-spaces'],
    },
    {
        id: 'ginza-one',
        name: 'Ginza One',
        completedYear: 2024,
        location: 'Chino Roces Ext., Makati City',
        overview:
            'Ginza One is a commercial spaces project on Chino Roces Ext., Makati City, completed in 2024. The project spans wall accents as well as countertops & backsplash, and features patagonia quartzite and onyx. Explore the stones used in this project below.',
        thumbnail: '/storage/projects/1a5d7e3c6f8b2a4f9c1e5d8a3b6f0e9c.jpg',
        gallery: [
            {
                id: 'ginza-img-2',
                projectId: 'ginza-one',
                src: '/storage/projects/9f2a6c1e4d8b5f3a7c0e1d9b2f6e8c5a.jpg',
            },
            {
                id: 'ginza-img-3',
                projectId: 'ginza-one',
                src: '/storage/projects/3c8e1f5a9b4d7c2e6f0a3b8d1c5e9f6a.jpg',
            },
            {
                id: 'ginza-img-4',
                projectId: 'ginza-one',
                src: '/storage/projects/5e1a9c6f3d8b2f7c4a0e1b6c9d2f5a8e.jpg',
            },
        ],
        stones: ['patagonia-quartzite', 'onyx'],
        categories: [
            'commercial-spaces',
            'wall-accents',
            'countertops-backsplash',
        ],
    },
    {
        id: 'restaurant-idalia',
        name: 'Restaurant Idalia',
        completedYear: 2025,
        location: 'Valero St., Makati City',
        overview:
            'Restaurant Idalia is a commercial spaces project on Valero St., Makati City, completed in 2025. The project features onyx wall accents, a natural stone prized for its dramatic veining and translucent quality. Explore the stone used in this project below.',
        thumbnail: '/storage/projects/2d9e4c1f8a5b6f3e7c0a1d9b4e2f6c8a.jpg',
        gallery: [
            {
                id: 'idalia-img-2',
                projectId: 'restaurant-idalia',
                src: '/storage/projects/7a2f5c9e1b6d3f8c4e0a1d7b5f2c9e6a.jpg',
            },
            {
                id: 'idalia-img-3',
                projectId: 'restaurant-idalia',
                src: '/storage/projects/4c1e9f6a3d8b2f7e5c0a1b6d9e2a4f7c.jpg',
            },
            {
                id: 'idalia-img-4',
                projectId: 'restaurant-idalia',
                src: '/storage/projects/8b3a1f5e9c6d2a7f4e0c1b8d5f3e9a6c.jpg',
            },
        ],
        stones: ['onyx'],
        categories: ['commercial-spaces', 'wall-accents'],
    },
    {
        id: 'lazy-suzy-restaurant',
        name: 'Lazy Suzy Restaurant',
        completedYear: 2025,
        location: 'Podium Mall, Mandaluyong City',
        overview:
            'Lazy Suzy Restaurant is a commercial spaces project at Podium Mall, Mandaluyong City, completed in 2025. The project features onyx wall accents, a natural stone prized for its dramatic veining and translucent quality. Explore the stone used in this project below.',
        thumbnail: '/storage/projects/a3f1c8d5e9b4726f0a2e5c8b1d4f7a9e.jpg',
        gallery: [
            {
                id: 'lazy-suzy-img-2',
                projectId: 'lazy-suzy-restaurant',
                src: '/storage/projects/2b7e3a9f1c5d8a6b4e0f2c7d9a1b5e8f.jpg',
            },
            {
                id: 'lazy-suzy-img-3',
                projectId: 'lazy-suzy-restaurant',
                src: '/storage/projects/6d9c2f1e8a5b3f7c4d0a1b6e9f2c5a8d.jpg',
            },
            {
                id: 'lazy-suzy-img-4',
                projectId: 'lazy-suzy-restaurant',
                src: '/storage/projects/e5b1f7d2a9c4e8f3b6d1a5c8f2e9b4a7.jpg',
            },
        ],
        stones: ['onyx'],
        categories: ['commercial-spaces', 'wall-accents'],
    },
];
