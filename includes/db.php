<?php
require_once __DIR__ . '/../includes/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (DB_DRIVER === 'mysql' && DB_NAME !== '') {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            init_mysql_db($pdo);
            seed_tutorial_files($pdo);
            return $pdo;
        } catch (PDOException $e) {
            $pdo = null; // fall through to SQLite
        }
    }

    // SQLite (default): database/app.db, shared with server.js
    try {
        $path = SQLITE_PATH;
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $legacy = __DIR__ . '/../app.db';
        if (!is_file($path) && is_file($legacy)) @rename($legacy, $path); // one-time move out of the web root
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        init_sqlite_db($pdo);
        seed_tutorial_files($pdo);
    } catch (PDOException $e2) {
        http_response_code(500);
        echo '<div style="font-family: sans-serif; max-width: 600px; margin: 50px auto; padding: 24px; border: 1px solid #E2E8F0; border-radius: 12px; background: #FFF;">';
        echo '<h2 style="color: #DC2626; margin-top: 0;">Database Error</h2>';
        echo '<p>Could not open the SQLite database (database/app.db). Make sure the <code>database</code> folder is writable by PHP (permissions 755) and that the pdo_sqlite extension is enabled.</p>';
        echo '<p><small>Error details: ' . htmlspecialchars($e2->getMessage()) . '</small></p>';
        echo '</div>';
        exit;
    }
    return $pdo;
}

function init_mysql_db(PDO $pdo): void {
    static $initialized = false;
    if ($initialized) return;
    $initialized = true;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                email VARCHAR(190) NOT NULL,
                phone VARCHAR(30) NOT NULL,
                batch VARCHAR(60) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS subjects (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(60) NOT NULL,
                name VARCHAR(200) NOT NULL,
                institution VARCHAR(200) NULL,
                description TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS tutorials (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                subject_id INT UNSIGNED NOT NULL,
                slug VARCHAR(60) NOT NULL,
                title VARCHAR(200) NOT NULL,
                description TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_subject_slug (subject_id, slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS questions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tutorial_id INT UNSIGNED NOT NULL,
                q_key VARCHAR(20) NOT NULL,
                code VARCHAR(20) NOT NULL,
                topic VARCHAR(200) NOT NULL,
                title VARCHAR(300) NOT NULL,
                statement MEDIUMTEXT NOT NULL,
                sketch MEDIUMTEXT NULL,
                fig LONGTEXT NULL,
                fig_caption VARCHAR(200) NULL,
                given_json MEDIUMTEXT NOT NULL,
                hint_json MEDIUMTEXT NOT NULL,
                seed MEDIUMTEXT NULL,
                parts_json MEDIUMTEXT NOT NULL,
                steps_json MEDIUMTEXT NOT NULL,
                original MEDIUMTEXT NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS videos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tutorial_id INT UNSIGNED NOT NULL,
                title VARCHAR(200) NOT NULL,
                url VARCHAR(500) NOT NULL,
                description TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS pdfs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tutorial_id INT UNSIGNED NOT NULL,
                title VARCHAR(200) NOT NULL,
                url VARCHAR(500) NOT NULL,
                description TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS slides (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tutorial_id INT UNSIGNED NOT NULL,
                title VARCHAR(200) NOT NULL,
                url VARCHAR(500) NOT NULL,
                description TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS notes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tutorial_id INT UNSIGNED NOT NULL,
                title VARCHAR(200) NOT NULL,
                content MEDIUMTEXT NOT NULL,
                description TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS content_blocks (
                block_key VARCHAR(60) NOT NULL PRIMARY KEY,
                value_json LONGTEXT NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS contact_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(100) NOT NULL DEFAULT 'General Inquiry',
                name VARCHAR(150) NOT NULL,
                email VARCHAR(190) NOT NULL,
                phone VARCHAR(30) NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Throwable $ex) {}
}

function init_sqlite_db(PDO $pdo): void {
    static $initialized = false;
    if ($initialized) return;
    $initialized = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            phone TEXT NOT NULL,
            batch TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS subjects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            institution TEXT,
            description TEXT,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS tutorials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject_id INTEGER NOT NULL,
            slug TEXT NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(subject_id, slug)
        );

        CREATE TABLE IF NOT EXISTS questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tutorial_id INTEGER DEFAULT 1,
            q_key TEXT NOT NULL,
            code TEXT NOT NULL,
            topic TEXT NOT NULL,
            title TEXT NOT NULL,
            statement TEXT NOT NULL,
            sketch TEXT,
            fig TEXT,
            fig_caption TEXT,
            given_json TEXT NOT NULL,
            hint_json TEXT NOT NULL,
            seed TEXT,
            parts_json TEXT NOT NULL,
            steps_json TEXT NOT NULL,
            original TEXT NOT NULL,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS videos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tutorial_id INTEGER DEFAULT 1,
            title TEXT NOT NULL,
            url TEXT NOT NULL,
            description TEXT,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS pdfs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tutorial_id INTEGER DEFAULT 1,
            title TEXT NOT NULL,
            url TEXT NOT NULL,
            description TEXT,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS slides (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tutorial_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            url TEXT NOT NULL,
            description TEXT,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tutorial_id INTEGER DEFAULT 1,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            description TEXT,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS content_blocks (
            block_key TEXT PRIMARY KEY,
            value_json TEXT NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS contact_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT DEFAULT 'General Inquiry',
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT NOT NULL,
            message TEXT NOT NULL,
            is_read INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $subjCount = (int)$pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
    if ($subjCount === 0) {
        $stmt = $pdo->prepare("INSERT INTO subjects (slug, name, institution, description, sort_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['mcen2003-machine-dynamics', 'MCEN2003 Machine Dynamics', 'Curtin University', 'Interactive Tutorial Workbook for Kinematics.', 1]);
        $subjectId = $pdo->lastInsertId();

        $stmt2 = $pdo->prepare("INSERT INTO tutorials (subject_id, slug, title, description, sort_order) VALUES (?, ?, ?, ?, ?)");
        $stmt2->execute([$subjectId, 'tutorial-1', 'Tutorial 1 — Kinematics', 'Interactive tutorial questions with diagrams & step-by-step reasoning.', 1]);
        $tutorialId = $pdo->lastInsertId();

        $seedFile = __DIR__ . '/../seed/questions_seed.json';
        if (file_exists($seedFile)) {
            $questions = json_decode(file_get_contents($seedFile), true);
            if (is_array($questions)) {
                $insQ = $pdo->prepare("
                    INSERT INTO questions (tutorial_id, q_key, code, topic, title, statement, sketch, fig, fig_caption, given_json, hint_json, seed, parts_json, steps_json, original, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $order = 10;
                foreach ($questions as $q) {
                    $insQ->execute([
                        $tutorialId,
                        $q['id'],
                        $q['code'],
                        $q['topic'],
                        $q['title'],
                        $q['statement'],
                        $q['sketch'] ?? null,
                        $q['fig'] ?? null,
                        $q['figCap'] ?? null,
                        json_encode($q['given']),
                        json_encode($q['hint']),
                        $q['seed'] ?? null,
                        json_encode($q['parts']),
                        json_encode($q['steps']),
                        $q['original'],
                        $order
                    ]);
                    $order += 10;
                }
            }
        }

        $fsFile = __DIR__ . '/../seed/formula_sheet_seed.json';
        if (file_exists($fsFile)) {
            $fsContent = file_get_contents($fsFile);
            $insFS = $pdo->prepare("INSERT INTO content_blocks (block_key, value_json) VALUES ('formula_sheet', ?)");
            $insFS->execute([$fsContent]);
        }
    }
}

/**
 * Same as seedTutorialFiles() in server.js: loads seed/tutorials/*.json so
 * tutorials and questions added through files (the /add-tutorial workflow)
 * appear here too. Questions are upserted by id; videos / PDFs / slides /
 * notes are added when their title is new. Cheap on every request: it skips
 * the work when the files have not changed since they were last applied.
 */
function seed_tutorial_files(PDO $pdo): void {
    $dir = __DIR__ . '/../seed/tutorials';
    if (!is_dir($dir)) return;
    $files = glob($dir . '/*.json') ?: [];
    sort($files);
    if (!$files) return;

    $stamp = '';
    foreach ($files as $f) $stamp .= basename($f) . ':' . filemtime($f) . ':' . filesize($f) . ';';
    $stamp = md5($stamp);
    try {
        $row = $pdo->query("SELECT value_json FROM content_blocks WHERE block_key = 'seed_tutorials_stamp'")->fetchColumn();
        if ($row === $stamp) return;
    } catch (Throwable $e) {}

    $subjectId = $pdo->query("SELECT id FROM subjects WHERE slug = 'mcen2003-machine-dynamics'")->fetchColumn();
    if (!$subjectId) return;

    foreach ($files as $file) {
        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data) || empty($data['slug'])) continue;

        $st = $pdo->prepare('SELECT id FROM tutorials WHERE subject_id = ? AND slug = ?');
        $st->execute([$subjectId, $data['slug']]);
        $tid = $st->fetchColumn();
        if (!$tid) {
            if (empty($data['title'])) continue;
            $pdo->prepare('INSERT INTO tutorials (subject_id, slug, title, description, sort_order) VALUES (?, ?, ?, ?, ?)')
                ->execute([$subjectId, $data['slug'], $data['title'], $data['description'] ?? null, $data['sort_order'] ?? 0]);
            $tid = $pdo->lastInsertId();
        } elseif (!empty($data['title'])) {
            $pdo->prepare('UPDATE tutorials SET title = ?, description = ?, sort_order = ? WHERE id = ?')
                ->execute([$data['title'], $data['description'] ?? null, $data['sort_order'] ?? 0, $tid]);
        }

        foreach (($data['questions'] ?? []) as $q) {
            if (empty($q['id'])) continue;
            $vals = [
                $q['code'] ?? '', $q['topic'] ?? '', $q['title'] ?? '', $q['statement'] ?? '',
                $q['sketch'] ?? null, $q['fig'] ?? null, $q['figCap'] ?? null,
                json_encode($q['given'] ?? []), json_encode($q['hint'] ?? (object)[]), $q['seed'] ?? null,
                json_encode($q['parts'] ?? []), json_encode($q['steps'] ?? []), $q['original'] ?? '',
            ];
            $st = $pdo->prepare('SELECT id FROM questions WHERE tutorial_id = ? AND q_key = ?');
            $st->execute([$tid, $q['id']]);
            $qid = $st->fetchColumn();
            if ($qid) {
                $pdo->prepare('UPDATE questions SET code = ?, topic = ?, title = ?, statement = ?, sketch = ?, fig = ?, fig_caption = ?,
                    given_json = ?, hint_json = ?, seed = ?, parts_json = ?, steps_json = ?, original = ? WHERE id = ?')
                    ->execute(array_merge($vals, [$qid]));
            } else {
                $st = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM questions WHERE tutorial_id = ?');
                $st->execute([$tid]);
                $order = $q['sort_order'] ?? ((int)$st->fetchColumn() + 10);
                $pdo->prepare('INSERT INTO questions (tutorial_id, q_key, code, topic, title, statement, sketch, fig, fig_caption,
                    given_json, hint_json, seed, parts_json, steps_json, original, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute(array_merge([$tid, $q['id']], $vals, [$order]));
            }
        }

        $lists = [
            'videos' => ['title', 'url', 'description'],
            'pdfs'   => ['title', 'url', 'description'],
            'slides' => ['title', 'url', 'description'],
            'notes'  => ['title', 'description', 'content'],
        ];
        foreach ($lists as $table => $cols) {
            foreach (($data[$table] ?? []) as $it) {
                if (empty($it['title'])) continue;
                $st = $pdo->prepare("SELECT id FROM $table WHERE tutorial_id = ? AND title = ?");
                $st->execute([$tid, $it['title']]);
                if ($st->fetchColumn()) continue;
                $st = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM $table WHERE tutorial_id = ?");
                $st->execute([$tid]);
                $order = $it['sort_order'] ?? ((int)$st->fetchColumn() + 10);
                $vals = array_map(fn($c) => $it[$c] ?? null, $cols);
                $pdo->prepare("INSERT INTO $table (tutorial_id, " . implode(', ', $cols) . ", sort_order) VALUES (?, ?, ?, ?, ?)")
                    ->execute(array_merge([$tid], $vals, [$order]));
            }
        }

        if (!empty($data['formula_sheet'])) {
            $key = 'formula_sheet_t' . $tid;
            $json = json_encode($data['formula_sheet'], JSON_UNESCAPED_UNICODE);
            $pdo->prepare('DELETE FROM content_blocks WHERE block_key = ?')->execute([$key]);
            $pdo->prepare('INSERT INTO content_blocks (block_key, value_json) VALUES (?, ?)')->execute([$key, $json]);
        }
    }

    $pdo->prepare("DELETE FROM content_blocks WHERE block_key = 'seed_tutorials_stamp'")->execute();
    $pdo->prepare("INSERT INTO content_blocks (block_key, value_json) VALUES ('seed_tutorials_stamp', ?)")->execute([$stamp]);
}
