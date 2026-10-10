import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import {
    buildLibrariesUrl,
    buildNotebooksUrl,
    buildProjectsUrl,
    buildSectionAssignmentBody,
    buildTodoUrl,
    formatSectionLabel,
    getErrorMessage,
    isValidId,
    labelForReviewReason,
    LIBRARY_PAGE_SIZE,
    NOTEBOOK_PAGE_SIZE,
    parseSectionOptionValue,
    PROJECT_PAGE_SIZE,
    requestApi,
    sectionOptionValue,
    SECTION_SOURCE_LIMIT,
    setText,
    TODO_PAGE_SIZE,
} from '../../webroot/js/app.mjs';

test('ToDo listing URL includes pagination and omits empty filters', () => {
    const url = new URL(buildTodoUrl(), 'https://example.test');

    assert.equal(url.pathname, '/api/todos');
    assert.equal(url.searchParams.get('page'), '1');
    assert.equal(url.searchParams.get('limit'), String(TODO_PAGE_SIZE));
    assert.equal(url.searchParams.has('q'), false);
    assert.equal(url.searchParams.has('status'), false);
    assert.equal(url.searchParams.has('tag'), false);
});

test('ToDo listing URL encodes search and carries selected filters', () => {
    const url = new URL(buildTodoUrl({
        query: '  plan & review  ',
        status: 'active',
        tagId: '27',
        page: 3,
    }), 'https://example.test');

    assert.equal(url.searchParams.get('q'), 'plan & review');
    assert.equal(url.searchParams.get('status'), 'active');
    assert.equal(url.searchParams.get('tag'), '27');
    assert.equal(url.searchParams.get('page'), '3');
});

test('API errors use the established message or a status fallback', () => {
    assert.equal(getErrorMessage({ error: { message: 'Not allowed.' } }, 403), 'Not allowed.');
    assert.equal(
        getErrorMessage({ data: { message: 'not an error' } }, 503),
        'The request could not be completed (503).',
    );
});

test('resource IDs reject malformed and unsafe values', () => {
    for (const id of [1, '2', Number.MAX_SAFE_INTEGER]) {
        assert.equal(isValidId(id), true);
    }
    for (const id of [0, -1, true, ' 2', '1 OR 1=1', '9007199254740992', null, undefined]) {
        assert.equal(isValidId(id), false);
    }
});

test('review reason codes are presented as readable labels', () => {
    assert.equal(labelForReviewReason('no_section'), 'Not in a Project or Notebook section');
    assert.equal(labelForReviewReason('review_overdue'), 'Review is overdue');
    assert.equal(labelForReviewReason('future_rule'), 'future rule');
});

test('user-provided text is assigned as text, never parsed as markup', () => {
    const element = { textContent: '' };
    const hostile = '<img src=x onerror=alert(1)>';
    setText(element, hostile);

    assert.equal(element.textContent, hostile);
});

test('API client unwraps success data and uses same-origin session requests', async () => {
    const originalFetch = globalThis.fetch;
    let captured;
    globalThis.fetch = async (path, options) => {
        captured = { path, options };
        return {
            ok: true,
            json: async () => ({ data: { saved: true }, meta: { pagination: { total: 1 } } }),
        };
    };
    try {
        assert.deepEqual(await requestApi('/api/todos', {
            method: 'POST',
            body: JSON.stringify({ title: 'Write tests' }),
        }), { saved: true, meta: { pagination: { total: 1 } } });
        assert.equal(captured.path, '/api/todos');
        assert.equal(captured.options.credentials, 'same-origin');
        assert.equal(captured.options.headers.Accept, 'application/json');
        assert.equal(captured.options.headers['Content-Type'], 'application/json');
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test('API client propagates safe API errors and their status', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async () => ({
        ok: false,
        status: 401,
        json: async () => ({ error: { message: 'Authentication is required.' } }),
    });
    try {
        await assert.rejects(
            requestApi('/api/auth/me'),
            (error) => error.status === 401 && error.message === 'Authentication is required.',
        );
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test('client has no unsafe HTML sinks or persistent credential storage', async () => {
    const source = await readFile(new URL('../../webroot/js/app.mjs', import.meta.url), 'utf8');

    assert.doesNotMatch(source, /\.innerHTML|\.outerHTML|insertAdjacentHTML/);
    assert.doesNotMatch(source, /localStorage|sessionStorage/);
    assert.match(source, /credentials:\s*'same-origin'/);
});

test('pagination requests never fall below the first page', () => {
    for (const page of [0, -3, Number.NaN]) {
        const url = new URL(buildTodoUrl({ page }), 'https://example.test');

        assert.equal(url.searchParams.get('page'), '1');
    }
});

test('an unreadable error body still produces a readable message', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async () => ({
        ok: false,
        status: 500,
        json: async () => {
            throw new SyntaxError('Unexpected token < in JSON');
        },
    });
    try {
        await assert.rejects(
            requestApi('/api/todos'),
            (error) => error.status === 500
                && error.message === 'The request could not be completed (500).',
        );
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test('an unreachable server is reported as a connection problem, not a crash', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async () => {
        throw new TypeError('Failed to fetch');
    };
    try {
        await assert.rejects(
            requestApi('/api/todos'),
            (error) => error.status === undefined
                && error.message === 'The server could not be reached. Check your connection and try again.',
        );
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test('a 401 response is distinguishable so the client can return to sign-in', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async () => ({
        ok: false,
        status: 401,
        json: async () => ({ error: { code: 'unauthenticated', message: 'Authentication is required.' } }),
    });
    try {
        await requestApi('/api/dashboard');
        assert.fail('expected a rejection');
    } catch (error) {
        assert.equal(error.status, 401);
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test('empty collections are returned as data, not as a missing response', async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = async () => ({
        ok: true,
        json: async () => ({ data: { items: [] }, meta: { pagination: { total: 0, page: 1 } } }),
    });
    try {
        const data = await requestApi('/api/todos');

        assert.deepEqual(data.items, []);
        assert.equal(data.meta.pagination.total, 0);
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test('Project listing URL pages at the client page size', () => {
    const url = new URL(buildProjectsUrl(), 'https://example.test');

    assert.equal(url.pathname, '/api/projects');
    assert.equal(url.searchParams.get('page'), '1');
    assert.equal(url.searchParams.get('limit'), String(PROJECT_PAGE_SIZE));
    assert.equal(
        new URL(buildProjectsUrl({ page: 4 }), 'https://example.test').searchParams.get('page'),
        '4',
    );
});

test('Project listing URL clamps pages and limits to what the API accepts', () => {
    for (const page of [0, -2, Number.NaN]) {
        const url = new URL(buildProjectsUrl({ page }), 'https://example.test');

        assert.equal(url.searchParams.get('page'), '1');
    }

    const overLimit = new URL(buildProjectsUrl({ limit: 5000 }), 'https://example.test');
    assert.equal(overLimit.searchParams.get('limit'), String(SECTION_SOURCE_LIMIT));

    const underLimit = new URL(buildProjectsUrl({ limit: 0 }), 'https://example.test');
    assert.equal(underLimit.searchParams.get('limit'), '1');
});

test('a section is labelled with the Project or Notebook it belongs to', () => {
    assert.equal(formatSectionLabel('Kitchen rebuild', 'Waiting on quotes'), 'Kitchen rebuild · Waiting on quotes');
    assert.equal(formatSectionLabel('  Kitchen  ', '  Quotes  '), 'Kitchen · Quotes');
    assert.equal(formatSectionLabel(null, 'Quotes'), 'Quotes');
    assert.equal(formatSectionLabel('Kitchen', ''), 'Kitchen');
    assert.equal(formatSectionLabel(undefined, undefined), 'Untitled section');
});

test('Notebook and Library listings page at their client page sizes', () => {
    const notebooks = new URL(buildNotebooksUrl(), 'https://example.test');
    assert.equal(notebooks.pathname, '/api/notebooks');
    assert.equal(notebooks.searchParams.get('page'), '1');
    assert.equal(notebooks.searchParams.get('limit'), String(NOTEBOOK_PAGE_SIZE));

    const libraries = new URL(buildLibrariesUrl({ page: 3 }), 'https://example.test');
    assert.equal(libraries.pathname, '/api/libraries');
    assert.equal(libraries.searchParams.get('page'), '3');
    assert.equal(libraries.searchParams.get('limit'), String(LIBRARY_PAGE_SIZE));
});

test('Notebook and Library listings clamp pages and limits like Projects do', () => {
    for (const build of [buildNotebooksUrl, buildLibrariesUrl]) {
        for (const page of [0, -5, Number.NaN]) {
            const url = new URL(build({ page }), 'https://example.test');

            assert.equal(url.searchParams.get('page'), '1');
        }
        assert.equal(
            new URL(build({ limit: 5000 }), 'https://example.test').searchParams.get('limit'),
            String(SECTION_SOURCE_LIMIT),
        );
        assert.equal(
            new URL(build({ limit: 0 }), 'https://example.test').searchParams.get('limit'),
            '1',
        );
    }
});

test('a section picker value carries the kind of section that was chosen', () => {
    assert.equal(sectionOptionValue('project', 12), 'project:12');
    assert.equal(sectionOptionValue('notebook', '7'), 'notebook:7');
    assert.equal(sectionOptionValue('library', 7), '');
    assert.equal(sectionOptionValue('project', 0), '');
    assert.equal(sectionOptionValue('notebook', '1 OR 1=1'), '');

    assert.deepEqual(parseSectionOptionValue('project:12'), { kind: 'project', id: 12 });
    assert.deepEqual(parseSectionOptionValue('notebook:7'), { kind: 'notebook', id: 7 });
    for (const value of ['', 'project:', 'project:abc', 'tag:1', '12', null, undefined]) {
        assert.equal(parseSectionOptionValue(value), null);
    }
});

test('moving a ToDo always sends both section fields so only one assignment survives', () => {
    assert.deepEqual(buildSectionAssignmentBody('project:12'), {
        project_section_id: 12,
        notebook_section_id: null,
    });
    assert.deepEqual(buildSectionAssignmentBody('notebook:7'), {
        project_section_id: null,
        notebook_section_id: 7,
    });
    assert.deepEqual(buildSectionAssignmentBody(''), {
        project_section_id: null,
        notebook_section_id: null,
    });
});
