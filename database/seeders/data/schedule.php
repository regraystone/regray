<?php

/*
 * Booking-style visits for the sample client (ScheduleSeeder).
 *
 * - `by`: who booked it, the client or marketing (marketing@example.com).
 * - `status`: pending (waiting for marketing to assign it), awaiting_sales,
 *   confirmed, served or cancelled.
 * - `sales`: which sales member it is assigned to, as an index into the sales
 *   accounts (0 is sales@example.com); null for one nobody has been assigned.
 *   Awaiting sales, confirmed and served visits always have one.
 * - `declined`: a pending visit a sales member sent back, and why.
 */
return [
    'items' => [
        ['id' => 'apt-01', 'title' => 'Showroom visit', 'week' => 'current', 'day' => 0, 'hour' => 10, 'by' => 'client', 'status' => 'confirmed', 'sales' => 0, 'notes' => 'Morning showroom visit'],
        ['id' => 'apt-02', 'title' => 'Material selection', 'week' => 'current', 'day' => 0, 'hour' => 10, 'by' => 'client', 'status' => 'pending', 'sales' => null, 'notes' => 'Slab selection'],
        ['id' => 'apt-03', 'title' => 'Quotation review', 'week' => 'current', 'day' => 0, 'hour' => 10, 'by' => 'marketing', 'status' => 'served', 'sales' => 0, 'notes' => 'Pricing discussion'],
        ['id' => 'apt-04', 'title' => 'Site ocular', 'week' => 'current', 'day' => 1, 'hour' => 11, 'by' => 'marketing', 'status' => 'confirmed', 'sales' => 1, 'notes' => 'Site visit'],
        ['id' => 'apt-05', 'title' => 'Client follow-up', 'week' => 'current', 'day' => 1, 'hour' => 11, 'by' => 'client', 'status' => 'awaiting_sales', 'sales' => 0, 'notes' => 'Follow-up call'],
        ['id' => 'apt-06', 'title' => 'Showroom visit', 'week' => 'current', 'day' => 2, 'hour' => 14, 'by' => 'client', 'status' => 'confirmed', 'sales' => 0, 'notes' => 'Afternoon visit'],
        ['id' => 'apt-07', 'title' => 'Color matching', 'week' => 'current', 'day' => 2, 'hour' => 14, 'by' => 'client', 'status' => 'pending', 'sales' => null, 'declined' => 'I am at a site visit in Batangas all afternoon', 'notes' => 'Color review'],
        ['id' => 'apt-08', 'title' => 'Design consultation', 'week' => 'current', 'day' => 2, 'hour' => 14, 'by' => 'marketing', 'status' => 'awaiting_sales', 'sales' => 1, 'notes' => 'Layout discussion'],
        ['id' => 'apt-09', 'title' => 'Budget review', 'week' => 'current', 'day' => 3, 'hour' => 15, 'by' => 'client', 'status' => 'pending', 'sales' => null, 'notes' => 'Budget discussion'],
        ['id' => 'apt-10', 'title' => 'Delivery schedule', 'week' => 'current', 'day' => 3, 'hour' => 15, 'by' => 'client', 'status' => 'confirmed', 'sales' => 1, 'notes' => 'Scheduling'],
        ['id' => 'apt-11', 'title' => 'Sample review', 'week' => 'current', 'day' => 4, 'hour' => 10, 'by' => 'marketing', 'status' => 'confirmed', 'sales' => 0, 'notes' => 'New samples'],
        ['id' => 'apt-12', 'title' => 'Technical meeting', 'week' => 'current', 'day' => 4, 'hour' => 13, 'by' => 'client', 'status' => 'confirmed', 'sales' => 0, 'notes' => 'Technical discussion'],
        ['id' => 'apt-13', 'title' => 'Final inspection', 'week' => 'current', 'day' => 5, 'hour' => 16, 'by' => 'client', 'status' => 'confirmed', 'sales' => 1, 'notes' => 'Final check'],
        ['id' => 'apt-14', 'title' => 'Wrap-up', 'week' => 'current', 'day' => 5, 'hour' => 16, 'by' => 'marketing', 'status' => 'awaiting_sales', 'sales' => 0, 'notes' => 'Closeout'],
        ['id' => 'apt-15', 'title' => 'Handover', 'week' => 'current', 'day' => 5, 'hour' => 16, 'by' => 'client', 'status' => 'served', 'sales' => 1, 'notes' => 'Handover'],
        ['id' => 'apt-16', 'title' => 'Warranty review', 'week' => 'current', 'day' => 5, 'hour' => 16, 'by' => 'client', 'status' => 'confirmed', 'sales' => 0, 'notes' => 'Warranty'],
        ['id' => 'apt-17', 'title' => 'Feedback session', 'week' => 'current', 'day' => 5, 'hour' => 16, 'by' => 'client', 'status' => 'pending', 'sales' => null, 'notes' => 'Feedback'],
        ['id' => 'apt-18', 'title' => 'Cancelled demo', 'week' => 'current', 'day' => 1, 'hour' => 14, 'by' => 'client', 'status' => 'cancelled', 'sales' => null, 'notes' => 'Cancelled by client'],
        ['id' => 'apt-19', 'title' => 'Slab viewing', 'week' => 'next', 'day' => 1, 'hour' => 10, 'by' => 'marketing', 'status' => 'awaiting_sales', 'sales' => 1, 'notes' => 'Viewing the reserved slabs'],
        ['id' => 'apt-20', 'title' => 'Measurement visit', 'week' => 'next', 'day' => 2, 'hour' => 13, 'by' => 'client', 'status' => 'pending', 'sales' => null, 'notes' => 'Kitchen measurements'],
    ],
];
