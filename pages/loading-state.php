<div class="additional-skeleton-page" aria-label="Memuat halaman">
  <header><span class="sk sk-title"></span><span class="sk sk-subtitle"></span></header>
  <section class="skeleton-kpis"><?php for($i=0;$i<5;$i++): ?><article><i class="sk"></i><span><b class="sk"></b><small class="sk"></small></span><em class="sk"></em></article><?php endfor; ?></section>
  <section class="skeleton-charts"><article><b class="sk"></b><div class="skeleton-bars"><?php for($i=0;$i<8;$i++): ?><i class="sk" style="height:<?= e(25+$i*7) ?>%"></i><?php endfor; ?></div></article><article><b class="sk"></b><div class="skeleton-donut sk"></div></article></section>
  <section class="skeleton-table"><b class="sk"></b><?php for($i=0;$i<5;$i++): ?><p><i class="sk"></i><span class="sk"></span><span class="sk"></span><span class="sk"></span><em class="sk"></em></p><?php endfor; ?></section>
</div>
