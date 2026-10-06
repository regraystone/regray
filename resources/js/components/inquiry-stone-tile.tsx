import type { Stone } from '@/data/constant';

/**
 * One stone on a sheet the reader is checking rather than choosing: what it is,
 * how much of it, and how it is worked.
 *
 * A picture over its own four answers rather than a row in a list, because the
 * material is the thing a client recognises and the specifications are the thing
 * they are checking: giving the picture the whole width of the tile and putting
 * the answers under it says which is which without either of them being shrunk to
 * fit beside the other.
 *
 * Name and count share a line, pushed to the two ends, because that pair is one
 * fact — how much of this material — and reading them together is what the reader
 * is checking first.
 *
 * The edge and the finish are given as their bare values, with no "Edge profile"
 * or "Finish" above them. The labels restate the question on a sheet where the
 * answer is already filled in, and every tile on the sheet carries its two lines
 * in the same order, so the edge is always the first and the finish the second.
 * The reader only has to learn that once instead of reading it nine times.
 *
 * Shared by the requirements form and the Review sheet so the same project reads
 * the same way in both, and so one of them cannot quietly drift away from the
 * other.
 */
export default function StoneTile({
    stone,
    quantity,
    edge,
    finish,
}: {
    stone: Stone;
    quantity: number;
    edge: string;
    finish: string;
}) {
    return (
        <li className="min-w-0">
            <img
                src={stone.thumbnail}
                alt=""
                loading="lazy"
                className="aspect-square w-full object-cover"
            />

            <div className="mt-3 flex items-baseline justify-between gap-x-3">
                <p className="min-w-0 truncate text-sm font-bold text-foreground">
                    {stone.name}
                </p>
                <p className="shrink-0 text-sm font-bold text-foreground">
                    {quantity} {quantity === 1 ? 'pc' : 'pcs'}
                </p>
            </div>

            <p className="mt-1.5 truncate text-sm text-foreground">{edge}</p>
            <p className="mt-1 truncate text-sm text-foreground">{finish}</p>
        </li>
    );
}
