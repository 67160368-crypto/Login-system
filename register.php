<?php

//include config ตรงนี้นะจ๊ะ 
$db_host = '127.0.0.1';
$db_name = 's67160368';
$db_user = 's67160368';
$db_pass = 'vBwzb8s8';

// เปิด session เพื่อใช้ CSRF token และ flash message
session_start();

// สร้าง CSRF token ครั้งแรก
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = "";

// เชื่อมต่อฐานข้อมูล (MySQLi)
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
  die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

// ฟังก์ชันเล็ก ๆ กัน XSS เวลา echo ค่าเดิมกลับฟอร์ม
function e($str){ return htmlspecialchars($str ?? "", ENT_QUOTES, "UTF-8"); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // ตรวจ CSRF token
  if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $errors[] = "CSRF token ไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองอีกครั้ง";
  }

  // รับค่าจากฟอร์ม
  $username  = trim($_POST['username'] ?? "");
  $password  = $_POST['password'] ?? "";
  $email     = trim($_POST['email'] ?? "");
  $full_name = trim($_POST['name'] ?? "");

  // ตรวจความถูกต้องเบื้องต้น
  if ($username === "" || !preg_match('/^[A-Za-z0-9_\.]{3,30}$/', $username)) {
    $errors[] = "กรุณากรอก username 3–30 ตัวอักษร (a-z, A-Z, 0-9, _, .)";
  }
  if (strlen($password) < 8) {
    $errors[] = "รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร";
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "อีเมลไม่ถูกต้อง";
  }
  if ($full_name === "" || mb_strlen($full_name) > 100) {
    $errors[] = "กรุณากรอกชื่อ–นามสกุล (ไม่เกิน 100 ตัวอักษร)";
  }

  // ตรวจซ้ำ username/email
  if (!$errors) {
    $sql = "SELECT 1 FROM users WHERE username = ? OR email = ? LIMIT 1";
    if ($stmt = $mysqli->prepare($sql)) {
      $stmt->bind_param("ss", $username, $email);
      $stmt->execute();
      $stmt->store_result();
      if ($stmt->num_rows > 0) {
        $errors[] = "Username หรือ Email นี้ถูกใช้แล้ว";
      }
      $stmt->close();
    } else {
      $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบข้อมูล (prepare)";
    }
  }

  // บันทึกลงฐานข้อมูล
  if (!$errors) {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)";
    if ($stmt = $mysqli->prepare($sql)) {
      $stmt->bind_param("ssss", $username, $email, $password_hash, $full_name);
      if ($stmt->execute()) {
        $success = "สมัครสมาชิกสำเร็จ! คุณสามารถล็อกอินได้แล้วค่ะ";
        // regenerate CSRF token หลังสำเร็จ
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        // เคลียร์ฟอร์ม
        $username = $email = $full_name = "";
      } else {
        if ($mysqli->errno == 1062) {
          $errors[] = "Username/Email ซ้ำ กรุณาใช้ค่าอื่น";
        } else {
          $errors[] = "บันทึกข้อมูลไม่สำเร็จ: " . e($mysqli->error);
        }
      }
      $stmt->close();
    } else {
      $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล (prepare)";
    }
  }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register</title>
  <style>
    body{font-family:system-ui, sans-serif; background:#f7f7fb; margin:0; padding:0;}
    .container{max-width:480px; margin:40px auto; background:#fff; border-radius:16px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,.06);}
    h1{margin:0 0 16px;}
    .alert{padding:12px 14px; border-radius:12px; margin-bottom:12px; font-size:14px;}
    .alert.error{background:#ffecec; color:#a40000; border:1px solid #ffc9c9;}
    .alert.success{background:#efffed; color:#0a7a28; border:1px solid #c9f5cf;}
    label{display:block; font-size:14px; margin:10px 0 6px;}
    input{width:100%; padding:12px; border-radius:12px; border:1px solid #ddd;}
    button{width:100%; padding:12px; border:none; border-radius:12px; margin-top:14px; background:#3b82f6; color:#fff; font-weight:600; cursor:pointer;}
    button:hover{filter:brightness(.95);}
    .hint{font-size:12px; color:#666;}
    .login-link{display:block; text-align:center; margin-top:16px;}
    .login-link a{color:#3b82f6; text-decoration:none; font-weight:600;}
    .login-link a:hover{text-decoration:underline;}
  </style>
</head>
<body>
  <div class="container">
    <h1>สมัครสมาชิก</h1>

    <?php if ($errors): ?>
      <div class="alert error">
        <?php foreach ($errors as $m) echo "<div>".e($m)."</div>"; ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <label>Username</label>
      <input type="text" name="username" value="<?= e($username ?? "") ?>" required>
      <div class="hint">อนุญาต a-z, A-Z, 0-9, _ และ . (3–30 ตัว)</div>

      <label>Password</label>
      <input type="password" name="password" required>
      <div class="hint">อย่างน้อย 8 ตัวอักษร</div>

      <label>Email</label>
      <input type="email" name="email" value="<?= e($email ?? "") ?>" required>

      <label>ชื่อ–นามสกุล</label>
      <input type="text" name="name" value="<?= e($full_name ?? "") ?>" required>

      <button type="submit">สมัครสมาชิก</button>
    </form>

    <!-- 🔹 เพิ่มปุ่มไปหน้า Login -->
    <div class="login-link">
      <a href="login.php">ไปหน้าเข้าสู่ระบบ</a>
    </div>

  </div>
</body>
</html>
