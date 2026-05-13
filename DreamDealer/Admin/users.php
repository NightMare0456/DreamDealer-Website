<?php
require __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Admin only
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "admin") {
  header("Location: /DreamDealer/index.php");
  exit;
}

// Update role (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $uid  = (int)($_POST["user_id"] ?? 0);
  $role = $_POST["role"] ?? "";

  $allowed = ["buyer","seller","admin"];

  if ($uid > 0 && in_array($role, $allowed, true)) {
    // Block changing your own admin to buyer by mistake
    if ($uid === (int)$_SESSION["user_id"] && $role !== "admin") {
      $_SESSION["flash_success"] = "⚠️ You cannot remove your own admin role.";
    } else {
      $stmt = $conn->prepare("UPDATE users SET role=? WHERE id=?");
      $stmt->bind_param("si", $role, $uid);
      $stmt->execute();
      $stmt->close();
      $_SESSION["flash_success"] = "✅ User #{$uid} role updated to {$role}.";
    }
  } else {
    $_SESSION["flash_success"] = "❌ Invalid role update.";
  }

  header("Location: /DreamDealer/admin/users.php");
  exit;
}

// Search
$q = trim($_GET["q"] ?? "");

// Fetch users
if ($q !== "") {
  $like = "%".$q."%";
  $stmt = $conn->prepare("
    SELECT id, full_name, email, role, created_at
    FROM users
    WHERE full_name LIKE ? OR email LIKE ?
    ORDER BY id DESC
  ");
  $stmt->bind_param("ss", $like, $like);
} else {
  $stmt = $conn->prepare("
    SELECT id, full_name, email, role, created_at
    FROM users
    ORDER BY id DESC
    LIMIT 200
  ");
}

$stmt->execute();
$res = $stmt->get_result();

include __DIR__ . '/../partials/header.php';
?>

<div class="container" style="padding:30px 0 70px;">
  <h2 style="margin:0;">Admin — Users</h2>
  <p class="muted" style="margin-top:6px;">Search users and change roles.</p>

  <form method="GET" style="margin-top:12px; display:flex; gap:10px;">
    <input
      name="q"
      value="<?php echo htmlspecialchars($q); ?>"
      placeholder="Search by name or email..."
      style="flex:1; padding:12px; border-radius:14px; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.04); color:#fff;"
    />
    <button class="btn" type="submit">Search</button>
    <a class="btn" href="/DreamDealer/admin/users.php" style="text-decoration:none;">Clear</a>
  </form>

  <div class="card" style="margin-top:14px; overflow:auto;">
    <table style="width:100%; border-collapse:collapse; min-width:900px;">
      <thead>
        <tr style="text-align:left; border-bottom:1px solid rgba(255,255,255,.10);">
          <th style="padding:10px;">ID</th>
          <th style="padding:10px;">Name</th>
          <th style="padding:10px;">Email</th>
          <th style="padding:10px;">Role</th>
          <th style="padding:10px;">Created</th>
          <th style="padding:10px;">Action</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($res->num_rows === 0): ?>
          <tr>
            <td colspan="6" class="muted" style="padding:14px;">No users found.</td>
          </tr>
        <?php else: ?>
          <?php while($u = $res->fetch_assoc()): ?>
            <?php
              $uid = (int)$u["id"];
              $currentRole = $u["role"] ?? "buyer";
            ?>
            <tr style="border-bottom:1px solid rgba(255,255,255,.06);">
              <td style="padding:10px;"><?php echo $uid; ?></td>
              <td style="padding:10px;"><b><?php echo htmlspecialchars($u["full_name"]); ?></b></td>
              <td style="padding:10px;"><?php echo htmlspecialchars($u["email"]); ?></td>
              <td style="padding:10px;">
                <span class="muted"><?php echo htmlspecialchars($currentRole); ?></span>
              </td>
              <td style="padding:10px;" class="muted"><?php echo htmlspecialchars($u["created_at"]); ?></td>

              <td style="padding:10px;">
                <form method="POST" style="display:flex; gap:8px; align-items:center;">
                  <input type="hidden" name="user_id" value="<?php echo $uid; ?>">

                  <select name="role"
                          style="padding:10px; border-radius:14px; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.04); color:#fff;">
                    <option value="buyer"  <?php if($currentRole==="buyer") echo "selected"; ?>>buyer</option>
                    <option value="seller" <?php if($currentRole==="seller") echo "selected"; ?>>seller</option>
                    <option value="admin"  <?php if($currentRole==="admin") echo "selected"; ?>>admin</option>
                  </select>

                  <button class="btn" type="submit">Update</button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="muted" style="margin-top:10px;">
    Tip: You can make a user seller/admin from here.
  </div>
</div>

<?php
$stmt->close();
include __DIR__ . '/../partials/footer.php';
?>
