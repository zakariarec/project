<?php
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') == 443);

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

define('SESSION_TIMEOUT', 1800);

if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    session_start();
}

$_SESSION['last_activity'] = time();

define('DB_PATH',      __DIR__ . '/data/blog.db');
define('LOG_PATH',     __DIR__ . '/data/users_log.txt');
define('UPLOADS_PATH', __DIR__ . '/uploads/');
class Database {
    private static ?PDO $instance = null;
    public static function get(): PDO {
        if (self::$instance === null) {
            if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755);
            self::$instance = new PDO('sqlite:' . DB_PATH);
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$instance->exec('PRAGMA foreign_keys = ON;');
            self::seed(self::$instance);
        }
        return self::$instance;
    }
    private static function seed(PDO $db): void {
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                username   TEXT NOT NULL UNIQUE,
                email      TEXT NOT NULL UNIQUE,
                password   TEXT NOT NULL,
                role       TEXT NOT NULL DEFAULT 'user',
                bio        TEXT DEFAULT '',
                avatar     TEXT DEFAULT '',
                created_at TEXT DEFAULT (datetime('now'))
            );
            CREATE TABLE IF NOT EXISTS posts (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                title      TEXT NOT NULL,
                content    TEXT NOT NULL,
                image      TEXT DEFAULT '',
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
            CREATE TABLE IF NOT EXISTS comments (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id    INTEGER NOT NULL,
                user_id    INTEGER NOT NULL,
                content    TEXT NOT NULL,
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
            CREATE TABLE IF NOT EXISTS likes (
                user_id    INTEGER NOT NULL,
                post_id    INTEGER NOT NULL,
                PRIMARY KEY (user_id, post_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
            );
        ");

        if ($db->query("SELECT COUNT(*) FROM users")->fetchColumn() == 0) {
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            $db->exec("INSERT INTO users (username, email, password, role) VALUES ('admin', 'admin@blog.com', '$hash', 'admin')");
        }
    }
}
class User {
    private PDO $db;
    public function __construct() { $this->db = Database::get(); }
    public function register(string $username, string $email, string $password): array {
        if (strlen($username) < 3) return ['error' => 'Username needs to be at least 3 characters.'];
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) return ['error' => 'Use only letters, numbers, and underscores for username.'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['error' => 'That email does not look valid.'];
        if (strlen($password) < 6) return ['error' => 'Password needs 6 or more characters.'];
        $s = $this->db->prepare("SELECT id FROM users WHERE username=? OR email=?");
        $s->execute([$username, $email]);
        if ($s->fetch()) return ['error' => 'That username or email is already taken.'];
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->db->prepare("INSERT INTO users (username,email,password) VALUES (?,?,?)")->execute([$username,$email,$hash]);
        return ['success' => true];
    }
    public function login(string $email, string $password): array {
        $s = $this->db->prepare("SELECT * FROM users WHERE email=?");
        $s->execute([$email]);
        $u = $s->fetch();
        if (!$u || !password_verify($password, $u['password'])) return ['error' => 'Login failed. Check your email and password.'];
        return ['success' => true, 'user' => $u];
    }
    public function getById(int $id): ?array {
        $s = $this->db->prepare("SELECT * FROM users WHERE id=?");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }
    public function getAll(): array {
        return $this->db->query("SELECT u.*, (SELECT COUNT(*) FROM posts WHERE user_id=u.id) AS post_count FROM users u ORDER BY u.created_at DESC")->fetchAll();
    }
    public function update(int $id, string $bio, string $avatar): void {
        $this->db->prepare("UPDATE users SET bio=?, avatar=? WHERE id=?")->execute([$bio, $avatar, $id]);
    }
    public function changeRole(int $id, string $role): void {
        $this->db->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $id]);
    }
    public function delete(int $id): void {
        $this->db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    }
    public function writeLog(array $u): void {
        $posts = $this->db->prepare("SELECT COUNT(*) FROM posts WHERE user_id=?");
        $posts->execute([$u['id']]);
        $count   = $posts->fetchColumn();
        $current = file_exists(LOG_PATH) ? file_get_contents(LOG_PATH) : '';
        $line    = substr_count($current, "\n") + 1 . "\t[" . date('Y-m-d H:i:s') . "] #" . $u['id'] . " | " . $u['username'] . " | " . $u['email'] . " | " . $u['role'] . " | posts: $count\n";
        file_put_contents(LOG_PATH, $line, FILE_APPEND);
    }
}
class Post {
    private PDO $db;
    public function __construct() { $this->db = Database::get(); }
    public function create(int $userId, string $title, string $content, string $image = ''): void {
        $title = trim($title);
        $content = trim($content);
        if ($title === '' || $content === '') throw new Exception('Please add both title and content.');
        if (strlen($title) > 120) throw new Exception('Title is too long, keep it shorter.');
        $this->db->prepare("INSERT INTO posts (user_id,title,content,image) VALUES (?,?,?,?)")->execute([$userId,$title,$content,$image]);
    }
    public function getAll(): array {
        return $this->db->query("SELECT p.*, u.username, (SELECT COUNT(*) FROM comments WHERE post_id=p.id) AS comment_count, (SELECT COUNT(*) FROM likes WHERE post_id=p.id) AS like_count FROM posts p JOIN users u ON u.id=p.user_id ORDER BY p.created_at DESC")->fetchAll();
    }
    public function getById(int $id): ?array {
        $s = $this->db->prepare("SELECT p.*, u.username FROM posts p JOIN users u ON u.id=p.user_id WHERE p.id=?");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }
    public function getByUser(int $userId): array {
        $s = $this->db->prepare("SELECT * FROM posts WHERE user_id=? ORDER BY created_at DESC");
        $s->execute([$userId]);
        return $s->fetchAll();
    }
    public function delete(int $id): void {
        $this->db->prepare("DELETE FROM posts WHERE id=?")->execute([$id]);
    }
    public function upload(array $file): string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new Exception('Upload failed, please try again.');
        if ($file['size'] > 4 * 1024 * 1024) throw new Exception('File too large. Max size is 4MB.');

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        $mime = false;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        }

        if (!$mime) {
            $imageInfo = @getimagesize($file['tmp_name']);
            $mime = $imageInfo['mime'] ?? false;
        }

        if (!$mime || !isset($allowed[$mime])) throw new Exception('That file type is not allowed. Use an image.');

        $originalName = pathinfo($file['name'] ?? 'upload', PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($originalName));
        $safeBase = trim($safeBase, '-_');
        if ($safeBase === '') {
            $safeBase = 'image';
        }

        $name = $safeBase . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], UPLOADS_PATH . $name)) {
            throw new Exception('Could not save the file.');
        }
        return $name;
    }
}
class Comment {
    private PDO $db;
    public function __construct() { $this->db = Database::get(); }
    public function create(int $postId, int $userId, string $content): void {
        $this->db->prepare("INSERT INTO comments (post_id,user_id,content) VALUES (?,?,?)")->execute([$postId,$userId,$content]);
    }
    public function getByPost(int $postId): array {
        $s = $this->db->prepare("SELECT c.*, u.username FROM comments c JOIN users u ON u.id=c.user_id WHERE c.post_id=? ORDER BY c.created_at ASC");
        $s->execute([$postId]);
        return $s->fetchAll();
    }
    public function delete(int $id): void {
        $this->db->prepare("DELETE FROM comments WHERE id=?")->execute([$id]);
    }
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function timeAgo(string $dt): string {
    $d = time() - strtotime($dt);
    if ($d < 60)    return $d . 's ago';
    if ($d < 3600)  return floor($d/60) . 'm ago';
    if ($d < 86400) return floor($d/3600) . 'h ago';
    return floor($d/86400) . 'd ago';
}
function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $u = null;
    if ($u === null) {
        $obj = new User();
        $u   = $obj->getById((int)$_SESSION['user_id']);
        if ($u === null) {
            session_destroy();
            session_start();
        }
    }
    return $u;
}
function requireLogin(): void {
    if (empty($_SESSION['user_id']) || currentUser() === null) {
        header('Location: login.php'); exit;
    }
}
function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: index.php'); exit; }
}
function nav(): string {
    $me   = currentUser();
    $page = basename($_SERVER['PHP_SELF'], '.php');
    $links = '<li><a href="index.php"' . ($page==='index'?' class="active"':'') . '>Home</a></li>';
    if ($me) {
        $links .= '<li><a href="profile.php"' . ($page==='profile'?' class="active"':'') . '>Profile</a></li>';
        if ($me['role'] === 'admin') $links .= '<li><a href="admin.php"' . ($page==='admin'?' class="active"':'') . '>Admin</a></li>';
        $links .= '<li><a href="logout.php">Logout</a></li>';
    } else {
        $links .= '<li><a href="login.php"' . ($page==='login'?' class="active"':'') . '>Login</a></li>';
    }
    $user = '';
    if ($me) {
        $avatar = !empty($me['avatar'])
            ? '<img class="nav-avatar" src="uploads/' . h($me['avatar']) . '" alt="' . h($me['username']) . ' avatar">'
            : '<span class="nav-avatar nav-avatar-letter" aria-hidden="true">' . strtoupper($me['username'][0] ?? '?') . '</span>';
        $user = '<div class="nav-right"><a class="nav-profile" href="profile.php" aria-label="Open profile">' . $avatar . '</a></div>';
    }
    return '<nav class="navbar"><ul class="nav-links">' . $links . '</ul>' . $user . '</nav>';
}
