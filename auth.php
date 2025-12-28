<?php
// auth.php
require 'config.php';

function require_login() {
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        header('Location: login.php');
        exit();
    }
}

function require_role(array $allowed_roles) {
    require_login();
    if (!in_array($_SESSION['role'], $allowed_roles, true)) {
        http_response_code(403);
        echo "Access denied.";
        exit();
    }
}
