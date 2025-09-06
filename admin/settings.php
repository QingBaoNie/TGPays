<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
  <meta charset="UTF-8">
  <title>系统设置（演示）</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { font-family: ui-sans-serif, system-ui, "Inter", "PingFang SC", "Microsoft YaHei"; background:#f9fafb; }
    .card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px; box-shadow:0 4px 10px rgba(0,0,0,0.04); }
    .field { width:100%; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px; font-size:14px; }
    .field:focus { outline:none; border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.2); }
    .label { font-size:14px; font-weight:500; color:#374151; margin-bottom:6px; display:block; }
    .btn { background:#4f46e5; color:#fff; font-weight:600; padding:10px 20px; border-radius:8px; }
    .btn:hover { background:#4338ca; }
  </style>
</head>
<body>
  <div class="max-w-5xl mx-auto p-6 space-y-6">

    <!-- 顶部提示 -->
    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg">
      ⚠️ 这是一个 <strong>演示页面</strong>，修改不会保存到数据库，仅用于展示界面效果。
    </div>

    <!-- 系统信息 -->
    <div class="card space-y-4">
      <h2 class="text-lg font-semibold text-slate-900">站点信息</h2>
      <div>
        <label class="label">站点名称</label>
        <input type="text" class="field" value="演示支付平台">
      </div>
      <div>
        <label class="label">站点域名</label>
        <input type="text" class="field" value="https://demo.example.com">
      </div>
      <div>
        <label class="label">管理员邮箱</label>
        <input type="email" class="field" value="admin@demo.com">
      </div>
    </div>

    <!-- 邮件通知 -->
    <div class="card space-y-4">
      <h2 class="text-lg font-semibold text-slate-900">邮件通知</h2>
      <div>
        <label class="label">SMTP 服务器</label>
        <input type="text" class="field" value="smtp.example.com">
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="label">邮箱账号</label>
          <input type="text" class="field" value="noreply@example.com">
        </div>
        <div>
          <label class="label">邮箱密码</label>
          <input type="password" class="field" value="password123">
        </div>
      </div>
      <div>
        <label class="label">收件人（多个用逗号隔开）</label>
        <input type="text" class="field" value="admin@demo.com, support@demo.com">
      </div>
    </div>

    <!-- 支付设置 -->
    <div class="card space-y-4">
      <h2 class="text-lg font-semibold text-slate-900">支付设置</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="label">支持货币</label>
          <select class="field">
            <option selected>CNY 人民币</option>
            <option>USD 美元</option>
            <option>EUR 欧元</option>
          </select>
        </div>
        <div>
          <label class="label">最大单笔金额</label>
          <input type="number" class="field" value="999">
        </div>
      </div>
      <div>
        <label class="label">是否开启测试模式</label>
        <select class="field">
          <option selected>否</option>
          <option>是</option>
        </select>
      </div>
    </div>

    <!-- 风格设置 -->
    <div class="card space-y-4">
      <h2 class="text-lg font-semibold text-slate-900">界面风格</h2>
      <div>
        <label class="label">主题颜色</label>
        <select class="field">
          <option selected>紫色（默认）</option>
          <option>蓝色</option>
          <option>绿色</option>
          <option>黑白简约</option>
        </select>
      </div>
      <div>
        <label class="label">LOGO 地址</label>
        <input type="text" class="field" value="https://demo.example.com/logo.png">
      </div>
      <div>
        <label class="label">自定义页脚文字</label>
        <input type="text" class="field" value="© 2025 DemoPay. All rights reserved.">
      </div>
    </div>

    <!-- 保存按钮 -->
    <div>
      <button type="button" class="btn">保存设置（演示用，不会保存）</button>
    </div>
  </div>
</body>
</html>
