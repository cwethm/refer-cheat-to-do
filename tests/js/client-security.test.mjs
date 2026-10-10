/**
 * Standing client security regression suite.
 *
 * Every future client slice must keep these assertions passing. They encode the three
 * invariants the web client depends on: no HTML sinks, no persisted credentials, and
 * same-origin session requests only.
 */
import assert from 'node:assert/strict';
import { readdir, readFile } from 'node:fs/promises';
import test from 'node:test';

const CLIENT_SCRIPT_DIR = new URL('../../webroot/js/', import.meta.url);
const CLIENT_TEMPLATE_DIR = new URL('../../templates/Pages/', import.meta.url);

async function readFiles(directory, extension) {
    const names = (await readdir(directory)).filter((name) => name.endsWith(extension));

    return Promise.all(names.map(async (name) => ({
        name,
        source: await readFile(new URL(name, directory), 'utf8'),
    })));
}

test('no client script uses an HTML sink', async () => {
    for (const { name, source } of await readFiles(CLIENT_SCRIPT_DIR, '.mjs')) {
        assert.doesNotMatch(
            source,
            /\.innerHTML|\.outerHTML|insertAdjacentHTML|document\.write|new Function|eval\(/,
            `${name} introduces an HTML or code-execution sink`,
        );
    }
});

test('no client script persists credentials or session state in the browser', async () => {
    for (const { name, source } of await readFiles(CLIENT_SCRIPT_DIR, '.mjs')) {
        assert.doesNotMatch(
            source,
            /localStorage|sessionStorage|indexedDB|document\.cookie/,
            `${name} persists state in the browser`,
        );
    }
});

test('every client fetch is a same-origin session request', async () => {
    for (const { name, source } of await readFiles(CLIENT_SCRIPT_DIR, '.mjs')) {
        const fetchCalls = source.match(/fetch\(/g) ?? [];
        if (fetchCalls.length === 0) {
            continue;
        }
        assert.match(source, /credentials:\s*'same-origin'/, `${name} does not pin same-origin credentials`);
        assert.doesNotMatch(source, /credentials:\s*'include'/, `${name} sends credentials cross-origin`);
        assert.doesNotMatch(
            source,
            /fetch\(\s*['"`]https?:\/\//,
            `${name} calls an absolute remote URL`,
        );
    }
});

test('client templates load no remote scripts, styles or fonts', async () => {
    for (const { name, source } of await readFiles(CLIENT_TEMPLATE_DIR, '.php')) {
        if (name !== 'app.php') {
            continue;
        }
        assert.doesNotMatch(source, /src=["']https?:\/\//, `${name} loads a remote script`);
        assert.doesNotMatch(source, /href=["']https?:\/\/(?!www\.w3\.org)/, `${name} loads a remote resource`);
        assert.doesNotMatch(source, /<script(?![^>]*src=)[^>]*>[\s\S]*?\S[\s\S]*?<\/script>/, `${name} inlines a script`);
    }
});
