<?php
// bot.php
set_time_limit(0);
date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = include 'config.php';
$tg_token = $config['tg_token'];
$api_url = "https://api.telegram.org/bot{$tg_token}/";

/** Telegram 请求辅助 */
function tgRequest($method, $params = [])
{
    global $api_url;
    $url = $api_url . $method;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // 统一使用 POST
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $result = curl_exec($ch);
    if ($result === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'error' => $err];
    }
    curl_close($ch);
    $json = json_decode($result, true);
    return $json ?: ['ok' => false, 'raw' => $result];
}

// 设置命令（中文描述不转义）
tgRequest("setMyCommands", [
    'commands' => json_encode([
        ['command' => 'start', 'description' => '支付'],
        ['command' => 'login', 'description' => '后台登录'],
        ['command' => 'pay',   'description' => '快速支付 /pay 88.66'],
    ], JSON_UNESCAPED_UNICODE),
]);

// 连接 MySQL
$mysqli = new mysqli(
    $config['mysql']['host'],
    $config['mysql']['user'],
    $config['mysql']['password'],
    $config['mysql']['database'],
    (int)$config['mysql']['port']
);
if ($mysqli->connect_errno) {
    die("MySQL连接失败：" . $mysqli->connect_error);
}

/** 生成唯一订单号 */
function generateOrderNo()
{
    return date("YmdHis") . mt_rand(1000, 9999);
}

/**
 * 易支付签名算法
 * 1. 移除 sign, sign_type 和空值参数
 * 2. 按参数名ASCII从小到大排序
 * 3. 拼接为 a=b&c=d...（不URL编码）
 * 4. 末尾追加 KEY，md5 后转小写
 */
function generateSign($params, $key)
{
    unset($params['sign'], $params['sign_type']);
    $filtered = array_filter($params, function ($v) {
        return $v !== "" && $v !== null;
    });
    ksort($filtered);
    $pairs = [];
    foreach ($filtered as $k => $v) {
        $pairs[] = "$k=$v";
    }
    $query = implode("&", $pairs);
    return strtolower(md5($query . $key));
}

/** 发起易支付请求 */
function initiatePayment($order, $pay_type)
{
    global $mysqli;

    $res = $mysqli->query("SELECT * FROM easypay_config LIMIT 1");
    $config_pay = $res ? $res->fetch_assoc() : null;
    if (!$config_pay) {
        return ['code' => 0, 'msg' => '未配置易支付信息'];
    }

    $device = ($pay_type === 'wxpay') ? 'wechat' : (($pay_type === 'alipay') ? 'alipay' : 'pc');

    $params = [
        'pid'          => $config_pay['pid'],
        'type'         => $pay_type,
        'out_trade_no' => $order['order_no'],
        'notify_url'   => $config_pay['notify_url'],
        'return_url'   => $config_pay['return_url'],
        'name'         => "TG",
        'money'        => number_format((float)$order['amount'], 2, '.', ''),
        'clientip'     => (string)($order['client_ip'] ?? ''),
        'device'       => $device,
        'param'        => '',
        'sign_type'    => 'MD5',
    ];

    $params['sign'] = generateSign($params, $config_pay['key']);

    $ch = curl_init($config_pay['gateway']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['code' => 0, 'msg' => "CURL错误: $error"];
    }

    $json = json_decode($response, true);
    if (!$json) {
        return ['code' => 0, 'msg' => "JSON解析失败: $response"];
    }
    return $json;
}

// --------------------------
// JWT 一次性登录链接相关函数
// --------------------------
function base64UrlEncode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function generateJWT($payload, $secret)
{
    $header = json_encode(["alg" => "HS256", "typ" => "JWT"], JSON_UNESCAPED_UNICODE);
    $payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $base64UrlHeader = base64UrlEncode($header);
    $base64UrlPayload = base64UrlEncode($payload);
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = base64UrlEncode($signature);
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

/** 生成一次性登录链接（有效60秒） */
function generateLoginLink($tg_id)
{
    global $config;
    $payload = [
        "action"  => "login",
        "bot_id"  => (string)$config['owner_id'],
        "exp"     => time() + 60,
        "user_id" => (string)$tg_id,
    ];
    $jwt = generateJWT($payload, $config['tg_token']);
    return rtrim($config['domain'], '/') . "/admin/login.php?short_token=" . $jwt;
}
// --------------------------

/** 发送“选择支付方式”的消息 */
function sendChoosePayUI($chat_id, $order_no, $amount)
{
    $textTpl =
        "🧾 <b>支付订单创建完成！</b>\n\n" .
        "💰 <b>支付金额：</b><code>{$amount}</code> 元\n" .
        "💱 <b>支付货币：</b><code>CNY</code>\n" .
        "👤 <b>支付名称：</b><code>TG{$chat_id}</code>\n\n" .
        "📌 <b>请选择下方支付方式进行付款：</b>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '支付宝', 'callback_data' => "pay_alipay:{$order_no}"],
                ['text' => '微信',   'callback_data' => "pay_wxpay:{$order_no}"],
            ],
            [
                ['text' => '❌ 取消操作', 'callback_data' => "cancel_order:{$order_no}"],
            ],
        ],
    ];
    tgRequest("sendMessage", [
        'parse_mode'   => 'HTML',
        'chat_id'      => $chat_id,
        'text'         => $textTpl,
        'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
    ]);
}

$offset = 0;
while (true) {
    $resp = tgRequest("getUpdates", ['offset' => $offset, 'timeout' => 20]);
    if ($resp && isset($resp['result'])) {
        foreach ($resp['result'] as $update) {
            $offset = $update['update_id'] + 1;

            /* ================= 文本消息 ================= */
            if (isset($update['message'])) {
                $message = $update['message'];
                $chat_id = $message['chat']['id'];
                $text    = trim($message['text'] ?? '');

                // /start
                if (strpos($text, '/start') === 0) {
                    $keyboard = [
                        'inline_keyboard' => [
                            [['text' => '💰 输入支付金额', 'callback_data' => 'enter_amount']],
                        ],
                    ];
                    tgRequest("sendMessage", [
                        'chat_id' => $chat_id,
                        'text' => "💳 支付中心\n\n请点击下方按钮，然后发送您要支付的金额。\n⚠️ 请确保金额为数字（最多两位小数），不得超过 999 元",
                        'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
                    ]);
                    continue;
                }

                // /login（仅 owner）
                if (strpos($text, '/login') === 0) {
                    if ((string)$chat_id !== (string)$config['owner_id']) {
                        tgRequest("sendMessage", [
                            'chat_id' => $chat_id,
                            'text'    => "🚫 无权限登录后台，仅限管理员使用。",
                        ]);
                    } else {
                        $link = generateLoginLink($chat_id);
                        tgRequest("sendMessage", [
                            'chat_id'    => $chat_id,
                            'parse_mode' => 'HTML',
                            'text'       => "🔐 <b>后台登录授权</b>\n\n"
                                . "请点击以下链接登录后台（<b>60秒</b> 内有效）：\n"
                                . "<a href=\"{$link}\">👉 点击进入后台</a>\n\n"
                                . "如链接过期，请重新发送 <code>/login</code> 获取新链接。",
                        ]);
                    }
                    continue;
                }

                // /pay 88.66 （可选）
                if (strpos($text, '/pay') === 0) {
                    $parts = preg_split('/\s+/', $text);
                    if (count($parts) >= 2 && preg_match('/^\d{1,3}(\.\d{1,2})?$/', $parts[1])) {
                        $amount = (float)$parts[1];
                    } else {
                        tgRequest("sendMessage", [
                            'chat_id' => $chat_id,
                            'text'    => "格式错误，请使用：/pay 金额（如 /pay 88.66）",
                        ]);
                        continue;
                    }
                    if ($amount <= 0 || $amount > 999) {
                        tgRequest("sendMessage", [
                            'chat_id' => $chat_id,
                            'text'    => "⚠️ 金额必须为 0~999 间的数字，最多两位小数",
                        ]);
                        continue;
                    }

                    $order_no = generateOrderNo();
                    $stmt = $mysqli->prepare("INSERT INTO orders (order_no, tg_id, amount, status, create_time) VALUES (?, ?, ?, 0, NOW())");
                    $stmt->bind_param("sid", $order_no, $chat_id, $amount);
                    $stmt->execute();
                    $stmt->close();

                    sendChoosePayUI($chat_id, $order_no, $amount);
                    continue;
                }

                // 直接发送金额
                if ($text !== '' && preg_match('/^\d{1,3}(\.\d{1,2})?$/', $text)) {
                    $amount = (float)$text;
                    if ($amount <= 0 || $amount > 999) {
                        tgRequest("sendMessage", [
                            'chat_id' => $chat_id,
                            'text'    => "⚠️ 金额必须为 0~999 间的数字，最多两位小数",
                        ]);
                        continue;
                    }

                    $order_no = generateOrderNo();
                    $stmt = $mysqli->prepare("INSERT INTO orders (order_no, tg_id, amount, status, create_time) VALUES (?, ?, ?, 0, NOW())");
                    $stmt->bind_param("sid", $order_no, $chat_id, $amount);
                    $stmt->execute();
                    $stmt->close();

                    sendChoosePayUI($chat_id, $order_no, $amount);
                    continue;
                }

                // 其他文本忽略（保持安静）
                continue;
            }

            /* ================= 回调按钮 ================= */
            if (isset($update['callback_query'])) {
                $callback   = $update['callback_query'];
                $chat_id    = $callback['message']['chat']['id'];
                $message_id = $callback['message']['message_id'];
                $data       = $callback['data'] ?? '';

                // “输入金额”按钮
                if ($data === 'enter_amount') {
                    tgRequest("sendMessage", [
                        'chat_id' => $chat_id,
                        'text'    => "💰 请输入您要支付的金额，例如：88.66",
                    ]);
                    tgRequest("answerCallbackQuery", [
                        'callback_query_id' => $callback['id'],
                        'text'       => "请输入金额",
                        'show_alert' => false,
                    ]);
                    continue;
                }

                // 形如 action:order_no
                if (strpos($data, ':') !== false) {
                    list($action, $order_no) = explode(":", $data, 2);

                    // 取消订单：删除消息 + hint
                    if ($action === 'cancel_order') {
                        tgRequest("deleteMessage", [
                            'chat_id'    => $chat_id,
                            'message_id' => $message_id,
                        ]);
                        tgRequest("answerCallbackQuery", [
                            'callback_query_id' => $callback['id'],
                            'text'       => "订单已取消",
                            'show_alert' => false,
                        ]);
                        continue;
                    }

                    // 关闭订单
                    if ($action === 'close_order') {
                        $stmt = $mysqli->prepare("UPDATE orders SET status = 9, update_time = NOW() WHERE order_no = ?");
                        $stmt->bind_param("s", $order_no);
                        $stmt->execute();
                        $stmt->close();

                        tgRequest("deleteMessage", [
                            'chat_id'    => $chat_id,
                            'message_id' => $message_id,
                        ]);
                        tgRequest("sendMessage", [
                            'chat_id' => $chat_id,
                            'text'    => "✅ 订单已关闭",
                        ]);
                        tgRequest("answerCallbackQuery", [
                            'callback_query_id' => $callback['id'],
                            'text'       => "订单已关闭",
                            'show_alert' => false,
                        ]);
                        continue;
                    }

                    // 选择支付方式
                    if ($action === 'pay_alipay' || $action === 'pay_wxpay') {
                        $pay_type = ($action === 'pay_alipay') ? 'alipay' : 'wxpay';

                        tgRequest("editMessageText", [
                            'chat_id'    => $chat_id,
                            'message_id' => $message_id,
                            'text'       => "已选择：" . ($pay_type === 'alipay' ? '支付宝' : '微信') . "，正在生成二维码...",
                        ]);

                        // 更新订单 client_ip（记 from.id）
                        $client_ip = (string)($callback['from']['id'] ?? '');
                        $stmt = $mysqli->prepare("UPDATE orders SET client_ip = ? WHERE order_no = ?");
                        $stmt->bind_param("ss", $client_ip, $order_no);
                        $stmt->execute();
                        $stmt->close();

                        // 查询订单
                        $stmt = $mysqli->prepare("SELECT * FROM orders WHERE order_no = ? LIMIT 1");
                        $stmt->bind_param("s", $order_no);
                        $stmt->execute();
                        $res   = $stmt->get_result();
                        $order = $res->fetch_assoc();
                        $stmt->close();

                        if (!$order) {
                            tgRequest("answerCallbackQuery", [
                                'callback_query_id' => $callback['id'],
                                'text'       => "订单不存在！",
                                'show_alert' => true,
                            ]);
                            continue;
                        }

                        // 发起支付
                        $result = initiatePayment($order, $pay_type);
                        if (isset($result['code']) && (int)$result['code'] === 1) {
                            // 更新订单状态
                            $stmt = $mysqli->prepare("UPDATE orders SET pay_type = ?, status = 1, update_time = NOW() WHERE order_no = ?");
                            $stmt->bind_param("ss", $pay_type, $order_no);
                            $stmt->execute();
                            $stmt->close();

                            // 提取支付链接
                            $value = "";
                            if (!empty($result['payurl']))        $value = $result['payurl'];
                            elseif (!empty($result['qrcode']))    $value = $result['qrcode'];
                            elseif (!empty($result['urlscheme'])) $value = $result['urlscheme'];
                            else $value = "支付接口返回未知数据，请联系管理员。";

                            // 删除“正在生成二维码...”这条消息
                            tgRequest("deleteMessage", [
                                'chat_id'    => $chat_id,
                                'message_id' => $message_id,
                            ]);

                            // 构建按钮
                            $buttons = [[
                                ['text' => '✅ 前往支付', 'url' => $value],
                                ['text' => '❌ 关闭订单', 'callback_data' => "close_order:{$order_no}"],
                            ]];

                            $payAmount   = $order['amount'];
                            $captionText =
                                "💳 <b><u>支付信息</u></b>\n\n" .
                                "💰 <b>金额：</b><b><code>{$payAmount}</code></b> 元\n" .
                                "💱 <b>货币：</b><b><code>CNY</code></b>\n" .
                                "🆔 <b>订单号：</b><b><code>{$order_no}</code></b>\n" .
                                "⏰ <b>有效期：</b><b>15分钟</b>\n\n" .
                                "📌 <b>请扫描二维码或点击按钮完成支付：</b>\n";

                            if (filter_var($value, FILTER_VALIDATE_URL)) {
                                $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($value);
                                tgRequest("sendPhoto", [
                                    'chat_id'      => $chat_id,
                                    'photo'        => $qr_code_url,
                                    'caption'      => $captionText,
                                    'parse_mode'   => 'HTML',
                                    'reply_markup' => json_encode(['inline_keyboard' => $buttons], JSON_UNESCAPED_UNICODE),
                                ]);
                            } else {
                                tgRequest("sendMessage", [
                                    'chat_id'      => $chat_id,
                                    'text'         => $value,
                                    'reply_markup' => json_encode(['inline_keyboard' => $buttons], JSON_UNESCAPED_UNICODE),
                                ]);
                            }
                        } else {
                            $msg = $result['msg'] ?? '未知错误';
                            tgRequest("answerCallbackQuery", [
                                'callback_query_id' => $callback['id'],
                                'text'       => "支付请求失败：{$msg}",
                                'show_alert' => true,
                            ]);
                        }
                        continue;
                    }
                }

                // 其它无效回调
                tgRequest("answerCallbackQuery", [
                    'callback_query_id' => $callback['id'],
                    'text'       => "无效操作",
                    'show_alert' => false,
                ]);
                continue;
            }

            // 其它 update 类型：此处不做任何处理（不输出）
        }
    }
}
