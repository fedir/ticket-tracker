<?php
session_start();

// Configuration
define('DATA_DIR', 'data/');
define('UPLOADS_DIR', 'uploads/');
define('ISSUES_FILE', DATA_DIR . 'issues.json');
define('USERS_FILE', DATA_DIR . 'users.json');
define('FILES_FILE', DATA_DIR . 'files.json');
define('LOCALES_FILE', DATA_DIR . 'locales.json');

// Security functions
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Initialize data files
function init_data_files() {
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
    if (!is_dir(UPLOADS_DIR)) mkdir(UPLOADS_DIR, 0755, true);
    
    // Initialize users.json with default admin
    if (!file_exists(USERS_FILE)) {
        $users = [
            'admin' => [
                'password' => password_hash('tracker02', PASSWORD_DEFAULT),
                'role' => 'admin'
            ]
        ];
        file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
    }
    
    // Initialize issues.json
    if (!file_exists(ISSUES_FILE)) {
        file_put_contents(ISSUES_FILE, json_encode([], JSON_PRETTY_PRINT));
    }
    
    // Initialize files.json
    if (!file_exists(FILES_FILE)) {
        file_put_contents(FILES_FILE, json_encode([], JSON_PRETTY_PRINT));
    }
    
    // Initialize locales.json
    if (!file_exists(LOCALES_FILE)) {
        $locales = [
            'fr' => [
                'login' => 'Connexion',
                'logout' => 'Déconnexion',
                'username' => 'Nom d\'utilisateur',
                'password' => 'Mot de passe',
                'submit' => 'Valider',
                'home' => 'Accueil',
                'new_issue' => 'Nouveau ticket',
                'issues' => 'Tickets',
                'issue' => 'Ticket',
                'id' => 'ID',
                'date' => 'Date',
                'category' => 'Catégorie',
                'subject' => 'Sujet',
                'description' => 'Description',
                'state' => 'État',
                'comments' => 'Commentaires',
                'comment' => 'Commentaire',
                'author' => 'Auteur',
                'attachment' => 'Pièce jointe',
                'add_comment' => 'Ajouter un commentaire',
                'download' => 'Télécharger',
                'new' => 'Nouveau',
                'in_process' => 'En cours',
                'review' => 'En révision',
                'done' => 'Terminé',
                'bug' => 'Bug',
                'feature' => 'Fonctionnalité',
                'support' => 'Support',
                'improvement' => 'Amélioration'
            ],
            'en' => [
                'login' => 'Login',
                'logout' => 'Logout',
                'username' => 'Username',
                'password' => 'Password',
                'submit' => 'Submit',
                'home' => 'Home',
                'new_issue' => 'New Issue',
                'issues' => 'Issues',
                'issue' => 'Issue',
                'id' => 'ID',
                'date' => 'Date',
                'category' => 'Category',
                'subject' => 'Subject',
                'description' => 'Description',
                'state' => 'State',
                'comments' => 'Comments',
                'comment' => 'Comment',
                'author' => 'Author',
                'attachment' => 'Attachment',
                'add_comment' => 'Add Comment',
                'download' => 'Download',
                'new' => 'New',
                'in_process' => 'In Process',
                'review' => 'Review',
                'done' => 'Done',
                'bug' => 'Bug',
                'feature' => 'Feature',
                'support' => 'Support',
                'improvement' => 'Improvement'
            ]
        ];
        file_put_contents(LOCALES_FILE, json_encode($locales, JSON_PRETTY_PRINT));
    }
    
    // Create .htaccess for uploads protection
    $htaccess_content = "Order Deny,Allow\nDeny from all\n";
    file_put_contents(UPLOADS_DIR . '.htaccess', $htaccess_content);
}

// Language functions
function get_locale() {
    return $_SESSION['locale'] ?? 'fr';
}

function t($key) {
    static $locales = null;
    if ($locales === null) {
        $locales = json_decode(file_get_contents(LOCALES_FILE), true);
    }
    $locale = get_locale();
    return $locales[$locale][$key] ?? $key;
}

// Data functions
function load_data($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

function save_data($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function authenticate($username, $password) {
    $users = load_data(USERS_FILE);
    if (isset($users[$username]) && password_verify($password, $users[$username]['password'])) {
        $_SESSION['user'] = $username;
        $_SESSION['role'] = $users[$username]['role'];
        return true;
    }
    return false;
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

function get_next_issue_id() {
    $issues = load_data(ISSUES_FILE);
    return count($issues) + 1;
}

function upload_file($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    
    $hash = hash('sha256', $file['name'] . time());
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $hash . '.' . $extension;
    $filepath = UPLOADS_DIR . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $files = load_data(FILES_FILE);
        $files[$hash] = [
            'original_name' => $file['name'],
            'filename' => $filename,
            'size' => $file['size'],
            'type' => $file['type'],
            'uploaded_by' => $_SESSION['user'],
            'uploaded_at' => date('Y-m-d H:i:s')
        ];
        save_data(FILES_FILE, $files);
        return $hash;
    }
    return false;
}

// Initialize
init_data_files();

// Handle language switch
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    $_SESSION['locale'] = $_GET['lang'];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle login
if ($_POST && isset($_POST['login'])) {
    if (verify_csrf($_POST['csrf_token']) && authenticate(sanitize_input($_POST['username']), $_POST['password'])) {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}

// Handle new issue
if ($_POST && isset($_POST['new_issue']) && is_logged_in()) {
    if (verify_csrf($_POST['csrf_token'])) {
        $issues = load_data(ISSUES_FILE);
        $id = get_next_issue_id();
        
        $attachment = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $attachment = upload_file($_FILES['attachment']);
        }
        
        $issue = [
            'id' => $id,
            'date' => date('Y-m-d H:i:s'),
            'category' => sanitize_input($_POST['category']),
            'subject' => sanitize_input($_POST['subject']),
            'description' => sanitize_input($_POST['description']),
            'state' => 'new',
            'author' => $_SESSION['user'],
            'comments' => [],
            'attachment' => $attachment
        ];
        
        $issues[] = $issue;
        save_data(ISSUES_FILE, $issues);
        header('Location: ?view=issue&id=' . $id);
        exit;
    }
}

// Handle new comment
if ($_POST && isset($_POST['new_comment']) && is_logged_in()) {
    if (verify_csrf($_POST['csrf_token'])) {
        $issues = load_data(ISSUES_FILE);
        $issue_id = (int)$_POST['issue_id'];
        
        $attachment = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $attachment = upload_file($_FILES['attachment']);
        }
        
        foreach ($issues as &$issue) {
            if ($issue['id'] == $issue_id) {
                $comment = [
                    'date' => date('Y-m-d H:i:s'),
                    'comment' => sanitize_input($_POST['comment']),
                    'author' => $_SESSION['user'],
                    'attachment' => $attachment
                ];
                $issue['comments'][] = $comment;
                break;
            }
        }
        
        save_data(ISSUES_FILE, $issues);
        header('Location: ?view=issue&id=' . $issue_id);
        exit;
    }
}

// Handle state update
if ($_POST && isset($_POST['update_state']) && is_logged_in()) {
    if (verify_csrf($_POST['csrf_token'])) {
        $issues = load_data(ISSUES_FILE);
        $issue_id = (int)$_POST['issue_id'];
        $new_state = sanitize_input($_POST['state']);
        
        foreach ($issues as &$issue) {
            if ($issue['id'] == $issue_id) {
                $issue['state'] = $new_state;
                break;
            }
        }
        
        save_data(ISSUES_FILE, $issues);
        header('Location: ?view=issue&id=' . $issue_id);
        exit;
    }
}

// Handle file download
if (isset($_GET['download']) && is_logged_in()) {
    $file_hash = sanitize_input($_GET['download']);
    $files = load_data(FILES_FILE);
    
    if (isset($files[$file_hash])) {
        $file_info = $files[$file_hash];
        $filepath = UPLOADS_DIR . $file_info['filename'];
        
        if (file_exists($filepath)) {
            // Set headers for download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file_info['original_name'] . '"');
            header('Content-Length: ' . filesize($filepath));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            // Output file
            readfile($filepath);
            exit;
        }
    }
    
    // File not found
    header('HTTP/1.0 404 Not Found');
    echo 'File not found';
    exit;
}

$view = $_GET['view'] ?? 'home';
$issue_id = $_GET['id'] ?? null;
?>
<!DOCTYPE html>
<html lang="<?= get_locale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('issues') ?> Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php if (!is_logged_in()): ?>
        <!-- Login Form -->
        <div class="min-h-screen flex items-center justify-center">
            <div class="bg-white p-8 rounded-lg shadow-md w-96">
                <h1 class="text-2xl font-bold mb-6 text-center"><?= t('login') ?></h1>
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= $error ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2"><?= t('username') ?></label>
                        <input type="text" name="username" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2"><?= t('password') ?></label>
                        <input type="password" name="password" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    <button type="submit" name="login" class="w-full bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600"><?= t('submit') ?></button>
                </form>
                <div class="mt-4 text-center">
                    <a href="?lang=fr" class="text-blue-500 hover:underline">Français</a> | 
                    <a href="?lang=en" class="text-blue-500 hover:underline">English</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Navigation -->
        <nav class="bg-blue-600 text-white p-4">
            <div class="container mx-auto flex justify-between items-center">
                <h1 class="text-xl font-bold"><?= t('issues') ?> Tracker</h1>
                <div class="flex items-center space-x-4">
                    <a href="?" class="hover:underline"><?= t('home') ?></a>
                    <a href="?view=new" class="hover:underline"><?= t('new_issue') ?></a>
                    <span>Welcome, <?= $_SESSION['user'] ?></span>
                    <a href="?logout" class="hover:underline"><?= t('logout') ?></a>
                    <a href="?lang=fr" class="hover:underline">FR</a>
                    <a href="?lang=en" class="hover:underline">EN</a>
                </div>
            </div>
        </nav>

        <div class="container mx-auto p-4">
            <?php if ($view === 'home'): ?>
                <!-- Issues List -->
                <h2 class="text-2xl font-bold mb-4"><?= t('issues') ?></h2>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left"><?= t('id') ?></th>
                                <th class="px-4 py-2 text-left"><?= t('date') ?></th>
                                <th class="px-4 py-2 text-left"><?= t('subject') ?></th>
                                <th class="px-4 py-2 text-left"><?= t('category') ?></th>
                                <th class="px-4 py-2 text-left"><?= t('state') ?></th>
                                <th class="px-4 py-2 text-left"><?= t('comments') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $issues = load_data(ISSUES_FILE); ?>
                            <?php foreach (array_reverse($issues) as $issue): ?>
                                <tr class="border-t hover:bg-gray-50">
                                    <td class="px-4 py-2">
                                        <a href="?view=issue&id=<?= $issue['id'] ?>" class="text-blue-500 hover:underline">#<?= $issue['id'] ?></a>
                                    </td>
                                    <td class="px-4 py-2"><?= date('Y-m-d', strtotime($issue['date'])) ?></td>
                                    <td class="px-4 py-2"><?= $issue['subject'] ?></td>
                                    <td class="px-4 py-2"><?= t($issue['category']) ?></td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-1 text-xs rounded-full 
                                            <?= $issue['state'] === 'new' ? 'bg-blue-100 text-blue-800' : 
                                               ($issue['state'] === 'in_process' ? 'bg-yellow-100 text-yellow-800' : 
                                               ($issue['state'] === 'review' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800')) ?>">
                                            <?= t($issue['state']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2"><?= count($issue['comments']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($view === 'issue' && $issue_id): ?>
                <!-- Issue Details -->
                <?php 
                $issues = load_data(ISSUES_FILE);
                $current_issue = null;
                foreach ($issues as $issue) {
                    if ($issue['id'] == $issue_id) {
                        $current_issue = $issue;
                        break;
                    }
                }
                if (!$current_issue): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">Issue not found</div>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <div class="flex justify-between items-start mb-4">
                            <h2 class="text-2xl font-bold">#<?= $current_issue['id'] ?> - <?= $current_issue['subject'] ?></h2>
                            <form method="POST" class="flex items-center space-x-2">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="issue_id" value="<?= $current_issue['id'] ?>">
                                <select name="state" class="px-3 py-1 border rounded">
                                    <option value="new" <?= $current_issue['state'] === 'new' ? 'selected' : '' ?>><?= t('new') ?></option>
                                    <option value="in_process" <?= $current_issue['state'] === 'in_process' ? 'selected' : '' ?>><?= t('in_process') ?></option>
                                    <option value="review" <?= $current_issue['state'] === 'review' ? 'selected' : '' ?>><?= t('review') ?></option>
                                    <option value="done" <?= $current_issue['state'] === 'done' ? 'selected' : '' ?>><?= t('done') ?></option>
                                </select>
                                <button type="submit" name="update_state" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">Update</button>
                            </form>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4 text-sm text-gray-600">
                            <div><strong><?= t('date') ?>:</strong> <?= $current_issue['date'] ?></div>
                            <div><strong><?= t('category') ?>:</strong> <?= t($current_issue['category']) ?></div>
                            <div><strong><?= t('author') ?>:</strong> <?= $current_issue['author'] ?></div>
                            <div><strong><?= t('state') ?>:</strong> <?= t($current_issue['state']) ?></div>
                        </div>
                        <div class="mb-4">
                            <strong><?= t('description') ?>:</strong>
                            <p class="mt-2 text-gray-700"><?= nl2br($current_issue['description']) ?></p>
                        </div>
                        <?php if ($current_issue['attachment']): ?>
                            <?php $files = load_data(FILES_FILE); ?>
                            <?php if (isset($files[$current_issue['attachment']])): ?>
                                <div class="mb-4">
                                    <strong><?= t('attachment') ?>:</strong>
                                    <a href="?download=<?= $current_issue['attachment'] ?>" class="ml-2 text-blue-500 hover:underline">
                                        📎 <?= $files[$current_issue['attachment']]['original_name'] ?>
                                    </a>
                                    <span class="ml-2 text-gray-500 text-sm">(<?= number_format($files[$current_issue['attachment']]['size'] / 1024, 1) ?> KB)</span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Comments -->
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <h3 class="text-xl font-bold mb-4"><?= t('comments') ?> (<?= count($current_issue['comments']) ?>)</h3>
                        <?php foreach ($current_issue['comments'] as $comment): ?>
                            <div class="border-b pb-4 mb-4 last:border-b-0">
                                <div class="flex justify-between items-center mb-2">
                                    <strong><?= $comment['author'] ?></strong>
                                    <span class="text-sm text-gray-500"><?= $comment['date'] ?></span>
                                </div>
                                <p class="text-gray-700 mb-2"><?= nl2br($comment['comment']) ?></p>
                                <?php if ($comment['attachment']): ?>
                                    <?php $files = load_data(FILES_FILE); ?>
                                    <?php if (isset($files[$comment['attachment']])): ?>
                                        <div class="text-sm">
                                            <a href="?download=<?= $comment['attachment'] ?>" class="text-blue-500 hover:underline">
                                                📎 <?= $files[$comment['attachment']]['original_name'] ?>
                                            </a>
                                            <span class="ml-2 text-gray-500">(<?= number_format($files[$comment['attachment']]['size'] / 1024, 1) ?> KB)</span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <!-- Add Comment -->
                        <form method="POST" enctype="multipart/form-data" class="mt-6">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="issue_id" value="<?= $current_issue['id'] ?>">
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2"><?= t('add_comment') ?></label>
                                <textarea name="comment" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" rows="4"></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2"><?= t('attachment') ?></label>
                                <input type="file" name="attachment" class="w-full px-3 py-2 border rounded-lg">
                            </div>
                            <button type="submit" name="new_comment" class="bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600"><?= t('submit') ?></button>
                        </form>
                    </div>
                <?php endif; ?>

            <?php elseif ($view === 'new'): ?>
                <!-- New Issue Form -->
                <h2 class="text-2xl font-bold mb-6"><?= t('new_issue') ?></h2>
                <div class="bg-white rounded-lg shadow p-6">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2"><?= t('category') ?></label>
                            <select name="category" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                                <option value="bug"><?= t('bug') ?></option>
                                <option value="feature"><?= t('feature') ?></option>
                                <option value="support"><?= t('support') ?></option>
                                <option value="improvement"><?= t('improvement') ?></option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2"><?= t('subject') ?></label>
                            <input type="text" name="subject" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2"><?= t('description') ?></label>
                            <textarea name="description" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" rows="6"></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2"><?= t('attachment') ?></label>
                            <input type="file" name="attachment" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <button type="submit" name="new_issue" class="bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600"><?= t('submit') ?></button>
                        <a href="?" class="ml-4 text-gray-500 hover:underline">Cancel</a>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>
