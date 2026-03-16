<?php
require_once dirname(__FILE__) . '/../includes/helpers.php';
requireLogin();
$admin  = currentAdmin();
$pdo    = db();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── GENERATE INVOICE NUMBER ───────────────────────────────
function genInvoiceNum(PDO $pdo): string {
    $year  = date('Y');
    $count = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE YEAR(created_at)=$year")->fetchColumn() + 1;
    return sprintf('INV-%s-%04d', $year, $count);
}

// ── MARK PAID ─────────────────────────────────────────────
if ($action === 'pay' && $id) {
    $method = $_GET['method'] ?? 'Cash';
    $pdo->prepare("UPDATE invoices SET status='Paid', amount_paid=total, balance=0,
                   payment_method=?, paid_at=NOW() WHERE id=?")
        ->execute([$method, $id]);
    logActivity('Marked invoice as paid', "ID:$id");
    flash('main', 'Invoice marked as paid.', 'success');
    header('Location: /panacea/admin/invoices.php?action=view&id=' . $id); exit;
}

// ── CANCEL ────────────────────────────────────────────────
if ($action === 'cancel' && $id) {
    $pdo->prepare("UPDATE invoices SET status='Cancelled' WHERE id=?")->execute([$id]);
    flash('main', 'Invoice cancelled.', 'success');
    header('Location: /panacea/admin/invoices.php'); exit;
}

// ── CREATE INVOICE ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    verifyCsrf();
    $patientId    = (int)($_POST['patient_id']    ?? 0);
    $appointmentId= ($_POST['appointment_id'] ?? '') ?: null;
    $taxPercent   = (float)($_POST['tax_percent']  ?? 0);
    $discount     = (float)($_POST['discount']     ?? 0);
    $payMethod    = $_POST['payment_method']        ?? 'Cash';
    $payStatus    = $_POST['pay_status']            ?? 'Unpaid';
    $notes        = trim($_POST['notes']            ?? '');

    $serviceIds   = $_POST['service_id']    ?? [];
    $quantities   = $_POST['quantity']      ?? [];
    $unitPrices   = $_POST['unit_price']    ?? [];
    $serviceNames = $_POST['service_name']  ?? [];
    $categories   = $_POST['service_cat']   ?? [];

    if (!$patientId || empty($serviceIds)) {
        flash('main','Please select a patient and at least one service.','error');
        header('Location: /panacea/admin/invoices.php?action=add'); exit;
    }

    // Calculate totals
    $subtotal = 0;
    foreach ($serviceIds as $i => $sid) {
        $qty   = max(1, (int)($quantities[$i] ?? 1));
        $price = (float)($unitPrices[$i] ?? 0);
        $subtotal += $qty * $price;
    }
    $taxAmount = round($subtotal * ($taxPercent / 100), 2);
    $total     = $subtotal + $taxAmount - $discount;
    $amtPaid   = $payStatus === 'Paid' ? $total : 0;
    $balance   = $total - $amtPaid;
    $paidAt    = $payStatus === 'Paid' ? date('Y-m-d H:i:s') : null;

    $invNum = genInvoiceNum($pdo);
    $pdo->prepare('INSERT INTO invoices
        (invoice_number,patient_id,appointment_id,subtotal,tax_percent,tax_amount,
         discount,total,amount_paid,balance,status,payment_method,notes,created_by,paid_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$invNum,$patientId,$appointmentId,$subtotal,$taxPercent,$taxAmount,
                   $discount,$total,$amtPaid,$balance,$payStatus,$payMethod,$notes,
                   $admin['id'],$paidAt]);

    $invId = (int)$pdo->lastInsertId();

    // Insert items
    foreach ($serviceIds as $i => $sid) {
        $qty      = max(1, (int)($quantities[$i] ?? 1));
        $price    = (float)($unitPrices[$i] ?? 0);
        $svcName  = trim($serviceNames[$i] ?? '');
        $svcCat   = trim($categories[$i]   ?? '');
        $total_i  = $qty * $price;
        $pdo->prepare('INSERT INTO invoice_items
            (invoice_id,service_id,service_name,category,quantity,unit_price,total_price)
            VALUES (?,?,?,?,?,?,?)')
            ->execute([$invId, $sid ?: null, $svcName, $svcCat, $qty, $price, $total_i]);
    }

    logActivity('Created invoice', $invNum);
    flash('main', 'Invoice ' . $invNum . ' created successfully.', 'success');
    header('Location: /panacea/admin/invoices.php?action=view&id=' . $invId); exit;
}

// ── VIEW INVOICE ──────────────────────────────────────────
if ($action === 'view' && $id) {
    $inv = $pdo->prepare('SELECT i.*,p.full_name AS patient_name,p.patient_id AS pid,
                          p.phone,p.email,a.ref_number AS appt_ref
                          FROM invoices i
                          LEFT JOIN patients p ON i.patient_id=p.id
                          LEFT JOIN appointments a ON i.appointment_id=a.id
                          WHERE i.id=?');
    $inv->execute([$id]); $inv = $inv->fetch();
    if (!$inv) { flash('main','Invoice not found.','error'); header('Location: /panacea/admin/invoices.php'); exit; }

    $items = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id=?');
    $items->execute([$id]); $items = $items->fetchAll();

    $pageTitle = 'Invoice ' . $inv['invoice_number'];
    require_once dirname(__FILE__) . '/../includes/layout_header.php';

    $statusColor = ['Unpaid'=>'#c0162c','Paid'=>'#3aaa8c','Partial'=>'#d08000','Cancelled'=>'#7a8da8'];
    $sc = $statusColor[$inv['status']] ?? '#7a8da8';
    ?>

    <!-- Action Buttons -->
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
      <a href="/panacea/admin/invoices.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
      <?php if ($inv['status'] === 'Unpaid'): ?>
        <div class="dropdown">
          <button class="btn btn-sm btn-success dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-check-circle me-1"></i>Mark as Paid
          </button>
          <ul class="dropdown-menu">
            <?php foreach (['Cash','CBE Birr','Telebirr','Awash Bank','Bank Transfer','Insurance'] as $m): ?>
              <li><a class="dropdown-item" style="font-size:.85rem"
                     href="?action=pay&id=<?= $id ?>&method=<?= urlencode($m) ?>">
                <?= $m ?>
              </a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <a href="?action=cancel&id=<?= $id ?>"
           class="btn btn-sm btn-outline-danger"
           data-confirm="Cancel this invoice?">
          <i class="bi bi-x-circle me-1"></i>Cancel
        </a>
      <?php endif; ?>
      <button onclick="window.print()" class="btn btn-sm btn-outline-primary ms-auto">
        <i class="bi bi-printer me-1"></i>Print Invoice
      </button>
    </div>

    <!-- Invoice Card -->
    <div class="data-card" id="invoicePrint">
      <!-- Header -->
      <div style="padding:32px 36px;border-bottom:1px solid var(--border)">
        <div class="row align-items-center">
          <div class="col-md-6">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
              <div style="width:48px;height:48px;background:linear-gradient(135deg,var(--blue-mid),var(--green-soft,#3aaa8c));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#fff">
                <i class="bi bi-hospital"></i>
              </div>
              <div>
                <strong style="font-family:'Playfair Display',serif;font-size:1.3rem;color:var(--blue-deep)">Panacea Hospital</strong>
                <div style="font-size:.75rem;color:var(--muted)">Hawassa, Sidama Region, Ethiopia</div>
              </div>
            </div>
            <div style="font-size:.8rem;color:var(--muted)">
              <i class="bi bi-telephone me-1"></i>+251 917 000 000 ·
              <i class="bi bi-envelope ms-2 me-1"></i>info@panaceahospital.et
            </div>
          </div>
          <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <div style="font-size:1.8rem;font-weight:700;font-family:'Playfair Display',serif;color:var(--blue-deep)">
              INVOICE
            </div>
            <div style="font-size:1rem;font-weight:600;color:var(--blue-mid)"><?= htmlspecialchars($inv['invoice_number']) ?></div>
            <div style="margin-top:8px">
              <span style="display:inline-block;background:<?= $sc ?>;color:#fff;padding:5px 16px;border-radius:20px;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em">
                <?= $inv['status'] ?>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Patient & Invoice Info -->
      <div style="padding:24px 36px;border-bottom:1px solid var(--border)">
        <div class="row g-4">
          <div class="col-md-6">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px">Bill To</div>
            <div style="font-weight:600;font-size:1rem;color:var(--blue-deep)"><?= htmlspecialchars($inv['patient_name']) ?></div>
            <div style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($inv['pid']) ?></div>
            <div style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($inv['phone'] ?? '') ?></div>
            <div style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($inv['email'] ?? '') ?></div>
          </div>
          <div class="col-md-6">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px">Invoice Details</div>
            <table style="font-size:.85rem;width:100%">
              <tr><td style="color:var(--muted);padding:3px 0">Invoice No.</td><td style="font-weight:600;text-align:right"><?= htmlspecialchars($inv['invoice_number']) ?></td></tr>
              <tr><td style="color:var(--muted);padding:3px 0">Date Issued</td><td style="font-weight:600;text-align:right"><?= date('d M Y', strtotime($inv['created_at'])) ?></td></tr>
              <?php if ($inv['appt_ref']): ?>
              <tr><td style="color:var(--muted);padding:3px 0">Appointment</td><td style="font-weight:600;text-align:right"><?= htmlspecialchars($inv['appt_ref']) ?></td></tr>
              <?php endif; ?>
              <?php if ($inv['paid_at']): ?>
              <tr><td style="color:var(--muted);padding:3px 0">Paid On</td><td style="font-weight:600;text-align:right;color:#3aaa8c"><?= date('d M Y', strtotime($inv['paid_at'])) ?></td></tr>
              <tr><td style="color:var(--muted);padding:3px 0">Payment</td><td style="font-weight:600;text-align:right"><?= htmlspecialchars($inv['payment_method']) ?></td></tr>
              <?php endif; ?>
            </table>
          </div>
        </div>
      </div>

      <!-- Items Table -->
      <div style="padding:0 36px">
        <table style="width:100%;border-collapse:collapse;font-size:.875rem;margin:24px 0">
          <thead>
            <tr style="background:var(--bg)">
              <th style="padding:12px 14px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);border-bottom:2px solid var(--border)">#</th>
              <th style="padding:12px 14px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);border-bottom:2px solid var(--border)">Service</th>
              <th style="padding:12px 14px;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);border-bottom:2px solid var(--border)">Category</th>
              <th style="padding:12px 14px;text-align:center;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);border-bottom:2px solid var(--border)">Qty</th>
              <th style="padding:12px 14px;text-align:right;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);border-bottom:2px solid var(--border)">Unit Price</th>
              <th style="padding:12px 14px;text-align:right;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);border-bottom:2px solid var(--border)">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $i => $item): ?>
            <tr style="border-bottom:1px solid var(--border)">
              <td style="padding:12px 14px;color:var(--muted)"><?= $i+1 ?></td>
              <td style="padding:12px 14px;font-weight:500"><?= htmlspecialchars($item['service_name']) ?></td>
              <td style="padding:12px 14px"><span style="background:var(--bg);padding:3px 10px;border-radius:12px;font-size:.72rem;color:var(--muted)"><?= htmlspecialchars($item['category'] ?? '—') ?></span></td>
              <td style="padding:12px 14px;text-align:center"><?= $item['quantity'] ?></td>
              <td style="padding:12px 14px;text-align:right"><?= number_format($item['unit_price'], 2) ?> ETB</td>
              <td style="padding:12px 14px;text-align:right;font-weight:600"><?= number_format($item['total_price'], 2) ?> ETB</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Totals -->
      <div style="padding:0 36px 32px;display:flex;justify-content:flex-end">
        <div style="min-width:280px">
          <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:.875rem;border-bottom:1px solid var(--border)">
            <span style="color:var(--muted)">Subtotal</span>
            <span><?= number_format($inv['subtotal'], 2) ?> ETB</span>
          </div>
          <?php if ($inv['tax_percent'] > 0): ?>
          <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:.875rem;border-bottom:1px solid var(--border)">
            <span style="color:var(--muted)">Tax (<?= $inv['tax_percent'] ?>%)</span>
            <span><?= number_format($inv['tax_amount'], 2) ?> ETB</span>
          </div>
          <?php endif; ?>
          <?php if ($inv['discount'] > 0): ?>
          <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:.875rem;border-bottom:1px solid var(--border)">
            <span style="color:#3aaa8c">Discount</span>
            <span style="color:#3aaa8c">- <?= number_format($inv['discount'], 2) ?> ETB</span>
          </div>
          <?php endif; ?>
          <div style="display:flex;justify-content:space-between;padding:14px 0 8px;font-size:1.1rem;font-weight:700;color:var(--blue-deep)">
            <span>TOTAL</span>
            <span><?= number_format($inv['total'], 2) ?> ETB</span>
          </div>
          <?php if ($inv['amount_paid'] > 0): ?>
          <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:.875rem;color:#3aaa8c;font-weight:600">
            <span>Amount Paid</span>
            <span><?= number_format($inv['amount_paid'], 2) ?> ETB</span>
          </div>
          <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:.875rem;font-weight:700;color:<?= $inv['balance'] > 0 ? 'var(--red)' : '#3aaa8c' ?>">
            <span>Balance Due</span>
            <span><?= number_format($inv['balance'], 2) ?> ETB</span>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($inv['notes']): ?>
      <div style="padding:20px 36px;border-top:1px solid var(--border);background:var(--bg)">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:6px">Notes</div>
        <p style="font-size:.85rem;color:var(--gray-dark,#3d4f6b);margin:0"><?= htmlspecialchars($inv['notes']) ?></p>
      </div>
      <?php endif; ?>

      <!-- Footer -->
      <div style="padding:20px 36px;border-top:1px solid var(--border);text-align:center">
        <p style="font-size:.78rem;color:var(--muted);margin:0">
          Thank you for choosing Panacea Hospital · Hawassa, Sidama Region, Ethiopia ·
          +251 917 000 000 · info@panaceahospital.et
        </p>
      </div>
    </div>

    <style>
    @media print {
      .sidebar, .topbar, .main-wrap > .topbar,
      .d-flex.align-items-center.gap-2.mb-4 { display:none!important }
      .main-wrap { margin-left:0!important }
      .content { padding:0!important }
      #invoicePrint { box-shadow:none!important;border:none!important }
    }
    </style>
    <?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; exit;
}

// ── CREATE FORM ───────────────────────────────────────────
if ($action === 'add') {
    $patients = $pdo->query('SELECT id,patient_id,full_name,phone FROM patients ORDER BY full_name')->fetchAll();
    $services = $pdo->query('SELECT * FROM services WHERE is_active=1 ORDER BY category,name')->fetchAll();
    $servicesByCategory = [];
    foreach ($services as $s) $servicesByCategory[$s['category']][] = $s;

    $pageTitle = 'New Invoice';
    require_once dirname(__FILE__) . '/../includes/layout_header.php';
    ?>
    <div class="d-flex align-items-center gap-3 mb-4">
      <a href="/panacea/admin/invoices.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
    </div>
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="form-card">
          <div class="form-card-head"><h4>Create New Invoice</h4></div>
          <div class="form-card-body">
            <form method="POST" id="invoiceForm">
              <input type="hidden" name="csrf_token" value="<?= csrf() ?>"/>

              <!-- Patient -->
              <div class="row g-3 mb-4">
                <div class="col-md-8">
                  <label class="form-label">Patient *</label>
                  <select name="patient_id" class="form-select" required id="patSel" onchange="loadAppts()">
                    <option value="">Select Patient</option>
                    <?php foreach ($patients as $p): ?>
                      <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['patient_id']) ?> — <?= htmlspecialchars($p['full_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Appointment (optional)</label>
                  <select name="appointment_id" class="form-select" id="apptSel">
                    <option value="">— None —</option>
                  </select>
                </div>
              </div>

              <!-- Services -->
              <div class="section-divider" style="padding-top:0;border-top:none;margin-top:0">Services</div>

              <!-- Service picker -->
              <div style="background:var(--bg);border-radius:10px;padding:16px;margin-bottom:16px">
                <label class="form-label">Add Service</label>
                <div class="d-flex gap-2">
                  <select id="svcPicker" class="form-select" style="flex:1">
                    <option value="">— Select a service —</option>
                    <?php foreach ($servicesByCategory as $cat => $svcs): ?>
                      <optgroup label="<?= $cat ?>">
                        <?php foreach ($svcs as $s): ?>
                          <option value="<?= $s['id'] ?>"
                                  data-name="<?= htmlspecialchars($s['name']) ?>"
                                  data-price="<?= $s['price'] ?>"
                                  data-cat="<?= htmlspecialchars($s['category']) ?>">
                            <?= htmlspecialchars($s['name']) ?> — <?= number_format($s['price'],2) ?> ETB
                          </option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endforeach; ?>
                  </select>
                  <button type="button" onclick="addService()" class="btn btn-primary px-3">
                    <i class="bi bi-plus"></i> Add
                  </button>
                </div>
              </div>

              <!-- Items table -->
              <div class="table-responsive mb-4">
                <table class="table" id="itemsTable">
                  <thead>
                    <tr>
                      <th style="font-size:.72rem;color:var(--muted)">Service</th>
                      <th style="font-size:.72rem;color:var(--muted);width:80px">Qty</th>
                      <th style="font-size:.72rem;color:var(--muted);width:130px">Price (ETB)</th>
                      <th style="font-size:.72rem;color:var(--muted);width:120px">Total</th>
                      <th style="width:40px"></th>
                    </tr>
                  </thead>
                  <tbody id="itemsBody">
                    <tr id="emptyRow">
                      <td colspan="5" class="text-center text-muted py-4" style="font-size:.85rem">
                        No services added yet. Select a service above and click Add.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Totals -->
              <div class="row g-3 mb-4">
                <div class="col-md-4">
                  <label class="form-label">Tax (%)</label>
                  <input type="number" name="tax_percent" id="taxInput" class="form-control"
                         value="0" min="0" max="100" step="0.1" onchange="calcTotals()"/>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Discount (ETB)</label>
                  <input type="number" name="discount" id="discountInput" class="form-control"
                         value="0" min="0" step="0.01" onchange="calcTotals()"/>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Total</label>
                  <div style="background:var(--blue-deep);color:#fff;padding:11px 16px;border-radius:9px;font-weight:700;font-size:1.1rem" id="totalDisplay">
                    0.00 ETB
                  </div>
                </div>
              </div>

              <!-- Payment -->
              <div class="section-divider">Payment</div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Payment Method</label>
                  <select name="payment_method" class="form-select">
                    <?php foreach (['Cash','CBE Birr','Telebirr','Awash Bank','Bank Transfer','Insurance','Other'] as $m): ?>
                      <option><?= $m ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Payment Status</label>
                  <select name="pay_status" class="form-select">
                    <option value="Unpaid">Unpaid</option>
                    <option value="Paid">Paid Now</option>
                    <option value="Partial">Partial Payment</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Notes</label>
                  <textarea name="notes" class="form-control" rows="2" placeholder="Any additional notes..."></textarea>
                </div>
                <div class="col-12">
                  <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-receipt me-1"></i>Create Invoice
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Summary sidebar -->
      <div class="col-lg-4">
        <div class="data-card p-4 sticky-top" style="top:90px">
          <h5 style="font-family:'Playfair Display',serif;color:var(--blue-deep);margin-bottom:20px">
            <i class="bi bi-calculator me-2" style="color:var(--blue-bright)"></i>Summary
          </h5>
          <div id="summaryItems" style="font-size:.83rem;color:var(--muted);margin-bottom:16px">
            No services added yet.
          </div>
          <div style="border-top:1px solid var(--border);padding-top:12px">
            <div class="d-flex justify-content-between mb-2" style="font-size:.85rem">
              <span style="color:var(--muted)">Subtotal</span>
              <span id="subtotalDisplay">0.00 ETB</span>
            </div>
            <div class="d-flex justify-content-between mb-2" style="font-size:.85rem">
              <span style="color:var(--muted)">Tax</span>
              <span id="taxDisplay">0.00 ETB</span>
            </div>
            <div class="d-flex justify-content-between mb-2" style="font-size:.85rem">
              <span style="color:#3aaa8c">Discount</span>
              <span id="discDisplay" style="color:#3aaa8c">0.00 ETB</span>
            </div>
            <div class="d-flex justify-content-between" style="font-size:1.1rem;font-weight:700;color:var(--blue-deep);border-top:2px solid var(--border);padding-top:10px;margin-top:4px">
              <span>TOTAL</span>
              <span id="totalSummary">0.00 ETB</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
    let items = [];
    let itemCount = 0;

    function fmt(n) { return parseFloat(n).toFixed(2); }

    function addService() {
      const sel = document.getElementById('svcPicker');
      const opt = sel.options[sel.selectedIndex];
      if (!opt.value) return alert('Please select a service first.');

      const item = {
        idx: itemCount++,
        id: opt.value,
        name: opt.dataset.name,
        price: parseFloat(opt.dataset.price),
        qty: 1,
        cat: opt.dataset.cat
      };
      items.push(item);
      renderItems();
      sel.selectedIndex = 0;
    }

    function removeItem(idx) {
      items = items.filter(i => i.idx !== idx);
      renderItems();
    }

    function renderItems() {
      const tbody = document.getElementById('itemsBody');
      const empty = document.getElementById('emptyRow');

      // Clear hidden inputs
      document.querySelectorAll('.item-input').forEach(e => e.remove());

      if (items.length === 0) {
        tbody.innerHTML = '<tr id="emptyRow"><td colspan="5" class="text-center text-muted py-4" style="font-size:.85rem">No services added yet.</td></tr>';
        calcTotals(); return;
      }

      tbody.innerHTML = items.map(item => `
        <tr>
          <td style="font-size:.85rem;font-weight:500">${item.name}
            <input type="hidden" name="service_id[]"   value="${item.id}"    class="item-input"/>
            <input type="hidden" name="service_name[]" value="${item.name}"  class="item-input"/>
            <input type="hidden" name="service_cat[]"  value="${item.cat}"   class="item-input"/>
          </td>
          <td>
            <input type="number" name="quantity[]" value="${item.qty}" min="1"
                   class="form-control form-control-sm item-input"
                   onchange="updateQty(${item.idx}, this.value)" style="width:65px"/>
          </td>
          <td>
            <input type="number" name="unit_price[]" value="${fmt(item.price)}" min="0" step="0.01"
                   class="form-control form-control-sm item-input"
                   onchange="updatePrice(${item.idx}, this.value)" style="width:110px"/>
          </td>
          <td style="font-weight:600;font-size:.9rem" id="row-total-${item.idx}">
            ${fmt(item.qty * item.price)} ETB
          </td>
          <td>
            <button type="button" onclick="removeItem(${item.idx})"
                    class="btn btn-sm btn-outline-danger" style="padding:2px 8px">
              <i class="bi bi-x"></i>
            </button>
          </td>
        </tr>
      `).join('');
      calcTotals();
    }

    function updateQty(idx, val) {
      const item = items.find(i => i.idx === idx);
      if (item) { item.qty = Math.max(1, parseInt(val)||1); calcTotals(); }
      const el = document.getElementById('row-total-'+idx);
      if (el) el.textContent = fmt(item.qty * item.price) + ' ETB';
    }

    function updatePrice(idx, val) {
      const item = items.find(i => i.idx === idx);
      if (item) { item.price = parseFloat(val)||0; calcTotals(); }
      const el = document.getElementById('row-total-'+idx);
      if (el) el.textContent = fmt(item.qty * item.price) + ' ETB';
    }

    function calcTotals() {
      const subtotal  = items.reduce((s, i) => s + i.qty * i.price, 0);
      const taxPct    = parseFloat(document.getElementById('taxInput').value)||0;
      const discount  = parseFloat(document.getElementById('discountInput').value)||0;
      const tax       = subtotal * (taxPct/100);
      const total     = Math.max(0, subtotal + tax - discount);

      document.getElementById('subtotalDisplay').textContent = fmt(subtotal) + ' ETB';
      document.getElementById('taxDisplay').textContent      = fmt(tax)      + ' ETB';
      document.getElementById('discDisplay').textContent     = fmt(discount) + ' ETB';
      document.getElementById('totalSummary').textContent    = fmt(total)    + ' ETB';
      document.getElementById('totalDisplay').textContent    = fmt(total)    + ' ETB';

      // Summary items
      const si = document.getElementById('summaryItems');
      if (items.length === 0) { si.textContent = 'No services added yet.'; return; }
      si.innerHTML = items.map(i =>
        `<div class="d-flex justify-content-between mb-1">
          <span>${i.name} x${i.qty}</span>
          <span>${fmt(i.qty*i.price)} ETB</span>
        </div>`
      ).join('');
    }

    // Load appointments for selected patient
    function loadAppts() {
      const pid = document.getElementById('patSel').value;
      const sel = document.getElementById('apptSel');
      sel.innerHTML = '<option value="">— None —</option>';
      if (!pid) return;
      fetch('/panacea/admin/invoices.php?ajax=appts&patient_id=' + pid)
        .then(r=>r.json())
        .then(data => {
          data.forEach(a => {
            const opt = document.createElement('option');
            opt.value = a.id;
            opt.textContent = a.ref_number + ' — ' + a.dept_name + ' (' + a.appt_date + ')';
            sel.appendChild(opt);
          });
        });
    }

    document.getElementById('invoiceForm').addEventListener('submit', function(e) {
      if (items.length === 0) { e.preventDefault(); alert('Please add at least one service.'); }
    });
    </script>
    <?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; exit;
}

// ── AJAX: Patient Appointments ────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'appts') {
    $pid   = (int)($_GET['patient_id'] ?? 0);
    $appts = $pdo->prepare("SELECT a.id,a.ref_number,a.appt_date,d.name AS dept_name
                             FROM appointments a LEFT JOIN departments d ON a.department_id=d.id
                             WHERE a.patient_id=? ORDER BY a.appt_date DESC LIMIT 10");
    $appts->execute([$pid]);
    header('Content-Type: application/json');
    echo json_encode($appts->fetchAll());
    exit;
}

// ── LIST ──────────────────────────────────────────────────
$search     = trim($_GET['q']      ?? '');
$filterStat = $_GET['status']      ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 15;
$offset     = ($page - 1) * $perPage;

$where  = []; $params = [];
if ($search) {
    $where[]  = '(i.invoice_number LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%");
}
if ($filterStat) { $where[] = 'i.status=?'; $params[] = $filterStat; }
$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM invoices i LEFT JOIN patients p ON i.patient_id=p.id $whereStr");
$total->execute($params); $total = (int)$total->fetchColumn();

$invoices = $pdo->prepare("
    SELECT i.*, p.full_name AS patient_name, p.patient_id AS pid
    FROM invoices i LEFT JOIN patients p ON i.patient_id=p.id
    $whereStr ORDER BY i.created_at DESC LIMIT $perPage OFFSET $offset
"); $invoices->execute($params); $invoices = $invoices->fetchAll();

// Revenue stats
$todayRev   = $pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='Paid' AND DATE(paid_at)=CURDATE()")->fetchColumn();
$monthRev   = $pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='Paid' AND MONTH(paid_at)=MONTH(NOW()) AND YEAR(paid_at)=YEAR(NOW())")->fetchColumn();
$unpaidCount= $pdo->query("SELECT COUNT(*) FROM invoices WHERE status='Unpaid'")->fetchColumn();
$totalRev   = $pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='Paid'")->fetchColumn();

$pg = paginate($total, $perPage, $page);
$pageTitle = 'Invoices & Billing';
require_once dirname(__FILE__) . '/../includes/layout_header.php';
?>

<!-- Revenue Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-ico" style="background:#edf7f3;color:#3aaa8c"><i class="bi bi-cash-coin"></i></div>
      <div><div class="stat-val" style="font-size:1.4rem"><?= number_format($todayRev) ?></div><div class="stat-lbl">Today (ETB)</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-ico" style="background:#e8f2fb;color:var(--blue-mid)"><i class="bi bi-graph-up-arrow"></i></div>
      <div><div class="stat-val" style="font-size:1.4rem"><?= number_format($monthRev) ?></div><div class="stat-lbl">This Month (ETB)</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-ico" style="background:#fff0f0;color:var(--red)"><i class="bi bi-exclamation-circle"></i></div>
      <div><div class="stat-val"><?= $unpaidCount ?></div><div class="stat-lbl">Unpaid Invoices</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-ico" style="background:#fff8e8;color:#d08000"><i class="bi bi-currency-dollar"></i></div>
      <div><div class="stat-val" style="font-size:1.4rem"><?= number_format($totalRev) ?></div><div class="stat-lbl">Total Revenue (ETB)</div></div>
    </div>
  </div>
</div>

<!-- Filters -->
<form method="GET" class="row g-2 align-items-end mb-4">
  <div class="col-auto flex-grow-1">
    <div class="search-bar">
      <i class="bi bi-search"></i>
      <input type="text" name="q" class="form-control" placeholder="Search invoice, patient…" value="<?= clean($search) ?>"/>
    </div>
  </div>
  <div class="col-auto">
    <select name="status" class="form-select" style="font-size:.875rem;height:38px">
      <option value="">All Statuses</option>
      <?php foreach (['Unpaid','Paid','Partial','Cancelled'] as $s): ?>
        <option <?= $filterStat===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-outline-primary" style="height:38px">Filter</button>
    <a href="/panacea/admin/invoices.php" class="btn btn-outline-secondary" style="height:38px">Clear</a>
  </div>
  <div class="col-auto ms-auto">
    <a href="/panacea/admin/invoices.php?action=add" class="btn btn-primary" style="height:38px">
      <i class="bi bi-receipt me-1"></i>New Invoice
    </a>
  </div>
</form>

<div class="data-card">
  <div class="data-card-head">
    <i class="bi bi-receipt text-primary me-2"></i>
    <h5>All Invoices</h5>
    <span style="font-size:.78rem;color:var(--muted)"><?= number_format($total) ?> total</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr><th>Invoice #</th><th>Patient</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($invoices as $inv): ?>
        <tr>
          <td>
            <a href="?action=view&id=<?= $inv['id'] ?>"
               style="color:var(--blue-mid);text-decoration:none;font-weight:600;font-size:.85rem">
              <?= clean($inv['invoice_number']) ?>
            </a>
          </td>
          <td>
            <div style="font-weight:500;font-size:.875rem"><?= clean($inv['patient_name']) ?></div>
            <div style="font-size:.72rem;color:var(--muted)"><?= clean($inv['pid']) ?></div>
          </td>
          <td style="font-size:.82rem"><?= date('d M Y', strtotime($inv['created_at'])) ?></td>
          <td style="font-weight:700;color:var(--blue-deep)"><?= number_format($inv['total'], 2) ?> <span style="font-size:.72rem;color:var(--muted)">ETB</span></td>
          <td style="color:#3aaa8c;font-weight:600"><?= number_format($inv['amount_paid'], 2) ?> <span style="font-size:.72rem;color:var(--muted)">ETB</span></td>
          <td style="color:<?= $inv['balance'] > 0 ? 'var(--red)' : '#3aaa8c' ?>;font-weight:600">
            <?= number_format($inv['balance'], 2) ?> <span style="font-size:.72rem">ETB</span>
          </td>
          <td><?= statusBadge($inv['status']) ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="?action=view&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
              <?php if ($inv['status'] === 'Unpaid'): ?>
                <a href="?action=pay&id=<?= $inv['id'] ?>&method=Cash"
                   class="btn btn-sm btn-outline-success" title="Mark Paid (Cash)">
                  <i class="bi bi-check-circle"></i>
                </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$invoices): ?>
          <tr><td colspan="8" class="text-center text-muted py-5">No invoices found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/layout_footer.php'; ?>
