<?php
// admin/easypay_config.php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}
$config = include '../config.php';
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
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pid = trim($_POST['pid']);
    $key = trim($_POST['key']);
    $gateway = trim($_POST['gateway']);
    $notify_url = trim($_POST['notify_url']);
    $return_url = trim($_POST['return_url']);
    $res = $mysqli->query("SELECT id FROM easypay_config LIMIT 1");
    if ($res->num_rows > 0) {
        $stmt = $mysqli->prepare("UPDATE easypay_config SET pid=?, `key`=?, gateway=?, notify_url=?, return_url=? WHERE id=1");
        $stmt->bind_param("issss", $pid, $key, $gateway, $notify_url, $return_url);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $mysqli->prepare("INSERT INTO easypay_config (id, pid, `key`, gateway, notify_url, return_url) VALUES (1,?,?,?,?,?)");
        $stmt->bind_param("issss", $pid, $key, $gateway, $notify_url, $return_url);
        $stmt->execute();
        $stmt->close();
    }
    $message = "配置保存成功！";
}
$res = $mysqli->query("SELECT * FROM easypay_config LIMIT 1");
$config_pay = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>后台管理 - 易支付配置</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/layui/2.7.6/css/layui.css">
</head>
<body class="layui-layout-body">
<div class="layui-layout layui-layout-admin">
  <div class="layui-header">
    <div class="layui-logo">易支付配置</div>
    <ul class="layui-nav layui-layout-right">
      <li class="layui-nav-item"><a href="index.php">支付记录</a></li>
    </ul>
  </div>
  <div class="layui-side layui-bg-black">
    <div class="layui-side-scroll">
      <ul class="layui-nav layui-nav-tree" lay-filter="test">
        <li class="layui-nav-item"><a href="index.php">支付记录</a></li>
      </ul>
    </div>
  </div>
  <div class="layui-body">
    <div style="padding: 15px;">
      <?php if ($message): ?>
        <div class="layui-alert layui-bg-green"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      <form class="layui-form" method="post">
        <div class="layui-form-item">
          <label class="layui-form-label">商户ID (pid)</label>
          <div class="layui-input-block">
            <input type="text" name="pid" required value="<?= htmlspecialchars($config_pay['pid'] ?? '') ?>" class="layui-input">
          </div>
        </div>
        <div class="layui-form-item">
          <label class="layui-form-label">商户密钥 (key)</label>
          <div class="layui-input-block">
            <input type="text" name="key" required value="<?= htmlspecialchars($config_pay['key'] ?? '') ?>" class="layui-input">
          </div>
        </div>
        <div class="layui-form-item">
          <label class="layui-form-label">网关地址</label>
          <div class="layui-input-block">
            <input type="text" name="gateway" required value="<?= htmlspecialchars($config_pay['gateway'] ?? 'https://x.com/mapi.php') ?>" class="layui-input">
          </div>
        </div>
        <div class="layui-form-item">
          <label class="layui-form-label">异步通知地址</label>
          <div class="layui-input-block">
            <input type="text" name="notify_url" required value="<?= htmlspecialchars($config_pay['notify_url'] ?? ($config['domain'] . '/notify.php')) ?>" class="layui-input">
          </div>
        </div>
        <div class="layui-form-item">
          <label class="layui-form-label">跳转通知地址</label>
          <div class="layui-input-block">
            <input type="text" name="return_url" value="<?= htmlspecialchars($config_pay['return_url'] ?? ($config['domain'] . '/return.php')) ?>" class="layui-input">
          </div>
        </div>
        <div class="layui-form-item">
          <div class="layui-input-block">
            <button class="layui-btn" type="submit">保存配置</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <div class="layui-footer">© TGQing</div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/layui/2.7.6/layui.js"></script>
</body>
</html>
