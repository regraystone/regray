export const STAGGER_ASPECTS = ['aspect-[3/4]', 'aspect-[4/3]'] as const;

export type StaggeredMode = 'two-column' | 'three-column';

export const STAGGERED_COLUMN_COUNT: Record<StaggeredMode, number> = {
    'two-column': 2,
    'three-column': 3,
};

export function getStaggeredAspect(index: number, columns: number): string {
    const column = index % columns;
    const rowInColumn = Math.floor(index / columns);

    return STAGGER_ASPECTS[(column + rowInColumn) % 2];
}

export function withStaggeredAspects<T>(
    items: T[],
    columns: number,
): Array<Array<T & { aspect: string }>> {
    const groups: Array<Array<T & { aspect: string }>> = Array.from(
        { length: columns },
        () => [],
    );

    items.forEach((item, index) => {
        groups[index % columns].push({
            ...item,
            aspect: getStaggeredAspect(index, columns),
        });
    });

    return groups;
}
