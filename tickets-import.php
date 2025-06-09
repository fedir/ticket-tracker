<?php
session_start();

// Configuration - Same as main app
define('DATA_DIR', 'data/');
define('UPLOADS_DIR', 'uploads/');
define('ISSUES_FILE', DATA_DIR . 'issues.json');
define('USERS_FILE', DATA_DIR . 'users.json');
define('FILES_FILE', DATA_DIR . 'files.json');
define('LOCALES_FILE', DATA_DIR . 'locales.json');

// Security functions - Same as main app
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

// Data functions - Same as main app
function load_data($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

function save_data($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

function get_next_issue_id() {
    $issues = load_data(ISSUES_FILE);
    return count($issues) + 1;
}

// Language functions - Same as main app
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

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

// Handle import
$imported_count = 0;
$errors = [];

if ($_POST && isset($_POST['import_tickets'])) {
    if (verify_csrf($_POST['csrf_token'])) {
        $tickets_text = $_POST['tickets_text'];
        $default_category = sanitize_input($_POST['default_category']);
        
        if (!empty($tickets_text)) {
            $lines = explode("\n", $tickets_text);
            $issues = load_data(ISSUES_FILE);
            
            foreach ($lines as $line_number => $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Parse line: subject;description
                $parts = explode(';', $line, 2);
                $subject = trim($parts[0]);
                $description = isset($parts[1]) ? trim($parts[1]) : '';
                
                if (empty($subject)) {
                    $errors[] = "Line " . ($line_number + 1) . ": Empty subject";
                    continue;
                }
                
                // Create new issue
                $id = get_next_issue_id() + $imported_count;
                $issue = [
                    'id' => $id,
                    'date' => date('Y-m-d H:i:s'),
                    'category' => $default_category,
                    'subject' => sanitize_input($subject),
                    'description' => sanitize_input($description),
                    'state' => 'new',
                    'author' => $_SESSION['user'],
                    'comments' => [],
                    'attachment' => null
                ];
                
                $issues[] = $issue;
                $imported_count++;
            }
            
            // Save all issues
            if ($imported_count > 0) {
                save_data(ISSUES_FILE, $issues);
            }
        }
    } else {
        $errors[] = "Invalid CSRF token";
    }
}
?>
<!DOCTYPE html>
<html lang="<?= get_locale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('issues') ?> Import</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold"><?= t('issues') ?> Import</h1>
            <div class="flex items-center space-x-4">
                <a href="index.php" class="hover:underline">← Back to Tracker</a>
                <span>Welcome, <?= $_SESSION['user'] ?></span>
                <a href="index.php?logout" class="hover:underline"><?= t('logout') ?></a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-4 max-w-4xl">
        <?php if ($imported_count > 0): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <strong>Success!</strong> Imported <?= $imported_count ?> ticket(s) successfully.
                <a href="index.php" class="ml-4 underline">View all tickets</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Errors:</strong>
                <ul class="mt-2 list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Import Form -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-2xl font-bold mb-6">Import Tickets</h2>
            
            <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                <h3 class="font-bold text-blue-800 mb-2">Format Instructions:</h3>
                <ul class="text-blue-700 text-sm space-y-1">
                    <li>• Each line creates a new ticket</li>
                    <li>• Format: <code class="bg-blue-200 px-1 rounded">Subject;Description</code></li>
                    <li>• The part before semicolon (;) becomes the subject</li>
                    <li>• The part after semicolon becomes the description (optional)</li>
                    <li>• Empty lines are ignored</li>
                </ul>
                
                <div class="mt-3">
                    <strong class="text-blue-800">Examples:</strong>
                    <pre class="mt-1 text-xs bg-blue-200 p-2 rounded"><code>Fix login bug;Users cannot login with special characters in password
Add dark mode;Implement dark theme for better user experience
Update documentation
Improve performance;Database queries are too slow on large datasets</code></pre>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Default Category</label>
                    <select name="default_category" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        <option value="bug"><?= t('bug') ?></option>
                        <option value="feature"><?= t('feature') ?></option>
                        <option value="support"><?= t('support') ?></option>
                        <option value="improvement"><?= t('improvement') ?></option>
                    </select>
                    <p class="text-gray-500 text-xs mt-1">All imported tickets will use this category</p>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Tickets to Import</label>
                    <textarea 
                        name="tickets_text" 
                        required 
                        class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500 font-mono text-sm" 
                        rows="15"
                        placeholder="Fix login bug;Users cannot login with special characters in password
Add dark mode;Implement dark theme for better user experience
Update documentation
Improve performance;Database queries are too slow on large datasets"
                    ></textarea>
                    <p class="text-gray-500 text-xs mt-1">One ticket per line. Use semicolon to separate subject from description.</p>
                </div>

                <div class="flex items-center space-x-4">
                    <button type="submit" name="import_tickets" class="bg-blue-500 text-white py-2 px-6 rounded-lg hover:bg-blue-600">
                        Import Tickets
                    </button>
                    <a href="index.php" class="text-gray-500 hover:underline">Cancel</a>
                </div>
            </form>
        </div>

        <!-- Preview Section -->
        <div class="mt-6 bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold mb-4">Live Preview</h3>
            <div id="preview" class="space-y-2 text-sm">
                <p class="text-gray-500 italic">Type in the textarea above to see a preview of how tickets will be created...</p>
            </div>
        </div>
    </div>

    <script>
        // Live preview functionality
        const textarea = document.querySelector('textarea[name="tickets_text"]');
        const preview = document.getElementById('preview');
        const categorySelect = document.querySelector('select[name="default_category"]');

        function updatePreview() {
            const text = textarea.value.trim();
            const category = categorySelect.value;
            
            if (!text) {
                preview.innerHTML = '<p class="text-gray-500 italic">Type in the textarea above to see a preview of how tickets will be created...</p>';
                return;
            }

            const lines = text.split('\n').filter(line => line.trim());
            let html = '';
            let ticketNumber = <?= get_next_issue_id() ?>;

            lines.forEach((line, index) => {
                line = line.trim();
                if (!line) return;

                const parts = line.split(';', 2);
                const subject = parts[0].trim();
                const description = parts[1] ? parts[1].trim() : '';

                html += `
                    <div class="border rounded p-3 bg-gray-50">
                        <div class="flex items-center space-x-2 mb-1">
                            <span class="font-mono text-xs bg-blue-100 px-2 py-1 rounded">#${ticketNumber}</span>
                            <span class="text-xs bg-gray-200 px-2 py-1 rounded">${category}</span>
                            <span class="text-xs text-gray-500">New</span>
                        </div>
                        <div class="font-medium text-gray-800">${subject || '<span class="text-red-500">Empty subject</span>'}</div>
                        ${description ? `<div class="text-sm text-gray-600 mt-1">${description}</div>` : '<div class="text-xs text-gray-400 italic">No description</div>'}
                    </div>
                `;
                ticketNumber++;
            });

            preview.innerHTML = html || '<p class="text-gray-500 italic">No valid tickets found</p>';
        }

        textarea.addEventListener('input', updatePreview);
        categorySelect.addEventListener('change', updatePreview);
        
        // Initial preview
        updatePreview();
    </script>
</body>
</html>
