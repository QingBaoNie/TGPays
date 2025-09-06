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

/* ---------------- 清理操作 ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'clear_unpaid') {
            $mysqli->query("DELETE FROM orders WHERE status = 0");
            header("Location: orders.php?status=cleared_unpaid");
            exit;
        } elseif ($_POST['action'] === 'clear_all') {
            $mysqli->query("DELETE FROM orders");
            header("Location: orders.php?status=cleared_all");
            exit;
        }
    }
}

/* ---------------- 分页参数 ---------------- */
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if ($per_page < 10)  $per_page = 10;
if ($per_page > 200) $per_page = 200;

$offset = ($page - 1) * $per_page;

/* ---------------- 统计总数 ---------------- */
$total = 0;
$res_count = $mysqli->query("SELECT COUNT(*) AS cnt FROM orders");
if ($res_count && ($row = $res_count->fetch_assoc())) {
    $total = (int)$row['cnt'];
}
$total_pages = max(1, (int)ceil($total / $per_page));

if ($page > $total_pages) {
    $page   = $total_pages;
    $offset = ($page - 1) * $per_page;
}

/* ---------------- 查询订单（分页）---------------- */
$orders = [];
$stmt = $mysqli->prepare("SELECT order_no, tg_id, amount, pay_type, status, create_time, update_time
                          FROM orders
                          ORDER BY create_time DESC
                          LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $per_page, $offset);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

/* ---------------- 帮助函数：保留查询串（去掉page） ---------------- */
function build_query_keep(array $except = ['page']) {
    $params = $_GET;
    foreach ($except as $ex) unset($params[$ex]);
    return http_build_query($params);
}
$base_qs = build_query_keep(); // e.g. "per_page=50&status=..."
$qs_prefix = $base_qs === '' ? '' : $base_qs . '&';

/* ---------------- 计算展示范围 ---------------- */
$from = $total === 0 ? 0 : ($offset + 1);
$to   = min($offset + $per_page, $total);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
  <meta charset="utf-8">
  <title>订单列表</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
  <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
  <style>
    body { font-family: ui-sans-serif, system-ui, "Inter", "PingFang SC", "Microsoft YaHei", Arial; }
    .badge { display:inline-flex; align-items:center; padding:2px 10px; border-radius:9999px; font-size:12px; font-weight:600; line-height:1.6; }
    .badge-green { color:#065f46; background:#d1fae5; border:1px solid #a7f3d0; }
    .badge-orange{ color:#7c2d12; background:#ffedd5; border:1px solid #fed7aa; }
    .badge-gray { color:#374151; background:#f3f4f6; border:1px solid #e5e7eb; }
    .badge-blue { color:#1e3a8a; background:#dbeafe; border:1px solid #bfdbfe; }
    .badge-violet{ color:#4c1d95; background:#ede9fe; border:1px solid #ddd6fe; }
    .table-wrap { overflow:auto; border-radius:12px; border:1px solid #e5e7eb; background:#fff; }
    table { width:100%; border-collapse:separate; border-spacing:0; }
    thead th { position:sticky; top:0; background:#f8fafc; z-index:1; }
    tbody tr:nth-child(odd){ background:#fff; }
    tbody tr:nth-child(even){ background:#fafafa; }
    tbody tr:hover { background:#f1f5f9; }
    th, td { padding:12px 14px; text-align:left; white-space:nowrap; }
    th { font-size:12px; letter-spacing:.04em; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e5e7eb; }
    td { font-size:14px; color:#111827; border-bottom:1px solid #f1f5f9; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    .btn { display:inline-flex; align-items:center; gap:8px; border-radius:10px; padding:8px 12px; font-size:14px; }
    .btn-amber { border:1px solid #fcd34d; background:#fffbeb; color:#92400e; }
    .btn-amber:hover{ background:#fef3c7; }
    .btn-rose { border:1px solid #fda4af; background:#fff1f2; color:#9f1239; }
    .btn-rose:hover{ background:#ffe4e6; }
    .pager a, .pager span {
      display:inline-flex; align-items:center; justify-content:center;
      min-width:36px; height:36px; padding:0 10px;
      border:1px solid #e5e7eb; background:#fff; color:#374151;
      border-radius:8px; font-size:14px;
    }
    .pager a:hover { background:#f8fafc; }
    .pager .active { background:#1d4ed8; color:#fff; border-color:#1d4ed8; }
    .pager .disabled { color:#94a3b8; background:#f8fafc; border-color:#e5e7eb; cursor:not-allowed; }
    .select { border:1px solid #e5e7eb; border-radius:8px; padding:6px 10px; background:#fff; color:#111827; }
  </style>
</head>
<body class="bg-slate-50">
  <div class="max-w-7xl mx-auto px-6 py-6">
    <!-- 标题与操作条 -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5 mb-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h3 class="text-xl font-semibold text-slate-900">订单列表</h3>
          <p class="text-sm text-slate-500 mt-1">
            第 <span class="font-semibold text-slate-800"><?= $from ?></span>–<span class="font-semibold text-slate-800"><?= $to ?></span> 条，
            共 <span class="font-semibold text-slate-800"><?= number_format($total) ?></span> 条记录
          </p>
        </div>
        <div class="flex items-center gap-3">
          <!-- 每页数量选择 -->
          <form method="get" class="hidden md:flex items-center gap-2">
            <?php
              // 保留除 per_page 外的其他 GET 参数（比如 status）
              foreach ($_GET as $k => $v) {
                if ($k === 'per_page' || $k === 'page') continue;
                echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">';
              }
            ?>
            <label for="per_page" class="text-sm text-slate-600">每页</label>
            <select class="select text-sm" name="per_page" id="per_page" onchange="this.form.submit()">
              <?php foreach ([10,20,30,50,100,200] as $n): ?>
                <option value="<?= $n ?>" <?= $per_page===$n?'selected':'' ?>><?= $n ?></option>
              <?php endforeach; ?>
            </select>
          </form>

          <!-- 操作按钮 -->
          <form method="post" onsubmit="return confirm('确定清除所有未支付订单？')">
            <input type="hidden" name="action" value="clear_unpaid">
            <button class="btn btn-amber">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
                <path d="M3 6h18M8 6V4h8v2M6 6l1 14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              清除未支付
            </button>
          </form>
          <form method="post" onsubmit="return confirm('⚠️ 确定要清空所有订单？此操作不可恢复')">
            <input type="hidden" name="action" value="clear_all">
            <button class="btn btn-rose">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
                <path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              清空所有订单
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- 表格 -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>订单号</th>
            <th>TG 用户 ID</th>
            <th>金额</th>
            <th>支付方式</th>
            <th>状态</th>
            <th>创建时间</th>
            <th>更新时间</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td class="mono text-slate-800"><?= htmlspecialchars($o['order_no']) ?></td>
              <td class="mono"><?= htmlspecialchars($o['tg_id']) ?></td>
              <td class="font-semibold">¥ <?= number_format((float)$o['amount'], 2) ?></td>
              <td>
                <?php
                  $pt = strtolower((string)$o['pay_type']);
                  if ($pt === 'alipay') {
                      echo '<span class="badge badge-blue">Alipay</span>';
                  } elseif ($pt === 'wechat' || $pt === 'wxpay') {
                      echo '<span class="badge badge-violet">WeChat</span>';
                  } else {
                      echo '<span class="badge badge-gray">—</span>';
                  }
                ?>
              </td>
              <td>
                <?php
                  switch ((int)$o['status']) {
                    case 0: echo '<span class="badge badge-gray">待支付</span>'; break;
                    case 1: echo '<span class="badge badge-orange">已发起</span>'; break;
                    case 2: echo '<span class="badge badge-green">支付成功</span>'; break;
                    default: echo '<span class="badge badge-gray">未知</span>';
                  }
                ?>
              </td>
              <td class="text-slate-700"><?= htmlspecialchars($o['create_time']) ?></td>
              <td class="text-slate-700"><?= htmlspecialchars($o['update_time']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($orders) === 0): ?>
            <tr>
              <td colspan="7" class="text-center text-slate-500 py-10">暂无数据</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- 分页器 -->
    <div class="mt-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div class="text-sm text-slate-600">
        每页
        <strong class="text-slate-900"><?= $per_page ?></strong>
        条，共 <strong class="text-slate-900"><?= number_format($total_pages) ?></strong> 页
      </div>

      <div class="pager flex items-center gap-2">
        <?php
          $first_link = "orders.php?{$qs_prefix}page=1&per_page={$per_page}";
          $prev_link  = "orders.php?{$qs_prefix}page=" . max(1, $page-1) . "&per_page={$per_page}";
          $next_link  = "orders.php?{$qs_prefix}page=" . min($total_pages, $page+1) . "&per_page={$per_page}";
          $last_link  = "orders.php?{$qs_prefix}page={$total_pages}&per_page={$per_page}";
        ?>
        <?php if ($page > 1): ?>
          <a href="<?= htmlspecialchars($first_link) ?>">首页</a>
          <a href="<?= htmlspecialchars($prev_link) ?>">上一页</a>
        <?php else: ?>
          <span class="disabled">首页</span>
          <span class="disabled">上一页</span>
        <?php endif; ?>

        <!-- 当前页附近页码（窗口大小 2） -->
        <?php
          $start = max(1, $page - 2);
          $end   = min($total_pages, $page + 2);
          for ($p = $start; $p <= $end; $p++):
            $link = "orders.php?{$qs_prefix}page={$p}&per_page={$per_page}";
        ?>
          <?php if ($p == $page): ?>
            <span class="active"><?= $p ?></span>
          <?php else: ?>
            <a href="<?= htmlspecialchars($link) ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
          <a href="<?= htmlspecialchars($next_link) ?>">下一页</a>
          <a href="<?= htmlspecialchars($last_link) ?>">末页</a>
        <?php else: ?>
          <span class="disabled">下一页</span>
          <span class="disabled">末页</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- 底部小提示 -->
    <p class="text-xs text-slate-400 mt-3">提示：就是没有提示</p>
  </div>

  <?php if (isset($_GET['status'])): ?>
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      var txt = '操作完成', bg = '#3b82f6';
      switch ('<?= addslashes($_GET['status']) ?>') {
        case 'cleared_unpaid': txt = '✅ 已清理所有未支付订单'; bg = '#22c55e'; break;
        case 'cleared_all': txt = '⚠️ 所有订单已被清空'; bg = '#f97316'; break;
      }
      Toastify({
        text: txt,
        duration: 3000,
        gravity: "top",
        position: "right",
        backgroundColor: bg,
        close: true
      }).showToast();
    });
  </script>
  <?php endif; ?>
</body>
</html>
