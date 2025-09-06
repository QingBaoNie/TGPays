<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// 收入统计
$total_income = $today_income = $today_ali = $today_wechat = 0;

$res = $mysqli->query("SELECT SUM(amount) as total FROM orders WHERE status = 2");
if ($row = $res->fetch_assoc()) $total_income = $row['total'] ?? 0;

$res = $mysqli->query("SELECT SUM(amount) as total FROM orders WHERE status = 2 AND DATE(create_time) = CURDATE()");
if ($row = $res->fetch_assoc()) $today_income = $row['total'] ?? 0;

$res = $mysqli->query("SELECT SUM(amount) as total FROM orders WHERE status = 2 AND pay_type='alipay' AND DATE(create_time) = CURDATE()");
if ($row = $res->fetch_assoc()) $today_ali = $row['total'] ?? 0;

$res = $mysqli->query("SELECT SUM(amount) as total FROM orders WHERE status = 2 AND pay_type='wechat' AND DATE(create_time) = CURDATE()");
if ($row = $res->fetch_assoc()) $today_wechat = $row['total'] ?? 0;

// 默认城市
$city = '北京';

// ip 定位
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://ipapi.co/json/",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
]);
$ip_info = curl_exec($curl);
curl_close($curl);
$ip_data = json_decode($ip_info, true);

if (isset($ip_data['country_code']) && $ip_data['country_code'] === 'CN' &&
    isset($ip_data['city']) && !empty($ip_data['city'])) {
    $city = trim($ip_data['city']);
}

// 天气 API
$weather = null;
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://v2.xxapi.cn/api/weather?city=" . urlencode($city),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
]);
$response = curl_exec($curl);
curl_close($curl);
$data = json_decode($response, true);
if (
    isset($data['code']) && $data['code'] === 200 &&
    isset($data['data']['data'][0])
) {
    $weather = $data['data']['data'][0];
}

// 近7日收入
$dates = [];
$incomes = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} day"));
    $dates[] = date('m-d', strtotime($day));
    $res = $mysqli->query("SELECT SUM(amount) as total FROM orders WHERE status = 2 AND DATE(create_time) = '{$day}'");
    $row = $res->fetch_assoc();
    $incomes[] = floatval($row['total'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
  <meta charset="utf-8">
  <title>仪表盘</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
</head>
<body class="bg-gray-100 font-sans">
  <div class="max-w-7xl mx-auto px-6 py-6">
    <!-- 欢迎卡片 -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
      <h2 class="text-xl font-semibold text-gray-800">欢迎回来，Admin 👋</h2>
      <p class="mt-2 text-gray-600">
        今日天气：
        <?php if ($weather): ?>
          <span class="font-medium text-indigo-600"><?= htmlspecialchars($city) ?>：</span>
          <?= $weather['date'] ?>，
          <?= $weather['weather'] ?>，
          温度 <?= $weather['temperature'] ?>，
          风力 <?= $weather['wind'] ?>，
          空气质量 <?= $weather['air_quality'] ?>
        <?php else: ?>
          <span class="text-gray-400">天气获取失败</span>
        <?php endif; ?>
      </p>
    </div>

    <!-- 四个统计卡片 -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
      <div class="bg-white rounded-xl shadow p-5">
        <div class="text-sm text-gray-500">总收入</div>
        <div class="mt-2 text-2xl font-bold text-gray-900">¥ <?= number_format($total_income, 2) ?></div>
      </div>
      <div class="bg-white rounded-xl shadow p-5">
        <div class="text-sm text-gray-500">今日收入</div>
        <div class="mt-2 text-2xl font-bold text-gray-900">¥ <?= number_format($today_income, 2) ?></div>
      </div>
      <div class="bg-white rounded-xl shadow p-5">
        <div class="text-sm text-gray-500">今日支付宝</div>
        <div class="mt-2 text-2xl font-bold text-gray-900">¥ <?= number_format($today_ali, 2) ?></div>
      </div>
      <div class="bg-white rounded-xl shadow p-5">
        <div class="text-sm text-gray-500">今日微信</div>
        <div class="mt-2 text-2xl font-bold text-gray-900">¥ <?= number_format($today_wechat, 2) ?></div>
      </div>
    </div>

    <!-- 收入趋势图 -->
    <div class="bg-white rounded-xl shadow p-6">
      <div id="incomeChart" style="height:360px;"></div>
    </div>
  </div>

  <script>
    const chartDom = document.getElementById('incomeChart');
    const myChart = echarts.init(chartDom);
    const option = {
      title: {
        text: '近 7 日收入走势',
        left: 'center',
        textStyle: { fontSize: 16, color: '#374151' }
      },
      tooltip: { trigger: 'axis' },
      grid: { left: '4%', right: '4%', bottom: '5%', containLabel: true },
      xAxis: {
        type: 'category',
        boundaryGap: false,
        data: <?= json_encode($dates) ?>,
        axisLine: { lineStyle: { color: '#e5e7eb' } },
        axisLabel: { color: '#6b7280' }
      },
      yAxis: {
        type: 'value',
        name: '收入 (¥)',
        axisLine: { lineStyle: { color: '#e5e7eb' } },
        splitLine: { lineStyle: { color: '#f3f4f6' } },
        axisLabel: { color: '#6b7280' }
      },
      series: [{
        data: <?= json_encode($incomes) ?>,
        type: 'line',
        smooth: true,
        symbol: 'circle',
        symbolSize: 6,
        lineStyle: { color: '#2563eb', width: 3 },
        itemStyle: { color: '#2563eb', borderWidth: 2 },
        areaStyle: { color: '#93c5fd', opacity: 0.3 }
      }]
    };
    myChart.setOption(option);
    window.addEventListener('resize', () => myChart.resize());
  </script>
</body>
</html>
