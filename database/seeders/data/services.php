<?php

return [
    /*
     * selection_group: services can share a project only when they share a
     * group. Stone relocation is its own job, so it sits alone in its group.
     */
    'services' => [
        [
            'slug' => 'natural-stone-supply',
            'name' => 'Natural Stone Supply',
            'image_path' => 'stones/bac6a5d8d41046a9fe8cb35205cbd34c.png',
            'is_standalone' => false,
            'selection_group' => 'stone-work',
        ],
        [
            'slug' => 'custom-stone-fabrication',
            'name' => 'Custom Stone Fabrication',
            'image_path' => 'stones/4b683a754fea6cc2e9c52669a4c2ca34.png',
            'is_standalone' => false,
            'selection_group' => 'stone-work',
        ],
        [
            'slug' => 'natural-stone-installation',
            'name' => 'Natural Stone Installation',
            'image_path' => 'stones/20845a043f5abd823694192363b27458.png',
            'is_standalone' => false,
            'selection_group' => 'stone-work',
        ],
        [
            'slug' => 'custom-stone-furniture',
            'name' => 'Custom Stone Furniture',
            'image_path' => 'stones/a2a72c93b7369e681b72f0e05380df17.png',
            'is_standalone' => false,
            'selection_group' => 'stone-work',
        ],
        [
            'slug' => 'design-project-consultation',
            'name' => 'Design & Project Consultation',
            'image_path' => 'stones/32429db4b70ad914e80ab79f031e4263.png',
            'is_standalone' => false,
            'selection_group' => 'stone-work',
        ],
        [
            'slug' => 'stone-relocation-dismantling-reinstallation',
            'name' => 'Stone Relocation, Dismantling & Reinstallation',
            'image_path' => 'stones/8f7804fd9673f34737ecd0c1dfca9723.png',
            'is_standalone' => true,
            'selection_group' => 'stone-relocation',
        ],
        [
            'slug' => 'kitchen-cabinetry-solutions',
            'name' => 'Kitchen & Cabinetry Solutions',
            'image_path' => 'stones/ef03fcfd4ff0e6063df004f6b897fe7a.png',
            'is_standalone' => false,
            'selection_group' => 'stone-work',
        ],
    ],
    'features' => [
        'natural-stone-supply' => [
            'Premium natural stone slabs',
            'Marble, Quartzite, Onyx, Granite, Travertine, and other specialty stones',
            'Sourcing of materials based on project requirements',
        ],
        'custom-stone-fabrication' => [
            'Precision cutting and fabrication',
            'Custom dimensions and designs',
            'Edge profiling and finishing',
            'Fabrication for architectural and furniture applications',
        ],
        'natural-stone-installation' => [
            'Accent walls and feature walls',
            'Wall cladding',
            'Countertops and kitchen islands',
            'Flooring and other architectural applications',
            'Nationwide installation services',
        ],
        'custom-stone-furniture' => [
            'Dining tables',
            'Center/coffee tables',
            'Side tables',
            'Console tables',
            'Pedestals and other bespoke stone pieces',
            'Made-to-order designs based on client specifications',
        ],
        'design-project-consultation' => [
            'Material selection',
            'Design consultation',
            'Project assessment',
            'Recommendations based on dimensions, application, and design requirements',
        ],
        'stone-relocation-dismantling-reinstallation' => [
            'Professional dismantling of existing stone',
            'Safe handling and relocation',
            'Reinstallation while maintaining the integrity of the material',
        ],
        'kitchen-cabinetry-solutions' => [
            'Custom kitchen cabinetry',
            'Stainless steel kitchen cabinets',
            'Custom storage solutions',
            'Integration of cabinetry with natural stone surfaces',
        ],
    ],
];
