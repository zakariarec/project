<?php
require 'config.php';
requireAdmin();

$me      = currentUser();
$userObj = new User();
$db      = Database::get();
$msg     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['user_id'] ?? 0);

    if ($action === 'delete' && $uid && $uid !== (int)$me['id']) {
        $userObj->delete($uid);
        $msg = 'User removed.';
    }
    if ($action === 'role' && $uid && $uid !== (int)$me['id']) {
        $newRole = $_POST['role'] ?? 'user';
        if (in_array($newRole, ['user', 'admin'])) {
            $userObj->changeRole($uid, $newRole);
            $msg = 'Role updated.';
        }
    }
    if ($action === 'log' && $uid) {
        $u = $userObj->getById($uid);
        if ($u) {
            $userObj->writeLog($u);
            $msg = 'User info saved to log.';
        }
    }
    if ($action === 'log_all') {
        $allUsers = $userObj->getAll();
        foreach ($allUsers as $u) {
            $userObj->writeLog($u);
        }
        $msg = 'All users logged.';
    }
    header('Location: admin.php' . ($msg ? '?msg=' . urlencode($msg) : ''));
    exit;
}

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

$users = $userObj->getAll();
$log   = file_exists(LOG_PATH) ? file_get_contents(LOG_PATH) : '(no log entries yet)';

$totalUsers    = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalPosts    = $db->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$totalComments = $db->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$totalLikes    = $db->query("SELECT COUNT(*) FROM likes")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Project</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?= nav() ?>

<main class="container">
    <h1 class="page-title">Admin Dashboard</h1>

    <?php if ($msg): ?>
        <p class="alert alert-success"><?= h($msg) ?></p>
    <?php endif; ?>

    <section class="stat-grid">
        <article class="stat-box">
            <p class="stat-box-val"><?= $totalUsers ?></p>
            <p class="stat-box-lbl">Users</p>
        </article>
        <article class="stat-box">
            <p class="stat-box-val"><?= $totalPosts ?></p>
            <p class="stat-box-lbl">Posts</p>
        </article>
        <article class="stat-box">
            <p class="stat-box-val"><?= $totalComments ?></p>
            <p class="stat-box-lbl">Comments</p>
        </article>
        <article class="stat-box">
            <p class="stat-box-val"><?= $totalLikes ?></p>
            <p class="stat-box-lbl">Likes</p>
        </article>
    </section>

    <section class="card">
        <section class="card-body">
            <header class="section-head">
                <strong>User Log File</strong>
                <form method="POST">
                    <input type="hidden" name="action" value="log_all">
                    <button class="btn btn-outline btn-sm" type="submit">Log All Users</button>
                </form>
            </header>
            <aside class="log-box" id="log-box"><?= h($log) ?></aside>
        </section>
    </section>

    <section class="card">
        <section class="card-body">
            <strong class="section-title">Manage Users</strong>

            <section class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Posts</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="text-muted">#<?= $u['id'] ?></td>
                                <td><strong>@<?= h($u['username']) ?></strong></td>
                                <td class="text-muted"><?= h($u['email']) ?></td>
                                <td>
                                    <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                                        <form method="POST">
                                            <input type="hidden" name="action"  value="role">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <select name="role" onchange="this.form.submit()" class="role-select">
                                                <option value="user"  <?= $u['role'] === 'user'  ? 'selected' : '' ?>>User</option>
                                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            </select>
                                        </form>
                                    <?php else: ?>
                                        <strong class="badge badge-admin">Admin (you)</strong>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$u['post_count'] ?></td>
                                <td>
                                    <section class="flex">
                                        <form method="POST">
                                            <input type="hidden" name="action"  value="log">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button class="btn btn-outline btn-sm" type="submit">Log</button>
                                        </form>

                                        <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                                            <form method="POST" onsubmit="return confirm('Delete @<?= h($u['username']) ?>? This cannot be undone.')">
                                                <input type="hidden" name="action"  value="delete">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </section>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </section>
    </section>

</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
// auto-scroll the log viewer so the latest log entries are visible on page load
$(function () {
    var logBox = document.getElementById('log-box');
    if (logBox) {
        logBox.scrollTop = logBox.scrollHeight;
    }
});
</script>

</body>
</html>
