<?php
require_once __DIR__ . '/../includes/functions.php';

$user = current_user();
json_response(['user' => $user]);
