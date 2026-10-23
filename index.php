
<?php
session_start();
require 'db.php';

// CSRF TOKEN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// DELETE USER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($csrfToken, $_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('Invalid request. Please refresh the page and try again.');
    }

    $deleteId = filter_var($_POST['delete_id'], FILTER_VALIDATE_INT);

    if ($deleteId !== false && $deleteId > 0) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$deleteId]);
    }

    header("Location: index.php?deleted=1");
    exit;
}

// SEARCH
$search = trim($_GET['search'] ?? '');

if (function_exists('mb_substr')) {
    $search = mb_substr($search, 0, 100);
} else {
    $search = substr($search, 0, 100);
}

// SORTING
$allowedSorts = ['id', 'name', 'email', 'join_date'];
$sort = $_GET['sort'] ?? 'id';

if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'id';
}

$order = strtoupper($_GET['order'] ?? 'DESC');

if (!in_array($order, ['ASC', 'DESC'], true)) {
    $order = 'DESC';
}

// PAGINATION
$allowedLimits = [5, 10, 25];
$perPage = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);

if (!in_array($perPage, $allowedLimits, true)) {
    $perPage = 10;
}

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

if ($page === false || $page < 1) {
    $page = 1;
}

// SEARCH CONDITIONS
$where = '';
$params = [];

if ($search !== '') {
    $where = "WHERE CAST(id AS CHAR) LIKE ?
              OR name LIKE ?
              OR email LIKE ?";

    $term = '%' . $search . '%';
    $params = [$term, $term, $term];
}

// TOTAL MATCHING USERS
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$countStmt->execute($params);
$totalMatching = (int) $countStmt->fetchColumn();

// DASHBOARD STATISTICS
$totalUsers = (int) $pdo
    ->query("SELECT COUNT(*) FROM users")
    ->fetchColumn();

$newestStmt = $pdo->query(
    "SELECT name FROM users ORDER BY join_date DESC, id DESC LIMIT 1"
);
$newestUser = $newestStmt->fetchColumn();

// PAGINATION CALCULATION
$totalPages = max(1, (int) ceil($totalMatching / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// FETCH USERS
$sql = "SELECT id, name, email, join_date
        FROM users
        $where
        ORDER BY `$sort` $order
        LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);

$position = 1;

foreach ($params as $param) {
    $stmt->bindValue($position++, $param, PDO::PARAM_STR);
}

$stmt->bindValue($position++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($position, $offset, PDO::PARAM_INT);

$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// URL HELPER
function pageUrl(array $changes = []): string {
    $query = array_merge($_GET, $changes);
    return 'index.php?' . http_build_query($query);
}

// HTML ESCAPE HELPER
function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// SORT LINK HELPER
function sortUrl(string $column, string $currentSort, string $currentOrder): string {
    $nextOrder = ($column === $currentSort && $currentOrder === 'ASC')
        ? 'DESC'
        : 'ASC';

    return pageUrl([
        'sort' => $column,
        'order' => $nextOrder,
        'page' => 1
    ]);
}

$firstRecord = $totalMatching > 0 ? $offset + 1 : 0;
$lastRecord = min($offset + $perPage, $totalMatching);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | VioletPanel</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
<div class="app-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <a href="index.php" class="brand">
            <span class="brand-icon">✦</span>
            <span>Violet<span class="brand-highlight">Panel</span></span>
        </a>

        <p class="sidebar-label">WORKSPACE</p>

        <nav class="sidebar-nav">
            <a href="index.php" class="sidebar-link active">
                <span class="nav-icon">▦</span>
                <span>Dashboard</span>
            </a>

            <a href="index.php" class="sidebar-link">
                <span class="nav-icon">♙</span>
                <span>Users</span>
            </a>

            <a href="create.php" class="sidebar-link">
                <span class="nav-icon">＋</span>
                <span>Add New User</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="sidebar-profile">
                <div class="profile-avatar">A</div>
                <div>
                    <strong>Administrator</strong>
                    <small>User Management</small>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="dashboard">

            <!-- HEADER -->
            <header class="dashboard-header">
                <div>
                    <span class="eyebrow">ADMINISTRATION / USERS</span>
                    <h1>User Management</h1>
                    <p class="subtitle">
                        Manage your users, search records, and organize your data.
                    </p>
                </div>

                <a href="create.php" class="btn-primary">
                    <span class="plus-icon">+</span>
                    Add New User
                </a>
            </header>

            <!-- NOTIFICATION -->
            <?php if (isset($_GET['deleted'])): ?>
                <div class="notice success" role="status">
                    User deletion request completed.
                </div>
            <?php endif; ?>

            <!-- STATISTICS -->
            <section class="stats-grid">

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">TOTAL USERS</span>
                        <span class="stat-icon purple">U</span>
                    </div>
                    <div class="stat-number">
                        <?= number_format($totalUsers) ?>
                    </div>
                    <p class="stat-description">All registered users</p>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">MATCHING RECORDS</span>
                        <span class="stat-icon blue">⌕</span>
                    </div>
                    <div class="stat-number">
                        <?= number_format($totalMatching) ?>
                    </div>
                    <p class="stat-description">Based on your current search</p>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">LATEST USER</span>
                        <span class="stat-icon green">✦</span>
                    </div>
                    <div class="stat-latest">
                        <?= $newestUser !== false ? e($newestUser) : 'No users yet' ?>
                    </div>
                    <p class="stat-description">Most recently joined</p>
                </div>

            </section>

            <!-- USERS TABLE -->
            <section class="table-panel">

                <div class="panel-heading">
                    <div>
                        <h2>All Users</h2>
                        <p class="subtitle">
                            Browse and manage your user records.
                        </p>
                    </div>

                    <span class="record-badge">
                        <?= number_format($totalMatching) ?> records
                    </span>
                </div>

                <!-- SEARCH AND FILTERS -->
                <form action="index.php" method="GET" class="toolbar">
                    <div class="search-box">
                        <span class="search-icon">⌕</span>

                        <input
                            type="text"
                            name="search"
                            value="<?= e($search) ?>"
                            placeholder="Search name, email, or ID..."
                            aria-label="Search users"
                        >

                        <input type="hidden" name="sort" value="<?= e($sort) ?>">
                        <input type="hidden" name="order" value="<?= e($order) ?>">
                        <input type="hidden" name="per_page" value="<?= e($perPage) ?>">
                    </div>

                    <button type="submit" class="btn-secondary">Search</button>

                    <?php if ($search !== ''): ?>
                        <a href="index.php" class="btn-secondary">Clear</a>
                    <?php endif; ?>

                    <label class="per-page-control">
                        <span>Show</span>
                        <select
                            name="per_page"
                            onchange="this.form.submit()"
                            aria-label="Records per page"
                        >
                            <?php foreach ([5, 10, 25] as $limit): ?>
                                <option
                                    value="<?= $limit ?>"
                                    <?= $perPage === $limit ? 'selected' : '' ?>
                                >
                                    <?= $limit ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>

                <!-- TABLE -->
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>
                                    <a class="sort-link" href="<?= e(sortUrl('id', $sort, $order)) ?>">
                                        ID
                                        <?= $sort === 'id' ? ($order === 'ASC' ? '↑' : '↓') : '↕' ?>
                                    </a>
                                </th>

                                <th>
                                    <a class="sort-link" href="<?= e(sortUrl('name', $sort, $order)) ?>">
                                        USER
                                        <?= $sort === 'name' ? ($order === 'ASC' ? '↑' : '↓') : '↕' ?>
                                    </a>
                                </th>

                                <th>
                                    <a class="sort-link" href="<?= e(sortUrl('email', $sort, $order)) ?>">
                                        EMAIL
                                        <?= $sort === 'email' ? ($order === 'ASC' ? '↑' : '↓') : '↕' ?>
                                    </a>
                                </th>

                                <th>
                                    <a class="sort-link" href="<?= e(sortUrl('join_date', $sort, $order)) ?>">
                                        JOIN DATE
                                        <?= $sort === 'join_date' ? ($order === 'ASC' ? '↑' : '↓') : '↕' ?>
                                    </a>
                                </th>

                                <th>ACTIONS</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <span class="id-label">
                                            #<?= e($user['id']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="user-info">
                                            <div class="avatar">
                                                <?php
                                                $displayName = trim($user['name'] ?? '');
                                                $initial = $displayName !== '' ? $displayName : '?';

                                                if (function_exists('mb_substr')) {
                                                    $initial = mb_substr($initial, 0, 1);
                                                    $initial = mb_strtoupper($initial);
                                                } else {
                                                    $initial = strtoupper(substr($initial, 0, 1));
                                                }

                                                echo e($initial);
                                                ?>
                                            </div>

                                            <span class="user-name">
                                                <?= e($user['name']) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <td class="email-cell">
                                        <?= e($user['email']) ?>
                                    </td>

                                    <td>
                                        <span class="join-date join-date-label">
                                            <?php if (!empty($user['join_date'])): ?>
                                                <?php
                                                $date = strtotime($user['join_date']);
                                                echo $date !== false ? e(date('M d, Y', $date)) : '—';
                                                ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="action-group">
                                            <a
                                                href="update.php?id=<?= e($user['id']) ?>"
                                                class="btn-edit"
                                            >
                                                Edit
                                            </a>

                                            <form
                                                action="index.php"
                                                method="POST"
                                                class="delete-form"
                                                onsubmit="return confirm('Are you sure you want to delete this user?');"
                                            >
                                                <input type="hidden" name="delete_id" value="<?= e($user['id']) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                                                <button type="submit" class="btn-delete">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <div class="empty-icon">⌕</div>
                                    <strong>No users found</strong>
                                    <p>Try a different search or add a new user.</p>

                                    <?php if ($search !== ''): ?>
                                        <a href="index.php" class="btn-secondary">
                                            Clear Search
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <footer class="table-footer">
                    <p class="results-info">
                        Showing <strong><?= $firstRecord ?></strong>
                        to <strong><?= $lastRecord ?></strong>
                        of <strong><?= $totalMatching ?></strong> users
                    </p>

                    <nav class="pagination" aria-label="User pages">
                        <?php if ($page > 1): ?>
                            <a class="page-link"
                               href="<?= e(pageUrl(['page' => $page - 1])) ?>"
                               aria-label="Previous page">&larr;</a>
                        <?php else: ?>
                            <span class="page-link disabled">&larr;</span>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a
                                class="page-link <?= $i === $page ? 'active' : '' ?>"
                                href="<?= e(pageUrl(['page' => $i])) ?>"
                                <?= $i === $page ? 'aria-current="page"' : '' ?>
                            >
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a class="page-link"
                               href="<?= e(pageUrl(['page' => $page + 1])) ?>"
                               aria-label="Next page">&rarr;</a>
                        <?php else: ?>
                            <span class="page-link disabled">&rarr;</span>
                        <?php endif; ?>
                    </nav>
                </footer>
            </section>

            <footer class="dashboard-footer">
                <span>USER MANAGEMENT SYSTEM</span>
                <span>Admin Dashboard</span>
            </footer>

        </div>
    </main>
</div>
</body>
</html>
