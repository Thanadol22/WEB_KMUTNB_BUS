<?php
/**
 * API Authentication Guard
 * Include this file at the top of every API endpoint to ensure
 * that only authenticated admin users can access it.
 */
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized: กรุณาเข้าสู่ระบบก่อนใช้งาน'
    ]);
    exit;
}
