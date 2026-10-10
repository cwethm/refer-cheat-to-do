import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import {
    ACTIVITY_PAGE_SIZE,
    buildActivityUrl,
    buildLibrariesUrl,
    buildNotebooksUrl,
    buildProjectsUrl,
    buildRelatedTodoUrl,
    buildSectionAssignmentBody,
    buildSnoozeBody,
    buildTodoActionUrl,
    buildTodoUrl,
    formatActivityEntry,
    formatActivityTimestamp,
    formatSectionLabel,
    getErrorMessage,
    isValidId,
    labelForActivityAction,
    labelForReviewReason,
    LIBRARY_PAGE_SIZE,
    NOTEBOOK_PAGE_SIZE,
    parseSectionOptionValue,
    PROJECT_PAGE_SIZE,
    requestApi,
    requiresConfirmation,
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

test('lifecycle and relationship URLs are built only from known actions and valid ids', () => {
    assert.equal(buildTodoActionUrl(12, 'archive'), '/api/todos/12/archive');
    assert.equal(buildTodoActionUrl('12', 'permanent'), '/api/todos/12/permanent');
    assert.equal(buildTodoActionUrl(12, 'hierarchy'), '/api/todos/12/hierarchy');

    for (const [id, action] of [
        [12, 'drop-database'],
        [12, '../../auth/logout'],
        [0, 'archive'],
        ['1 OR 1=1', 'archive'],
        [null, 'archive'],
        [12, ''],
    ]) {
        assert.equal(buildTodoActionUrl(id, action), '');
    }
});

test('a relationship URL refuses malformed ids and self-linking', () => {
    assert.equal(buildRelatedTodoUrl(4, 9), '/api/todos/4/related/9');
    assert.equal(buildRelatedTodoUrl('4', '9'), '/api/todos/4/related/9');
    assert.equal(buildRelatedTodoUrl(4, 4), '');
    assert.equal(buildRelatedTodoUrl(4, ''), '');
    assert.equal(buildRelatedTodoUrl('', 9), '');
    assert.equal(buildRelatedTodoUrl(4, '9; DROP TABLE todos'), '');
});

test('only permanent deletion is treated as needing confirmation', () => {
    assert.equal(requiresConfirmation('permanent'), true);
    for (const action of ['archive', 'trash', 'restore', 'complete', '', undefined]) {
        assert.equal(requiresConfirmation(action), false);
    }
});

test('a snooze always asks for at least one whole day', () => {
    assert.deepEqual(buildSnoozeBody('14'), { days: 14 });
    assert.deepEqual(buildSnoozeBody(3.7), { days: 3 });
    for (const value of ['', 0, -5, 'soon', null, undefined]) {
        assert.deepEqual(buildSnoozeBody(value), { days: 1 });
    }
});

test('the activity listing URL pages at the client page size and clamps requests', () => {
    const url = new URL(buildActivityUrl(), 'https://example.test');

    assert.equal(url.pathname, '/api/activity');
    assert.equal(url.searchParams.get('page'), '1');
    assert.equal(url.searchParams.get('limit'), String(ACTIVITY_PAGE_SIZE));
    assert.equal(
        new URL(buildActivityUrl({ page: 0 }), 'https://example.test').searchParams.get('page'),
        '1',
    );
    assert.equal(
        new URL(buildActivityUrl({ limit: 5000 }), 'https://example.test').searchParams.get('limit'),
        String(SECTION_SOURCE_LIMIT),
    );
});

test('activity actions are presented as readable sentences', () => {
    assert.equal(labelForActivityAction('todo.created'), 'Created a ToDo');
    assert.equal(labelForActivityAction('todo.trashed'), 'Moved a ToDo to the trash');
    assert.equal(labelForActivityAction('todo.tag_attached'), 'Added a tag to a ToDo');
    assert.equal(labelForActivityAction('widget.future_action'), 'widget future action');
    assert.equal(labelForActivityAction(undefined), '');
});

test('activity timestamps are rendered unambiguously and never as a broken date', () => {
    assert.equal(formatActivityTimestamp('2026-10-10T15:37:47+00:00'), '2026-10-10 15:37 UTC');
    for (const value of ['', 'not a date', null, undefined, 42]) {
        assert.equal(formatActivityTimestamp(value), '');
    }
});

test('an activity entry formats its action, subject and moment', () => {
    assert.deepEqual(formatActivityEntry({
        action: 'todo.archived',
        subject_type: 'todo',
        subject_id: 12,
        created: '2026-10-10T15:37:47+00:00',
    }), {
        action: 'Archived a ToDo',
        subject: 'ToDo #12',
        when: '2026-10-10 15:37 UTC',
    });

    assert.deepEqual(formatActivityEntry({
        action: 'request.sent',
        subject_type: 'cross_context_request',
        subject_id: '4',
        created: null,
    }), {
        action: 'Sent a request',
        subject: 'Request #4',
        when: '',
    });

    assert.deepEqual(formatActivityEntry({}), { action: '', subject: '', when: '' });
});
