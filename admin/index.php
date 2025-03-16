<?php
// admin/index.php
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
$res = $mysqli->query("SELECT * FROM orders ORDER BY create_time DESC LIMIT 50");
$orders = [];
while ($row = $res->fetch_assoc()) {
    $orders[] = $row;
}
$res = $mysqli->query("SELECT pay_type, COUNT(*) as count, SUM(amount) as total FROM orders WHERE status IN (1,2) GROUP BY pay_type");
$stats = [];
while ($row = $res->fetch_assoc()) {
    $stats[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>后台管理 - 支付记录</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/layui/2.7.6/css/layui.css">
</head>
<body class="layui-layout-body">
<div class="layui-layout layui-layout-admin">
  <!-- 头部 -->
  <div class="layui-header">
    <div class="layui-logo">TG支付机器人后台</div>
    <ul class="layui-nav layui-layout-right">
      <li class="layui-nav-item"><a href="easypay_config.php">易支付配置</a></li>
    </ul>
  </div>
  <!-- 侧边栏 -->
  <div class="layui-side layui-bg-black">
    <div class="layui-side-scroll">
      <ul class="layui-nav layui-nav-tree"  lay-filter="test">
        <li class="layui-nav-item layui-this"><a href="index.php">支付记录</a></li>
      </ul>
    </div>
  </div>
  <!-- 内容主体 -->
  <div class="layui-body">
    <div style="padding: 15px;">
      <h3>支付记录</h3>
      <table class="layui-table">
        <thead>
          <tr>
            <th>订单号</th>
            <th>TG用户ID</th>
            <th>金额</th>
            <th>支付方式</th>
            <th>状态 (0：待支付,1：已发起,2：支付成功)</th>
            <th>创建时间</th>
            <th>更新时间</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
          <tr>
            <td><?= htmlspecialchars($o['order_no']) ?></td>
            <td><?= htmlspecialchars($o['tg_id']) ?></td>
            <td><?= htmlspecialchars($o['amount']) ?></td>
            <td><?= htmlspecialchars($o['pay_type']) ?></td>
            <td><?= htmlspecialchars($o['status']) ?></td>
            <td><?= htmlspecialchars($o['create_time']) ?></td>
            <td><?= htmlspecialchars($o['update_time']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <h3>支付统计</h3>
      <table class="layui-table">
        <thead>
          <tr>
            <th>支付方式</th>
            <th>订单数量</th>
            <th>总金额</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($stats as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['pay_type']) ?></td>
            <td><?= htmlspecialchars($s['count']) ?></td>
            <td><?= htmlspecialchars($s['total']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <!-- 底部 -->
  <div class="layui-footer">
    © TGQing
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/layui/2.7.6/layui.js"></script>
</body>
</html>
