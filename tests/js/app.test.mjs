import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import {
    buildTodoUrl,
    getErrorMessage,
    isValidId,
    labelForReviewReason,
    requestApi,
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
