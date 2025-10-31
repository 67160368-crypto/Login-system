<?php
// dashboard.php — Retail DW Dashboard (Thai Bright Theme)
// ใช้ MySQL (mysqli) + Chart.js + Bootstrap

$DB_HOST = '127.0.0.1';
$DB_USER = 's67160368';
$DB_PASS = 'vBwzb8s8';
$DB_NAME = 's67160368';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  http_response_code(500);
  die('Database connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

function fetch_all($mysqli, $sql) {
  $res = $mysqli->query($sql);
  if (!$res) return [];
  $rows = [];
  while ($row = $res->fetch_assoc()) $rows[] = $row;
  $res->free();
  return $rows;
}

// --- ดึงข้อมูล ---
$monthly = fetch_all($mysqli, "SELECT ym, net_sales FROM v_monthly_sales");
$category = fetch_all($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
$region = fetch_all($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
$topProducts = fetch_all($mysqli, "SELECT product_name, qty_sold, net_sales FROM v_top_products");
$payment = fetch_all($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
$hourly = fetch_all($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales");
$newReturning = fetch_all($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");
$kpis = fetch_all($mysqli, "
  SELECT
    (SELECT SUM(net_amount) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS sales_30d,
    (SELECT SUM(quantity) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS qty_30d,
    (SELECT COUNT(DISTINCT customer_id) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS buyers_30d
");
$kpi = $kpis ? $kpis[0] : ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0];

function nf($n){ return number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Retail DW Dashboard (Thai Bright Theme)</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
  body {
    font-family: 'Prompt', sans-serif;
    background: #f9fafb;
    color: #1e293b;
  }
  h2,h5 { font-weight: 600; }
  .card {
    background: #ffffff;
    border: none;
    border-radius: 1rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
  }
  .card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.1); }
  .kpi { font-size: 1.5rem; font-weight: 700; }
  .sub { color: #744a37ff; font-size: .9rem; }
  .grid { display: grid; gap: 1rem; grid-template-columns: repeat(12, 1fr); }
  .col-12 { grid-column: span 12; }
  .col-6 { grid-column: span 6; }
  .col-4 { grid-column: span 4; }
  .col-8 { grid-column: span 8; }
  @media (max-width: 991px) {
    .col-6, .col-4, .col-8 { grid-column: span 12; }
  }
  canvas { max-height: 360px; }

  /* ปุ่ม Logout */
  .btn-logout {
    background: linear-gradient(90deg, #ef4444, #f97316);
    color: #fff;
    border: none;
    font-weight: 600;
    border-radius: 50px;
    padding: 0.4rem 1rem;
  }
  .btn-logout:hover {
    background: linear-gradient(90deg, #dc2626, #ea580c);
    color: #fff;
  }

  /* สีหลักของกราฟ */
  :root {
    --green: #22c55e;
    --yellow: #eab308;
    --orange: #f97316;
    --blue: #3b82f6;
  }

  .kpi-row {
  display: flex;              /* จัดให้อยู่ในแถวเดียว */
  gap: 1rem;                  /* ระยะห่างระหว่างกล่อง */
}

.kpi-card {
  flex: 1;                    /* ให้แต่ละกล่องแบ่งความกว้างเท่ากัน */
  text-align: center;         /* จัดข้อความให้อยู่กลาง */
}

</style>
</head>
<body class="p-3 p-md-4">

<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h2 class="mb-0 text-primary">ยอดขาย (Retail DW) — Dashboard</h2>
    <div>
      <a href="logout.php" class="btn btn-logout">Logout</a>
      <span class="sub ms-2">แหล่งข้อมูล: MySQL (mysqli)</span>
    </div>
  </div>

  <!-- KPI -->
<div class="kpi-row mb-3">
  <div class="card p-3 kpi-card border-top border-4 border-success">
    <h5>ยอดขาย 30 วัน</h5>
    <div class="kpi text-success">฿<?= nf($kpi['sales_30d']) ?></div>
  </div>
  <div class="card p-3 kpi-card border-top border-4 border-warning">
    <h5>จำนวนชิ้นขาย 30 วัน</h5>
    <div class="kpi text-warning"><?= number_format((int)$kpi['qty_30d']) ?> ชิ้น</div>
  </div>
  <div class="card p-3 kpi-card border-top border-4 border-info">
    <h5>จำนวนผู้ซื้อ 30 วัน</h5>
    <div class="kpi text-info"><?= number_format((int)$kpi['buyers_30d']) ?> คน</div>
  </div>
</div>

<!-- Charts -->
<div class="d-flex gap-3 mb-3">
  <div class="card p-3 flex-fill">
    <h5>ยอดขายรายเดือน (2 ปี)</h5>
    <canvas id="chartMonthly"></canvas>
  </div>
  <div class="card p-3 flex-fill">
    <h5>สัดส่วนยอดขายตามหมวด</h5>
    <canvas id="chartCategory"></canvas>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card p-3">
      <h5>Top 10 สินค้าขายดี</h5>
      <canvas id="chartTopProducts"></canvas>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3">
      <h5>ยอดขายตามภูมิภาค</h5>
      <canvas id="chartRegion"></canvas>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3">
      <h5>วิธีการชำระเงิน</h5>
      <canvas id="chartPayment"></canvas>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card p-3">
      <h5>ยอดขายรายชั่วโมง</h5>
      <canvas id="chartHourly"></canvas>
    </div>
  </div>
  <div class="col-12">
    <div class="card p-3">
      <h5>ลูกค้าใหม่ vs ลูกค้าเดิม (รายวัน)</h5>
      <canvas id="chartNewReturning"></canvas>
    </div>
  </div>
</div>

<script>
// เตรียมข้อมูลจาก PHP -> JS
const monthly = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;
const category = <?= json_encode($category, JSON_UNESCAPED_UNICODE) ?>;
const region = <?= json_encode($region, JSON_UNESCAPED_UNICODE) ?>;
const topProducts = <?= json_encode($topProducts, JSON_UNESCAPED_UNICODE) ?>;
const payment = <?= json_encode($payment, JSON_UNESCAPED_UNICODE) ?>;
const hourly = <?= json_encode($hourly, JSON_UNESCAPED_UNICODE) ?>;
const newReturning = <?= json_encode($newReturning, JSON_UNESCAPED_UNICODE) ?>;

// Utility: pick labels & values
const toXY = (arr, x, y) => ({ labels: arr.map(o => o[x]), values: arr.map(o => parseFloat(o[y])) });

// Monthly
(() => {
  const {labels, values} = toXY(monthly, 'ym', 'net_sales');
  new Chart(document.getElementById('chartMonthly'), {
    type: 'line',
    data: { labels, datasets: [{ label: 'ยอดขาย (฿)', data: values, tension: .25, fill: true }] },
    options: { plugins: { legend: { labels: { color: '#d30909ff' } } }, scales: {
      x: { ticks: { color: '#bd06ffff' }, grid: { color: 'rgba(255,255,255,.08)' } },
      y: { ticks: { color: '#ff06f3ff' }, grid: { color: 'rgba(255,255,255,.08)' } }
    }}
  });
})();

// Category
(() => {
  const {labels, values} = toXY(category, 'category', 'net_sales');
  new Chart(document.getElementById('chartCategory'), {
    type: 'doughnut',
    data: { labels, datasets: [{ data: values }] },
    options: { plugins: { legend: { position: 'bottom', labels: { color: '#000000ff' } } } }
  });
})();

// Top products
(() => {
  const labels = topProducts.map(o => o.product_name);
  const qty = topProducts.map(o => parseInt(o.qty_sold));
  new Chart(document.getElementById('chartTopProducts'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'ชิ้นที่ขาย', data: qty }] },
    options: {
      indexAxis: 'y',
      plugins: { legend: { labels: { color: '#d30909ff' } } },
      scales: {
        x: { ticks: { color: '#0a795fff' }, grid: { color: 'rgba(255,255,255,.08)' } },
        y: { ticks: { color: '#13d11cff' }, grid: { color: 'rgba(255,255,255,.08)' } }
      }
    }
  });
})();

// Region
(() => {
  const {labels, values} = toXY(region, 'region', 'net_sales');
  new Chart(document.getElementById('chartRegion'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'ยอดขาย (฿)', data: values }] },
    options: { plugins: { legend: { labels: { color: '#d30909ff' } } }, scales: {
      x: { ticks: { color: '#bb440dff' }, grid: { color: 'rgba(255,255,255,.08)' } },
      y: { ticks: { color: '#c7b10aff' }, grid: { color: 'rgba(255,255,255,.08)' } }
    }}
  });
})();

// Payment
(() => {
  const {labels, values} = toXY(payment, 'payment_method', 'net_sales');
  new Chart(document.getElementById('chartPayment'), {
    type: 'pie',
    data: { labels, datasets: [{ data: values }] },
    options: { plugins: { legend: { position: 'bottom', labels: { color: '#151515ff' } } } }
  });
})();

// Hourly
(() => {
  const {labels, values} = toXY(hourly, 'hour_of_day', 'net_sales');
  new Chart(document.getElementById('chartHourly'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'ยอดขาย (฿)', data: values }] },
    options: { plugins: { legend: { labels: { color: '#d30909ff'  } } }, scales: {
      x: { ticks: { color: '#28b80fff' }, grid: { color: 'rgba(255,255,255,.08)' } },
      y: { ticks: { color: '#c3c90eff' }, grid: { color: 'rgba(255,255,255,.08)' } }
    }}
  });
})();

// New vs Returning
(() => {
  const labels = newReturning.map(o => o.date_key);
  const newC = newReturning.map(o => parseFloat(o.new_customer_sales));
  const retC = newReturning.map(o => parseFloat(o.returning_sales));
  new Chart(document.getElementById('chartNewReturning'), {
    type: 'line',
    data: { labels,
      datasets: [
        { label: 'ลูกค้าใหม่ (฿)', data: newC, tension: .25, fill: false },
        { label: 'ลูกค้าเดิม (฿)', data: retC, tension: .25, fill: false }
      ]
    },
    options: { plugins: { legend: { labels: { color: '#070606ff'  } } }, scales: {
      x: { ticks: { color: '#e38e39ff', maxTicksLimit: 12 }, grid: { color: 'rgba(255,255,255,.08)' } },
      y: { ticks: { color: '#3521e8ff' }, grid: { color: 'rgba(255,255,255,.08)' } }
    }}
  });
})();
</script>

</body>
</html>

