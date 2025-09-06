<?php
// admin/login.php
declare(strict_types=1);
session_start();

$config = include __DIR__ . '/../config.php';

/**
 * 安全的 Base64URL 解码
 */
function base64UrlDecode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    $data = strtr($data, '-_', '+/');
    $out = base64_decode($data, true);
    return $out === false ? '' : $out;
}

/**
 * 恒定时间比较，避免时序攻击
 */
function timingSafeEquals(string $a, string $b): bool
{
    if (function_exists('hash_equals')) {
        return hash_equals($a, $b);
    }
    if (strlen($a) !== strlen($b)) return false;
    $res = 0;
    for ($i = 0; $i < strlen($a); $i++) {
        $res |= ord($a[$i]) ^ ord($b[$i]);
    }
    return $res === 0;
}

/**
 * 验证 HS256 JWT
 * - 返回 [bool $ok, array $payload | string $error]
 */
function verifyJWT(string $jwt, string $secret): array
{
    // 基本格式检查
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return [false, 'Token 格式非法'];
    }
    [$hB64, $pB64, $sB64] = $parts;

    $headerJson = base64UrlDecode($hB64);
    $payloadJson = base64UrlDecode($pB64);
    $signature = base64UrlDecode($sB64);

    if ($headerJson === '' || $payloadJson === '' || $signature === '') {
        return [false, 'Token Base64 解析失败'];
    }

    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);

    if (!is_array($header) || !is_array($payload)) {
        return [false, 'Token JSON 解析失败'];
    }

    // 只接受 HS256
    if (($header['alg'] ?? '') !== 'HS256' || ($header['typ'] ?? '') !== 'JWT') {
        return [false, '不支持的 Token 算法/类型'];
    }

    // 重新计算签名
    $data = $hB64 . '.' . $pB64;
    $calc = hash_hmac('sha256', $data, $secret, true);

    if (!timingSafeEquals($calc, $signature)) {
        return [false, '签名验证失败'];
    }

    // exp 校验（如果有）
    if (!isset($payload['exp']) || !is_numeric($payload['exp'])) {
        return [false, '缺少到期时间 exp'];
    }
    if (time() > (int)$payload['exp']) {
        return [false, '登录链接已过期'];
    }

    return [true, $payload];
}

// 仅允许 GET + 带 short_token
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['short_token'])) {
    http_response_code(400);
    echo '非法访问！';
    exit;
}

$token = trim((string)$_GET['short_token']);
if ($token === '' || strlen($token) > 4096) {
    http_response_code(400);
    echo 'Token 非法！';
    exit;
}

// 使用与 bot.php 相同的密钥验证（那里用的是 $config["tg_token"] 作为 HS256 密钥）
[$ok, $payloadOrError] = verifyJWT($token, (string)$config['tg_token']);
if (!$ok) {
    http_response_code(401);
    echo htmlspecialchars((string)$payloadOrError, ENT_QUOTES, 'UTF-8');
    exit;
}

$payload = $payloadOrError;

// 业务字段严格校验：只允许机器人主人使用一次性链接
$ownerId = (string)$config['owner_id'];
if (($payload['action'] ?? '') !== 'login') {
    http_response_code(403);
    echo 'Token 用途不正确';
    exit;
}
if ((string)($payload['bot_id'] ?? '') !== $ownerId) {
    http_response_code(403);
    echo 'bot_id 不匹配';
    exit;
}
if ((string)($payload['user_id'] ?? '') !== $ownerId) {
    http_response_code(403);
    echo '无权限（仅限管理员使用）';
    exit;
}

// ✅ 验证通过：建立会话并跳转后台首页
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_login_time'] = time();
header('Location: index.php');
exit;
