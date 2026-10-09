import { type AgendaStatus } from '@/data/agenda';

export const currentUserId = 'me';
const hero = '/images/regray-hero.jpg';
export type Person = {
    id: string;
    name: string;
    role: string;
    avatar?: string;
};

export type ChatMessage =
    | {
          id: string;
          senderId: string;
          text: string;
          sentAt: string;
          kind?: 'text';
      }
    | {
          id: string;
          kind: 'agenda';
          sentAt: string;
          agendaId: string;
          status: AgendaStatus;
          bookedBy: 'client' | 'staff';
          senderId?: string;
      };

export type Conversation = {
    id: string;
    type: 'direct' | 'group';
    name: string;
    avatar?: string;
    unread: boolean;
    memberIds: string[];
    messages: ChatMessage[];
    /** The inquiry this conversation was opened for, and the thread's first card. */
    inquiryId?: string;
};

/**
 * Everyone the business has on the books.
 *
 * The four who pick up inquiries are titled by the officer role they hold rather
 * than by what they used to do, because that is the only thing a staff member
 * reading an inquiry needs to know about who has it.
 */
export const people: Person[] = [
    { id: 'maria', name: 'Maria Alvarez', role: 'Sales Officer' },
    { id: 'daniel', name: 'Daniel Okafor', role: 'Technical Officer' },
    { id: 'sofia', name: 'Sofia Bianchi', role: 'Sales Officer' },
    { id: 'kenji', name: 'Kenji Watanabe', role: 'Procurement Lead' },
    { id: 'priya', name: 'Priya Raman', role: 'Technical Officer' },
    { id: 'lucas', name: 'Lucas Moreau', role: 'Kitchen Studio Owner' },
    { id: 'amara', name: 'Amara Nwosu', role: 'Sales Director' },
    { id: 'tomas', name: 'Tomas Lindqvist', role: 'Logistics Coordinator' },
    {
        id: 'beatriz',
        name: 'Beatriz Vasconcelos Cummings',
        role: 'Architectural Stone Specialist',
    },
    {
        id: 'hassan',
        name: 'Hassan Al-Farsi',
        role: 'Facade & Cladding Advisor',
    },
];

export const conversations: Conversation[] = [
    {
        id: 'chat-inq-1048',
        type: 'group',
        name: 'Northgate Cafe — Counters',
        unread: true,
        memberIds: ['maria', 'daniel'],
        messages: [
            {
                id: 'ag-1',
                kind: 'agenda',
                sentAt: '2026-09-28T10:00:00.000Z',
                agendaId: 'apt-04',
                status: 'requested',
                bookedBy: 'client',
            },
            {
                id: 'm-a',
                senderId: 'maria',
                text: 'The site visit request has been received. Let me coordinate with Daniel for the best time.',
                sentAt: '2026-09-28T10:15:00.000Z',
            },
            {
                id: 'ag-2',
                kind: 'agenda',
                sentAt: '2026-09-28T11:30:00.000Z',
                agendaId: 'apt-04',
                status: 'confirmed',
                bookedBy: 'client',
                senderId: 'maria',
            },
            {
                id: 'm-b',
                senderId: 'daniel',
                text: 'Perfect. I will bring the site assessment checklist and samples.',
                sentAt: '2026-09-28T11:45:00.000Z',
            },
        ],
        inquiryId: 'inq-1048',
    },
    {
        id: 'chat-maria',
        type: 'direct',
        name: 'Maria Alvarez',
        unread: false,
        memberIds: ['maria'],
        messages: [
            {
                id: 'm-1',
                senderId: 'maria',
                text: 'The Patagonia quartzite samples you approved are ready to collect.',
                sentAt: '2026-09-28T09:12:00.000Z',
            },
            {
                id: 'm-2',
                senderId: 'me',
                text: 'Great — I will pick them up Thursday afternoon.',
                sentAt: '2026-09-28T09:14:00.000Z',
            },
            {
                id: 'm-3',
                senderId: 'maria',
                text: 'Perfect. I will have them wrapped and labelled for the kitchen.',
                sentAt: '2026-09-28T09:15:00.000Z',
            },
            {
                id: 'm-4',
                senderId: 'me',
                text: 'Thank you Maria!',
                sentAt: '2026-09-28T09:16:00.000Z',
            },
        ],
    },
    {
        id: 'chat-supply',
        type: 'group',
        name: 'Supply Chain & Global Freight Coordination',
        unread: true,
        memberIds: ['daniel', 'kenji', 'tomas'],
        messages: [
            {
                id: 'm-5',
                senderId: 'daniel',
                text: 'Booking the vessel slot for the Italian marble this week.',
                sentAt: '2026-09-27T15:40:00.000Z',
            },
            {
                id: 'm-6',
                senderId: 'tomas',
                text: 'Port congestion is still high, expect four extra days on arrival.',
                sentAt: '2026-09-27T15:52:00.000Z',
            },
            {
                id: 'm-7',
                senderId: 'kenji',
                text: 'Then we shift the showroom install to the following Monday.',
                sentAt: '2026-09-27T16:05:00.000Z',
            },
        ],
    },
    {
        id: 'chat-daniel',
        type: 'direct',
        name: 'Daniel Okafor',
        unread: true,
        memberIds: ['daniel'],
        messages: [
            {
                id: 'm-8',
                senderId: 'daniel',
                text: 'Quote for the tower lobby is attached for your review.',
                sentAt: '2026-09-27T11:20:00.000Z',
            },
            {
                id: 'm-9',
                senderId: 'me',
                text: 'Got it, reviewing the slab quantities now.',
                sentAt: '2026-09-27T11:31:00.000Z',
            },
            {
                id: 'm-10',
                senderId: 'daniel',
                text: 'Anything unclear, send it over.',
                sentAt: '2026-09-27T11:33:00.000Z',
            },
        ],
    },
    {
        id: 'chat-kitchen',
        type: 'group',
        name: 'Granite Kitchen Project',
        avatar: hero,
        unread: false,
        memberIds: ['sofia', 'lucas', 'maria'],
        messages: [
            {
                id: 'ag-5',
                kind: 'agenda',
                sentAt: '2026-09-26T08:00:00.000Z',
                agendaId: 'apt-18',
                status: 'cancelled',
                bookedBy: 'client',
            },
            {
                id: 'm-11',
                senderId: 'sofia',
                text: 'Uploaded the revised kitchen elevation with the waterfall island.',
                sentAt: '2026-09-26T08:45:00.000Z',
            },
            {
                id: 'm-12',
                senderId: 'lucas',
                text: 'Looking good. Can we thicken the edge profile to 40 mm?',
                sentAt: '2026-09-26T09:10:00.000Z',
            },
            {
                id: 'm-13',
                senderId: 'me',
                text: 'Yes, that is within our standard fabrication range.',
                sentAt: '2026-09-26T09:18:00.000Z',
            },
            {
                id: 'm-14',
                senderId: 'maria',
                text: 'I will update the quote to reflect the thicker edge.',
                sentAt: '2026-09-26T09:24:00.000Z',
            },
            {
                id: 'm-15',
                senderId: 'sofia',
                text: 'Thanks Maria. Lucas, we can confirm on our end.',
                sentAt: '2026-09-26T09:30:00.000Z',
            },
        ],
    },
    {
        id: 'chat-sofia',
        type: 'direct',
        name: 'Sofia Bianchi',
        unread: false,
        memberIds: ['sofia'],
        inquiryId: 'inq-1044',
        messages: [
            {
                id: 'ag-3',
                kind: 'agenda',
                sentAt: '2026-09-25T13:00:00.000Z',
                agendaId: 'apt-06',
                status: 'confirmed',
                bookedBy: 'staff',
                senderId: 'sofia',
            },
            {
                id: 'ag-4',
                kind: 'agenda',
                sentAt: '2026-09-26T09:00:00.000Z',
                agendaId: 'apt-03',
                status: 'completed',
                bookedBy: 'staff',
                senderId: 'sofia',
            },
            {
                id: 'm-16',
                senderId: 'me',
                text: 'Do you have the honed finish samples for the bathroom?',
                sentAt: '2026-09-25T14:02:00.000Z',
            },
            {
                id: 'm-17',
                senderId: 'sofia',
                text: 'Yes, sending a courier over tomorrow morning.',
                sentAt: '2026-09-25T14:09:00.000Z',
            },
        ],
    },
    {
        id: 'chat-facade',
        type: 'group',
        name: 'Facade Cladding Coordination, Tower B Phase Two, Harbourfront Development',
        unread: true,
        memberIds: ['hassan', 'beatriz', 'priya', 'daniel'],
        messages: [],
    },
    {
        id: 'chat-kenji',
        type: 'direct',
        name: 'Kenji Watanabe',
        unread: false,
        memberIds: ['kenji'],
        messages: [
            {
                id: 'm-18',
                senderId: 'kenji',
                text: 'Can you hold the onyx allocation until Friday?',
                sentAt: '2026-09-24T10:15:00.000Z',
            },
            {
                id: 'm-19',
                senderId: 'me',
                text: 'Held. I will confirm the balance once the deposit clears.',
                sentAt: '2026-09-24T10:22:00.000Z',
            },
        ],
    },
    {
        id: 'chat-priya',
        type: 'direct',
        name: 'Priya Raman',
        unread: true,
        memberIds: ['priya'],
        messages: [
            {
                id: 'm-20',
                senderId: 'priya',
                text: 'Structural report for the cantilevered counter is ready for sign-off.',
                sentAt: '2026-09-23T16:48:00.000Z',
            },
        ],
    },
];
