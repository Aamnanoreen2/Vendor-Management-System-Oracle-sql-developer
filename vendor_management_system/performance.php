<?php include 'config.php'; ?>
<?php
$msg = '';

// ── ADD PERFORMANCE ──────────────────────────────────────────────────
if (isset($_POST['add_performance'])) {
    $rating = (float)$_POST['rating'];
    $sql = "INSERT INTO VMS_VENDORPERFORMANCE (PERFORMANCE_ID, VENDOR_ID, ORDER_ID, RATING, REVIEW_COMMENTS, REVIEW_DATE)
            VALUES (VMS_PERFORMANCE_SEQ.NEXTVAL, :vid, :oid, :rating, :comments, TO_DATE(:rdate, 'YYYY-MM-DD'))";
    $stmt = oci_parse($conn, $sql);
    
    $oid = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    oci_bind_by_name($stmt, ':vid',      $_POST['vendor_id']);
    oci_bind_by_name($stmt, ':oid',      $oid, -1, SQLT_INT);
    oci_bind_by_name($stmt, ':rating',   $rating);
    oci_bind_by_name($stmt, ':comments', $_POST['review_comments']);
    oci_bind_by_name($stmt, ':rdate',    $_POST['review_date']);

    if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
        $msg = ['type' => 'success', 'text' => 'Review submitted successfully!'];
    } else {
        $e = oci_error($stmt);
        $msg = ['type' => 'danger', 'text' => $e['message']];
    }
    oci_free_statement($stmt);
}

// ── EDIT PERFORMANCE ─────────────────────────────────────────────────
if (isset($_POST['edit_performance'])) {
    $sql = "UPDATE VMS_VENDORPERFORMANCE SET VENDOR_ID=:vid, ORDER_ID=:oid, RATING=:rating, REVIEW_COMMENTS=:comments, REVIEW_DATE=TO_DATE(:rdate, 'YYYY-MM-DD') WHERE PERFORMANCE_ID=:pid";
    $stmt = oci_parse($conn, $sql);
    
    $oid = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    oci_bind_by_name($stmt, ':pid',      $_POST['performance_id']);
    oci_bind_by_name($stmt, ':vid',      $_POST['vendor_id']);
    oci_bind_by_name($stmt, ':oid',      $oid, -1, SQLT_INT);
    oci_bind_by_name($stmt, ':rating',   $_POST['rating']);
    oci_bind_by_name($stmt, ':comments', $_POST['review_comments']);
    oci_bind_by_name($stmt, ':rdate',    $_POST['review_date']);

    if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
        $msg = ['type' => 'success', 'text' => 'Review updated!'];
    } else {
        $e = oci_error($stmt);
        $msg = ['type' => 'danger', 'text' => $e['message']];
    }
    oci_free_statement($stmt);
}

// ── DELETE ───────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    if (!has_role(['Admin', 'Manager'])) {
        $msg = ['type'=>'danger','text'=>'Access Denied. Only Manager or Admin can delete reviews.'];
    } else {
        $sql = "DELETE FROM VMS_VENDORPERFORMANCE WHERE PERFORMANCE_ID = :pid";
        $stmt = oci_parse($conn, $sql);
        $pid = (int)$_GET['delete'];
        oci_bind_by_name($stmt, ':pid', $pid);
        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            header("Location: performance.php?deleted=1");
            exit;
        }
    }
}
if (isset($_GET['deleted'])) $msg = ['type'=>'success','text'=>'Review deleted.'];

// ── FETCH DATA ───────────────────────────────────────────────────────
$search = $_GET['search'] ?? '';
$where = $search ? "WHERE UPPER(v.VENDOR_NAME) LIKE UPPER('%'||:s||'%') OR UPPER(p.REVIEW_COMMENTS) LIKE UPPER('%'||:s||'%')" : "";

$sql_all = "SELECT p.*, v.VENDOR_NAME, TO_CHAR(p.REVIEW_DATE, 'DD-Mon-YYYY') as RDATE_F 
            FROM VMS_VENDORPERFORMANCE p 
            JOIN VMS_VENDORS v ON p.VENDOR_ID = v.VENDOR_ID 
            $where ORDER BY p.REVIEW_DATE DESC";
$stmt = oci_parse($conn, $sql_all);
if ($search) oci_bind_by_name($stmt, ':s', $search);
oci_execute($stmt);
$records = [];
while($r = oci_fetch_assoc($stmt)) $records[] = $r;

// Dropdowns
$vendors = db_fetch_all($conn, "SELECT VENDOR_ID, VENDOR_NAME FROM VMS_VENDORS ORDER BY VENDOR_NAME");
$orders = db_fetch_all($conn, "SELECT o.ORDER_ID, v.VENDOR_NAME FROM VMS_ORDERS o JOIN VMS_VENDORS v ON o.VENDOR_ID = v.VENDOR_ID ORDER BY o.ORDER_ID DESC");

// Edit Fetch
$edit = null;
if (isset($_GET['edit'])) {
    $rows = db_fetch_all($conn, "SELECT p.*, TO_CHAR(p.REVIEW_DATE, 'YYYY-MM-DD') as RDATE_RAW FROM VMS_VENDORPERFORMANCE p WHERE PERFORMANCE_ID=:pid", [':pid'=>(int)$_GET['edit']]);
    $edit = $rows[0] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VMS — Performance Metrics</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .star-rating { color: var(--warning); font-size: 14px; }
    .review-card { border-left: 4px solid var(--border); }
    .review-card.good { border-left-color: var(--accent3); }
    .review-card.bad { border-left-color: var(--danger); }
    .meta-line { font-size: 11px; color: var(--muted); margin-bottom: 8px; }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'nav.php'; ?>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="page-title">Performance <span>Scorecards</span></h1>
        <p class="page-sub">Evaluate vendor service quality and historical fulfillment ratings.</p>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; margin-bottom: 24px;">
      <form method="GET" style="display: flex; gap: 8px;">
        <input type="text" name="search" placeholder="Search vendor or comment..." value="<?= htmlspecialchars($search) ?>" style="width: 250px;">
        <button type="submit" class="btn btn-primary">Search</button>
      </form>
      <button class="btn btn-primary" onclick="openModal('addRevModal')">+ New Performance Review</button>
    </div>

    <div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
      <?php foreach ($records as $r): 
        $isGood = (float)$r['RATING'] >= 4;
        $isBad = (float)$r['RATING'] <= 2;
      ?>
      <div class="card review-card <?= $isGood?'good':($isBad?'bad':'') ?>">
        <div class="meta-line">
          <span>ID #<?= $r['PERFORMANCE_ID'] ?></span> | <span><?= $r['RDATE_F'] ?></span>
        </div>
        <h3 style="margin: 0 0 10px; font-size: 17px;"><?= htmlspecialchars($r['VENDOR_NAME']) ?></h3>
        
        <div style="margin-bottom: 12px;">
          <span class="star-rating">
            <?php for($i=1; $i<=5; $i++) echo ($r['RATING']>=$i ? '★' : ($r['RATING']>=$i-0.5 ? '◪' : '☆')); ?>
          </span>
          <span style="font-size: 13px; font-weight: 700; margin-left: 6px;"><?= $r['RATING'] ?>/5</span>
        </div>

        <p style="font-size: 13px; color: var(--text); line-height: 1.5; margin-bottom: 15px; font-style: italic;">
          "<?= htmlspecialchars($r['REVIEW_COMMENTS']) ?>"
        </p>

        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); pt: 12px; margin-top: 10px; padding-top: 10px;">
          <span style="font-size: 11px; color: var(--muted);">Order: <?= $r['ORDER_ID'] ? '#'.$r['ORDER_ID'] : 'N/A' ?></span>
          <div style="display: flex; gap: 8px;">
            <a href="performance.php?edit=<?= $r['PERFORMANCE_ID'] ?>" class="btn btn-sm" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Edit</a>
            <?php if (has_role(['Admin', 'Manager'])): ?>
              <a href="performance.php?delete=<?= $r['PERFORMANCE_ID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete review?')">Delete</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($records)): ?><div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--muted);">No performance records available.</div><?php endif; ?>
    </div>

    <!-- ADD MODAL -->
    <div id="addRevModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin:0; font-size: 20px;">Submit Vendor Review</h2>
          <span class="modal-close" onclick="closeModal('addRevModal')">&times;</span>
        </div>
        <form method="POST">
          <div class="form-grid">
            <div class="form-group">
              <label>Vendor</label>
              <select name="vendor_id" required>
                <?php foreach ($vendors as $v): ?><option value="<?= $v['VENDOR_ID'] ?>"><?= htmlspecialchars($v['VENDOR_NAME']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Linked Order (Optional)</label>
              <select name="order_id">
                <option value="">-- No Order --</option>
                <?php foreach ($orders as $o): ?><option value="<?= $o['ORDER_ID'] ?>">Order #<?= $o['ORDER_ID'] ?> (<?= htmlspecialchars($o['VENDOR_NAME']) ?>)</option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Rating (1-5)</label>
              <input type="number" name="rating" step="0.5" min="1" max="5" value="5" required>
            </div>
            <div class="form-group">
              <label>Review Date</label>
              <input type="date" name="review_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group" style="grid-column: span 2;">
              <label>Performance Comments</label>
              <textarea name="review_comments" rows="3" required placeholder="Describe vendor performance..."></textarea>
            </div>
          </div>
          <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn" onclick="closeModal('addRevModal')" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Cancel</button>
            <button type="submit" name="add_performance" class="btn btn-primary">Submit Scorecard</button>
          </div>
        </form>
      </div>
    </div>

    <!-- EDIT MODAL -->
    <?php if ($edit): ?>
    <div id="editRevModal" class="modal active">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin:0; font-size: 20px;">Edit Scorecard #<?= $edit['PERFORMANCE_ID'] ?></h2>
          <span class="modal-close" onclick="window.location.href='performance.php'">&times;</span>
        </div>
        <form method="POST">
          <input type="hidden" name="performance_id" value="<?= $edit['PERFORMANCE_ID'] ?>">
          <div class="form-grid">
            <div class="form-group">
              <label>Vendor</label>
              <select name="vendor_id" required>
                <?php foreach ($vendors as $v): ?>
                  <option value="<?= $v['VENDOR_ID'] ?>" <?= $edit['VENDOR_ID']==$v['VENDOR_ID']?'selected':'' ?>><?= htmlspecialchars($v['VENDOR_NAME']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Linked Order</label>
              <select name="order_id">
                <option value="">-- No Order --</option>
                <?php foreach ($orders as $o): ?>
                  <option value="<?= $o['ORDER_ID'] ?>" <?= $edit['ORDER_ID']==$o['ORDER_ID']?'selected':'' ?>>Order #<?= $o['ORDER_ID'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Rating (1-5)</label>
              <input type="number" name="rating" step="0.5" min="1" max="5" value="<?= $edit['RATING'] ?>" required>
            </div>
            <div class="form-group">
              <label>Review Date</label>
              <input type="date" name="review_date" value="<?= $edit['RDATE_RAW'] ?>" required>
            </div>
            <div class="form-group" style="grid-column: span 2;">
              <label>Performance Comments</label>
              <textarea name="review_comments" rows="3" required><?= htmlspecialchars($edit['REVIEW_COMMENTS']) ?></textarea>
            </div>
          </div>
          <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn" onclick="window.location.href='performance.php'" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Cancel</button>
            <button type="submit" name="edit_performance" class="btn btn-primary">Update Scorecard</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
window.onclick = function(e) { if(e.target.classList.contains('modal')) { e.target.classList.remove('active'); if(e.target.id === 'editRevModal') window.location.href='performance.php'; } }
</script>

<?php oci_close($conn); ?>
</body>
</html>
