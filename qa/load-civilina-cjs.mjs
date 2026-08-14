import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const directory = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../resources/js/civilina-ai');
const cache = new Map();

export function loadCivilina(file) {
    const absolutePath = path.resolve(directory, file);
    if (!absolutePath.startsWith(directory) || !fs.existsSync(absolutePath)) {
        throw new Error(`Unknown CIVILINA module: ${file}`);
    }
    if (cache.has(absolutePath)) {
        return cache.get(absolutePath).exports;
    }

    const module = { exports: {} };
    cache.set(absolutePath, module);
    const source = fs.readFileSync(absolutePath, 'utf8');
    const localRequire = request => loadCivilina(path.relative(directory, path.resolve(path.dirname(absolutePath), request)));
    new Function('module', 'exports', 'require', source)(module, module.exports, localRequire);

    return module.exports;
}
