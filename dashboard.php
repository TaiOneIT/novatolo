<?php
// dashboard.php - Mobile-first NOVATOLO member dashboard
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$message = '';
$error = '';

/* Auto-initialize support messages ledger for Live Chat with media attachment & edit/delete columns */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS support_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        sender_type ENUM('user','admin') NOT NULL DEFAULT 'user',
        message TEXT NOT NULL,
        file_path VARCHAR(255) NULL,
        file_type ENUM('image','audio','none') NOT NULL DEFAULT 'none',
        is_read TINYINT(1) DEFAULT 0,
        is_deleted TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $colCheck = $pdo->query("SHOW COLUMNS FROM support_messages LIKE 'file_path'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE support_messages ADD COLUMN file_path VARCHAR(255) NULL, ADD COLUMN file_type ENUM('image','audio','none') NOT NULL DEFAULT 'none'");
    }
    $colDelCheck = $pdo->query("SHOW COLUMNS FROM support_messages LIKE 'is_deleted'")->fetch();
    if (!$colDelCheck) {
        $pdo->exec("ALTER TABLE support_messages ADD COLUMN is_deleted TINYINT(1) DEFAULT 0, ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP");
    }
} catch (Throwable $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS welcome_bonus_awards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        amount DECIMAL(14,2) NOT NULL DEFAULT 1000.00,
        qualifying_transaction_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS referral_reward_awards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        inviter_id INT NOT NULL,
        invited_user_id INT NOT NULL,
        tier_level INT NOT NULL DEFAULT 1,
        amount DECIMAL(14,2) NOT NULL DEFAULT 800.00,
        qualifying_transaction_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_tier (invited_user_id, tier_level)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// Automatically process ALL active investments that have reached or passed maturity
try {
    $activeInvs = $pdo->prepare("SELECT i.*, p.duration_days 
        FROM investments i 
        JOIN investment_plans p ON i.plan_id = p.id 
        WHERE LOWER(i.status) = 'active'");
    $activeInvs->execute();
    $investmentsToProcess = $activeInvs->fetchAll();

    foreach ($investmentsToProcess as $inv) {
        $duration = max(1, (int)$inv['duration_days']);
        $startDate = !empty($inv['last_payout_date']) ? (string)$inv['last_payout_date'] : (string)$inv['created_at'];
        $startTs = strtotime($startDate . (strlen($startDate) === 10 ? ' 00:00:00' : ''));
        $endTs = $startTs + ($duration * 86400);

        if (time() >= $endTs) {
            $pdo->beginTransaction();
            $payoutAmount = (float)$inv['amount'] + (float)$inv['total_profit'];
            $invUserId = (int)$inv['user_id'];
            
            $updInv = $pdo->prepare("UPDATE investments SET status = 'completed', days_counted = ? WHERE id = ? AND LOWER(status) = 'active'");
            $updInv->execute([$duration, $inv['id']]);

            if ($updInv->rowCount() === 1) {
                $updUser = $pdo->prepare("UPDATE users SET profit_balance = profit_balance + ? WHERE id = ?");
                $updUser->execute([$payoutAmount, $invUserId]);
            }
            $pdo->commit();
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
}

$hasRecipientNameColumn = false;
try {
    $columns = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'recipient_name'")->fetch();
    if (!$columns) {
        try {
            $pdo->exec("ALTER TABLE transactions ADD COLUMN recipient_name VARCHAR(150) NULL");
        } catch (Throwable $e) {}
        $columns = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'recipient_name'")->fetch();
    }
    $hasRecipientNameColumn = (bool)$columns;
} catch (Throwable $e) {}

$withdrawType = 'withdraw';
try {
    $typeColumn = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'type'")->fetch();
    $typeDefinition = strtolower((string)($typeColumn['Type'] ?? ''));
    if (strpos($typeDefinition, "'withdrawal'") !== false && strpos($typeDefinition, "'withdraw'") === false) {
        $withdrawType = 'withdrawal';
    }

    $repair = $pdo->prepare("UPDATE transactions SET type = ? WHERE (type = '' OR type IS NULL) AND COALESCE(phone, '') <> ''");
    $repair->execute([$withdrawType]);
} catch (Throwable $e) {}

function money($amount): string {
    return number_format((float)$amount, 0) . ' RWF';
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

if (empty($user['ref_code'])) {
    $ref_code = 'REF-' . strtoupper(substr(hash('sha256', $user_id . microtime(true)), 0, 8));
    $stmt = $pdo->prepare('UPDATE users SET ref_code = ? WHERE id = ?');
    $stmt->execute([$ref_code, $user_id]);
    $user['ref_code'] = $ref_code;
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$ref_link = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/register.php?ref=' . rawurlencode($user['ref_code']);
$whatsapp_link = 'https://wa.me/447480275490?text=' . rawurlencode('Hello');

/* One-time RWF 1,000 welcome bonus */
try {
    $stmt = $pdo->prepare("SELECT id, amount FROM transactions WHERE user_id = ? AND type = 'deposit' AND status = 'approved' AND amount >= 5000 ORDER BY created_at ASC, id ASC LIMIT 1");
    $stmt->execute([$user_id]);
    $qualifyingDeposit = $stmt->fetch();

    if ($qualifyingDeposit) {
        $stmt = $pdo->prepare('SELECT id FROM welcome_bonus_awards WHERE user_id = ? LIMIT 1');
        $stmt->execute([$user_id]);
        if (!$stmt->fetch()) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id FROM welcome_bonus_awards WHERE user_id = ? FOR UPDATE');
            $stmt->execute([$user_id]);
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare('INSERT INTO welcome_bonus_awards (user_id, amount, qualifying_transaction_id) VALUES (?, 1000, ?)');
                $stmt->execute([$user_id, $qualifyingDeposit['id']]);
                $stmt = $pdo->prepare('UPDATE users SET balance = balance + 1000 WHERE id = ?');
                $stmt->execute([$user_id]);
            }
            $pdo->commit();
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
}

/* Dynamic Tiered Referral Rewards */
try {
    $stmt = $pdo->prepare("SELECT u.id AS invited_user_id, SUM(t.amount) AS total_approved_deposit, MAX(t.id) AS qualifying_transaction_id
        FROM users u
        JOIN transactions t ON t.user_id = u.id
        WHERE u.referred_by = ? AND t.type = 'deposit' AND t.status = 'approved'
        GROUP BY u.id");
    $stmt->execute([$user_id]);
    $referredDeposits = $stmt->fetchAll();

    foreach ($referredDeposits as $ref) {
        $invitedId = (int)$ref['invited_user_id'];
        $totDep = (float)$ref['total_approved_deposit'];
        $qTxId = $ref['qualifying_transaction_id'];

        $tiersToAward = [];
        if ($totDep >= 30000) {
            $tiersToAward = [1 => 800, 2 => 1200, 3 => 2000, 4 => 3000];
        } elseif ($totDep >= 20000) {
            $tiersToAward = [1 => 800, 2 => 1200, 3 => 2000];
        } elseif ($totDep >= 10000) {
            $tiersToAward = [1 => 800, 2 => 1200];
        } elseif ($totDep >= 5000) {
            $tiersToAward = [1 => 800];
        }

        foreach ($tiersToAward as $tierLevel => $rewardAmount) {
            $chk = $pdo->prepare("SELECT id FROM referral_reward_awards WHERE invited_user_id = ? AND tier_level = ? LIMIT 1");
            $chk->execute([$invitedId, $tierLevel]);
            if (!$chk->fetch()) {
                $pdo->beginTransaction();
                $chk = $pdo->prepare("SELECT id FROM referral_reward_awards WHERE invited_user_id = ? AND tier_level = ? FOR UPDATE");
                $chk->execute([$invitedId, $tierLevel]);
                if (!$chk->fetch()) {
                    $ins = $pdo->prepare("INSERT INTO referral_reward_awards (inviter_id, invited_user_id, tier_level, amount, qualifying_transaction_id) VALUES (?, ?, ?, ?, ?)");
                    $ins->execute([$user_id, $invitedId, $tierLevel, $rewardAmount, $qTxId]);
                    $upd = $pdo->prepare("UPDATE users SET profit_balance = profit_balance + ? WHERE id = ?");
                    $upd->execute([$rewardAmount, $user_id]);
                }
                $pdo->commit();
            }
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$tab = $_GET['tab'] ?? 'home';
$allowedTabs = ['home','plans','invests','transactions','invited','chat'];
if (!in_array($tab, $allowedTabs, true)) $tab = 'home';

if ($tab === 'chat') {
    try {
        $updRead = $pdo->prepare("UPDATE support_messages SET is_read = 1 WHERE user_id = ? AND sender_type = 'admin' AND is_read = 0");
        $updRead->execute([$user_id]);
    } catch (Throwable $e) {}
}

$unreadAdminMsgs = 0;
try {
    $uStmt = $pdo->prepare("SELECT COUNT(*) FROM support_messages WHERE user_id = ? AND sender_type = 'admin' AND is_read = 0");
    $uStmt->execute([$user_id]);
    $unreadAdminMsgs = (int)$uStmt->fetchColumn();
} catch (Throwable $e) {}

// Helper function for user chat file and audio uploads
function handle_user_chat_upload($file, $audioBlobData = null) {
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    if (!empty($audioBlobData)) {
        if (preg_match('/^data:audio\/(\w+);base64,/', $audioBlobData, $type)) {
            $data = substr($audioBlobData, strpos($audioBlobData, ',') + 1);
            $data = base64_decode($data);
            if ($data !== false) {
                $filename = uniqid('chat_audio_', true) . '.webm';
                $targetPath = $uploadDir . $filename;
                if (file_put_contents($targetPath, $data)) {
                    return ['path' => $targetPath, 'type' => 'audio'];
                }
            }
        }
    }

    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => null, 'type' => 'none'];
    }
    
    $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedAudioTypes = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm', 'video/webm'];
    
    $fileTypeMime = @mime_content_type($file['tmp_name']) ?: '';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('chat_', true) . '.' . ($ext ?: 'bin');
    $targetPath = $uploadDir . $filename;
    
    if (in_array($fileTypeMime, $allowedImageTypes) || in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['path' => $targetPath, 'type' => 'image'];
        }
    } elseif (in_array($fileTypeMime, $allowedAudioTypes) || strpos($fileTypeMime, 'audio/') === 0 || in_array($ext, ['mp3','wav','ogg','webm','m4a'])) {
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['path' => $targetPath, 'type' => 'audio'];
        }
    }
    return ['path' => null, 'type' => 'none'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // User Message Edit Handler
    if ($action === 'edit_support_msg') {
        $editId = intval($_POST['edit_msg_id'] ?? 0);
        $newText = trim($_POST['edit_message_text'] ?? '');
        if ($editId > 0 && !empty($newText)) {
            $upd = $pdo->prepare("UPDATE support_messages SET message = ? WHERE id = ? AND user_id = ? AND sender_type = 'user'");
            $upd->execute([$newText, $editId, $user_id]);
            header('Location: dashboard.php?tab=chat&msg=chat_edited');
            exit;
        }
    }

    // User Message Delete Handler
    if ($action === 'delete_support_msg') {
        $delId = intval($_POST['delete_msg_id'] ?? 0);
        if ($delId > 0) {
            $del = $pdo->prepare("UPDATE support_messages SET is_deleted = 1, message = '[Message Deleted]' WHERE id = ? AND user_id = ? AND sender_type = 'user'");
            $del->execute([$delId, $user_id]);
            header('Location: dashboard.php?tab=chat&msg=chat_deleted');
            exit;
        }
    }

    // Direct Chat Submission with Photos & Audio support
    if ($action === 'send_support_msg') {
        $chatMsg = trim($_POST['chat_message'] ?? '');
        $audioBlob = $_POST['audio_blob_data'] ?? null;
        
        $uploadResult = handle_user_chat_upload($_FILES['chat_file'] ?? null, $audioBlob);

        if (!empty($chatMsg) || $uploadResult['type'] !== 'none') {
            $insMsg = $pdo->prepare("INSERT INTO support_messages (user_id, sender_type, message, file_path, file_type) VALUES (?, 'user', ?, ?, ?)");
            $insMsg->execute([$user_id, $chatMsg, $uploadResult['path'], $uploadResult['type']]);
            header('Location: dashboard.php?tab=chat&msg=chat_sent');
            exit;
        }
    }

    // Deposit request
    if ($action === 'deposit') {
        $amount = (float)($_POST['amount'] ?? 0);
        $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND type = 'deposit' AND status = 'pending'");
        $pendingStmt->execute([$user_id]);
        $pendingDeposits = (int)$pendingStmt->fetchColumn();

        if ($pendingDeposits >= 2) {
            $error = 'You already have 2 pending deposits. Please wait for approval.';
        } elseif ($amount < 1000) {
            $error = 'Minimum deposit amount is 1,000 RWF.';
        } elseif ($amount > 100000000) {
            $error = 'Please enter a valid deposit amount.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status) VALUES (?, 'deposit', ?, 'pending')");
            $stmt->execute([$user_id, $amount]);
            header('Location: dashboard.php?tab=transactions&msg=deposit_submitted');
            exit;
        }
    }

    // Process Investment Action
    if ($action === 'invest') {
        $plan_id = (int)($_POST['plan_id'] ?? 0);
        
        $planStmt = $pdo->prepare('SELECT * FROM investment_plans WHERE id = ?');
        $planStmt->execute([$plan_id]);
        $selectedPlan = $planStmt->fetch();

        if (!$selectedPlan) {
            $error = 'Invalid investment plan selected.';
        } else {
            $checkFirst3 = $pdo->query('SELECT id FROM investment_plans ORDER BY id ASC LIMIT 3')->fetchAll(PDO::FETCH_COLUMN);
            $isFirstThree = in_array($plan_id, $checkFirst3);

            if ($isFirstThree) {
                $alreadyInvested = $pdo->prepare('SELECT COUNT(*) FROM investments WHERE user_id = ? AND plan_id = ?');
                $alreadyInvested->execute([$user_id, $plan_id]);
                if ((int)$alreadyInvested->fetchColumn() >= 1) {
                    $error = 'You can only invest in this plan 1 time.';
                }
            }

            if (!$error) {
                $investAmount = (float)$selectedPlan['min_amount'];
                $depositBalance = (float)$user['balance'];
                $earningBalance = (float)$user['profit_balance'];
                $combinedBalance = $depositBalance + $earningBalance;

                if ($combinedBalance < $investAmount) {
                    $error = 'Insufficient balance. Your total available balance is lower than ' . money($investAmount) . '.';
                } else {
                    try {
                        $pdo->beginTransaction();
                        
                        if ($depositBalance >= $investAmount) {
                            $deductDeposit = $investAmount;
                            $deductEarning = 0;
                        } else {
                            $deductDeposit = $depositBalance;
                            $deductEarning = $investAmount - $depositBalance;
                        }

                        if ($deductDeposit > 0) {
                            $updDep = $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?');
                            $updDep->execute([$deductDeposit, $user_id, $deductDeposit]);
                        }

                        if ($deductEarning > 0) {
                            $updEarn = $pdo->prepare('UPDATE users SET profit_balance = profit_balance - ? WHERE id = ? AND profit_balance >= ?');
                            $updEarn->execute([$deductEarning, $user_id, $deductEarning]);
                        }

                        $profitRate = (float)$selectedPlan['profit_rate'];
                        $duration = (int)$selectedPlan['duration_days'];
                        $totalProfit = $investAmount * ($profitRate / 100);
                        $dailyEarning = $totalProfit / max(1, $duration);

                        $insInv = $pdo->prepare("INSERT INTO investments (user_id, plan_id, amount, daily_earning, total_profit, duration_days, days_counted, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, 'active', NOW())");
                        $insInv->execute([$user_id, $plan_id, $investAmount, $dailyEarning, $totalProfit, $duration]);

                        $pdo->commit();
                        header('Location: dashboard.php?tab=invests&msg=invest_success');
                        exit;
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $error = 'Failed to create investment. ' . $e->getMessage();
                    }
                }
            }
        }
    }

    // Withdraw request
    if ($action === 'withdraw') {
        $amount = (float)($_POST['amount'] ?? 0);
        $phone = trim($_POST['momo_phone'] ?? '');
        $registeredName = trim($_POST['momo_name'] ?? '');
        $withdrawable = (float)$user['profit_balance'];

        $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND LOWER(type) IN ('withdraw','withdrawal') AND status = 'pending'");
        $pendingStmt->execute([$user_id]);
        $pendingWithdrawals = (int)$pendingStmt->fetchColumn();

        if ($pendingWithdrawals >= 1) {
            $error = 'You already have 1 pending withdrawal request.';
        } elseif ($amount < 500) {
            $error = 'Minimum withdrawal amount is 500 RWF.';
        } elseif ($amount > $withdrawable) {
            $error = 'Insufficient earning balance.';
        } elseif (!preg_match('/^07\d{8}$/', preg_replace('/\s+/', '', $phone))) {
            $error = 'Enter a valid Mobile Money number, for example 0792841159.';
        } elseif ($registeredName === '') {
            $error = 'Please enter the registered name for the receiving number.';
        } else {
            $phone = preg_replace('/\s+/', '', $phone);
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('UPDATE users SET profit_balance = profit_balance - ? WHERE id = ? AND profit_balance >= ?');
                $stmt->execute([$amount, $user_id, $amount]);

                if ($hasRecipientNameColumn) {
                    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, phone, recipient_name, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                    $stmt->execute([$user_id, $withdrawType, $amount, $phone, $registeredName]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, phone, status) VALUES (?, ?, ?, ?, 'pending')");
                    $stmt->execute([$user_id, $withdrawType, $amount, $phone]);
                }
                $pdo->commit();
                header('Location: dashboard.php?tab=transactions&msg=withdraw_submitted');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Withdrawal request could not be submitted.';
            }
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deposit_submitted') $message = 'Deposit submitted and waiting for admin approval.';
    if ($_GET['msg'] === 'withdraw_submitted') $message = 'Withdrawal request submitted. It is now waiting for admin payment.';
    if ($_GET['msg'] === 'invest_success') $message = 'Investment activated successfully using your available account balance.';
    if ($_GET['msg'] === 'chat_sent') $message = 'Message sent to NOVATOLO support!';
    if ($_GET['msg'] === 'chat_edited') $message = 'Message updated successfully!';
    if ($_GET['msg'] === 'chat_deleted') $message = 'Message deleted successfully!';
}

$plan_images = [
    1 => 'florian-media-dock-2898497.jpg',
    2 => 'mahavir-shah-phone-7832257.jpg',
    3 => '16078228-iphone-5051984.jpg',
    4 => 'fredriktheteacher-apple-watch-4028102.jpg',
    5 => 'doc-wood-apple-2362149_1920.jpg',
    6 => 'stocksnap-lifestyle-2569185.jpg',
    7 => 'pexels-apple-1853259.jpg',
    8 => 'pexels-home-office-1867759.jpg',
    9 => 'pexels-laptop-1842297.jpg'
];

$plans = [];
try {
    $plans = $pdo->query('SELECT * FROM investment_plans ORDER BY id ASC LIMIT 9')->fetchAll();
} catch (Throwable $e) { $plans = []; }

$userInvestmentsByPlan = [];
try {
    $invStmt = $pdo->prepare('SELECT plan_id, COUNT(*) as cnt FROM investments WHERE user_id = ? GROUP BY plan_id');
    $invStmt->execute([$user_id]);
    while ($r = $invStmt->fetch()) {
        $userInvestmentsByPlan[$r['plan_id']] = (int)$r['cnt'];
    }
} catch (Throwable $e) {}

$stmt = $pdo->prepare("SELECT i.*, p.name AS plan_name, p.profit_rate
    FROM investments i JOIN investment_plans p ON i.plan_id = p.id
    WHERE i.user_id = ? ORDER BY i.created_at DESC");
$stmt->execute([$user_id]);
$investments = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC, id DESC");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();

$chatMessages = [];
try {
    $cStmt = $pdo->prepare("SELECT * FROM support_messages WHERE user_id = ? ORDER BY created_at ASC");
    $cStmt->execute([$user_id]);
    $chatMessages = $cStmt->fetchAll();
} catch (Throwable $e) {}

$stmt = $pdo->prepare("SELECT u.id, u.full_name, u.phone, u.created_at,
    COALESCE((
        SELECT SUM(t.amount) FROM transactions t WHERE t.user_id = u.id AND t.type = 'deposit' AND t.status = 'approved'
    ), 0) AS total_deposited
    FROM users u WHERE u.referred_by = ? AND u.is_admin = 0 AND COALESCE(u.is_archived,0) = 0 ORDER BY u.created_at DESC");
$stmt->execute([$user_id]);
$invitedMembers = $stmt->fetchAll();

$activeInvited = array_values(array_filter($invitedMembers, fn($m) => (float)$m['total_deposited'] >= 5000));
$inactiveInvited = array_values(array_filter($invitedMembers, fn($m) => (float)$m['total_deposited'] < 5000));

$depositBalance = (float)$user['balance'];
$earningBalance = (float)$user['profit_balance'];
$totalBalance = $depositBalance + $earningBalance;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#071B2D">
<title>Dashboard - NOVATOLO</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
:root{
 --bg:#f5f8fb;--card:#fff;--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;
 --blue:#2563eb;--blue2:#1d4ed8;--blueSoft:#eff6ff;--gold:#f59e0b;--gold2:#d97706;
 --purple:#7c3aed;--red:#ef4444;--dark:#071b2d;
 --shadow:0 10px 30px rgba(15,23,42,.07);--shadow2:0 18px 45px rgba(15,23,42,.10)
}
*{box-sizing:border-box;margin:0;padding:0;font-family:'Plus Jakarta Sans',sans-serif}
html,body{background:var(--bg);color:var(--ink);min-height:100%;overflow-x:hidden}
body{padding-bottom:96px}button,input,textarea{font:inherit}button{cursor:pointer}a{text-decoration:none;color:inherit}
.topbar{height:64px;background:rgba(255,255,255,.96);backdrop-filter:blur(18px);border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 16px;position:sticky;top:0;z-index:90;box-shadow:0 4px 20px rgba(15,23,42,.04)}
.brand{display:flex;align-items:center;gap:9px;font-weight:800;font-size:18px;letter-spacing:-.6px;white-space:nowrap}.brand b{color:var(--blue)}
.brand-mark{width:36px;height:36px;border-radius:11px;background:linear-gradient(135deg,#2563eb,#1d4ed8);display:grid;place-items:center;color:#fff;box-shadow:0 7px 18px rgba(37,99,235,.25)}
.top-links{display:flex;align-items:center;gap:5px;margin-left:auto;margin-right:10px}.top-link{border:1px solid transparent;background:transparent;color:#64748b;padding:8px 10px;border-radius:10px;font-size:10px;font-weight:800;display:flex;align-items:center;gap:5px;position:relative}.top-link:hover,.top-link.active{color:#1d4ed8;background:#eff6ff;border-color:#bfdbfe}

.top-link.chat-highlight, .nav-item.chat-highlight {
    background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
    color: #fff !important;
    border: 1px solid #93c5fd !important;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    animation: chatTabPulse 2s infinite;
}
.top-link.chat-highlight.active, .nav-item.chat-highlight.active {
    background: #0f172a !important;
    border-color: #f59e0b !important;
}
@keyframes chatTabPulse {
    0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.6); }
    70% { box-shadow: 0 0 0 8px rgba(37, 99, 235, 0); }
    100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
}

.logout{display:flex;align-items:center;gap:5px;border:1px solid var(--line);background:#fff;color:var(--muted);padding:8px 11px;border-radius:999px;font-size:10px;font-weight:800;white-space:nowrap}
.mobile-nav-strip{display:none}
.container{width:min(1180px,100%);margin:0 auto;padding:18px 14px 24px}.alert{padding:12px 14px;border-radius:13px;font-size:11px;font-weight:700;margin-bottom:14px;border:1px solid;display:flex;gap:8px;align-items:flex-start;box-shadow:0 5px 18px rgba(15,23,42,.04)}.success{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}.danger{background:#fef2f2;border-color:#fecaca;color:#b91c1c}
.page{display:none;animation:fade .2s ease}.page.active{display:block}@keyframes fade{from{opacity:.45;transform:translateY(4px)}to{opacity:1;transform:none}}
.hero{background:radial-gradient(circle at 90% 0%,rgba(37,99,235,.25),transparent 32%),linear-gradient(135deg,#071b2d 0%,#0f172a 55%,#1e3a8a 100%);border-radius:22px;color:#fff;padding:25px 22px;margin-bottom:14px;box-shadow:var(--shadow2);position:relative;overflow:hidden}.hero small{color:#93c5fd;font-size:9px;font-weight:800;letter-spacing:1.3px}.hero h1{font-size:25px;line-height:1.2;margin-top:5px;position:relative;z-index:1}.hero p{font-size:10px;color:#cbd5e1;margin-top:7px;max-width:700px;line-height:1.55;position:relative;z-index:1}

.hero-chips-row{display:flex;flex-wrap:wrap;gap:8px;margin-top:13px;position:relative;z-index:1}
.ref-chip{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.08);padding:7px 11px;border-radius:99px;font-size:10px;font-weight:800;color:#fff}
.phone-chip{display:inline-flex;align-items:center;gap:5px;background:linear-gradient(135deg, rgba(37,99,235,0.25), rgba(29,78,216,0.3));border:1px solid rgba(37,99,235,0.35);padding:7px 11px;border-radius:99px;font-size:10px;font-weight:800;color:#93c5fd}

.balance-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:13px}
.balance-card{background:var(--card);border:1px solid var(--line);border-radius:15px;padding:13px;min-width:0;box-shadow:0 5px 18px rgba(15,23,42,.035);position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;}
.balance-card.total{border-color:#93c5fd;background:linear-gradient(145deg,#eff6ff,#fff)}
.b-label{font-size:9px;color:var(--muted);font-weight:800;text-transform:uppercase;display:flex;align-items:center;gap:5px;line-height:1.25;position:relative;z-index:1}
.b-value{font-size:clamp(14px, 4vw, 18px);font-weight:800;margin-top:6px;word-break:break-word;overflow-wrap:break-word;line-height:1.3;position:relative;z-index:1}
.b-value.green{color:#10b981;}
.b-note{font-size:8px;color:var(--muted);margin-top:4px;position:relative;z-index:1}

@media(max-width:768px){
 .balance-grid{grid-template-columns:repeat(2,1fr);gap:8px;}
 .balance-card{padding:11px 10px;}
}
@media(max-width:400px){
 .balance-grid{grid-template-columns:1fr;}
}

.actions{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-bottom:15px}.action-btn{border:0;border-radius:13px;padding:13px;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:7px;transition:.18s;box-shadow:0 7px 18px rgba(15,23,42,.06)}.deposit-btn{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff}.withdraw-btn{background:#eff6ff;color:#1d4ed8;border:1.5px solid #bfdbfe;box-shadow:0 6px 16px rgba(37,99,235,0.12)}

.section-head{display:flex;justify-content:space-between;align-items:end;margin:18px 0 10px}.section-head h2{font-size:15px;font-weight:800}.section-head span{font-size:9px;color:var(--muted);font-weight:700}
.info-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.info-card{background:#fff;border:1px solid var(--line);border-radius:15px;padding:13px;box-shadow:0 5px 18px rgba(15,23,42,.025)}.info-card h3{font-size:11px;margin:8px 0 5px}.info-card p{font-size:9px;color:var(--muted);line-height:1.55}.info-icon{width:34px;height:34px;border-radius:10px;background:#eff6ff;color:#1d4ed8;display:grid;place-items:center}
.contact{margin-top:11px;background:linear-gradient(135deg,#071b2d,#1e3a8a);color:#fff;border-radius:15px;padding:13px;display:flex;align-items:center;justify-content:space-between;gap:10px;box-shadow:0 8px 22px rgba(30,58,138,.16)}.contact h3{font-size:12px}.contact p{font-size:9px;color:#bfdbfe;margin-top:3px}.wa{width:42px;height:42px;border-radius:50%;background:#fff;color:#1e3a8a;display:grid;place-items:center;flex:none}

.plans-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:17px}.plan{background:#fff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;box-shadow:0 8px 25px rgba(15,23,42,.055);display:flex;flex-direction:column;justify-content:space-between;min-height:330px;transition:transform .18s,box-shadow .18s}.plan:hover{transform:translateY(-3px);box-shadow:0 16px 35px rgba(15,23,42,.10)}
.plan-top{height:120px;padding:14px;display:flex;align-items:flex-end;position:relative;overflow:hidden;background-size:cover;background-position:center}
.plan-top-overlay{position:absolute;inset:0;background:linear-gradient(180deg, rgba(15,23,42,0.2) 0%, rgba(15,23,42,0.75) 100%)}
.plan-pill{background:rgba(255,255,255,.96);color:#0f172a;padding:6px 12px;border-radius:18px;font-size:12px;font-weight:800;position:relative;z-index:2;box-shadow:0 3px 10px rgba(15,23,42,.08)}
.plan-body{padding:16px}.rate-row{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}.rate-badge{display:flex;align-items:center;gap:9px}.pct-box{background:#eff6ff;color:#1d4ed8;font-size:17px;font-weight:800;width:38px;height:38px;border-radius:99px;display:flex;align-items:center;justify-content:center;border:1px solid #bfdbfe}.rate-label{font-size:10px;color:var(--muted);font-weight:700}.rate-val{font-size:20px;font-weight:800;color:#2563eb;margin-top:1px}.duration-val{font-size:14px;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:5px;margin-top:1px}.duration-val svg{color:#2563eb}.limits-box{background:#f8fafc;border:1px solid var(--line);border-radius:10px;padding:10px 12px;display:flex;flex-direction:column;gap:6px;margin-bottom:15px}.limit-row{display:flex;justify-content:space-between;align-items:center}.limit-label{font-size:9px;color:var(--muted);font-weight:800;text-transform:uppercase}.limit-val{font-size:12px;font-weight:800;color:var(--ink)}.btn-invest{width:100%;padding:12px;background:linear-gradient(135deg,#f59e0b,#d97706);border:0;border-radius:10px;color:#fff;font-weight:800;font-size:14px;cursor:pointer;box-shadow:0 6px 14px rgba(245,158,11,.22);display:flex;align-items:center;justify-content:center;gap:6px}.btn-invest:disabled{background:#cbd5e1;cursor:not-allowed;box-shadow:none}

.inv-card{background:#fff;border:1px solid var(--line);border-radius:17px;padding:13px;margin-bottom:11px;box-shadow:0 5px 18px rgba(15,23,42,.035)}.inv-card.invest-active{border-color:#93c5fd;box-shadow:0 10px 28px rgba(37,99,235,.11)}.inv-head{display:flex;justify-content:space-between;align-items:flex-start;gap:8px}.inv-title{font-size:13px;font-weight:800}.inv-date{font-size:8px;color:var(--muted);margin-top:3px}.status{font-size:8px;font-weight:800;text-transform:uppercase;border-radius:999px;padding:5px 8px;background:#eff6ff;color:#1d4ed8}.inv-metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-top:10px}.metric{background:#f8fafc;border:1px solid #eef2f7;border-radius:10px;padding:9px}.metric span{font-size:8px;color:var(--muted);font-weight:700}.metric strong{display:block;font-size:11px;margin-top:4px}.countdown{margin-top:10px;background:linear-gradient(135deg,#061826,#0f172a 58%,#1e3a8a);color:#fff;border-radius:13px;padding:12px;display:flex;align-items:center;justify-content:space-between;gap:10px}

.tx-switch{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:0 0 14px}.tx-switch button{border:1px solid var(--line);background:#fff;border-radius:14px;padding:13px 10px;font-size:10px;font-weight:800;color:var(--muted);display:flex;align-items:center;justify-content:center;gap:6px}.tx-switch button.active{background:linear-gradient(135deg,#eff6ff,#dbeafe);border-color:#93c5fd;color:#1d4ed8}.tx-panel{display:none}.tx-panel.active{display:block}.tx-card{background:#fff;border:1px solid var(--line);border-radius:14px;margin-bottom:9px;padding:12px}.tx-line{display:flex;justify-content:space-between;gap:10px;align-items:center}.tx-type{font-size:11px;font-weight:800;display:flex;align-items:center;gap:6px}.amount{font-size:12px;font-weight:800}.badge{font-size:8px;font-weight:800;text-transform:uppercase;padding:4px 7px;border-radius:999px}.badge.pending{background:#fffbeb;color:#b45309}.badge.approved{background:#eff6ff;color:#1d4ed8}

.chat-container{background:#fff;border:1px solid var(--line);border-radius:18px;display:flex;flex-direction:column;height:520px;box-shadow:0 10px 30px rgba(15,23,42,.05);overflow:hidden}
.chat-header{background:linear-gradient(135deg,#071b2d,#0f172a);color:#fff;padding:14px 18px;display:flex;align-items:center;gap:10px}
.chat-header h3{font-size:14px;font-weight:800}
.chat-messages{flex:1;padding:16px;overflow-y:auto;display:flex;flex-direction:column;gap:12px;background:#f8fafc}
.msg-bubble{max-width:80%;padding:10px 14px;border-radius:14px;font-size:12px;line-height:1.5;position:relative}
.msg-bubble.user{align-self:flex-end;background:#2563eb;color:#fff;border-bottom-right-radius:2px}
.msg-bubble.admin{align-self:flex-start;background:#fff;color:#0f172a;border:1px solid #e2e8f0;border-bottom-left-radius:2px;box-shadow:0 2px 8px rgba(0,0,0,0.03)}
.msg-time{font-size:8px;opacity:0.75;margin-top:4px;display:block;text-align:right}
.chat-form{padding:12px;background:#fff;border-top:1px solid #e2e8f0;display:flex;gap:8px;align-items:center}
.chat-form textarea{flex:1;border:1px solid #cbd5e1;border-radius:10px;padding:10px;font-size:12px;outline:none;resize:none;height:42px}
.chat-form button{background:#2563eb;color:#fff;border:0;border-radius:10px;padding:0 18px;font-weight:800;font-size:12px}

.bottom-nav{position:fixed;left:10px;right:10px;bottom:10px;height:70px;background:rgba(255,255,255,.98);backdrop-filter:blur(18px);border:1px solid #dbe4ee;border-radius:20px;display:grid;grid-template-columns:repeat(6,1fr);z-index:100;padding:4px;box-shadow:0 14px 35px rgba(15,23,42,.14)}.nav-item{border:0;background:transparent;color:#64748b;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;font-size:7.5px;font-weight:800;border-radius:12px;transition:.18s;position:relative}.nav-item.active{color:#1d4ed8;background:linear-gradient(180deg,#f0f7ff,#eff6ff);border:1px solid #bfdbfe;}.nav-icon{width:32px;height:26px;border-radius:99px;display:grid;place-items:center;position:relative}

.modal{position:fixed;inset:0;background:rgba(7,27,45,.66);backdrop-filter:blur(6px);display:none;align-items:flex-end;justify-content:center;z-index:150;padding:10px}.modal.active{display:flex}.modal-box{background:#fff;width:min(500px,100%);border-radius:21px 21px 13px 13px;padding:18px;max-height:93vh;overflow:auto;position:relative;box-shadow:0 25px 80px rgba(0,0,0,.25)}.close{position:absolute;right:12px;top:12px;width:31px;height:31px;border:0;border-radius:50%;background:#f1f5f9;display:grid;place-items:center}.modal-title{font-size:16px;font-weight:800;margin-bottom:11px;padding-right:38px}
.notice-large{background:#eff6ff;border:1.5px solid #bfdbfe;color:#1d4ed8;border-radius:14px;padding:14px;font-size:12px;line-height:1.6;margin-bottom:12px}
.code-copy-row{display:inline-flex;align-items:center;gap:8px;background:#ffffff;border:1px solid #bfdbfe;padding:6px 10px;border-radius:8px;margin:4px 0}
.btn-code-copy{border:0;background:#2563eb;color:#fff;padding:4px 9px;border-radius:6px;font-size:11px;font-weight:800;cursor:pointer}
.field{margin-bottom:11px}.field label{display:block;font-size:9px;font-weight:800;margin-bottom:5px;color:#334155}.field input{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:10px;outline:none;font-size:12px;background:#fff}
.quick{display:flex;gap:6px;overflow:auto;margin:7px 0 11px}.quick button{white-space:nowrap;border:1px solid #bfdbfe;background:#fff;color:#1d4ed8;border-radius:8px;padding:7px 9px;font-size:9px;font-weight:800}
.submit{width:100%;padding:12px;border:0;border-radius:10px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;font-size:11px;font-weight:800}
.submit-deposit-needed{background:linear-gradient(135deg,#ef4444,#dc2626) !important;box-shadow:0 6px 14px rgba(239,68,68,0.3) !important;}
.withdraw-balance{background:linear-gradient(135deg,#071b2d,#0f172a);color:#fff;border-radius:12px;padding:12px;margin-bottom:10px}.withdraw-balance small{display:block;color:#93c5fd;font-size:8px;font-weight:800}.withdraw-balance strong{font-size:20px;display:block;margin-top:3px}
.return{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;padding:12px;border-radius:12px;margin-bottom:11px}.return small{font-size:8px;font-weight:800;color:#bfdbfe}.return strong{display:block;font-size:20px;margin:3px 0}
.plan-summary{background:#f8fafc;border:1px solid var(--line);padding:10px;border-radius:11px;margin-bottom:11px;font-size:9px}.plan-summary div{display:flex;justify-content:space-between;gap:12px;margin:4px 0}

.chat-attention-badge{display:inline-flex;align-items:center;justify-content:center;background:#f59e0b;color:#000;font-size:9px;font-weight:900;width:16px;height:16px;border-radius:50%;margin-left:4px;box-shadow:0 0 0 0 rgba(245,158,11,0.7);animation:yellowBip 1.2s infinite ease-in-out;}
.chat-attention-dot{position:absolute;top:2px;right:10px;width:10px;height:10px;background:#f59e0b;border:2px solid #fff;border-radius:50%;animation:yellowBip 1.2s infinite ease-in-out;}
@keyframes yellowBip {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.8); }
    70% { transform: scale(1.15); box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}

.invited-switch{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px;margin-bottom:14px;}
.invited-switch button{border:1px solid var(--line);background:#fff;border-radius:14px;padding:12px 10px;font-size:11px;font-weight:800;color:var(--muted);display:flex;align-items:center;justify-content:center;gap:6px;}
.invited-switch button.active{background:linear-gradient(135deg,#eff6ff,#dbeafe);border-color:#93c5fd;color:#1d4ed8;}
.invited-panel{display:none;}.invited-panel.active{display:block;}
.member-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:12px;margin-bottom:8px;display:flex;align-items:center;justify-content:space-between;}

@media(max-width:900px){.top-links{display:none}.mobile-nav-strip{display:flex;position:sticky;top:64px;z-index:80;overflow-x:auto;gap:5px;padding:7px 10px;background:rgba(248,250,252,.96);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}.mobile-nav-strip button{white-space:nowrap;border:1px solid #dbe4ee;background:#fff;color:#64748b;border-radius:999px;padding:7px 10px;font-size:8px;font-weight:800}.mobile-nav-strip button.active{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8}.plans-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:680px){.container{padding:13px 11px 20px}.plans-grid{grid-template-columns:1fr;gap:12px}.info-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<header class="topbar">
    <a class="brand" href="dashboard.php?tab=home"><span class="brand-mark"><i data-lucide="trending-up" size="18"></i></span>NOVATOLO</a>
    <nav class="top-links">
        <button class="top-link <?= $tab==='home'?'active':'' ?>" data-tab="home"><i data-lucide="home" size="14"></i>Home</button>
        <button class="top-link <?= $tab==='plans'?'active':'' ?>" data-tab="plans"><i data-lucide="layers-3" size="14"></i>Plans</button>
        <button class="top-link <?= $tab==='invests'?'active':'' ?>" data-tab="invests"><i data-lucide="trending-up" size="14"></i>Invests</button>
        <button class="top-link <?= $tab==='transactions'?'active':'' ?>" data-tab="transactions"><i data-lucide="receipt" size="14"></i>Transactions</button>
        <button class="top-link <?= $tab==='invited'?'active':'' ?>" data-tab="invited"><i data-lucide="users" size="14"></i>Invited</button>
        <button class="top-link chat-highlight <?= $tab==='chat'?'active':'' ?>" data-tab="chat">
            <i data-lucide="message-square" size="14"></i>LIVE CHAT
            <?php if ($unreadAdminMsgs > 0): ?><span class="chat-attention-badge">+</span><?php endif; ?>
        </button>
    </nav>
    <a class="logout" href="login.php"><i data-lucide="log-out" size="13"></i> Logout</a>
</header>

<main class="container">
<?php if ($message): ?><div class="alert success"><i data-lucide="check-circle" size="15"></i><span><?= e($message) ?></span></div><?php endif; ?>
<?php if ($error): ?><div class="alert danger"><i data-lucide="alert-circle" size="15"></i><span><?= e($error) ?></span></div><?php endif; ?>

<!-- HOME PAGE -->
<section id="page-home" class="page <?= $tab === 'home' ? 'active' : '' ?>">
    <div class="hero">
        <small>WELCOME TO NOVATOLO</small>
        <h1><?= e($user['full_name']) ?></h1>
        <p>Manage deposits, investments, earnings, and live support directly from your NOVATOLO dashboard.</p>
        <div class="hero-chips-row">
            <span class="phone-chip"><i data-lucide="smartphone" size="13"></i> <?= e($user['phone']) ?></span>
            <span class="ref-chip"><i data-lucide="badge-check" size="13"></i> Referral: <?= e($user['ref_code']) ?></span>
        </div>
    </div>

    <div class="balance-grid">
        <div class="balance-card total"><div class="b-label">Total Balance</div><div class="b-value"><?= money($totalBalance) ?></div><div class="b-note">Deposit + earning wallets</div></div>
        <div class="balance-card"><div class="b-label">Deposit Balance</div><div class="b-value"><?= money($depositBalance) ?></div><div class="b-note">Available for investments</div></div>
        <div class="balance-card"><div class="b-label">Earning Balance</div><div class="b-value green"><?= money($earningBalance) ?></div><div class="b-note">Withdrawable & investable</div></div>
        <div class="balance-card"><div class="b-label">Welcome Bonus</div><div class="b-value">1,000 RWF</div><div class="b-note">Unlocked at 5,000+ RWF deposit</div></div>
    </div>

    <div class="actions">
        <button class="action-btn deposit-btn" onclick="openModal('depositModal')"><i data-lucide="plus-circle" size="16"></i>KUBITSA</button>
        <button class="action-btn withdraw-btn" onclick="openModal('withdrawModal')"><i data-lucide="arrow-up-right" size="16"></i>KUBIKUZA</button>
    </div>

    <div class="section-head"><h2>How NOVATOLO works</h2><span>Simple & mobile friendly</span></div>
    <div class="info-grid">
        <div class="info-card"><div class="info-icon"><i data-lucide="smartphone" size="16"></i></div><h3>Mobile Money Deposits</h3><p>Make your Mobile Money payment, then submit the exact amount so the deposit can be verified and credited to your account.</p></div>
        <div class="info-card"><div class="info-icon"><i data-lucide="shield-check" size="16"></i></div><h3>Clear Financial Control</h3><p>Deposits, active investments, earned returns, and cash withdrawals are tracked in realtime transparently.</p></div>
        <div class="info-card"><div class="info-icon"><i data-lucide="timer" size="16"></i></div><h3>Automated Investment Growth</h3><p>Select a verified growth plan, monitor countdown timers, and watch your capital and earnings automatically settle.</p></div>
    </div>

    <div class="contact">
        <div>
            <h3>Need help? Contact Support on WhatsApp</h3>
            <p>Tap the WhatsApp button to send: “Hello” to our official UK support line.</p>
        </div>
        <a class="wa" href="<?= e($whatsapp_link) ?>" target="_blank" rel="noopener"><i data-lucide="message-circle" size="21"></i></a>
    </div>
</section>

<!-- PLANS PAGE -->
<section id="page-plans" class="page <?= $tab === 'plans' ? 'active' : '' ?>">
    <div class="plans-grid">
    <?php 
    foreach ($plans as $index => $plan): 
        $pId = (int)$plan['id'];
        $imgFile = $plan_images[$pId] ?? 'florian-media-dock-2898497.jpg';
        $planAmount = (float)$plan['min_amount'];
        $profitRate = (float)$plan['profit_rate'];
        $durationDays = (int)$plan['duration_days'];
        $totalProfit = $planAmount * ($profitRate / 100);
        $totalReturn = $planAmount + $totalProfit;
        $dailyProfit = $totalProfit / max(1, $durationDays);
        
        $isFirst3 = $index < 3;
        $userInvestedCount = $userInvestmentsByPlan[$plan['id']] ?? 0;
        $isLimitReached = $isFirst3 && $userInvestedCount >= 1;
    ?>
        <article class="plan">
            <div>
                <div class="plan-top" style="background-image: url('<?= $imgFile ?>');">
                    <div class="plan-top-overlay"></div>
                    <span class="plan-pill"><?= e($plan['name']) ?></span>
                </div>
                <div class="plan-body">
                    <div class="rate-row">
                        <div class="rate-badge"><div class="pct-box">%</div><div><div class="rate-label">Profit Rate</div><div class="rate-val"><?= e($plan['profit_rate']) ?>%</div></div></div>
                        <div style="text-align:right"><div class="rate-label">Duration</div><div class="duration-val"><i data-lucide="clock" size="14"></i><span><?= $durationDays ?> Days</span></div></div>
                    </div>
                    <div class="limits-box">
                        <div class="limit-row"><span class="limit-label">Invest Amount</span><strong class="limit-val"><?= money($planAmount) ?></strong></div>
                        <div class="limit-row"><span class="limit-label">Daily Profit</span><strong class="limit-val green"><?= money($dailyProfit) ?> / day</strong></div>
                        <div class="limit-row"><span class="limit-label">Total Return</span><strong class="limit-val"><?= money($totalReturn) ?></strong></div>
                    </div>
                    <?php if ($isLimitReached): ?>
                        <button class="btn-invest" disabled><i data-lucide="check-circle" size="15"></i> Plan Already Used</button>
                    <?php else: ?>
                        <button class="btn-invest" onclick="openInvestModal(<?= (int)$plan['id'] ?>, '<?= e(addslashes($plan['name'])) ?>', <?= $planAmount ?>, <?= $profitRate ?>, <?= $durationDays ?>, <?= $totalReturn ?>)"><i data-lucide="zap" size="15"></i> Invest Now</button>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
</section>

<!-- INVESTMENTS PAGE -->
<section id="page-invests" class="page <?= $tab === 'invests' ? 'active' : '' ?>">
    <div class="section-head" style="margin-top:0"><div><h2>My Active Investments</h2><span>Track active & matured plans</span></div><span><?= count($investments) ?> Total</span></div>
    <?php if (empty($investments)): ?>
        <p style="font-size:12px; color:#64748b;">No active investments found.</p>
    <?php else: ?>
        <?php foreach ($investments as $inv): 
            $status = strtolower((string)$inv['status']);
            $duration = max(1, (int)$inv['duration_days']);
            $totalReturn = (float)$inv['amount'] + (float)$inv['total_profit'];
            $startDate = !empty($inv['last_payout_date']) ? (string)$inv['last_payout_date'] : (string)$inv['created_at'];
            $startTs = strtotime($startDate . (strlen($startDate) === 10 ? ' 00:00:00' : ''));
            $endTs = $startTs + ($duration * 86400);
        ?>
        <article class="inv-card invest-<?= e($status) ?>">
            <div class="inv-head">
                <div><div class="inv-title"><?= e($inv['plan_name']) ?></div><div class="inv-date">Started: <?= e($inv['created_at']) ?></div></div>
                <span class="status"><?= e($status) ?></span>
            </div>
            <div class="inv-metrics">
                <div class="metric"><span>Capital</span><strong><?= money($inv['amount']) ?></strong></div>
                <div class="metric"><span>Profit</span><strong style="color:#2563eb;">+<?= money($inv['total_profit']) ?></strong></div>
                <div class="metric"><span>Total Return</span><strong><?= money($totalReturn) ?></strong></div>
            </div>
            <?php if ($status === 'active'): ?>
                <div class="countdown">
                    <div><small>MATURITY COUNTDOWN</small><br><strong class="timer" data-end="<?= $endTs ?>">Calculating...</strong></div>
                    <i data-lucide="clock" size="20" style="color:#f59e0b"></i>
                </div>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<!-- TRANSACTIONS PAGE -->
<section id="page-transactions" class="page <?= $tab === 'transactions' ? 'active' : '' ?>">
    <div class="tx-switch">
        <button id="btn-tx-deposits" class="active" onclick="switchTxPanel('deposits')"><i data-lucide="arrow-down-left" size="15"></i> Deposits</button>
        <button id="btn-tx-withdrawals" onclick="switchTxPanel('withdrawals')"><i data-lucide="arrow-up-right" size="15"></i> Withdrawals</button>
    </div>

    <div id="panel-deposits" class="tx-panel active">
        <?php foreach (array_filter($transactions, fn($t) => strtolower((string)$t['type']) === 'deposit') as $t): ?>
            <div class="tx-card">
                <div class="tx-line">
                    <div class="tx-type"><i data-lucide="arrow-down-left" size="15" style="color:#2563eb"></i> Deposit</div>
                    <div class="amount" style="color:#2563eb;">+<?= money($t['amount']) ?></div>
                </div>
                <div class="tx-line" style="margin-top:6px;">
                    <small style="color:#64748b; font-size:8px;"><?= e($t['created_at']) ?></small>
                    <span class="badge <?= e(strtolower($t['status'])) ?>"><?= e($t['status']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="panel-withdrawals" class="tx-panel">
        <?php foreach (array_filter($transactions, fn($t) => in_array(strtolower((string)$t['type']), ['withdraw','withdrawal'], true)) as $t): ?>
            <div class="tx-card">
                <div class="tx-line">
                    <div class="tx-type"><i data-lucide="arrow-up-right" size="15" style="color:#ef4444"></i> Withdrawal</div>
                    <div class="amount" style="color:#ef4444;">-<?= money($t['amount']) ?></div>
                </div>
                <div class="tx-line" style="margin-top:6px;">
                    <small style="color:#64748b; font-size:8px;"><?= e($t['created_at']) ?> (<?= e($t['phone']) ?>)</small>
                    <span class="badge <?= e(strtolower($t['status'])) ?>"><?= e($t['status']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- INVITED PAGE -->
<section id="page-invited" class="page <?= $tab === 'invited' ? 'active' : '' ?>">
    <div style="background:#071b2d; color:#fff; border-radius:18px; padding:16px; margin-bottom:12px;">
        <h3>Invite Friends & Earn Rewards</h3>
        <p style="font-size:10px; color:#cbd5e1; margin-top:4px;">Earn up to 3,000 RWF per referral based on their deposit level!</p>
        <div style="display:flex; gap:6px; margin-top:10px;">
            <input type="text" id="refUrl" value="<?= e($ref_link) ?>" readonly style="flex:1; padding:8px; border-radius:8px; border:none; font-size:10px;">
            <button onclick="copyRef()" style="background:#2563eb; color:#fff; border:none; padding:0 12px; border-radius:8px; font-weight:800; font-size:10px;">Copy</button>
        </div>
    </div>

    <div class="invited-switch">
        <button id="btn-invited-active" class="active" onclick="switchInvitedPanel('active')"><i data-lucide="user-check" size="15"></i> Active Users (<?= count($activeInvited) ?>)</button>
        <button id="btn-invited-inactive" onclick="switchInvitedPanel('inactive')"><i data-lucide="user-x" size="15"></i> Inactive Users (<?= count($inactiveInvited) ?>)</button>
    </div>

    <div id="panel-invited-active" class="invited-panel active">
        <?php if (empty($activeInvited)): ?>
            <p style="font-size:12px; color:#64748b; padding:10px 0;">No active invited users yet. (Requires 5,000+ RWF deposit)</p>
        <?php else: ?>
            <?php foreach ($activeInvited as $m): ?>
                <div class="member-card">
                    <div>
                        <strong style="font-size:12px; display:block;"><?= e($m['full_name']) ?></strong>
                        <small style="font-size:9px; color:#64748b;"><?= e($m['phone']) ?> • Joined <?= date('M d, Y', strtotime($m['created_at'])) ?></small>
                    </div>
                    <span style="font-size:10px; font-weight:800; color:#1d4ed8; background:#eff6ff; padding:4px 8px; border-radius:6px; border:1px solid #bfdbfe;">
                        Deposited: <?= money($m['total_deposited']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="panel-invited-inactive" class="invited-panel">
        <?php if (empty($inactiveInvited)): ?>
            <p style="font-size:12px; color:#64748b; padding:10px 0;">No inactive invited users.</p>
        <?php else: ?>
            <?php foreach ($inactiveInvited as $m): ?>
                <div class="member-card">
                    <div>
                        <strong style="font-size:12px; display:block;"><?= e($m['full_name']) ?></strong>
                        <small style="font-size:9px; color:#64748b;"><?= e($m['phone']) ?> • Joined <?= date('M d, Y', strtotime($m['created_at'])) ?></small>
                    </div>
                    <span style="font-size:10px; font-weight:800; color:#b45309; background:#fffbeb; padding:4px 8px; border-radius:6px; border:1px solid #fde68a;">
                        Deposited: <?= money($m['total_deposited']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- LIVE SUPPORT CHAT -->
<section id="page-chat" class="page <?= $tab === 'chat' ? 'active' : '' ?>">
    <div class="chat-container">
        <div class="chat-header">
            <i data-lucide="message-square" size="20"></i>
            <div>
                <h3>NOVATOLO Direct Support Chat</h3>
                <small style="font-size:10px; color:#bfdbfe;">Send text, photos, or recorded audio messages to Admin</small>
            </div>
        </div>
        <div class="chat-messages" id="chatBox">
            <?php if (empty($chatMessages)): ?>
                <div style="text-align:center; padding:30px; color:#64748b; font-size:12px;">
                    Send a message, photo, or audio file to start chatting directly with NOVATOLO Admin Support.
                </div>
            <?php else: ?>
              <?php foreach ($chatMessages as $cm): ?>
    <div class="msg-bubble <?= $cm['sender_type'] === 'user' ? 'user' : 'admin' ?>">
        <strong><?= $cm['sender_type'] === 'user' ? 'You' : 'NOVATOLO Support' ?>:</strong><br>
        <?php if (!empty($cm['message'])): ?>
            <div style="margin-top:2px;"><?= e($cm['message']) ?></div>
        <?php endif; ?>

        <?php if (!empty($cm['file_path']) && $cm['file_type'] === 'image'): ?>
            <div style="margin-top:6px;">
                <a href="view.php?file=<?= e(basename($cm['file_path'])) ?>" target="_blank">
                    <img src="view.php?file=<?= e(basename($cm['file_path'])) ?>" style="max-width:200px; border-radius:8px; display:block;">
                </a>
                <a href="view.php?file=<?= e(basename($cm['file_path'])) ?>" download class="btn-act btn-blue" style="font-size:9px; margin-top:4px; padding:2px 6px; background:#1e40af;">Download Image</a>
            </div>
        <?php elseif (!empty($cm['file_path']) && $cm['file_type'] === 'audio'): ?>
            <div style="margin-top:6px;">
                <audio controls style="height:36px; width:220px;">
                    <source src="view.php?file=<?= e(basename($cm['file_path'])) ?>" type="audio/webm">
                </audio>
                <br>
                <a href="view.php?file=<?= e(basename($cm['file_path'])) ?>" download class="btn-act btn-blue" style="font-size:9px; margin-top:4px; padding:2px 6px; background:#1e40af;">Download Audio</a>
            </div>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px; gap:8px;">
            <span class="msg-time"><?= date('H:i, M d', strtotime($cm['created_at'])) ?></span>
            <?php if ($cm['sender_type'] === 'user' && empty($cm['is_deleted'])): ?>
                <div style="font-size:9px;">
                    <a href="javascript:void(0);" onclick="enableUserEdit(<?= $cm['id'] ?>, '<?= htmlspecialchars(addslashes($cm['message'])) ?>')" style="color:#fde047; text-decoration:underline; margin-right:4px;">Edit</a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this message?')">
                        <input type="hidden" name="action" value="delete_support_msg">
                        <input type="hidden" name="delete_msg_id" value="<?= $cm['id'] ?>">
                        <button type="submit" style="background:none; border:none; color:#fca5a5; text-decoration:underline; font-size:9px; cursor:pointer;">Delete</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
            <?php endif; ?>
        </div>
        <form method="POST" enctype="multipart/form-data" class="chat-form">
            <input type="hidden" name="action" value="send_support_msg">
            <input type="hidden" name="audio_blob_data" id="userAudioBlobData">
            <textarea name="chat_message" placeholder="Type message or attach photo/audio..."></textarea>
            <button type="button" id="userRecordBtn" class="btn-act" style="background:#ef4444; color:#fff; padding:8px 10px; border-radius:8px;" onclick="toggleUserAudioRecording()" title="Record Audio">🎤</button>
            <label style="cursor:pointer; background:#EFF6FF; border:1px solid #BFDBFE; padding:8px 10px; border-radius:8px; font-size:14px;" title="Attach Photo or Audio">
                📎 <input type="file" name="chat_file" accept="image/*,audio/*" style="display:none;" onchange="document.getElementById('userFileNameSpan').innerText = this.files[0] ? this.files[0].name : ''">
            </label> 
          <button type="submit" style="background-color: #2563eb; color: #ffffff; font-size: 16px; padding: 12px 24px; border: none; border-radius: 8px; font-weight: bold;">SEND</button>
        </form>
        <div id="userFileNameSpan" style="padding:0 12px 6px 12px; font-size:10px; color:#10B981; font-weight:bold;"></div>
        <div id="userRecordingStatus" style="padding:0 12px 6px 12px; font-size:10px; color:#ef4444; font-weight:bold; display:none;">🔴 Recording audio... Click microphone again to stop.</div>
    </div>
</section>

</main>

<!-- User Edit Message Modal -->
<div id="userEditModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:20px; border-radius:10px; width:min(400px, 90%);">
        <h4>Edit Message</h4>
        <form method="POST">
            <input type="hidden" name="action" value="edit_support_msg">
            <input type="hidden" name="edit_msg_id" id="userModalEditMsgId">
            <textarea name="edit_message_text" id="userModalEditMsgText" style="width:100%; height:80px; margin:10px 0; padding:8px; border:1px solid #ccc; border-radius:6px;" required></textarea>
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn-act btn-red" onclick="document.getElementById('userEditModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-act btn-blue">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<nav class="bottom-nav">
    <button class="nav-item <?= $tab==='home'?'active':'' ?>" data-tab="home"><div class="nav-icon"><i data-lucide="home" size="18"></i></div>Home</button>
    <button class="nav-item <?= $tab==='plans'?'active':'' ?>" data-tab="plans"><div class="nav-icon"><i data-lucide="layers-3" size="18"></i></div>Plans</button>
    <button class="nav-item <?= $tab==='invests'?'active':'' ?>" data-tab="invests"><div class="nav-icon"><i data-lucide="trending-up" size="18"></i></div>Invests</button>
    <button class="nav-item <?= $tab==='transactions'?'active':'' ?>" data-tab="transactions"><div class="nav-icon"><i data-lucide="receipt" size="18"></i></div>History</button>
    <button class="nav-item <?= $tab==='invited'?'active':'' ?>" data-tab="invited"><div class="nav-icon"><i data-lucide="users" size="18"></i></div>Invited</button>
    <button class="nav-item chat-highlight <?= $tab==='chat'?'active':'' ?>" data-tab="chat">
        <div class="nav-icon">
            <i data-lucide="message-square" size="18"></i>
            <?php if ($unreadAdminMsgs > 0): ?><span class="chat-attention-dot"></span><?php endif; ?>
        </div>
        LIVE CHAT
    </button>
</nav>

<!-- Deposit Modal Structure -->
<div id="depositModal" class="modal">
    <div class="modal-box">
        <button class="close" onclick="closeModal('depositModal')"><i data-lucide="x" size="16"></i></button>
        <div class="modal-title">Deposit Funds</div>
        <div class="notice-large">
            <strong>📌 Instructions to Deposit:</strong><br>
            1. Dial MoMo USSD Code:<br>
            <div class="code-copy-row">
                <strong>*182*1*1*0792841159#</strong>
                <button type="button" class="btn-code-copy" onclick="copyMomoCode('*182*1*1*0792841159#')">Copy</button>
            </div><br>
            2. pay to names: NYANDWI callixte<br>
            3. Enter paid amount below and click submit.
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="deposit">
            <div class="field">
                <label>Deposit Amount (RWF)</label>
                <input type="number" id="depAmount" name="amount" min="1000" placeholder="Minimum 1,000 RWF" required>
            </div>
            <div class="quick">
                <button type="button" onclick="setDep(5000)">5,000</button>
                <button type="button" onclick="setDep(10000)">10,000</button>
                <button type="button" onclick="setDep(20000)">20,000</button>
                <button type="button" onclick="setDep(50000)">50,000</button>
            </div>
            <button type="submit" class="submit">Submit Deposit Request</button>
        </form>
    </div>
</div>

<!-- Withdraw Modal Structure -->
<div id="withdrawModal" class="modal">
    <div class="modal-box">
        <button class="close" onclick="closeModal('withdrawModal')"><i data-lucide="x" size="16"></i></button>
        <div class="modal-title">Withdraw Earnings</div>
        <div class="withdraw-balance"><small>WITHDRAWABLE EARNING BALANCE</small><strong><?= money($earningBalance) ?></strong></div>
        <form method="POST">
            <input type="hidden" name="action" value="withdraw">
            <div class="field"><label>Amount (RWF)</label><input type="number" name="amount" min="500" max="<?= $earningBalance ?>" placeholder="Minimum 500 RWF" required></div>
            <div class="field"><label>MoMo Phone Number</label><input type="text" name="momo_phone" value="<?= e($user['phone']) ?>" required></div>
            <div class="field"><label>Registered MoMo Name</label><input type="text" name="momo_name" placeholder="Full Registered Name" required></div>
            <button type="submit" class="submit" style="background:#2563eb;">Confirm Withdrawal Request</button>
        </form>
    </div>
</div>

<!-- Invest Modal Structure -->
<div id="investModal" class="modal">
    <div class="modal-box">
        <button class="close" onclick="closeModal('investModal')"><i data-lucide="x" size="16"></i></button>
        <div class="modal-title" id="mPlanName">Activate Investment</div>
        <form method="POST" id="investForm" onsubmit="return handleInvestSubmit(event)">
            <input type="hidden" name="action" value="invest">
            <input type="hidden" id="mPlanId" name="plan_id" value="">
            <div class="return">
                <small>EXPECTED TOTAL RETURN</small>
                <strong id="mTotalReturn">0 RWF</strong>
            </div>
            <div class="plan-summary">
                <div><span>Invest Amount:</span><strong id="mInvestAmount">0 RWF</strong></div>
                <div><span>Profit Rate:</span><strong id="mProfitRate">0%</strong></div>
                <div><span>Duration:</span><strong id="mDuration">0 Days</strong></div>
                <div><span>Available Balance:</span><strong><?= money($totalBalance) ?></strong></div>
            </div>
            <button type="submit" id="btnConfirmInvest" class="submit"><i data-lucide="zap" size="14"></i> Confirm & Activate</button>
        </form>
    </div>
</div>

<script>
    let userTotalBalance = <?= (float)$totalBalance ?>;
    let selectedPlanReqAmount = 0;

    lucide.createIcons();

    let userMediaRecorder;
    let userAudioChunks = [];
    let userIsRecording = false;

    function toggleUserAudioRecording() {
        const recStatus = document.getElementById('userRecordingStatus');
        const recBtn = document.getElementById('userRecordBtn');
        
        if (!userIsRecording) {
            navigator.mediaDevices.getUserMedia({ audio: true }).then(stream => {
                userMediaRecorder = new MediaRecorder(stream);
                userAudioChunks = [];
                
                userMediaRecorder.ondataavailable = e => {
                    if (e.data.size > 0) userAudioChunks.push(e.data);
                };
                
                userMediaRecorder.onstop = () => {
                    const audioBlob = new Blob(userAudioChunks, { type: 'audio/webm' });
                    const reader = new FileReader();
                    reader.readAsDataURL(audioBlob);
                    reader.onloadend = () => {
                        document.getElementById('userAudioBlobData').value = reader.result;
                        document.getElementById('userFileNameSpan').innerText = 'Recorded_Audio.webm';
                    };
                    stream.getTracks().forEach(track => track.stop());
                };
                
                userMediaRecorder.start();
                userIsRecording = true;
                recBtn.style.background = '#10b981';
                recStatus.style.display = 'block';
            }).catch(err => {
                alert('Microphone access denied or unavailable.');
            });
        } else {
            userMediaRecorder.stop();
            userIsRecording = false;
            recBtn.style.background = '#ef4444';
            recStatus.style.display = 'none';
        }
    }

    function enableUserEdit(msgId, currentText) {
        document.getElementById('userModalEditMsgId').value = msgId;
        document.getElementById('userModalEditMsgText').value = currentText;
        document.getElementById('userEditModal').style.display = 'flex';
    }

    function openModal(id){
        const m = document.getElementById(id);
        if(m) m.classList.add('active');
    }
    
    function closeModal(id){
        const m = document.getElementById(id);
        if(m) m.classList.remove('active');
    }

    function setDep(val){
        document.getElementById('depAmount').value = val;
    }

    function copyRef(){
        const input = document.getElementById('refUrl');
        input.select();
        document.execCommand('copy');
        alert('Referral link copied!');
    }

    function copyMomoCode(codeStr){
        navigator.clipboard.writeText(codeStr).then(() => {
            alert('MoMo code copied: ' + codeStr);
        }).catch(() => {
            alert('Code: ' + codeStr);
        });
    }

    function switchTxPanel(type){
        document.querySelectorAll('.tx-panel').forEach(p => p.classList.remove('active'));
        document.getElementById('btn-tx-deposits').classList.remove('active');
        document.getElementById('btn-tx-withdrawals').classList.remove('active');

        if(type === 'deposits'){
            document.getElementById('panel-deposits').classList.add('active');
            document.getElementById('btn-tx-deposits').classList.add('active');
        } else {
            document.getElementById('panel-withdrawals').classList.add('active');
            document.getElementById('btn-tx-withdrawals').classList.add('active');
        }
    }

    function switchInvitedPanel(type){
        document.querySelectorAll('.invited-panel').forEach(p => p.classList.remove('active'));
        document.getElementById('btn-invited-active').classList.remove('active');
        document.getElementById('btn-invited-inactive').classList.remove('active');

        if(type === 'active'){
            document.getElementById('panel-invited-active').classList.add('active');
            document.getElementById('btn-invited-active').classList.add('active');
        } else {
            document.getElementById('panel-invited-inactive').classList.add('active');
            document.getElementById('btn-invited-inactive').classList.add('active');
        }
    }

    function openInvestModal(id, name, amount, rate, duration, totalReturn){
        selectedPlanReqAmount = amount;
        document.getElementById('mPlanId').value = id;
        document.getElementById('mPlanName').innerText = 'Invest in ' + name;
        document.getElementById('mInvestAmount').innerText = amount.toLocaleString() + ' RWF';
        document.getElementById('mProfitRate').innerText = rate + '%';
        document.getElementById('mDuration').innerText = duration + ' Days';
        document.getElementById('mTotalReturn').innerText = totalReturn.toLocaleString() + ' RWF';
        
        const btn = document.getElementById('btnConfirmInvest');
        btn.classList.remove('submit-deposit-needed');
        btn.innerHTML = '<i data-lucide="zap" size="14"></i> Confirm & Activate';
        lucide.createIcons();

        openModal('investModal');
    }

    function handleInvestSubmit(e) {
        const btn = document.getElementById('btnConfirmInvest');
        if (userTotalBalance < selectedPlanReqAmount) {
            e.preventDefault();
            if (!btn.classList.contains('submit-deposit-needed')) {
                btn.classList.add('submit-deposit-needed');
                btn.innerHTML = '<i data-lucide="plus-circle" size="14"></i> Deposit First';
                lucide.createIcons();
            } else {
                closeModal('investModal');
                const neededDeposit = selectedPlanReqAmount - userTotalBalance;
                if (neededDeposit > 0) {
                    setDep(neededDeposit);
                }
                openModal('depositModal');
            }
            return false;
        }
        return true;
    }

    document.querySelectorAll('[data-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            const tabName = btn.getAttribute('data-tab');
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('[data-tab]').forEach(b => b.classList.remove('active'));
            
            const targetPage = document.getElementById('page-' + tabName);
            if(targetPage) targetPage.classList.add('active');

            document.querySelectorAll('[data-tab="' + tabName + '"]').forEach(b => b.classList.add('active'));
            history.replaceState(null, null, 'dashboard.php?tab=' + tabName);
            
            if (tabName === 'chat') {
                const chatBox = document.getElementById('chatBox');
                if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
            }
        });
    });

    function updateTimers() {
        const now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('.timer[data-end]').forEach(el => {
            const end = parseInt(el.getAttribute('data-end'));
            const diff = end - now;

            if (diff <= 0) {
                el.innerText = '00h 00m 00s (Finished)';
                return;
            }

            const days = Math.floor(diff / 86400);
            const hours = Math.floor((diff % 86400) / 3600);
            const mins = Math.floor((diff % 3600) / 60);
            const secs = diff % 60;

            let str = '';
            if (days > 0) str += days + 'd ';
            str += String(hours).padStart(2, '0') + 'h ' +
                   String(mins).padStart(2, '0') + 'm ' +
                   String(secs).padStart(2, '0') + 's';

            el.innerText = str;
        });
    }

    setInterval(updateTimers, 1000);
    updateTimers();

    window.addEventListener('DOMContentLoaded', () => {
        const chatBox = document.getElementById('chatBox');
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    });
</script>
</body>
</html>