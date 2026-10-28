<?php

/**
 * The demo clients' leads, inquiries and the inquiries' group chats. One
 * inquiry is one project.
 *
 * `leads`: marketing's lead for each client. `created_at` is when `marketing`
 * was given the client's Regray Team thread (or, for a client whose last lead
 * was lost, when their next inquiry opened a new one). `won_at` or `lost_at`
 * is when marketing marked it; neither means it is still idle. A client has
 * one open lead at a time, and each inquiry names its `lead`.
 *
 * stone_slug is a legacy category reference; InquirySeeder resolves its first
 * individual stone. Every project has a deadline date and time (Manila):
 * InquirySeeder books it as the inquiry's schedule, the way sending an
 * inquiry does.
 *
 * `events` is what happened to the inquiry after it was sent, oldest first, in
 * UTC. Each event has an `at`, a `by` (the short keys InquirySeeder maps to
 * users) and one of:
 * - `sales`: sales moves to this value. `quoted` is the client's verbal
 *   approval in the group chat, and carries the `quote` total (the quotation
 *   file and the next quote revision) and, when sales has it, the `job_order`;
 * - `job_order`: sales uploads the job order after the quote. With the quote
 *   or later, it starts logistics at order_received;
 * - `payment_status`: accounting moves payment. `awaiting` starts it, with
 *   the billing statement; `partial` and `fully_paid` carry the `payment`
 *   received and its `reference`, with a receipt and an invoice;
 * - `process`: logistics moves to this value;
 * - `note`: why, as the history row records it.
 *
 * No role status has started when an inquiry is sent. Sales starts at
 * negotiation when its lead is won, or at once for an inquiry sent on a lead
 * that is already won (or for a direct client); logistics starts at
 * order_received with the job order, and payment at awaiting with the billing
 * statement. `shows` is the status the inquiry ends up shown
 * at; InquirySeeder checks it, and every step, against the flow in
 * docs/database/regray-erd.md, and tests/Feature/Database/InquirySeedConsistencyTest.php
 * checks the result.
 *
 * `direct_clients`: walk-ins with no account, taken down by `created_by`. An
 * inquiry names its `direct_client` instead of a `client_id` and a `lead`: it
 * has no lead, and sales starts with it, by whoever took the client down.
 *
 * `groups`: the group chats marketing made for an inquiry, with the sales and
 * technical `staff` it picked (every owner is added too), and the accounting
 * and logistics users a sales member added `later`.
 */
return [
    'leads' => [
        ['reference' => 'LEAD-0001', 'client_id' => 'cl-grace', 'marketing' => 'marketing', 'created_at' => '2026-08-12T07:30:00Z', 'won_at' => '2026-08-12T09:30:00Z'],
        ['reference' => 'LEAD-0002', 'client_id' => 'cl-haruki', 'marketing' => 'marketing', 'created_at' => '2026-08-28T06:30:00Z', 'won_at' => '2026-08-29T03:20:00Z'],
        ['reference' => 'LEAD-0003', 'client_id' => 'cl-elena', 'marketing' => 'marketing', 'created_at' => '2026-09-10T03:00:00Z', 'won_at' => '2026-09-11T02:00:00Z'],
        ['reference' => 'LEAD-0004', 'client_id' => 'cl-ivan', 'marketing' => 'marketing', 'created_at' => '2026-09-11T09:00:00Z', 'won_at' => '2026-09-12T02:10:00Z'],
        ['reference' => 'LEAD-0005', 'client_id' => 'cl-nadine', 'marketing' => 'marketing', 'created_at' => '2026-09-15T05:30:00Z', 'won_at' => '2026-09-16T01:05:00Z'],
        ['reference' => 'LEAD-0006', 'client_id' => 'cl-rafael', 'marketing' => 'marketing', 'created_at' => '2026-09-27T04:00:00Z', 'won_at' => '2026-09-28T06:15:00Z'],
        // Lucas went elsewhere, then came back with a new inquiry, which
        // opened a new lead for the marketing user holding his thread.
        ['reference' => 'LEAD-0007', 'client_id' => 'lucas', 'marketing' => 'marketing', 'created_at' => '2026-10-01T03:30:00Z', 'lost_at' => '2026-10-03T02:00:00Z'],
        ['reference' => 'LEAD-0008', 'client_id' => 'lucas', 'marketing' => 'marketing', 'created_at' => '2026-10-26T02:00:00Z', 'opened_by_inquiry' => true],
    ],
    'direct_clients' => [
        [
            'id' => 'dc-carmela',
            'full_name' => 'Carmela Reyes',
            'company' => 'Reyes Bakeshop',
            'address' => '14 Caruncho Avenue, San Nicolas, Pasig',
            'created_by' => 'maria',
            'contacts' => [
                ['kind' => 'phone', 'value' => '+63 917 555 0142'],
                ['kind' => 'viber', 'value' => '+63 917 555 0142'],
                ['kind' => 'email', 'value' => 'carmela@reyesbakeshop.example'],
            ],
        ],
    ],
    'groups' => [
        [
            'inquiry' => 'INQ-1049',
            'name' => 'Northgate Cafe — Dining Floor',
            'created_at' => '2026-09-28T07:00:00Z',
            'staff' => ['maria', 'daniel'],
            'later' => ['at' => '2026-09-30T06:00:00Z', 'by' => 'maria', 'add' => ['accounting', 'tomas']],
        ],
        [
            'inquiry' => 'INQ-1046',
            'name' => 'Brightleaf Studio — Reception Desk',
            'created_at' => '2026-09-20T07:00:00Z',
            'staff' => ['sofia', 'priya'],
            'later' => ['at' => '2026-09-23T02:00:00Z', 'by' => 'sofia', 'add' => ['accounting', 'kenji']],
        ],
        [
            'inquiry' => 'INQ-1044',
            'name' => 'Garden Court — Vanity Tops',
            'created_at' => '2026-09-16T02:00:00Z',
            'staff' => ['maria', 'daniel'],
        ],
    ],
    'inquiries' => [
        [
            'reference' => 'INQ-1039',
            'client_id' => 'cl-grace',
            'lead' => 'LEAD-0001',
            'created_at' => '2026-08-12T06:55:00Z',
            'handlers' => ['sofia'],
            'project' => [
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
            ],
            // The client turned the quote down in the group chat.
            'shows' => 'cancelled',
            'events' => [
                ['at' => '2026-08-14T01:10:00Z', 'by' => 'sofia', 'sales' => 'rejected', 'note' => 'The client turned the quotation down.'],
            ],
        ],
        [
            'reference' => 'INQ-1041',
            'client_id' => 'cl-haruki',
            'lead' => 'LEAD-0002',
            'created_at' => '2026-08-28T05:45:00Z',
            'handlers' => ['daniel', 'sofia'],
            'project' => [
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
            ],
            // Visited, quoted, approved, paid in two parts and installed.
            'shows' => 'closed',
            'events' => [
                ['at' => '2026-08-30T02:00:00Z', 'by' => 'sofia', 'sales' => 'site_visited', 'note' => 'Site visit done.'],
                ['at' => '2026-09-01T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '412800.00', 'job_order' => 'JO-2034', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-09-01T05:30:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
                ['at' => '2026-09-01T06:30:00Z', 'by' => 'accounting', 'payment_status' => 'partial', 'payment' => '206400.00', 'reference' => 'BPI-0901-7720', 'note' => 'Payment received.'],
                ['at' => '2026-09-01T08:00:00Z', 'by' => 'tomas', 'process' => 'measure'],
                ['at' => '2026-09-02T02:00:00Z', 'by' => 'kenji', 'process' => 'sourcing'],
                ['at' => '2026-09-02T09:00:00Z', 'by' => 'tomas', 'process' => 'production'],
                ['at' => '2026-09-04T03:00:00Z', 'by' => 'tomas', 'process' => 'quality_check'],
                ['at' => '2026-09-04T08:00:00Z', 'by' => 'tomas', 'process' => 'ready'],
                ['at' => '2026-09-05T01:00:00Z', 'by' => 'tomas', 'process' => 'dispatched'],
                ['at' => '2026-09-05T03:00:00Z', 'by' => 'accounting', 'payment_status' => 'fully_paid', 'payment' => '206400.00', 'reference' => 'BPI-0905-7794', 'note' => 'Paid in full.'],
                ['at' => '2026-09-06T02:00:00Z', 'by' => 'tomas', 'process' => 'installation'],
                ['at' => '2026-09-08T04:15:00Z', 'by' => 'tomas', 'process' => 'completed'],
            ],
        ],
        [
            'reference' => 'INQ-1042',
            'client_id' => 'cl-haruki',
            'lead' => 'LEAD-0002',
            'created_at' => '2026-08-28T05:50:00Z',
            'handlers' => ['daniel'],
            'project' => [
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
            ],
            // The pavers were delivered in two drops and laid by the client's
            // own crew, so logistics went from dispatched to completed with no
            // installation step.
            'shows' => 'closed',
            'events' => [
                ['at' => '2026-08-31T01:30:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '289000.00', 'job_order' => 'JO-2033', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-08-31T04:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
                ['at' => '2026-08-31T05:00:00Z', 'by' => 'accounting', 'payment_status' => 'partial', 'payment' => '144500.00', 'reference' => 'BPI-0831-7651', 'note' => 'Payment received.'],
                ['at' => '2026-08-31T07:00:00Z', 'by' => 'tomas', 'process' => 'measure'],
                ['at' => '2026-09-01T01:00:00Z', 'by' => 'kenji', 'process' => 'sourcing'],
                ['at' => '2026-09-01T06:00:00Z', 'by' => 'tomas', 'process' => 'production'],
                ['at' => '2026-09-02T03:00:00Z', 'by' => 'tomas', 'process' => 'quality_check'],
                ['at' => '2026-09-02T08:00:00Z', 'by' => 'tomas', 'process' => 'ready'],
                ['at' => '2026-09-03T01:00:00Z', 'by' => 'tomas', 'process' => 'dispatched'],
                ['at' => '2026-09-03T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'fully_paid', 'payment' => '144500.00', 'reference' => 'BPI-0903-7702', 'note' => 'Paid in full.'],
                ['at' => '2026-09-04T09:00:00Z', 'by' => 'tomas', 'process' => 'completed'],
            ],
        ],
        [
            'reference' => 'INQ-1043',
            'client_id' => 'cl-ivan',
            'lead' => 'LEAD-0004',
            'created_at' => '2026-09-11T08:25:00Z',
            'handlers' => ['priya'],
            'project' => [
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
            ],
            // Deposit paid; on hold for a replacement block after the first
            // one cracked at the saw.
            'shows' => 'on_hold',
            'events' => [
                ['at' => '2026-09-13T04:20:00Z', 'by' => 'maria', 'sales' => 'quoted', 'quote' => '268400.00', 'job_order' => 'JO-2036', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-09-14T06:40:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
                ['at' => '2026-09-14T07:40:00Z', 'by' => 'accounting', 'payment_status' => 'partial', 'payment' => '134200.00', 'reference' => 'GCASH-0914-2208', 'note' => 'Payment received.'],
                ['at' => '2026-09-15T02:00:00Z', 'by' => 'kenji', 'process' => 'measure'],
                ['at' => '2026-09-16T03:00:00Z', 'by' => 'kenji', 'process' => 'sourcing'],
                ['at' => '2026-09-18T03:30:00Z', 'by' => 'kenji', 'process' => 'on_hold'],
            ],
        ],
        [
            'reference' => 'INQ-1044',
            'client_id' => 'cl-nadine',
            'lead' => 'LEAD-0005',
            'created_at' => '2026-09-15T04:50:00Z',
            'handlers' => ['maria'],
            'project' => [
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
            ],
            // The client asked for changes; sales is still working on them.
            'shows' => 'negotiation',
            'events' => [],
        ],
        [
            'reference' => 'INQ-1046',
            'client_id' => 'cl-elena',
            'lead' => 'LEAD-0003',
            'created_at' => '2026-09-20T02:30:00Z',
            'handlers' => ['daniel'],
            'project' => [
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
            ],
            // Paid in full up front; the desk is being cut.
            'shows' => 'production',
            'events' => [
                ['at' => '2026-09-21T03:00:00Z', 'by' => 'sofia', 'sales' => 'site_visited', 'note' => 'Site visit done.'],
                ['at' => '2026-09-23T01:15:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '96500.00', 'job_order' => 'JO-2037', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-09-23T04:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
                ['at' => '2026-09-23T05:00:00Z', 'by' => 'accounting', 'payment_status' => 'fully_paid', 'payment' => '96500.00', 'reference' => 'BDO-0923-6015', 'note' => 'Paid in full.'],
                ['at' => '2026-09-24T02:00:00Z', 'by' => 'tomas', 'process' => 'measure'],
                ['at' => '2026-09-26T02:00:00Z', 'by' => 'kenji', 'process' => 'sourcing'],
                ['at' => '2026-10-02T02:00:00Z', 'by' => 'tomas', 'process' => 'production'],
            ],
        ],
        [
            'reference' => 'INQ-1047',
            'client_id' => 'cl-elena',
            'lead' => 'LEAD-0003',
            'created_at' => '2026-09-20T02:35:00Z',
            'handlers' => ['daniel'],
            'project' => [
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
            ],
            // The onyx shipment is late at the port.
            'shows' => 'delayed',
            'events' => [
                ['at' => '2026-09-24T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '174000.00', 'job_order' => 'JO-2038', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-09-25T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
                ['at' => '2026-09-25T03:00:00Z', 'by' => 'accounting', 'payment_status' => 'partial', 'payment' => '87000.00', 'reference' => 'BDO-0925-6102', 'note' => 'Payment received.'],
                ['at' => '2026-09-26T02:00:00Z', 'by' => 'tomas', 'process' => 'measure'],
                ['at' => '2026-09-29T02:00:00Z', 'by' => 'kenji', 'process' => 'sourcing'],
                ['at' => '2026-10-06T02:20:00Z', 'by' => 'kenji', 'process' => 'delayed'],
            ],
        ],
        [
            'reference' => 'INQ-1048',
            'client_id' => 'cl-rafael',
            'lead' => 'LEAD-0006',
            'created_at' => '2026-09-27T03:40:00Z',
            'handlers' => ['daniel', 'maria'],
            'project' => [
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
            ],
            // Still in discussion: the client has not approved a quotation.
            'shows' => 'negotiation',
            'events' => [],
        ],
        [
            'reference' => 'INQ-1049',
            'client_id' => 'cl-rafael',
            'lead' => 'LEAD-0006',
            'created_at' => '2026-09-27T03:45:00Z',
            'handlers' => ['maria'],
            'project' => [
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
            ],
            // Quoted, approved, paid in two parts and installed.
            'shows' => 'closed',
            'events' => [
                ['at' => '2026-09-30T05:30:00Z', 'by' => 'maria', 'sales' => 'quoted', 'quote' => '312000.00', 'job_order' => 'JO-2039', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-10-01T01:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
                ['at' => '2026-10-01T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'partial', 'payment' => '156000.00', 'reference' => 'BDO-1001-4471', 'note' => 'Payment received.'],
                ['at' => '2026-10-02T02:00:00Z', 'by' => 'tomas', 'process' => 'measure'],
                ['at' => '2026-10-05T02:00:00Z', 'by' => 'kenji', 'process' => 'sourcing'],
                ['at' => '2026-10-08T02:00:00Z', 'by' => 'tomas', 'process' => 'production'],
                ['at' => '2026-10-14T02:00:00Z', 'by' => 'tomas', 'process' => 'quality_check'],
                ['at' => '2026-10-15T02:00:00Z', 'by' => 'tomas', 'process' => 'ready'],
                ['at' => '2026-10-17T02:00:00Z', 'by' => 'tomas', 'process' => 'dispatched'],
                ['at' => '2026-10-20T06:00:00Z', 'by' => 'accounting', 'payment_status' => 'fully_paid', 'payment' => '156000.00', 'reference' => 'BDO-1020-5183', 'note' => 'Paid in full.'],
                ['at' => '2026-10-21T02:00:00Z', 'by' => 'tomas', 'process' => 'installation'],
                ['at' => '2026-10-24T08:00:00Z', 'by' => 'tomas', 'process' => 'completed'],
            ],
        ],
        [
            'reference' => 'INQ-1050',
            'client_id' => 'lucas',
            'lead' => 'LEAD-0008',
            'created_at' => '2026-10-26T02:00:00Z',
            'handlers' => [],
            'project' => [
                'name' => 'Moreau Studio — Kitchen Island',
                'categories' => ['kitchen-islands'],
                'services' => ['Custom Stone Fabrication', 'Natural Stone Installation'],
                'stones' => [
                    ['stone_slug' => 'marble', 'quantity' => 3, 'edge' => 'Half Bullnose', 'finish' => 'Polished'],
                ],
                'location' => 'Quezon City',
                'landmark' => null,
                'area_sqm' => 3.2,
                'deadline_date' => '2026-12-05',
                'deadline_time' => '10:00',
                'notes' => 'The island has a waterfall end on the side facing the dining room.',
                'attachments' => [],
            ],
            // Sent after the client's first lead was lost, so it opened a new
            // idle lead; marketing has not reached the client again yet.
            'shows' => 'pending',
            'events' => [],
        ],
        [
            'reference' => 'INQ-1051',
            'client_id' => 'cl-nadine',
            'lead' => 'LEAD-0005',
            'created_at' => '2026-10-12T03:00:00Z',
            'handlers' => ['maria'],
            'project' => [
                'name' => 'Lumen & Co — Lobby Feature Wall',
                'categories' => ['wall-accents'],
                'services' => ['Natural Stone Supply', 'Natural Stone Installation'],
                'stones' => [
                    ['stone_slug' => 'onyx', 'quantity' => 8, 'edge' => 'Straight Edge', 'finish' => 'Honed'],
                ],
                'location' => 'Taguig',
                'landmark' => 'Ground floor lobby, behind the reception desk',
                'area_sqm' => 16,
                'deadline_date' => '2026-12-12',
                'deadline_time' => '10:00',
                'notes' => 'The wall is backlit, so the onyx needs to be book-matched.',
                'attachments' => [
                    ['name' => 'lumen-lobby-elevation.pdf', 'size_bytes' => 702464, 'mime_type' => 'application/pdf'],
                ],
            ],
            'shows' => 'site_visited',
            'events' => [
                ['at' => '2026-10-16T06:00:00Z', 'by' => 'maria', 'sales' => 'site_visited', 'note' => 'Site visit done.'],
            ],
        ],
        [
            'reference' => 'INQ-1052',
            'client_id' => 'cl-ivan',
            'lead' => 'LEAD-0004',
            'created_at' => '2026-10-05T04:00:00Z',
            'handlers' => ['priya'],
            'project' => [
                'name' => 'Hillcrest Showroom — Front Steps',
                'categories' => ['exterior-walls'],
                'services' => ['Natural Stone Supply', 'Natural Stone Installation'],
                'stones' => [
                    ['stone_slug' => 'granite', 'quantity' => 14, 'edge' => 'Full Bevel', 'finish' => 'Leather'],
                ],
                'location' => 'San Juan',
                'landmark' => 'Street entrance',
                'area_sqm' => 9,
                'deadline_date' => '2026-12-01',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [],
            ],
            // The client is waiting on the building's permit before going on.
            'shows' => 'on_hold',
            'events' => [
                ['at' => '2026-10-08T03:00:00Z', 'by' => 'maria', 'sales' => 'site_visited', 'note' => 'Site visit done.'],
                ['at' => '2026-10-10T05:00:00Z', 'by' => 'maria', 'sales' => 'on_hold', 'note' => 'On hold at the client’s request.'],
            ],
        ],
        [
            'reference' => 'INQ-1053',
            'client_id' => 'cl-haruki',
            'lead' => 'LEAD-0002',
            'created_at' => '2026-10-14T02:00:00Z',
            'handlers' => ['sofia'],
            'project' => [
                'name' => 'The Grove — Guest Bath Vanity',
                'categories' => ['lavatories'],
                'services' => ['Custom Stone Fabrication', 'Natural Stone Installation'],
                'stones' => [
                    ['stone_slug' => 'marble', 'quantity' => 2, 'edge' => 'Reverse Chamfer', 'finish' => 'Honed'],
                ],
                'location' => 'Muntinlupa',
                'landmark' => null,
                'area_sqm' => 2.4,
                'deadline_date' => '2026-11-28',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [],
            ],
            // Quoted twice (the client changed a stone after the first) and
            // approved; the job order is not in yet, and accounting has sent
            // the billing statement for the deposit.
            'shows' => 'quoted',
            'events' => [
                ['at' => '2026-10-17T03:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '124500.00', 'note' => 'The client approved the first quotation in the group chat.'],
                ['at' => '2026-10-18T02:00:00Z', 'by' => 'sofia', 'sales' => 'negotiation', 'note' => 'The client asked to change a stone, so the quotation is being revised.'],
                ['at' => '2026-10-20T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '128900.00', 'note' => 'The client approved the revised quotation in the group chat.'],
                ['at' => '2026-10-20T05:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
            ],
        ],
        [
            'reference' => 'INQ-1054',
            'client_id' => 'lucas',
            'lead' => 'LEAD-0007',
            'created_at' => '2026-10-01T03:00:00Z',
            'handlers' => ['sofia'],
            'project' => [
                'name' => 'Moreau Studio — Garden Path',
                'categories' => ['exterior-walls'],
                'services' => ['Natural Stone Supply'],
                'stones' => [
                    ['stone_slug' => 'cobblestone', 'quantity' => 40, 'edge' => 'Straight Edge', 'finish' => 'Honed'],
                ],
                'location' => 'Quezon City',
                'landmark' => null,
                'area_sqm' => 22,
                'deadline_date' => '2026-11-10',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [],
            ],
            // The client went with a landscaper's own supplier: marketing lost
            // the lead before sales had started.
            'shows' => 'cancelled',
            'events' => [],
        ],
        [
            'reference' => 'INQ-1055',
            'client_id' => 'cl-elena',
            'lead' => 'LEAD-0003',
            'created_at' => '2026-10-08T02:00:00Z',
            'handlers' => ['daniel'],
            'project' => [
                'name' => 'Brightleaf Studio — Pantry Counter',
                'categories' => ['countertops-backsplash'],
                'services' => ['Custom Stone Fabrication', 'Natural Stone Installation'],
                'stones' => [
                    ['stone_slug' => 'granite', 'quantity' => 2, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                ],
                'location' => 'Pasig',
                'landmark' => null,
                'area_sqm' => 2.8,
                'deadline_date' => '2026-11-20',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [],
            ],
            // The job order is in; logistics has it, the deposit is not.
            'shows' => 'order_received',
            'events' => [
                ['at' => '2026-10-15T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '84300.00', 'job_order' => 'JO-2045', 'note' => 'The client approved the quotation in the group chat.'],
            ],
        ],
        [
            'reference' => 'INQ-1056',
            'client_id' => 'cl-rafael',
            'lead' => 'LEAD-0006',
            'created_at' => '2026-10-03T02:00:00Z',
            'handlers' => ['maria'],
            'project' => [
                'name' => 'Northgate Cafe — Restroom Vanities',
                'categories' => ['lavatories'],
                'services' => ['Custom Stone Fabrication', 'Natural Stone Installation'],
                'stones' => [
                    ['stone_slug' => 'quartzite', 'quantity' => 4, 'edge' => 'Half Bevel', 'finish' => 'Honed'],
                ],
                'location' => 'Makati',
                'landmark' => 'Second floor, beside the mezzanine staircase',
                'area_sqm' => 3.6,
                'deadline_date' => '2026-11-21',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [],
            ],
            'shows' => 'measure',
            'events' => [
                ['at' => '2026-10-09T02:00:00Z', 'by' => 'maria', 'sales' => 'quoted', 'quote' => '142000.00', 'job_order' => 'JO-2043', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-10-11T01:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
                ['at' => '2026-10-11T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'partial', 'payment' => '71000.00', 'reference' => 'BPI-1011-3301', 'note' => 'Payment received.'],
                ['at' => '2026-10-13T02:00:00Z', 'by' => 'tomas', 'process' => 'measure'],
            ],
        ],
        [
            'reference' => 'INQ-1057',
            'client_id' => 'cl-nadine',
            'lead' => 'LEAD-0005',
            'created_at' => '2026-09-25T02:00:00Z',
            'handlers' => ['maria'],
            'project' => [
                'name' => 'Lumen & Co — Conference Table',
                'categories' => ['dining-tables'],
                'services' => ['Custom Stone Furniture'],
                'stones' => [
                    ['stone_slug' => 'marble', 'quantity' => 2, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                ],
                'location' => 'Taguig',
                'landmark' => 'Fourteenth floor boardroom',
                'area_sqm' => 4,
                'deadline_date' => '2026-11-14',
                'deadline_time' => '10:00',
                'notes' => 'The top seats twelve; the base is the client\'s own steel frame.',
                'attachments' => [],
            ],
            'shows' => 'sourcing',
            'events' => [
                ['at' => '2026-10-01T02:00:00Z', 'by' => 'maria', 'sales' => 'quoted', 'quote' => '98500.00', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-10-02T02:00:00Z', 'by' => 'maria', 'job_order' => 'JO-2042', 'note' => 'Job order in for the counters.'],
                ['at' => '2026-10-02T05:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
                ['at' => '2026-10-02T06:00:00Z', 'by' => 'accounting', 'payment_status' => 'partial', 'payment' => '49250.00', 'reference' => 'GCASH-1002-4410', 'note' => 'Payment received.'],
                ['at' => '2026-10-05T02:00:00Z', 'by' => 'tomas', 'process' => 'measure'],
                ['at' => '2026-10-09T02:00:00Z', 'by' => 'kenji', 'process' => 'sourcing'],
            ],
        ],
        [
            'reference' => 'INQ-1058',
            'client_id' => 'cl-ivan',
            'lead' => 'LEAD-0004',
            'created_at' => '2026-09-18T02:00:00Z',
            'handlers' => ['priya'],
            'project' => [
                'name' => 'Hillcrest Showroom — Reception Counter',
                'categories' => ['countertops-backsplash'],
                'services' => ['Custom Stone Fabrication', 'Natural Stone Installation'],
                'stones' => [
                    ['stone_slug' => 'quartzite', 'quantity' => 3, 'edge' => 'Straight Edge', 'finish' => 'Leather'],
                ],
                'location' => 'San Juan',
                'landmark' => null,
                'area_sqm' => 5.5,
                'deadline_date' => '2026-11-07',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [],
            ],
            'shows' => 'quality_check',
            'events' => [
                ['at' => '2026-09-23T02:00:00Z', 'by' => 'maria', 'sales' => 'quoted', 'quote' => '156000.00', 'job_order' => 'JO-2040', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-09-24T01:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
                ['at' => '2026-09-24T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'partial', 'payment' => '78000.00', 'reference' => 'BDO-0924-5521', 'note' => 'Payment received.'],
                ['at' => '2026-09-25T02:00:00Z', 'by' => 'tomas', 'process' => 'measure'],
                ['at' => '2026-09-29T02:00:00Z', 'by' => 'kenji', 'process' => 'sourcing'],
                ['at' => '2026-10-03T02:00:00Z', 'by' => 'tomas', 'process' => 'production'],
                ['at' => '2026-10-21T02:00:00Z', 'by' => 'tomas', 'process' => 'quality_check'],
            ],
        ],
        [
            'reference' => 'INQ-1059',
            'client_id' => 'cl-haruki',
            'lead' => 'LEAD-0002',
            'created_at' => '2026-09-16T02:00:00Z',
            'handlers' => ['daniel'],
            'project' => [
                'name' => 'The Grove — Outdoor Shower Wall',
                'categories' => ['exterior-walls'],
                'services' => ['Natural Stone Supply', 'Natural Stone Installation'],
                'stones' => [
                    ['stone_slug' => 'travertine', 'quantity' => 10, 'edge' => 'Straight Edge', 'finish' => 'Honed'],
                ],
                'location' => 'Muntinlupa',
                'landmark' => 'Beside the pool deck',
                'area_sqm' => 6,
                'deadline_date' => '2026-10-31',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [],
            ],
            // Paid in full; cut, checked and waiting to go out.
            'shows' => 'ready',
            'events' => [
                ['at' => '2026-09-21T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '112400.00', 'job_order' => 'JO-2041', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-09-22T01:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
                ['at' => '2026-09-22T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'fully_paid', 'payment' => '112400.00', 'reference' => 'BPI-0922-7810', 'note' => 'Paid in full.'],
                ['at' => '2026-09-23T02:00:00Z', 'by' => 'tomas', 'process' => 'measure'],
                ['at' => '2026-09-26T02:00:00Z', 'by' => 'kenji', 'process' => 'sourcing'],
                ['at' => '2026-10-01T02:00:00Z', 'by' => 'tomas', 'process' => 'production'],
                ['at' => '2026-10-15T02:00:00Z', 'by' => 'tomas', 'process' => 'quality_check'],
                ['at' => '2026-10-19T02:00:00Z', 'by' => 'tomas', 'process' => 'ready'],
            ],
        ],
        [
            'reference' => 'INQ-1060',
            'client_id' => 'cl-elena',
            'lead' => 'LEAD-0003',
            'created_at' => '2026-09-10T02:00:00Z',
            'handlers' => ['daniel'],
            'project' => [
                'name' => 'Brightleaf Studio — Window Sills',
                'categories' => ['fixtures'],
                'services' => ['Natural Stone Supply', 'Custom Stone Fabrication'],
                'stones' => [
                    ['stone_slug' => 'travertine', 'quantity' => 12, 'edge' => 'Straight Edge', 'finish' => 'Honed'],
                ],
                'location' => 'Pasig',
                'landmark' => null,
                'area_sqm' => 3,
                'deadline_date' => '2026-10-30',
                'deadline_time' => '10:00',
                'notes' => 'Supply only: the studio\'s contractor sets the sills.',
                'attachments' => [],
            ],
            'shows' => 'dispatched',
            'events' => [
                ['at' => '2026-09-15T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '46800.00', 'job_order' => 'JO-2035', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-09-16T01:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
                ['at' => '2026-09-16T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'partial', 'payment' => '23400.00', 'reference' => 'BDO-0916-6020', 'note' => 'Payment received.'],
                ['at' => '2026-09-17T02:00:00Z', 'by' => 'tomas', 'process' => 'measure'],
                ['at' => '2026-09-21T02:00:00Z', 'by' => 'kenji', 'process' => 'sourcing'],
                ['at' => '2026-09-28T02:00:00Z', 'by' => 'tomas', 'process' => 'production'],
                ['at' => '2026-10-08T02:00:00Z', 'by' => 'tomas', 'process' => 'quality_check'],
                ['at' => '2026-10-12T02:00:00Z', 'by' => 'tomas', 'process' => 'ready'],
                ['at' => '2026-10-23T02:00:00Z', 'by' => 'tomas', 'process' => 'dispatched'],
            ],
        ],
        [
            'reference' => 'INQ-1061',
            'client_id' => 'cl-grace',
            'lead' => 'LEAD-0001',
            'created_at' => '2026-09-05T02:00:00Z',
            'handlers' => ['sofia'],
            'project' => [
                'name' => 'Maple Lane — Bathroom Floor',
                'categories' => ['lavatories'],
                'services' => ['Natural Stone Supply', 'Natural Stone Installation'],
                'stones' => [
                    ['stone_slug' => 'marble', 'quantity' => 18, 'edge' => 'Straight Edge', 'finish' => 'Honed'],
                ],
                'location' => 'Quezon City',
                'landmark' => null,
                'area_sqm' => 7.5,
                'deadline_date' => '2026-10-28',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [
                    ['name' => 'maple-lane-bathroom-plan.jpg', 'size_bytes' => 534528, 'mime_type' => 'image/jpeg'],
                ],
            ],
            'shows' => 'installation',
            'events' => [
                ['at' => '2026-09-08T03:00:00Z', 'by' => 'sofia', 'sales' => 'site_visited', 'note' => 'Site visit done.'],
                ['at' => '2026-09-12T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '134500.00', 'job_order' => 'JO-2032', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-09-13T01:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
                ['at' => '2026-09-13T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'partial', 'payment' => '67250.00', 'reference' => 'GCASH-0913-1180', 'note' => 'Payment received.'],
                ['at' => '2026-09-14T02:00:00Z', 'by' => 'tomas', 'process' => 'measure'],
                ['at' => '2026-09-17T02:00:00Z', 'by' => 'kenji', 'process' => 'sourcing'],
                ['at' => '2026-09-22T02:00:00Z', 'by' => 'tomas', 'process' => 'production'],
                ['at' => '2026-10-02T02:00:00Z', 'by' => 'tomas', 'process' => 'quality_check'],
                ['at' => '2026-10-06T02:00:00Z', 'by' => 'tomas', 'process' => 'ready'],
                ['at' => '2026-10-14T02:00:00Z', 'by' => 'tomas', 'process' => 'dispatched'],
                ['at' => '2026-10-20T02:00:00Z', 'by' => 'tomas', 'process' => 'installation'],
            ],
        ],
        [
            'reference' => 'INQ-1062',
            'direct_client' => 'dc-carmela',
            'created_at' => '2026-10-20T03:00:00Z',
            'handlers' => ['maria'],
            'project' => [
                'name' => 'Reyes Bakeshop — Display Counter',
                'categories' => ['countertops-backsplash'],
                'services' => ['Natural Stone Supply'],
                'stones' => [
                    ['stone_slug' => 'granite', 'quantity' => 2, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                ],
                'location' => 'Pasig',
                'landmark' => null,
                'area_sqm' => 4.5,
                'deadline_date' => '2026-12-12',
                'deadline_time' => '10:00',
                'notes' => 'Walked into the showroom; prefers to be called.',
                'attachments' => [],
            ],
            // A walk-in with no account: no lead, sales from the start.
            'shows' => 'negotiation',
            'events' => [],
        ],
    ],
];
