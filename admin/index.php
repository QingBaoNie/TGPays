<!DOCTYPE html>
<html lang="zh" class="h-full">
<head>
  <meta charset="UTF-8" />
  <title>管理后台</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body{font-family: ui-sans-serif, system-ui, "Inter", "PingFang SC", "Microsoft YaHei", Arial;}
    iframe{width:100%; height:calc(100vh - 64px); border:none; background:#fafafa;}
    .nav-active{background:#f1f5f9; color:#111827; font-weight:600;}
    .nav-active .indicator{opacity:1;}
    .indicator{opacity:0; transition:opacity .2s;}
  </style>
</head>
<body class="h-full bg-slate-50">

  <!-- 顶部栏 -->
  <header class="h-16 bg-white border-b border-slate-200 sticky top-0 z-30">
    <div class="h-full max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button id="sidebarToggle" class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-md border border-slate-300 text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="切换侧边栏">
          <!-- 菜单图标 -->
          <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </button>
        <div class="flex items-center gap-2">
          <span class="text-xl font-semibold tracking-tight text-slate-900">Admin Console</span>
          <span class="text-xs px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-100">v1.0</span>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <div class="hidden md:flex items-center gap-2 text-sm text-slate-600">
          <span>语言</span>
          <select class="border border-slate-300 rounded-md px-2 py-1 text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option>简体中文</option>
            <option>English</option>
          </select>
        </div>
        <div class="w-px h-6 bg-slate-200 hidden md:block"></div>
        <div class="flex items-center gap-3">
          <img src="https://i.pravatar.cc/40" class="w-9 h-9 rounded-full border border-slate-200" alt="用户头像">
          <div class="hidden md:block">
            <div class="text-sm font-medium text-slate-900">Admin</div>
            <div class="text-xs text-slate-500">Administrator</div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <div class="w-full">
    <div class="max-w-screen-2xl mx-auto">
      <div class="flex">
        <!-- 侧边栏 -->
        <aside id="sidebar"
               class="w-64 bg-white border-r border-slate-200 h-[calc(100vh-64px)] shrink-0 px-3 py-4 overflow-y-auto lg:translate-x-0 -translate-x-full lg:static fixed z-20 transition-transform duration-200 ease-out">
          <nav class="space-y-1 text-sm">
            <div class="px-2 pb-1 text-[11px] tracking-widest uppercase text-slate-400">总览</div>

            <a href="#"
               class="group relative flex items-center gap-3 rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 nav-active"
               data-page="dashboard.php" onclick="return loadPage(event,'dashboard.php')">
              <span class="absolute left-0 top-0 bottom-0 w-1 bg-indigo-600 rounded-r indicator"></span>
              <!-- 图标：仪表盘 -->
              <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5 text-slate-500 group-[.nav-active]:text-indigo-600">
                <path d="M12 3a9 9 0 0 0-9 9v6a3 3 0 0 0 3 3h3v-6H6v-3a6 6 0 0 1 12 0v3h-3v6h3a3 3 0 0 0 3-3v-6a9 9 0 0 0-9-9Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
              </svg>
              <span>仪表盘</span>
            </a>

            <a href="#"
               class="group relative flex items-center gap-3 rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
               data-page="orders.php" onclick="return loadPage(event,'orders.php')">
              <span class="absolute left-0 top-0 bottom-0 w-1 bg-indigo-600 rounded-r indicator"></span>
              <!-- 图标：列表 -->
              <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5 text-slate-500 group-[.nav-active]:text-indigo-600">
                <path d="M9 6h11M4 6h.01M9 12h11M4 12h.01M9 18h11M4 18h.01" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
              </svg>
              <span>订单列表</span>
            </a>

            <a href="#"
               class="group relative flex items-center gap-3 rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
               data-page="forward.php" onclick="return loadPage(event,'forward.php')">
              <span class="absolute left-0 top-0 bottom-0 w-1 bg-indigo-600 rounded-r indicator"></span>
              <!-- 图标：支付 -->
              <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5 text-slate-500 group-[.nav-active]:text-indigo-600">
                <path d="M3.5 8h17M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                <rect x="6.5" y="12" width="5" height="3.2" rx="0.6" stroke="currentColor" stroke-width="1.6"/>
              </svg>
              <span>支付管理</span>
            </a>

            <div class="mt-4 px-2 pb-1 text-[11px] tracking-widest uppercase text-slate-400">系统</div>

            <a href="#"
               class="group relative flex items-center gap-3 rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
               data-page="settings.php" onclick="return loadPage(event,'settings.php')">
              <span class="absolute left-0 top-0 bottom-0 w-1 bg-indigo-600 rounded-r indicator"></span>
              <!-- 图标：设置 -->
              <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5 text-slate-500 group-[.nav-active]:text-indigo-600">
                <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.6"/>
                <path d="M19.4 15a1.6 1.6 0 0 1 .32 1.76l-.22.4a2.2 2.2 0 0 1-2.12 1.12l-.7-.06a1.6 1.6 0 0 0-1.54.86l-.32.64a2.2 2.2 0 0 1-2 1.2 2.2 2.2 0 0 1-2-.2l-.64-.32a1.6 1.6 0 0 0-1.54.06l-.64.36A2.2 2.2 0 0 1 5 20.4l-.4-.22a1.6 1.6 0 0 1-.76-1.76l.06-.7a1.6 1.6 0 0 0-.86-1.54l-.64-.32A2.2 2.2 0 0 1 2.2 12a2.2 2.2 0 0 1 .2-2l.32-.64c.27-.52.2-1.16-.06-1.54l-.36-.64A2.2 2.2 0 0 1 3.6 4.6l.4-.22A1.6 1.6 0 0 1 5.76 4l.7.06a1.6 1.6 0 0 0 1.54-.86l.32-.64A2.2 2.2 0 0 1 10.8 2.2c.7 0 1.38.18 2 .52l.64.32c.52.27 1.16.2 1.54-.06l.64-.36A2.2 2.2 0 0 1 18.4 3.6l.22.4c.27.52.86.86 1.54.76l.7-.06a1.6 1.6 0 0 1 1.54.86l.32.64c.26.52.2 1.16-.06 1.54l-.36.64c-.27.52-.2 1.16.06 1.54l.36.64A2.2 2.2 0 0 1 21.8 12c0 .7-.18 1.38-.52 2l-.32.64c-.27.52-.2 1.16.06 1.54l.36.64Z" stroke="currentColor" stroke-width="1.2" opacity=".55"/>
              </svg>
              <span>系统设置</span>
            </a>
          </nav>
        </aside>

        <!-- 内容区域 -->
        <main class="flex-1 min-w-0">
          <iframe id="contentFrame" src="dashboard.php"></iframe>
        </main>
      </div>
    </div>
  </div>

  <script>
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebarToggle');

    // 响应式：移动端侧边栏开合
    toggle?.addEventListener('click', () => {
      const opened = !sidebar.classList.contains('-translate-x-full');
      if (opened) {
        sidebar.classList.add('-translate-x-full');
      } else {
        sidebar.classList.remove('-translate-x-full');
      }
    });

    // 切换页面 + 选中态
    function loadPage(e, page) {
      e?.preventDefault?.();
      const frame = document.getElementById('contentFrame');
      frame.src = page;

      document.querySelectorAll('#sidebar a').forEach(a=>{
        a.classList.remove('nav-active');
      });
      const target = e.currentTarget || e.target;
      target.classList.add('nav-active');

      // 移动端点击后自动收起
      if (window.innerWidth < 1024) {
        sidebar.classList.add('-translate-x-full');
      }
      return false;
    }
    // 导航深链接支持（可选）：根据哈希加载页面
    window.addEventListener('load', ()=>{
      const hash = location.hash.replace('#','').trim();
      if (hash) {
        const link = document.querySelector(`#sidebar a[data-page="${hash}"]`);
        if (link) link.click();
        else document.getElementById('contentFrame').src = hash;
      }
    });
  </script>
</body>
</html>
