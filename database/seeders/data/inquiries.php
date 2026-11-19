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
 * individual stone. A stone row's `edge`, `finish`, `slab_size` (`[width,
 * length, unit]`) and `thickness` (`[value, unit]`) are written only where the
 * project's service asks for them, as the Inquire page sends them; the slab
 * services ask all four, every furniture service asks the project area, and
 * Countertop and Backsplash asks fascia (yes or no). `answers` are the
 * project's answers to its service's project-scope questions
 * (data/services.php), by question slug, a closed option by its slug;
 * InquirySeeder refuses a required one left out, and one the service does
 * not ask. Every project has a deadline date and time (Manila):
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
 * A project's `place` is where it is delivered or installed (a key of
 * data/places.php). A service that asks Delivery or Pickup
 * (`delivery_or_pickup`) is a delivery unless it says `delivery_method`
 * `pickup`, with no place; an installed service asks no method. Its brands are
 * the brands of its stones unless it names `brands` (slugs) or says
 * `brand_no_preference`; a project with no stones has no preference.
 *
 * `direct_clients`: walk-ins with no account, taken down by `created_by`,
 * with a phone and maybe an email, as the Direct client form asks. An
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
        // Lucas went elsewhere, so marketing lost his first inquiry; he came
        // back later with a new one, which waits on the same lead.
        ['reference' => 'LEAD-0007', 'client_id' => 'lucas', 'marketing' => 'marketing', 'created_at' => '2026-10-01T03:30:00Z', 'lost_at' => '2026-10-03T02:00:00Z'],
    ],
    'direct_clients' => [
        [
            'id' => 'dc-carmela',
            'full_name' => 'Carmela Reyes',
            'created_by' => 'maria',
            'phone' => '+63 917 555 0142',
            'email' => 'carmela@reyesbakeshop.example',
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
                'service' => 'countertop-backsplash',
                'stones' => [
                    ['stone_slug' => 'onyx', 'quantity' => 3, 'edge' => 'Half Bullnose', 'finish' => 'Honed'],
                    ['stone_slug' => 'granite', 'quantity' => 10, 'edge' => 'Half Bullnose', 'finish' => 'Honed'],
                ],
                'place' => 'Quezon City',
                'answers' => ['area_sqm' => 3, 'fascia' => true],
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
                'service' => 'wall-cladding',
                'stones' => [
                    ['stone_slug' => 'quartzite', 'quantity' => 12, 'edge' => 'Full Bevel', 'finish' => 'Leather'],
                    ['stone_slug' => 'granite', 'quantity' => 6, 'edge' => 'Full Bevel', 'finish' => 'Leather'],
                    ['stone_slug' => 'marble', 'quantity' => 4, 'edge' => 'Full Bevel', 'finish' => 'Leather'],
                ],
                'place' => 'Muntinlupa',
                'answers' => ['area_sqm' => 11.5],
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
                ['at' => '2026-09-01T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '412800.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-09-01T02:01:00Z', 'by' => 'sofia', 'sales' => 'won', 'job_order' => 'JO-2034', 'note' => 'The client approved the quotation in the group chat.'],
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
                'service' => 'flooring',
                'stones' => [
                    ['stone_slug' => 'travertine', 'quantity' => 20, 'edge' => 'Straight Edge', 'finish' => 'Honed'],
                ],
                'place' => 'Muntinlupa',
                'answers' => ['area_sqm' => 24.25],
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
                ['at' => '2026-08-31T01:30:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '289000.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-08-31T01:31:00Z', 'by' => 'sofia', 'sales' => 'won', 'job_order' => 'JO-2033', 'note' => 'The client approved the quotation in the group chat.'],
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
                'service' => 'dining-tables',
                'stones' => [
                    ['stone_slug' => 'granite', 'quantity' => 5, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                    ['stone_slug' => 'monumental', 'quantity' => 2, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                ],
                'place' => 'San Juan',
                'answers' => ['area_sqm' => 4.5, 'seaters' => 8, 'table_base' => 'same-as-stone'],
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
                ['at' => '2026-09-13T04:20:00Z', 'by' => 'maria', 'sales' => 'quoted', 'quote' => '268400.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-09-13T04:21:00Z', 'by' => 'maria', 'sales' => 'won', 'job_order' => 'JO-2036', 'note' => 'The client approved the quotation in the group chat.'],
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
                'service' => 'staircase',
                'stones' => [
                    ['stone_slug' => 'travertine', 'quantity' => 8, 'edge' => 'Reverse Chamfer', 'finish' => 'Honed'],
                    ['stone_slug' => 'marble', 'quantity' => 5, 'edge' => 'Reverse Chamfer', 'finish' => 'Honed'],
                ],
                'place' => 'San Juan',
                'answers' => ['area_sqm' => 9.75],
                'deadline_date' => '2026-10-30',
                'deadline_time' => '09:00',
                'notes' => null,
                'attachments' => [],
            ],
            // Quoted, then revised when the client asked for changes; the
            // revised quotation waits on the client's approval.
            'shows' => 'revision',
            'events' => [
                ['at' => '2026-10-20T02:00:00Z', 'by' => 'maria', 'sales' => 'quoted', 'quote' => '216000.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-10-24T03:00:00Z', 'by' => 'maria', 'sales' => 'revision', 'quote' => '198500.00', 'note' => 'Sent the revised quotation, with the changes the client asked for.'],
            ],
        ],
        [
            'reference' => 'INQ-1046',
            'client_id' => 'cl-elena',
            'lead' => 'LEAD-0003',
            'created_at' => '2026-09-20T02:30:00Z',
            'handlers' => ['daniel'],
            'project' => [
                'name' => 'Brightleaf Studio — Reception Desk',
                'service' => 'other-customized-furniture',
                'stones' => [
                    ['stone_slug' => 'marble', 'quantity' => 2, 'edge' => 'Half Bevel', 'finish' => 'Polished'],
                ],
                'place' => 'Pasig',
                'answers' => ['area_sqm' => 1.5, 'size' => '220 x 70 cm'],
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
                ['at' => '2026-09-23T01:15:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '96500.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-09-23T01:16:00Z', 'by' => 'sofia', 'sales' => 'won', 'job_order' => 'JO-2037', 'note' => 'The client approved the quotation in the group chat.'],
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
                'service' => 'wall-cladding',
                'stones' => [
                    ['stone_slug' => 'marble', 'quantity' => 6, 'edge' => 'Straight Edge', 'finish' => 'Honed'],
                ],
                'place' => 'Pasig',
                'answers' => ['area_sqm' => 14],
                'deadline_date' => '2026-11-06',
                'deadline_time' => '09:00',
                'notes' => null,
                'attachments' => [],
            ],
            // The onyx shipment is late at the port.
            'shows' => 'delayed',
            'events' => [
                ['at' => '2026-09-24T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '174000.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-09-24T02:01:00Z', 'by' => 'sofia', 'sales' => 'won', 'job_order' => 'JO-2038', 'note' => 'The client approved the quotation in the group chat.'],
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
                'service' => 'countertop-backsplash',
                'stones' => [
                    ['stone_slug' => 'quartzite', 'quantity' => 7, 'edge' => 'Half Bevel', 'finish' => 'Leather'],
                    ['stone_slug' => 'quartzite', 'quantity' => 3, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                    ['stone_slug' => 'granite', 'quantity' => 4, 'edge' => 'Half Bevel', 'finish' => 'Leather'],
                    ['stone_slug' => 'granite', 'quantity' => 2, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                ],
                'place' => 'Makati',
                'answers' => ['area_sqm' => 18.5, 'fascia' => false],
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
                'service' => 'pedestal',
                'stones' => [
                    ['stone_slug' => 'quartzite', 'quantity' => 9, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                ],
                'place' => 'Makati',
                'answers' => ['area_sqm' => 0.4, 'size' => '60 x 60 cm'],
                'deadline_date' => '2026-10-24',
                'deadline_time' => '14:00',
                'notes' => "Wanted square to the window wall so the joints run with it.\nPlease do the floor before the counters are set.",
                'attachments' => [],
            ],
            // Quoted, approved, paid in two parts and installed.
            'shows' => 'closed',
            'events' => [
                ['at' => '2026-09-30T05:30:00Z', 'by' => 'maria', 'sales' => 'quoted', 'quote' => '312000.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-09-30T05:31:00Z', 'by' => 'maria', 'sales' => 'won', 'job_order' => 'JO-2039', 'note' => 'The client approved the quotation in the group chat.'],
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
            'lead' => 'LEAD-0007',
            'created_at' => '2026-10-26T02:00:00Z',
            'handlers' => [],
            'project' => [
                'name' => 'Moreau Studio — Kitchen Island',
                'service' => 'slab-supply-only',
                'stones' => [
                    ['stone_slug' => 'marble', 'quantity' => 3, 'edge' => 'Half Bullnose', 'finish' => 'Polished', 'slab_size' => [120, 240, 'cm'], 'thickness' => [20, 'mm']],
                ],
                'place' => 'Quezon City',
                'answers' => [],
                'deadline_date' => '2026-12-05',
                'deadline_time' => '10:00',
                'notes' => 'The island has a waterfall end on the side facing the dining room.',
                'attachments' => [],
            ],
            // Sent after marketing lost the client's first inquiry: it waits on
            // the same lead, and marketing has not decided it yet.
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
                'service' => 'countertop-backsplash',
                'stones' => [
                    ['stone_slug' => 'onyx', 'quantity' => 8, 'edge' => 'Straight Edge', 'finish' => 'Honed'],
                ],
                'place' => 'Taguig',
                'answers' => ['area_sqm' => 16, 'fascia' => true],
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
                'service' => 'wall-cladding',
                'stones' => [
                    ['stone_slug' => 'granite', 'quantity' => 14, 'edge' => 'Full Bevel', 'finish' => 'Leather'],
                ],
                'place' => 'San Juan',
                'answers' => ['area_sqm' => 9],
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
                'service' => 'flooring',
                'stones' => [
                    ['stone_slug' => 'marble', 'quantity' => 2, 'edge' => 'Reverse Chamfer', 'finish' => 'Honed'],
                ],
                'place' => 'Muntinlupa',
                'answers' => ['area_sqm' => 2.4],
                'deadline_date' => '2026-11-28',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [],
            ],
            // Quoted twice (the client changed a stone after the first) and
            // approved with the job order; the deposit is in, so logistics has
            // received it.
            'shows' => 'order_received',
            'events' => [
                ['at' => '2026-10-17T03:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '124500.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-10-18T02:00:00Z', 'by' => 'sofia', 'sales' => 'negotiation', 'note' => 'The client asked to change a stone, so the quotation is being revised.'],
                ['at' => '2026-10-20T02:00:00Z', 'by' => 'sofia', 'sales' => 'revision', 'quote' => '128900.00', 'note' => 'Sent the revised quotation to the client.'],
                ['at' => '2026-10-20T02:01:00Z', 'by' => 'sofia', 'sales' => 'won', 'job_order' => 'JO-2046', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-10-20T05:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
                ['at' => '2026-10-22T03:00:00Z', 'by' => 'accounting', 'payment_status' => 'partial', 'payment' => '64450.00', 'reference' => 'BPI-1022-8114', 'note' => 'Deposit received.'],
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
                'service' => 'staircase',
                'stones' => [
                    ['stone_slug' => 'cobblestone', 'quantity' => 40, 'edge' => 'Straight Edge', 'finish' => 'Honed'],
                ],
                'place' => 'Quezon City',
                'answers' => ['area_sqm' => 22],
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
                'service' => 'countertop-backsplash',
                'stones' => [
                    ['stone_slug' => 'granite', 'quantity' => 2, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                ],
                'place' => 'Pasig',
                'answers' => ['area_sqm' => 2.8, 'fascia' => false],
                'deadline_date' => '2026-11-20',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [],
            ],
            // Approved with the job order and billed, but nothing paid yet, so
            // logistics has not received it: it shows quoted.
            'shows' => 'quoted',
            'events' => [
                ['at' => '2026-10-15T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '84300.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-10-15T02:01:00Z', 'by' => 'sofia', 'sales' => 'won', 'job_order' => 'JO-2045', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-10-15T06:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
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
                'service' => 'lavatories',
                'stones' => [
                    ['stone_slug' => 'quartzite', 'quantity' => 4, 'edge' => 'Half Bevel', 'finish' => 'Honed'],
                ],
                'place' => 'Makati',
                'answers' => ['area_sqm' => 3.6],
                'deadline_date' => '2026-11-21',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [],
            ],
            'shows' => 'measure',
            'events' => [
                ['at' => '2026-10-09T02:00:00Z', 'by' => 'maria', 'sales' => 'quoted', 'quote' => '142000.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-10-09T02:01:00Z', 'by' => 'maria', 'sales' => 'won', 'job_order' => 'JO-2043', 'note' => 'The client approved the quotation in the group chat.'],
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
                'service' => 'dining-tables',
                'stones' => [
                    ['stone_slug' => 'marble', 'quantity' => 2, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                ],
                'place' => 'Taguig',
                'answers' => ['area_sqm' => 6, 'seaters' => 10, 'table_base' => 'steel'],
                'deadline_date' => '2026-11-14',
                'deadline_time' => '10:00',
                'notes' => 'The top seats twelve; the base is the client\'s own steel frame.',
                'attachments' => [],
            ],
            'shows' => 'sourcing',
            'events' => [
                ['at' => '2026-10-01T02:00:00Z', 'by' => 'maria', 'sales' => 'quoted', 'quote' => '98500.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-10-01T02:01:00Z', 'by' => 'maria', 'sales' => 'won', 'job_order' => 'JO-2042', 'note' => 'The client approved the quotation in the group chat.'],
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
                'service' => 'other-customized-furniture',
                'stones' => [
                    ['stone_slug' => 'quartzite', 'quantity' => 3, 'edge' => 'Straight Edge', 'finish' => 'Leather'],
                ],
                // They collect it from the showroom: a pickup has no address.
                'delivery_method' => 'pickup',
                'place' => null,
                'brand_no_preference' => true,
                'answers' => ['area_sqm' => 1.1, 'size' => '180 x 60 cm'],
                'deadline_date' => '2026-11-07',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [],
            ],
            'shows' => 'quality_check',
            'events' => [
                ['at' => '2026-09-23T02:00:00Z', 'by' => 'maria', 'sales' => 'quoted', 'quote' => '156000.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-09-23T02:01:00Z', 'by' => 'maria', 'sales' => 'won', 'job_order' => 'JO-2040', 'note' => 'The client approved the quotation in the group chat.'],
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
                'service' => 'pedestal',
                'stones' => [
                    ['stone_slug' => 'travertine', 'quantity' => 10, 'edge' => 'Straight Edge', 'finish' => 'Honed'],
                ],
                'place' => 'Muntinlupa',
                'answers' => ['area_sqm' => 0.6, 'size' => '80 x 80 cm'],
                'deadline_date' => '2026-10-31',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [],
            ],
            // Paid in full; cut, checked and waiting to go out.
            'shows' => 'ready',
            'events' => [
                ['at' => '2026-09-21T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '112400.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-09-21T02:01:00Z', 'by' => 'sofia', 'sales' => 'won', 'job_order' => 'JO-2041', 'note' => 'The client approved the quotation in the group chat.'],
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
                'service' => 'slab-supply-only',
                'stones' => [
                    ['stone_slug' => 'travertine', 'quantity' => 12, 'edge' => 'Straight Edge', 'finish' => 'Honed', 'slab_size' => [60, 120, 'cm'], 'thickness' => [18, 'mm']],
                ],
                'place' => 'Pasig',
                'answers' => [],
                'deadline_date' => '2026-10-30',
                'deadline_time' => '10:00',
                'notes' => 'Supply only: the studio\'s contractor sets the sills.',
                'attachments' => [],
            ],
            'shows' => 'dispatched',
            'events' => [
                ['at' => '2026-09-15T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '46800.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-09-15T02:01:00Z', 'by' => 'sofia', 'sales' => 'won', 'job_order' => 'JO-2035', 'note' => 'The client approved the quotation in the group chat.'],
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
                'service' => 'countertop-backsplash',
                'stones' => [
                    ['stone_slug' => 'marble', 'quantity' => 18, 'edge' => 'Straight Edge', 'finish' => 'Honed'],
                ],
                'place' => 'Quezon City',
                'answers' => ['area_sqm' => 7.5, 'fascia' => true],
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
                ['at' => '2026-09-12T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '134500.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-09-12T02:01:00Z', 'by' => 'sofia', 'sales' => 'won', 'job_order' => 'JO-2032', 'note' => 'The client approved the quotation in the group chat.'],
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
                'service' => 'wall-cladding',
                'stones' => [
                    ['stone_slug' => 'granite', 'quantity' => 2, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                ],
                'place' => 'Pasig',
                'answers' => ['area_sqm' => 4.5],
                'deadline_date' => '2026-12-12',
                'deadline_time' => '10:00',
                'notes' => 'Walked into the showroom; prefers to be called.',
                'attachments' => [],
            ],
            // A walk-in with no account: no lead, sales from the start.
            // Quoted; the quotation waits on the client's approval.
            'shows' => 'quoted',
            'events' => [
                ['at' => '2026-10-23T02:00:00Z', 'by' => 'maria', 'sales' => 'quoted', 'quote' => '58200.00', 'note' => 'Sent the quotation to the client.'],
            ],
        ],
        [
            'reference' => 'INQ-1063',
            'client_id' => 'cl-elena',
            'lead' => 'LEAD-0003',
            'created_at' => '2026-10-01T02:00:00Z',
            'handlers' => ['sofia'],
            'project' => [
                'name' => 'Elena — Kitchen Island Top',
                'service' => 'flooring',
                'stones' => [
                    ['stone_slug' => 'granite', 'quantity' => 3, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                ],
                'place' => 'Makati',
                'answers' => ['area_sqm' => 5],
                'deadline_date' => '2026-12-15',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [],
            ],
            // Approved and part-paid, then the client asked for their money
            // back: sales requested the refund, and accounting has not
            // refunded anything yet.
            'shows' => 'for_refund',
            'events' => [
                ['at' => '2026-10-02T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '96000.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-10-02T02:01:00Z', 'by' => 'sofia', 'sales' => 'won', 'job_order' => 'JO-2047', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-10-03T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
                ['at' => '2026-10-04T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'partial', 'payment' => '48000.00', 'reference' => 'BPI-1004-8120', 'note' => 'Deposit received.'],
                ['at' => '2026-10-20T02:00:00Z', 'by' => 'sofia', 'sales' => 'for_refund', 'note' => 'The client cancelled the project and asked for the deposit back.'],
            ],
        ],
        [
            'reference' => 'INQ-1064',
            'client_id' => 'cl-ivan',
            'lead' => 'LEAD-0004',
            'created_at' => '2026-10-02T02:00:00Z',
            'handlers' => ['sofia'],
            'project' => [
                'name' => 'Ivan — Bar Counter',
                'service' => 'staircase',
                'stones' => [
                    ['stone_slug' => 'marble', 'quantity' => 2, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                ],
                'place' => 'Taguig',
                'answers' => ['area_sqm' => 5],
                'deadline_date' => '2026-12-15',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [],
            ],
            // Paid in full, then a refund requested; accounting has refunded
            // part of it so far.
            'shows' => 'for_refund',
            'events' => [
                ['at' => '2026-10-03T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '80000.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-10-03T02:01:00Z', 'by' => 'sofia', 'sales' => 'won', 'job_order' => 'JO-2048', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-10-04T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent.'],
                ['at' => '2026-10-05T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'fully_paid', 'payment' => '80000.00', 'reference' => 'BPI-1005-8201', 'note' => 'Payment received in full.'],
                ['at' => '2026-10-07T02:00:00Z', 'by' => 'tomas', 'process' => 'measure'],
                ['at' => '2026-10-22T02:00:00Z', 'by' => 'sofia', 'sales' => 'for_refund', 'note' => 'The client moved abroad and cancelled; refund requested.'],
                ['at' => '2026-10-25T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'partially_refunded', 'refund' => '30000.00', 'reference' => 'BPI-1025-RF01', 'note' => 'First part of the refund sent.'],
            ],
        ],
        [
            'reference' => 'INQ-1065',
            'client_id' => 'cl-nadine',
            'lead' => 'LEAD-0005',
            'created_at' => '2026-10-03T02:00:00Z',
            'handlers' => ['sofia'],
            'project' => [
                'name' => 'Nadine — Vanity Top',
                'service' => 'lavatories',
                'stones' => [
                    ['stone_slug' => 'granite', 'quantity' => 1, 'edge' => 'Straight Edge', 'finish' => 'Polished'],
                ],
                'place' => 'Pasig',
                'answers' => ['area_sqm' => 5],
                'deadline_date' => '2026-12-15',
                'deadline_time' => '10:00',
                'notes' => null,
                'attachments' => [],
            ],
            // Part-paid, refund requested, and refunded in two parts, which
            // cancelled it.
            'shows' => 'cancelled',
            'events' => [
                ['at' => '2026-10-04T02:00:00Z', 'by' => 'sofia', 'sales' => 'quoted', 'quote' => '60000.00', 'note' => 'Sent the quotation to the client.'],
                ['at' => '2026-10-04T02:01:00Z', 'by' => 'sofia', 'sales' => 'won', 'job_order' => 'JO-2049', 'note' => 'The client approved the quotation in the group chat.'],
                ['at' => '2026-10-05T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'awaiting', 'note' => 'Billing statement sent for the deposit.'],
                ['at' => '2026-10-06T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'partial', 'payment' => '30000.00', 'reference' => 'GCASH-1006-2290', 'note' => 'Deposit received.'],
                ['at' => '2026-10-15T02:00:00Z', 'by' => 'sofia', 'sales' => 'for_refund', 'note' => 'The client found the stone elsewhere and cancelled.'],
                ['at' => '2026-10-17T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'partially_refunded', 'refund' => '10000.00', 'reference' => 'GCASH-1017-RF01', 'note' => 'First part of the refund sent.'],
                ['at' => '2026-10-20T02:00:00Z', 'by' => 'accounting', 'payment_status' => 'refunded', 'refund' => '20000.00', 'reference' => 'GCASH-1020-RF02', 'note' => 'Refund settled in full.'],
            ],
        ],
    ],
];
