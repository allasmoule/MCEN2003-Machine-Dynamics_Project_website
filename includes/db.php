<?php
require_once __DIR__ . '/../config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            init_mysql_db($pdo);
        } catch (PDOException $e) {
            // MySQL connection failed (e.g. XAMPP MySQL is not running).
            // Fallback to local SQLite database so the app & admin panel work seamlessly.
            try {
                $sqlitePath = __DIR__ . '/../app.db';
                $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                init_sqlite_db($pdo);
            } catch (PDOException $e2) {
                http_response_code(500);
                echo '<div style="font-family: sans-serif; max-width: 600px; margin: 50px auto; padding: 24px; border: 1px solid #E2E8F0; border-radius: 12px; background: #FFF;">';
                echo '<h2 style="color: #DC2626; margin-top: 0;">Database Connection Error</h2>';
                echo '<p>Could not connect to MySQL database. Please make sure MySQL is started in XAMPP Control Panel.</p>';
                echo '<p><small>Error details: ' . htmlspecialchars($e->getMessage()) . '</small></p>';
                echo '</div>';
                exit;
            }
        }
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

