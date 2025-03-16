<?php
// admin/login.php
session_start();
$config = include '../config.php';
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['short_token'])) {
    $token = trim($_GET['short_token']);
    list($headerB64, $payloadB64, $signatureB64) = explode('.', $token);
    $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);
    if (!$payload || !isset($payload['exp']) || time() > $payload['exp']) {
        die("登录链接已过期！");
    }
    $_SESSION['admin_logged_in'] = true;
    header("Location: index.php");
    exit;
} else {
    echo "非法访问！";
}
