<?php
// reset_admin.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once 'db.php';

$phone = '0792841159';
$password_raw = 'adminpassword';
$password_hashed = password_hash($password_raw, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE phone = ?");
    $stmt->execute([$phone]);

    $stmt = $pdo->prepare("INSERT INTO users (full_name, phone, password, balance, is_admin) VALUES (?, ?, ?, 0.00, 1)");
    $stmt->execute(['NOVATOLO Admin', $phone, $password_hashed]);

    echo "<h2 style='color:green;'>Success! NOVATOLO Admin Account Reset Complete.</h2>";
    echo "<p><strong>Phone:</strong> 0792841159</p>";
    echo "<p><strong>Password:</strong> adminpassword</p>";
    echo "<a href='login.php'>Click here to Login</a>";

} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Error:</h2> " . $e->getMessage();
}
?>