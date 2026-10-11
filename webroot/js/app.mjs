export const TODO_PAGE_SIZE = 50;
export const PROJECT_PAGE_SIZE = 20;
export const NOTEBOOK_PAGE_SIZE = 20;
export const LIBRARY_PAGE_SIZE = 20;
export const ACTIVITY_PAGE_SIZE = 20;
export const REFERENCE_PAGE_SIZE = 20;

/**
 * Most cheat sheets, and most cards on one sheet, fetched in a single request (the API cap).
 */
export const CHEAT_SHEET_LIMIT = 100;

/**
 * Longest search text the reference API accepts, in characters.
 */
export const REFERENCE_SEARCH_MAX_CHARS = 255;

export const REFERENCE_CARD_TYPES = {
    concept: 'Concept',
    dialogue_pattern: 'Dialogue pattern',
};

/**
 * Structured dialogue-pattern fields, in reading order, with the label each block is shown under.
 */
export const DIALOGUE_FIELDS = [
    ['situation', 'Situation'],
    ['aim', 'Aim'],
    ['approach', 'Approach'],
    ['avoid', 'Avoid'],
    ['rationale', 'Rationale'],
];

/**
 * Highest number of Projects or Notebooks whose sections are offered in a ToDo's section picker.
 *
 * The API caps `limit` at 100, so the picker covers at most the newest 100 of each.
 */
export const SECTION_SOURCE_LIMIT = 100;

/**
 * Build a paged collection URL for the owner-scoped record lists.
 *
 * Projects, Notebooks and Libraries all page the same way, so the clamping lives here
 * rather than being restated once per collection.
 */
export function buildRecordListUrl(path, { page = 1, limit = PROJECT_PAGE_SIZE } = {}) {
    const requestedPage = Number(page);
    const safePage = Number.isFinite(requestedPage) ? Math.max(1, Math.trunc(requestedPage)) : 1;
    const requestedLimit = Number(limit);
    const safeLimit = Number.isFinite(requestedLimit)
        ? Math.min(SECTION_SOURCE_LIMIT, Math.max(1, Math.trunc(requestedLimit)))
        : PROJECT_PAGE_SIZE;
    const params = new URLSearchParams({ page: String(safePage), limit: String(safeLimit) });

    return `${path}?${params.toString()}`;
}

export function buildProjectsUrl(options = {}) {
    return buildRecordListUrl('/api/projects', { limit: PROJECT_PAGE_SIZE, ...options });
}

export function buildNotebooksUrl(options = {}) {
    return buildRecordListUrl('/api/notebooks', { limit: NOTEBOOK_PAGE_SIZE, ...options });
}

export function buildLibrariesUrl(options = {}) {
    return buildRecordListUrl('/api/libraries', { limit: LIBRARY_PAGE_SIZE, ...options });
}

export function buildActivityUrl(options = {}) {
    return buildRecordListUrl('/api/activity', { limit: ACTIVITY_PAGE_SIZE, ...options });
}

/**
 * Build the reference-card search URL. Unknown types and malformed ids are dropped rather than
 * sent, and the search text is trimmed and bounded to what the API accepts.
 */
export function buildReferenceCardsUrl({ query = '', cardType = '', tagId = '', cheatSheetId = '', page = 1 } = {}) {
    const requestedPage = Number(page);
    const safePage = Number.isFinite(requestedPage) ? Math.max(1, Math.trunc(requestedPage)) : 1;
    const params = new URLSearchParams({ page: String(safePage), limit: String(REFERENCE_PAGE_SIZE) });
    const cleanQuery = Array.from(String(query ?? '').trim()).slice(0, REFERENCE_SEARCH_MAX_CHARS).join('').trim();

    if (cleanQuery) {
        params.set('q', cleanQuery);
    }
    if (typeof cardType === 'string' && Object.hasOwn(REFERENCE_CARD_TYPES, cardType)) {
        params.set('card_type', cardType);
    }
    if (isValidId(tagId)) {
        params.set('tag', String(Number(tagId)));
    }
    if (isValidId(cheatSheetId)) {
        params.set('cheat_sheet', String(Number(cheatSheetId)));
    }

    return `/api/reference-cards?${params.toString()}`;
}

/**
 * Build a create or update payload from the card form. Blank optional text is sent as `null`,
 * and a concept card always clears the dialogue-pattern fields the server reserves for patterns.
 */
export function buildReferenceCardBody(values = {}) {
    const cardType = typeof values.card_type === 'string' && Object.hasOwn(REFERENCE_CARD_TYPES, values.card_type)
        ? values.card_type
        : 'concept';
    const optional = (value) => (typeof value === 'string' && value.trim() !== '' ? value : null);
    const body = {
        card_type: cardType,
        title: typeof values.title === 'string' ? values.title.trim() : '',
        summary: optional(values.summary),
        body: optional(values.body),
    };
    for (const [field] of DIALOGUE_FIELDS) {
        body[field] = cardType === 'dialogue_pattern' ? optional(values[field]) : null;
    }

    return body;
}

/**
 * The populated dialogue-pattern fields of a card as labelled blocks; empty fields are hidden.
 */
export function dialogueBlocks(card) {
    if (card?.card_type !== 'dialogue_pattern') {
        return [];
    }

    return DIALOGUE_FIELDS
        .filter(([field]) => typeof card[field] === 'string' && card[field].trim() !== '')
        .map(([field, label]) => ({ field, label, text: card[field] }));
}

export function labelForCardType(type) {
    return typeof type === 'string' && Object.hasOwn(REFERENCE_CARD_TYPES, type)
        ? REFERENCE_CARD_TYPES[type]
        : 'Reference card';
}

/**
 * Build a cheat sheet's card-membership URL; malformed ids yield no URL at all.
 */
export function buildCheatSheetCardUrl(sheetId, cardId) {
    if (!isValidId(sheetId)) {
        return '';
    }
    const base = `/api/cheat-sheets/${Number(sheetId)}/cards`;
    if (cardId === undefined) {
        return base;
    }

    return isValidId(cardId) ? `${base}/${Number(cardId)}` : '';
}

/**
 * The position a card moves to when nudged up or down a sheet of `count` cards, or `null`
 * when the move would leave the sheet.
 */
export function nudgedPosition(position, direction, count) {
    const current = Number(position);
    const size = Number(count);
    if (!Number.isSafeInteger(current) || !Number.isSafeInteger(size) || current < 1 || current > size) {
        return null;
    }
    const target = direction === 'up' ? current - 1 : direction === 'down' ? current + 1 : current;

    return target !== current && target >= 1 && target <= size ? target : null;
}

/**
 * Sub-resources of a ToDo, and the HTTP method each one is reached with.
 *
 * The client never builds one of these paths by hand: an unknown action yields no URL at
 * all, so a typo cannot become a request against an unintended endpoint.
 */
export const TODO_ACTIONS = {
    activate: 'POST',
    complete: 'POST',
    archive: 'POST',
    trash: 'POST',
    restore: 'POST',
    reviewed: 'POST',
    snooze: 'POST',
    hierarchy: 'GET',
    parent: 'PATCH',
    objective: 'PATCH',
    result: 'POST',
    permanent: 'DELETE',
};

/**
 * Actions that destroy data irrecoverably and therefore need an explicit confirmation.
 */
export function requiresConfirmation(action) {
    return action === 'permanent';
}

export function buildTodoActionUrl(todoId, action) {
    if (!isValidId(todoId) || !Object.hasOwn(TODO_ACTIONS, action)) {
        return '';
    }

    return `/api/todos/${Number(todoId)}/${action}`;
}

export function buildRelatedTodoUrl(todoId, relatedId) {
    if (!isValidId(todoId) || !isValidId(relatedId) || Number(todoId) === Number(relatedId)) {
        return '';
    }

    return `/api/todos/${Number(todoId)}/related/${Number(relatedId)}`;
}

/**
 * Build the body that pushes a ToDo's next review out by a number of days.
 *
 * The API accepts either a duration or an explicit date; the client offers the duration,
 * and the server still owns the range rules.
 */
export function buildSnoozeBody(days) {
    const requested = Number(days);

    return { days: Number.isFinite(requested) ? Math.max(1, Math.trunc(requested)) : 1 };
}

const ACTIVITY_ACTION_LABELS = {
    'todo.created': 'Created a ToDo',
    'todo.updated': 'Updated a ToDo',
    'todo.deleted': 'Permanently deleted a ToDo',
    'todo.inbox': 'Moved a ToDo back to the inbox',
    'todo.active': 'Moved a ToDo to in progress',
    'todo.done': 'Marked a ToDo done',
    'todo.archived': 'Archived a ToDo',
    'todo.trashed': 'Moved a ToDo to the trash',
    'todo.tag_attached': 'Added a tag to a ToDo',
    'todo.tag_detached': 'Removed a tag from a ToDo',
    'project.created': 'Created a Project',
    'project.deleted': 'Deleted a Project',
    'notebook.created': 'Created a Notebook',
    'notebook.deleted': 'Deleted a Notebook',
    'library.created': 'Created a Library',
    'library.deleted': 'Deleted a Library',
    'capability.granted': 'Granted a capability',
    'capability.revoked': 'Revoked a capability',
    'request.sent': 'Sent a request',
    'request.accepted': 'Accepted a request',
    'request.rejected': 'Rejected a request',
    'request.modified': 'Changed a request',
    'request.callback': 'Reported back on a request',
    'reference_card.created': 'Created a reference card',
    'reference_card.updated': 'Updated a reference card',
    'reference_card.deleted': 'Deleted a reference card',
    'reference_card.tag_attached': 'Added a tag to a reference card',
    'reference_card.tag_detached': 'Removed a tag from a reference card',
    'cheat_sheet.created': 'Created a cheat sheet',
    'cheat_sheet.updated': 'Updated a cheat sheet',
    'cheat_sheet.deleted': 'Deleted a cheat sheet',
    'cheat_sheet.card_added': 'Added a card to a cheat sheet',
    'cheat_sheet.card_moved': 'Moved a card on a cheat sheet',
    'cheat_sheet.card_removed': 'Removed a card from a cheat sheet',
};

export function labelForActivityAction(action) {
    const key = typeof action === 'string' ? action : '';

    return ACTIVITY_ACTION_LABELS[key] ?? key.replaceAll('.', ' ').replaceAll('_', ' ').trim();
}

const ACTIVITY_SUBJECT_NOUNS = {
    todo: 'ToDo',
    project: 'Project',
    notebook: 'Notebook',
    library: 'Library',
    capability_grant: 'Capability grant',
    cross_context_request: 'Request',
    reference_card: 'Reference card',
    cheat_sheet: 'Cheat sheet',
};

/**
 * Format a stored timestamp without depending on the reader's locale.
 *
 * Activity entries are an audit trail, so an unambiguous UTC rendering is preferred over a
 * friendlier but ambiguous local one.
 */
export function formatActivityTimestamp(value) {
    if (typeof value !== 'string' || value === '') {
        return '';
    }
    const moment = new Date(value);
    if (Number.isNaN(moment.getTime())) {
        return '';
    }

    return `${moment.toISOString().slice(0, 16).replace('T', ' ')} UTC`;
}

/**
 * Turn one activity record into the three readable parts the history list shows.
 */
export function formatActivityEntry(entry) {
    const subjectType = typeof entry?.subject_type === 'string' ? entry.subject_type : '';
    const noun = ACTIVITY_SUBJECT_NOUNS[subjectType] ?? subjectType.replaceAll('_', ' ');

    return {
        action: labelForActivityAction(entry?.action),
        subject: isValidId(entry?.subject_id) && noun !== ''
            ? `${noun} #${Number(entry.subject_id)}`
            : noun,
        when: formatActivityTimestamp(entry?.created),
    };
}

/**
 * Label a section in the context of the Project or Notebook it belongs to, for pickers
 * and assignment summaries.
 */
export function formatSectionLabel(parentName, sectionName) {
    const parent = typeof parentName === 'string' ? parentName.trim() : '';
    const section = typeof sectionName === 'string' ? sectionName.trim() : '';

    if (parent && section) {
        return `${parent} · ${section}`;
    }

    return section || parent || 'Untitled section';
}

/**
 * Encode the kind and id of a section as a single picker value.
 *
 * A ToDo has at most one organizational section, so one picker offers both Project and
 * Notebook sections and the value has to say which kind was chosen.
 */
export function sectionOptionValue(kind, id) {
    if ((kind !== 'project' && kind !== 'notebook') || !isValidId(id)) {
        return '';
    }

    return `${kind}:${Number(id)}`;
}

export function parseSectionOptionValue(value) {
    if (typeof value !== 'string') {
        return null;
    }
    const parts = value.split(':');
    if (parts.length !== 2) {
        return null;
    }
    const [kind, id] = parts;
    if ((kind !== 'project' && kind !== 'notebook') || !isValidId(id)) {
        return null;
    }

    return { kind, id: Number(id) };
}

/**
 * Build the body that moves a ToDo to the chosen section.
 *
 * Both fields are always sent, so moving between a Project section and a Notebook
 * section clears one assignment and sets the other in a single request, as
 * `docs/adr/0001-single-organizational-section-per-todo.md` requires.
 */
export function buildSectionAssignmentBody(value) {
    const choice = parseSectionOptionValue(value);

    return {
        project_section_id: choice?.kind === 'project' ? choice.id : null,
        notebook_section_id: choice?.kind === 'notebook' ? choice.id : null,
    };
}

export function buildTodoUrl({ query = '', status = '', tagId = '', page = 1 } = {}) {
    const requestedPage = Number(page);
    const safePage = Number.isFinite(requestedPage) ? Math.max(1, Math.trunc(requestedPage)) : 1;
    const params = new URLSearchParams({ page: String(safePage), limit: String(TODO_PAGE_SIZE) });
    const cleanQuery = query.trim();

    if (cleanQuery) {
        params.set('q', cleanQuery);
    }
    if (status) {
        params.set('status', status);
    }
    if (tagId) {
        params.set('tag', tagId);
    }

    return `/api/todos?${params.toString()}`;
}

export function getErrorMessage(payload, status) {
    const message = payload?.error?.message;

    return typeof message === 'string' && message.trim() !== ''
        ? message
        : `The request could not be completed (${status}).`;
}

export function isValidId(value) {
    if (typeof value !== 'number' && (typeof value !== 'string' || !/^\d+$/.test(value))) {
        return false;
    }
    const id = Number(value);

    return Number.isSafeInteger(id) && id > 0;
}

export function labelForReviewReason(reason) {
    const labels = {
        no_section: 'Not in a Project or Notebook section',
        no_tags: 'No tags yet',
        review_overdue: 'Review is overdue',
        never_reviewed: 'Never reviewed',
        stale: 'Has not been reviewed recently',
    };

    return labels[reason] ?? String(reason ?? '').replaceAll('_', ' ');
}

export function setText(element, value) {
    element.textContent = value === null || value === undefined ? '' : String(value);
}

export async function requestApi(path, options = {}) {
    const headers = { Accept: 'application/json', ...options.headers };
    const method = String(options.method ?? 'GET').toUpperCase();
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
        const csrfToken = globalThis.document?.querySelector('meta[name="csrf-token"]')?.content;
        if (typeof csrfToken === 'string' && csrfToken !== '') {
            headers['X-CSRF-Token'] = csrfToken;
        }
    }
    const requestOptions = {
        ...options,
        credentials: 'same-origin',
        headers,
    };

    if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    let response;
    try {
        response = await fetch(path, requestOptions);
    } catch {
        throw new Error('The server could not be reached. Check your connection and try again.');
    }

    let payload;
    try {
        payload = await response.json();
    } catch {
        payload = null;
    }

    if (!response.ok) {
        const error = new Error(getErrorMessage(payload, response.status));
        error.status = response.status;
        throw error;
    }

    const data = payload?.data;
    if (data && typeof data === 'object' && !Array.isArray(data) && payload?.meta) {
        return { ...data, meta: payload.meta };
    }

    return data ?? {};
}

const byId = (id) => document.getElementById(id);
const make = (tag, className = '', text = null) => {
    const element = document.createElement(tag);
    if (className) {
        element.className = className;
    }
    if (text !== null && text !== undefined) {
        setText(element, text);
    }

    return element;
};

const state = {
    userId: null,
    page: 1,
    total: 0,
    tags: [],
    view: 'dashboard',
    filters: { query: '', status: '', tagId: '' },
    // The ToDos currently listed, so the parent and related pickers can offer them by name
    // instead of asking a person to type an id.
    todoItems: [],
    todoDetail: { id: null },
    activity: { page: 1, total: 0 },
    records: {
        project: { items: [], page: 1, total: 0, openId: null },
        notebook: { items: [], page: 1, total: 0, openId: null },
    },
    libraries: { items: [], page: 1, total: 0, openId: null },
    reference: emptyReferenceState(),
    collaboration: { resource: null, inbox: [], outbox: [] },
    sectionOptions: [],
    // Monotonic tokens so a slow response cannot repaint a list that a newer
    // request has already replaced, which would silently discard a person's selection.
    requests: {
        todos: 0,
        review: 0,
        projects: 0,
        notebooks: 0,
        libraries: 0,
        activity: 0,
        capabilities: 0,
        referenceCards: 0,
        referenceCard: 0,
        cheatSheets: 0,
        cheatSheet: 0,
    },
};

function emptyReferenceState() {
    return {
        page: 1,
        total: 0,
        filters: { query: '', cardType: '', tagId: '', cheatSheetId: '' },
        openCardId: null,
        openCard: null,
        editingCardId: null,
        sheets: [],
        openSheetId: null,
        sheetCardCount: 0,
    };
}

function setNotice(message, isError = false) {
    const notice = byId('notice');
    setText(notice, message);
    notice.classList.toggle('is-error', isError);
    notice.setAttribute('role', isError ? 'alert' : 'status');
    notice.setAttribute('aria-live', isError ? 'assertive' : 'polite');
}

function showLogin(message = '') {
    state.userId = null;
    state.view = 'dashboard';
    state.collaboration = { resource: null, inbox: [], outbox: [] };
    byId('collaboration-resource-form').reset();
    byId('capability-grant-form').reset();
    byId('cross-context-request-form').reset();
    byId('capability-panel').hidden = true;
    byId('capability-list').replaceChildren();
    byId('request-inbox-list').replaceChildren();
    byId('request-outbox-list').replaceChildren();
    byId('request-inbox-empty').hidden = false;
    byId('request-outbox-empty').hidden = false;
    resetReferenceView();
    for (const button of document.querySelectorAll('[data-view]')) {
        const current = button.dataset.view === 'dashboard';
        button.classList.toggle('is-current', current);
        button.setAttribute('aria-pressed', String(current));
    }
    for (const name of ['dashboard', 'inbox', 'projects', 'notebooks', 'libraries', 'reference', 'review', 'activity', 'collaboration']) {
        byId(`${name}-view`).hidden = name !== 'dashboard';
    }
    byId('workspace').hidden = true;
    byId('account-actions').hidden = true;
    byId('login-panel').hidden = false;
    setNotice(message);
}

function showWorkspace(email) {
    byId('login-panel').hidden = true;
    byId('workspace').hidden = false;
    byId('account-actions').hidden = false;
    setText(byId('account-email'), email);
    setNotice('');
}

function displayError(error) {
    if (error.status === 401) {
        showLogin('Your session has ended. Please sign in again.');
        return;
    }
    setNotice(error.message || 'Something went wrong. Please try again.', true);
}

function validItems(value) {
    return Array.isArray(value) ? value : [];
}

function makeButton(label, action, className = 'button button-quiet') {
    const button = make('button', className, label);
    button.type = 'button';
    button.dataset.todoAction = action;

    return button;
}

function renderTags(container, tags, todoId) {
    const meta = make('div', 'todo-meta');
    for (const tag of validItems(tags)) {
        if (typeof tag?.name === 'string') {
            const pill = make('span', 'tag-pill');
            pill.append(make('span', '', tag.name));
            if (isValidId(todoId) && isValidId(tag?.id)) {
                const detach = make('button', 'tag-remove', '×');
                detach.type = 'button';
                detach.dataset.todoAction = 'detach-tag';
                detach.dataset.todoId = String(Number(todoId));
                detach.dataset.tagId = String(Number(tag.id));
                detach.setAttribute('aria-label', `Remove ${tag.name} from this ToDo`);
                pill.append(detach);
            }
            meta.append(pill);
        }
    }
    if (meta.childElementCount > 0) {
        container.append(meta);
    }
}

function sectionOptionFor(value) {
    return state.sectionOptions.find((entry) => entry.value === value) ?? null;
}

/**
 * Describe where a ToDo currently lives. A ToDo has at most one organizational section,
 * so at most one of the two section fields is ever set.
 */
function sectionAssignmentText(todo) {
    const value = currentSectionValue(todo);
    if (value === '') {
        return '';
    }
    const option = sectionOptionFor(value);
    if (option) {
        return `In ${option.label}`;
    }

    return value.startsWith('project:') ? 'In a Project section' : 'In a Notebook section';
}

/**
 * The picker value matching a ToDo's current section, or '' when it has none.
 */
function currentSectionValue(todo) {
    if (isValidId(todo?.project_section_id)) {
        return sectionOptionValue('project', todo.project_section_id);
    }
    if (isValidId(todo?.notebook_section_id)) {
        return sectionOptionValue('notebook', todo.notebook_section_id);
    }

    return '';
}

function renderTodo(todo, reasons = null, suggestedActions = null) {
    const item = make('li');
    const article = make('article', 'todo-card');
    const heading = make('div', 'todo-card-heading');
    const title = make('h3', '', typeof todo?.title === 'string' ? todo.title : 'Untitled ToDo');
    const status = make('span', 'status-label', todo?.status ?? '');
    heading.append(title, status);
    article.append(heading);

    if (typeof todo?.notes === 'string' && todo.notes !== '') {
        article.append(make('p', '', todo.notes));
    }
    renderTags(article, todo?.tags, todo?.id);

    const assignment = sectionAssignmentText(todo);
    if (assignment) {
        article.append(make('p', 'section-assignment', assignment));
    }

    if (Array.isArray(reasons) && reasons.length > 0) {
        const reasonList = make('ul', 'review-reasons');
        for (const reason of reasons) {
            if (typeof reason === 'string') {
                reasonList.append(make('li', '', labelForReviewReason(reason)));
            }
        }
        article.append(reasonList);
        if (validItems(suggestedActions).includes('assign_section')) {
            article.append(make('p', 'status-label', 'Assign this ToDo to a Project or Notebook section to clear this reminder.'));
        }
    }

    const actions = make('div', 'todo-actions');
    if (isValidId(todo?.id) && ['inbox', 'active'].includes(todo?.status)) {
        const complete = makeButton('Mark done', 'complete', 'button button-secondary');
        complete.dataset.todoId = String(Number(todo.id));
        actions.append(complete);
    } else if (isValidId(todo?.id) && todo?.status === 'done') {
        const reopen = makeButton('Move to in progress', 'activate', 'button button-secondary');
        reopen.dataset.todoId = String(Number(todo.id));
        actions.append(reopen);
    }

    if (
        Array.isArray(reasons)
        && reasons.length > 0
        && validItems(suggestedActions).includes('mark_reviewed')
        && isValidId(todo?.id)
    ) {
        const reviewed = makeButton('Mark reviewed', 'reviewed', 'button button-secondary');
        reviewed.dataset.todoId = String(Number(todo.id));
        actions.append(reviewed);
    }

    // Snoozing belongs next to the review queue: it defers the next review without
    // claiming the ToDo was looked at.
    if (Array.isArray(reasons) && isValidId(todo?.id)) {
        const todoTitle = typeof todo?.title === 'string' ? todo.title : 'ToDo';
        const label = make('label', '', 'Snooze for days');
        const input = make('input');
        input.type = 'number';
        input.min = '1';
        input.max = '365';
        input.value = '7';
        input.dataset.snoozeDays = '';
        input.setAttribute('aria-label', `Days to snooze ${todoTitle}`);
        label.append(input);
        const snooze = makeButton('Snooze', 'snooze', 'button button-quiet');
        snooze.dataset.todoId = String(Number(todo.id));
        snooze.setAttribute('aria-label', `Snooze ${todoTitle}`);
        actions.append(label, snooze);
    }

    if (reasons === null && isValidId(todo?.id)) {
        const todoTitle = typeof todo?.title === 'string' ? todo.title : 'ToDo';
        const details = makeButton('Details', 'open-detail', 'button button-quiet');
        details.dataset.todoId = String(Number(todo.id));
        details.setAttribute('aria-label', `Open details for ${todoTitle}`);
        actions.append(details);
    }

    if (isValidId(todo?.id) && state.tags.length > 0) {
        const label = make('label', '', 'Add a tag');
        const select = make('select');
        select.setAttribute('aria-label', `Add a tag to ${typeof todo?.title === 'string' ? todo.title : 'ToDo'}`);
        select.dataset.tagPicker = '';
        select.append(new Option('Choose a tag', ''));
        const attached = new Set(validItems(todo?.tags).map((tag) => String(tag.id)));
        for (const tag of state.tags) {
            if (isValidId(tag?.id) && !attached.has(String(tag.id))) {
                select.append(new Option(tag.name, String(tag.id)));
            }
        }
        label.append(select);
        const attach = makeButton('Attach', 'attach-tag', 'button button-quiet');
        attach.dataset.todoId = String(Number(todo.id));
        actions.append(label, attach);
    }

    if (isValidId(todo?.id) && state.sectionOptions.length > 0) {
        const todoTitle = typeof todo?.title === 'string' ? todo.title : 'ToDo';
        const label = make('label', '', 'Section');
        const select = make('select');
        select.setAttribute('aria-label', `Section for ${todoTitle}`);
        select.dataset.sectionPicker = '';
        select.append(new Option('No section', ''));
        for (const option of state.sectionOptions) {
            select.append(new Option(option.label, option.value));
        }
        const current = currentSectionValue(todo);
        select.value = sectionOptionFor(current) ? current : '';
        label.append(select);
        const move = makeButton('Move', 'assign-section', 'button button-quiet');
        move.dataset.todoId = String(Number(todo.id));
        actions.append(label, move);
    }

    if (actions.childElementCount > 0) {
        article.append(actions);
    }
    item.append(article);

    return item;
}

function renderDashboard(data) {
    const counts = data?.counts ?? {};
    for (const [key, elementId] of Object.entries({
        inbox: 'inbox-count',
        active: 'active-count',
        review_due: 'review-count',
        orphans: 'orphan-count',
    })) {
        setText(byId(elementId), Number.isFinite(Number(counts[key])) ? counts[key] : 0);
    }

    const list = byId('recent-todos');
    list.replaceChildren();
    const todos = validItems(data?.recent?.todos);
    for (const todo of todos) {
        const item = make('li');
        const link = make('a', '', typeof todo?.title === 'string' ? todo.title : 'Untitled ToDo');
        link.href = '#todos-title';
        link.addEventListener('click', () => selectView('inbox'));
        item.append(link, make('span', 'status-label', todo?.status ?? ''));
        list.append(item);
    }
    byId('recent-empty').hidden = todos.length > 0;
}

async function loadDashboard() {
    const data = await requestApi('/api/dashboard?recent_limit=5');
    renderDashboard(data);
}

function syncTagControls() {
    for (const filter of [byId('tag-filter'), byId('reference-tag-filter')]) {
        const selected = filter.value;
        filter.replaceChildren(new Option('Any tag', ''));

        for (const tag of state.tags) {
            if (isValidId(tag?.id) && typeof tag?.name === 'string') {
                filter.append(new Option(tag.name, String(tag.id)));
            }
        }
        filter.value = state.tags.some((tag) => String(tag.id) === selected) ? selected : '';
    }
    if (!state.tags.some((tag) => isValidId(tag?.id) && String(Number(tag.id)) === state.reference.filters.tagId)) {
        state.reference.filters.tagId = '';
    }

    const list = byId('tag-list');
    list.replaceChildren();
    for (const tag of state.tags) {
        if (!isValidId(tag?.id) || typeof tag?.name !== 'string') {
            continue;
        }
        const item = make('li', 'tag-item');
        item.append(make('span', '', tag.name));
        const remove = make('button', '', '×');
        remove.type = 'button';
        remove.dataset.deleteTag = String(Number(tag.id));
        remove.setAttribute('aria-label', `Delete tag ${tag.name}`);
        item.append(remove);
        list.append(item);
    }
    byId('tag-empty').hidden = state.tags.length > 0;
}

async function loadTags() {
    const data = await requestApi('/api/tags?limit=100&page=1');
    state.tags = validItems(data?.items);
    syncTagControls();
}

async function loadTodos() {
    const token = ++state.requests.todos;
    const data = await requestApi(buildTodoUrl({ ...state.filters, page: state.page }));
    if (token !== state.requests.todos) {
        return;
    }
    const items = validItems(data?.items);
    state.todoItems = items;
    state.total = Number(data?.meta?.pagination?.total) || 0;
    const list = byId('todo-list');
    list.replaceChildren(...items.map((todo) => renderTodo(todo)));
    byId('todo-empty').hidden = items.length > 0;

    const pageCount = Math.max(1, Math.ceil(state.total / TODO_PAGE_SIZE));
    setText(byId('todo-summary'), `Showing ${items.length} of ${state.total} ToDos`);
    setText(byId('page-label'), `Page ${state.page} of ${pageCount}`);
    byId('previous-page').disabled = state.page <= 1;
    byId('next-page').disabled = state.page >= pageCount;

    await loadTodoDetail();
}

/**
 * Lifecycle actions offered for each status, with the sentence that states what each does.
 *
 * The server owns the transition rules; this map only decides what is worth showing, and it
 * is deliberately explicit so no destructive action is ever offered by accident.
 */
const LIFECYCLE_ACTIONS = {
    archive: {
        label: 'Archive',
        className: 'button button-quiet',
        explanation: 'Archiving hides this ToDo from your lists. You can move it back later.',
        statuses: ['inbox', 'active', 'done'],
    },
    activate: {
        label: 'Move to in progress',
        className: 'button button-secondary',
        explanation: 'Bringing an archived ToDo back puts it in progress again.',
        statuses: ['archived'],
    },
    trash: {
        label: 'Move to trash',
        className: 'button button-quiet',
        explanation: 'Trashing keeps this ToDo so you can restore it, and is required before it can be deleted.',
        statuses: ['inbox', 'active', 'done', 'archived'],
    },
    restore: {
        label: 'Restore',
        className: 'button button-secondary',
        explanation: 'Restoring returns this ToDo to the status it had before it was trashed.',
        statuses: ['trashed'],
    },
    permanent: {
        label: 'Delete permanently',
        className: 'button button-danger',
        explanation: 'Deleting permanently removes this ToDo and cannot be undone.',
        statuses: ['trashed'],
    },
};

function todoTitleOf(todo) {
    return typeof todo?.title === 'string' && todo.title !== '' ? todo.title : 'Untitled ToDo';
}

/**
 * Offer every ToDo except the one being edited as a candidate parent or related ToDo.
 */
function todoChoices(excludeId) {
    return state.todoItems.filter(
        (todo) => isValidId(todo?.id) && Number(todo.id) !== Number(excludeId),
    );
}

function fillTodoPicker(select, excludeId, placeholder) {
    select.replaceChildren(new Option(placeholder, ''));
    for (const todo of todoChoices(excludeId)) {
        select.append(new Option(todoTitleOf(todo), String(Number(todo.id))));
    }
}

function renderRelatedTodo(todo, todoId) {
    const item = make('li', 'record-item');
    const heading = make('div', 'record-heading');
    heading.append(make('h4', '', todoTitleOf(todo)));
    heading.append(make('span', 'status-label', todo?.status ?? ''));
    item.append(heading);

    if (isValidId(todo?.id) && isValidId(todoId)) {
        const actions = make('div', 'record-actions');
        const unlink = make('button', 'button button-quiet', 'Unlink');
        unlink.type = 'button';
        unlink.dataset.todoAction = 'unrelate';
        unlink.dataset.todoId = String(Number(todoId));
        unlink.dataset.relatedId = String(Number(todo.id));
        unlink.setAttribute('aria-label', `Unlink ${todoTitleOf(todo)}`);
        actions.append(unlink);
        item.append(actions);
    }

    return item;
}

function renderChildTodo(todo) {
    const item = make('li', 'record-item');
    const heading = make('div', 'record-heading');
    heading.append(make('h4', '', todoTitleOf(todo)));
    heading.append(make('span', 'status-label', todo?.status ?? ''));
    item.append(heading);
    if (typeof todo?.result_summary === 'string' && todo.result_summary !== '') {
        item.append(make('p', 'record-description', `Reported: ${todo.result_summary}`));
    }

    return item;
}

/**
 * Render the lifecycle controls for the open ToDo.
 *
 * `Delete permanently` is armed by a first press and only sends the request on the second,
 * so no single click can destroy a ToDo.
 */
function renderLifecycleActions(todo) {
    const container = byId('todo-lifecycle-actions');
    const explanations = byId('todo-lifecycle-explanations');
    container.replaceChildren();
    explanations.replaceChildren();
    const status = typeof todo?.status === 'string' ? todo.status : '';
    const todoId = String(Number(todo.id));

    for (const [action, config] of Object.entries(LIFECYCLE_ACTIONS)) {
        if (!config.statuses.includes(status)) {
            continue;
        }
        const button = make('button', config.className, config.label);
        button.type = 'button';
        button.dataset.todoAction = action;
        button.dataset.todoId = todoId;
        button.setAttribute('aria-label', `${config.label}: ${todoTitleOf(todo)}`);
        if (requiresConfirmation(action)) {
            button.dataset.confirmed = 'false';
        }
        container.append(button);
        explanations.append(make('li', '', `${config.label}: ${config.explanation}`));
    }
}

/**
 * Load and render the open ToDo's parent, children, related ToDos, objective and lifecycle.
 */
async function loadTodoDetail() {
    const detail = byId('todo-detail');
    const openId = state.todoDetail.id;
    if (!isValidId(openId)) {
        detail.hidden = true;
        return;
    }

    const data = await requestApi(buildTodoActionUrl(openId, 'hierarchy'));
    const todo = data?.todo;
    setText(byId('todo-detail-title'), todoTitleOf(todo));
    setText(byId('todo-detail-status'), `Status: ${todo?.status ?? 'unknown'}`);

    const parentSelect = byId('todo-parent-select');
    fillTodoPicker(parentSelect, openId, 'No parent');
    const parentId = data?.parent?.id;
    if (isValidId(parentId)) {
        if (!todoChoices(openId).some((candidate) => Number(candidate.id) === Number(parentId))) {
            parentSelect.append(new Option(todoTitleOf(data.parent), String(Number(parentId))));
        }
        parentSelect.value = String(Number(parentId));
    } else {
        parentSelect.value = '';
    }
    fillTodoPicker(byId('todo-related-select'), openId, 'Choose a ToDo');

    const children = validItems(data?.children);
    byId('todo-children-list').replaceChildren(...children.map((child) => renderChildTodo(child)));
    byId('todo-children-empty').hidden = children.length > 0;

    const related = validItems(data?.related);
    byId('todo-related-list').replaceChildren(
        ...related.map((entry) => renderRelatedTodo(entry, openId)),
    );
    byId('todo-related-empty').hidden = related.length > 0;

    byId('todo-objective').value = typeof todo?.terminal_objective === 'string' ? todo.terminal_objective : '';
    byId('todo-result').value = '';
    const reported = byId('todo-result-summary');
    setText(
        reported,
        typeof todo?.result_summary === 'string' && todo.result_summary !== ''
            ? `Reported result: ${todo.result_summary}`
            : '',
    );
    reported.hidden = !reported.textContent;

    renderLifecycleActions(todo);
    detail.hidden = false;
}

async function loadActivity() {
    const token = ++state.requests.activity;
    const data = await requestApi(buildActivityUrl({ page: state.activity.page }));
    if (token !== state.requests.activity) {
        return;
    }
    const items = validItems(data?.items);
    state.activity.total = Number(data?.meta?.pagination?.total) || 0;
    const list = byId('activity-list');
    list.replaceChildren(...items.map((entry) => {
        const formatted = formatActivityEntry(entry);
        const item = make('li');
        item.append(make('span', '', `${formatted.action} — ${formatted.subject}`));
        item.append(make('span', 'status-label', formatted.when));

        return item;
    }));
    byId('activity-empty').hidden = items.length > 0;

    const pageCount = Math.max(1, Math.ceil(state.activity.total / ACTIVITY_PAGE_SIZE));
    setText(byId('activity-summary'), `Showing ${items.length} of ${state.activity.total} entries`);
    setText(byId('activity-page-label'), `Activity page ${state.activity.page} of ${pageCount}`);
    byId('previous-activity-page').disabled = state.activity.page <= 1;
    byId('next-activity-page').disabled = state.activity.page >= pageCount;
}

async function loadReviewQueue() {
    const token = ++state.requests.review;
    const data = await requestApi('/api/todos/review-queue?limit=50&page=1');
    if (token !== state.requests.review) {
        return;
    }
    const items = validItems(data?.items);
    const list = byId('review-list');
    list.replaceChildren(...items.map((entry) => renderTodo(entry?.todo, entry?.reasons, entry?.suggested_actions)));
    byId('review-empty').hidden = items.length > 0;
}

/**
 * Build the flat list of sections offered when moving a ToDo.
 *
 * Sections are read per Project and per Notebook because the API exposes them that way;
 * the picker therefore covers the newest `SECTION_SOURCE_LIMIT` of each.
 */
async function loadSectionOptionsFor(kind, listUrl, path) {
    const data = await requestApi(listUrl);
    const records = validItems(data?.items).filter((record) => isValidId(record?.id));
    const responses = await Promise.all(
        records.map((record) => requestApi(`${path}/${Number(record.id)}/sections`)),
    );

    const options = [];
    responses.forEach((response, index) => {
        for (const section of validItems(response?.items)) {
            if (isValidId(section?.id)) {
                options.push({
                    kind,
                    value: sectionOptionValue(kind, section.id),
                    label: formatSectionLabel(records[index]?.name, section?.name),
                });
            }
        }
    });

    return options;
}

async function loadSectionOptions() {
    const [projectOptions, notebookOptions] = await Promise.all([
        loadSectionOptionsFor(
            'project',
            buildProjectsUrl({ page: 1, limit: SECTION_SOURCE_LIMIT }),
            '/api/projects',
        ),
        loadSectionOptionsFor(
            'notebook',
            buildNotebooksUrl({ page: 1, limit: SECTION_SOURCE_LIMIT }),
            '/api/notebooks',
        ),
    ]);
    state.sectionOptions = [...projectOptions, ...notebookOptions];
}

const RECORD_KINDS = {
    project: {
        noun: 'Project',
        plural: 'Projects',
        path: '/api/projects',
        payloadKey: 'project',
        pageSize: PROJECT_PAGE_SIZE,
        listUrl: buildProjectsUrl,
        ids: {
            list: 'project-list',
            empty: 'project-empty',
            summary: 'project-summary',
            pageLabel: 'project-page-label',
            previous: 'previous-project-page',
            next: 'next-project-page',
            detail: 'project-detail',
            detailTitle: 'project-detail-title',
            detailDescription: 'project-detail-description',
            sectionList: 'section-list',
            sectionEmpty: 'section-empty',
        },
    },
    notebook: {
        noun: 'Notebook',
        plural: 'Notebooks',
        path: '/api/notebooks',
        payloadKey: 'notebook',
        pageSize: NOTEBOOK_PAGE_SIZE,
        listUrl: buildNotebooksUrl,
        ids: {
            list: 'notebook-list',
            empty: 'notebook-empty',
            summary: 'notebook-summary',
            pageLabel: 'notebook-page-label',
            previous: 'previous-notebook-page',
            next: 'next-notebook-page',
            detail: 'notebook-detail',
            detailTitle: 'notebook-detail-title',
            detailDescription: 'notebook-detail-description',
            sectionList: 'notebook-section-list',
            sectionEmpty: 'notebook-section-empty',
        },
    },
};

/**
 * Projects and Notebooks are listed, opened and sectioned identically, so one renderer
 * and one loader serve both; only the endpoint, the wording and the element ids differ.
 */
function renderRecord(kind, record) {
    const config = RECORD_KINDS[kind];
    const item = make('li', 'record-item');
    const heading = make('div', 'record-heading');
    const name = typeof record?.name === 'string' ? record.name : `Untitled ${config.noun}`;
    heading.append(make('h3', '', name));
    item.append(heading);

    if (typeof record?.description === 'string' && record.description !== '') {
        item.append(make('p', 'record-description', record.description));
    }

    if (!isValidId(record?.id)) {
        return item;
    }
    const recordId = String(Number(record.id));

    const actions = make('div', 'record-actions');
    const label = make('label', '', 'Rename');
    const input = make('input');
    input.type = 'text';
    input.maxLength = 255;
    input.value = name;
    input.dataset.recordNameInput = '';
    input.setAttribute('aria-label', `New name for ${name}`);
    label.append(input);

    actions.append(label);
    for (const [text, action, className, description] of [
        ['Save name', 'rename', 'button button-quiet', `Save the new name for ${name}`],
        ['Open', 'open', 'button button-secondary', `Open ${name}`],
        ['Delete', 'delete', 'button button-danger', `Delete ${name}`],
    ]) {
        const button = make('button', className, text);
        button.type = 'button';
        button.dataset.recordAction = action;
        button.dataset.recordKind = kind;
        button.dataset.recordId = recordId;
        button.setAttribute('aria-label', description);
        actions.append(button);
    }
    item.append(actions);

    return item;
}

function renderSection(kind, section) {
    const item = make('li', 'record-item');
    const heading = make('div', 'record-heading');
    const name = typeof section?.name === 'string' ? section.name : 'Untitled section';
    heading.append(make('h4', '', name));
    item.append(heading);

    const todos = validItems(section?.todos);
    if (todos.length > 0) {
        const list = make('ul', 'record-todos');
        for (const todo of todos) {
            const entry = make('li', '', typeof todo?.title === 'string' ? todo.title : 'Untitled ToDo');
            entry.append(make('span', 'status-label', ` — ${todo?.status ?? ''}`));
            list.append(entry);
        }
        item.append(list);
    } else {
        item.append(make('p', 'record-description', 'No ToDos in this section yet.'));
    }

    if (!isValidId(section?.id)) {
        return item;
    }

    const actions = make('div', 'record-actions');
    const label = make('label', '', 'Rename');
    const input = make('input');
    input.type = 'text';
    input.maxLength = 255;
    input.value = name;
    input.dataset.sectionNameInput = '';
    input.setAttribute('aria-label', `New name for section ${name}`);
    label.append(input);

    const rename = make('button', 'button button-quiet', 'Save name');
    rename.type = 'button';
    rename.dataset.sectionAction = 'rename';
    rename.dataset.sectionKind = kind;
    rename.dataset.sectionId = String(Number(section.id));
    rename.setAttribute('aria-label', `Save the new name for section ${name}`);

    const remove = make('button', 'button button-danger', 'Delete');
    remove.type = 'button';
    remove.dataset.sectionAction = 'delete';
    remove.dataset.sectionKind = kind;
    remove.dataset.sectionId = String(Number(section.id));
    remove.setAttribute('aria-label', `Delete section ${name}`);

    actions.append(label, rename, remove);
    item.append(actions);

    return item;
}

async function loadRecordDetail(kind) {
    const config = RECORD_KINDS[kind];
    const openId = state.records[kind].openId;
    const detail = byId(config.ids.detail);
    if (!isValidId(openId)) {
        detail.hidden = true;
        return;
    }

    const data = await requestApi(`${config.path}/${Number(openId)}`);
    const record = data?.[config.payloadKey];
    setText(byId(config.ids.detailTitle), typeof record?.name === 'string' ? record.name : config.noun);
    const description = byId(config.ids.detailDescription);
    setText(description, typeof record?.description === 'string' ? record.description : '');
    description.hidden = !description.textContent;

    const sections = validItems(record?.sections);
    byId(config.ids.sectionList).replaceChildren(...sections.map((section) => renderSection(kind, section)));
    byId(config.ids.sectionEmpty).hidden = sections.length > 0;
    detail.hidden = false;
}

async function loadRecords(kind) {
    const config = RECORD_KINDS[kind];
    const records = state.records[kind];
    const token = ++state.requests[`${kind}s`];
    const data = await requestApi(config.listUrl({ page: records.page }));
    if (token !== state.requests[`${kind}s`]) {
        return;
    }
    const items = validItems(data?.items);
    records.items = items;
    records.total = Number(data?.meta?.pagination?.total) || 0;
    const pageCount = Math.max(1, Math.ceil(records.total / config.pageSize));
    if (records.page > pageCount) {
        records.page = pageCount;
        await loadRecords(kind);
        return;
    }
    byId(config.ids.list).replaceChildren(...items.map((record) => renderRecord(kind, record)));
    byId(config.ids.empty).hidden = items.length > 0;

    setText(byId(config.ids.summary), `Showing ${items.length} of ${records.total} ${config.plural}`);
    setText(byId(config.ids.pageLabel), `${config.plural} page ${records.page} of ${pageCount}`);
    byId(config.ids.previous).disabled = records.page <= 1;
    byId(config.ids.next).disabled = records.page >= pageCount;

    await loadRecordDetail(kind);
}

function renderLibraryMember(member) {
    const item = make('li', 'record-item');
    item.append(make('h4', '', typeof member?.name === 'string' ? member.name : 'Untitled'));
    if (typeof member?.description === 'string' && member.description !== '') {
        item.append(make('p', 'record-description', member.description));
    }

    return item;
}

function renderLibrary(library) {
    const item = make('li', 'record-item');
    const name = typeof library?.name === 'string' ? library.name : 'Untitled Library';
    const heading = make('div', 'record-heading');
    heading.append(make('h3', '', name));
    item.append(heading);

    if (typeof library?.description === 'string' && library.description !== '') {
        item.append(make('p', 'record-description', library.description));
    }

    if (isValidId(library?.id)) {
        const actions = make('div', 'record-actions');
        const open = make('button', 'button button-secondary', 'Open');
        open.type = 'button';
        open.dataset.libraryAction = 'open';
        open.dataset.libraryId = String(Number(library.id));
        open.setAttribute('aria-label', `Open ${name}`);
        actions.append(open);
        item.append(actions);
    }

    return item;
}

/**
 * Libraries are browsed, not managed: the view reads the collection and its members and
 * offers no mutation, which is what the Slice 16 contract allows.
 */
async function loadLibraryDetail() {
    const detail = byId('library-detail');
    if (!isValidId(state.libraries.openId)) {
        detail.hidden = true;
        return;
    }

    const data = await requestApi(`/api/libraries/${Number(state.libraries.openId)}`);
    const library = data?.library;
    setText(byId('library-detail-title'), typeof library?.name === 'string' ? library.name : 'Library');
    const description = byId('library-detail-description');
    setText(description, typeof library?.description === 'string' ? library.description : '');
    description.hidden = !description.textContent;

    const projects = validItems(library?.projects);
    const notebooks = validItems(library?.notebooks);
    byId('library-projects').replaceChildren(...projects.map((member) => renderLibraryMember(member)));
    byId('library-notebooks').replaceChildren(...notebooks.map((member) => renderLibraryMember(member)));
    byId('library-members-empty').hidden = projects.length + notebooks.length > 0;
    detail.hidden = false;
}

async function loadLibraries() {
    const token = ++state.requests.libraries;
    const data = await requestApi(buildLibrariesUrl({ page: state.libraries.page }));
    if (token !== state.requests.libraries) {
        return;
    }
    const items = validItems(data?.items);
    state.libraries.items = items;
    state.libraries.total = Number(data?.meta?.pagination?.total) || 0;
    byId('library-list').replaceChildren(...items.map((library) => renderLibrary(library)));
    byId('library-empty').hidden = items.length > 0;

    const pageCount = Math.max(1, Math.ceil(state.libraries.total / LIBRARY_PAGE_SIZE));
    setText(byId('library-summary'), `Showing ${items.length} of ${state.libraries.total} Libraries`);
    setText(byId('library-page-label'), `Libraries page ${state.libraries.page} of ${pageCount}`);
    byId('previous-library-page').disabled = state.libraries.page <= 1;
    byId('next-library-page').disabled = state.libraries.page >= pageCount;

    await loadLibraryDetail();
}

function resourceLabel(type, id) {
    const noun = { project: 'Project', notebook: 'Notebook', library: 'Library' }[type] ?? 'resource';

    return `${noun} #${Number(id)}`;
}

function renderCapabilityGrant(grant) {
    const item = make('li', 'record-item');
    item.append(make('p', 'record-description', `User #${grant.subject_user_id} · ${grant.capability}`));
    if (isValidId(grant.id)) {
        const revoke = make('button', 'button button-quiet', 'Revoke');
        revoke.type = 'button';
        revoke.dataset.revokeGrant = String(Number(grant.id));
        revoke.setAttribute('aria-label', `Revoke ${grant.capability} from user ${grant.subject_user_id}`);
        item.append(revoke);
    }

    return item;
}

function renderCrossContextRequest(request, listKind) {
    const item = make('li', 'record-item');
    const title = typeof request?.title === 'string' ? request.title : 'Untitled request';
    item.append(make('h4', 'panel-title', title));
    item.append(make(
        'p',
        'record-description',
        `Request #${request.id} · ${request.request_type} · ${request.status}`,
    ));
    item.append(make(
        'p',
        'record-description',
        `From ${resourceLabel(request.source_type, request.source_id)} to ${resourceLabel(request.target_type, request.target_id)}.`,
    ));
    if (typeof request?.body === 'string' && request.body !== '') {
        item.append(make('p', 'record-description', request.body));
    }
    if (request.status === 'pending') {
        item.append(make(
            'p',
            'result-summary',
            'A referenced context may have been deleted or access may have changed; the server checks every action.',
        ));
    }
    if (typeof request?.resolution_note === 'string' && request.resolution_note !== '') {
        item.append(make('p', 'record-description', `Response: ${request.resolution_note}`));
    }
    if (typeof request?.callback_summary === 'string' && request.callback_summary !== '') {
        item.append(make('p', 'record-description', `Callback: ${request.callback_summary}`));
    }

    if (listKind === 'inbox' && request.status === 'pending' && isValidId(request.id)) {
        const actions = make('div', 'record-actions');
        for (const [label, action, className] of [
            ['Accept', 'accept', 'button button-secondary'],
            ['Decline', 'reject', 'button button-quiet'],
        ]) {
            const button = make('button', className, label);
            button.type = 'button';
            button.dataset.requestAction = action;
            button.dataset.requestId = String(Number(request.id));
            button.setAttribute('aria-label', `${label} request: ${title}`);
            actions.append(button);
        }
        item.append(actions);
    } else if (
        listKind === 'inbox'
        && request.status === 'accepted'
        && request.callback_at === null
        && isValidId(request.id)
    ) {
        const form = make('form', 'form-stack');
        form.dataset.callbackRequest = String(Number(request.id));
        const label = make('label', '', `Callback for ${title}`);
        const textarea = make('textarea');
        textarea.rows = 2;
        textarea.required = true;
        textarea.maxLength = 1000;
        textarea.setAttribute('aria-label', `Callback summary for ${title}`);
        label.append(textarea);
        const submit = make('button', 'button button-secondary', 'Send callback');
        submit.type = 'submit';
        form.append(label, submit);
        item.append(form);
    }

    return item;
}

async function loadCollaboration() {
    const [inbox, outbox] = await Promise.all([
        requestApi('/api/cross-context-requests/inbox'),
        requestApi('/api/cross-context-requests/outbox'),
    ]);
    state.collaboration.inbox = validItems(inbox?.items);
    state.collaboration.outbox = validItems(outbox?.items);
    byId('request-inbox-list').replaceChildren(
        ...state.collaboration.inbox.map((request) => renderCrossContextRequest(request, 'inbox')),
    );
    byId('request-outbox-list').replaceChildren(
        ...state.collaboration.outbox.map((request) => renderCrossContextRequest(request, 'outbox')),
    );
    byId('request-inbox-empty').hidden = state.collaboration.inbox.length > 0;
    byId('request-outbox-empty').hidden = state.collaboration.outbox.length > 0;
}

async function loadCapabilities() {
    const type = byId('collaboration-resource-type').value;
    const id = byId('collaboration-resource-id').value;
    if (!['project', 'notebook', 'library'].includes(type) || !isValidId(id)) {
        setNotice('Enter a valid resource type and ID.', true);
        return;
    }

    const token = ++state.requests.capabilities;
    const data = await requestApi(
        `/api/capabilities?resource_type=${encodeURIComponent(type)}&resource_id=${Number(id)}`,
    );
    if (token !== state.requests.capabilities) {
        return;
    }
    const grants = validItems(data?.items);
    state.collaboration.resource = { type, id: Number(id) };
    byId('capability-list').replaceChildren(...grants.map((grant) => renderCapabilityGrant(grant)));
    byId('capability-empty').hidden = grants.length > 0;
    byId('capability-panel').hidden = false;
    setNotice(`Showing active grants for ${resourceLabel(type, id)}.`);
}

function showCollaborationError(error) {
    if (error.status === 403 || error.status === 404) {
        setNotice('The action was denied or the resource is unavailable. Check its ID and your granted capability.', true);
        return;
    }
    displayError(error);
}

/**
 * Labelled blocks for the populated dialogue-pattern fields of a card.
 */
function renderDialogueBlocks(card) {
    const list = make('dl', 'reference-fields');
    for (const block of dialogueBlocks(card)) {
        const entry = make('div', 'reference-field');
        entry.append(make('dt', '', block.label), make('dd', '', block.text));
        list.append(entry);
    }

    return list;
}

function referenceButton(label, action, dataset = {}, className = 'button button-quiet') {
    const button = make('button', className, label);
    button.type = 'button';
    button.dataset.referenceAction = action;
    for (const [key, value] of Object.entries(dataset)) {
        button.dataset[key] = value;
    }

    return button;
}

function renderReferenceCardItem(card) {
    const item = make('li', 'record-item');
    const title = typeof card?.title === 'string' ? card.title : 'Untitled card';
    const heading = make('div', 'record-heading');
    heading.append(make('h3', '', title), make('span', 'status-label', labelForCardType(card?.card_type)));
    item.append(heading);
    if (typeof card?.summary === 'string' && card.summary !== '') {
        item.append(make('p', 'record-description', card.summary));
    }
    if (isValidId(card?.id)) {
        const actions = make('div', 'record-actions');
        const open = referenceButton('Open', 'open-card', { cardId: String(Number(card.id)) }, 'button button-secondary');
        open.setAttribute('aria-label', `Open ${title}`);
        actions.append(open);
        item.append(actions);
    }

    return item;
}

function fillSelect(select, placeholder, options) {
    const selected = select.value;
    select.replaceChildren(new Option(placeholder, ''));
    for (const [value, label] of options) {
        select.append(new Option(label, value));
    }
    select.value = options.some(([value]) => value === selected) ? selected : '';
}

function syncCheatSheetControls() {
    const options = state.reference.sheets
        .filter((sheet) => isValidId(sheet?.id) && typeof sheet?.name === 'string')
        .map((sheet) => [String(Number(sheet.id)), sheet.name]);
    fillSelect(byId('reference-sheet-filter'), 'Any sheet', options);
    fillSelect(byId('reference-sheet-picker'), 'Choose a cheat sheet', options);
}

async function requestAllPages(buildUrl, limit) {
    const firstPage = await requestApi(buildUrl(1, limit));
    const total = Number(firstPage?.meta?.pagination?.total) || 0;
    const pageCount = Math.max(1, Math.ceil(total / limit));
    const remainingPages = await Promise.all(
        Array.from({ length: pageCount - 1 }, (_, index) => requestApi(buildUrl(index + 2, limit))),
    );

    return {
        items: [
            ...validItems(firstPage?.items),
            ...remainingPages.flatMap((page) => validItems(page?.items)),
        ],
        total,
    };
}

function resetReferenceView() {
    state.reference = emptyReferenceState();
    for (const id of ['reference-list', 'cheat-sheet-list', 'cheat-sheet-cards', 'reference-card-fields', 'reference-card-tags']) {
        byId(id).replaceChildren();
    }
    hideReferenceDetail(byId('reference-card-detail'), 'cardId');
    hideReferenceDetail(byId('cheat-sheet-detail'), 'sheetId');
    byId('reference-search-form').reset();
    syncCheatSheetControls();
    resetReferenceCardForm();
}

function resetReferenceCardForm() {
    state.reference.editingCardId = null;
    byId('reference-card-form').reset();
    byId('reference-dialogue-fieldset').hidden = true;
    setText(byId('reference-form-title'), 'Create a card');
    setText(byId('reference-card-submit'), 'Create card');
    byId('reference-card-cancel').hidden = true;
}

function startEditingReferenceCard(card) {
    state.reference.editingCardId = Number(card.id);
    const form = byId('reference-card-form');
    form.elements.card_type.value = Object.hasOwn(REFERENCE_CARD_TYPES, card.card_type) ? card.card_type : 'concept';
    for (const field of ['title', 'summary', 'body', ...DIALOGUE_FIELDS.map(([name]) => name)]) {
        form.elements[field].value = typeof card[field] === 'string' ? card[field] : '';
    }
    byId('reference-dialogue-fieldset').hidden = form.elements.card_type.value !== 'dialogue_pattern';
    setText(byId('reference-form-title'), 'Edit card');
    setText(byId('reference-card-submit'), 'Save card');
    byId('reference-card-cancel').hidden = false;
    form.elements.title.focus();
}

/**
 * The id of the record a detail panel is currently showing, or `null` when it shows none.
 */
function shownReferenceId(detail, key) {
    return !detail.hidden && isValidId(detail.dataset[key]) ? Number(detail.dataset[key]) : null;
}

function hideReferenceDetail(detail, key) {
    detail.hidden = true;
    delete detail.dataset[key];
}

async function loadReferenceCardDetail() {
    const detail = byId('reference-card-detail');
    const token = ++state.requests.referenceCard;
    if (shownReferenceId(detail, 'cardId') !== state.reference.openCardId) {
        // Hide the previous card at once so its actions cannot run against the newly chosen one.
        hideReferenceDetail(detail, 'cardId');
    }
    if (!isValidId(state.reference.openCardId)) {
        state.reference.openCard = null;
        return;
    }

    let card;
    try {
        card = (await requestApi(`/api/reference-cards/${Number(state.reference.openCardId)}`))?.card;
    } catch (error) {
        if (error.status === 404 && token === state.requests.referenceCard) {
            state.reference.openCardId = null;
            state.reference.openCard = null;
            hideReferenceDetail(detail, 'cardId');
            return;
        }
        throw error;
    }
    if (token !== state.requests.referenceCard) {
        return;
    }
    state.reference.openCard = card ?? null;

    setText(byId('reference-card-type'), labelForCardType(card?.card_type));
    setText(byId('reference-card-title'), typeof card?.title === 'string' ? card.title : 'Reference card');
    const summary = byId('reference-card-summary');
    setText(summary, typeof card?.summary === 'string' ? card.summary : '');
    summary.hidden = !summary.textContent;
    const fields = byId('reference-card-fields');
    fields.replaceChildren(...renderDialogueBlocks(card).children);
    fields.hidden = fields.children.length === 0;
    const body = byId('reference-card-body');
    setText(body, typeof card?.body === 'string' ? card.body : '');
    body.hidden = !body.textContent;

    const tags = validItems(card?.tags).filter((tag) => isValidId(tag?.id) && typeof tag?.name === 'string');
    byId('reference-card-tags').replaceChildren(...tags.map((tag) => {
        const pill = make('span', 'tag-pill');
        pill.append(make('span', '', tag.name));
        const detach = referenceButton('×', 'detach-tag', { tagId: String(Number(tag.id)) }, 'tag-remove');
        detach.setAttribute('aria-label', `Remove tag ${tag.name}`);
        pill.append(detach);

        return pill;
    }));
    const attached = new Set(tags.map((tag) => Number(tag.id)));
    fillSelect(
        byId('reference-tag-picker'),
        'Choose a tag',
        state.tags
            .filter((tag) => isValidId(tag?.id) && typeof tag?.name === 'string' && !attached.has(Number(tag.id)))
            .map((tag) => [String(Number(tag.id)), tag.name]),
    );
    const remove = byId('reference-delete-card');
    delete remove.dataset.confirmed;
    setText(remove, 'Delete card');
    detail.dataset.cardId = String(Number(state.reference.openCardId));
    detail.hidden = false;
}

async function loadReferenceCards() {
    const token = ++state.requests.referenceCards;
    const data = await requestApi(buildReferenceCardsUrl({ ...state.reference.filters, page: state.reference.page }));
    if (token !== state.requests.referenceCards) {
        return;
    }
    const items = validItems(data?.items);
    state.reference.total = Number(data?.meta?.pagination?.total) || 0;
    const pageCount = Math.max(1, Math.ceil(state.reference.total / REFERENCE_PAGE_SIZE));
    if (state.reference.page > pageCount) {
        state.reference.page = pageCount;
        await loadReferenceCards();
        return;
    }
    byId('reference-list').replaceChildren(...items.map((card) => renderReferenceCardItem(card)));
    byId('reference-empty').hidden = items.length > 0;

    setText(byId('reference-summary'), `Showing ${items.length} of ${state.reference.total} cards`);
    setText(byId('reference-page-label'), `Cards page ${state.reference.page} of ${pageCount}`);
    byId('previous-reference-page').disabled = state.reference.page <= 1;
    byId('next-reference-page').disabled = state.reference.page >= pageCount;
}

function renderCheatSheetItem(sheet) {
    const item = make('li', 'record-item');
    const name = typeof sheet?.name === 'string' ? sheet.name : 'Untitled cheat sheet';
    const heading = make('div', 'record-heading');
    const count = Number(sheet?.card_count) || 0;
    heading.append(make('h4', '', name), make('span', 'status-label', count === 1 ? '1 card' : `${count} cards`));
    item.append(heading);
    if (isValidId(sheet?.id)) {
        const actions = make('div', 'record-actions');
        const open = referenceButton('Open sheet', 'open-sheet', { sheetId: String(Number(sheet.id)) }, 'button button-secondary');
        open.setAttribute('aria-label', `Open cheat sheet ${name}`);
        actions.append(open);
        item.append(actions);
    }

    return item;
}

async function loadCheatSheets() {
    const token = ++state.requests.cheatSheets;
    const { items, total } = await requestAllPages(
        (page, limit) => `/api/cheat-sheets?page=${page}&limit=${limit}`,
        CHEAT_SHEET_LIMIT,
    );
    if (token !== state.requests.cheatSheets) {
        return;
    }
    state.reference.sheets = items;
    byId('cheat-sheet-list').replaceChildren(...items.map((sheet) => renderCheatSheetItem(sheet)));
    byId('cheat-sheet-empty').hidden = items.length > 0;
    setText(
        byId('cheat-sheet-summary'),
        total > 0 ? `Showing all ${items.length} of ${total} cheat sheets` : '',
    );
    syncCheatSheetControls();
}

function renderCheatSheetCard(membership, count) {
    const card = membership?.card;
    const cardId = Number(membership?.reference_card_id);
    const position = Number(membership?.position);
    const title = typeof card?.title === 'string' ? card.title : 'Untitled card';
    const item = make('li', 'cheat-sheet-card');
    const heading = make('div', 'record-heading');
    heading.append(make('h4', '', title), make('span', 'status-label', labelForCardType(card?.card_type)));
    item.append(heading);
    if (typeof card?.summary === 'string' && card.summary !== '') {
        item.append(make('p', 'reference-summary', card.summary));
    }
    const blocks = renderDialogueBlocks(card);
    if (blocks.children.length > 0) {
        item.append(blocks);
    }

    const actions = make('div', 'record-actions');
    const data = { cardId: String(cardId), position: String(position) };
    const up = referenceButton('Move up', 'move-up', data);
    up.disabled = nudgedPosition(position, 'up', count) === null;
    up.setAttribute('aria-label', `Move ${title} up`);
    const down = referenceButton('Move down', 'move-down', data);
    down.disabled = nudgedPosition(position, 'down', count) === null;
    down.setAttribute('aria-label', `Move ${title} down`);
    const open = referenceButton('Open card', 'open-card', { cardId: String(cardId) });
    open.setAttribute('aria-label', `Open ${title}`);
    const remove = referenceButton('Remove', 'remove-from-sheet', data);
    remove.setAttribute('aria-label', `Remove ${title} from this sheet`);
    actions.append(up, down, open, remove);
    item.append(actions);

    return item;
}

async function loadCheatSheetDetail() {
    const detail = byId('cheat-sheet-detail');
    const token = ++state.requests.cheatSheet;
    const sheetId = state.reference.openSheetId;
    if (shownReferenceId(detail, 'sheetId') !== sheetId) {
        // Hide the previous sheet at once so its actions cannot run against the newly chosen one.
        hideReferenceDetail(detail, 'sheetId');
    }
    if (!isValidId(sheetId)) {
        return;
    }

    let sheet;
    let cards;
    try {
        [sheet, cards] = await Promise.all([
            requestApi(`/api/cheat-sheets/${Number(sheetId)}`),
            requestAllPages(
                (page, limit) => `${buildCheatSheetCardUrl(sheetId)}?page=${page}&limit=${limit}`,
                CHEAT_SHEET_LIMIT,
            ),
        ]);
    } catch (error) {
        if (error.status === 404 && token === state.requests.cheatSheet) {
            state.reference.openSheetId = null;
            hideReferenceDetail(detail, 'sheetId');
            return;
        }
        throw error;
    }
    if (token !== state.requests.cheatSheet) {
        return;
    }

    const record = sheet?.cheat_sheet;
    setText(byId('cheat-sheet-title'), typeof record?.name === 'string' ? record.name : 'Cheat sheet');
    const description = byId('cheat-sheet-description');
    setText(description, typeof record?.description === 'string' ? record.description : '');
    description.hidden = !description.textContent;
    const items = validItems(cards?.items).filter((item) => isValidId(item?.reference_card_id));
    const total = cards?.total ?? 0;
    state.reference.sheetCardCount = total;
    byId('cheat-sheet-cards').replaceChildren(...items.map((item) => renderCheatSheetCard(item, total)));
    byId('cheat-sheet-cards-empty').hidden = items.length > 0;
    setText(
        byId('cheat-sheet-cards-summary'),
        total > 0 ? `Showing all ${items.length} cards` : '',
    );
    const remove = byId('cheat-sheet-delete');
    delete remove.dataset.confirmed;
    setText(remove, 'Delete cheat sheet');
    detail.dataset.sheetId = String(Number(sheetId));
    detail.hidden = false;
}

async function loadReference() {
    await loadCheatSheets();
    await Promise.all([loadReferenceCards(), loadReferenceCardDetail(), loadCheatSheetDetail()]);
}

/**
 * Arm a destructive reference action on the first press; report whether it is now confirmed.
 */
function confirmReferenceAction(button, label) {
    if (button.dataset.confirmed === 'true') {
        return true;
    }
    button.dataset.confirmed = 'true';
    setText(button, `Confirm: ${label}`);
    setNotice('This cannot be undone. Press the button again to confirm.', true);

    return false;
}

async function handleReferenceAction(button) {
    const action = button.dataset.referenceAction;
    // Act on what is on screen, never on a selection whose detail has not rendered yet.
    const cardId = shownReferenceId(byId('reference-card-detail'), 'cardId');
    const sheetId = shownReferenceId(byId('cheat-sheet-detail'), 'sheetId');

    if (action === 'open-card' && isValidId(button.dataset.cardId)) {
        state.reference.openCardId = Number(button.dataset.cardId);
        await loadReferenceCardDetail();
        byId('reference-card-title').scrollIntoView({ block: 'nearest' });
        byId('reference-card-title').focus?.();
        return;
    }
    if (action === 'open-sheet' && isValidId(button.dataset.sheetId)) {
        state.reference.openSheetId = Number(button.dataset.sheetId);
        await loadCheatSheetDetail();
        byId('cheat-sheet-title').scrollIntoView({ block: 'nearest' });
        return;
    }

    if (['move-up', 'move-down', 'remove-from-sheet'].includes(action)) {
        const url = buildCheatSheetCardUrl(sheetId, button.dataset.cardId);
        if (url === '') {
            return;
        }
        if (action === 'remove-from-sheet') {
            await requestApi(url, { method: 'DELETE' });
            setNotice('Card removed from the cheat sheet.');
        } else {
            const position = nudgedPosition(
                button.dataset.position,
                action === 'move-up' ? 'up' : 'down',
                state.reference.sheetCardCount,
            );
            if (position === null) {
                return;
            }
            await requestApi(url, { method: 'PATCH', body: JSON.stringify({ position }) });
            setNotice(action === 'move-up' ? 'Card moved up.' : 'Card moved down.');
        }
        await Promise.all([loadCheatSheets(), loadCheatSheetDetail()]);
        return;
    }

    if (action === 'delete-sheet' && isValidId(sheetId)) {
        if (!confirmReferenceAction(button, 'delete cheat sheet')) {
            return;
        }
        await requestApi(`/api/cheat-sheets/${Number(sheetId)}`, { method: 'DELETE' });
        state.reference.openSheetId = null;
        if (Number(state.reference.filters.cheatSheetId) === Number(sheetId)) {
            state.reference.filters.cheatSheetId = '';
        }
        setNotice('Cheat sheet deleted. Its cards are unchanged.');
        await loadReference();
        return;
    }

    if (!isValidId(cardId)) {
        return;
    }
    if (action === 'edit-card' && state.reference.openCard) {
        startEditingReferenceCard(state.reference.openCard);
        return;
    }
    if (action === 'delete-card') {
        if (!confirmReferenceAction(button, 'delete card')) {
            return;
        }
        await requestApi(`/api/reference-cards/${Number(cardId)}`, { method: 'DELETE' });
        state.reference.openCardId = null;
        if (state.reference.editingCardId === Number(cardId)) {
            resetReferenceCardForm();
        }
        setNotice('Card deleted.');
        await loadReference();
        return;
    }
    if (action === 'attach-tag' || action === 'detach-tag') {
        const tagId = action === 'attach-tag' ? byId('reference-tag-picker').value : button.dataset.tagId;
        if (!isValidId(tagId)) {
            setNotice('Choose a tag before adding it.', true);
            return;
        }
        await requestApi(`/api/reference-cards/${Number(cardId)}/tags/${Number(tagId)}`, {
            method: action === 'attach-tag' ? 'POST' : 'DELETE',
            ...(action === 'attach-tag' ? { body: '{}' } : {}),
        });
        setNotice(action === 'attach-tag' ? 'Tag added to the card.' : 'Tag removed from the card.');
        await Promise.all([loadReferenceCards(), loadReferenceCardDetail()]);
        return;
    }
    if (action === 'add-to-sheet') {
        const targetSheet = byId('reference-sheet-picker').value;
        const url = buildCheatSheetCardUrl(targetSheet);
        if (url === '') {
            setNotice('Choose a cheat sheet before adding the card.', true);
            return;
        }
        await requestApi(url, { method: 'POST', body: JSON.stringify({ reference_card_id: Number(cardId) }) });
        setNotice('Card added to the cheat sheet.');
        await Promise.all([loadCheatSheets(), loadCheatSheetDetail()]);
    }
}

function bindReferenceEvents() {
    byId('reference-search-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        state.reference.filters = {
            query: String(form.get('q') ?? ''),
            cardType: String(form.get('card_type') ?? ''),
            tagId: String(form.get('tag') ?? ''),
            cheatSheetId: String(form.get('cheat_sheet') ?? ''),
        };
        state.reference.page = 1;
        await loadReferenceCards().catch(displayError);
    });

    byId('previous-reference-page').addEventListener('click', async () => {
        if (state.reference.page > 1) {
            state.reference.page--;
            await loadReferenceCards().catch(displayError);
        }
    });

    byId('next-reference-page').addEventListener('click', async () => {
        if (state.reference.page * REFERENCE_PAGE_SIZE < state.reference.total) {
            state.reference.page++;
            await loadReferenceCards().catch(displayError);
        }
    });

    byId('reference-card-type-input').addEventListener('change', (event) => {
        byId('reference-dialogue-fieldset').hidden = event.currentTarget.value !== 'dialogue_pattern';
    });

    byId('reference-card-cancel').addEventListener('click', () => {
        resetReferenceCardForm();
    });

    byId('reference-card-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const values = Object.fromEntries(new FormData(event.currentTarget));
        const editingId = state.reference.editingCardId;
        try {
            const data = await requestApi(
                isValidId(editingId) ? `/api/reference-cards/${Number(editingId)}` : '/api/reference-cards',
                {
                    method: isValidId(editingId) ? 'PATCH' : 'POST',
                    body: JSON.stringify(buildReferenceCardBody(values)),
                },
            );
            resetReferenceCardForm();
            if (isValidId(data?.card?.id)) {
                state.reference.openCardId = Number(data.card.id);
            }
            setNotice(isValidId(editingId) ? 'Card saved.' : 'Card created.');
            await loadReference();
        } catch (error) {
            displayError(error);
        }
    });

    byId('cheat-sheet-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const formElement = event.currentTarget;
        const form = new FormData(formElement);
        try {
            const data = await requestApi('/api/cheat-sheets', {
                method: 'POST',
                body: JSON.stringify({ name: form.get('name') }),
            });
            formElement.reset();
            if (isValidId(data?.cheat_sheet?.id)) {
                state.reference.openSheetId = Number(data.cheat_sheet.id);
            }
            setNotice('Cheat sheet created.');
            await Promise.all([loadCheatSheets(), loadCheatSheetDetail()]);
        } catch (error) {
            displayError(error);
        }
    });

    byId('reference-view').addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-reference-action]');
        if (!button || button.disabled) {
            return;
        }
        try {
            await handleReferenceAction(button);
        } catch (error) {
            displayError(error);
        }
    });
}

const VIEW_LOADERS = {
    dashboard: loadDashboard,
    inbox: loadTodos,
    review: loadReviewQueue,
    projects: () => loadRecords('project'),
    notebooks: () => loadRecords('notebook'),
    libraries: loadLibraries,
    reference: loadReference,
    activity: loadActivity,
    collaboration: loadCollaboration,
};

async function refreshWorkspace() {
    await Promise.all([loadDashboard(), loadTags(), loadSectionOptions()]);
    if (state.view !== 'dashboard') {
        await VIEW_LOADERS[state.view]();
    }
}

async function selectView(view) {
    const target = Object.hasOwn(VIEW_LOADERS, view) ? view : 'dashboard';
    state.view = target;
    for (const name of Object.keys(VIEW_LOADERS)) {
        byId(`${name}-view`).hidden = name !== target;
        for (const button of document.querySelectorAll(`[data-view="${name}"]`)) {
            button.classList.toggle('is-current', name === target);
            button.setAttribute('aria-pressed', String(name === target));
        }
    }

    try {
        await VIEW_LOADERS[target]();
    } catch (error) {
        displayError(error);
    }
}

async function initializeWorkspace(email, userId) {
    state.userId = isValidId(userId) ? Number(userId) : null;
    showWorkspace(email);
    try {
        await Promise.all([loadDashboard(), loadTags(), loadSectionOptions()]);
    } catch (error) {
        displayError(error);
    }
}

function bindEvents() {
    bindReferenceEvents();

    byId('login-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const formElement = event.currentTarget;
        const form = new FormData(formElement);
        try {
            const data = await requestApi('/api/auth/login', {
                method: 'POST',
                body: JSON.stringify({
                    email: form.get('email'),
                    password: form.get('password'),
                }),
            });
            await initializeWorkspace(data?.user?.email ?? '', data?.user?.id);
            formElement.reset();
        } catch (error) {
            setNotice(error.message, true);
        }
    });

    byId('logout-button').addEventListener('click', async () => {
        try {
            await requestApi('/api/auth/logout', { method: 'POST', body: '{}' });
            showLogin('You have signed out.');
        } catch (error) {
            displayError(error);
        }
    });

    byId('capture-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const formElement = event.currentTarget;
        const form = new FormData(formElement);
        try {
            await requestApi('/api/todos', {
                method: 'POST',
                body: JSON.stringify({
                    title: form.get('title'),
                    notes: form.get('notes') || null,
                }),
            });
            formElement.reset();
            setNotice('Added to your inbox.');
            await refreshWorkspace();
        } catch (error) {
            displayError(error);
        }
    });

    byId('filter-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        state.filters = {
            query: String(form.get('q') ?? ''),
            status: String(form.get('status') ?? ''),
            tagId: String(form.get('tag') ?? ''),
        };
        state.page = 1;
        await loadTodos().catch(displayError);
    });

    byId('previous-page').addEventListener('click', async () => {
        if (state.page > 1) {
            state.page--;
            await loadTodos().catch(displayError);
        }
    });

    byId('next-page').addEventListener('click', async () => {
        if (state.page * TODO_PAGE_SIZE < state.total) {
            state.page++;
            await loadTodos().catch(displayError);
        }
    });

    byId('tag-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const formElement = event.currentTarget;
        const form = new FormData(formElement);
        try {
            await requestApi('/api/tags', {
                method: 'POST',
                body: JSON.stringify({ name: form.get('name') }),
            });
            formElement.reset();
            await loadTags();
            if (state.view === 'inbox') {
                await loadTodos();
            }
            setNotice('Tag added.');
        } catch (error) {
            displayError(error);
        }
    });

    for (const kind of Object.keys(RECORD_KINDS)) {
        const config = RECORD_KINDS[kind];
        byId(`${kind}-form`).addEventListener('submit', async (event) => {
            event.preventDefault();
            const formElement = event.currentTarget;
            const form = new FormData(formElement);
            try {
                await requestApi(config.path, {
                    method: 'POST',
                    body: JSON.stringify({
                        name: form.get('name'),
                        description: form.get('description') || null,
                    }),
                });
                formElement.reset();
                state.records[kind].page = 1;
                await loadSectionOptions();
                await loadRecords(kind);
                setNotice(`${config.noun} added.`);
            } catch (error) {
                displayError(error);
            }
        });

        byId(`${kind}-section-form`).addEventListener('submit', async (event) => {
            event.preventDefault();
            const openId = state.records[kind].openId;
            if (!isValidId(openId)) {
                setNotice(`Open a ${config.noun} before adding a section.`, true);
                return;
            }
            const formElement = event.currentTarget;
            const form = new FormData(formElement);
            try {
                await requestApi(`${config.path}/${Number(openId)}/sections`, {
                    method: 'POST',
                    body: JSON.stringify({ name: form.get('name') }),
                });
                formElement.reset();
                await loadSectionOptions();
                await loadRecords(kind);
                setNotice('Section added.');
            } catch (error) {
                displayError(error);
            }
        });

        byId(config.ids.previous).addEventListener('click', async () => {
            if (state.records[kind].page > 1) {
                state.records[kind].page--;
                await loadRecords(kind).catch(displayError);
            }
        });

        byId(config.ids.next).addEventListener('click', async () => {
            const records = state.records[kind];
            if (records.page * config.pageSize < records.total) {
                records.page++;
                await loadRecords(kind).catch(displayError);
            }
        });
    }

    byId('previous-library-page').addEventListener('click', async () => {
        if (state.libraries.page > 1) {
            state.libraries.page--;
            await loadLibraries().catch(displayError);
        }
    });

    byId('next-library-page').addEventListener('click', async () => {
        if (state.libraries.page * LIBRARY_PAGE_SIZE < state.libraries.total) {
            state.libraries.page++;
            await loadLibraries().catch(displayError);
        }
    });

    byId('previous-activity-page').addEventListener('click', async () => {
        if (state.activity.page > 1) {
            state.activity.page--;
            await loadActivity().catch(displayError);
        }
    });

    byId('next-activity-page').addEventListener('click', async () => {
        if (state.activity.page * ACTIVITY_PAGE_SIZE < state.activity.total) {
            state.activity.page++;
            await loadActivity().catch(displayError);
        }
    });

    byId('collaboration-resource-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        await loadCapabilities().catch(showCollaborationError);
    });

    byId('capability-grant-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const resource = state.collaboration.resource;
        const subjectId = byId('capability-subject-id').value;
        if (!resource || !isValidId(subjectId)) {
            setNotice('Choose a resource and enter a valid user ID.', true);
            return;
        }
        if (Number(subjectId) === state.userId) {
            setNotice('You cannot grant a capability to yourself.', true);
            return;
        }
        try {
            await requestApi('/api/capabilities', {
                method: 'POST',
                body: JSON.stringify({
                    resource_type: resource.type,
                    resource_id: resource.id,
                    subject_user_id: Number(subjectId),
                    capability: byId('capability-name').value,
                }),
            });
            byId('capability-subject-id').value = '';
            await loadCapabilities();
            setNotice('Capability granted.');
        } catch (error) {
            showCollaborationError(error);
        }
    });

    byId('cross-context-request-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const formElement = event.currentTarget;
        const sourceId = byId('request-source-id').value;
        const targetId = byId('request-target-id').value;
        if (!isValidId(sourceId) || !isValidId(targetId)) {
            setNotice('Enter valid source and target context IDs.', true);
            return;
        }
        try {
            await requestApi('/api/cross-context-requests', {
                method: 'POST',
                body: JSON.stringify({
                    source_type: byId('request-source-type').value,
                    source_id: Number(sourceId),
                    target_type: byId('request-target-type').value,
                    target_id: Number(targetId),
                    request_type: byId('request-type').value,
                    title: byId('request-title').value,
                    body: byId('request-body').value || null,
                }),
            });
            formElement.reset();
            await loadCollaboration();
            setNotice('Cross-context request sent.');
        } catch (error) {
            showCollaborationError(error);
        }
    });

    byId('request-inbox-list').addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-callback-request]');
        if (!form) {
            return;
        }
        event.preventDefault();
        const requestId = form.dataset.callbackRequest;
        const summary = form.querySelector('textarea')?.value.trim() ?? '';
        if (!isValidId(requestId) || summary === '') {
            setNotice('Enter a callback summary before sending.', true);
            return;
        }
        try {
            const data = await requestApi(`/api/cross-context-requests/${Number(requestId)}/callback`, {
                method: 'POST',
                body: JSON.stringify({ summary }),
            });
            state.collaboration.inbox = state.collaboration.inbox.map((request) =>
                Number(request.id) === Number(requestId) ? data.request : request,
            );
            byId('request-inbox-list').replaceChildren(
                ...state.collaboration.inbox.map((request) => renderCrossContextRequest(request, 'inbox')),
            );
            setNotice('Callback sent.');
        } catch (error) {
            showCollaborationError(error);
        }
    });

    byId('todo-parent-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const openId = state.todoDetail.id;
        if (!isValidId(openId)) {
            return;
        }
        const chosen = byId('todo-parent-select').value;
        try {
            await requestApi(buildTodoActionUrl(openId, 'parent'), {
                method: 'PATCH',
                body: JSON.stringify({ parent_todo_id: isValidId(chosen) ? Number(chosen) : null }),
            });
            setNotice(isValidId(chosen) ? 'Parent set.' : 'Parent cleared.');
            await loadTodoDetail();
        } catch (error) {
            displayError(error);
        }
    });

    byId('todo-relate-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const openId = state.todoDetail.id;
        const chosen = byId('todo-related-select').value;
        const url = buildRelatedTodoUrl(openId, chosen);
        if (url === '') {
            setNotice('Choose another ToDo to link.', true);
            return;
        }
        try {
            await requestApi(url, { method: 'POST', body: '{}' });
            setNotice('ToDos linked.');
            await loadTodoDetail();
        } catch (error) {
            displayError(error);
        }
    });

    byId('todo-objective-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const openId = state.todoDetail.id;
        if (!isValidId(openId)) {
            return;
        }
        const objective = byId('todo-objective').value.trim();
        try {
            await requestApi(buildTodoActionUrl(openId, 'objective'), {
                method: 'PATCH',
                body: JSON.stringify({ terminal_objective: objective === '' ? null : objective }),
            });
            setNotice(objective === '' ? 'Objective cleared.' : 'Objective saved.');
            await loadTodoDetail();
        } catch (error) {
            displayError(error);
        }
    });

    byId('todo-result-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const openId = state.todoDetail.id;
        if (!isValidId(openId)) {
            return;
        }
        try {
            await requestApi(buildTodoActionUrl(openId, 'result'), {
                method: 'POST',
                body: JSON.stringify({ result: byId('todo-result').value }),
            });
            setNotice('Result reported.');
            await loadTodoDetail();
        } catch (error) {
            displayError(error);
        }
    });

    for (const button of document.querySelectorAll('[data-view]')) {
        button.addEventListener('click', () => {
            void selectView(button.dataset.view);
        });
    }

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('button');
        if (!button) {
            return;
        }

        if (button.dataset.deleteTag) {
            try {
                await requestApi(`/api/tags/${button.dataset.deleteTag}`, { method: 'DELETE' });
                await refreshWorkspace();
                setNotice('Tag deleted.');
            } catch (error) {
                displayError(error);
            }
            return;
        }

        if (isValidId(button.dataset.revokeGrant) && state.collaboration.resource) {
            try {
                await requestApi(`/api/capabilities/${Number(button.dataset.revokeGrant)}`, { method: 'DELETE' });
                await loadCapabilities();
                setNotice('Capability revoked.');
            } catch (error) {
                showCollaborationError(error);
            }
            return;
        }

        const requestAction = button.dataset.requestAction;
        if (['accept', 'reject'].includes(requestAction) && isValidId(button.dataset.requestId)) {
            const requestId = Number(button.dataset.requestId);
            try {
                const data = await requestApi(
                    `/api/cross-context-requests/${requestId}/${requestAction}`,
                    { method: 'POST', body: '{}' },
                );
                if (requestAction === 'accept') {
                    state.collaboration.inbox = state.collaboration.inbox.map((request) =>
                        Number(request.id) === requestId ? data.request : request,
                    );
                } else {
                    state.collaboration.inbox = state.collaboration.inbox.filter(
                        (request) => Number(request.id) !== requestId,
                    );
                }
                byId('request-inbox-list').replaceChildren(
                    ...state.collaboration.inbox.map((request) => renderCrossContextRequest(request, 'inbox')),
                );
                byId('request-inbox-empty').hidden = state.collaboration.inbox.length > 0;
                setNotice(requestAction === 'accept' ? 'Request accepted.' : 'Request declined.');
            } catch (error) {
                showCollaborationError(error);
            }
            return;
        }

        const recordAction = button.dataset.recordAction;
        const recordKind = button.dataset.recordKind;
        if (recordAction && Object.hasOwn(RECORD_KINDS, recordKind) && isValidId(button.dataset.recordId)) {
            const config = RECORD_KINDS[recordKind];
            const recordId = Number(button.dataset.recordId);
            try {
                if (recordAction === 'open') {
                    state.records[recordKind].openId = recordId;
                    await loadRecordDetail(recordKind);
                    byId(config.ids.detailTitle).scrollIntoView({ block: 'nearest' });
                    return;
                }
                if (recordAction === 'rename') {
                    const input = button.parentElement.querySelector('[data-record-name-input]');
                    await requestApi(`${config.path}/${recordId}`, {
                        method: 'PATCH',
                        body: JSON.stringify({ name: input?.value ?? '' }),
                    });
                    setNotice(`${config.noun} renamed.`);
                } else if (recordAction === 'delete') {
                    await requestApi(`${config.path}/${recordId}`, { method: 'DELETE' });
                    if (state.records[recordKind].openId === recordId) {
                        state.records[recordKind].openId = null;
                    }
                    setNotice(`${config.noun} deleted.`);
                } else {
                    return;
                }
                await loadSectionOptions();
                await loadRecords(recordKind);
            } catch (error) {
                displayError(error);
            }
            return;
        }

        const sectionAction = button.dataset.sectionAction;
        const sectionKind = button.dataset.sectionKind;
        if (
            sectionAction
            && Object.hasOwn(RECORD_KINDS, sectionKind)
            && isValidId(button.dataset.sectionId)
            && isValidId(state.records[sectionKind].openId)
        ) {
            const config = RECORD_KINDS[sectionKind];
            const parentId = Number(state.records[sectionKind].openId);
            const sectionId = Number(button.dataset.sectionId);
            const sectionPath = `${config.path}/${parentId}/sections/${sectionId}`;
            try {
                if (sectionAction === 'rename') {
                    const input = button.parentElement.querySelector('[data-section-name-input]');
                    await requestApi(sectionPath, {
                        method: 'PATCH',
                        body: JSON.stringify({ name: input?.value ?? '' }),
                    });
                    setNotice('Section renamed.');
                } else if (sectionAction === 'delete') {
                    await requestApi(sectionPath, { method: 'DELETE' });
                    setNotice('Section deleted.');
                } else {
                    return;
                }
                await loadSectionOptions();
                await loadRecords(sectionKind);
            } catch (error) {
                displayError(error);
            }
            return;
        }

        if (button.dataset.libraryAction === 'open' && isValidId(button.dataset.libraryId)) {
            state.libraries.openId = Number(button.dataset.libraryId);
            try {
                await loadLibraryDetail();
                byId('library-detail-title').scrollIntoView({ block: 'nearest' });
            } catch (error) {
                displayError(error);
            }
            return;
        }

        const todoId = button.dataset.todoId;
        const action = button.dataset.todoAction;
        if (!isValidId(todoId) || !action) {
            return;
        }

        if (action === 'open-detail') {
            state.todoDetail.id = Number(todoId);
            try {
                await loadTodoDetail();
                byId('todo-detail-title').scrollIntoView({ block: 'nearest' });
            } catch (error) {
                displayError(error);
            }
            return;
        }

        // A destructive action is armed by the first press and only sent by the second, so
        // one misdirected click can never destroy a ToDo.
        if (requiresConfirmation(action) && button.dataset.confirmed !== 'true') {
            button.dataset.confirmed = 'true';
            setText(button, `Confirm: ${LIFECYCLE_ACTIONS[action].label.toLowerCase()}`);
            setNotice('This cannot be undone. Press the button again to confirm.', true);
            return;
        }

        try {
            if (action === 'detach-tag') {
                if (!isValidId(button.dataset.tagId)) {
                    return;
                }
                await requestApi(`/api/todos/${todoId}/tags/${Number(button.dataset.tagId)}`, {
                    method: 'DELETE',
                });
            } else if (action === 'complete' || action === 'activate') {
                await requestApi(`/api/todos/${todoId}/${action}`, { method: 'POST', body: '{}' });
            } else if (action === 'reviewed') {
                await requestApi(`/api/todos/${todoId}/reviewed`, { method: 'POST', body: '{}' });
            } else if (action === 'archive' || action === 'trash' || action === 'restore') {
                await requestApi(buildTodoActionUrl(todoId, action), { method: 'POST', body: '{}' });
            } else if (action === 'permanent') {
                await requestApi(buildTodoActionUrl(todoId, action), { method: 'DELETE' });
                state.todoDetail.id = null;
                byId('todo-detail').hidden = true;
            } else if (action === 'unrelate') {
                const url = buildRelatedTodoUrl(todoId, button.dataset.relatedId);
                if (url === '') {
                    return;
                }
                await requestApi(url, { method: 'DELETE' });
            } else if (action === 'snooze') {
                const input = button.parentElement.querySelector('[data-snooze-days]');
                await requestApi(buildTodoActionUrl(todoId, 'snooze'), {
                    method: 'POST',
                    body: JSON.stringify(buildSnoozeBody(input?.value)),
                });
            } else if (action === 'attach-tag') {
                const picker = button.parentElement.querySelector('[data-tag-picker]');
                if (!isValidId(picker?.value)) {
                    setNotice('Choose a tag before attaching it.', true);
                    return;
                }
                await requestApi(`/api/todos/${todoId}/tags/${Number(picker.value)}`, {
                    method: 'POST',
                    body: '{}',
                });
            } else if (action === 'assign-section') {
                const picker = button.parentElement.querySelector('[data-section-picker]');
                const choice = picker?.value ?? '';
                if (choice !== '' && parseSectionOptionValue(choice) === null) {
                    setNotice('Choose a section before moving this ToDo.', true);
                    return;
                }
                // Both section fields are sent, so a move between a Project section and a
                // Notebook section clears one assignment and sets the other in one request.
                await requestApi(`/api/todos/${todoId}`, {
                    method: 'PATCH',
                    body: JSON.stringify(buildSectionAssignmentBody(choice)),
                });
            } else {
                return;
            }
            setNotice(action === 'permanent' ? 'ToDo permanently deleted.' : 'ToDo updated.');
            await refreshWorkspace();
        } catch (error) {
            displayError(error);
        }
    });
}

async function start() {
    bindEvents();
    try {
        const data = await requestApi('/api/auth/me');
        await initializeWorkspace(data?.user?.email ?? '', data?.user?.id);
    } catch (error) {
        if (error.status === 401) {
            showLogin();
        } else {
            showLogin('Could not check your session. Please try again.');
        }
    }
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        void start();
    });
}
