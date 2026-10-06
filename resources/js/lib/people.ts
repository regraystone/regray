import { people, type Person } from '@/data/conversations';

export function getPerson(id: string): Person | undefined {
    return people.find((person) => person.id === id);
}

export function getFirstName(id: string): string {
    const name = getPerson(id)?.name.trim();

    if (!name) {
        return '';
    }

    return name.split(/\s+/u)[0];
}
