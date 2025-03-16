<?php
// bot.php
set_time_limit(0);
date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = include 'config.php';
$tg_token = $config['tg_token'];
$api_url = "https://api.telegram.org/bot{$tg_token}/";

// 连接 MySQL
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

/**
 * 向Telegram发送请求的辅助函数
 */
function tgRequest($method, $params = []) {
    global $api_url;
    $url = $api_url . $method;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

/**
 * 生成唯一订单号
 */
function generateOrderNo() {
    return date("YmdHis") . rand(1000, 9999);
}

/**
 * 易支付签名算法
 * 1. 移除 sign, sign_type 和空值参数
 * 2. 按参数名ASCII从小到大排序
 * 3. 拼接为 URL 键值对格式（a=b&c=d&e=f），注意不要进行 URL 编码
 * 4. 在拼接好的字符串末尾直接追加商户密钥 KEY，进行 MD5 加密，结果转为小写
 */
function generateSign($params, $key) {
    unset($params['sign'], $params['sign_type']);
    $filtered = array_filter($params, function($v) {
        return $v !== "";
    });
    ksort($filtered);
    $pairs = [];
    foreach ($filtered as $k => $v) {
        $pairs[] = "$k=$v";
    }
    $query = implode("&", $pairs);
    return strtolower(md5($query . $key));
}

/**
 * 发起易支付请求
 */
function initiatePayment($order, $pay_type) {
    global $mysqli;
    
 
    $res = $mysqli->query("SELECT * FROM easypay_config LIMIT 1");
    $config_pay = $res->fetch_assoc();
    if (!$config_pay) {
        return ['code' => 0, 'msg' => '未配置易支付信息'];
    }
    

    if ($pay_type == 'wxpay') {
        $device = 'wechat';
    } elseif ($pay_type == 'alipay') {
        $device = 'alipay';
    } else {
        $device = 'pc';
    }
    
    // 组装请求参数
    $params = [
        'pid'          => $config_pay['pid'],
        'type'         => $pay_type,
        'out_trade_no' => $order['order_no'],
        'notify_url'   => $config_pay['notify_url'],
        'return_url'   => $config_pay['return_url'],
        'name'         => "TG", 
        'money'        => number_format($order['amount'], 2, '.', ''),
        'clientip'     => $order['client_ip'],
        'device'       => $device,
        'param'        => '',
        'sign_type'    => 'MD5'
    ];
    
    // 生成签名
    $sign = generateSign($params, $config_pay['key']);
    $params['sign'] = $sign;
    
    // 发起 POST 请求
    $ch = curl_init($config_pay['gateway']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
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
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function generateJWT($payload, $secret) {
    $header = json_encode(["alg" => "HS256", "typ" => "JWT"]);
    $payload = json_encode($payload);
    $base64UrlHeader = base64UrlEncode($header);
    $base64UrlPayload = base64UrlEncode($payload);
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = base64UrlEncode($signature);
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

/**
 * 生成一次性登录链接（有效60秒）
 */
function generateLoginLink($tg_id) {
    global $config;
    $payload = [
        "action"  => "login",
        "bot_id"  => (string)$config['owner_id'],
        "exp"     => time() + 60,
        "user_id" => (string)$tg_id
    ];
    $jwt = generateJWT($payload, $config['tg_token']);
    return $config['domain'] . "/admin/login.php?short_token=" . $jwt;
}
// --------------------------

$offset = 0;
while (true) {
    $resp = tgRequest("getUpdates", ['offset' => $offset, 'timeout' => 20]);
    if ($resp && isset($resp['result'])) {
        foreach ($resp['result'] as $update) {
            $offset = $update['update_id'] + 1;
            
            
            if (isset($update['message'])) {
                $message = $update['message'];
                $chat_id = $message['chat']['id'];
                $text    = trim($message['text'] ?? '');
                
                if (strpos($text, '/start') === 0) {
                    tgRequest("sendMessage", [
                        'chat_id' => $chat_id,
                        'text'    => "欢迎使用TG支付机器人！\n发送 /pay 金额 例如：/pay 2\n发送 /login 获取后台登录链接。"
                    ]);
                }
                elseif (strpos($text, '/pay') === 0) {
                    $parts = explode(" ", $text);
                    if (count($parts) >= 2 && is_numeric($parts[1])) {
                        $amount   = floatval($parts[1]);
                        $order_no = generateOrderNo();
                        $client_ip = $message['from']['id'];  
                        
                      
                        $stmt = $mysqli->prepare("INSERT INTO orders (order_no, tg_id, amount, status, create_time) VALUES (?, ?, ?, 0, NOW())");
                        $stmt->bind_param("sid", $order_no, $chat_id, $amount);
                        $stmt->execute();
                        $stmt->close();
                        
                      
                        $keyboard = [
                            'inline_keyboard' => [
                                [
                                    ['text' => '支付宝', 'callback_data' => "pay_alipay:{$order_no}"],
                                    ['text' => '微信',   'callback_data' => "pay_wxpay:{$order_no}"]
                                ]
                            ]
                        ];
                        tgRequest("sendMessage", [
                            'chat_id'      => $chat_id,
                            'text'         => "支付金额：{$amount} 元，请选择支付方式：",
                            'reply_markup' => json_encode($keyboard)
                        ]);
                    } else {
                        tgRequest("sendMessage", [
                            'chat_id' => $chat_id,
                            'text'    => "格式错误，请使用：/pay 金额"
                        ]);
                    }
                }
                elseif (strpos($text, '/login') === 0) {
                    // 只有主人ID才能获取后台登录链接
                    if ($chat_id != $config['owner_id']) {
                        tgRequest("sendMessage", [
                            'chat_id' => $chat_id,
                            'text'    => "无权限登录后台！"
                        ]);
                    } else {
                        $link = generateLoginLink($chat_id);
                        tgRequest("sendMessage", [
                            'chat_id' => $chat_id,
                            'text'    => "请点击以下链接登录后台（60秒内有效）：\n{$link}"
                        ]);
                    }
                }
            }
            // 处理内联按钮回调
            elseif (isset($update['callback_query'])) {
                $callback = $update['callback_query'];
                $chat_id  = $callback['message']['chat']['id'];
                $message_id = $callback['message']['message_id'];
                $data     = $callback['data'];

               
                list($action, $order_no) = explode(":", $data);

               
                if ($action === 'pay_alipay' || $action === 'pay_wxpay') {
                    $pay_type = ($action == 'pay_alipay') ? 'alipay' : 'wxpay';

                   
                    tgRequest("editMessageText", [
                        'chat_id'    => $chat_id,
                        'message_id' => $message_id,
                        'text'       => "已选择：" . ($pay_type === 'alipay' ? '支付宝' : '微信') . "，正在生成二维码..."
                    ]);

                    //  发起支付前，更新订单记录
                    $client_ip = $callback['from']['id'];
                    $mysqli->query("UPDATE orders SET client_ip = '{$client_ip}' WHERE order_no = '{$order_no}'");
                    
                    //  查询订单信息
                    $res   = $mysqli->query("SELECT * FROM orders WHERE order_no = '{$order_no}' LIMIT 1");
                    $order = $res->fetch_assoc();
                    if (!$order) {
                        tgRequest("answerCallbackQuery", [
                            'callback_query_id' => $callback['id'],
                            'text'              => "订单不存在！",
                            'show_alert'        => true,
                        ]);
                        return;
                    }

                    //  发起易支付请求
                    $result = initiatePayment($order, $pay_type);
                    if ($result['code'] == 1) {
                        // 更新订单：记录支付方式，状态设置为已发起（1）
                        $stmt = $mysqli->prepare("UPDATE orders SET pay_type = ?, status = 1, update_time = NOW() WHERE order_no = ?");
                        $stmt->bind_param("ss", $pay_type, $order_no);
                        $stmt->execute();
                        $stmt->close();

                      
                        $value = "";
                        if (!empty($result['payurl'])) {
                            $value = $result['payurl'];
                        } elseif (!empty($result['qrcode'])) {
                            $value = $result['qrcode'];
                        } elseif (!empty($result['urlscheme'])) {
                            $value = $result['urlscheme'];
                        } else {
                            $value = "支付接口返回未知数据，请联系管理员。";
                        }

                       
                        tgRequest("deleteMessage", [
                            'chat_id'    => $chat_id,
                            'message_id' => $message_id
                        ]);

                       
                        $payAmount = $order['amount']; 
                        $captionText = "支付金额：{$payAmount} 元\n"
                                    . "订单有效期：15分钟\n\n"
                                    . "请扫描二维码进行支付：";

                        
                        if (filter_var($value, FILTER_VALIDATE_URL)) {
                            $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($value);

                            // 通过 sendPhoto 发送二维码
                            tgRequest("sendPhoto", [
                                'chat_id'      => $chat_id,
                                'photo'        => $qr_code_url,
                                'caption'      => $captionText . "\n{$value}"
                            ]);
                        } else {
                            // 如果不是 URL，直接发文本消息
                            tgRequest("sendMessage", [
                                'chat_id' => $chat_id,
                                'text'    => $value
                            ]);
                        }
                    } else {
                        // 支付请求失败
                        tgRequest("answerCallbackQuery", [
                            'callback_query_id' => $callback['id'],
                            'text'              => "支付请求失败：" . $result['msg'],
                            'show_alert'        => true,
                        ]);
                    }
                }
            }
        }
    }
}
