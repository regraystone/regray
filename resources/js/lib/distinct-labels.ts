/**
 * A list's labels made so no two read the same: a label that repeats
 * (ignoring case) is followed by its place among those that share it,
 * "Honed (1)", "Honed (2)". The Combobox finds an option by the text chosen,
 * so two options with the same label would otherwise both choose the first.
 *
 * The labels come back in the options' order, one for each.
 */
export const distinctLabels = <T>(
    options: readonly T[],
    label: (option: T) => string,
): string[] => {
    const key = (text: string) => text.trim().toLowerCase();
    const total = new Map<string, number>();

    for (const option of options) {
        const k = key(label(option));
        total.set(k, (total.get(k) ?? 0) + 1);
    }

    const seen = new Map<string, number>();

    return options.map((option) => {
        const text = label(option);
        const k = key(text);

        if ((total.get(k) ?? 0) < 2) {
            return text;
        }

        const place = (seen.get(k) ?? 0) + 1;
        seen.set(k, place);

        return `${text} (${place})`;
    });
};
