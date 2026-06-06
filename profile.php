<?php
require 'config.php';
requireLogin();

$me      = currentUser();
$userObj = new User();
$postObj = new Post();
$error   = '';
$success = '';

$viewId       = isset($_GET['id']) ? (int)$_GET['id'] : (int)$me['id'];
$isOwnProfile = ($viewId === (int)$me['id']);

if ($isOwnProfile) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $bio    = substr(trim($_POST['bio'] ?? ''), 0, 200);
        $avatar = $me['avatar'] ?? '';

        if (!empty($_FILES['avatar']['name'])) {
            try {
                $avatar = $postObj->upload($_FILES['avatar']);
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        if (!$error) {
            $userObj->update((int)$me['id'], $bio, $avatar);
            $success = 'Profile saved!';
            $me      = $userObj->getById((int)$me['id']);
        }
    }

    $subject = $me;
    $posts   = $postObj->getByUser((int)$me['id']);
} else {
    $subject = $userObj->getById($viewId);

    if (!$subject) {
        header('Location: index.php');
        exit;
    }

    $posts = $postObj->getByUser($viewId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($subject['username']) ?> - Project</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?= nav() ?>

<main class="container">
    <section class="profile-card">
        <header class="profile-header">
            <?php if ($subject['avatar']): ?>
                <img class="profile-avatar" src="uploads/<?= h($subject['avatar']) ?>" alt="Avatar">
            <?php else: ?>
                <div class="avatar-letter"><?= strtoupper($subject['username'][0]) ?></div>
            <?php endif; ?>

            <section class="profile-info">
                <h2>
                    @<?= h($subject['username']) ?>
                    <small class="badge badge-<?= h($subject['role']) ?>"><?= h($subject['role']) ?></small>
                </h2>
                <p class="profile-bio">
                    <?= $subject['bio'] ? h($subject['bio']) : 'This user has no bio' ?>
                </p>
                <p class="text-muted profile-meta">
                    <?php if ($isOwnProfile): ?>
                        <?= h($subject['email']) ?> &middot;
                    <?php endif; ?>
                    Joined <?= date('M Y', strtotime($subject['created_at'])) ?>
                </p>
            </section>
        </header>

        <?php if ($isOwnProfile): ?>

            <?php if ($error): ?>
                <p class="alert alert-error"><?= h($error) ?></p>
            <?php endif; ?>

            <?php if ($success): ?>
                <p class="alert alert-success"><?= h($success) ?></p>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">

                <div class="form-group">
                    <label class="form-label" for="bio">Bio</label>
                    <textarea class="form-control" id="bio" name="bio"
                              rows="3" maxlength="200"
                              placeholder="Tell us about yourself"><?= h($subject['bio'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Profile Picture</label>
                    <label class="file-input-label">
                        &#128247; <small id="av-name">Choose image...</small>
                        <input type="file" name="avatar" id="av-input" accept="image/*">
                    </label>
                </div>

                <button class="btn btn-primary" type="submit">Save Changes</button>
            </form>

        <?php endif; ?>
    </section>

    <h2 class="page-title">
        <?php if ($isOwnProfile): ?>
            My Posts (<?= count($posts) ?>)
        <?php else: ?>
            <?= h($subject['username']) ?>'s Posts (<?= count($posts) ?>)
        <?php endif; ?>
    </h2>

    <?php if (empty($posts)): ?>
        <p class="text-muted">
            <?= $isOwnProfile ? "You haven't posted anything" : 'This user has no posts' ?>
        </p>
    <?php else: ?>
        <section class="post-list">
            <?php foreach ($posts as $p): ?>
                <article class="post-card" data-href="post.php?id=<?= $p['id'] ?>" role="link" tabindex="0">
                    <?php if ($p['image']): ?>
                        <img class="post-card-img" src="uploads/<?= h($p['image']) ?>" alt="Post image">
                    <?php endif; ?>

                    <section class="post-card-body">
                        <h3 class="post-card-title"><?= h($p['title']) ?></h3>

                        <footer class="post-meta">
                            <time datetime="<?= h($p['created_at']) ?>"><?= timeAgo($p['created_at']) ?></time>

                            <?php if ($isOwnProfile || $me['role'] === 'admin'): ?>
                                <form method="POST" action="post.php" class="push-right">
                                    <input type="hidden" name="action"  value="delete_post">
                                    <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-danger btn-sm" type="submit"
                                            onclick="return confirm('Delete post?')">Delete</button>
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
// choosing a new profile picture will show the file name in the "choose image" label
$(function () {
    $('#av-input').on('change', function () {
        if (this.files[0]) {
            $('#av-name').text(this.files[0].name);
        } else {
            $('#av-name').text('Choose image...');
        }
    });
});
</script>

</body>
</html>
