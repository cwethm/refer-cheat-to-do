export const TODO_PAGE_SIZE = 50;
export const PROJECT_PAGE_SIZE = 20;

/**
 * Highest number of Projects whose sections are offered in a ToDo's section picker.
 *
 * The API caps `limit` at 100, so the picker covers at most the newest 100 Projects.
 */
export const SECTION_SOURCE_LIMIT = 100;

export function buildProjectsUrl({ page = 1, limit = PROJECT_PAGE_SIZE } = {}) {
    const requestedPage = Number(page);
    const safePage = Number.isFinite(requestedPage) ? Math.max(1, Math.trunc(requestedPage)) : 1;
    const requestedLimit = Number(limit);
    const safeLimit = Number.isFinite(requestedLimit)
        ? Math.min(SECTION_SOURCE_LIMIT, Math.max(1, Math.trunc(requestedLimit)))
        : PROJECT_PAGE_SIZE;
    const params = new URLSearchParams({ page: String(safePage), limit: String(safeLimit) });

    return `/api/projects?${params.toString()}`;
}

/**
 * Label a section in the context of its Project, for pickers and assignment summaries.
 */
export function formatSectionLabel(projectName, sectionName) {
    const project = typeof projectName === 'string' ? projectName.trim() : '';
    const section = typeof sectionName === 'string' ? sectionName.trim() : '';

    if (project && section) {
        return `${project} · ${section}`;
    }

    return section || project || 'Untitled section';
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
    page: 1,
    total: 0,
    tags: [],
    view: 'dashboard',
    filters: { query: '', status: '', tagId: '' },
    projects: [],
    projectPage: 1,
    projectTotal: 0,
    openProjectId: null,
    sectionOptions: [],
    // Monotonic tokens so a slow response cannot repaint a list that a newer
    // request has already replaced, which would silently discard a person's selection.
    requests: { todos: 0, review: 0, projects: 0 },
};

function setNotice(message, isError = false) {
    const notice = byId('notice');
    setText(notice, message);
    notice.classList.toggle('is-error', isError);
    notice.setAttribute('role', isError ? 'alert' : 'status');
    notice.setAttribute('aria-live', isError ? 'assertive' : 'polite');
}

function showLogin(message = '') {
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

function sectionLabelById(sectionId) {
    const option = state.sectionOptions.find((entry) => String(entry.id) === String(sectionId));

    return option ? option.label : null;
}

/**
 * Describe where a ToDo currently lives. A ToDo has at most one organizational section,
 * so at most one of the two section fields is ever set.
 */
function sectionAssignmentText(todo) {
    if (isValidId(todo?.project_section_id)) {
        return `In ${sectionLabelById(todo.project_section_id) ?? 'a Project section'}`;
    }
    if (isValidId(todo?.notebook_section_id)) {
        return 'In a Notebook section';
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
        const label = make('label', '', 'Project section');
        const select = make('select');
        select.setAttribute('aria-label', `Project section for ${todoTitle}`);
        select.dataset.sectionPicker = '';
        select.append(new Option('No section', ''));
        for (const option of state.sectionOptions) {
            select.append(new Option(option.label, String(option.id)));
        }
        select.value = isValidId(todo?.project_section_id) ? String(Number(todo.project_section_id)) : '';
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
    const filter = byId('tag-filter');
    const selected = filter.value;
    filter.replaceChildren(new Option('Any tag', ''));

    for (const tag of state.tags) {
        if (isValidId(tag?.id) && typeof tag?.name === 'string') {
            filter.append(new Option(tag.name, String(tag.id)));
        }
    }
    filter.value = state.tags.some((tag) => String(tag.id) === selected) ? selected : '';

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
    state.total = Number(data?.meta?.pagination?.total) || 0;
    const list = byId('todo-list');
    list.replaceChildren(...items.map((todo) => renderTodo(todo)));
    byId('todo-empty').hidden = items.length > 0;

    const pageCount = Math.max(1, Math.ceil(state.total / TODO_PAGE_SIZE));
    setText(byId('todo-summary'), `Showing ${items.length} of ${state.total} ToDos`);
    setText(byId('page-label'), `Page ${state.page} of ${pageCount}`);
    byId('previous-page').disabled = state.page <= 1;
    byId('next-page').disabled = state.page >= pageCount;
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
 * Build the flat list of Project sections offered when moving a ToDo.
 *
 * Sections are read per Project because the API exposes them that way; the picker
 * therefore covers the newest `SECTION_SOURCE_LIMIT` Projects.
 */
async function loadSectionOptions() {
    const data = await requestApi(buildProjectsUrl({ page: 1, limit: SECTION_SOURCE_LIMIT }));
    const projects = validItems(data?.items).filter((project) => isValidId(project?.id));
    const responses = await Promise.all(
        projects.map((project) => requestApi(`/api/projects/${Number(project.id)}/sections`)),
    );

    const options = [];
    responses.forEach((response, index) => {
        for (const section of validItems(response?.items)) {
            if (isValidId(section?.id)) {
                options.push({
                    id: Number(section.id),
                    label: formatSectionLabel(projects[index]?.name, section?.name),
                });
            }
        }
    });
    state.sectionOptions = options;
}

function renderProject(project) {
    const item = make('li', 'record-item');
    const heading = make('div', 'record-heading');
    const name = typeof project?.name === 'string' ? project.name : 'Untitled Project';
    heading.append(make('h3', '', name));
    item.append(heading);

    if (typeof project?.description === 'string' && project.description !== '') {
        item.append(make('p', 'record-description', project.description));
    }

    if (!isValidId(project?.id)) {
        return item;
    }
    const projectId = String(Number(project.id));

    const actions = make('div', 'record-actions');
    const label = make('label', '', 'Rename');
    const input = make('input');
    input.type = 'text';
    input.maxLength = 255;
    input.value = name;
    input.dataset.projectNameInput = '';
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
        button.dataset.projectAction = action;
        button.dataset.projectId = projectId;
        button.setAttribute('aria-label', description);
        actions.append(button);
    }
    item.append(actions);

    return item;
}

function renderSection(section) {
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
    rename.dataset.sectionId = String(Number(section.id));
    rename.setAttribute('aria-label', `Save the new name for section ${name}`);

    const remove = make('button', 'button button-danger', 'Delete');
    remove.type = 'button';
    remove.dataset.sectionAction = 'delete';
    remove.dataset.sectionId = String(Number(section.id));
    remove.setAttribute('aria-label', `Delete section ${name}`);

    actions.append(label, rename, remove);
    item.append(actions);

    return item;
}

async function loadProjectDetail() {
    const detail = byId('project-detail');
    if (!isValidId(state.openProjectId)) {
        detail.hidden = true;
        return;
    }

    const data = await requestApi(`/api/projects/${Number(state.openProjectId)}`);
    const project = data?.project;
    setText(byId('project-detail-title'), typeof project?.name === 'string' ? project.name : 'Project');
    const description = byId('project-detail-description');
    setText(description, typeof project?.description === 'string' ? project.description : '');
    description.hidden = !description.textContent;

    const sections = validItems(project?.sections);
    byId('section-list').replaceChildren(...sections.map((section) => renderSection(section)));
    byId('section-empty').hidden = sections.length > 0;
    detail.hidden = false;
}

async function loadProjects() {
    const token = ++state.requests.projects;
    const data = await requestApi(buildProjectsUrl({ page: state.projectPage }));
    if (token !== state.requests.projects) {
        return;
    }
    const items = validItems(data?.items);
    state.projects = items;
    state.projectTotal = Number(data?.meta?.pagination?.total) || 0;
    byId('project-list').replaceChildren(...items.map((project) => renderProject(project)));
    byId('project-empty').hidden = items.length > 0;

    const pageCount = Math.max(1, Math.ceil(state.projectTotal / PROJECT_PAGE_SIZE));
    setText(byId('project-summary'), `Showing ${items.length} of ${state.projectTotal} Projects`);
    setText(byId('project-page-label'), `Projects page ${state.projectPage} of ${pageCount}`);
    byId('previous-project-page').disabled = state.projectPage <= 1;
    byId('next-project-page').disabled = state.projectPage >= pageCount;

    await loadProjectDetail();
}

async function refreshWorkspace() {
    await Promise.all([loadDashboard(), loadTags(), loadSectionOptions()]);
    if (state.view === 'inbox') {
        await loadTodos();
    } else if (state.view === 'review') {
        await loadReviewQueue();
    } else if (state.view === 'projects') {
        await loadProjects();
    }
}

async function selectView(view) {
    state.view = view;
    for (const name of ['dashboard', 'inbox', 'projects', 'review']) {
        byId(`${name}-view`).hidden = name !== view;
        for (const button of document.querySelectorAll(`[data-view="${name}"]`)) {
            button.classList.toggle('is-current', name === view);
            button.setAttribute('aria-pressed', String(name === view));
        }
    }

    try {
        if (view === 'inbox') {
            await loadTodos();
        } else if (view === 'review') {
            await loadReviewQueue();
        } else if (view === 'projects') {
            await loadProjects();
        } else {
            await loadDashboard();
        }
    } catch (error) {
        displayError(error);
    }
}

async function initializeWorkspace(email) {
    showWorkspace(email);
    try {
        await Promise.all([loadDashboard(), loadTags(), loadSectionOptions()]);
    } catch (error) {
        displayError(error);
    }
}

function bindEvents() {
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
            await initializeWorkspace(data?.user?.email ?? '');
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

    byId('project-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const formElement = event.currentTarget;
        const form = new FormData(formElement);
        try {
            await requestApi('/api/projects', {
                method: 'POST',
                body: JSON.stringify({
                    name: form.get('name'),
                    description: form.get('description') || null,
                }),
            });
            formElement.reset();
            state.projectPage = 1;
            await loadSectionOptions();
            await loadProjects();
            setNotice('Project added.');
        } catch (error) {
            displayError(error);
        }
    });

    byId('section-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!isValidId(state.openProjectId)) {
            setNotice('Open a Project before adding a section.', true);
            return;
        }
        const formElement = event.currentTarget;
        const form = new FormData(formElement);
        try {
            await requestApi(`/api/projects/${Number(state.openProjectId)}/sections`, {
                method: 'POST',
                body: JSON.stringify({ name: form.get('name') }),
            });
            formElement.reset();
            await loadSectionOptions();
            await loadProjects();
            setNotice('Section added.');
        } catch (error) {
            displayError(error);
        }
    });

    byId('previous-project-page').addEventListener('click', async () => {
        if (state.projectPage > 1) {
            state.projectPage--;
            await loadProjects().catch(displayError);
        }
    });

    byId('next-project-page').addEventListener('click', async () => {
        if (state.projectPage * PROJECT_PAGE_SIZE < state.projectTotal) {
            state.projectPage++;
            await loadProjects().catch(displayError);
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

        const projectAction = button.dataset.projectAction;
        if (projectAction && isValidId(button.dataset.projectId)) {
            const projectId = Number(button.dataset.projectId);
            try {
                if (projectAction === 'open') {
                    state.openProjectId = projectId;
                    await loadProjectDetail();
                    byId('project-detail-title').scrollIntoView({ block: 'nearest' });
                    return;
                }
                if (projectAction === 'rename') {
                    const input = button.parentElement.querySelector('[data-project-name-input]');
                    await requestApi(`/api/projects/${projectId}`, {
                        method: 'PATCH',
                        body: JSON.stringify({ name: input?.value ?? '' }),
                    });
                    setNotice('Project renamed.');
                } else if (projectAction === 'delete') {
                    await requestApi(`/api/projects/${projectId}`, { method: 'DELETE' });
                    if (state.openProjectId === projectId) {
                        state.openProjectId = null;
                    }
                    setNotice('Project deleted.');
                } else {
                    return;
                }
                await loadSectionOptions();
                await loadProjects();
            } catch (error) {
                displayError(error);
            }
            return;
        }

        const sectionAction = button.dataset.sectionAction;
        if (sectionAction && isValidId(button.dataset.sectionId) && isValidId(state.openProjectId)) {
            const projectId = Number(state.openProjectId);
            const sectionId = Number(button.dataset.sectionId);
            try {
                if (sectionAction === 'rename') {
                    const input = button.parentElement.querySelector('[data-section-name-input]');
                    await requestApi(`/api/projects/${projectId}/sections/${sectionId}`, {
                        method: 'PATCH',
                        body: JSON.stringify({ name: input?.value ?? '' }),
                    });
                    setNotice('Section renamed.');
                } else if (sectionAction === 'delete') {
                    await requestApi(`/api/projects/${projectId}/sections/${sectionId}`, { method: 'DELETE' });
                    setNotice('Section deleted.');
                } else {
                    return;
                }
                await loadSectionOptions();
                await loadProjects();
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
                if (choice !== '' && !isValidId(choice)) {
                    setNotice('Choose a section before moving this ToDo.', true);
                    return;
                }
                await requestApi(`/api/todos/${todoId}`, {
                    method: 'PATCH',
                    body: JSON.stringify({
                        project_section_id: choice === '' ? null : Number(choice),
                    }),
                });
            } else {
                return;
            }
            setNotice('ToDo updated.');
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
        await initializeWorkspace(data?.user?.email ?? '');
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
