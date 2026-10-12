// Read-only deployment checks: no form submissions, mail or application mutations.
const assert = require('node:assert/strict');
const {createHash} = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');

const base = new URL(process.argv[2] || 'https://test.spravmisluzbu.sk');
assert.equal(base.origin, 'https://test.spravmisluzbu.sk', 'This check is scoped to beta.');
const root = path.resolve(__dirname, '..');

async function get(route, contentType) {
    const response = await fetch(new URL(route, base), {
        headers: {'Cache-Control': 'no-cache'},
        redirect: 'error',
        signal: AbortSignal.timeout(30000),
    });
    assert.equal(response.status, 200, `${route} must return HTTP 200`);
    assert.match(response.headers.get('content-type') || '', contentType, `${route} content type`);
    return Buffer.from(await response.arrayBuffer());
}

async function main() {
    const home = (await get('/', /text\/html/i)).toString('utf8');
    const links = [...home.matchAll(/<a\b[^>]*\bhref="([^"]+)"[^>]*>/gi)];
    assert.ok(links.filter(([, href]) => new URL(href, base).pathname === '/demand/create').length >= 3,
        'Header, hero and footer must link to the demand form; purge stale Latte templates.');
    const form = (await get('/demand/create', /text\/html/i)).toString('utf8');
    assert.match(form, /id="frm-demandForm"/, 'Demand form must render.');
    assert.match(form, /name="description"/, 'Demand description must render.');

    for (const file of ['front.bundle.js', 'front.bundle.css', 'admin.bundle.js', 'admin.bundle.css']) {
        const remote = await get(`/bundle/${file}`, file.endsWith('.css') ? /text\/css/i : /(?:javascript|ecmascript)/i);
        const local = fs.readFileSync(path.join(root, 'www/bundle', file));
        const hash = data => createHash('sha256').update(data).digest('hex');
        assert.equal(hash(remote), hash(local), `${file} must match this build, not an older deployment`);
    }
    console.log('Beta demand navigation/form and all four deployed bundle hashes verified.');
}

main().catch(error => {
    console.error(error.message);
    process.exitCode = 1;
});
