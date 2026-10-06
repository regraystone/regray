/**
 * Who an inquiry came from.
 *
 * Its own list rather than a `people` entry, because a client is somebody the
 * business is quoting to rather than a member of its team: they have a company
 * and a site address, which no one on staff has.
 */
export type Client = {
    id: string;
    name: string;
    email: string;
    phone: string;
    company?: string;
    address: string;
};
/**
 * One client per sample inquiry.
 *
 * The people and their contact details are invented, and the emails use the
 * reserved `.example` domain, so nothing here can reach a real person. The
 * company and the address are on the record because a client has both, even
 * though the details page shows neither: it is a page for reading what was
 * asked for, not for who asked it.
 */
export const clients: Client[] = [
    {
        id: 'cl-elena',
        name: 'Elena Villanueva',
        email: 'elena.villanueva@brightleafdesign.example',
        phone: '+63 917 555 0142',
        company: 'Brightleaf Design Studio',
        address:
            '12 Kalachuchi Street, Barangay Commonwealth, Quezon City, 1103',
    },
    {
        id: 'cl-rafael',
        name: 'Rafael Domingo',
        email: 'rafael.doming@thenorthgatecafe.example',
        phone: '+63 2 8123 4409',
        company: 'The Northgate Cafe',
        address: 'Unit 4, Dela Paz Compound, Legazpi Village, Makati, 1229',
    },
    {
        id: 'cl-nadine',
        name: 'Nadine Delacroix',
        email: 'nadine.delacroix@lumenandco.example',
        phone: '+63 998 401 2277',
        company: 'Lumen & Co. Architects',
        address: '88 Katipunan Avenue, Barangay Juan Lopez, Quezon City, 1108',
    },
    {
        id: 'cl-ivan',
        name: 'Ivan Mercado',
        email: 'ivan.mercado@hillcrestshowroom.example',
        phone: '+63 917 220 8815',
        company: 'Hillcrest Showroom',
        address: '3 Greenwood Street, Barangay Addition Hills, San Juan, 1050',
    },
    {
        id: 'cl-haruki',
        name: 'Haruki Nakamura',
        email: 'haruki.nakamura@groveresidences.example',
        phone: '+63 917 630 3345',
        company: 'The Grove Residences',
        address: '17 Balete Drive, Barangay Ayala Alabang, Muntinlupa, 1780',
    },
    {
        id: 'cl-grace',
        name: 'Grace Lim',
        email: 'grace.lim@maplelane.example',
        phone: '+63 2 7441 9022',
        address: '5 Maple Lane, Barangay Tunay, Quezon City, 1105',
    },
];
