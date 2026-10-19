<?php
// register.php
session_start();
require_once 'db.php';

if (!empty($_GET['ref'])) {
    $_SESSION['ref_code'] = trim($_GET['ref']);
    setcookie('ref_code', $_SESSION['ref_code'], time() + (86400 * 30), "/");
} elseif (empty($_SESSION['ref_code']) && !empty($_COOKIE['ref_code'])) {
    $_SESSION['ref_code'] = $_COOKIE['ref_code'];
}

$ref_code_param = $_SESSION['ref_code'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone     = trim($_POST['phone']);
    $password  = trim($_POST['password']);
    $ref_by    = trim($_POST['ref_by'] ?? '');

    if (empty($full_name) || empty($phone) || empty($password)) {
        $error = "Nyamuneka uzuze imyanya yose n'agaciro kabyo.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $error = "Iyi nimero ya terefone yarangije kwiyandikisha.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            $inviter_id = null;
            if (!empty($ref_by)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE ref_code = ?");
                $stmt->execute([$ref_by]);
                $inviter = $stmt->fetch();
                if ($inviter) {
                    $inviter_id = $inviter['id'];
                }
            }

            $stmt = $pdo->prepare("INSERT INTO users (full_name, phone, password, referred_by) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$full_name, $phone, $hashed_password, $inviter_id])) {
                unset($_SESSION['ref_code']);
                setcookie('ref_code', '', time() - 3600, "/");
                header("Location: login.php?registered=1&phone=" . urlencode($phone) . "&pass=" . urlencode($password));
                exit;
            } else {
                $error = "Kwiyandikisha ntibyakunze. Ongeera ugerageze.";
            }
        }
    }
}

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$site_url = $protocol . "://" . $_SERVER['HTTP_HOST'];
$logo_url = $site_url . "/logo.php";

$ref_query = !empty($ref_code_param) ? '?ref=' . rawurlencode($ref_code_param) : '';
?>
<!DOCTYPE html>
<html lang="rw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kwiyandikisha - NOVATOLO LTD</title>
    
    <meta property="og:title" content="NOVATOLO - Digital Platform">
    <meta property="og:description" content="Kwiyandikisha kuri NOVATOLO uze utangire kwakira inyungu ku buryo bwizewe.">
    <meta property="og:image" content="<?= $logo_url ?>">
    <meta property="og:type" content="website">

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        html, body { width: 100%; max-width: 100vw; overflow-x: hidden; background-color: #F8FAFC; color: #0F172A; min-height: 100vh; display: flex; flex-direction: column; }
        .navbar { background-color: #FFFFFF; border-bottom: 1px solid #E2E8F0; padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; width: 100%; box-shadow: 0 4px 15px rgba(15,23,42,0.03); }
        .brand-logo { display: flex; align-items: center; gap: 9px; text-decoration: none; font-weight: 800; font-size: 18px; color: #0F172A; }
        .logo-icon { background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%); color: #FFF; width: 36px; height: 36px; border-radius: 11px; display: flex; align-items: center; justify-content: center; box-shadow: 0 6px 16px rgba(37, 99, 235, 0.25); }
        
        .btn-more-info { display: flex; align-items: center; gap: 6px; background: #EFF6FF; border: 1px solid #BFDBFE; color: #1D4ED8; padding: 8px 14px; border-radius: 99px; text-decoration: none; font-size: 11px; font-weight: 800; transition: all 0.2s ease; }
        .btn-more-info:hover { background: #2563EB; color: #FFFFFF; }

        .main-container { flex: 1; display: flex; align-items: center; justify-content: center; padding: 28px 14px; width: 100%; }
        .auth-card { background: #FFFFFF; width: 100%; max-width: 440px; padding: 28px 22px; border-radius: 22px; border: 1px solid #E2E8F0; box-shadow: 0 18px 45px rgba(15,23,42,0.08); }
        
        .form-toggle-switch { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: #F1F5F9; padding: 4px; border-radius: 14px; margin-bottom: 22px; }
        .toggle-tab { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px; border-radius: 10px; font-size: 12px; font-weight: 800; text-decoration: none; color: #64748B; transition: all 0.2s ease; }
        .toggle-tab.active { background: #2563EB; color: #FFFFFF; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); }

        .card-brand-header { display: flex; flex-direction: column; align-items: center; margin-bottom: 18px; text-align: center; }
        .brand-subtitle { font-size: 10px; font-weight: 800; color: #2563EB; letter-spacing: 1.2px; text-transform: uppercase; margin-top: 4px; }

        .form-header { text-align: center; margin-bottom: 20px; }
        .form-header h2 { font-size: 20px; font-weight: 800; color: #0F172A; }
        .form-header p { font-size: 12px; color: #64748B; margin-top: 4px; }
        
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 11px; font-weight: 800; color: #334155; margin-bottom: 6px; text-transform: uppercase; }
        
        .password-wrapper { position: relative; width: 100%; }
        .password-wrapper input { width: 100%; padding: 12px 42px 12px 12px; background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 10px; font-size: 14px; outline: none; }
        .password-wrapper input:focus { border-color: #2563EB; background: #FFF; }
        .toggle-password-btn {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #64748B; cursor: pointer;
            display: flex; align-items: center; justify-content: center; padding: 4px;
        }

        input[type="text"] { width: 100%; padding: 12px; background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 10px; font-size: 14px; outline: none; }
        input[type="text"]:focus { border-color: #2563EB; background: #FFF; }
        .btn-submit { width: 100%; padding: 14px; background: linear-gradient(135deg, #2563EB, #1D4ED8); border: none; border-radius: 10px; color: #FFFFFF; font-weight: 800; font-size: 15px; cursor: pointer; margin-top: 6px; box-shadow: 0 6px 18px rgba(37, 99, 235, 0.25); }
        .alert-error { background-color: #FEF2F2; border: 1px solid #FECDD3; color: #B91C1C; font-size: 12px; padding: 12px; border-radius: 10px; margin-bottom: 16px; text-align: center; font-weight: 700; }
    </style>
</head>
<body>

<header class="navbar">
    <a href="index.php<?= $ref_query ?>" class="brand-logo">
        <div class="logo-icon"><i data-lucide="trending-up" size="18"></i></div>
        <span>NOVATOLO</span>
    </a>
    <a href="index.php<?= $ref_query ?>" class="btn-more-info">
        <i data-lucide="info" size="14"></i> More Info
    </a>
</header>

<main class="main-container">
    <div class="auth-card">
        <!-- Top Action Toggle Buttons -->
        <div class="form-toggle-switch">
            <a href="register.php<?= $ref_query ?>" class="toggle-tab active"><i data-lucide="user-plus" size="14"></i> KWIYANDIKISHA</a>
            <a href="login.php<?= $ref_query ?>" class="toggle-tab"><i data-lucide="log-in" size="14"></i> KWINJIRA</a>
        </div>

        <!-- NOVATOLO LTD Logo Header -->
        <div class="card-brand-header">
            <div class="logo-icon" style="width: 48px; height: 48px; border-radius: 14px;">
                <i data-lucide="trending-up" size="26"></i>
            </div>
            <span style="font-size: 22px; font-weight: 800; margin-top: 6px;">NOVATOLO LTD</span>
            <span class="brand-subtitle">Digital Growth Platform</span>
        </div>

        <div class="form-header">
            <h2>Fungura Konti Nshya</h2>
            <p>Iyandikishe kuri NOVATOLO Platform</p>
        </div>

        <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="ref_by" value="<?= htmlspecialchars($ref_code_param) ?>">
            
            <div class="form-group">
                <label for="full_name">Izina Ryose (Full Name)</label>
                <input type="text" id="full_name" name="full_name" placeholder="John Doe" required>
            </div>

            <div class="form-group">
                <label for="phone">Nimero yawe ya (Terefone)</label>
                <input type="text" id="phone" name="phone" placeholder="0788000000" required>
            </div>

            <div class="form-group">
                <label for="password">Ijambo ry'Ibanga (Password)</label>
                <div class="password-wrapper">
                    <input type="text" id="registerPasswordInput" name="password" placeholder="injiza ijambo ry'ibanga" required>
                    <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility('registerPasswordInput', this)">
                        <i data-lucide="eye" size="18"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit">Kwiyandikisha Kuri NOVATOLO</button>
        </form>
    </div>
</main>

<script>
    lucide.createIcons();

    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (input.type === "text") {
            input.type = "password";
            btn.innerHTML = '<i data-lucide="eye-off" size="18"></i>';
        } else {
            input.type = "text";
            btn.innerHTML = '<i data-lucide="eye" size="18"></i>';
        }
        lucide.createIcons();
    }
</script>
</body>
</html>