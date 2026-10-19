<?php
// cron.php - Automated Daily Earnings Processor for NOVATOLO
require_once 'db.php';

$stmt = $pdo->query("SELECT * FROM investments WHERE status = 'active'");
$investments = $stmt->fetchAll();

$today = date('Y-m-d');

foreach ($investments as $inv) {
    if ($inv['last_payout_date'] === $today) {
        continue;
    }

    $new_days = $inv['days_counted'] + 1;
    $new_earned = $inv['earned_so_far'] + $inv['daily_earning'];

    $pdo->beginTransaction();

    if ($new_days >= $inv['duration_days']) {
        $stmt = $pdo->prepare("UPDATE investments SET days_counted = ?, earned_so_far = ?, status = 'completed', last_payout_date = ? WHERE id = ?");
        $stmt->execute([$new_days, $new_earned, $today, $inv['id']]);

        $total_return = $inv['amount'] + $inv['total_profit'];

        $stmt = $pdo->prepare("UPDATE users SET profit_balance = profit_balance + ? WHERE id = ?");
        $stmt->execute([$total_return, $inv['user_id']]);
    } else {
        $stmt = $pdo->prepare("UPDATE investments SET days_counted = ?, earned_so_far = ?, last_payout_date = ? WHERE id = ?");
        $stmt->execute([$new_days, $new_earned, $today, $inv['id']]);
    }

    $pdo->commit();
}

echo "NOVATOLO daily profits calculation completed successfully on " . date('Y-m-d H:i:s');
?>