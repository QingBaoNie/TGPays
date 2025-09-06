<?php
// return.php — Simple & Animated
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

// 接参与签名
$params = $_GET;
$received_sign = $params['sign'] ?? '';
unset($params['sign'], $params['sign_type']);

// 过滤空值、排序、a=b&c=d 拼接（不做 urlencode）
$filtered = array_filter($params, fn($v) => $v !== "");
ksort($filtered);
$pairs = [];
foreach ($filtered as $k => $v) { $pairs[] = "{$k}={$v}"; }
$query = implode("&", $pairs);

// 取密钥
$res = $mysqli->query("SELECT * FROM easypay_config LIMIT 1");
$config_pay = $res ? $res->fetch_assoc() : null;
if (!$config_pay) { die("未配置易支付信息"); }

$calculated_sign = strtolower(md5($query . $config_pay['key']));
$sign_ok = hash_equals($calculated_sign, (string)$received_sign);

$trade_status = $params['trade_status'] ?? '';
$out_trade_no = $params['out_trade_no'] ?? '';
$is_success   = $sign_ok && $trade_status === 'TRADE_SUCCESS';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>支付结果</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<style>
  :root{
    --bg:#f6f7fb;
    --card:#ffffff;
    --ink:#0f172a;
    --muted:#6b7280;
    --line:#e5e7eb;
    --ok:#16a34a;      --ok-bg:#ecfdf5;   --ok-bd:#bbf7d0;
    --err:#ef4444;     --err-bg:#fef2f2;  --err-bd:#fecaca;
    --brand:#3b82f6;   --brand-dark:#1d4ed8;
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0;
    background:
      radial-gradient(900px 300px at 10% -10%, #e6f0ff 0, transparent 60%),
      radial-gradient(600px 240px at 120% 0%, #e8fff6 0, transparent 60%),
      var(--bg);
    font-family: ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC","Microsoft YaHei", Arial;
    color:var(--ink);
    display:flex; align-items:center; justify-content:center;
    padding:20px;
  }

  .card{
    width:100%; max-width:460px;
    background:var(--card);
    border:1px solid var(--line);
    border-radius:16px;
    box-shadow:0 16px 44px rgba(15,23,42,.08);
    padding:28px 24px;
    text-align:center;
    opacity:0; transform: translateY(8px) scale(.98);
    animation: popIn .5s ease-out forwards;
  }
  @keyframes popIn{
    to{ opacity:1; transform: translateY(0) scale(1); }
  }

  .icon-wrap{
    width:84px; height:84px; border-radius:50%;
    display:grid; place-items:center; margin:0 auto 14px;
    border:1px solid var(--line);
    background:#fff;
  }
  .icon-wrap.ok{ background: var(--ok-bg); border-color: var(--ok-bd); }
  .icon-wrap.err{ background: var(--err-bg); border-color: var(--err-bd); }

  /* SVG 描边动画 */
  .draw{
    stroke-dasharray: 100;
    stroke-dashoffset: 100;
    animation: draw 700ms ease-out forwards 120ms;
  }
  @keyframes draw{
    to{ stroke-dashoffset: 0; }
  }

  h1{ font-size:20px; margin:6px 0 6px; font-weight:700; }
  p.sub{ margin:0 0 14px; font-size:14px; color:var(--muted); }

  .info{
    margin-top:12px;
    border:1px solid var(--line);
    border-radius:12px;
    padding:14px 12px;
    text-align:left;
  }
  .row{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:6px 0; }
  .label{ width:86px; font-size:12px; color:#6b7280; letter-spacing:.04em; text-transform:uppercase; }
  .value{ font-size:14px; color:#111827; }

  .chip{
    display:inline-flex; align-items:center; gap:8px;
    padding:6px 10px; border-radius:10px;
    background:#f8fafc; border:1px solid var(--line);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Courier New", monospace;
  }
  .badge{
    font-size:12px; padding:4px 8px; border-radius:999px; border:1px solid rgba(0,0,0,.06);
  }
  .badge.ok{ background:var(--ok-bg); color:#065f46; border-color:var(--ok-bd); }
  .badge.err{ background:var(--err-bg); color:#7f1d1d; border-color:var(--err-bd); }

  .copy{
    border:1px solid var(--line);
    background:#fff; color:#1f2937;
    border-radius:10px; padding:6px 10px; font-size:12px; cursor:pointer;
    transition:.18s ease background, .18s ease transform, .18s ease box-shadow;
  }
  .copy:hover{ background:#f3f4f6; transform: translateY(-1px); box-shadow: 0 6px 18px rgba(2,6,23,.06); }

  .actions{ display:flex; gap:10px; justify-content:center; margin-top:16px; flex-wrap:wrap; }
  .btn{
    display:inline-flex; align-items:center; gap:8px; text-decoration:none;
    padding:10px 14px; border-radius:12px; font-size:14px; border:1px solid var(--line); color:#111827; background:#fff;
    transition:.18s ease transform, .18s ease box-shadow, .18s ease background;
  }
  .btn:hover{ background:#f8fafc; transform: translateY(-1px); box-shadow: 0 8px 22px rgba(2,6,23,.08); }
  .btn.primary{ background:var(--brand); color:#fff; border-color:var(--brand); }
  .btn.primary:hover{ background:var(--brand-dark); }

  .foot{ margin-top:10px; font-size:12px; color:#9aa3b2; }
</style>
</head>
<body>
  <main class="card" role="main" aria-labelledby="title">
    <div class="icon-wrap <?= $is_success ? 'ok' : 'err' ?>">
      <?php if ($is_success): ?>
        <!-- 勾：圆 + 对勾（描边动画） -->
        <svg width="38" height="38" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <circle cx="12" cy="12" r="9" stroke="<?= htmlspecialchars('#16a34a') ?>" stroke-width="2" class="draw"></circle>
          <path d="M7 12.5l3 3 7-7" stroke="<?= htmlspecialchars('#16a34a') ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="draw"></path>
        </svg>
      <?php else: ?>
        <!-- 叉：圆 + 叉（描边动画） -->
        <svg width="38" height="38" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <circle cx="12" cy="12" r="9" stroke="<?= htmlspecialchars('#ef4444') ?>" stroke-width="2" class="draw"></circle>
          <path d="M8 8l8 8M16 8l-8 8" stroke="<?= htmlspecialchars('#ef4444') ?>" stroke-width="2" stroke-linecap="round" class="draw"></path>
        </svg>
      <?php endif; ?>
    </div>

    <h1 id="title"><?= $is_success ? '支付成功' : '支付未完成' ?></h1>
    <p class="sub">
      <?= $is_success ? '交易已确认，我们已收到款项。' : '可能由于签名失败或支付未完成，请稍后重试或联系管理员。' ?>
    </p>

    <div class="info" aria-label="订单信息">
      <div class="row">
        <div class="label">订单号</div>
        <div class="value">
          <span id="orderNo" class="chip"><?= htmlspecialchars($out_trade_no !== '' ? $out_trade_no : '未知') ?></span>
          <button class="copy" type="button" onclick="copyOrder()" aria-label="复制订单号">复制</button>
        </div>
      </div>
      <div class="row">
        <div class="label">支付状态</div>
        <div class="value">
          <span class="badge <?= $is_success ? 'ok' : 'err' ?>"><?= htmlspecialchars($trade_status ?: '未知') ?></span>
        </div>
      </div>
      <div class="row">
        <div class="label">签名校验</div>
        <div class="value">
          <span class="badge <?= $sign_ok ? 'ok' : 'err' ?>"><?= $sign_ok ? '通过' : '未通过' ?></span>
        </div>
      </div>
    </div>


    <div class="foot">结果仅供参考，最终以异步通知为准 · © <?= date('Y') ?> </div>
  </main>

<script>
  function copyOrder(){
    const el = document.getElementById('orderNo');
    const text = el?.innerText || '';
    if (!text || text === '未知') return;
    const flash = () => {
      el.style.transition='box-shadow .2s ease, transform .2s ease';
      el.style.boxShadow='0 0 0 6px rgba(59,130,246,.18)';
      el.style.transform='translateY(-1px)';
      setTimeout(()=>{ el.style.boxShadow='none'; el.style.transform='none'; }, 260);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(flash, flash);
    } else {
      const ta = document.createElement('textarea');
      ta.value = text; ta.style.position='fixed'; ta.style.left='-9999px';
      document.body.appendChild(ta); ta.select();
      try{ document.execCommand('copy'); }catch(e){}
      document.body.removeChild(ta);
      flash();
    }
  }
</script>
</body>
</html>
