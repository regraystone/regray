/** The letters a card without an image shows: "Cucine Lube" is "CL", "Ginza One" is "GO". */
export const initials = (name: string): string =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase() ?? '')
        .join('');
