<?php
// notify.php
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
$params = $_GET;
$received_sign = $params['sign'] ?? '';
unset($params['sign'], $params['sign_type']);
$filtered = array_filter($params, function($v) {
    return $v !== "";
});
ksort($filtered);
$query = http_build_query($filtered);
$res = $mysqli->query("SELECT * FROM easypay_config LIMIT 1");
$config_pay = $res->fetch_assoc();
if (!$config_pay) {
    die("未配置易支付信息");
}
$calculated_sign = strtolower(md5($query . $config_pay['key']));
if ($received_sign != $calculated_sign) {
    die("签名验证失败");
}
if ($params['trade_status'] == 'TRADE_SUCCESS') {
    $order_no = $params['out_trade_no'];
    $stmt = $mysqli->prepare("UPDATE orders SET status = 2, update_time = NOW() WHERE order_no = ?");
    $stmt->bind_param("s", $order_no);
    $stmt->execute();
    $stmt->close();
    // 查询订单，获取 TG 用户ID
    $res_order = $mysqli->query("SELECT tg_id FROM orders WHERE order_no = '{$order_no}' LIMIT 1");
    if ($order = $res_order->fetch_assoc()) {
        // 发送 TG 消息通知用户支付成功
        $tg_token = $config['tg_token'];
        $api_url = "https://api.telegram.org/bot{$tg_token}/";
        $url = $api_url . "sendMessage";
        $data = [
            'chat_id' => $order['tg_id'],
            'text'    => "支付成功！"
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_exec($ch);
        curl_close($ch);
    }
    echo "success";
} else {
    echo "fail";
}
