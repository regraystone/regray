export function storageUrl(path: string): string {
    const baseUrl = import.meta.env.VITE_ASSET_URL || '/storage';

    return `${baseUrl.replace(/\/+$/, '')}/${path.replace(/^\/+/, '')}`;
}
