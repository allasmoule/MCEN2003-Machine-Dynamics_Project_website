<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$rows = db()->query('
    SELECT s.id, s.slug, s.name, s.institution, s.description,
           (SELECT COUNT(*) FROM tutorials t WHERE t.subject_id = s.id) AS tutorial_count
    FROM subjects s ORDER BY s.sort_order ASC, s.id ASC
')->fetchAll();

json_response(['subjects' => $rows]);
