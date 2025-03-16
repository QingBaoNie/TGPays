<?php
// config_install.php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mysql_host = trim($_POST['mysql_host']);
    $mysql_port = trim($_POST['mysql_port']);
    $mysql_user = trim($_POST['mysql_user']);
    $mysql_password = trim($_POST['mysql_password']);
    $mysql_database = trim($_POST['mysql_database']);

    $redis_host = trim($_POST['redis_host']);
    $redis_port = trim($_POST['redis_port']);
    $redis_password = trim($_POST['redis_password']);

    $tg_token = trim($_POST['tg_token']);
    $owner_id = trim($_POST['owner_id']);
    $domain   = trim($_POST['domain']);

    // 检查必填项
    if (!$mysql_host || !$mysql_port || !$mysql_user || !$mysql_database || !$tg_token || !$owner_id || !$domain) {
        die("请填写所有必填项！");
    }

    $config = [
        'mysql' => [
            'host'     => $mysql_host,
            'port'     => $mysql_port,
            'user'     => $mysql_user,
            'password' => $mysql_password,
            'database' => $mysql_database,
        ],
        'redis' => [
            'host'     => $redis_host,
            'port'     => $redis_port,
            'password' => $redis_password,
        ],
        'tg_token'  => $tg_token,
        'owner_id'  => $owner_id,
        'domain'    => $domain, // 必须包含 http(s):// 前缀的完整域名
    ];

    $config_content = "<?php\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents("config.php", $config_content)) {
        // 自动导入SQL数据库表
        $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_password, $mysql_database, $mysql_port);
        if ($mysqli->connect_errno) {
            die("配置写入成功，但连接MySQL失败：" . $mysqli->connect_error);
        }
        // SQL 语句：订单表
        $sql_orders = "CREATE TABLE IF NOT EXISTS `orders` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `order_no` varchar(50) NOT NULL,
          `tg_id` varchar(50) NOT NULL,
          `amount` decimal(10,2) NOT NULL,
          `pay_type` varchar(20) DEFAULT NULL,
          `status` tinyint(1) NOT NULL DEFAULT '0',  -- 0:待支付,1:已发起,2:支付成功
          `client_ip` varchar(50) DEFAULT NULL,
          `create_time` datetime NOT NULL,
          `update_time` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `order_no` (`order_no`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $mysqli->query($sql_orders);

       
        $sql_tokens = "CREATE TABLE IF NOT EXISTS `login_tokens` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `token` varchar(64) NOT NULL,
          `expire_time` int(11) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `token` (`token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $mysqli->query($sql_tokens);

        // 易支付配置表
        $sql_easypay = "CREATE TABLE IF NOT EXISTS `easypay_config` (
          `id` int(11) NOT NULL,
          `pid` int(11) NOT NULL,
          `key` varchar(100) NOT NULL,
          `gateway` varchar(255) NOT NULL,
          `notify_url` varchar(255) NOT NULL,
          `return_url` varchar(255) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        $mysqli->query($sql_easypay);
        $mysqli->close();

        echo "安装成功！请删除或保护 config_install.php 文件，然后启动机器人。";
    } else {
        echo "写入配置文件失败，请检查目录权限。";
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>安装配置</title>
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/4.6.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h3>安装配置</h3>
    <form method="post">
        <h5>MySQL配置</h5>
        <div class="form-group">
            <label>MySQL Host</label>
            <input type="text" name="mysql_host" class="form-control" value="127.0.0.1" required>
        </div>
        <div class="form-group">
            <label>MySQL Port</label>
            <input type="text" name="mysql_port" class="form-control" value="3306" required>
        </div>
        <div class="form-group">
            <label>MySQL 用户名</label>
            <input type="text" name="mysql_user" class="form-control" required>
        </div>
        <div class="form-group">
            <label>MySQL 密码</label>
            <input type="password" name="mysql_password" class="form-control">
        </div>
        <div class="form-group">
            <label>MySQL 数据库名</label>
            <input type="text" name="mysql_database" class="form-control" required>
        </div>

        <h5>Redis配置</h5>
        <div class="form-group">
            <label>Redis Host</label>
            <input type="text" name="redis_host" class="form-control" value="127.0.0.1" required>
        </div>
        <div class="form-group">
            <label>Redis Port</label>
            <input type="text" name="redis_port" class="form-control" value="6379" required>
        </div>
        <div class="form-group">
            <label>Redis 密码（如有）</label>
            <input type="text" name="redis_password" class="form-control">
        </div>

        <h5>TG机器人配置</h5>
        <div class="form-group">
            <label>机器人 Token</label>
            <input type="text" name="tg_token" class="form-control" required>
        </div>
        <div class="form-group">
            <label>主人TG ID</label>
            <input type="text" name="owner_id" class="form-control" required>
        </div>
        <div class="form-group">
            <label>完整域名（如：https://yourdomain.com）</label>
            <input type="text" name="domain" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary">立即安装并启动机器人</button>
    </form>
</div>
</body>
</html>
