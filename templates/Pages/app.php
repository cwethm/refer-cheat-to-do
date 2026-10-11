<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 */
$this->disableAutoLayout();
?>
<!doctype html>
<html lang="en">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f7f2">
    <meta name="csrf-token" content="<?= h((string)$this->getRequest()->getAttribute('csrfToken', '')) ?>">
    <title>Cheat to do</title>
    <?= $this->Html->css('/css/app.css?v=' . filemtime(WWW_ROOT . 'css/app.css')) ?>
    <?= $this->Html->script('/js/app.mjs?v=' . filemtime(WWW_ROOT . 'js/app.mjs'), ['type' => 'module']) ?>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="site-header">
        <a class="brand" href="/app" aria-label="Cheat to do home">cheat<span>to</span>do</a>
        <div id="account-actions" class="account-actions" hidden>
            <span id="account-email" class="account-email"></span>
            <a id="export-link" class="button button-quiet" href="/api/export" download>Export my data</a>
            <button id="logout-button" class="button button-quiet" type="button">Sign out</button>
        </div>
    </header>
    <main id="main-content" class="page-shell">
        <p id="notice" class="notice" role="status" aria-live="polite">Checking your session…</p>
        <section id="login-panel" class="login-panel" aria-labelledby="login-title" hidden>
            <div class="eyebrow">A little more headspace</div>
            <h1 id="login-title">Pick up where you left off.</h1>
            <p class="intro">Sign in to capture, organize, and review what matters.</p>
            <form id="login-form" class="form-stack">
                <label for="login-email">Email</label>
                <input id="login-email" name="email" type="email" autocomplete="username" required>
                <label for="login-password">Password</label>
                <input id="login-password" name="password" type="password" autocomplete="current-password" required>
                <button class="button button-primary" type="submit">Sign in</button>
            </form>
            <p class="login-aside"><button id="forgot-password-button" class="text-button" type="button">Forgot your password?</button></p>
        </section>
        <section id="reset-request-panel" class="login-panel" aria-labelledby="reset-request-title" hidden>
            <div class="eyebrow">Locked out?</div>
            <h1 id="reset-request-title">Reset your password.</h1>
            <p class="intro">Enter the address you sign in with. If it has an account, we will email it a link that sets a new password. The link works once and expires within the hour.</p>
            <form id="reset-request-form" class="form-stack">
                <label for="reset-request-email">Email address</label>
                <input id="reset-request-email" name="email" type="email" autocomplete="username" maxlength="191" required>
                <button class="button button-primary" type="submit">Email me a reset link</button>
            </form>
            <p class="login-aside"><button class="text-button" type="button" data-account-back>Back to sign in</button></p>
        </section>
        <section id="account-link-panel" class="login-panel" aria-labelledby="account-link-title" hidden>
            <div class="eyebrow">Almost there</div>
            <h1 id="account-link-title">Choose a password.</h1>
            <p id="account-link-intro" class="intro"></p>
            <form id="account-link-form" class="form-stack" novalidate>
                <label for="account-link-password">New password</label>
                <input id="account-link-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="255" required aria-describedby="account-link-hint">
                <label for="account-link-confirm">Repeat the new password</label>
                <input id="account-link-confirm" name="confirm" type="password" autocomplete="new-password" minlength="8" maxlength="255" required>
                <p id="account-link-hint" class="result-summary">Use at least 8 characters.</p>
                <button id="account-link-submit" class="button button-primary" type="submit">Save password</button>
            </form>
            <p class="login-aside"><button class="text-button" type="button" data-account-back>Back to sign in</button></p>
        </section>
        <section id="workspace" hidden>
            <div class="welcome-row">
                <div>
                    <div class="eyebrow">Your workspace</div>
                    <h1>Make room for what matters.</h1>
                </div>
                <nav class="view-nav" aria-label="Workspace views">
                    <button class="view-button is-current" type="button" data-view="dashboard" aria-pressed="true">Today</button>
                    <button class="view-button" type="button" data-view="inbox" aria-pressed="false">ToDos</button>
                    <button class="view-button" type="button" data-view="projects" aria-pressed="false">Projects</button>
                    <button class="view-button" type="button" data-view="notebooks" aria-pressed="false">Notebooks</button>
                    <button class="view-button" type="button" data-view="libraries" aria-pressed="false">Libraries</button>
                    <button class="view-button" type="button" data-view="reference" aria-pressed="false">Reference</button>
                    <button class="view-button" type="button" data-view="review" aria-pressed="false">Review</button>
                    <button class="view-button" type="button" data-view="activity" aria-pressed="false">Activity</button>
                    <button class="view-button" type="button" data-view="collaboration" aria-pressed="false">Collaboration</button>
                    <button class="view-button" type="button" data-view="account" aria-pressed="false">Account</button>
                </nav>
            </div>
            <section id="dashboard-view" class="view-panel" aria-labelledby="dashboard-title">
                <h2 id="dashboard-title" class="visually-hidden">Today</h2>
                <div class="count-grid">
                    <button class="count-card" type="button" data-view="inbox">
                        <span class="count-label">In your inbox</span><strong id="inbox-count">—</strong>
                    </button>
                    <button class="count-card" type="button" data-view="inbox">
                        <span class="count-label">In progress</span><strong id="active-count">—</strong>
                    </button>
                    <button class="count-card" type="button" data-view="review">
                        <span class="count-label">Ready to review</span><strong id="review-count">—</strong>
                    </button>
                    <button class="count-card" type="button" data-view="review">
                        <span class="count-label">Need a little care</span><strong id="orphan-count">—</strong>
                    </button>
                </div>
                <div class="content-grid">
                    <section class="surface" aria-labelledby="capture-title">
                        <div class="section-heading">
                            <div><div class="eyebrow">Get it out of your head</div><h2 id="capture-title">Quick capture</h2></div>
                        </div>
                        <form id="capture-form" class="form-stack">
                            <label for="todo-title">What do you need to remember?</label>
                            <input id="todo-title" name="title" maxlength="255" required placeholder="A thought, a task, a question…">
                            <label for="todo-notes">Notes <span class="optional">(optional)</span></label>
                            <textarea id="todo-notes" name="notes" rows="3" placeholder="Add a detail while it’s fresh"></textarea>
                            <button class="button button-primary" type="submit">Add to inbox</button>
                        </form>
                    </section>
                    <section class="surface" aria-labelledby="recent-title">
                        <div class="section-heading"><div><div class="eyebrow">Recently touched</div><h2 id="recent-title">On your mind</h2></div></div>
                        <ul id="recent-todos" class="simple-list"></ul>
                        <p id="recent-empty" class="empty-copy" hidden>Your recent ToDos will show up here.</p>
                    </section>
                </div>
            </section>
            <section id="inbox-view" class="view-panel" aria-labelledby="todos-title" hidden>
                <div class="section-heading">
                    <div><div class="eyebrow">Capture and find</div><h2 id="todos-title">Your ToDos</h2></div>
                </div>
                <form id="filter-form" class="filter-bar">
                    <label class="search-field" for="todo-search">Search ToDos<input id="todo-search" name="q" type="search" placeholder="Search titles and notes"></label>
                    <label for="status-filter">Status
                        <select id="status-filter" name="status">
                            <option value="">All visible</option><option value="inbox">Inbox</option>
                            <option value="active">In progress</option><option value="done">Done</option>
                            <option value="archived">Archived</option><option value="trashed">In the trash</option>
                        </select>
                    </label>
                    <label for="tag-filter">Tag
                        <select id="tag-filter" name="tag"><option value="">Any tag</option></select>
                    </label>
                    <button class="button button-secondary" type="submit">Find ToDos</button>
                </form>
                <p id="todo-summary" class="result-summary"></p>
                <ul id="todo-list" class="todo-list"></ul>
                <p id="todo-empty" class="empty-copy" hidden>No ToDos match those filters.</p>
                <div class="pagination">
                    <button id="previous-page" class="button button-quiet" type="button" disabled>Previous</button>
                    <span id="page-label" aria-live="polite"></span>
                    <button id="next-page" class="button button-quiet" type="button" disabled>Next</button>
                </div>
                <section id="todo-detail" class="surface todo-detail" aria-labelledby="todo-detail-title" hidden>
                    <div class="section-heading">
                        <div><div class="eyebrow">This ToDo in context</div><h3 id="todo-detail-title" class="panel-title"></h3></div>
                    </div>
                    <p id="todo-detail-status" class="result-summary"></p>
                    <h4 class="panel-title">Hierarchy</h4>
                    <form id="todo-parent-form" class="tag-form">
                        <label for="todo-parent-select">Parent ToDo</label>
                        <select id="todo-parent-select"><option value="">No parent</option></select>
                        <button class="button button-secondary" type="submit">Save parent</button>
                    </form>
                    <ul id="todo-children-list" class="record-list"></ul>
                    <p id="todo-children-empty" class="empty-copy" hidden>No other ToDo reports to this one.</p>
                    <h4 class="panel-title">Related ToDos</h4>
                    <form id="todo-relate-form" class="tag-form">
                        <label for="todo-related-select">Link a related ToDo</label>
                        <select id="todo-related-select"><option value="">Choose a ToDo</option></select>
                        <button class="button button-secondary" type="submit">Link ToDo</button>
                    </form>
                    <ul id="todo-related-list" class="record-list"></ul>
                    <p id="todo-related-empty" class="empty-copy" hidden>Nothing is linked to this ToDo yet.</p>
                    <h4 class="panel-title">Objective and result</h4>
                    <form id="todo-objective-form" class="form-stack">
                        <label for="todo-objective">Objective <span class="optional">(what finishing this means)</span></label>
                        <textarea id="todo-objective" rows="2" placeholder="e.g. The quotes are signed"></textarea>
                        <button class="button button-quiet" type="submit">Save objective</button>
                    </form>
                    <p id="todo-result-summary" class="result-summary" hidden></p>
                    <form id="todo-result-form" class="form-stack">
                        <label for="todo-result">Report a result to the parent ToDo</label>
                        <textarea id="todo-result" rows="2" placeholder="What came of this?"></textarea>
                        <button class="button button-quiet" type="submit">Report result</button>
                    </form>
                    <h4 class="panel-title">Lifecycle</h4>
                    <div id="todo-lifecycle-actions" class="record-actions"></div>
                    <ul id="todo-lifecycle-explanations" class="record-todos"></ul>
                </section>
                <section class="surface tag-manager" aria-labelledby="tag-title">
                    <div><div class="eyebrow">Add a little structure</div><h2 id="tag-title">Your tags</h2></div>
                    <form id="tag-form" class="tag-form">
                        <label for="new-tag">Create a tag</label>
                        <input id="new-tag" name="name" maxlength="80" required placeholder="e.g. Follow up">
                        <button class="button button-secondary" type="submit">Add tag</button>
                    </form>
                    <ul id="tag-list" class="tag-list"></ul>
                    <p id="tag-empty" class="empty-copy" hidden>No tags yet. Add one to make related ToDos easier to find.</p>
                </section>
            </section>
            <section id="projects-view" class="view-panel" aria-labelledby="projects-title" hidden>
                <div class="section-heading">
                    <div><div class="eyebrow">Give work a home</div><h2 id="projects-title">Your Projects</h2></div>
                </div>
                <section class="surface" aria-labelledby="project-form-title">
                    <h3 id="project-form-title" class="panel-title">Start a Project</h3>
                    <form id="project-form" class="form-stack">
                        <label for="project-name">Project name</label>
                        <input id="project-name" name="name" maxlength="255" required placeholder="e.g. Kitchen rebuild">
                        <label for="project-description">Description <span class="optional">(optional)</span></label>
                        <textarea id="project-description" name="description" rows="2" placeholder="What is this Project for?"></textarea>
                        <button class="button button-primary" type="submit">Add Project</button>
                    </form>
                </section>
                <p id="project-summary" class="result-summary"></p>
                <ul id="project-list" class="record-list"></ul>
                <p id="project-empty" class="empty-copy" hidden>No Projects yet. Add one to give your ToDos a home.</p>
                <div class="pagination">
                    <button id="previous-project-page" class="button button-quiet" type="button" disabled>Previous Projects</button>
                    <span id="project-page-label" aria-live="polite"></span>
                    <button id="next-project-page" class="button button-quiet" type="button" disabled>Next Projects</button>
                </div>
                <section id="project-detail" class="surface" aria-labelledby="project-detail-title" hidden>
                    <div class="section-heading">
                        <div><div class="eyebrow">Inside this Project</div><h3 id="project-detail-title" class="panel-title"></h3></div>
                    </div>
                    <p id="project-detail-description" class="intro"></p>
                    <form id="project-section-form" class="tag-form">
                        <label for="project-section-name">Add a section to this Project</label>
                        <input id="project-section-name" name="name" maxlength="255" required placeholder="e.g. Waiting on quotes">
                        <button class="button button-secondary" type="submit">Add Project section</button>
                    </form>
                    <ul id="section-list" class="record-list"></ul>
                    <p id="section-empty" class="empty-copy" hidden>This Project has no sections yet.</p>
                </section>
            </section>
            <section id="notebooks-view" class="view-panel" aria-labelledby="notebooks-title" hidden>
                <div class="section-heading">
                    <div><div class="eyebrow">Keep what you are thinking about</div><h2 id="notebooks-title">Your Notebooks</h2></div>
                </div>
                <section class="surface" aria-labelledby="notebook-form-title">
                    <h3 id="notebook-form-title" class="panel-title">Start a Notebook</h3>
                    <form id="notebook-form" class="form-stack">
                        <label for="notebook-name">Notebook name</label>
                        <input id="notebook-name" name="name" maxlength="255" required placeholder="e.g. Reading notes">
                        <label for="notebook-description">Description <span class="optional">(optional)</span></label>
                        <textarea id="notebook-description" name="description" rows="2" placeholder="What is this Notebook for?"></textarea>
                        <button class="button button-primary" type="submit">Add Notebook</button>
                    </form>
                </section>
                <p id="notebook-summary" class="result-summary"></p>
                <ul id="notebook-list" class="record-list"></ul>
                <p id="notebook-empty" class="empty-copy" hidden>No Notebooks yet. Add one to keep related notes together.</p>
                <div class="pagination">
                    <button id="previous-notebook-page" class="button button-quiet" type="button" disabled>Previous Notebooks</button>
                    <span id="notebook-page-label" aria-live="polite"></span>
                    <button id="next-notebook-page" class="button button-quiet" type="button" disabled>Next Notebooks</button>
                </div>
                <section id="notebook-detail" class="surface" aria-labelledby="notebook-detail-title" hidden>
                    <div class="section-heading">
                        <div><div class="eyebrow">Inside this Notebook</div><h3 id="notebook-detail-title" class="panel-title"></h3></div>
                    </div>
                    <p id="notebook-detail-description" class="intro"></p>
                    <form id="notebook-section-form" class="tag-form">
                        <label for="notebook-section-name">Add a section to this Notebook</label>
                        <input id="notebook-section-name" name="name" maxlength="255" required placeholder="e.g. Open questions">
                        <button class="button button-secondary" type="submit">Add Notebook section</button>
                    </form>
                    <ul id="notebook-section-list" class="record-list"></ul>
                    <p id="notebook-section-empty" class="empty-copy" hidden>This Notebook has no sections yet.</p>
                </section>
            </section>
            <section id="libraries-view" class="view-panel" aria-labelledby="libraries-title" hidden>
                <div class="section-heading">
                    <div><div class="eyebrow">See how things group together</div><h2 id="libraries-title">Your Libraries</h2></div>
                </div>
                <p class="intro">Libraries gather Projects and Notebooks for browsing. Nothing here changes what they contain.</p>
                <p id="library-summary" class="result-summary"></p>
                <ul id="library-list" class="record-list"></ul>
                <p id="library-empty" class="empty-copy" hidden>No Libraries yet. Create one through the API to group Projects and Notebooks.</p>
                <div class="pagination">
                    <button id="previous-library-page" class="button button-quiet" type="button" disabled>Previous Libraries</button>
                    <span id="library-page-label" aria-live="polite"></span>
                    <button id="next-library-page" class="button button-quiet" type="button" disabled>Next Libraries</button>
                </div>
                <section id="library-detail" class="surface" aria-labelledby="library-detail-title" hidden>
                    <div class="section-heading">
                        <div><div class="eyebrow">Inside this Library</div><h3 id="library-detail-title" class="panel-title"></h3></div>
                    </div>
                    <p id="library-detail-description" class="intro"></p>
                    <h4 class="panel-title">Projects</h4>
                    <ul id="library-projects" class="record-list"></ul>
                    <h4 class="panel-title">Notebooks</h4>
                    <ul id="library-notebooks" class="record-list"></ul>
                    <p id="library-members-empty" class="empty-copy" hidden>This Library has no members yet.</p>
                </section>
            </section>
            <section id="reference-view" class="view-panel" aria-labelledby="reference-title" hidden>
                <div class="section-heading">
                    <div><div class="eyebrow">Look it up mid-conversation</div><h2 id="reference-title">Reference</h2></div>
                </div>
                <p class="intro">Concepts and dialogue patterns you consult rather than complete. Cards are private to you and never become ToDos.</p>
                <form id="reference-search-form" class="filter-bar reference-filter" role="search" aria-label="Search reference cards">
                    <label class="search-field" for="reference-search">Search cards<input id="reference-search" name="q" type="search" maxlength="255" placeholder="Search titles, summaries and text"></label>
                    <label for="reference-type-filter">Type
                        <select id="reference-type-filter" name="card_type">
                            <option value="">Any type</option><option value="concept">Concept</option>
                            <option value="dialogue_pattern">Dialogue pattern</option>
                        </select>
                    </label>
                    <label for="reference-tag-filter">Tag
                        <select id="reference-tag-filter" name="tag"><option value="">Any tag</option></select>
                    </label>
                    <label for="reference-sheet-filter">Cheat sheet
                        <select id="reference-sheet-filter" name="cheat_sheet"><option value="">Any sheet</option></select>
                    </label>
                    <button class="button button-secondary" type="submit">Find cards</button>
                </form>
                <p id="reference-summary" class="result-summary"></p>
                <ul id="reference-list" class="record-list"></ul>
                <p id="reference-empty" class="empty-copy" hidden>No cards match. Create one below.</p>
                <div class="pagination">
                    <button id="previous-reference-page" class="button button-quiet" type="button" disabled>Previous cards</button>
                    <span id="reference-page-label" aria-live="polite"></span>
                    <button id="next-reference-page" class="button button-quiet" type="button" disabled>Next cards</button>
                </div>
                <section id="reference-card-detail" class="surface reference-detail" aria-labelledby="reference-card-title" hidden>
                    <div class="section-heading">
                        <div><div id="reference-card-type" class="eyebrow"></div><h3 id="reference-card-title" class="panel-title" tabindex="-1"></h3></div>
                    </div>
                    <p id="reference-card-summary" class="reference-summary"></p>
                    <dl id="reference-card-fields" class="reference-fields"></dl>
                    <p id="reference-card-body" class="reference-body"></p>
                    <div id="reference-card-tags" class="todo-meta"></div>
                    <div class="record-actions">
                        <label for="reference-tag-picker">Add a tag
                            <select id="reference-tag-picker"><option value="">Choose a tag</option></select>
                        </label>
                        <button class="button button-quiet" type="button" data-reference-action="attach-tag">Add tag</button>
                    </div>
                    <div class="record-actions">
                        <label for="reference-sheet-picker">Add to a cheat sheet
                            <select id="reference-sheet-picker"><option value="">Choose a cheat sheet</option></select>
                        </label>
                        <button class="button button-quiet" type="button" data-reference-action="add-to-sheet">Add to sheet</button>
                    </div>
                    <div class="record-actions">
                        <button class="button button-secondary" type="button" data-reference-action="edit-card">Edit card</button>
                        <button id="reference-delete-card" class="button button-danger" type="button" data-reference-action="delete-card">Delete card</button>
                    </div>
                </section>
                <section class="surface reference-editor" aria-labelledby="reference-form-title">
                    <div><div class="eyebrow">Write it down once</div><h3 id="reference-form-title" class="panel-title">Create a card</h3></div>
                    <form id="reference-card-form" class="form-stack">
                        <label for="reference-card-type-input">Type</label>
                        <select id="reference-card-type-input" name="card_type">
                            <option value="concept">Concept</option><option value="dialogue_pattern">Dialogue pattern</option>
                        </select>
                        <label for="reference-card-title-input">Title</label>
                        <input id="reference-card-title-input" name="title" maxlength="255" required placeholder="e.g. Active listening">
                        <label for="reference-card-summary-input">Summary <span class="optional">(the line you read at a glance)</span></label>
                        <input id="reference-card-summary-input" name="summary" maxlength="500">
                        <fieldset id="reference-dialogue-fieldset" class="reference-dialogue-fieldset" hidden>
                            <legend>Dialogue pattern <span class="optional">(each optional)</span></legend>
                            <label for="reference-card-situation-input">Situation</label>
                            <textarea id="reference-card-situation-input" name="situation" rows="2" maxlength="2000"></textarea>
                            <label for="reference-card-aim-input">Aim</label>
                            <textarea id="reference-card-aim-input" name="aim" rows="2" maxlength="2000"></textarea>
                            <label for="reference-card-approach-input">Approach</label>
                            <textarea id="reference-card-approach-input" name="approach" rows="2" maxlength="2000"></textarea>
                            <label for="reference-card-avoid-input">Avoid</label>
                            <textarea id="reference-card-avoid-input" name="avoid" rows="2" maxlength="2000"></textarea>
                            <label for="reference-card-rationale-input">Rationale</label>
                            <textarea id="reference-card-rationale-input" name="rationale" rows="2" maxlength="2000"></textarea>
                        </fieldset>
                        <label for="reference-card-body-input">Text <span class="optional">(optional, plain text)</span></label>
                        <textarea id="reference-card-body-input" name="body" rows="4"></textarea>
                        <div class="record-actions">
                            <button id="reference-card-submit" class="button button-primary" type="submit">Create card</button>
                            <button id="reference-card-cancel" class="button button-quiet" type="button" hidden>Cancel editing</button>
                        </div>
                    </form>
                </section>
                <section class="surface reference-sheets" aria-labelledby="cheat-sheets-title">
                    <div><div class="eyebrow">Keep the right cards together</div><h3 id="cheat-sheets-title" class="panel-title">Cheat sheets</h3></div>
                    <form id="cheat-sheet-form" class="tag-form">
                        <label for="cheat-sheet-name">Create a cheat sheet</label>
                        <input id="cheat-sheet-name" name="name" maxlength="255" required placeholder="e.g. Difficult meetings">
                        <button class="button button-secondary" type="submit">Create sheet</button>
                    </form>
                    <p id="cheat-sheet-summary" class="result-summary"></p>
                    <ul id="cheat-sheet-list" class="record-list"></ul>
                    <p id="cheat-sheet-empty" class="empty-copy" hidden>No cheat sheets yet.</p>
                </section>
                <section id="cheat-sheet-detail" class="surface reference-detail" aria-labelledby="cheat-sheet-title" hidden>
                    <div class="section-heading">
                        <div><div class="eyebrow">Cheat sheet</div><h3 id="cheat-sheet-title" class="panel-title"></h3></div>
                    </div>
                    <p id="cheat-sheet-description" class="intro"></p>
                    <p id="cheat-sheet-cards-summary" class="result-summary"></p>
                    <ol id="cheat-sheet-cards" class="cheat-sheet-cards"></ol>
                    <p id="cheat-sheet-cards-empty" class="empty-copy" hidden>This sheet has no cards yet. Open a card and add it here.</p>
                    <div class="record-actions">
                        <button id="cheat-sheet-delete" class="button button-danger" type="button" data-reference-action="delete-sheet">Delete cheat sheet</button>
                    </div>
                </section>
            </section>
            <section id="review-view" class="view-panel" aria-labelledby="review-title" hidden>
                <div class="section-heading"><div><div class="eyebrow">A gentle reset</div><h2 id="review-title">Ready for review</h2></div></div>
                <p class="intro">See why an item surfaced, then mark it reviewed or snooze it for later.</p>
                <ul id="review-list" class="todo-list"></ul>
                <p id="review-empty" class="empty-copy" hidden>You’re all caught up. Enjoy the breathing room.</p>
            </section>
            <section id="activity-view" class="view-panel" aria-labelledby="activity-title" hidden>
                <div class="section-heading"><div><div class="eyebrow">What you have done</div><h2 id="activity-title">Your activity</h2></div></div>
                <p class="intro">This history is yours alone: it records only the actions you took, and nothing here can be changed.</p>
                <p id="activity-summary" class="result-summary"></p>
                <ul id="activity-list" class="simple-list"></ul>
                <p id="activity-empty" class="empty-copy" hidden>No activity recorded yet.</p>
                <div class="pagination">
                    <button id="previous-activity-page" class="button button-quiet" type="button" disabled>Previous activity</button>
                    <span id="activity-page-label" aria-live="polite"></span>
                    <button id="next-activity-page" class="button button-quiet" type="button" disabled>Next activity</button>
                </div>
            </section>
            <section id="collaboration-view" class="view-panel" aria-labelledby="collaboration-title" hidden>
                <div class="section-heading">
                    <div><div class="eyebrow">Work across contexts</div><h2 id="collaboration-title">Collaboration</h2></div>
                </div>
                <p class="intro">The server checks every capability. A missing resource and an action you cannot access may receive the same response.</p>
                <section class="surface collaboration-surface" aria-labelledby="capability-title">
                    <h3 id="capability-title" class="panel-title">Capabilities on a resource</h3>
                    <form id="collaboration-resource-form" class="tag-form">
                        <label for="collaboration-resource-type">Resource type</label>
                        <select id="collaboration-resource-type">
                            <option value="project">Project</option><option value="notebook">Notebook</option><option value="library">Library</option>
                        </select>
                        <label for="collaboration-resource-id">Resource ID</label>
                        <input id="collaboration-resource-id" type="number" min="1" step="1" required>
                        <button class="button button-secondary" type="submit">Show capabilities</button>
                    </form>
                    <section id="capability-panel" aria-labelledby="capability-list-title" hidden>
                        <h4 id="capability-list-title" class="panel-title">Active grants</h4>
                        <ul id="capability-list" class="record-list"></ul>
                        <p id="capability-empty" class="empty-copy" hidden>No active grants on this resource.</p>
                        <form id="capability-grant-form" class="form-stack">
                            <label for="capability-subject-id">Grant to user ID</label>
                            <input id="capability-subject-id" type="number" min="1" step="1" required>
                            <label for="capability-name">Capability</label>
                            <select id="capability-name">
                                <option value="discover">discover</option><option value="read">read</option>
                                <option value="reference">reference</option><option value="query">query</option>
                                <option value="suggest">suggest</option><option value="send_task">send_task</option>
                                <option value="callback">callback</option><option value="contribute">contribute</option>
                                <option value="edit">edit</option><option value="archive">archive</option>
                                <option value="delete">delete</option><option value="manage_permissions">manage_permissions</option>
                            </select>
                            <button class="button button-primary" type="submit">Grant capability</button>
                        </form>
                    </section>
                </section>
                <section class="surface collaboration-surface" aria-labelledby="request-send-title">
                    <h3 id="request-send-title" class="panel-title">Send a cross-context request</h3>
                    <p class="result-summary">Enter the source and target context IDs. You need read access to the source and send_task on the target.</p>
                    <form id="cross-context-request-form" class="form-stack">
                        <label for="request-source-type">Source type</label>
                        <select id="request-source-type"><option value="project">Project</option><option value="notebook">Notebook</option></select>
                        <label for="request-source-id">Source ID</label>
                        <input id="request-source-id" type="number" min="1" step="1" required>
                        <label for="request-target-type">Target type</label>
                        <select id="request-target-type"><option value="project">Project</option><option value="notebook">Notebook</option></select>
                        <label for="request-target-id">Target ID</label>
                        <input id="request-target-id" type="number" min="1" step="1" required>
                        <label for="request-type">Request type</label>
                        <select id="request-type"><option value="task">Task</option><option value="question">Question</option><option value="suggestion">Suggestion</option></select>
                        <label for="request-title">Title</label>
                        <input id="request-title" maxlength="255" required>
                        <label for="request-body">Details <span class="optional">(optional)</span></label>
                        <textarea id="request-body" rows="3"></textarea>
                        <button class="button button-primary" type="submit">Send request</button>
                    </form>
                </section>
                <section class="surface collaboration-surface" aria-labelledby="request-inbox-title">
                    <h3 id="request-inbox-title" class="panel-title">Request inbox</h3>
                    <ul id="request-inbox-list" class="record-list"></ul>
                    <p id="request-inbox-empty" class="empty-copy" hidden>No pending requests you can resolve.</p>
                </section>
                <section class="surface collaboration-surface" aria-labelledby="request-outbox-title">
                    <h3 id="request-outbox-title" class="panel-title">Request outbox</h3>
                    <ul id="request-outbox-list" class="record-list"></ul>
                    <p id="request-outbox-empty" class="empty-copy" hidden>You have not sent any requests.</p>
                </section>
            </section>
            <section id="account-view" class="view-panel" aria-labelledby="account-title" hidden>
                <div class="section-heading">
                    <div><div class="eyebrow">Keep it yours</div><h2 id="account-title">Account</h2></div>
                </div>
                <p class="intro">Changing your password signs out every other browser where you are signed in.</p>
                <section class="surface account-surface" aria-labelledby="password-change-title">
                    <h3 id="password-change-title" class="panel-title">Change your password</h3>
                    <form id="password-change-form" class="form-stack" novalidate>
                        <label for="current-password">Current password</label>
                        <input id="current-password" name="current_password" type="password" autocomplete="current-password" maxlength="255" required>
                        <label for="new-password">New password</label>
                        <input id="new-password" name="new_password" type="password" autocomplete="new-password" minlength="8" maxlength="255" required aria-describedby="new-password-hint">
                        <label for="new-password-confirm">Repeat the new password</label>
                        <input id="new-password-confirm" name="confirm" type="password" autocomplete="new-password" minlength="8" maxlength="255" required>
                        <p id="new-password-hint" class="result-summary">Use at least 8 characters, different from the current password.</p>
                        <button class="button button-primary" type="submit">Change password</button>
                    </form>
                </section>
                <section class="surface account-surface" aria-labelledby="invitation-title">
                    <h3 id="invitation-title" class="panel-title">Invite someone</h3>
                    <p class="result-summary">We email them a link to create their own account. The link works once and expires after a week. Nothing you own is shared with them.</p>
                    <form id="invitation-form" class="form-stack">
                        <label for="invitation-email">Their email address</label>
                        <input id="invitation-email" name="email" type="email" autocomplete="off" maxlength="191" required>
                        <button class="button button-secondary" type="submit">Send invitation</button>
                    </form>
                </section>
            </section>
        </section>
    </main>
</body>
</html>
