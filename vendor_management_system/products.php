<?php include 'config.php'; ?>
<?php
$msg = '';

// ── ADD PRODUCT ──────────────────────────────────────────────────────
if (isset($_POST['add_product'])) {
    $cat = $_POST['category'];
    if ($cat === 'Other' && !empty($_POST['custom_category'])) {
        $cat = $_POST['custom_category'];
    }

    $sql  = "INSERT INTO VMS_PRODUCTS (PRODUCT_ID, PRODUCT_NAME, CATEGORY, PRICE)
             VALUES (VMS_PRODUCT_SEQ.NEXTVAL, :pname, :cat, :price)";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':pname', $_POST['product_name']);
    oci_bind_by_name($stmt, ':cat',   $cat);
    oci_bind_by_name($stmt, ':price', $_POST['price']);

    if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
        $msg = ['type'=>'success','text'=>'Product added successfully!'];
    } else {
        $e = oci_error($stmt);
        $msg = ['type'=>'danger','text'=>$e['message']];
    }
    oci_free_statement($stmt);
}

// ── EDIT PRODUCT ─────────────────────────────────────────────────────
if (isset($_POST['edit_product'])) {
    $cat = $_POST['category'];
    if ($cat === 'Other' && !empty($_POST['custom_category'])) {
        $cat = $_POST['custom_category'];
    }

    $sql  = "UPDATE VMS_PRODUCTS SET PRODUCT_NAME=:pname, CATEGORY=:cat, PRICE=:price WHERE PRODUCT_ID=:pid";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':pid',   $_POST['product_id']);
    oci_bind_by_name($stmt, ':pname', $_POST['product_name']);
    oci_bind_by_name($stmt, ':cat',   $cat);
    oci_bind_by_name($stmt, ':price', $_POST['price']);

    if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
        $msg = ['type'=>'success','text'=>'Product information updated!'];
    } else {
        $e = oci_error($stmt);
        $msg = ['type'=>'danger','text'=>$e['message']];
    }
    oci_free_statement($stmt);
}

// ── DELETE PRODUCT ───────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    if (!has_role('Admin')) {
        $msg = ['type'=>'danger','text'=>'Access Denied. Only Admin can delete products.'];
    } else {
        $sql  = "DELETE FROM VMS_PRODUCTS WHERE PRODUCT_ID=:pid";
        $stmt = oci_parse($conn, $sql);
        $pid  = (int)$_GET['delete'];
        oci_bind_by_name($stmt, ':pid', $pid);

        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            header("Location: products.php?deleted=1");
            exit;
        } else {
            $e = oci_error($stmt);
            $msg = ['type'=>'danger','text'=>'Cannot delete — product is used in orders or inventory.'];
        }
        oci_free_statement($stmt);
    }
}

if (isset($_GET['deleted'])) {
    $msg = ['type'=>'success','text'=>'Product removed from catalog.'];
}

// ── FETCH DATA ───────────────────────────────────────────────────────
$search = $_GET['search'] ?? '';
$filter_cat = $_GET['category'] ?? '';

$where = "WHERE 1=1";
if ($search) $where .= " AND UPPER(PRODUCT_NAME) LIKE UPPER('%'||:s||'%')";
if ($filter_cat) $where .= " AND CATEGORY = :c";

$sql_p = "SELECT * FROM VMS_PRODUCTS $where ORDER BY CATEGORY, PRODUCT_NAME";
$stmt = oci_parse($conn, $sql_p);
if ($search) oci_bind_by_name($stmt, ':s', $search);
if ($filter_cat) oci_bind_by_name($stmt, ':c', $filter_cat);
oci_execute($stmt);
$products = [];
while($r = oci_fetch_assoc($stmt)) $products[] = $r;

$cats = db_fetch_all($conn, "SELECT DISTINCT CATEGORY FROM VMS_PRODUCTS ORDER BY CATEGORY");

// Edit Fetch
$edit = null;
if (isset($_GET['edit'])) {
    $rows = db_fetch_all($conn, "SELECT * FROM VMS_PRODUCTS WHERE PRODUCT_ID=:p", [':p'=>(int)$_GET['edit']]);
    $edit = $rows[0] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VMS — Product Catalog</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .cat-pill { padding: 6px 12px; background: var(--surface2); border: 1px solid var(--border); border-radius: 20px; font-size: 11px; color: var(--muted); text-decoration: none; transition: 0.2s; }
    .cat-pill.active { background: var(--accent); color: white; border-color: var(--accent); }
    .price-tag { font-weight: 700; color: var(--accent3); }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'nav.php'; ?>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="page-title">Product <span>Catalog</span></h1>
        <p class="page-sub">Global repository of all procurement items and SKU data.</p>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
    <?php endif; ?>

    <div class="page-actions">
      <div style="display: flex; gap: 12px; align-items: center;">
        <form method="GET" style="display: flex; gap: 8px;">
          <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>" style="width: 250px;">
          <button type="submit" class="btn btn-primary">Search</button>
        </form>
        <div style="display: flex; gap: 8px; margin-left: 10px;">
          <a href="products.php" class="cat-pill <?= !$filter_cat ? 'active' : '' ?>">All</a>
          <?php foreach ($cats as $c): ?>
            <a href="products.php?category=<?= urlencode($c['CATEGORY']) ?>" class="cat-pill <?= $filter_cat == $c['CATEGORY'] ? 'active' : '' ?>">
              <?= htmlspecialchars($c['CATEGORY']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="btn btn-primary" onclick="openModal('addProdModal')">+ Add New Item</button>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th><th>Product Name</th><th>Category</th><th>Standard Price</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $p): ?>
            <tr>
              <td><span class="badge badge-purple">#<?= $p['PRODUCT_ID'] ?></span></td>
              <td><strong><?= htmlspecialchars($p['PRODUCT_NAME']) ?></strong></td>
              <td><span class="badge badge-yellow" style="text-transform: uppercase; font-size: 9px;"><?= htmlspecialchars($p['CATEGORY']) ?></span></td>
              <td><span class="price-tag">PKR <?= number_format((float)$p['PRICE'], 2) ?></span></td>
              <td>
                <a href="products.php?edit=<?= $p['PRODUCT_ID'] ?>" class="btn btn-sm btn-secondary" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Edit</a>
                <?php if (has_role('Admin')): ?>
                  <a href="products.php?delete=<?= $p['PRODUCT_ID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete item?')">Delete</a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?><tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--muted);">No items found in catalog.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ADD MODAL -->
    <div id="addProdModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin:0; font-size: 20px;">Register New SKU</h2>
          <span class="modal-close" onclick="closeModal('addProdModal')">&times;</span>
        </div>
        <form method="POST">
          <div class="form-grid">
            <div class="form-group" style="grid-column: span 2;">
              <label>Product Name</label>
              <input type="text" name="product_name" required placeholder="e.g. Dell Latitude 5420">
            </div>
            <div class="form-group">
              <label>Category</label>
              <select name="category" required onchange="toggleCustomCat(this, 'custom_cat_add')">
                <option value="">— Select Category —</option>
                <option value="Electronics">Electronics</option>
                <option value="Office Supplies">Office Supplies</option>
                <option value="Furniture">Furniture</option>
                <option value="Maintenance">Maintenance</option>
                <option value="Services">Services</option>
                <option value="Raw Materials">Raw Materials</option>
                <option value="Other">Other / Custom...</option>
              </select>
              <input type="text" name="custom_category" id="custom_cat_add" placeholder="Enter custom category" style="display:none; margin-top:8px;">
            </div>
            <div class="form-group">
              <label>Standard Unit Price (PKR)</label>
              <input type="number" step="0.01" name="price" required placeholder="0.00">
            </div>
          </div>
          <div style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn" onclick="closeModal('addProdModal')" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Cancel</button>
            <button type="submit" name="add_product" class="btn btn-primary">Add to Catalog</button>
          </div>
        </form>
      </div>
    </div>

    <!-- EDIT MODAL -->
    <?php if ($edit): ?>
    <div id="editProdModal" class="modal active">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin:0; font-size: 20px;">Edit Product #<?= $edit['PRODUCT_ID'] ?></h2>
          <span class="modal-close" onclick="window.location.href='products.php'">&times;</span>
        </div>
        <form method="POST">
          <input type="hidden" name="product_id" value="<?= $edit['PRODUCT_ID'] ?>">
          <div class="form-grid">
            <div class="form-group" style="grid-column: span 2;">
              <label>Product Name</label>
              <input type="text" name="product_name" required value="<?= htmlspecialchars($edit['PRODUCT_NAME']) ?>">
            </div>
            <div class="form-group">
              <label>Category</label>
              <select name="category" required onchange="toggleCustomCat(this, 'custom_cat_edit')">
                <?php 
                $preset_cats = ['Electronics', 'Office Supplies', 'Furniture', 'Maintenance', 'Services', 'Raw Materials'];
                $is_custom = !in_array($edit['CATEGORY'], $preset_cats);
                foreach ($preset_cats as $cat): ?>
                  <option value="<?= $cat ?>" <?= $edit['CATEGORY'] == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                <?php endforeach; ?>
                <option value="Other" <?= $is_custom ? 'selected' : '' ?>>Other / Custom...</option>
              </select>
              <input type="text" name="custom_category" id="custom_cat_edit" value="<?= $is_custom ? htmlspecialchars($edit['CATEGORY']) : '' ?>" placeholder="Enter custom category" style="<?= $is_custom ? '' : 'display:none;' ?> margin-top:8px;">
            </div>
            <div class="form-group">
              <label>Standard Unit Price (PKR)</label>
              <input type="number" step="0.01" name="price" required value="<?= $edit['PRICE'] ?>">
            </div>
          </div>
          <div style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn" onclick="window.location.href='products.php'" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Cancel</button>
            <button type="submit" name="edit_product" class="btn btn-primary">Update Product</button>
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

function toggleCustomCat(select, inputId) {
    const input = document.getElementById(inputId);
    if (select.value === 'Other') {
        input.style.display = 'block';
        input.required = true;
        input.focus();
    } else {
        input.style.display = 'none';
        input.required = false;
    }
}

window.onclick = function(e) { if(e.target.classList.contains('modal')) { e.target.classList.remove('active'); if(e.target.id === 'editProdModal') window.location.href='products.php'; } }
</script>

<?php oci_close($conn); ?>
</body>
</html>