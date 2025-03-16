<?php
// return.php
$config = include 'config.php';
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

// 取GET参数
$params = $_GET;

// 可选：签名校验
$received_sign = $params['sign'] ?? '';
unset($params['sign'], $params['sign_type']);

// 过滤空值并排序
$filtered = array_filter($params, function($v) {
    return $v !== "";
});
ksort($filtered);
$query = http_build_query($filtered);

// 从数据库中获取易支付配置，获取key
$res = $mysqli->query("SELECT * FROM easypay_config LIMIT 1");
$config_pay = $res->fetch_assoc();
if (!$config_pay) {
    die("未配置易支付信息");
}
$calculated_sign = strtolower(md5($query . $config_pay['key']));

// 判断签名是否正确
$sign_ok = ($received_sign === $calculated_sign);

// 取关键信息
$trade_status = $params['trade_status'] ?? '';
$out_trade_no = $params['out_trade_no'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>支付结果</title>
</head>
<body>
<?php if ($sign_ok && $trade_status === 'TRADE_SUCCESS'): ?>
    <h2>支付成功</h2>
    <p>您的订单号：<?= htmlspecialchars($out_trade_no) ?></p>
    <p>系统已收到支付结果，若有疑问请联系管理员。</p>
<?php else: ?>
    <h2>支付失败或签名错误</h2>
    <p>如果您确认已扣款，请联系管理员核实订单状态。</p>
<?php endif; ?>
</body>
</html>
