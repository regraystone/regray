<?php

/**
 * stone_slug is a legacy category reference; InquirySeeder resolves its first individual stone.
 *
 * Every project has a deadline date and time (Manila): InquirySeeder books each
 * one as the project's schedule, the way sending an inquiry does.
 *
 * Each project also carries what happened to it, in UTC:
 * - `status` and `status_changed_at`: where the project is now, and since when;
 * - `quotations`: one row per revision, the last one live;
 * - `payment`: what was paid against the live quotation, as transactions. The
 *   amount due is the quotation's total and the amount paid is the sum of the
 *   `payment` transactions, so neither is written here;
 * - `job_orders`: the work done against the live quotation;
 * - `cards`: every snapshot (project, quotation, payment status) a project
 *   card announced, in the order it happened.
 *
 * Staff and clients are named by the short keys InquirySeeder maps to users.
 * The statuses follow the rules in docs/database/regray-erd.md section 4, and
 * tests/Feature/Database/InquirySeedConsistencyTest.php checks that they do.
 */
return [
    'inquiries' => [
        [
            'id' => 'inq-1048',
            'reference' => 'INQ-1048',
            'status' => 'ongoing',
            'created_at' => '2026-09-27T03:40:00Z',
            'updated_at' => '2026-10-24T08:00:00Z',
            'client_id' => 'cl-rafael',
            'handlers' => ['daniel', 'maria'],
            'projects' => [
                [
                    'id' => 'prj-1048-1',
                    'name' => 'Northgate Cafe — Counters and Flooring',
                    'categories' => ['countertops-backsplash'],
                    'services' => ['Custom Stone Fabrication', 'Natural Stone Installation'],
                    'stones' => [
                        ['stone_slug' => 'quartzite', 'quantity' => 7, 'edge' => 'Half Bevel', 'finish' => 'Leather'],
                        ['stone_slug' => 'quartzite', 'quantity' => 3, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                        ['stone_slug' => 'granite', 'quantity' => 4, 'edge' => 'Half Bevel', 'finish' => 'Leather'],
                        ['stone_slug' => 'crazy-cut', 'quantity' => 2, 'edge' => 'Half Bevel', 'finish' => 'Leather'],
                    ],
                    'location' => 'Makati',
                    'landmark' => 'Second floor, beside the mezzanine staircase',
                    'area_sqm' => 18.5,
                    'deadline_date' => '2026-11-14',
                    'deadline_time' => '10:00',
                    'notes' => "The bar top has to clear the service window by 60 mm.\nPlease keep the seam out of the sight line from the entrance.",
                    'attachments' => [
                        ['name' => 'northgate-bar-layout.pdf', 'size_bytes' => 1284096, 'mime_type' => 'application/pdf'],
                        ['name' => 'exposed-concrete-photo.png', 'size_bytes' => 486400, 'mime_type' => 'image/png'],
                    ],
                    // Quoted and waiting on the client: still pending.
                    'status' => 'pending',
                    'status_changed_at' => '2026-09-27T03:40:00Z',
                    'quotations' => [
                        ['revision_no' => 1, 'status' => 'pending', 'total_amount' => '486500.00', 'issued_at' => '2026-10-02T03:00:00Z', 'responded_at' => null, 'created_by' => 'daniel'],
                    ],
                    'payment' => null,
                    'job_orders' => [],
                    'cards' => [
                        ['project' => 'pending', 'quotation' => 'pending', 'payment' => 'awaiting', 'at' => '2026-10-02T03:00:00Z'],
                    ],
                ],
                [
                    'id' => 'prj-1048-2',
                    'name' => 'Northgate Cafe — Dining Floor',
                    'categories' => ['dining-tables'],
                    'services' => ['Custom Stone Fabrication', 'Natural Stone Installation'],
                    'stones' => [
                        ['stone_slug' => 'quartzite', 'quantity' => 9, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                    ],
                    'location' => 'Makati',
                    'landmark' => 'Ground floor, under the window wall',
                    'area_sqm' => 26,
                    'deadline_date' => '2026-10-24',
                    'deadline_time' => '14:00',
                    'notes' => "Wanted square to the window wall so the joints run with it.\nPlease do the floor before the counters are set.",
                    'attachments' => [],
                    // Quoted, accepted, paid in two parts and installed: closed.
                    'status' => 'closed',
                    'status_changed_at' => '2026-10-24T08:00:00Z',
                    'quotations' => [
                        ['revision_no' => 1, 'status' => 'closed', 'total_amount' => '312000.00', 'issued_at' => '2026-09-29T02:00:00Z', 'responded_at' => '2026-09-30T05:30:00Z', 'updated_at' => '2026-10-24T08:00:00Z', 'created_by' => 'maria'],
                    ],
                    'payment' => [
                        'status' => 'fully_paid',
                        'created_at' => '2026-09-30T05:30:00Z',
                        'status_changed_at' => '2026-10-20T06:00:00Z',
                        'transactions' => [
                            ['kind' => 'payment', 'amount' => '156000.00', 'reference' => 'BDO-1001-4471', 'received_at' => '2026-10-01T02:00:00Z', 'recorded_by' => 'maria'],
                            ['kind' => 'payment', 'amount' => '156000.00', 'reference' => 'BDO-1020-5183', 'received_at' => '2026-10-20T06:00:00Z', 'recorded_by' => 'maria'],
                        ],
                    ],
                    'job_orders' => [
                        ['reference' => 'JO-2039', 'status' => 'completed', 'created_at' => '2026-09-30T08:00:00Z', 'status_changed_at' => '2026-10-24T08:00:00Z', 'notes' => 'Cut as one run so the veining follows across the floor, then installed under the window wall.'],
                    ],
                    'cards' => [
                        ['project' => 'pending', 'quotation' => 'pending', 'payment' => 'awaiting', 'at' => '2026-09-29T02:00:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'closeable', 'payment' => 'awaiting', 'at' => '2026-09-30T05:30:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'closeable', 'payment' => 'partial', 'at' => '2026-10-01T02:00:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'closeable', 'payment' => 'fully_paid', 'at' => '2026-10-20T06:00:00Z'],
                        ['project' => 'closed', 'quotation' => 'closed', 'payment' => 'fully_paid', 'at' => '2026-10-24T08:00:00Z'],
                    ],
                ],
            ],
            'status_history' => [
                ['from_status' => null, 'to_status' => 'draft', 'at' => '2026-09-27T03:40:00Z', 'changed_by' => 'cl-rafael'],
                ['from_status' => 'draft', 'to_status' => 'pending', 'at' => '2026-09-28T06:15:00Z', 'changed_by' => 'maria'],
                ['from_status' => 'pending', 'to_status' => 'ongoing', 'at' => '2026-09-30T05:30:00Z', 'changed_by' => 'maria'],
            ],
        ],
        [
            'id' => 'inq-1044',
            'reference' => 'INQ-1044',
            'status' => 'ongoing',
            'created_at' => '2026-09-15T04:50:00Z',
            'updated_at' => '2026-09-24T07:15:00Z',
            'client_id' => 'cl-nadine',
            'handlers' => ['maria'],
            'projects' => [
                [
                    'id' => 'prj-1044-1',
                    'name' => 'Garden Court — Vanity Tops',
                    'categories' => ['lavatories'],
                    'services' => ['Design & Project Consultation'],
                    'stones' => [
                        ['stone_slug' => 'travertine', 'quantity' => 8, 'edge' => 'Reverse Chamfer', 'finish' => 'Honed'],
                        ['stone_slug' => 'marble', 'quantity' => 5, 'edge' => 'Reverse Chamfer', 'finish' => 'Honed'],
                    ],
                    'location' => 'San Juan',
                    'landmark' => 'Ground floor, beside the garden court entrance',
                    'area_sqm' => 9.75,
                    'deadline_date' => '2026-10-30',
                    'deadline_time' => '09:00',
                    'notes' => null,
                    'attachments' => [],
                    // The client asked for changes, so revision 2 replaced revision 1.
                    'status' => 'ongoing',
                    'status_changed_at' => '2026-09-24T07:15:00Z',
                    'quotations' => [
                        ['revision_no' => 1, 'status' => 'revision', 'total_amount' => '148200.00', 'issued_at' => '2026-09-19T09:30:00Z', 'responded_at' => '2026-09-22T03:00:00Z', 'updated_at' => '2026-09-24T07:15:00Z', 'created_by' => 'maria'],
                        ['revision_no' => 2, 'status' => 'pending', 'total_amount' => '151750.00', 'issued_at' => '2026-09-24T07:15:00Z', 'responded_at' => null, 'created_by' => 'maria'],
                    ],
                    'payment' => null,
                    'job_orders' => [],
                    'cards' => [
                        ['project' => 'pending', 'quotation' => 'pending', 'payment' => 'awaiting', 'at' => '2026-09-19T09:30:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'pending', 'payment' => 'awaiting', 'at' => '2026-09-24T07:15:00Z'],
                    ],
                ],
            ],
            'status_history' => [
                ['from_status' => null, 'to_status' => 'draft', 'at' => '2026-09-15T04:50:00Z', 'changed_by' => 'cl-nadine'],
                ['from_status' => 'draft', 'to_status' => 'pending', 'at' => '2026-09-16T01:05:00Z', 'changed_by' => 'maria'],
                ['from_status' => 'pending', 'to_status' => 'ongoing', 'at' => '2026-09-24T07:15:00Z', 'changed_by' => 'maria'],
            ],
        ],
        [
            'id' => 'inq-1043',
            'reference' => 'INQ-1043',
            'status' => 'ongoing',
            'created_at' => '2026-09-11T08:25:00Z',
            'updated_at' => '2026-09-24T05:00:00Z',
            'client_id' => 'cl-ivan',
            'handlers' => ['priya'],
            'projects' => [
                [
                    'id' => 'prj-1043-1',
                    'name' => 'Hillcrest Showroom — Display Units',
                    'categories' => ['center-side-tables'],
                    'services' => ['Custom Stone Furniture', 'Custom Stone Fabrication', 'Natural Stone Installation'],
                    'stones' => [
                        ['stone_slug' => 'granite', 'quantity' => 5, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                        ['stone_slug' => 'monumental', 'quantity' => 2, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                    ],
                    'location' => 'San Juan',
                    'landmark' => null,
                    'area_sqm' => 12,
                    'deadline_date' => '2026-09-26',
                    'deadline_time' => '13:00',
                    'notes' => null,
                    'attachments' => [
                        ['name' => 'hillcrest-display-units.png', 'size_bytes' => 655360, 'mime_type' => 'image/png'],
                    ],
                    // Accepted with a deposit paid: one job order is ready to install, the other is on hold.
                    'status' => 'ongoing',
                    'status_changed_at' => '2026-09-13T04:20:00Z',
                    'quotations' => [
                        ['revision_no' => 1, 'status' => 'closeable', 'total_amount' => '268400.00', 'issued_at' => '2026-09-12T10:45:00Z', 'responded_at' => '2026-09-13T04:20:00Z', 'created_by' => 'priya'],
                    ],
                    'payment' => [
                        'status' => 'partial',
                        'created_at' => '2026-09-13T04:20:00Z',
                        'status_changed_at' => '2026-09-14T07:40:00Z',
                        'transactions' => [
                            ['kind' => 'payment', 'amount' => '134200.00', 'reference' => 'GCASH-0914-2208', 'received_at' => '2026-09-14T07:40:00Z', 'recorded_by' => 'priya'],
                        ],
                    ],
                    'job_orders' => [
                        ['reference' => 'JO-2036', 'status' => 'for_install', 'created_at' => '2026-09-14T09:00:00Z', 'status_changed_at' => '2026-09-24T05:00:00Z', 'notes' => 'Granite plinths fabricated; waiting for the showroom to clear the floor.'],
                        ['reference' => 'JO-2037', 'status' => 'on_hold', 'created_at' => '2026-09-14T09:05:00Z', 'status_changed_at' => '2026-09-18T03:30:00Z', 'notes' => 'Monumental pedestals on hold for a replacement block: the first one cracked at the saw.'],
                    ],
                    'cards' => [
                        ['project' => 'pending', 'quotation' => 'pending', 'payment' => 'awaiting', 'at' => '2026-09-12T10:45:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'closeable', 'payment' => 'awaiting', 'at' => '2026-09-13T04:20:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'closeable', 'payment' => 'partial', 'at' => '2026-09-14T07:40:00Z'],
                    ],
                ],
            ],
            'status_history' => [
                ['from_status' => null, 'to_status' => 'draft', 'at' => '2026-09-11T08:25:00Z', 'changed_by' => 'cl-ivan'],
                ['from_status' => 'draft', 'to_status' => 'pending', 'at' => '2026-09-12T02:10:00Z', 'changed_by' => 'priya'],
                ['from_status' => 'pending', 'to_status' => 'ongoing', 'at' => '2026-09-13T04:20:00Z', 'changed_by' => 'priya'],
            ],
        ],
        [
            'id' => 'inq-1041',
            'reference' => 'INQ-1041',
            'status' => 'closed',
            'created_at' => '2026-08-28T05:45:00Z',
            'updated_at' => '2026-09-08T04:15:00Z',
            'client_id' => 'cl-haruki',
            'handlers' => ['daniel', 'sofia'],
            'projects' => [
                [
                    'id' => 'prj-1041-1',
                    'name' => 'The Grove — Outdoor Kitchen and Deck',
                    'categories' => ['kitchen-islands'],
                    'services' => ['Natural Stone Supply', 'Custom Stone Fabrication', 'Natural Stone Installation'],
                    'stones' => [
                        ['stone_slug' => 'quartzite', 'quantity' => 12, 'edge' => 'Full Bevel', 'finish' => 'Leather'],
                        ['stone_slug' => 'granite', 'quantity' => 6, 'edge' => 'Full Bevel', 'finish' => 'Leather'],
                        ['stone_slug' => 'marble', 'quantity' => 4, 'edge' => 'Full Bevel', 'finish' => 'Leather'],
                    ],
                    'location' => 'Muntinlupa',
                    'landmark' => 'Under the tree, at the far end of the deck',
                    'area_sqm' => 11.5,
                    'deadline_date' => '2026-09-05',
                    'deadline_time' => '10:00',
                    'notes' => "The counter runs along the pool deck, so it is in full sun for the afternoon and under the tree for the morning.\n\nThe grill sits at the left-hand end, so we would cut a recess for it rather than run the top past it.\n\nIf that is already decided, please say so here so the workshop is not asked to move the cut once it has started.",
                    'attachments' => [
                        ['name' => 'grove-outdoor-kitchen-plan.pdf', 'size_bytes' => 2411724, 'mime_type' => 'application/pdf'],
                        ['name' => 'grove-bar-reference.jpg', 'size_bytes' => 921600, 'mime_type' => 'image/jpeg'],
                        ['name' => 'quartzite-slabsite-mockup.png', 'size_bytes' => 1572864, 'mime_type' => 'image/png'],
                    ],
                    'status' => 'closed',
                    'status_changed_at' => '2026-09-08T04:15:00Z',
                    'quotations' => [
                        ['revision_no' => 1, 'status' => 'closed', 'total_amount' => '412800.00', 'issued_at' => '2026-08-31T08:55:00Z', 'responded_at' => '2026-09-01T02:00:00Z', 'updated_at' => '2026-09-08T04:15:00Z', 'created_by' => 'daniel'],
                    ],
                    'payment' => [
                        'status' => 'fully_paid',
                        'created_at' => '2026-09-01T02:00:00Z',
                        'status_changed_at' => '2026-09-05T03:00:00Z',
                        'transactions' => [
                            ['kind' => 'payment', 'amount' => '206400.00', 'reference' => 'BPI-0901-7720', 'received_at' => '2026-09-01T06:30:00Z', 'recorded_by' => 'daniel'],
                            ['kind' => 'payment', 'amount' => '206400.00', 'reference' => 'BPI-0905-7794', 'received_at' => '2026-09-05T03:00:00Z', 'recorded_by' => 'sofia'],
                        ],
                    ],
                    'job_orders' => [
                        ['reference' => 'JO-2034', 'status' => 'completed', 'created_at' => '2026-09-01T07:00:00Z', 'status_changed_at' => '2026-09-07T08:00:00Z', 'notes' => 'Counter top installed with the grill recess cut at the left-hand end.'],
                        ['reference' => 'JO-2035', 'status' => 'completed', 'created_at' => '2026-09-01T07:10:00Z', 'status_changed_at' => '2026-09-06T04:00:00Z', 'notes' => 'Offcut stepping stones, picked up by the client.'],
                    ],
                    'cards' => [
                        ['project' => 'pending', 'quotation' => 'pending', 'payment' => 'awaiting', 'at' => '2026-08-31T08:55:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'closeable', 'payment' => 'awaiting', 'at' => '2026-09-01T02:00:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'closeable', 'payment' => 'partial', 'at' => '2026-09-01T06:30:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'closeable', 'payment' => 'fully_paid', 'at' => '2026-09-05T03:00:00Z'],
                        ['project' => 'closed', 'quotation' => 'closed', 'payment' => 'fully_paid', 'at' => '2026-09-08T04:15:00Z'],
                    ],
                ],
                [
                    'id' => 'prj-1041-2',
                    'name' => 'The Grove - Pool Deck',
                    'categories' => ['exterior-walls'],
                    'services' => ['Natural Stone Supply', 'Natural Stone Installation'],
                    'stones' => [
                        ['stone_slug' => 'travertine', 'quantity' => 20, 'edge' => 'Straight Edge', 'finish' => 'Honed'],
                    ],
                    'location' => 'Muntinlupa',
                    'landmark' => 'Pool deck, beside the barbecue counter',
                    'area_sqm' => 24.25,
                    'deadline_date' => '2026-08-28',
                    'deadline_time' => '15:00',
                    'notes' => "The drainage channel the drawings show does not line up with the fall of the deck, and we would move it to the far end.\n\nThe deck goes down before the counter is set, so the earlier date on this one is deliberate.",
                    'attachments' => [
                        ['name' => 'grove-drainage-detail.pdf', 'size_bytes' => 384512, 'mime_type' => 'application/pdf'],
                    ],
                    'status' => 'closed',
                    'status_changed_at' => '2026-09-04T09:00:00Z',
                    'quotations' => [
                        ['revision_no' => 1, 'status' => 'closed', 'total_amount' => '289000.00', 'issued_at' => '2026-08-30T06:00:00Z', 'responded_at' => '2026-08-31T01:30:00Z', 'updated_at' => '2026-09-04T09:00:00Z', 'created_by' => 'daniel'],
                    ],
                    'payment' => [
                        'status' => 'fully_paid',
                        'created_at' => '2026-08-31T01:30:00Z',
                        'status_changed_at' => '2026-09-03T02:00:00Z',
                        'transactions' => [
                            ['kind' => 'payment', 'amount' => '144500.00', 'reference' => 'BPI-0831-7651', 'received_at' => '2026-08-31T05:00:00Z', 'recorded_by' => 'daniel'],
                            ['kind' => 'payment', 'amount' => '144500.00', 'reference' => 'BPI-0903-7702', 'received_at' => '2026-09-03T02:00:00Z', 'recorded_by' => 'daniel'],
                        ],
                    ],
                    'job_orders' => [
                        ['reference' => 'JO-2033', 'status' => 'completed', 'created_at' => '2026-08-31T03:00:00Z', 'status_changed_at' => '2026-09-04T09:00:00Z', 'notes' => 'Travertine pavers, delivered to the pool deck in two drops.'],
                    ],
                    'cards' => [
                        ['project' => 'pending', 'quotation' => 'pending', 'payment' => 'awaiting', 'at' => '2026-08-30T06:00:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'closeable', 'payment' => 'awaiting', 'at' => '2026-08-31T01:30:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'closeable', 'payment' => 'partial', 'at' => '2026-08-31T05:00:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'closeable', 'payment' => 'fully_paid', 'at' => '2026-09-03T02:00:00Z'],
                        ['project' => 'closed', 'quotation' => 'closed', 'payment' => 'fully_paid', 'at' => '2026-09-04T09:00:00Z'],
                    ],
                ],
            ],
            'status_history' => [
                ['from_status' => null, 'to_status' => 'draft', 'at' => '2026-08-28T05:45:00Z', 'changed_by' => 'cl-haruki'],
                ['from_status' => 'draft', 'to_status' => 'pending', 'at' => '2026-08-29T03:20:00Z', 'changed_by' => 'daniel'],
                ['from_status' => 'pending', 'to_status' => 'ongoing', 'at' => '2026-08-31T01:30:00Z', 'changed_by' => 'daniel'],
                ['from_status' => 'ongoing', 'to_status' => 'closed', 'at' => '2026-09-08T04:15:00Z', 'changed_by' => 'sofia'],
            ],
        ],
        [
            'id' => 'inq-1039',
            'reference' => 'INQ-1039',
            'status' => 'lost',
            'created_at' => '2026-08-12T06:55:00Z',
            'updated_at' => '2026-08-14T01:10:00Z',
            'client_id' => 'cl-grace',
            'handlers' => ['sofia'],
            'projects' => [
                [
                    'id' => 'prj-1039-1',
                    'name' => 'Maple Lane - Laundry',
                    'categories' => ['wall-accents'],
                    'services' => ['Stone Relocation, Dismantling & Reinstallation'],
                    'stones' => [
                        ['stone_slug' => 'onyx', 'quantity' => 3, 'edge' => 'Half Bullnose', 'finish' => 'Honed'],
                        ['stone_slug' => 'cobblestone', 'quantity' => 10, 'edge' => 'Half Bullnose', 'finish' => 'Honed'],
                    ],
                    'location' => 'Quezon City',
                    'landmark' => null,
                    'area_sqm' => null,
                    'deadline_date' => '2026-08-20',
                    'deadline_time' => '08:00',
                    'notes' => null,
                    'attachments' => [],
                    // The client turned the quotation down.
                    'status' => 'cancelled',
                    'status_changed_at' => '2026-08-14T01:10:00Z',
                    'quotations' => [
                        ['revision_no' => 1, 'status' => 'rejected', 'total_amount' => '58600.00', 'issued_at' => '2026-08-13T02:00:00Z', 'responded_at' => '2026-08-14T01:10:00Z', 'created_by' => 'sofia'],
                    ],
                    'payment' => null,
                    'job_orders' => [],
                    'cards' => [
                        ['project' => 'pending', 'quotation' => 'pending', 'payment' => 'awaiting', 'at' => '2026-08-13T02:00:00Z'],
                        ['project' => 'cancelled', 'quotation' => 'rejected', 'payment' => 'awaiting', 'at' => '2026-08-14T01:10:00Z'],
                    ],
                ],
            ],
            'status_history' => [
                ['from_status' => null, 'to_status' => 'draft', 'at' => '2026-08-12T06:55:00Z', 'changed_by' => 'cl-grace'],
                ['from_status' => 'draft', 'to_status' => 'pending', 'at' => '2026-08-12T09:30:00Z', 'changed_by' => 'sofia'],
                ['from_status' => 'pending', 'to_status' => 'lost', 'at' => '2026-08-14T01:10:00Z', 'changed_by' => 'sofia'],
            ],
        ],
        [
            'id' => 'inq-1046',
            'reference' => 'INQ-1046',
            'status' => 'lost',
            'created_at' => '2026-09-20T02:30:00Z',
            'updated_at' => '2026-10-06T02:20:00Z',
            'client_id' => 'cl-elena',
            'handlers' => ['daniel'],
            'projects' => [
                [
                    'id' => 'prj-1046-1',
                    'name' => 'Brightleaf Studio — Reception Desk',
                    'categories' => ['countertops-backsplash'],
                    'services' => ['Custom Stone Fabrication', 'Natural Stone Installation'],
                    'stones' => [
                        ['stone_slug' => 'marble', 'quantity' => 2, 'edge' => 'Half Bevel', 'finish' => 'Polished'],
                    ],
                    'location' => 'Pasig',
                    'landmark' => 'Second floor, facing the lift lobby',
                    'area_sqm' => 4.5,
                    'deadline_date' => '2026-10-30',
                    'deadline_time' => '10:00',
                    'notes' => 'The desk front is one slab, so please keep the veining running across the full width.',
                    'attachments' => [
                        ['name' => 'brightleaf-reception-desk.pdf', 'size_bytes' => 812544, 'mime_type' => 'application/pdf'],
                    ],
                    // Paid in full, then refunded when the lease fell through.
                    'status' => 'cancelled',
                    'status_changed_at' => '2026-10-06T02:00:00Z',
                    'quotations' => [
                        ['revision_no' => 1, 'status' => 'closed', 'total_amount' => '96500.00', 'issued_at' => '2026-09-22T03:00:00Z', 'responded_at' => '2026-09-23T01:15:00Z', 'updated_at' => '2026-09-23T05:00:00Z', 'created_by' => 'daniel'],
                    ],
                    'payment' => [
                        'status' => 'refunded',
                        'created_at' => '2026-09-23T01:15:00Z',
                        'status_changed_at' => '2026-10-06T02:00:00Z',
                        'transactions' => [
                            ['kind' => 'payment', 'amount' => '96500.00', 'reference' => 'BDO-0923-6015', 'received_at' => '2026-09-23T05:00:00Z', 'recorded_by' => 'daniel'],
                            ['kind' => 'refund', 'amount' => '96500.00', 'reference' => 'BDO-1006-6390', 'received_at' => '2026-10-06T02:00:00Z', 'recorded_by' => 'daniel'],
                        ],
                    ],
                    'job_orders' => [],
                    'cards' => [
                        ['project' => 'pending', 'quotation' => 'pending', 'payment' => 'awaiting', 'at' => '2026-09-22T03:00:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'closeable', 'payment' => 'awaiting', 'at' => '2026-09-23T01:15:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'closed', 'payment' => 'fully_paid', 'at' => '2026-09-23T05:00:00Z'],
                        ['project' => 'cancelled', 'quotation' => 'closed', 'payment' => 'refunded', 'at' => '2026-10-06T02:00:00Z'],
                    ],
                ],
                [
                    'id' => 'prj-1046-2',
                    'name' => 'Brightleaf Studio — Feature Wall',
                    'categories' => ['wall-accents'],
                    'services' => ['Natural Stone Supply', 'Natural Stone Installation'],
                    'stones' => [
                        ['stone_slug' => 'onyx', 'quantity' => 6, 'edge' => 'Straight Edge', 'finish' => 'Honed'],
                    ],
                    'location' => 'Pasig',
                    'landmark' => null,
                    'area_sqm' => 14,
                    'deadline_date' => '2026-11-06',
                    'deadline_time' => '09:00',
                    'notes' => null,
                    'attachments' => [],
                    // Its job order was cancelled in fabrication; the deposit covers the slabs already cut.
                    'status' => 'cancelled',
                    'status_changed_at' => '2026-10-06T02:20:00Z',
                    'quotations' => [
                        ['revision_no' => 1, 'status' => 'closeable', 'total_amount' => '174000.00', 'issued_at' => '2026-09-22T03:30:00Z', 'responded_at' => '2026-09-24T02:00:00Z', 'created_by' => 'daniel'],
                    ],
                    'payment' => [
                        'status' => 'partial',
                        'created_at' => '2026-09-24T02:00:00Z',
                        'status_changed_at' => '2026-09-25T03:00:00Z',
                        'transactions' => [
                            ['kind' => 'payment', 'amount' => '87000.00', 'reference' => 'BDO-0925-6102', 'received_at' => '2026-09-25T03:00:00Z', 'recorded_by' => 'daniel'],
                        ],
                    ],
                    'job_orders' => [
                        ['reference' => 'JO-2038', 'status' => 'cancelled', 'created_at' => '2026-09-25T05:00:00Z', 'status_changed_at' => '2026-10-06T02:20:00Z', 'notes' => 'Cancelled in fabrication when the second-floor lease fell through.'],
                    ],
                    'cards' => [
                        ['project' => 'pending', 'quotation' => 'pending', 'payment' => 'awaiting', 'at' => '2026-09-22T03:30:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'closeable', 'payment' => 'awaiting', 'at' => '2026-09-24T02:00:00Z'],
                        ['project' => 'ongoing', 'quotation' => 'closeable', 'payment' => 'partial', 'at' => '2026-09-25T03:00:00Z'],
                        ['project' => 'cancelled', 'quotation' => 'closeable', 'payment' => 'partial', 'at' => '2026-10-06T02:20:00Z'],
                    ],
                ],
            ],
            'status_history' => [
                ['from_status' => null, 'to_status' => 'draft', 'at' => '2026-09-20T02:30:00Z', 'changed_by' => 'cl-elena'],
                ['from_status' => 'draft', 'to_status' => 'pending', 'at' => '2026-09-20T06:00:00Z', 'changed_by' => 'daniel'],
                ['from_status' => 'pending', 'to_status' => 'ongoing', 'at' => '2026-09-23T01:15:00Z', 'changed_by' => 'daniel'],
                ['from_status' => 'ongoing', 'to_status' => 'lost', 'at' => '2026-10-06T02:20:00Z', 'changed_by' => 'daniel'],
            ],
        ],
    ],
];
