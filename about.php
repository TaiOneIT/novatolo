<?php
// about.php
session_start();
require_once 'db.php';

// Maintain Referral Persistence
if (!empty($_GET['ref'])) {
    $_SESSION['ref_code'] = trim($_GET['ref']);
    setcookie('ref_code', $_SESSION['ref_code'], time() + (86400 * 30), "/");
} elseif (empty($_SESSION['ref_code']) && !empty($_COOKIE['ref_code'])) {
    $_SESSION['ref_code'] = $_COOKIE['ref_code'];
}

$ref_code_param = $_SESSION['ref_code'] ?? '';
$ref_query = !empty($ref_code_param) ? '?ref=' . rawurlencode($ref_code_param) : '';

$user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#173c38">
    <title>About NOVATOLO</title>
    <link rel="icon" href="brand-icon.svg" type="image/svg+xml">
    <link rel="manifest" href="site.webmanifest">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Newsreader:opsz,wght@6..72,500;6..72,600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --bg-main: #F8FAFC;
            --text-dark: #0F172A;
            --text-muted: #64748B;
            --emerald-primary: #10B981;
            --emerald-hover: #059669;
            --emerald-soft: #ECFDF5;
            --card-border: #E2E8F0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        html, body { width: 100%; max-width: 100vw; overflow-x: hidden; background-color: var(--bg-main); color: var(--text-dark); min-height: 100vh; }
        
        .navbar { 
            background: #FFFFFF; 
            border-bottom: 1px solid var(--card-border); 
            padding: 8px 10px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            position: sticky; 
            top: 0; 
            z-index: 50; 
            width: 100%;
            gap: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
        .brand-logo { display: flex; align-items: center; gap: 5px; text-decoration: none; flex-shrink: 0; }
        .logo-icon { 
            background: linear-gradient(135deg, #10B981 0%, #059669 100%); 
            color: #FFF; 
            width: 28px; 
            height: 28px; 
            border-radius: 8px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            box-shadow: 0 3px 8px rgba(16, 185, 129, 0.25); 
        }
        .brand-name { font-size: 14px; font-weight: 800; color: var(--text-dark); letter-spacing: -0.4px; }

        .nav-center-menu { display: flex; align-items: center; gap: 2px; }
        .nav-link { 
            text-decoration: none; 
            color: var(--text-muted); 
            font-size: 11px; 
            font-weight: 700; 
            transition: all 0.2s ease; 
            display: flex; 
            align-items: center; 
            gap: 3px; 
            padding: 5px 8px;
            border-radius: 16px;
            white-space: nowrap;
        }
        .nav-link:hover { color: var(--emerald-primary); background: var(--emerald-soft); }
        .nav-link.active { color: var(--emerald-primary); background: var(--emerald-soft); border: 1px solid #A7F3D0; }

        .nav-right-auth { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
        .btn-auth-login { 
            display: flex; align-items: center; gap: 3px; border: 1px solid var(--card-border); 
            background: #FFF; padding: 5px 8px; border-radius: 16px; text-decoration: none; 
            color: var(--text-dark); font-size: 11px; font-weight: 700; transition: all 0.2s ease;
            white-space: nowrap;
        }
        .btn-auth-login:hover { background: #F1F5F9; border-color: #CBD5E1; }

        .btn-auth-signup { 
            display: flex; align-items: center; gap: 3px; border: none; 
            background: var(--emerald-primary); padding: 5px 8px; border-radius: 16px; 
            text-decoration: none; color: #FFF; font-size: 11px; font-weight: 700; transition: all 0.2s ease;
            box-shadow: 0 3px 8px rgba(16, 185, 129, 0.2);
            white-space: nowrap;
        }
        .btn-auth-signup:hover { background: var(--emerald-hover); }

        .container { max-width: 900px; margin: 16px auto; padding: 0 14px; width: 100%; min-height: calc(100vh - 280px); }
        
        .about-card { background: #FFF; border: 1px solid var(--card-border); border-radius: 16px; padding: 20px 16px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .about-card h1 { font-size: 20px; font-weight: 800; margin-bottom: 12px; line-height: 1.3; }
        .about-card p { font-size: 13px; color: var(--text-muted); line-height: 1.6; margin-bottom: 16px; }

        .feature-box { background: #F8FAFC; border: 1px solid var(--card-border); padding: 14px; border-radius: 12px; margin-bottom: 12px; }
        .feature-box h4 { font-size: 14px; font-weight: 800; color: var(--text-dark); margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }

        .btn-group-responsive { display: flex; flex-direction: column; gap: 10px; margin-top: 16px; }
        @media(min-width: 600px) { .btn-group-responsive { flex-direction: row; } }

        .btn-wa { display: flex; align-items: center; justify-content: center; gap: 8px; background: #25D366; color: #FFF; text-decoration: none; padding: 12px 20px; border-radius: 12px; font-weight: 800; font-size: 14px; width: 100%; text-align: center; }
        .btn-new-inv { display: flex; align-items: center; justify-content: center; gap: 8px; background: #F59E0B; color: #FFF; text-decoration: none; padding: 12px 20px; border-radius: 12px; font-weight: 800; font-size: 14px; width: 100%; text-align: center; }

        @media(max-width: 600px) { 
            .navbar { padding: 6px 6px; gap: 2px; }
            .brand-name { font-size: 12px; }
            .logo-icon { width: 24px; height: 24px; border-radius: 6px; }
            .nav-link { padding: 4px 6px; font-size: 10px; gap: 2px; }
            .btn-auth-login, .btn-auth-signup { padding: 4px 6px; font-size: 10px; gap: 2px; }
        }

        footer { 
            background: #FFFFFF; 
            border-top: 1px solid var(--card-border); 
            padding: 30px 16px; 
            color: var(--text-dark);
            width: 100%;
        }
        .footer-container { max-width: 1140px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; }
        .footer-col h4 { font-size: 15px; font-weight: 800; margin-bottom: 12px; }
        .footer-col p { font-size: 12px; color: var(--text-muted); line-height: 1.6; }
        .contact-list { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .contact-item { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text-dark); font-weight: 600; }
        
        .btn-whatsapp { 
            display: inline-flex; align-items: center; gap: 8px; 
            background: #25D366; color: #FFF; padding: 9px 14px; 
            border-radius: 10px; font-size: 12px; font-weight: 700; 
            text-decoration: none; margin-top: 8px; transition: background 0.2s ease;
        }
        .btn-whatsapp:hover { background: #1da851; }
        .footer-bottom { text-align: center; border-top: 1px solid var(--card-border); margin-top: 24px; padding-top: 16px; font-size: 11px; color: var(--text-muted); }
    </style>
    <link rel="stylesheet" href="brand.css">
</head>
<body>

<header class="navbar">
    <a href="index.php<?= $ref_query ?>" class="brand-logo">
        <div class="logo-icon"><img src="brand-icon.svg" alt="" width="22" height="22"></div>
        <span class="brand-name">NOVATOLO</span>
    </a>

    <div class="nav-center-menu">
        <a href="index.php<?= $ref_query ?>" class="nav-link"><i data-lucide="layers" size="13"></i> <span>Plans</span></a>
        <a href="about.php<?= $ref_query ?>" class="nav-link active"><i data-lucide="info" size="13"></i> <span>About</span></a>
    </div>

    <div class="nav-right-auth">
        <a href="login.php<?= $ref_query ?>" class="btn-auth-login"><i data-lucide="log-in" size="13"></i> Sign In</a>
        <a href="register.php<?= $ref_query ?>" class="btn-auth-signup"><i data-lucide="user-plus" size="13"></i> Sign Up</a>
    </div>
</header>

<div class="container">
    <div class="about-card">
        <h1>About NOVATOLO</h1>
        <p>NOVATOLO provides account tools for reviewing investment plans, tracking account activity, and contacting support. Plan terms and account activity are shown in the platform for members to review.</p>

        <div class="feature-box">
            <h4><i data-lucide="shield-check" color="#10B981" size="18"></i> How Plans Work & Yield Interest</h4>
            <p style="margin:0; font-size:12px;">Each plan shows its rate, term, minimum amount, and estimated return. Once an investment is active, its status and maturity are tracked in your account. Displayed estimates are not a guarantee of future results.</p>
        </div>

        <div class="feature-box">
            <h4><i data-lucide="gift" color="#F59E0B" size="18"></i> Referral Income</h4>
            <p style="margin:0; font-size:12px;">Referral rewards may be available when an invited member makes qualifying approved deposits. Check your account for the current reward status and applicable terms.</p>
        </div>

        <div style="margin-top:20px;">
            <h3 style="font-size:15px; font-weight:800; margin-bottom:6px;">Have Questions or Need Payment Support?</h3>
            <p style="font-size:12px;">Reach our official support team directly on WhatsApp for immediate assistance.</p>
            
            <div class="btn-group-responsive">
                <a href="https://wa.me/447456004195?text=hello" target="_blank" class="btn-wa">
                    <i data-lucide="message-circle" size="18"></i> Text Us on WhatsApp
                </a>

                <a href="index.php<?= $ref_query ?>" class="btn-new-inv">
                    <i data-lucide="plus-circle" size="18"></i> New Investment
                </a>
            </div>
        </div>
    </div>
</div>

<footer>
    <div class="footer-container">
        <div class="footer-col">
            <div class="brand-logo" style="margin-bottom: 10px;">
                <div class="logo-icon"><img src="brand-icon.svg" alt="" width="22" height="22"></div>
                <span class="brand-name">NOVATOLO</span>
            </div>
            <p>Account tools for reviewing investment plans, tracking activity, and contacting support.</p>
        </div>

        <div class="footer-col">
            <h4>Quick Links</h4>
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <a href="index.php<?= $ref_query ?>" class="nav-link">Home</a>
                <a href="about.php<?= $ref_query ?>" class="nav-link">About Us</a>
                <a href="login.php<?= $ref_query ?>" class="nav-link">Sign In</a>
                <a href="register.php<?= $ref_query ?>" class="nav-link">Sign Up</a>
            </div>
        </div>

        <div class="footer-col">
            <h4>Contact Us</h4>
            <ul class="contact-list">
                <li class="contact-item">
                    <i data-lucide="phone" size="15" color="#10B981"></i>
                    <span>+44 7456 004195</span>
                </li>
                <li class="contact-item">
                    <i data-lucide="mail" size="15" color="#10B981"></i>
                    <span>support@novatolo.com</span>
                </li>
            </ul>
            <a href="https://wa.me/447480275490?text=Hello%20NOVATOLO%20Support," target="_blank" rel="noopener noreferrer" class="btn-whatsapp">
                <i data-lucide="message-circle" size="15"></i> Chat on WhatsApp
            </a>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?php echo date("Y"); ?> NOVATOLO. All rights reserved.</p>
    </div>
</footer>

<script>lucide.createIcons();</script>
<script src="install.js" defer></script>
</body>
</html>