import { addDays, atHour, startOfWeek, weekdayIndex } from '@/lib/date';

/**
 * Where a booked visit has got to.
 */
export type AgendaStatus =
    | 'requested'
    | 'confirmed'
    | 'completed'
    | 'cancelled';

/**
 * One booked visit.
 */
export type AgendaItem = {
    id: string;
    title: string;
    /**
     * The time the client needs to be there.
     */
    arrival: Date;
    status: AgendaStatus;
    notes: string;
};

type MockPlacement = {
    week: 'current' | 'next';
    day: number;
    hour: number;
};

type MockItem = Omit<AgendaItem, 'arrival'> & MockPlacement;

const MOCK_ITEMS: MockItem[] = [
    {
        id: 'apt-01',
        title: 'Showroom visit',
        week: 'current',
        day: 0,
        hour: 10,
        status: 'confirmed',
        notes: 'Morning showroom visit',
    },
    {
        id: 'apt-02',
        title: 'Material selection',
        week: 'current',
        day: 0,
        hour: 10,
        status: 'requested',
        notes: 'Slab selection',
    },
    {
        id: 'apt-03',
        title: 'Quotation review',
        week: 'current',
        day: 0,
        hour: 10,
        status: 'completed',
        notes: 'Pricing discussion',
    },
    {
        id: 'apt-04',
        title: 'Site ocular',
        week: 'current',
        day: 1,
        hour: 11,
        status: 'confirmed',
        notes: 'Site visit',
    },
    {
        id: 'apt-05',
        title: 'Client follow-up',
        week: 'current',
        day: 1,
        hour: 11,
        status: 'requested',
        notes: 'Follow-up call',
    },
    {
        id: 'apt-06',
        title: 'Showroom visitss',
        week: 'current',
        day: 2,
        hour: 14,
        status: 'confirmed',
        notes: 'Afternoon visit',
    },
    {
        id: 'apt-07',
        title: 'Color matching',
        week: 'current',
        day: 2,
        hour: 14,
        status: 'requested',
        notes: 'Color review',
    },
    {
        id: 'apt-08',
        title: 'Design consultation',
        week: 'current',
        day: 2,
        hour: 14,
        status: 'confirmed',
        notes: 'Layout discussion',
    },
    {
        id: 'apt-09',
        title: 'Budget review',
        week: 'current',
        day: 3,
        hour: 15,
        status: 'requested',
        notes: 'Budget discussion',
    },
    {
        id: 'apt-10',
        title: 'Delivery schedule',
        week: 'current',
        day: 3,
        hour: 15,
        status: 'confirmed',
        notes: 'Scheduling',
    },
    {
        id: 'apt-11',
        title: 'Sample review',
        week: 'current',
        day: 4,
        hour: 10,
        status: 'confirmed',
        notes: 'New samples',
    },
    {
        id: 'apt-12',
        title: 'Technical meeting',
        week: 'current',
        day: 4,
        hour: 13,
        status: 'confirmed',
        notes: 'Technical discussion',
    },
    {
        id: 'apt-13',
        title: 'Final inspection',
        week: 'current',
        day: 5,
        hour: 16,
        status: 'confirmed',
        notes: 'Final check',
    },
    {
        id: 'apt-14',
        title: 'Wrap-up',
        week: 'current',
        day: 5,
        hour: 16,
        status: 'requested',
        notes: 'Closeout',
    },
    {
        id: 'apt-15',
        title: 'Handover',
        week: 'current',
        day: 5,
        hour: 16,
        status: 'completed',
        notes: 'Handover',
    },
    {
        id: 'apt-16',
        title: 'Warranty review',
        week: 'current',
        day: 5,
        hour: 16,
        status: 'confirmed',
        notes: 'Warranty',
    },
    {
        id: 'apt-17',
        title: 'Feedback session',
        week: 'current',
        day: 5,
        hour: 16,
        status: 'requested',
        notes: 'Feedback',
    },
    {
        id: 'apt-18',
        title: 'Cancelled demo',
        week: 'current',
        day: 1,
        hour: 14,
        status: 'cancelled',
        notes: 'Cancelled by client',
    },
];

const mockDayIndex = (
    todayIndex: number,
    { week, day }: Pick<MockPlacement, 'week' | 'day'>,
): number => {
    if (week === 'next') {
        return 7 + day;
    }

    return Math.min(Math.max(todayIndex + day, 0), 6);
};

export const mockAgendaItems = (today: Date = new Date()): AgendaItem[] => {
    const monday = startOfWeek(today);
    const todayIndex = weekdayIndex(today);

    return MOCK_ITEMS.map(({ week, day, hour, ...item }) => ({
        ...item,
        arrival: atHour(
            addDays(monday, mockDayIndex(todayIndex, { week, day })),
            hour,
        ),
    }));
};
