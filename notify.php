<?php
$config = include 'config.php';

// === DB ===
$mysqli = new mysqli(
    $config['mysql']['host'],
    $config['mysql']['user'],
    $config['mysql']['password'],
    $config['mysql']['database'],
    $config['mysql']['port']
);
if ($mysqli->connect_errno) {
    die("MySQL连接失败：" . $mysqli->connect_error);
}

// === 参数：POST 优先，兼容 GET ===
$params = $_POST ?: $_GET;

// === 验签（手工拼接，不使用 http_build_query）===
$received_sign = isset($params['sign']) ? (string)$params['sign'] : '';
unset($params['sign'], $params['sign_type']);

// 过滤空值并按键名 ASCII 排序
$filtered = array_filter($params, function ($v) {
    return $v !== "" && $v !== null;
});
ksort($filtered);

// 手工拼接 a=b&c=d（不做 urlencode）
$pairs = [];
foreach ($filtered as $k => $v) {
    $pairs[] = $k . '=' . $v;
}
$query = implode('&', $pairs);

// 取密钥
$res = $mysqli->query("SELECT * FROM easypay_config LIMIT 1");
$config_pay = $res ? $res->fetch_assoc() : null;
if (!$config_pay) {
    die("未配置易支付信息");
}

$calculated_sign = strtolower(md5($query . $config_pay['key']));
if (!hash_equals($calculated_sign, strtolower($received_sign))) {
    die("签名验证失败");
}

// === 关键信息 ===
$trade_status = isset($filtered['trade_status']) ? (string)$filtered['trade_status'] : '';
$order_no     = isset($filtered['out_trade_no']) ? (string)$filtered['out_trade_no'] : '';
$gateway_type = isset($filtered['type']) ? strtolower((string)$filtered['type']) : '';
$money_str    = isset($filtered['money']) ? (string)$filtered['money'] : null;

// === 查订单 ===
$stmt = $mysqli->prepare("SELECT tg_id, amount, status FROM orders WHERE order_no = ? LIMIT 1");
$stmt->bind_param("s", $order_no);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    // 仍返回 success，避免网关反复重试
    echo "success";
    exit;
}

// 金额核对（仅校验不改库；若不需要可注释下面 4 行）
if ($money_str !== null) {
    $paid_cents  = (int)round((float)$money_str * 100);
    $order_cents = (int)round((float)$order['amount'] * 100);
    if ($paid_cents !== $order_cents) {
        // 金额不一致直接返回 success（避免重试轰炸）；如需失败重试可 echo "fail"
        echo "success";
        exit;
    }
}


$pay_type_cn = $gateway_type === 'alipay' ? '支付宝' : ($gateway_type === 'wxpay' ? '微信' : $gateway_type);


if ((int)$order['status'] === 2) {
    echo "success";
    exit;
}

if ($trade_status === 'TRADE_SUCCESS') {
    
    $stmt = $mysqli->prepare("UPDATE orders SET status = 2, pay_type = ?, update_time = NOW() WHERE order_no = ? LIMIT 1");
    $stmt->bind_param("ss", $pay_type_cn, $order_no);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    
    if ($affected > 0) {
        $tg_id = (string)$order['tg_id'];
        if ($tg_id !== '') {
            $tg_token = $config['tg_token'];
            $api_url  = "https://api.telegram.org/bot{$tg_token}/sendMessage";
            $amount_fmt = number_format((float)$order['amount'], 2, '.', '');

           
            $text = "🎉 <b>支付结果通知</b>\n\n"
                  . "✅ 您的支付已成功！\n\n"
                  . "💳 <b>订单号：</b><code>{$order_no}</code>\n"
                  . "💰 <b>支付金额：</b>{$amount_fmt} 元\n"
                  . "💱 <b>支付方式：</b>{$pay_type_cn}\n\n"
                  . "感谢您的使用，我们已收到您的付款。";

            $data = [
                'chat_id'    => $tg_id,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ];

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_exec($ch);
            curl_close($ch);
        }
    }

    echo "success";
} else {
   
    echo "fail";
}
