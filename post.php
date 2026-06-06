<?php
require 'config.php';
requireLogin();

$me         = currentUser();
$postObj    = new Post();
$commentObj = new Comment();
$db         = Database::get();
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_post') {
        $pid  = (int)($_POST['post_id'] ?? 0);
        $post = $postObj->getById($pid);

        if ($post && ((int)$post['user_id'] === (int)$me['id'] || $me['role'] === 'admin')) {
            $postObj->delete($pid);
        }

        header('Location: index.php');
        exit;
    }
    if ($action === 'delete_comment') {
        $cid = (int)($_POST['comment_id'] ?? 0);
        $pid = (int)($_POST['post_id']    ?? 0);

        if ($me['role'] === 'admin') {
            $commentObj->delete($cid);
        }

        header('Location: post.php?id=' . $pid);
        exit;
    }
    if ($action === 'like') {
        $pid  = (int)($_POST['post_id'] ?? 0);
        $stmt = $db->prepare("SELECT 1 FROM likes WHERE user_id = ? AND post_id = ?");
        $stmt->execute([(int)$me['id'], $pid]);

        if ($stmt->fetch()) {
            $db->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?")->execute([(int)$me['id'], $pid]);
        } else {
            $db->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)")->execute([(int)$me['id'], $pid]);
        }

        header('Location: post.php?id=' . $pid);
        exit;
    }
    if ($action === 'comment') {
        $pid     = (int)($_POST['post_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');

        if ($content === '') {
            $error = 'You need to enter a comment to post';
        } else {
            $commentObj->create($pid, (int)$me['id'], $content);
            header('Location: post.php?id=' . $pid);
            exit;
        }
    }
}

$id   = (int)($_GET['id'] ?? 0);
$post = $postObj->getById($id);

if (!$post) {
    header('Location: index.php');
    exit;
}

$comments = $commentObj->getByPost($id);

$lcStmt = $db->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?");
$lcStmt->execute([$id]);
$likeCount = (int)$lcStmt->fetchColumn();

$likedStmt = $db->prepare("SELECT 1 FROM likes WHERE user_id = ? AND post_id = ?");
$likedStmt->execute([(int)$me['id'], $id]);
$liked = (bool)$likedStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($post['title']) ?> - Project</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?= nav() ?>

<main class="container">
    <article class="post-full">

        <?php if ($post['image']): ?>
            <img class="post-full-img" src="uploads/<?= h($post['image']) ?>" alt="Post image">
        <?php endif; ?>

        <section class="post-full-body">
            <h1 class="post-full-title"><?= h($post['title']) ?></h1>

            <header class="post-meta post-meta-main">
                <a href="profile.php?id=<?= $post['user_id'] ?>"><strong><?= h($post['username']) ?></strong></a>
                <time datetime="<?= h($post['created_at']) ?>"><?= timeAgo($post['created_at']) ?></time>

                <?php if ((int)$post['user_id'] === (int)$me['id'] || $me['role'] === 'admin'): ?>
                    <form method="POST" class="push-right">
                        <input type="hidden" name="action"  value="delete_post">
                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                        <button class="btn btn-danger btn-sm" type="submit"
                                onclick="return confirm('Delete this post?')">Delete Post</button>
                    </form>
                <?php endif; ?>
            </header>

            <p class="post-full-content"><?= h($post['content']) ?></p>

            <form method="POST" class="like-form">
                <input type="hidden" name="action"  value="like">
                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                <button class="btn <?= $liked ? 'btn-danger' : 'btn-outline' ?>" type="submit">
                    &#10084; <?= $liked ? 'Unlike' : 'Like' ?> (<?= $likeCount ?>)
                </button>
            </form>
        </section>
    </article>

    <section class="comments-section">
        <h3 class="comments-title">Comments (<?= count($comments) ?>)</h3>

        <?php if ($error): ?>
            <p class="alert alert-error"><?= h($error) ?></p>
        <?php endif; ?>

        <?php if (empty($comments)): ?>
            <p class="text-muted">No comments yet. Be the first!</p>
        <?php else: ?>
            <section class="comment-list">
                <?php foreach ($comments as $c): ?>
                    <article class="comment">
                        <header class="comment-meta">
                            <a href="profile.php?id=<?= $c['user_id'] ?>"><strong><?= h($c['username']) ?></strong></a>
                            &middot; <?= timeAgo($c['created_at']) ?>

                            <?php if ($me['role'] === 'admin'): ?>
                                <form method="POST" class="comment-delete-form">
                                    <input type="hidden" name="action"     value="delete_comment">
                                    <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                                    <input type="hidden" name="post_id"    value="<?= $post['id'] ?>">
                                    <button class="btn btn-danger btn-sm btn-icon" type="submit"
                                            onclick="return confirm('Delete this comment?')">&#x2715;</button>
                                </form>
                            <?php endif; ?>
                        </header>
                        <p class="comment-text"><?= nl2br(h($c['content'])) ?></p>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action"  value="comment">
            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">

            <div class="form-group">
                <label class="form-label" for="comment-text">Write a comment</label>
                <textarea class="form-control" id="comment-text" name="content"
                          placeholder="Type your comment here..." required rows="3"></textarea>
            </div>

            <button class="btn btn-primary" type="submit">Post Comment</button>
        </form>
    </section>

</main>

</body>
</html>
