<?php
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

$show_success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pid        = trim($_POST['pid']);
    $key        = trim($_POST['key']);
    $gateway    = trim($_POST['gateway']);
    $notify_url = trim($_POST['notify_url']);
    $return_url = trim($_POST['return_url']);

    $res = $mysqli->query("SELECT id FROM easypay_config LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $stmt = $mysqli->prepare("UPDATE easypay_config SET pid=?, `key`=?, gateway=?, notify_url=?, return_url=? WHERE id=1");
    } else {
        $stmt = $mysqli->prepare("INSERT INTO easypay_config (id, pid, `key`, gateway, notify_url, return_url) VALUES (1,?,?,?,?,?)");
    }
    // pid 为 int
    $pid_int = (int)$pid;
    $stmt->bind_param("issss", $pid_int, $key, $gateway, $notify_url, $return_url);
    $stmt->execute();
    $stmt->close();
    $show_success = true;
}

$res = $mysqli->query("SELECT * FROM easypay_config LIMIT 1");
$config_pay = $res ? $res->fetch_assoc() : null;
?>
<!DOCTYPE html>
<html lang="zh">
<head>
  <meta charset="utf-8">
  <title>易支付配置</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
  <style>
    body { font-family: ui-sans-serif, system-ui, "Inter", "PingFang SC", "Microsoft YaHei", Arial; background:#f1f5f9; }
    .field { display:block; width:100%; border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; font-size:14px; color:#0f172a; background:#fff; }
    .field:focus { outline:none; border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.15); }
    .label { display:flex; align-items:center; gap:8px; font-size:12px; letter-spacing:.02em; text-transform:uppercase; color:#64748b; margin-bottom:6px; }
    .card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow: 0 6px 14px rgba(15,23,42,0.03); }
    .btn-primary { background:#4f46e5; color:#fff; border:1px solid #4f46e5; padding:10px 16px; font-weight:600; border-radius:10px; }
    .btn-primary:hover { background:#4338ca; border-color:#4338ca; }
    .hint { font-size:12px; color:#64748b; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
  </style>
</head>
<body>
  <div class="max-w-6xl mx-auto px-5 py-8">
    <!-- 页头 -->
    <div class="mb-6">
      <h1 class="text-2xl font-semibold text-slate-900">易支付接口配置</h1>
      <p class="text-sm text-slate-500 mt-1">配置商户与网关信息，供机器人下单时调用。</p>
    </div>

    <!-- 主体两列 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- 左侧：表单（占2列） -->
      <div class="lg:col-span-2 card p-6">
        <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <!-- pid -->
          <div>
            <label class="label">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"><path d="M12 3l8 4v5c0 5-3.5 9-8 9s-8-4-8-9V7l8-4Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
              商户ID (pid)
            </label>
            <input class="field" type="text" name="pid" inputmode="numeric" pattern="\d*" required
                   placeholder="例如：1001"
                   value="<?= htmlspecialchars($config_pay['pid'] ?? '') ?>">
            <p class="hint mt-1">通常为数字 ID，由易支付平台分配。</p>
          </div>

          <!-- key（带显示/隐藏） -->
          <div>
            <label class="label">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"><path d="M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" stroke="currentColor" stroke-width="1.5"/><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.5" opacity=".5"/></svg>
              商户密钥 (key)
            </label>
            <div class="relative">
              <input class="field pr-11" type="password" id="keyField" name="key" required
                     placeholder="请粘贴密钥"
                     value="<?= htmlspecialchars($config_pay['key'] ?? '') ?>">
              <button type="button" id="toggleKey"
                      class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700"
                      aria-label="显示或隐藏密钥">
                <svg id="eyeOn" class="w-5 h-5" viewBox="0 0 24 24" fill="none"><path d="M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" stroke="currentColor" stroke-width="1.7"/><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.7" opacity=".55"/></svg>
                <svg id="eyeOff" class="w-5 h-5 hidden" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18M9.9 9.9A5 5 0 0 0 12 17a5 5 0 0 0 4.9-6.1M6.7 6.7C4 8 2.2 12 2.2 12S5.7 19 12 19c1.9 0 3.6-.6 5-1.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
              </button>
            </div>
            <p class="hint mt-1">请妥善保管密钥，不要泄露给他人。</p>
          </div>

          <!-- gateway -->
          <div class="md:col-span-2">
            <label class="label">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="1.5"/><path d="M3 9h18M7 14h6" stroke="currentColor" stroke-width="1.5"/></svg>
              网关地址
            </label>
            <input class="field" type="text" name="gateway" required
                   placeholder="例如：https://xxx.com/mapi.php"
                   value="<?= htmlspecialchars($config_pay['gateway'] ?? 'https://x.com/mapi.php') ?>">
            <p class="hint mt-1">通常是易支付平台提供的接口地址，以 <span class="mono">mapi.php</span> 结尾。</p>
          </div>

          <!-- notify_url -->
          <div class="md:col-span-2">
            <label class="label">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"><path d="M12 3v18M5 8h14M5 16h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              异步通知地址 (notify_url)
            </label>
            <input class="field" type="text" name="notify_url" required
                   placeholder="支付成功后，服务端回调地址"
                   value="<?= htmlspecialchars($config_pay['notify_url'] ?? ($config['domain'] . '/notify.php')) ?>">
            <p class="hint mt-1">服务器接收支付状态的地址，务必确保公网可访问。</p>
          </div>

          <!-- return_url -->
          <div class="md:col-span-2">
            <label class="label">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"><path d="M8 12h8M12 8v8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              跳转通知地址 (return_url)
            </label>
            <input class="field" type="text" name="return_url"
                   placeholder="用户支付后跳转的页面"
                   value="<?= htmlspecialchars($config_pay['return_url'] ?? ($config['domain'] . '/return.php')) ?>">
            <p class="hint mt-1">可为空；若填写，用户支付完成会跳转至该页面。</p>
          </div>

          <!-- 提交 -->
          <div class="md:col-span-2">
            <button class="btn-primary w-full md:w-auto" type="submit">保存配置</button>
          </div>
        </form>
      </div>

      <!-- 右侧：说明卡片 -->
      <aside class="card p-6">
        <h3 class="text-base font-semibold text-slate-900">使用说明</h3>
        <ul class="list-disc pl-5 mt-3 text-sm text-slate-600 space-y-2">
          <li><span class="font-medium">商户ID/密钥</span> 与你的易支付平台账户一致。</li>
          <li><span class="font-medium">网关地址</span> 通常以 <span class="mono">/mapi.php</span> 结尾。</li>
          <li><span class="font-medium">异步通知</span> 用于平台回调支付结果；确保可公网访问。</li>
          <li><span class="font-medium">跳转通知</span> 为可选项，用于用户端支付完成后的跳转。</li>
        </ul>
        <div class="mt-4 p-3 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-800 text-sm">
          提示：修改后立即生效。建议在机器人中创建一笔测试订单以验证配置。
        </div>
      </aside>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
  <?php if ($show_success): ?>
  <script>
    Toastify({
      text: "✅ 配置保存成功",
      duration: 3000,
      gravity: "top",
      position: "right",
      backgroundColor: "#22c55e",
      close: true
    }).showToast();
  </script>
  <?php endif; ?>

  <script>
    // 密钥显示/隐藏
    const keyField = document.getElementById('keyField');
    const toggle = document.getElementById('toggleKey');
    const eyeOn = document.getElementById('eyeOn');
    const eyeOff = document.getElementById('eyeOff');
    toggle?.addEventListener('click', () => {
      const show = keyField.type === 'password';
      keyField.type = show ? 'text' : 'password';
      eyeOn.classList.toggle('hidden', show);
      eyeOff.classList.toggle('hidden', !show);
    });
  </script>
</body>
</html>
