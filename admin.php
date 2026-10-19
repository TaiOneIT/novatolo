<?php
// admin.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit;
}

$message = '';
$current_sec = $_GET['sec'] ?? 'secDep';

// Auto-initialize support messages ledger with media & edit/delete columns
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

// Compatibility checks
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

function redirect_admin($section, $msg) {
    header("Location: admin.php?sec=" . urlencode($section) . "&msg=" . urlencode($msg) . "#" . urlencode($section));
    exit;
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

// Helper function to handle media and recorded audio uploads securely
function handle_chat_upload($file, $audioBlobData = null) {
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

// Admin Message Edit / Delete Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_msg_id'])) {
    $editId = intval($_POST['edit_msg_id']);
    $newText = trim($_POST['edit_message_text'] ?? '');
    $targetUser = intval($_POST['target_user_id'] ?? 0);
    if ($editId > 0 && !empty($newText)) {
        $upd = $pdo->prepare("UPDATE support_messages SET message = ? WHERE id = ? AND sender_type = 'admin'");
        $upd->execute([$newText, $editId]);
        redirect_admin('secChat', "Message updated successfully." . ($targetUser > 0 ? "&user_id=$targetUser" : ""));
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete_msg' && isset($_GET['msg_id'])) {
    $delMsgId = intval($_GET['msg_id']);
    $targetUser = intval($_GET['user_id'] ?? 0);
    if ($delMsgId > 0) {
        $del = $pdo->prepare("UPDATE support_messages SET is_deleted = 1, message = '[Message Deleted]' WHERE id = ? AND sender_type = 'admin'");
        $del->execute([$delMsgId]);
        redirect_admin('secChat', "Message deleted." . ($targetUser > 0 ? "&user_id=$targetUser" : ""));
    }
}

// Admin Reply / Broadcast / Single User Handler for Support Messages
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_reply_msg'])) {
    $targetMode = $_POST['target_mode'] ?? 'single'; 
    $replyText  = trim($_POST['reply_message'] ?? '');
    $audioBlob  = $_POST['admin_audio_blob_data'] ?? null;
    
    $uploadResult = handle_chat_upload($_FILES['chat_file'] ?? null, $audioBlob);

    if (!empty($replyText) || $uploadResult['type'] !== 'none') {
        if ($targetMode === 'all') {
            $allUsers = $pdo->query("SELECT id FROM users WHERE is_admin = 0 AND COALESCE(is_archived,0) = 0")->fetchAll(PDO::FETCH_COLUMN);
            $ins = $pdo->prepare("INSERT INTO support_messages (user_id, sender_type, message, file_path, file_type) VALUES (?, 'admin', ?, ?, ?)");
            foreach ($allUsers as $uId) {
                $ins->execute([$uId, $replyText, $uploadResult['path'], $uploadResult['type']]);
            }
            redirect_admin('secChat', "Broadcast message sent to all registered users successfully.");
        } elseif ($targetMode === 'selected') {
            $selectedUsers = $_POST['selected_chat_users'] ?? [];
            if (!empty($selectedUsers)) {
                $ins = $pdo->prepare("INSERT INTO support_messages (user_id, sender_type, message, file_path, file_type) VALUES (?, 'admin', ?, ?, ?)");
                foreach ($selectedUsers as $uId) {
                    $ins->execute([intval($uId), $replyText, $uploadResult['path'], $uploadResult['type']]);
                }
                redirect_admin('secChat', "Message sent to selected users successfully.");
            } else {
                redirect_admin('secChat', "No users selected for messaging.");
            }
        } else {
            $chatUserId = intval($_POST['chat_user_id'] ?? 0);
            if ($chatUserId > 0) {
                $ins = $pdo->prepare("INSERT INTO support_messages (user_id, sender_type, message, file_path, file_type) VALUES (?, 'admin', ?, ?, ?)");
                $ins->execute([$chatUserId, $replyText, $uploadResult['path'], $uploadResult['type']]);
                redirect_admin('secChat', "Reply sent to user account successfully.&user_id=$chatUserId");
            } else {
                redirect_admin('secChat', "Please select a valid user to message.");
            }
        }
    } else {
        redirect_admin('secChat', "Message or attachment cannot be empty.");
    }
}

// Clear Support Messages Chat History
if (isset($_GET['action']) && $_GET['action'] === 'clear_chat' && isset($_GET['user_id'])) {
    $clearUserId = intval($_GET['user_id']);
    if ($clearUserId > 0) {
        $stmt = $pdo->prepare("DELETE FROM support_messages WHERE user_id = ?");
        $stmt->execute([$clearUserId]);
        redirect_admin('secChat', "Chat history cleared for User #$clearUserId.");
    }
}

// Single Action Handlers
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'approve_inv') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT user_id FROM investments WHERE id = ?");
        $stmt->execute([$id]);
        $inv_user_id = $stmt->fetchColumn();

        $stmt = $pdo->prepare("UPDATE investments SET status = 'active', last_payout_date = CURDATE() WHERE id = ?");
        $stmt->execute([$id]);

        if ($inv_user_id) {
            $stmt = $pdo->prepare("UPDATE users SET is_active_investor = 1 WHERE id = ?");
            $stmt->execute([$inv_user_id]);
        }

        $pdo->commit();
        redirect_admin('secInv', "Investment #$id approved.");
    } elseif ($action === 'add_return') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT i.*, p.duration_days FROM investments i JOIN investment_plans p ON i.plan_id = p.id WHERE i.id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();

        if ($inv && strtolower($inv['status']) !== 'completed') {
            $totalReturn = (float)$inv['amount'] + (float)$inv['total_profit'];
            $duration = max(1, (int)$inv['duration_days']);

            $updInv = $pdo->prepare("UPDATE investments SET status = 'completed', days_counted = ? WHERE id = ?");
            $updInv->execute([$duration, $id]);

            $updUser = $pdo->prepare("UPDATE users SET profit_balance = profit_balance + ? WHERE id = ?");
            $updUser->execute([$totalReturn, $inv['user_id']]);

            $pdo->commit();
            redirect_admin('secInv', "Total return (" . number_format($totalReturn) . " RWF) successfully added to user account!");
        } else {
            $pdo->rollBack();
            redirect_admin('secInv', "Investment #$id was already completed or not found.");
        }
    } elseif ($action === 'delete_inv') {
        $stmt = $pdo->prepare("DELETE FROM investments WHERE id = ?");
        $stmt->execute([$id]);
        redirect_admin('secInv', "Investment #$id deleted.");
    }

    if ($action === 'unarchive') {
        $table = $_GET['table'] ?? '';
        if (in_array($table, ['transactions', 'investments', 'users'])) {
            $stmt = $pdo->prepare("UPDATE $table SET is_archived = 0 WHERE id = ?");
            $stmt->execute([$id]);
            redirect_admin($current_sec, "Item #$id unarchived successfully.");
        }
    }

    if ($action === 'approve_dep') {
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        $tx = $stmt->fetch();
        if ($tx && $tx['status'] !== 'approved') {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?");
            $stmt->execute([$id]);
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$tx['amount'], $tx['user_id']]);
            $pdo->commit();
            redirect_admin('secDep', "Deposit #$id approved.");
        }
    } elseif ($action === 'reject_dep') {
        $stmt = $pdo->prepare("UPDATE transactions SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$id]);
        redirect_admin('secDep', "Deposit #$id rejected.");
    } elseif ($action === 'approve_with') {
        $stmt = $pdo->prepare("UPDATE transactions SET status = 'approved' WHERE id = ? AND LOWER(type) IN ('withdraw','withdrawal') AND status = 'pending'");
        $stmt->execute([$id]);
        redirect_admin('secWith', $stmt->rowCount() ? "Withdrawal #$id approved." : "Withdrawal #$id processed or not found.");
    } elseif ($action === 'reject_with') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT user_id, amount FROM transactions WHERE id = ? AND LOWER(type) IN ('withdraw','withdrawal') AND status = 'pending' FOR UPDATE");
        $stmt->execute([$id]);
        $tx = $stmt->fetch();
        if ($tx) {
            $stmt = $pdo->prepare("UPDATE transactions SET status = 'rejected' WHERE id = ? AND LOWER(type) IN ('withdraw','withdrawal') AND status = 'pending'");
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 1) {
                $stmt = $pdo->prepare("UPDATE users SET profit_balance = profit_balance + ? WHERE id = ?");
                $stmt->execute([$tx['amount'], $tx['user_id']]);
            }
        }
        $pdo->commit();
        redirect_admin('secWith', $tx ? "Withdrawal #$id rejected and funds returned." : "Withdrawal #$id not found.");
    } elseif ($action === 'delete_with') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT user_id, amount, status FROM transactions WHERE id = ? AND LOWER(type) IN ('withdraw','withdrawal') FOR UPDATE");
        $stmt->execute([$id]);
        $tx = $stmt->fetch();
        if ($tx) {
            if ($tx['status'] === 'pending') {
                $stmt = $pdo->prepare("UPDATE users SET profit_balance = profit_balance + ? WHERE id = ?");
                $stmt->execute([$tx['amount'], $tx['user_id']]);
            }
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND LOWER(type) IN ('withdraw','withdrawal')");
            $stmt->execute([$id]);
        }
        $pdo->commit();
        redirect_admin('secWith', "Withdrawal #$id deleted.");
    }
}

// Bulk Actions Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $target_table = $_POST['target_table'];
    $selected_ids = $_POST['selected_ids'] ?? [];
    $sec = $_POST['active_sec'] ?? 'secUsers';

    if (!empty($selected_ids) && in_array($target_table, ['transactions', 'investments', 'users'])) {
        $in_clause = implode(',', array_map('intval', $selected_ids));
        
        if ($_POST['bulk_action'] === 'delete') {
            if ($target_table === 'transactions' && $sec === 'secWith') {
                $pdo->beginTransaction();
                $rows = $pdo->query("SELECT user_id, amount FROM transactions WHERE id IN ($in_clause) AND LOWER(type) IN ('withdraw','withdrawal') AND status = 'pending' FOR UPDATE")->fetchAll();
                foreach ($rows as $row) {
                    $stmt = $pdo->prepare("UPDATE users SET profit_balance = profit_balance + ? WHERE id = ?");
                    $stmt->execute([$row['amount'], $row['user_id']]);
                }
                $pdo->query("DELETE FROM transactions WHERE id IN ($in_clause) AND LOWER(type) IN ('withdraw','withdrawal')");
                $pdo->commit();
            } else {
                $pdo->query("DELETE FROM $target_table WHERE id IN ($in_clause) " . ($target_table === 'users' ? "AND is_admin = 0" : ""));
            }
            redirect_admin($sec, "Selected records deleted permanently.");
        } elseif ($_POST['bulk_action'] === 'archive') {
            $pdo->query("UPDATE $target_table SET is_archived = 1 WHERE id IN ($in_clause)");
            redirect_admin($sec, "Selected records archived.");
        } elseif ($_POST['bulk_action'] === 'unarchive') {
            $pdo->query("UPDATE $target_table SET is_archived = 0 WHERE id IN ($in_clause)");
            redirect_admin($sec, "Selected records restored/unarchived.");
        }
    }
}

// User Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'])) {
    $actionType = $_POST['admin_action'];
    if ($actionType === 'change_password') {
        $target_id = intval($_POST['target_user_id']);
        $new_pass_plain = trim($_POST['new_password']);
        if (!empty($new_pass_plain)) {
            $new_pass = password_hash($new_pass_plain, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$new_pass, $target_id]);
            redirect_admin('secUsers', "Password updated successfully for User #$target_id.");
        } else {
            redirect_admin('secUsers', "Password cannot be empty.");
        }
    } elseif ($actionType === 'remove_user') {
        $target_id = intval($_POST['target_user_id']);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
        $stmt->execute([$target_id]);
        redirect_admin('secUsers', "User #$target_id deleted permanently.");
    } elseif ($actionType === 'adjust_balance') {
        $target_id = intval($_POST['target_user_id']);
        $target_wallet = $_POST['wallet_type'] ?? 'profit_balance';
        $operation = $_POST['operation'] ?? 'add';
        $amount = (float)($_POST['amount'] ?? 0);

        if ($amount > 0 && in_array($target_wallet, ['balance', 'profit_balance'])) {
            $opSign = ($operation === 'add') ? '+' : '-';
            $stmt = $pdo->prepare("UPDATE users SET $target_wallet = GREATEST(0, $target_wallet $opSign ?) WHERE id = ?");
            $stmt->execute([$amount, $target_id]);
            
            $walletLabel = ($target_wallet === 'balance') ? 'Deposit Balance' : 'Earning Balance';
            $opLabel = ($operation === 'add') ? 'Added' : 'Deducted';
            redirect_admin('secUsers', "Successfully $opLabel " . number_format($amount) . " RWF to $walletLabel for User #$target_id.");
        } else {
            redirect_admin('secUsers', "Invalid amount or wallet operation specified.");
        }
    }
}

// Queries for Active Records
$deposits = $pdo->query("SELECT t.*, u.full_name, u.phone FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.type = 'deposit' AND COALESCE(t.is_archived,0) = 0 ORDER BY t.created_at DESC")->fetchAll();
$withdrawals = $pdo->query("SELECT t.*, u.full_name, u.phone as u_phone FROM transactions t JOIN users u ON t.user_id = u.id WHERE LOWER(t.type) IN ('withdraw','withdrawal') AND COALESCE(t.is_archived,0) = 0 ORDER BY t.created_at DESC")->fetchAll();
$investments = $pdo->query("SELECT i.*, u.full_name, p.name as plan_name, p.duration_days FROM investments i JOIN users u ON i.user_id = u.id JOIN investment_plans p ON i.plan_id = p.id WHERE COALESCE(i.is_archived,0) = 0 ORDER BY i.created_at DESC")->fetchAll();
$users = $pdo->query("SELECT * FROM users WHERE is_admin = 0 AND COALESCE(is_archived,0) = 0 ORDER BY created_at DESC")->fetchAll();

// Queries for Archived Records
$depositsArchived = $pdo->query("SELECT t.*, u.full_name, u.phone FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.type = 'deposit' AND COALESCE(t.is_archived,0) = 1 ORDER BY t.created_at DESC")->fetchAll();
$withdrawalsArchived = $pdo->query("SELECT t.*, u.full_name, u.phone as u_phone FROM transactions t JOIN users u ON t.user_id = u.id WHERE LOWER(t.type) IN ('withdraw','withdrawal') AND COALESCE(t.is_archived,0) = 1 ORDER BY t.created_at DESC")->fetchAll();
$investmentsArchived = $pdo->query("SELECT i.*, u.full_name, p.name as plan_name, p.duration_days FROM investments i JOIN users u ON i.user_id = u.id JOIN investment_plans p ON i.plan_id = p.id WHERE COALESCE(i.is_archived,0) = 1 ORDER BY i.created_at DESC")->fetchAll();
$usersArchived = $pdo->query("SELECT * FROM users WHERE is_admin = 0 AND COALESCE(is_archived,0) = 1 ORDER BY created_at DESC")->fetchAll();

$activeUsersCount = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM investments WHERE LOWER(status) = 'active' AND COALESCE(is_archived,0) = 0")->fetchColumn();
$pendingDepositsCount = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE type = 'deposit' AND status = 'pending' AND COALESCE(is_archived,0) = 0")->fetchColumn();
$pendingWithdrawalsCount = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE LOWER(type) IN ('withdraw','withdrawal') AND status = 'pending' AND COALESCE(is_archived,0) = 0")->fetchColumn();
$activeInvestmentCount = (int)$pdo->query("SELECT COUNT(*) FROM investments WHERE LOWER(status) = 'active' AND COALESCE(is_archived,0) = 0")->fetchColumn();

// Count total unread user messages for Admin notification indicator
$unreadUserMsgs = (int)$pdo->query("SELECT COUNT(*) FROM support_messages WHERE sender_type = 'user' AND is_read = 0")->fetchColumn();

// Fetch Support Chat Threads List (all registered users mapped with unread counts)
$chatThreads = $pdo->query("SELECT u.id as user_id, u.full_name, u.phone, 
    MAX(m.created_at) as last_msg_time,
    SUM(CASE WHEN m.sender_type = 'user' AND m.is_read = 0 THEN 1 ELSE 0 END) as unread_cnt
    FROM users u 
    LEFT JOIN support_messages m ON u.id = m.user_id 
    WHERE u.is_admin = 0 AND COALESCE(u.is_archived,0) = 0
    GROUP BY u.id, u.full_name, u.phone 
    ORDER BY last_msg_time DESC, u.id DESC")->fetchAll();

$activeChatUser = intval($_GET['user_id'] ?? ($chatThreads[0]['user_id'] ?? 0));
$activeChatMessages = [];
$activeUserInfo = null;

if ($activeChatUser > 0) {
    if ($current_sec === 'secChat') {
        try {
            $updReadAdmin = $pdo->prepare("UPDATE support_messages SET is_read = 1 WHERE user_id = ? AND sender_type = 'user' AND is_read = 0");
            $updReadAdmin->execute([$activeChatUser]);
        } catch (Throwable $e) {}
    }

    $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $uStmt->execute([$activeChatUser]);
    $activeUserInfo = $uStmt->fetch();

    $mStmt = $pdo->prepare("SELECT * FROM support_messages WHERE user_id = ? ORDER BY created_at ASC");
    $mStmt->execute([$activeChatUser]);
    $activeChatMessages = $mStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#173c38">
    <title>Operations | NOVATOLO</title>
    <link rel="icon" href="brand-icon.svg" type="image/svg+xml">
    <link rel="manifest" href="site.webmanifest">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Newsreader:opsz,wght@6..72,500;6..72,600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        html, body { width: 100%; max-width: 100vw; overflow-x: hidden; background: #F0F4F9; color: #0F172A; padding-bottom: 60px; }
        .navbar { background: #1E40AF; color: #FFF; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50; width: 100%; box-shadow: 0 4px 12px rgba(30, 64, 175, 0.15); }
        .brand { font-size: 16px; font-weight: 800; color: #FFF; display: flex; align-items: center; gap: 8px; }
        .brand span { color: #93C5FD; font-size: 13px; font-weight: 600; }
        .container { max-width: 1200px; margin: 16px auto; padding: 0 12px; width: 100%; }
        
        .admin-nav { display: flex; gap: 6px; margin-bottom: 16px; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 4px; }
        .admin-nav-btn { padding: 9px 14px; border: 1px solid #BFDBFE; background: #FFF; color: #1E3A8A; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 6px; position: relative; }
        .admin-nav-btn.active { background: #2563EB; color: #FFF; border-color: #2563EB; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); }

        .admin-nav-btn.chat-tab-highlight {
            background: linear-gradient(135deg, #1D4ED8, #2563EB);
            color: #FFF;
            border: 2px solid #93C5FD;
            animation: chatHighlightPulse 2s infinite;
        }
        .admin-nav-btn.chat-tab-highlight.active {
            background: #1E3A8A;
            border-color: #F59E0B;
            box-shadow: 0 0 15px rgba(245, 158, 11, 0.5);
        }
        @keyframes chatHighlightPulse {
            0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.5); }
            70% { box-shadow: 0 0 0 8px rgba(37, 99, 235, 0); }
            100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
        }

        .admin-section { display: none; background: #FFF; border: 1px solid #DBEAFE; border-radius: 12px; padding: 18px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(15, 23, 42, 0.03); }
        .admin-section.active { display: block; }

        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
        .section-header h3 { color: #1E40AF; font-weight: 800; }
        .search-box input { padding: 6px 10px; border: 1px solid #93C5FD; border-radius: 6px; font-size: 12px; outline: none; }

        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 12px; white-space: nowrap; }
        th, td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #E0E7FF; }
        th { background: #EFF6FF; color: #1E40AF; font-weight: 700; }

        .btn-act { padding: 5px 10px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 11px; display: inline-block; border: none; cursor: pointer; }
        .btn-green { background: #10B981; color: #FFF; }
        .btn-blue { background: #2563EB; color: #FFF; }
        .btn-yellow { background: #F59E0B; color: #FFF; }
        .btn-red { background: #EF4444; color: #FFF; }
        .btn-purple { background: #7C3AED; color: #FFF; }
        
        .status-badge { padding: 3px 6px; border-radius: 4px; font-weight: 700; font-size: 10px; display: inline-block; }
        .status-active { background: #DBEAFE; color: #1E40AF; }
        .status-finished { background: #DCFCE7; color: #15803D; }
        .status-pending { background: #FEF3C7; color: #D97706; }
        
        .time-countdown { font-family: monospace; font-weight: 700; color: #2563EB; font-size: 11px; }

        .balance-adjust-form { display: flex; align-items: center; gap: 4px; flex-wrap: nowrap; }
        .balance-adjust-form select, .balance-adjust-form input { padding: 3px 6px; border: 1px solid #93C5FD; border-radius: 4px; font-size: 11px; }

        .admin-chat-badge { display: inline-flex; align-items: center; justify-content: center; background: #EF4444; color: #FFF; font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 99px; margin-left: 4px; animation: adminPulse 1.2s infinite; }
        @keyframes adminPulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .chat-grid { display: grid; grid-template-columns: 280px 1fr; gap: 16px; min-height: 480px; }
        .chat-threads { border-right: 1px solid #DBEAFE; padding-right: 12px; }
        .thread-card { padding: 10px; border-radius: 8px; border: 1px solid #DBEAFE; margin-bottom: 8px; cursor: pointer; background: #EFF6FF; }
        .thread-card.active { background: #2563EB; color: #FFF; border-color: #2563EB; }
        .thread-card.active small { color: #BFDBFE !important; }
        .chat-view { display: flex; flex-direction: column; height: 100%; }
        .chat-history { flex: 1; padding: 12px; background: #F8FAFC; border: 1px solid #DBEAFE; border-radius: 8px; overflow-y: auto; max-height: 340px; display: flex; flex-direction: column; gap: 8px; }
        
        .chat-reply-box { margin-top: 10px; display: flex; flex-direction: column; gap: 8px; background: #F8FAFC; padding: 10px; border: 1px solid #DBEAFE; border-radius: 8px; }
        .chat-reply-controls { display: flex; gap: 8px; align-items: center; }
        .chat-reply-box textarea { flex: 1; border: 1px solid #93C5FD; border-radius: 6px; padding: 8px; font-size: 12px; outline: none; resize: none; height: 45px; }

        .archived-panel { display: none; background: #F8FAFC; border: 1px dashed #93C5FD; padding: 14px; border-radius: 8px; margin-top: 12px; }
        .archived-panel.active { display: block; }

        @media(max-width: 768px) {
            .chat-grid { grid-template-columns: 1fr; }
            .chat-threads { border-right: none; border-bottom: 1px solid #DBEAFE; padding-bottom: 12px; margin-bottom: 12px; }
        }
    </style>
    <link rel="stylesheet" href="brand.css">
</head>
<body>

<div class="navbar admin-navbar">
    <div class="brand"><span class="admin-brand-mark"><img src="brand-icon.svg" alt="" width="28" height="28"></span>NOVATOLO <span>Operations</span></div>
    <div style="display:flex; align-items:center; gap:8px;">
        <button type="button" class="btn-act btn-blue app-install" data-install-app title="Install NOVATOLO"><i data-lucide="download" size="14"></i></button>
        <a href="logout.php" class="btn-act btn-red">Logout</a>
    </div>
</div>

<div class="container">
    <?php if ($message): ?><div style="padding:10px; background:#EFF6FF; border:1px solid #93C5FD; color:#1E40AF; border-radius:8px; margin-bottom:14px; text-align:center; font-size:12px; font-weight:700;"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="admin-overview">
        <div><span>Pending deposits</span><strong><?= number_format($pendingDepositsCount) ?></strong></div>
        <div><span>Pending withdrawals</span><strong><?= number_format($pendingWithdrawalsCount) ?></strong></div>
        <div><span>Active investments</span><strong><?= number_format($activeInvestmentCount) ?></strong></div>
        <div><span>Active investors</span><strong><?= number_format((int)$activeUsersCount) ?></strong></div>
    </div>

    <div class="admin-nav">
        <button class="admin-nav-btn" id="tab_secDep" onclick="showSection('secDep', this)"><i data-lucide="arrow-down-to-line" size="14"></i> Deposits</button>
        <button class="admin-nav-btn" id="tab_secWith" onclick="showSection('secWith', this)"><i data-lucide="arrow-up-from-line" size="14"></i> Withdrawals</button>
        <button class="admin-nav-btn" id="tab_secInv" onclick="showSection('secInv', this)"><i data-lucide="chart-no-axes-combined" size="14"></i> Investments</button>
        <button class="admin-nav-btn" id="tab_secUsers" onclick="showSection('secUsers', this)"><i data-lucide="users" size="14"></i> Registered Users</button>
        <button class="admin-nav-btn chat-tab-highlight" id="tab_secChat" onclick="showSection('secChat', this)">
            <i data-lucide="messages-square" size="14"></i> Support
            <?php if ($unreadUserMsgs > 0): ?><span class="admin-chat-badge"><?= $unreadUserMsgs ?></span><?php endif; ?>
        </button>
    </div>

    <!-- 1. Deposits -->
    <div id="secDep" class="admin-section active">
        <form method="POST">
            <input type="hidden" name="target_table" value="transactions">
            <input type="hidden" name="active_sec" value="secDep">
            <div class="section-header">
                <h3>📥 Active Deposit Requests</h3>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <button type="button" class="btn-act btn-purple" onclick="toggleArchived('secDepArchived')">📦 View Archived (<?= count($depositsArchived) ?>)</button>
                    <button type="submit" name="bulk_action" value="archive" class="btn-act btn-yellow" onclick="return confirm('Archive selected?')">📦 Archive</button>
                    <button type="submit" name="bulk_action" value="delete" class="btn-act btn-red" onclick="return confirm('Delete selected?')">🗑️ Delete Selected</button>
                    <div class="search-box"><input type="text" onkeyup="filterTable(this, 'tblDep')" placeholder="Search..."></div>
                </div>
            </div>
            <div class="table-responsive">
                <table id="tblDep">
                    <thead>
                        <tr>
                            <th><input type="checkbox" onclick="selectAll(this, 'tblDep')"></th>
                            <th>User Name</th><th>Phone</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deposits as $d): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_ids[]" value="<?= $d['id'] ?>"></td>
                                <td><strong><?= htmlspecialchars($d['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($d['phone']) ?></td>
                                <td style="color:#10B981; font-weight:700;"><?= number_format($d['amount']) ?> RWF</td>
                                <td><?= strtoupper($d['status']) ?></td>
                                <td><?= $d['created_at'] ?></td>
                                <td>
                                    <?php if ($d['status'] === 'pending'): ?>
                                        <a href="admin.php?action=approve_dep&id=<?= $d['id'] ?>&sec=secDep" class="btn-act btn-green">Approve</a>
                                        <a href="admin.php?action=reject_dep&id=<?= $d['id'] ?>&sec=secDep" class="btn-act btn-red">Reject</a>
                                    <?php else: ?><span>Processed</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <div id="secDepArchived" class="archived-panel">
            <form method="POST">
                <input type="hidden" name="target_table" value="transactions">
                <input type="hidden" name="active_sec" value="secDep">
                <div class="section-header">
                    <h4>📦 Archived Deposits (<?= count($depositsArchived) ?>)</h4>
                    <div style="display:flex; gap:6px;">
                        <button type="submit" name="bulk_action" value="unarchive" class="btn-act btn-green">Unarchive Selected</button>
                        <div class="search-box"><input type="text" onkeyup="filterTable(this, 'tblDepArchived')" placeholder="Search archived..."></div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="tblDepArchived">
                        <thead>
                            <tr>
                                <th><input type="checkbox" onclick="selectAll(this, 'tblDepArchived')"></th>
                                <th>User Name</th><th>Phone</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($depositsArchived as $da): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_ids[]" value="<?= $da['id'] ?>"></td>
                                    <td><strong><?= htmlspecialchars($da['full_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($da['phone']) ?></td>
                                    <td><?= number_format($da['amount']) ?> RWF</td>
                                    <td><?= strtoupper($da['status']) ?></td>
                                    <td><?= $da['created_at'] ?></td>
                                    <td>
                                        <a href="admin.php?action=unarchive&table=transactions&id=<?= $da['id'] ?>&sec=secDep" class="btn-act btn-green">Unarchive</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. Withdrawals -->
    <div id="secWith" class="admin-section">
        <form method="POST">
            <input type="hidden" name="target_table" value="transactions">
            <input type="hidden" name="active_sec" value="secWith">
            <div class="section-header">
                <h3>📤 Active Withdrawal Requests</h3>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <button type="button" class="btn-act btn-purple" onclick="toggleArchived('secWithArchived')">📦 View Archived (<?= count($withdrawalsArchived) ?>)</button>
                    <button type="submit" name="bulk_action" value="archive" class="btn-act btn-yellow" onclick="return confirm('Archive selected?')">📦 Archive</button>
                    <button type="submit" name="bulk_action" value="delete" class="btn-act btn-red" onclick="return confirm('Delete selected?')">🗑️ Delete Selected</button>
                    <div class="search-box"><input type="text" onkeyup="filterTable(this, 'tblWith')" placeholder="Search..."></div>
                </div>
            </div>
            <div class="table-responsive">
                <table id="tblWith">
                    <thead>
                        <tr>
                            <th><input type="checkbox" onclick="selectAll(this, 'tblWith')"></th>
                            <th>User Name</th><th>Phone</th><th>Recipient Name</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($withdrawals as $w): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_ids[]" value="<?= $w['id'] ?>"></td>
                                <td><strong><?= htmlspecialchars($w['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($w['phone'] ?? $w['u_phone']) ?></td>
                                <td><?= htmlspecialchars($w['recipient_name'] ?? "") ?></td>
                                <td style="color:#DC2626; font-weight:700;"><?= number_format($w['amount']) ?> RWF</td>
                                <td><?= strtoupper($w['status']) ?></td>
                                <td><?= $w['created_at'] ?></td>
                                <td>
                                    <?php if ($w['status'] === 'pending'): ?>
                                        <a href="admin.php?action=approve_with&id=<?= $w['id'] ?>&sec=secWith" class="btn-act btn-green" onclick="return confirm('Approve this withdrawal?')">Approve Pay</a>
                                        <a href="admin.php?action=reject_with&id=<?= $w['id'] ?>&sec=secWith" class="btn-act btn-red" onclick="return confirm('Reject this withdrawal?')">Reject</a>
                                    <?php else: ?><span>Processed</span><?php endif; ?>
                                    <a href="admin.php?action=delete_with&id=<?= $w['id'] ?>&sec=secWith" class="btn-act btn-red" onclick="return confirm('Delete withdrawal permanently?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <div id="secWithArchived" class="archived-panel">
            <form method="POST">
                <input type="hidden" name="target_table" value="transactions">
                <input type="hidden" name="active_sec" value="secWith">
                <div class="section-header">
                    <h4>📦 Archived Withdrawals (<?= count($withdrawalsArchived) ?>)</h4>
                    <div style="display:flex; gap:6px;">
                        <button type="submit" name="bulk_action" value="unarchive" class="btn-act btn-green">Unarchive Selected</button>
                        <div class="search-box"><input type="text" onkeyup="filterTable(this, 'tblWithArchived')" placeholder="Search archived..."></div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="tblWithArchived">
                        <thead>
                            <tr>
                                <th><input type="checkbox" onclick="selectAll(this, 'tblWithArchived')"></th>
                                <th>User Name</th><th>Phone</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($withdrawalsArchived as $wa): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_ids[]" value="<?= $wa['id'] ?>"></td>
                                    <td><strong><?= htmlspecialchars($wa['full_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($wa['phone'] ?? $wa['u_phone']) ?></td>
                                    <td><?= number_format($wa['amount']) ?> RWF</td>
                                    <td><?= strtoupper($wa['status']) ?></td>
                                    <td><?= $wa['created_at'] ?></td>
                                    <td>
                                        <a href="admin.php?action=unarchive&table=transactions&id=<?= $wa['id'] ?>&sec=secWith" class="btn-act btn-green">Unarchive</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. Active Investments -->
    <div id="secInv" class="admin-section">
        <form method="POST">
            <input type="hidden" name="target_table" value="investments">
            <input type="hidden" name="active_sec" value="secInv">
            <div class="section-header">
                <h3>🚀 Active Investments</h3>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <button type="button" class="btn-act btn-purple" onclick="toggleArchived('secInvArchived')">📦 View Archived (<?= count($investmentsArchived) ?>)</button>
                    <button type="submit" name="bulk_action" value="archive" class="btn-act btn-yellow" onclick="return confirm('Archive selected?')">📦 Archive</button>
                    <button type="submit" name="bulk_action" value="delete" class="btn-act btn-red" onclick="return confirm('Delete selected?')">🗑️ Delete Selected</button>
                </div>
            </div>
            <div class="table-responsive">
                <table id="tblInv">
                    <thead>
                        <tr>
                            <th><input type="checkbox" onclick="selectAll(this, 'tblInv')"></th>
                            <th>User Name</th><th>Plan</th><th>Amount</th><th>Total Return</th><th>Remaining Time</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($investments as $inv): $duration = max(1, intval($inv['duration_days'] ?? 0)); $statusStr = strtolower($inv['status']); $totalReturn = (float)$inv['amount'] + (float)$inv['total_profit'];
                            $startDate = !empty($inv['last_payout_date']) ? (string)$inv['last_payout_date'] : (string)$inv['created_at'];
                            $startTs = strtotime($startDate . (strlen($startDate) === 10 ? ' 00:00:00' : '')); $endTs = $startTs + ($duration * 86400);
                        ?>
                            <tr>
                                <td><input type="checkbox" name="selected_ids[]" value="<?= $inv['id'] ?>"></td>
                                <td><strong><?= htmlspecialchars($inv['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($inv['plan_name']) ?></td>
                                <td><?= number_format($inv['amount']) ?> RWF</td>
                                <td style="color:#10B981; font-weight:700;"><?= number_format($totalReturn) ?> RWF</td>
                                <td>
                                    <?php if ($statusStr === 'active'): ?>
                                        <span class="admin-timer time-countdown" data-end="<?= $endTs ?>">Calculating...</span>
                                    <?php else: ?>
                                        <strong>Finished</strong>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-badge status-<?= $statusStr ?>"><?= strtoupper($statusStr) ?></span></td>
                                <td>
                                    <?php if ($inv['status'] === 'pending'): ?>
                                        <a href="admin.php?action=approve_inv&id=<?= $inv['id'] ?>&sec=secInv" class="btn-act btn-green">Approve</a>
                                    <?php endif; ?>
                                    <a href="admin.php?action=delete_inv&id=<?= $inv['id'] ?>&sec=secInv" class="btn-act btn-red" onclick="return confirm('Delete investment?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <div id="secInvArchived" class="archived-panel">
            <form method="POST">
                <input type="hidden" name="target_table" value="investments">
                <input type="hidden" name="active_sec" value="secInv">
                <div class="section-header">
                    <h4>📦 Archived Investments (<?= count($investmentsArchived) ?>)</h4>
                    <div style="display:flex; gap:6px;">
                        <button type="submit" name="bulk_action" value="unarchive" class="btn-act btn-green">Unarchive Selected</button>
                        <div class="search-box"><input type="text" onkeyup="filterTable(this, 'tblInvArchived')" placeholder="Search archived..."></div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="tblInvArchived">
                        <thead>
                            <tr>
                                <th><input type="checkbox" onclick="selectAll(this, 'tblInvArchived')"></th>
                                <th>User Name</th><th>Plan</th><th>Amount</th><th>Status</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($investmentsArchived as $ia): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_ids[]" value="<?= $ia['id'] ?>"></td>
                                    <td><strong><?= htmlspecialchars($ia['full_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($ia['plan_name']) ?></td>
                                    <td><?= number_format($ia['amount']) ?> RWF</td>
                                    <td><?= strtoupper($ia['status']) ?></td>
                                    <td>
                                        <a href="admin.php?action=unarchive&table=investments&id=<?= $ia['id'] ?>&sec=secInv" class="btn-act btn-green">Unarchive</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. Registered Users -->
    <div id="secUsers" class="admin-section">
        <form method="POST">
            <input type="hidden" name="target_table" value="users">
            <input type="hidden" name="active_sec" value="secUsers">
            <div class="section-header">
                <h3>👥 Registered Users (<?= count($users) ?>)</h3>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <button type="button" class="btn-act btn-purple" onclick="toggleArchived('secUsersArchived')">📦 View Archived Users (<?= count($usersArchived) ?>)</button>
                    <button type="submit" name="bulk_action" value="archive" class="btn-act btn-yellow" onclick="return confirm('Archive selected users?')">📦 Archive</button>
                    <button type="submit" name="bulk_action" value="delete" class="btn-act btn-red" onclick="return confirm('Delete selected users permanently?')">🗑️ Delete Selected</button>
                    <div class="search-box"><input type="text" onkeyup="filterTable(this, 'tblUsers')" placeholder="Search users..."></div>
                </div>
            </div>
            <div class="table-responsive">
                <table id="tblUsers">
                    <thead>
                        <tr>
                            <th><input type="checkbox" onclick="selectAll(this, 'tblUsers')"></th>
                            <th>ID</th><th>Full Name</th><th>Phone</th><th>Password Hash / Reset</th><th>Deposit Balance</th><th>Earning Balance</th><th>Adjust Amount</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_ids[]" value="<?= $u['id'] ?>"></td>
                                <td>#<?= $u['id'] ?></td>
                                <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($u['phone']) ?></td>
                                <td>
                                    <form method="POST" style="display:inline-flex; gap:4px; align-items:center;">
                                        <input type="hidden" name="admin_action" value="change_password">
                                        <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                        <input type="text" name="new_password" placeholder="New Pass" required style="width:75px; padding:3px 5px; font-size:10px; border:1px solid #93C5FD; border-radius:4px;">
                                        <button type="submit" class="btn-act btn-blue" style="font-size:10px; padding:3px 6px;">Set</button>
                                    </form>
                                </td>
                                <td style="font-weight:700; color:#2563EB;"><?= number_format($u['balance']) ?> RWF</td>
                                <td style="color:#10B981; font-weight:700;"><?= number_format($u['profit_balance']) ?> RWF</td>
                                <td>
                                    <form method="POST" class="balance-adjust-form">
                                        <input type="hidden" name="admin_action" value="adjust_balance">
                                        <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                        <select name="wallet_type">
                                            <option value="profit_balance">Earning</option>
                                            <option value="balance">Deposit</option>
                                        </select>
                                        <select name="operation">
                                            <option value="add">+</option>
                                            <option value="remove">-</option>
                                        </select>
                                        <input type="number" name="amount" placeholder="Amount" min="1" required style="width:75px;">
                                        <button type="submit" class="btn-act btn-blue">Save</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Delete user permanently?')">
                                        <input type="hidden" name="admin_action" value="remove_user">
                                        <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn-act btn-red">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <div id="secUsersArchived" class="archived-panel">
            <form method="POST">
                <input type="hidden" name="target_table" value="users">
                <input type="hidden" name="active_sec" value="secUsers">
                <div class="section-header">
                    <h4>📦 Archived Users (<?= count($usersArchived) ?>)</h4>
                    <div style="display:flex; gap:6px;">
                        <button type="submit" name="bulk_action" value="unarchive" class="btn-act btn-green">Unarchive Selected</button>
                        <div class="search-box"><input type="text" onkeyup="filterTable(this, 'tblUsersArchived')" placeholder="Search archived users..."></div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="tblUsersArchived">
                        <thead>
                            <tr>
                                <th><input type="checkbox" onclick="selectAll(this, 'tblUsersArchived')"></th>
                                <th>ID</th><th>Full Name</th><th>Phone</th><th>Deposit Balance</th><th>Earning Balance</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usersArchived as $ua): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_ids[]" value="<?= $ua['id'] ?>"></td>
                                    <td>#<?= $ua['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($ua['full_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($ua['phone']) ?></td>
                                    <td><?= number_format($ua['balance']) ?> RWF</td>
                                    <td><?= number_format($ua['profit_balance']) ?> RWF</td>
                                    <td>
                                        <a href="admin.php?action=unarchive&table=users&id=<?= $ua['id'] ?>&sec=secUsers" class="btn-act btn-green">Unarchive</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <!-- 5. Support Messages Tab -->
    <div id="secChat" class="admin-section">
        <h3>💬 NOVATOLO Live Support Messages & Broadcasting</h3>
        
        <div style="background:#EFF6FF; border:1px solid #BFDBFE; padding:12px; border-radius:8px; margin:12px 0; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <strong>Quick Messaging Controls:</strong>
            <button type="button" class="btn-act btn-blue" onclick="setBroadcastMode('all')">📢 Message All Registered Users (<?= count($users) ?>)</button>
            <button type="button" class="btn-act btn-purple" onclick="toggleMultiSelect()">☑️ Message Selected Users</button>
            <button type="button" class="btn-act btn-green" onclick="setBroadcastMode('single')" id="singleModeBtn" style="display:none;">💬 Back to Single Chat</button>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="admin_reply_msg" value="1">
            <input type="hidden" name="target_mode" id="targetModeInput" value="single">
            <input type="hidden" name="chat_user_id" id="chatUserIdInput" value="<?= $activeChatUser ?>">
            <input type="hidden" name="admin_audio_blob_data" id="adminAudioBlobData">

            <div class="chat-grid" style="margin-top:10px;">
                <div class="chat-threads">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <strong>Registered Users</strong>
                        <span id="selectedCountLabel" style="font-size:11px; color:#2563EB; font-weight:bold; display:none;">0 selected</span>
                    </div>
                    <div style="margin-top:10px; max-height:400px; overflow-y:auto;">
                        <?php if (empty($chatThreads)): ?>
                            <p style="font-size:12px; color:#6b7280;">No registered users found.</p>
                        <?php else: ?>
                            <div id="multiUserSelectList" style="display:none;">
                                <div style="padding:6px; background:#EFF6FF; border-bottom:1px solid #DBEAFE; display:flex; align-items:center; gap:8px;">
                                    <input type="checkbox" id="selectAllUsersCb" onclick="toggleSelectAllUsers(this)">
                                    <span style="font-size:11px; font-weight:bold;">Select All Users</span>
                                </div>
                                <?php foreach ($chatThreads as $us): ?>
                                    <div style="padding:6px; border-bottom:1px solid #DBEAFE; display:flex; align-items:center; gap:8px;">
                                        <input type="checkbox" name="selected_chat_users[]" value="<?= $us['user_id'] ?>" class="user-multi-cb" onchange="updateSelectedCount()">
                                        <span style="font-size:11px;"><strong><?= htmlspecialchars($us['full_name']) ?></strong> (<?= htmlspecialchars($us['phone']) ?>)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div id="regularThreadsList">
                                <?php foreach ($chatThreads as $ct): ?>
                                    <div class="thread-card <?= $ct['user_id'] == $activeChatUser ? 'active' : '' ?>" onclick="window.location.href='admin.php?sec=secChat&user_id=<?= $ct['user_id'] ?>'">
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <strong><?= htmlspecialchars($ct['full_name']) ?></strong>
                                            <?php if (!empty($ct['unread_cnt']) && $ct['unread_cnt'] > 0): ?>
                                                <span class="admin-chat-badge"><?= $ct['unread_cnt'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <small style="color:#2563eb; font-weight:bold;">Phone: <?= htmlspecialchars($ct['phone']) ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="chat-view">
                    <?php if ($activeUserInfo || true): ?>
                        <div style="padding-bottom:8px; border-bottom:1px solid #DBEAFE; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px;">
                            <div>
                                <h4 id="chatHeaderTitle">Chatting with: <?= $activeUserInfo ? htmlspecialchars($activeUserInfo['full_name']) : 'Select a User' ?></h4>
                                <span id="chatHeaderSub" style="font-size:12px; color:#2563eb; font-weight:bold;">Phone: <?= $activeUserInfo ? htmlspecialchars($activeUserInfo['phone']) : '-' ?></span>
                            </div>
                            <?php if ($activeUserInfo): ?>
                                <a href="admin.php?action=clear_chat&user_id=<?= $activeChatUser ?>&sec=secChat" class="btn-act btn-red" onclick="return confirm('Clear chat history for this user?')">Clear Chat History</a>
                            <?php endif; ?>
                        </div>

                        <div class="chat-history" id="adminChatHistoryBox">
                            <?php if ($activeChatUser > 0 && !empty($activeChatMessages)): ?>
                              <?php foreach ($activeChatMessages as $m): ?>
    <div style="margin-bottom:6px; text-align: <?= $m['sender_type'] === 'admin' ? 'right' : 'left' ?>;">
        <div style="display:inline-block; padding:8px 12px; border-radius:8px; font-size:12px; background: <?= $m['sender_type'] === 'admin' ? '#2563eb' : '#fff' ?>; color: <?= $m['sender_type'] === 'admin' ? '#fff' : '#0f172a' ?>; border:1px solid #DBEAFE; text-align:left; max-width:85%;">
            <?php if (!empty($m['message'])): ?>
                <div id="msgText_<?= $m['id'] ?>"><?= htmlspecialchars($m['message']) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($m['file_path']) && $m['file_type'] === 'image'): ?>
                <div style="margin-top:6px;">
                    <a href="view.php?file=<?= htmlspecialchars(basename($m['file_path'])) ?>" target="_blank">
                        <img src="view.php?file=<?= htmlspecialchars(basename($m['file_path'])) ?>" style="max-width:180px; border-radius:6px; display:block;">
                    </a>
                    <a href="view.php?file=<?= htmlspecialchars(basename($m['file_path'])) ?>" download class="btn-act btn-blue" style="font-size:9px; margin-top:4px; padding:2px 6px;">Download Image</a>
                </div>
            <?php elseif (!empty($m['file_path']) && $m['file_type'] === 'audio'): ?>
                <div style="margin-top:6px;">
                    <audio controls style="height:32px; width:180px;">
                        <source src="view.php?file=<?= htmlspecialchars(basename($m['file_path'])) ?>" type="audio/webm">
                    </audio>
                    <br>
                    <a href="view.php?file=<?= htmlspecialchars(basename($m['file_path'])) ?>" download class="btn-act btn-blue" style="font-size:9px; margin-top:4px; padding:2px 6px;">Download Audio</a>
                </div>
            <?php endif; ?>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px; gap:8px;">
                <span style="font-size:8px; opacity:0.8;"><?= $m['created_at'] ?></span>
                <?php if ($m['sender_type'] === 'admin' && empty($m['is_deleted'])): ?>
                    <div style="font-size:9px;">
                        <a href="javascript:void(0);" onclick="enableAdminEdit(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['message'])) ?>')" style="color:#fde047; text-decoration:underline; margin-right:4px;">Edit</a>
                        <a href="admin.php?action=delete_msg&msg_id=<?= $m['id'] ?>&user_id=<?= $activeChatUser ?>&sec=secChat" onclick="return confirm('Delete this message?')" style="color:#fca5a5; text-decoration:underline;">Delete</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
                            <?php else: ?>
                                <p style="color:#6b7280; font-size:12px; text-align:center; padding:40px;">Select a registered user on the left, or choose Broadcast to send a message.</p>
                            <?php endif; ?>
                        </div>

                        <div class="chat-reply-box">
                            <div id="broadcastBanner" style="font-size:11px; font-weight:bold; color:#1D4ED8; background:#EFF6FF; padding:4px 8px; border-radius:4px; display:none;">
                                📢 Mode: Broadcasting message to ALL registered users.
                            </div>
                            <div id="selectedBanner" style="font-size:11px; font-weight:bold; color:#7C3AED; background:#F3E8FF; padding:4px 8px; border-radius:4px; display:none;">
                                👥 Mode: Messaging selected users.
                            </div>
                            <div class="chat-reply-controls">
                                <textarea name="reply_message" id="replyMessageInput" placeholder="Type message, announcement or attach photo/audio..."></textarea>
                                <button type="button" id="adminRecordBtn" class="btn-act" style="background:#EF4444; color:#FFF; padding:10px 12px;" onclick="toggleAdminAudioRecording()" title="Record Audio">🎤</button>
                                <label class="btn-act btn-purple" style="cursor:pointer; display:flex; align-items:center; gap:4px; padding:10px 12px;">
                                    📎 <input type="file" name="chat_file" accept="image/*,audio/*" style="display:none;" onchange="document.getElementById('fileNameSpan').innerText = this.files[0] ? this.files[0].name : ''">
                                </label>
                              <button type="submit" style="background-color: #2563eb; color: #ffffff; font-size: 16px; padding: 12px 24px; border: none; border-radius: 8px; font-weight: bold;">SEND</button>
                            </div>
                            <span id="fileNameSpan" style="font-size:10px; color:#10B981; font-weight:bold;"></span>
                            <div id="adminRecordingStatus" style="font-size:10px; color:#ef4444; font-weight:bold; display:none;">🔴 Recording audio... Click microphone again to stop.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

</div>

<!-- Admin Edit Message Modal -->
<div id="adminEditModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:20px; border-radius:10px; width:min(400px, 90%);">
        <h4>Edit Message</h4>
        <form method="POST">
            <input type="hidden" name="edit_msg_id" id="modalEditMsgId">
            <input type="hidden" name="target_user_id" value="<?= $activeChatUser ?>">
            <textarea name="edit_message_text" id="modalEditMsgText" style="width:100%; height:80px; margin:10px 0; padding:8px; border:1px solid #ccc; border-radius:6px;" required></textarea>
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn-act btn-red" onclick="document.getElementById('adminEditModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-act btn-blue">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    lucide.createIcons();

    let adminMediaRecorder;
    let adminAudioChunks = [];
    let adminIsRecording = false;

    function toggleAdminAudioRecording() {
        const recStatus = document.getElementById('adminRecordingStatus');
        const recBtn = document.getElementById('adminRecordBtn');
        
        if (!adminIsRecording) {
            navigator.mediaDevices.getUserMedia({ audio: true }).then(stream => {
                adminMediaRecorder = new MediaRecorder(stream);
                adminAudioChunks = [];
                
                adminMediaRecorder.ondataavailable = e => {
                    if (e.data.size > 0) adminAudioChunks.push(e.data);
                };
                
                adminMediaRecorder.onstop = () => {
                    const audioBlob = new Blob(adminAudioChunks, { type: 'audio/webm' });
                    const reader = new FileReader();
                    reader.readAsDataURL(audioBlob);
                    reader.onloadend = () => {
                        document.getElementById('adminAudioBlobData').value = reader.result;
                        document.getElementById('fileNameSpan').innerText = 'Recorded_Audio.webm';
                    };
                    stream.getTracks().forEach(track => track.stop());
                };
                
                adminMediaRecorder.start();
                adminIsRecording = true;
                recBtn.style.background = '#10b981';
                recStatus.style.display = 'block';
            }).catch(err => {
                alert('Microphone access denied or unavailable.');
            });
        } else {
            adminMediaRecorder.stop();
            adminIsRecording = false;
            recBtn.style.background = '#ef4444';
            recStatus.style.display = 'none';
        }
    }

    function enableAdminEdit(msgId, currentText) {
        document.getElementById('modalEditMsgId').value = msgId;
        document.getElementById('modalEditMsgText').value = currentText;
        document.getElementById('adminEditModal').style.display = 'flex';
    }

    function showSection(secId, btn) {
        document.querySelectorAll('.admin-section').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.admin-nav-btn').forEach(b => b.classList.remove('active'));
        
        const secEl = document.getElementById(secId);
        if (secEl) {
            secEl.classList.add('active');
        }
        if (btn) {
            btn.classList.add('active');
        } else {
            const tabBtn = document.getElementById('tab_' + secId);
            if (tabBtn) tabBtn.classList.add('active');
        }
        history.replaceState(null, null, 'admin.php?sec=' + secId + '#' + secId);
    }

    function setBroadcastMode(mode) {
        const modeInput = document.getElementById('targetModeInput');
        const broadcastBanner = document.getElementById('broadcastBanner');
        const selectedBanner = document.getElementById('selectedBanner');
        const singleBtn = document.getElementById('singleModeBtn');
        const multiList = document.getElementById('multiUserSelectList');
        const regList = document.getElementById('regularThreadsList');
        const countLbl = document.getElementById('selectedCountLabel');
        const headerTitle = document.getElementById('chatHeaderTitle');
        const headerSub = document.getElementById('chatHeaderSub');

        if (mode === 'all') {
            modeInput.value = 'all';
            broadcastBanner.style.display = 'block';
            selectedBanner.style.display = 'none';
            singleBtn.style.display = 'inline-block';
            multiList.style.display = 'none';
            regList.style.display = 'block';
            countLbl.style.display = 'none';
            headerTitle.innerText = 'Broadcasting to ALL Registered Users';
            headerSub.innerText = 'Message will be delivered to every registered user inbox.';
        } else if (mode === 'single') {
            modeInput.value = 'single';
            broadcastBanner.style.display = 'none';
            selectedBanner.style.display = 'none';
            singleBtn.style.display = 'none';
            multiList.style.display = 'none';
            regList.style.display = 'block';
            countLbl.style.display = 'none';
            headerTitle.innerText = 'Select a Registered User';
            headerSub.innerText = 'Choose from the left column.';
        }
    }

    function toggleMultiSelect() {
        const modeInput = document.getElementById('targetModeInput');
        const broadcastBanner = document.getElementById('broadcastBanner');
        const selectedBanner = document.getElementById('selectedBanner');
        const singleBtn = document.getElementById('singleModeBtn');
        const multiList = document.getElementById('multiUserSelectList');
        const regList = document.getElementById('regularThreadsList');
        const countLbl = document.getElementById('selectedCountLabel');
        const headerTitle = document.getElementById('chatHeaderTitle');
        const headerSub = document.getElementById('chatHeaderSub');

        modeInput.value = 'selected';
        broadcastBanner.style.display = 'none';
        selectedBanner.style.display = 'block';
        singleBtn.style.display = 'inline-block';
        multiList.style.display = 'block';
        regList.style.display = 'none';
        countLbl.style.display = 'inline';
        headerTitle.innerText = 'Messaging Selected Registered Users';
        headerSub.innerText = 'Check the boxes next to users you want to message.';
    }

    function toggleSelectAllUsers(master) {
        document.querySelectorAll('.user-multi-cb').forEach(cb => cb.checked = master.checked);
        updateSelectedCount();
    }

    function updateSelectedCount() {
        const checkedCount = document.querySelectorAll('.user-multi-cb:checked').length;
        document.getElementById('selectedCountLabel').innerText = checkedCount + ' selected';
    }

    function toggleArchived(panelId) {
        const panel = document.getElementById(panelId);
        if (panel) {
            panel.classList.toggle('active');
        }
    }

    function filterTable(input, tableId) {
        const filter = input.value.toLowerCase();
        const rows = document.querySelectorAll(`#${tableId} tbody tr`);
        rows.forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    }

    function selectAll(master, tableId) {
        const checkboxes = document.querySelectorAll(`#${tableId} tbody input[type="checkbox"]`);
        checkboxes.forEach(cb => cb.checked = master.checked);
    }

    function updateAdminTimers() {
        const now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('.admin-timer[data-end]').forEach(el => {
            const end = parseInt(el.getAttribute('data-end'));
            const diff = end - now;

            if (diff <= 0) {
                el.innerText = 'Finished';
                el.style.color = '#15803D';
                return;
            }

            const days = Math.floor(diff / 86400);
            const hours = Math.floor((diff % 86400) / 3600);
            const mins = Math.floor((diff % 3600) / 60);
            const secs = diff % 60;

            let str = '⏳ ';
            if (days > 0) str += days + 'd ';
            str += String(hours).padStart(2, '0') + 'h ' +
                   String(mins).padStart(2, '0') + 'm ' +
                   String(secs).padStart(2, '0') + 's left';

            el.innerText = str;
        });
    }

    setInterval(updateAdminTimers, 1000);
    updateAdminTimers();

    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const activeSec = urlParams.get('sec') || window.location.hash.replace('#', '') || 'secDep';
        showSection(activeSec, null);
        
        const chatHist = document.getElementById('adminChatHistoryBox');
        if (chatHist) chatHist.scrollTop = chatHist.scrollHeight;
    });
</script>
<script src="install.js" defer></script>
</body>
</html>