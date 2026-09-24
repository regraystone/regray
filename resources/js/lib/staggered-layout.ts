export const STAGGER_ASPECTS = ['aspect-[3/4]', 'aspect-[4/3]'] as const;

export const DESKTOP_ASPECTS = [
    'aspect-[3/4]',
    'aspect-[4/3]',
    'aspect-[4/5]',
    'aspect-[4/3]',
] as const;

export type StaggeredMode = 'two-column' | 'three-column';

export function getStaggeredAspect(
    index: number,
    total: number,
    mode: StaggeredMode = 'two-column',
): string {
    if (mode === 'three-column') {
        return DESKTOP_ASPECTS[index % DESKTOP_ASPECTS.length];
    }

    const half = Math.floor(total / 2);
    const phase = index >= half ? (index - half + 1) % 2 : index % 2;

    return STAGGER_ASPECTS[phase];
}

export function withStaggeredAspect<T>(
    items: T[],
    mode: StaggeredMode = 'two-column',
): Array<T & { aspect: string }> {
    return items.map((item, index) => ({
        ...item,
        aspect: getStaggeredAspect(index, items.length, mode),
    }));
}
