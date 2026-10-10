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
                    <button class="view-button" type="button" data-view="review" aria-pressed="false">Review</button>
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
                    <form id="section-form" class="tag-form">
                        <label for="section-name">Add a section</label>
                        <input id="section-name" name="name" maxlength="255" required placeholder="e.g. Waiting on quotes">
                        <button class="button button-secondary" type="submit">Add section</button>
                    </form>
                    <ul id="section-list" class="record-list"></ul>
                    <p id="section-empty" class="empty-copy" hidden>This Project has no sections yet.</p>
                </section>
            </section>
            <section id="review-view" class="view-panel" aria-labelledby="review-title" hidden>
                <div class="section-heading"><div><div class="eyebrow">A gentle reset</div><h2 id="review-title">Ready for review</h2></div></div>
                <p class="intro">See why an item surfaced, then mark it reviewed when you’re ready.</p>
                <ul id="review-list" class="todo-list"></ul>
                <p id="review-empty" class="empty-copy" hidden>You’re all caught up. Enjoy the breathing room.</p>
            </section>
        </section>
    </main>
</body>
</html>
