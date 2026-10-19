<?php
// create_admin.php
require_once 'db.php';

$phone = '0792841159';
$password = 'adminpassword';
$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE phone = ?");
    $stmt->execute([$phone]);

    $stmt = $pdo->prepare("INSERT INTO users (full_name, phone, password, balance, is_admin) VALUES (?, ?, ?, 0.00, 1)");
    $stmt->execute(['NOVATOLO Admin', $phone, $hash]);

    echo "<div style='font-family:sans-serif; text-align:center; padding:40px;'>";
    echo "<h1 style='color:#10b981;'>NOVATOLO Admin Successfully Created!</h1>";
    echo "<p><strong>Phone:</strong> 0792841159</p>";
    echo "<p><strong>Password:</strong> adminpassword</p>";
    echo "<br><a href='login.php' style='background:#10b981; color:#fff; padding:12px 24px; text-decoration:none; border-radius:6px; font-weight:bold;'>Go to Login Now</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>