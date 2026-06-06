<?php
require 'config.php';
requireLogin();

$me      = currentUser();
$postObj = new Post();
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title']   ?? '');
    $content = trim($_POST['content'] ?? '');
    $image   = '';

    if ($title === '' || $content === '') {
        $error = 'Please add both title and content.';
    } else {
        if (!empty($_FILES['image']['name'])) {
            try {
                $image = $postObj->upload($_FILES['image']);
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        if (!$error) {
            $postObj->create((int)$me['id'], $title, $content, $image);
            header('Location: index.php');
            exit;
        }
    }
}

$posts = $postObj->getAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?= nav() ?>

<main class="container">
    <section class="compose-box">
        <h3>Create a New Post</h3>

        <?php if ($error): ?>
            <p class="alert alert-error"><?= h($error) ?></p>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label class="form-label" for="title">Post Title</label>
                <input class="form-control" type="text" id="title" name="title"
                       placeholder="Enter a title" required maxlength="120"
                       value="<?= h($_POST['title'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="content">Content</label>
                <textarea class="form-control" id="content" name="content"
                          placeholder="Write your post here..." required
                          rows="4"><?= h($_POST['content'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Image (optional)</label>
                <label class="file-input-label">
                    &#128247; <small id="img-name">Choose image...</small>
                    <input type="file" name="image" id="img-input" accept="image/*">
                </label>
            </div>

            <button class="btn btn-primary" type="submit">Publish Post</button>
        </form>
    </section>

    <h2 class="page-title">Recent Posts</h2>

    <?php if (empty($posts)): ?>
        <p class="text-muted text-center">No posts yet.</p>
    <?php else: ?>
        <section class="post-list">
            <?php foreach ($posts as $p): ?>
                <article class="post-card" data-href="post.php?id=<?= $p['id'] ?>" role="link" tabindex="0">

                    <?php if ($p['image']): ?>
                        <img class="post-card-img" src="uploads/<?= h($p['image']) ?>" alt="Post image">
                    <?php endif; ?>

                    <section class="post-card-body">
                        <h3 class="post-card-title"><?= h($p['title']) ?></h3>

                        <?php
                        $preview = $p['content'];
                        if (strlen($preview) > 120) $preview = substr($preview, 0, 120) . '...';
                        ?>
                        <p class="post-card-excerpt"><?= h($preview) ?></p>

                        <footer class="post-meta">
                            <a href="profile.php?id=<?= $p['user_id'] ?>"><strong><?= h($p['username']) ?></strong></a>
                            <time datetime="<?= h($p['created_at']) ?>"><?= timeAgo($p['created_at']) ?></time>
                            <small>&#128172; <?= (int)$p['comment_count'] ?></small>
                            <small>&#10084; <?= (int)$p['like_count'] ?></small>

                            <?php if ((int)$p['user_id'] === (int)$me['id'] || $me['role'] === 'admin'): ?>
                                <form method="POST" action="post.php" class="push-right">
                                    <input type="hidden" name="action"  value="delete_post">
                                    <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="from"    value="index">
                                    <button class="btn btn-danger btn-sm" type="submit"
                                            onclick="return confirm('Are you sure you want to delete this post?')">Delete</button>
                                </form>
                            <?php endif; ?>
                        </footer>
                    </section>

                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    //clickable post cards
$(function () {
    $('.post-card').on('click', function (e) {
        if ($(e.target).closest('a, button, form, input, textarea, label').length) {
            return;
        }
        window.location = $(this).data('href');
    });

    $('#img-input').on('change', function () {
        if (this.files[0]) {
            $('#img-name').text(this.files[0].name);
        } else {
            $('#img-name').text('Choose image...');
        }
    });
});
</script>

</body>
</html>
