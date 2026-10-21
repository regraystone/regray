import { stopProcess } from './stack';

export default async function globalTeardown(): Promise<void> {
    for (const name of ['queue', 'reverb', 'app'] as const) {
        await stopProcess(name);
    }
}
