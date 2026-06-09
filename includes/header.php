<?php $user = current_user(); ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITI Project Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              itiNavy: '#0b1b39',
              itiOrange: '#f15a24'
            }
          }
        }
      }
    </script>
    <style>
      body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
      .iti-scrollbar::-webkit-scrollbar { height: 8px; width: 8px; }
      .iti-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
      .iti-card { border-radius: 1.5rem; border: 1px solid rgb(226 232 240); background: #fff; box-shadow: 0 1px 2px rgb(15 23 42 / 0.04); }
      .app-ui { background: radial-gradient(circle at 78% 5%, #fff 0, #f8fafc 35%, #f1f5f9 100%); }
      .app-ui main { width: 100%; }
      .app-ui main > :not(.dashboard-shell) { max-width: 1600px; margin-right: auto; margin-left: auto; }
      .app-ui main > .mb-6:first-child h2, .app-ui main > .mb-6:first-child h1 { color: #0f172a; letter-spacing: -.035em; }
      .app-ui main > .mb-6:first-child p:first-child { letter-spacing: .12em; }
      .app-ui :is(section, article, form, div).rounded-3xl.border.bg-white,
      .app-ui :is(section, article, form, div).rounded-2xl.border.bg-white {
        border-color: #e2e8f0;
        background: rgba(255,255,255,.96);
        box-shadow: 0 3px 12px rgb(15 23 42 / .05);
        transition: transform .3s cubic-bezier(.2,.8,.2,1), border-color .3s ease, box-shadow .3s ease;
      }
      .app-ui :is(section, article, form, div).rounded-3xl.border.bg-white:hover,
      .app-ui :is(section, article, form, div).rounded-2xl.border.bg-white:hover {
        border-color: #fed7aa;
        box-shadow: 0 16px 38px rgb(15 23 42 / .09);
      }
      .app-ui input:not([type=checkbox]):not([type=radio]), .app-ui select, .app-ui textarea {
        outline: none;
        transition: border-color .2s ease, box-shadow .2s ease, background .2s ease, transform .2s ease;
      }
      .app-ui input:not([type=checkbox]):not([type=radio]):hover, .app-ui select:hover, .app-ui textarea:hover { border-color: #fdba74; }
      .app-ui input:not([type=checkbox]):not([type=radio]):focus, .app-ui select:focus, .app-ui textarea:focus {
        border-color: #f97316;
        background: #fff;
        box-shadow: 0 0 0 4px rgb(249 115 22 / .11);
      }
      .app-ui input[type=checkbox], .app-ui input[type=radio] { accent-color: #f97316; cursor: pointer; }
      .app-ui button, .app-ui a { -webkit-tap-highlight-color: transparent; }
      .app-ui :is(a,button,input,select,textarea):focus-visible { outline: 2px solid #f97316; outline-offset: 2px; }
      .app-ui form.is-submitting button[type="submit"] { pointer-events: none; opacity: .72; }
      .app-ui button { transition: transform .2s ease, box-shadow .2s ease, filter .2s ease, background .2s ease; }
      .app-ui button:hover { transform: translateY(-1px); filter: saturate(1.08); }
      .app-ui button:active { transform: translateY(0) scale(.98); }
      .app-ui button.bg-orange-600, .app-ui button.bg-orange-500, .app-ui a.bg-orange-600 {
        background-image: linear-gradient(135deg, #fb923c, #ea580c);
        box-shadow: 0 8px 18px rgb(234 88 12 / .18);
      }
      .app-ui button.bg-orange-600:hover, .app-ui button.bg-orange-500:hover, .app-ui a.bg-orange-600:hover { box-shadow: 0 12px 24px rgb(234 88 12 / .26); }
      .app-ui table tbody tr { transition: background .2s ease, box-shadow .2s ease, transform .2s ease; }
      .app-ui table tbody tr:hover { background: #fff7ed; box-shadow: inset 3px 0 #f97316; }
      .app-ui table thead { color: #64748b; background: linear-gradient(180deg, #fff, #f8fafc); }
      .app-ui .app-reveal { opacity: 0; transform: translateY(14px); }
      .app-ui .app-reveal.app-visible { animation: appReveal .55s cubic-bezier(.2,.8,.2,1) forwards; animation-delay: var(--app-delay, 0ms); }
      .app-ui .app-clickable-card { cursor: pointer; }
      .app-ui .app-clickable-card:hover { transform: translateY(-3px); }
      .app-ui .app-ripple { position: relative; overflow: hidden; }
      .app-ui .app-ripple-dot { position: absolute; width: 12px; height: 12px; border-radius: 999px; background: rgb(255 255 255 / .34); pointer-events: none; transform: translate(-50%,-50%) scale(0); animation: appRipple .55s ease-out forwards; }
      .app-ui .app-modal-panel { animation: appModalIn .25s ease both; }
      .app-ui .app-toast-like { animation: appNoticeIn .35s ease both; }
      .app-ui a[href*="detail.php"], .app-ui a[href*="page="] { transition: color .2s ease, transform .2s ease, background .2s ease, box-shadow .2s ease; }
      .app-ui a[href*="detail.php"]:hover { color: #ea580c; }
      .app-ui .iti-scrollbar { scrollbar-color: #fdba74 #f1f5f9; scrollbar-width: thin; }
      .data-table-scroll { width: 100%; overflow-x: hidden; }
      .data-table-scroll table { min-width: 0; }
      .data-table-scroll td { min-width: 0; overflow: hidden; text-overflow: ellipsis; }
      @media (max-width: 1199px) {
        .data-table-scroll { overflow-x: auto; overscroll-behavior-x: contain; scrollbar-width: none; -ms-overflow-style: none; }
        .data-table-scroll::-webkit-scrollbar { display: none; width: 0; height: 0; }
        .tasks-page .data-table-scroll table { min-width: 1120px; }
        .projects-page .data-table-scroll table { min-width: 940px; }
      }
      .projects-page .project-kpi { opacity: 0; transform: translateY(12px); animation: projectKpiIn .55s cubic-bezier(.2,.8,.2,1) forwards; animation-delay: var(--project-delay, 0ms); }
      .projects-page .project-kpi > div > span { transition: transform .3s ease, color .3s ease, background .3s ease; }
      .projects-page .project-kpi:hover > div > span { transform: scale(1.08) rotate(-5deg); }
      .projects-page .project-row { transition: background .2s ease, box-shadow .2s ease, transform .2s ease; }
      .projects-page .project-row:hover { background: #fff7ed; box-shadow: inset 3px 0 #f97316; transform: translateX(2px); }
      .projects-page .project-row td:first-child span:first-child { transition: transform .25s ease; }
      .projects-page .project-row:hover td:first-child span:first-child { transform: scale(1.1) rotate(-5deg); }
      .projects-page .project-progress { transform: scaleX(0); transform-origin: left; transition: transform .8s cubic-bezier(.2,.8,.2,1); }
      .projects-page .project-progress.is-ready { transform: scaleX(1); }
      .projects-page details[open] > div { animation: dashboardMenuOpen .2s ease both; }
      .projects-page .member-option:has(input:checked) { border-color: #fb923c; background: #fff7ed; box-shadow: 0 0 0 3px rgb(251 146 60 / .10); }
      .projects-page .project-filter-panel { transition: border-color .3s ease, box-shadow .3s ease, transform .3s ease; }
      .projects-page .project-filter-panel.is-active { border-color: #fed7aa; box-shadow: 0 12px 32px rgb(15 23 42 / .08); }
      .projects-page .project-filter-panel.is-active .project-search-field { border-color: #cbd5e1; background: #fff; }
      .projects-page .project-filter-chip { transition: transform .2s ease, color .2s ease, background .2s ease; animation: projectChipIn .28s ease both; }
      .projects-page .project-filter-chip:hover { color: #ea580c; background: #fff7ed; transform: translateY(-2px); }
      .projects-page .project-filter-chip:hover span { color: #ea580c; transform: rotate(90deg); }
      .projects-page .project-filter-chip span { transition: transform .2s ease, color .2s ease; }
      .projects-page .data-table-scroll { overflow-x: hidden !important; }
      .projects-page .data-table-scroll table { min-width: 0 !important; }
      .projects-page .project-direct-actions a { white-space: nowrap; transition: color .2s ease, border-color .2s ease, background .2s ease, transform .2s ease; }
      .projects-page .project-direct-actions a:hover { transform: translateY(-1px); }
      @media (max-width: 1199px) {
        .projects-page .data-table-scroll table th:nth-child(2), .projects-page .data-table-scroll table td:nth-child(2),
        .projects-page .data-table-scroll table th:nth-child(6), .projects-page .data-table-scroll table td:nth-child(6) { display: none; }
        .projects-page .data-table-scroll table th:first-child { width: 31%; }
        .projects-page .data-table-scroll table th:nth-child(3) { width: 21%; }
        .projects-page .data-table-scroll table th:nth-child(4) { width: 13%; }
        .projects-page .data-table-scroll table th:nth-child(5) { width: 14%; }
        .projects-page .data-table-scroll table th:nth-child(7) { width: 21%; }
      }
      @media (max-width: 767px) {
        .projects-page .data-table-scroll table th:nth-child(3), .projects-page .data-table-scroll table td:nth-child(3),
        .projects-page .data-table-scroll table th:nth-child(4), .projects-page .data-table-scroll table td:nth-child(4) { display: none; }
        .projects-page .data-table-scroll table th:first-child { width: 48%; }
        .projects-page .data-table-scroll table th:nth-child(5) { width: 22%; }
        .projects-page .data-table-scroll table th:nth-child(7) { width: 30%; }
        .projects-page .project-direct-actions { flex-direction: column; align-items: stretch; }
        .projects-page .project-direct-actions a { justify-content: center; padding: 6px; }
      }
      .project-modal-panel { animation: appModalIn .25s ease both; }
      .tasks-page .task-kpi { opacity: 0; transform: translateY(12px); animation: taskKpiIn .55s cubic-bezier(.2,.8,.2,1) forwards; animation-delay: var(--task-delay, 0ms); }
      .tasks-page .task-kpi > div > span { transition: transform .3s ease, filter .3s ease; }
      .tasks-page .task-kpi:hover > div > span { transform: scale(1.08) rotate(-5deg); filter: saturate(1.15); }
      .tasks-page .task-row { transition: background .2s ease, box-shadow .2s ease, transform .2s ease; }
      .tasks-page .task-row:hover { background: #fff7ed; box-shadow: inset 3px 0 #f97316; transform: translateX(2px); }
      .tasks-page .task-row td:first-child > a > span:first-child { transition: transform .25s ease; }
      .tasks-page .task-row:hover td:first-child > a > span:first-child { transform: scale(1.1) rotate(-5deg); }
      .tasks-page .task-progress { transform: scaleX(0); transform-origin: left; transition: transform .8s cubic-bezier(.2,.8,.2,1); }
      .tasks-page .task-progress.is-ready { transform: scaleX(1); }
      .task-assignee-option:has(input:checked) { border-color: #fb923c; background: #fff7ed; box-shadow: 0 0 0 3px rgb(251 146 60 / .1); }
      .task-modal-panel { animation: appModalIn .25s ease both; }
      .task-detail-page section, .task-detail-page article { animation: taskDetailIn .5s cubic-bezier(.2,.8,.2,1) both; }
      .task-detail-page .task-detail-ring { background: conic-gradient(#f97316 calc(var(--progress) * 1%), #e2e8f0 0); filter: drop-shadow(0 8px 14px rgb(15 23 42 / .10)); transition: transform .3s ease, filter .3s ease; }
      .task-detail-page .task-detail-ring:hover { transform: scale(1.08) rotate(4deg); filter: drop-shadow(0 12px 18px rgb(249 115 22 / .20)); }
      .task-detail-page .task-detail-progress { transform: scaleX(0); transform-origin: left; transition: transform .9s cubic-bezier(.2,.8,.2,1); }
      .task-detail-page .task-detail-progress.is-ready { transform: scaleX(1); }
      .task-detail-page aside section > div > div { border-radius: 10px; transition: background .2s ease, transform .2s ease; }
      .task-detail-page aside section > div > div:hover { background: #fff7ed; transform: translateX(2px); }
      .task-detail-modal { animation: appModalIn .25s ease both; }
      .admin-dense-dashboard { width:100%; max-width:1700px; margin:auto; font-size:11px; }
      .admin-reveal{opacity:0;transform:translateY(10px);transition:opacity .5s ease,transform .5s cubic-bezier(.2,.8,.2,1);transition-delay:var(--delay,0ms)}.admin-dense-dashboard.is-ready .admin-reveal{opacity:1;transform:none}
      .admin-dense-header { display:flex; align-items:center; justify-content:space-between; gap:18px; margin-bottom:12px; }
      .admin-dense-header h1 { font-size:24px; font-weight:900; letter-spacing:-.035em; }.admin-dense-header p{margin-top:2px;color:#64748b}.admin-dense-header p span{margin-left:8px;border-radius:6px;padding:3px 8px;color:#ea580c;background:#fff1e8;font-weight:800}.admin-export{border:1px solid #e2e8f0;border-radius:9px;padding:10px 13px;background:white;font-weight:800}.admin-export{color:white;background:#071b38}
      .admin-header-actions{display:flex;align-items:center;gap:8px}.admin-search{display:flex;width:350px;align-items:center;gap:7px;border:1px solid #e2e8f0;border-radius:10px;padding:5px 6px 5px 11px;background:#fff;transition:border-color .2s ease,box-shadow .2s ease}.admin-search:focus-within{border-color:#fb923c;box-shadow:0 0 0 3px rgb(251 146 60 / .12)}.admin-search svg{width:16px;color:#64748b}.admin-search input{min-width:0;flex:1;padding:5px 0;background:transparent;font-size:9px;outline:none}.admin-search button{border-radius:7px;padding:6px 10px;color:#fff;background:#f97316;font-size:8px;font-weight:900;transition:background .2s ease,transform .2s ease}.admin-search button:hover{background:#ea580c;transform:translateY(-1px)}.admin-search-results{margin-bottom:10px;padding:12px}.admin-search-results>div:last-child{display:flex;flex-wrap:wrap;gap:7px;margin-top:9px}.admin-search-results>div:last-child a{display:flex;gap:6px;border-radius:7px;padding:7px 9px;background:#f8fafc;transition:background .2s ease,transform .2s ease}.admin-search-results>div:last-child a:hover{background:#fff7ed;transform:translateY(-1px)}.admin-search-results>div:last-child b{color:#f97316}.admin-notification,.admin-avatar{position:relative;display:grid;width:40px;height:40px;place-items:center;border:1px solid #e2e8f0;border-radius:10px;background:#fff;transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}.admin-notification svg{width:18px}.admin-notification b{position:absolute;right:-4px;top:-5px;border-radius:99px;padding:1px 5px;color:#fff;background:#f97316;font-size:8px}.admin-avatar{border:0;border-radius:50%;color:#fff;background:#f97316;font-weight:900}.admin-notification:hover,.admin-avatar:hover,.admin-export:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgb(15 23 42 / .12)}
      .admin-card { border:1px solid #e2e8f0; border-radius:12px; background:white; box-shadow:0 2px 8px rgb(15 23 42 / .05); transition:transform .25s ease,border-color .25s ease,box-shadow .25s ease; }.admin-card:hover{transform:translateY(-2px);border-color:#fed7aa;box-shadow:0 12px 28px rgb(15 23 42 / .09)}
      .admin-kpi-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px}.admin-kpi{display:flex;align-items:center;gap:13px;min-height:100px;padding:14px}.admin-kpi-icon{display:grid;width:51px;height:51px;flex:none;place-items:center;border-radius:50%;transition:transform .3s ease,filter .3s ease}.admin-kpi:hover .admin-kpi-icon{transform:scale(1.1) rotate(-6deg);filter:saturate(1.15)}.admin-kpi-icon svg{width:26px}.admin-kpi-icon.tone-orange{color:#f97316;background:#ffede3}.admin-kpi-icon.tone-blue{color:#3b82f6;background:#e8f1ff}.admin-kpi-icon.tone-amber{color:#f59e0b;background:#fff3d6}.admin-kpi-icon.tone-green{color:#16a34a;background:#e4f7e9}.admin-kpi-icon.tone-purple{color:#7c3aed;background:#f0eaff}.admin-kpi small,.admin-kpi strong,.admin-kpi em{display:block}.admin-kpi strong{font-size:25px;font-weight:900}.admin-kpi em{margin-top:3px;color:#16a34a;font-size:8px;font-style:normal}
      .admin-chart-grid{display:grid;grid-template-columns:1.55fr 1fr 1fr;gap:10px;margin-top:10px}.admin-chart-grid>.admin-card{min-height:250px;padding:15px}.admin-card h2{font-size:13px;font-weight:900}.admin-card-title{display:flex;align-items:center;justify-content:space-between}.admin-card-title span,.admin-card-title a{color:#ea580c;font-size:9px;font-weight:800}.admin-card-title select{border:1px solid #e2e8f0;border-radius:7px;padding:5px 8px;background:#fff;font-size:8px;font-weight:800;outline:none;transition:border-color .2s ease,box-shadow .2s ease}.admin-card-title select:focus{border-color:#fb923c;box-shadow:0 0 0 3px rgb(251 146 60 / .12)}.admin-card-title a{transition:transform .2s ease}.admin-card-title a:hover{transform:translateX(3px)}.admin-legend{display:flex;align-items:center;gap:5px;margin-top:10px}.admin-legend button{display:flex;align-items:center;gap:5px;border-radius:7px;padding:5px 7px;color:#475569;font-size:8px;transition:background .2s ease,opacity .2s ease}.admin-legend button:hover{background:#f8fafc}.admin-legend button.is-muted{opacity:.25}.admin-legend i,.admin-donut-list i{width:7px;height:7px;flex:none;border-radius:50%}
      .admin-activity-chart{position:relative;height:185px;margin-top:3px;padding-left:20px}.admin-activity-chart svg{width:100%;height:165px;overflow:visible}.admin-chart-axis{position:absolute;left:0;top:14px;bottom:28px;display:flex;flex-direction:column;justify-content:space-between;color:#94a3b8;font-size:7px}.admin-chart-gridline{fill:none;stroke:#dbe3ee;stroke-width:1;stroke-dasharray:4 5;vector-effect:non-scaling-stroke}.admin-chart-line{stroke-linecap:round;stroke-linejoin:round;transition:opacity .2s ease,stroke-width .2s ease;animation:adminLineDraw 1s ease both}.admin-chart-point{cursor:pointer;transition:r .2s ease,opacity .2s ease,filter .2s ease}.admin-chart-point:hover,.admin-chart-point:focus{r:6;filter:drop-shadow(0 3px 3px rgb(15 23 42 / .22));outline:none}.admin-chart-line.is-muted,.admin-chart-point.is-muted{opacity:.15}.admin-chart-labels{display:flex;justify-content:space-between;padding:0 2px 0 5px;color:#64748b;font-size:7px}.admin-chart-tooltip,.admin-donut-tooltip{position:absolute;z-index:5;display:grid;gap:1px;min-width:90px;border:1px solid #e2e8f0;border-radius:8px;padding:7px 9px;background:rgb(255 255 255 / .97);box-shadow:0 9px 24px rgb(15 23 42 / .14);font-size:8px;pointer-events:none;opacity:0;transform:translate(-50%,-110%) scale(.94);transition:opacity .18s ease,transform .18s ease}.admin-chart-tooltip.is-visible,.admin-donut-tooltip.is-visible{opacity:1;transform:translate(-50%,-120%) scale(1)}
      .admin-donut-layout{display:flex;align-items:center;justify-content:center;gap:15px;height:202px}.admin-interactive-donut{position:relative;width:132px;height:132px;flex:none;filter:drop-shadow(0 9px 16px rgb(15 23 42 / .1));transition:transform .3s ease,filter .3s ease}.admin-interactive-donut:hover{transform:scale(1.035);filter:drop-shadow(0 13px 20px rgb(15 23 42 / .16))}.admin-interactive-donut svg{width:100%;height:100%}.admin-donut-track{fill:none;stroke:#f1f5f9;stroke-width:18}.admin-donut-segment{fill:none;stroke-width:18;cursor:pointer;transform-box:fill-box;transform-origin:center;transition:opacity .2s ease,stroke-width .2s ease,transform .2s ease,filter .2s ease;animation:taskDonutReveal .8s ease both}.admin-donut-segment:hover,.admin-donut-segment:focus,.admin-donut-segment.is-active{stroke-width:22;transform:scale(1.025);filter:drop-shadow(0 3px 3px rgb(15 23 42 / .22));outline:none}.admin-donut-segment.is-muted{opacity:.2}.admin-donut-center{position:absolute;inset:0;display:grid;place-content:center;text-align:center;pointer-events:none;transition:transform .2s ease}.admin-interactive-donut:hover .admin-donut-center{transform:scale(1.05)}.admin-donut-center b{font-size:22px}.admin-donut-center small{display:block;color:#64748b;font-size:8px}.admin-donut-tooltip{left:50%;top:15%}.admin-donut-list{min-width:125px}.admin-donut-list button{display:flex;width:100%;align-items:center;gap:6px;border-radius:7px;padding:7px 5px;text-align:left;transition:transform .2s ease,background .2s ease,opacity .2s ease}.admin-donut-list button:hover,.admin-donut-list button:focus,.admin-donut-list button.is-active{transform:translateX(3px);background:#f8fafc;outline:none}.admin-donut-list span{flex:1}.admin-donut-list b{font-size:8px}
      .admin-interactive-pie{position:relative;width:132px;height:132px;flex:none;filter:drop-shadow(0 9px 16px rgb(15 23 42 / .12));transition:transform .3s ease,filter .3s ease}.admin-interactive-pie:hover{transform:scale(1.04) rotate(2deg);filter:drop-shadow(0 14px 22px rgb(15 23 42 / .18))}.admin-interactive-pie svg{width:100%;height:100%;overflow:visible}.admin-pie-segment{cursor:pointer;transform-box:fill-box;transform-origin:center;transition:opacity .22s ease,transform .22s ease,filter .22s ease;animation:taskDonutReveal .8s ease both}.admin-pie-segment:hover,.admin-pie-segment:focus,.admin-pie-segment.is-active{transform:scale(1.07);filter:drop-shadow(0 4px 3px rgb(15 23 42 / .25));outline:none}.admin-pie-segment.is-muted{opacity:.2}.admin-interactive-pie .admin-donut-tooltip{left:50%;top:12%}.admin-pie-total{display:flex;align-items:end;gap:5px;margin:8px 5px 0;padding-top:8px;border-top:1px solid #eef2f7}.admin-pie-total b{font-size:18px}.admin-pie-total span{padding-bottom:2px;color:#64748b;font-size:8px}
      .admin-lower-grid{display:grid;grid-template-columns:2fr 1fr;gap:10px;margin-top:10px}.admin-lower-grid>.admin-card{padding:14px}.admin-project-table table{width:100%;margin-top:8px;border-collapse:collapse}.admin-project-table th,.admin-project-table td{padding:8px 7px;border-bottom:1px solid #eef2f7;text-align:left}.admin-project-table th{color:#64748b}.admin-project-table tbody tr{transition:background .2s ease,transform .2s ease,box-shadow .2s ease}.admin-project-table tbody tr:hover{background:#fff7ed;box-shadow:inset 3px 0 #f97316;transform:translateX(2px)}.admin-project-table td a b,.admin-project-table td a small{display:block;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.admin-project-table td a small{color:#64748b;font-size:8px}.admin-project-table td i{display:block;width:80px;height:4px;margin-top:4px;border-radius:9px;background:#e2e8f0}.admin-project-table td em{display:block;width:0;height:100%;border-radius:9px;background:#f97316;transition:width .9s cubic-bezier(.2,.8,.2,1)}
      .admin-upcoming>a{display:flex;align-items:center;gap:9px;border-radius:8px;padding:8px 5px;border-bottom:1px solid #eef2f7;transition:background .2s ease,transform .2s ease}.admin-upcoming>a:hover{background:#fff7ed;transform:translateX(3px)}.admin-upcoming>a>span{display:grid;width:32px;height:32px;place-items:center;border-radius:9px;color:#ef4444;background:#fee2e2;font-weight:900;transition:transform .2s ease}.admin-upcoming>a:hover>span{transform:rotate(-6deg) scale(1.08)}.admin-upcoming p{min-width:0;flex:1}.admin-upcoming p b,.admin-upcoming p small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.admin-upcoming p small{color:#64748b;font-size:8px}.admin-upcoming time{font-size:8px}.admin-upcoming em{border-radius:6px;padding:4px 7px;font-size:8px;font-style:normal}.admin-upcoming .high{color:#ef4444;background:#fee2e2}.admin-upcoming .medium{color:#f59e0b;background:#fef3c7}.admin-upcoming .low{color:#16a34a;background:#dcfce7}
      .admin-quick{margin-top:10px;padding:14px}.admin-quick>div{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-top:10px}.admin-quick a{position:relative;display:flex;align-items:center;gap:9px;overflow:hidden;border:1px solid #edf1f6;border-radius:10px;padding:11px;background:#fff;box-shadow:0 2px 6px rgb(15 23 42 / .04);transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}.admin-quick a:hover{transform:translateY(-3px);border-color:#fdba74;box-shadow:0 10px 20px rgb(15 23 42 / .09)}.admin-quick span{display:grid;width:27px;height:27px;place-items:center;border-radius:8px;color:#f97316;background:#fff1e8;font-size:13px;font-weight:900;transition:transform .25s ease,color .25s ease,background .25s ease}.admin-quick a:hover span{transform:rotate(-7deg) scale(1.1);color:#fff;background:#f97316}
      @media(max-width:1250px){.admin-kpi-grid{grid-template-columns:repeat(3,1fr)}.admin-chart-grid{grid-template-columns:1fr 1fr}.admin-activity{grid-column:1/-1}.admin-quick>div{grid-template-columns:repeat(3,1fr)}}@media(max-width:800px){.admin-dense-header{align-items:flex-start;flex-direction:column}.admin-header-actions{width:100%;flex-wrap:wrap}.admin-search{width:100%}.admin-kpi-grid,.admin-chart-grid,.admin-lower-grid{grid-template-columns:1fr}.admin-activity{grid-column:auto}.admin-quick>div{grid-template-columns:1fr 1fr}.admin-donut-layout{height:auto;padding:18px 0}}
      .project-detail-page .project-detail-progress { transform: scaleX(0); transform-origin: left; transition: transform 1s cubic-bezier(.2,.8,.2,1); }
      .project-detail-page .project-detail-progress.is-ready { transform: scaleX(1); }
      .project-detail-page .timeline-item, .project-detail-page .project-task-row, .project-detail-page .activity-row { border-radius: 10px; transition: transform .22s ease, background .22s ease, padding .22s ease; }
      .project-detail-page .timeline-item:hover, .project-detail-page .project-task-row:hover, .project-detail-page .activity-row:hover { padding-right: 8px; padding-left: 8px; background: #fff7ed; transform: translateX(3px); }
      .project-detail-page .team-member-row { border-radius: 10px; transition: background .2s ease, transform .2s ease; }
      .project-detail-page .team-member-row:hover { background: #fff7ed; transform: translateX(2px); }
      .project-detail-page section { animation: projectDetailIn .55s cubic-bezier(.2,.8,.2,1) both; }
      .project-detail-page section:nth-of-type(2) { animation-delay: 80ms; }
      .project-detail-page section:nth-of-type(3) { animation-delay: 150ms; }
      .iti-sidebar { position: relative; isolation: isolate; overflow: hidden; background: linear-gradient(180deg, #061b38 0%, #082748 100%); }
      .iti-sidebar::after { position: absolute; z-index: -1; right: 0; bottom: 0; left: 0; height: 34%; content: ""; opacity: .22; background: linear-gradient(180deg, #082748 0%, rgba(5,24,50,.65) 45%, rgba(4,20,42,.9) 100%), url("<?= e(app_url('assets/images/gedung-iti.jpg')) ?>") center / cover no-repeat; }
      .iti-side-logo { clip-path: polygon(50% 0,100% 50%,50% 100%,0 50%); }
      .dashboard-shell { width: 100%; max-width: 1600px; margin: 0 auto; }
      .dashboard-card { border: 1px solid #e2e8f0; border-radius: 14px; background: rgba(255,255,255,.98); box-shadow: 0 2px 8px rgba(15,23,42,.05); }
      .dashboard-stat-icon { display: grid; width: 62px; height: 62px; place-items: center; border-radius: 999px; color: #f15a24; background: #ffede3; }
      .dashboard-section-title { color: #0f172a; font-size: 14px; font-weight: 900; }
      .dashboard-compact-list > * + * { border-top: 1px solid #eef2f7; }
      .dashboard-top-search:focus-within { border-color: #94a3b8; box-shadow: 0 0 0 3px rgba(148,163,184,.12); }
      .dashboard-donut { background: conic-gradient(#22c55e 0 37%, #f97316 37% 81%, #fbbf24 81% 93%, #ef4444 93% 100%); }
      .dashboard-gridline { border-top: 1px dashed #dbe3ee; }
      .task-donut-wrap { position: relative; filter: drop-shadow(0 12px 18px rgb(15 23 42 / .10)); transition: transform .35s ease, filter .35s ease; }
      .task-donut-wrap:hover { transform: scale(1.035); filter: drop-shadow(0 16px 22px rgb(15 23 42 / .17)); }
      .task-donut-track { fill: none; stroke: #f1f5f9; stroke-width: 18; }
      .task-donut-segment { fill: none; stroke-width: 18; stroke-linecap: butt; cursor: pointer; opacity: .94; transform-box: fill-box; transform-origin: center; transition: opacity .25s ease, stroke-width .25s ease, filter .25s ease, transform .25s ease; animation: taskDonutReveal .8s cubic-bezier(.2,.8,.2,1) both; }
      .task-donut-segment:hover, .task-donut-segment:focus, .task-donut-segment.is-active { opacity: 1; stroke-width: 22; filter: drop-shadow(0 4px 4px rgb(15 23 42 / .24)); outline: none; transform: scale(1.025); }
      .task-donut-segment.is-muted { opacity: .28; }
      .task-donut-center { transition: transform .25s ease; }
      .task-donut-wrap:hover .task-donut-center { transform: scale(1.04); }
      .task-donut-legend { transition: transform .2s ease, background .2s ease, color .2s ease; }
      .task-donut-legend:hover, .task-donut-legend:focus, .task-donut-legend.is-active { transform: translateX(4px); color: #0f172a; background: #f8fafc; outline: none; }
      .task-donut-tooltip { position: absolute; z-index: 10; min-width: 112px; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 10px; color: #0f172a; background: rgba(255,255,255,.96); box-shadow: 0 10px 28px rgb(15 23 42 / .16); font-size: 10px; pointer-events: none; opacity: 0; transform: translate(-50%, -115%) scale(.92); transition: opacity .18s ease, transform .18s ease; }
      .task-donut-tooltip.is-visible { opacity: 1; transform: translate(-50%, -125%) scale(1); }
      .dashboard-shell .dashboard-card { position: relative; transition: transform .3s cubic-bezier(.2,.8,.2,1), border-color .3s ease, box-shadow .3s ease, background .3s ease; }
      .dashboard-shell .dashboard-card:hover { transform: translateY(-3px); border-color: #fed7aa; box-shadow: 0 14px 34px rgb(15 23 42 / .10); }
      .dashboard-shell .dashboard-card:focus-within { border-color: #fb923c; box-shadow: 0 0 0 3px rgb(251 146 60 / .15), 0 14px 34px rgb(15 23 42 / .10); }
      .dashboard-shell .dashboard-stat-icon { transition: transform .35s cubic-bezier(.2,.8,.2,1), color .3s ease, background .3s ease; }
      .dashboard-shell a.dashboard-card:hover .dashboard-stat-icon { color: #fff; background: #f97316; transform: rotate(-7deg) scale(1.1); }
      .dashboard-shell a.dashboard-card:hover .dashboard-stat-icon svg { animation: dashboardIconBounce .55s ease; }
      .dashboard-shell .dashboard-top-search { transition: width .3s ease, border-color .25s ease, box-shadow .25s ease, transform .25s ease; }
      .dashboard-shell .dashboard-top-search:hover { transform: translateY(-1px); border-color: #cbd5e1; box-shadow: 0 8px 22px rgb(15 23 42 / .07); }
      .dashboard-shell .dashboard-top-search:focus-within { width: 390px; border-color: #fb923c; box-shadow: 0 0 0 3px rgb(251 146 60 / .12), 0 8px 22px rgb(15 23 42 / .07); }
      .dashboard-shell .dashboard-interactive-icon { transition: color .2s ease, background .2s ease, transform .2s ease, box-shadow .2s ease; }
      .dashboard-shell .dashboard-interactive-icon:hover { color: #f97316; background: #fff7ed; transform: translateY(-2px) rotate(-5deg); box-shadow: 0 8px 18px rgb(15 23 42 / .10); }
      .dashboard-shell .dashboard-interactive-icon:hover svg { animation: dashboardBellRing .55s ease; }
      .dashboard-sidebar .iti-side-logo { transition: transform .35s cubic-bezier(.2,.8,.2,1), filter .35s ease; }
      .dashboard-sidebar .iti-side-logo:hover { transform: rotate(5deg) scale(1.08); filter: drop-shadow(0 8px 12px rgb(249 115 22 / .35)); }
      .dashboard-sidebar nav a { position: relative; overflow: hidden; transition: transform .22s ease, background .22s ease, color .22s ease, box-shadow .22s ease; }
      .dashboard-sidebar nav a:hover { transform: translateX(4px); }
      .dashboard-sidebar nav a svg { transition: transform .25s ease; }
      .dashboard-sidebar nav a:hover svg { transform: scale(1.12) rotate(-4deg); }
      .dashboard-sidebar nav a.bg-gradient-to-r::after { position: absolute; inset: 0; content: ""; background: linear-gradient(110deg, transparent 20%, rgb(255 255 255 / .20) 50%, transparent 80%); transform: translateX(-120%); animation: dashboardActiveShine 3.2s ease-in-out infinite; }
      .dashboard-sidebar nav a span.rounded-full { animation: dashboardNotificationPulse 2s ease-in-out infinite; }
      .dashboard-shell details > summary { transition: transform .2s ease, opacity .2s ease; }
      .dashboard-shell details > summary:hover { transform: translateY(-1px); opacity: .78; }
      .dashboard-shell details[open] > div { transform-origin: top right; animation: dashboardMenuOpen .2s ease both; }
      .dashboard-shell .dashboard-list-item { border-radius: 10px; transition: transform .22s ease, background .22s ease, padding .22s ease; }
      .dashboard-shell .dashboard-list-item:hover { padding-right: 8px; padding-left: 8px; background: #f8fafc; transform: translateX(3px); }
      .dashboard-shell .dashboard-list-item:hover > span:first-child { transform: scale(1.1) rotate(-4deg); }
      .dashboard-shell .dashboard-list-item > span:first-child { transition: transform .22s ease, box-shadow .22s ease; }
      .dashboard-shell .dashboard-table-row { transition: background .2s ease, transform .2s ease, box-shadow .2s ease; }
      .dashboard-shell .dashboard-table-row:hover { background: #fff7ed; transform: translateX(2px); box-shadow: inset 3px 0 #f97316; }
      .dashboard-shell .dashboard-table-row a span { transition: transform .25s ease; }
      .dashboard-shell .dashboard-table-row:hover a span { transform: scale(1.1) rotate(-5deg); }
      .dashboard-shell .dashboard-progress-fill { transform-origin: left; transform: scaleX(0); transition: transform .8s cubic-bezier(.2,.8,.2,1); }
      .dashboard-shell.is-ready .dashboard-progress-fill { transform: scaleX(1); }
      .dashboard-shell .dashboard-progress-point { cursor: pointer; transition: transform .2s ease, background .2s ease, box-shadow .2s ease; }
      .dashboard-shell .dashboard-progress-point:hover, .dashboard-shell .dashboard-progress-point:focus { z-index: 4; background: #f97316; box-shadow: 0 0 0 5px rgb(249 115 22 / .16); outline: none; transform: translateX(-50%) scale(1.35); }
      .dashboard-shell .dashboard-progress-line { stroke-dasharray: 700; stroke-dashoffset: 700; transition: stroke-dashoffset 1.2s cubic-bezier(.2,.8,.2,1); }
      .dashboard-shell.is-ready .dashboard-progress-line { stroke-dashoffset: 0; }
      .dashboard-shell .dashboard-reveal { opacity: 0; transform: translateY(12px); }
      .dashboard-shell.is-ready .dashboard-reveal { animation: dashboardReveal .55s cubic-bezier(.2,.8,.2,1) forwards; animation-delay: var(--reveal-delay, 0ms); }
      .dashboard-progress-tooltip { position: fixed; z-index: 50; min-width: 100px; padding: 7px 9px; border: 1px solid #e2e8f0; border-radius: 9px; color: #0f172a; background: rgba(255,255,255,.97); box-shadow: 0 10px 24px rgb(15 23 42 / .15); font-size: 10px; pointer-events: none; opacity: 0; transform: translate(-50%, -115%) scale(.94); transition: opacity .15s ease, transform .15s ease; }
      .dashboard-progress-tooltip.is-visible { opacity: 1; transform: translate(-50%, -125%) scale(1); }
      .mobile-dashboard { display: none; }
      .mobile-navigation-drawer, .mobile-drawer-backdrop { display: none; }
      @media (max-width: 767px) {
        .app-ui:has(.mobile-dashboard) { background: #f3f5f8; }
        .app-ui:has(.mobile-dashboard) main { padding: 0 0 92px; overflow: visible; }
        .app-ui:has(.mobile-dashboard) .dashboard-sidebar { display: none; }
        .mobile-drawer-backdrop { position:fixed; z-index:79; inset:0; display:block; visibility:hidden; background:rgb(2 10 24 / .68); opacity:0; backdrop-filter:blur(2px); transition:opacity .3s ease,visibility .3s ease; }
        .mobile-drawer-backdrop.is-open { visibility:visible; opacity:1; }
        .mobile-navigation-drawer { position:fixed; z-index:80; inset:0 auto 0 0; display:block; width:min(86vw,355px); overflow-y:auto; overscroll-behavior:contain; border-radius:0 28px 28px 0; color:white; background:radial-gradient(circle at 50% 15%,#0c315a,#061b38 65%); padding:24px 18px 28px; box-shadow:20px 0 50px rgb(0 0 0 / .3); transform:translateX(-105%); transition:transform .34s cubic-bezier(.2,.8,.2,1); scrollbar-width:none; }
        .mobile-navigation-drawer::-webkit-scrollbar{display:none}.mobile-navigation-drawer.is-open{transform:translateX(0)}
        .mobile-drawer-close{position:absolute;right:18px;top:17px;width:38px;height:38px;border-radius:50%;color:white;font-size:30px;line-height:1}.mobile-drawer-close:active{background:rgb(255 255 255 / .1);transform:rotate(90deg)}
        .mobile-drawer-brand{display:flex;align-items:center;gap:13px;padding-right:40px}.mobile-drawer-brand img{width:64px;height:64px;object-fit:cover;clip-path:polygon(50% 0,100% 50%,50% 100%,0 50%)}.mobile-drawer-brand strong{text-transform:uppercase;font-size:16px;line-height:1.25;letter-spacing:.04em}
        .mobile-drawer-profile{display:flex;align-items:center;gap:15px;margin-top:25px}.mobile-drawer-profile>span{display:grid;width:56px;height:56px;place-items:center;border:2px solid white;border-radius:50%;background:linear-gradient(135deg,#fb923c,#f97316);font-size:20px;font-weight:900}.mobile-drawer-profile b{display:block;font-size:16px}.mobile-drawer-profile small{display:block;margin-top:2px;color:#bac8d9;font-size:12px}
        .mobile-drawer-label{margin:28px 8px 10px;color:#9fb0c5;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em}.mobile-drawer-nav{display:grid;gap:4px}.mobile-drawer-nav a{display:flex;align-items:center;gap:15px;min-height:50px;border-radius:13px;padding:0 13px;color:#f8fafc;font-size:14px;font-weight:700;transition:background .2s ease,transform .2s ease,color .2s ease}.mobile-drawer-nav a:active{transform:scale(.98);background:rgb(255 255 255 / .08)}.mobile-drawer-nav a.is-active{position:relative;color:#f97316;background:linear-gradient(90deg,rgb(12 55 99 / .95),rgb(13 61 109 / .72))}.mobile-drawer-nav a.is-active:before{position:absolute;inset:7px auto 7px 0;width:3px;border-radius:99px;background:#f97316;content:""}.mobile-drawer-nav svg{width:22px;height:22px;flex:none}.mobile-drawer-nav span{flex:1}.mobile-drawer-nav i{color:#9fb0c5;font-size:25px;font-style:normal;font-weight:300}.mobile-drawer-nav b{display:grid;min-width:25px;height:25px;place-items:center;border-radius:50%;background:#f97316;font-size:11px}.mobile-drawer-divider{height:1px;margin:18px 8px 0;background:rgb(255 255 255 / .12)}
        .mobile-drawer-info{display:flex;align-items:center;gap:12px;margin:28px 5px 0;border:1px solid rgb(96 165 250 / .3);border-radius:14px;padding:15px;color:white;background:rgb(13 54 96 / .65)}.mobile-drawer-info>span{font-size:34px}.mobile-drawer-info div{min-width:0;flex:1}.mobile-drawer-info b{display:block;font-size:12px}.mobile-drawer-info small{display:block;margin-top:4px;color:#cbd5e1;font-size:10px;line-height:1.5}.mobile-drawer-info i{font-size:25px;font-style:normal}.mobile-navigation-drawer footer{margin:28px 8px 0;border-top:1px solid rgb(255 255 255 / .12);padding-top:20px;color:#9fb0c5;font-size:10px;line-height:1.8}
        .app-ui:has(.mobile-dashboard) .dashboard-shell { display: none; }
        .mobile-dashboard { display: block; min-height: 100vh; color: #0f172a; }
        .mobile-dashboard-hero { min-height: 268px; padding: 18px 17px 64px; background: radial-gradient(circle at 50% 10%, #0d315a, #061a36 70%); }
        .mobile-dashboard-logo { width: 52px; height: 52px; flex: none; object-fit: cover; clip-path: polygon(50% 0,100% 50%,50% 100%,0 50%); filter: drop-shadow(0 6px 10px rgb(0 0 0 / .22)); transition: transform .25s ease, filter .25s ease; }
        .mobile-dashboard-logo:active { transform: scale(.92) rotate(-4deg); filter: drop-shadow(0 3px 5px rgb(0 0 0 / .18)); }
        .mobile-menu-button { display: grid; width: 42px; gap: 6px; padding: 8px; }
        .mobile-menu-button span { display: block; height: 3px; border-radius: 99px; background: white; transition: transform .22s ease, width .22s ease; }
        .mobile-menu-button:active span:first-child { transform: translateX(5px); }.mobile-menu-button:active span:last-child { transform: translateX(-5px); }
        .mobile-dashboard-content { position: relative; z-index: 2; display: grid; gap: 11px; padding: 0 9px 14px; }
        .mobile-dashboard-card { overflow: hidden; border: 1px solid #e8edf3; border-radius: 18px; background: rgba(255,255,255,.98); padding: 15px 13px; box-shadow: 0 7px 20px rgb(15 23 42 / .075); animation: mobileCardIn .5s cubic-bezier(.2,.8,.2,1) both; transition: transform .22s ease, box-shadow .22s ease; }
        .mobile-dashboard-card:nth-child(2) { animation-delay:70ms }.mobile-dashboard-card:nth-child(3) { animation-delay:140ms }
        .mobile-dashboard-card:active { transform: scale(.99); box-shadow: 0 4px 12px rgb(15 23 42 / .08); }
        .mobile-dashboard-card h2 { font-size: 15px; font-weight: 900; }
        .mobile-dashboard-card > div:first-child a { color: #f97316; font-size: 12px; font-weight: 700; }
        .mobile-overview-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); }
        .mobile-overview-item { min-width: 0; padding: 0 5px; text-align: center; border-right: 1px solid #e5e7eb; transition: transform .2s ease, background .2s ease; }
        .mobile-overview-item:active { border-radius:12px; background:#fff7ed; transform:scale(.96); }
        .mobile-overview-item:last-child { border-right: 0; }
        .mobile-overview-icon { display: grid; width: 43px; height: 43px; margin: 0 auto 8px; place-items: center; border-radius: 50%; transition:transform .25s ease; }
        .mobile-overview-item:active .mobile-overview-icon { transform:rotate(-8deg) scale(1.08); }
        .mobile-overview-icon svg { width: 23px; height: 23px; }
        .mobile-overview-icon.tone-orange { color:#f97316;background:#ffede3 }.mobile-overview-icon.tone-blue { color:#3b82f6;background:#e6f0ff }.mobile-overview-icon.tone-amber { color:#f59e0b;background:#fff4d6 }.mobile-overview-icon.tone-green { color:#16a34a;background:#e0f7e8 }
        .mobile-overview-item strong { display:block; font-size:20px; font-weight:900 }.mobile-overview-item b { display:block; min-height:25px; margin-top:2px; font-size:8px }.mobile-overview-item small { display:block; margin-top:3px; font-size:6.5px; white-space:nowrap }
        .mobile-project-row { display:flex; align-items:center; gap:10px; padding:9px 0; border-radius:12px; transition:background .2s ease, transform .2s ease, padding .2s ease; }
        .mobile-project-row:active,.mobile-task-row:active { background:#fff7ed; transform:translateX(3px); padding-left:5px; padding-right:5px; }
        .mobile-project-cover { display:grid; width:64px; height:64px; flex:none; place-items:center; border-radius:11px; padding:6px; text-align:center; color:white; background:#06244a; transition:transform .25s ease; }
        .mobile-project-row:active .mobile-project-cover { transform:rotate(-4deg) scale(1.05); }
        .mobile-project-cover.cover-1 { background:linear-gradient(135deg,#fb923c,#f97316) }.mobile-project-cover.cover-2 { background:#09294e }
        .mobile-project-cover b { font-size:10px; line-height:1.15 }.mobile-project-cover svg { width:22px; margin-top:4px }
        .mobile-project-row em { font-style:normal; }
        .mobile-task-row { display:flex; align-items:center; gap:9px; padding:12px 2px; border-radius:12px; transition:background .2s ease, transform .2s ease, padding .2s ease; }
        .mobile-task-row time { font-size:9px; white-space:nowrap; }
        .mobile-priority { border-radius:8px; padding:5px 8px; font-size:9px; font-weight:700; }.priority-high{color:#ef4444;background:#fee2e2}.priority-medium{color:#2563eb;background:#dbeafe}.priority-low{color:#16a34a;background:#dcfce7}
        .mobile-bottom-nav { position:fixed; z-index:50; right:0; bottom:0; left:0; display:grid; grid-template-columns:repeat(5,1fr); align-items:end; border-top:1px solid #e2e8f0; border-radius:20px 20px 0 0; background:rgba(255,255,255,.96); padding:9px 8px 12px; box-shadow:0 -8px 25px rgb(15 23 42 / .08); backdrop-filter:blur(16px); }
        .mobile-bottom-nav a { display:grid; min-height:50px; place-items:center; gap:3px; border-radius:14px; color:#64748b; font-size:9px; font-weight:700; transition:transform .18s ease, color .18s ease, background .18s ease; }
        .mobile-bottom-nav a:active { transform:scale(.9); color:#f97316; background:#fff7ed; }
        .mobile-bottom-nav svg { width:23px; height:23px; fill:none; stroke:currentColor; stroke-width:1.8; }
        .mobile-bottom-nav .is-active { color:#f97316; background:#fff7ed; }.mobile-bottom-nav .is-active svg { fill:#f97316; stroke:#f97316; }
        .mobile-bottom-nav .mobile-add-button { color:#f97316; }.mobile-add-button b { display:grid; width:52px; height:52px; margin-top:-25px; place-items:center; border-radius:50%; color:white; background:linear-gradient(135deg,#fb923c,#ea580c); box-shadow:0 8px 20px rgb(234 88 12 / .28); font-size:34px; font-weight:300; transition:transform .2s ease, box-shadow .2s ease; animation:mobileAddPulse 2.8s ease-in-out infinite; }.mobile-add-button:active b{transform:scale(.86) rotate(90deg);box-shadow:0 4px 10px rgb(234 88 12 / .22)}
        .mobile-project-row em { transform:scaleX(0); transform-origin:left; animation:mobileProgressIn .8s cubic-bezier(.2,.8,.2,1) forwards; }
      }
      @keyframes taskDonutReveal { from { stroke-dashoffset: 100; opacity: 0; } }
      @keyframes dashboardReveal { to { opacity: 1; transform: translateY(0); } }
      @keyframes dashboardIconBounce { 50% { transform: translateY(-3px) scale(1.08); } }
      @keyframes dashboardBellRing { 25% { transform: rotate(-14deg); } 50% { transform: rotate(12deg); } 75% { transform: rotate(-8deg); } }
      @keyframes dashboardMenuOpen { from { opacity: 0; transform: translateY(-5px) scale(.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
      @keyframes dashboardActiveShine { 45%, 100% { transform: translateX(120%); } }
      @keyframes dashboardNotificationPulse { 50% { transform: scale(1.1); box-shadow: 0 0 0 5px rgb(249 115 22 / .12); } }
      @keyframes appReveal { to { opacity: 1; transform: translateY(0); } }
      @keyframes appRipple { to { opacity: 0; transform: translate(-50%,-50%) scale(14); } }
      @keyframes appModalIn { from { opacity: 0; transform: translateY(8px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
      @keyframes appNoticeIn { from { opacity: 0; transform: translateX(8px); } to { opacity: 1; transform: translateX(0); } }
      @keyframes projectKpiIn { to { opacity: 1; transform: translateY(0); } }
      @keyframes taskKpiIn { to { opacity: 1; transform: translateY(0); } }
      @keyframes taskDetailIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
      @keyframes adminDenseIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
      @keyframes adminLineDraw { from { stroke-dasharray: 1000; stroke-dashoffset: 1000; } to { stroke-dasharray: 1000; stroke-dashoffset: 0; } }
      @keyframes mobileCardIn { from { opacity:0; transform:translateY(12px) } to { opacity:1; transform:translateY(0) } }
      @keyframes mobileProgressIn { to { transform:scaleX(1) } }
      @keyframes mobileAddPulse { 50% { transform:translateY(-2px); box-shadow:0 11px 25px rgb(234 88 12 / .34) } }
      @keyframes projectChipIn { from { opacity: 0; transform: translateY(-4px) scale(.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
      @keyframes projectDetailIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
      @media (prefers-reduced-motion: reduce) {
        .task-donut-segment, .task-donut-wrap, .task-donut-center, .task-donut-legend, .task-donut-tooltip, .dashboard-shell *, .dashboard-progress-tooltip { animation: none !important; transition: none !important; }
        .dashboard-shell .dashboard-reveal { opacity: 1; transform: none; }
        .dashboard-shell .dashboard-progress-fill { transform: scaleX(1); }
        .app-ui .app-reveal { opacity: 1; transform: none; }
      }
      @media (max-width: 1023px) {
        .iti-mobile-pad { padding: 1rem; }
        .iti-sidebar::after { display: none; }
      }
    </style>
</head>
<body class="app-ui bg-slate-50 text-slate-900">
<div class="min-h-screen lg:flex">
