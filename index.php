<?php
// index.php
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
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$site_path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$site_url = rtrim($protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $site_path, '/');

function money($amount): string {
    return number_format((float)$amount, 0) . ' RWF';
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$plan_images = require 'plan_images.php';

// Read every configured plan without changing database records on page load.
$plans = $pdo->query('SELECT * FROM investment_plans ORDER BY id ASC')->fetchAll();
$startingPlanAmount = $plans ? min(array_map(static fn($plan) => (float)$plan['min_amount'], $plans)) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#173c38">
    <title>NOVATOLO | Investment Account</title>

    <meta name="description" content="Explore NOVATOLO investment plans, account services, and support. Review plan terms and risks before investing.">
    <link rel="canonical" href="<?= e($site_url . '/index.php') ?>">
    <link rel="icon" href="brand-icon.svg" type="image/svg+xml">
    <link rel="manifest" href="site.webmanifest">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="NOVATOLO">
    <meta property="og:title" content="NOVATOLO | Investment Account">
    <meta property="og:description" content="Explore NOVATOLO investment plans and account services. Review plan terms and risks before investing.">
    <meta property="og:url" content="<?= e($site_url . '/index.php' . $ref_query) ?>">
    <meta property="og:image" content="<?= e($site_url . '/logo.jpg') ?>">
    <meta property="og:image:alt" content="NOVATOLO Digital Platform">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="NOVATOLO | Investment Account">
    <meta name="twitter:description" content="Explore NOVATOLO investment plans and account services.">
    <meta name="twitter:image" content="<?= e($site_url . '/logo.jpg') ?>">

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
        .brand-name span { color: var(--emerald-primary); }

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

        .container { width: 100%; max-width: 1140px; margin: 16px auto; padding: 0 14px; }
        
        .hero-banner { 
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); 
            color: #FFF; padding: 28px 18px; border-radius: 18px; margin-bottom: 24px; text-align: center; 
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); 
        }
        .hero-banner h1 { font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
        .hero-banner p { color: #94A3B8; font-size: 12px; margin-top: 6px; max-width: 600px; margin-left: auto; margin-right: auto; line-height: 1.5; }

        .section-title { font-size: 17px; font-weight: 800; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }

        .plans-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 40px; }
        .plan { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; box-shadow: 0 8px 25px rgba(15,23,42,.055); display: flex; flex-direction: column; justify-content: space-between; min-height: 320px; transition: transform .18s,box-shadow .18s; }
        .plan:hover { transform: translateY(-3px); box-shadow: 0 16px 35px rgba(15,23,42,.10); }
        .plan-top { height: 110px; padding: 12px; display: flex; align-items: flex-end; position: relative; overflow: hidden; background-size: cover; background-position: center; }
        .plan-top-overlay { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(15,23,42,0.15) 0%, rgba(15,23,42,0.7) 100%); }
        .plan-pill { background: rgba(255,255,255,.96); color: #0f172a; padding: 5px 10px; border-radius: 18px; font-size: 11px; font-weight: 800; position: relative; z-index: 2; box-shadow: 0 3px 10px rgba(15,23,42,.08); }
        .plan-body { padding: 14px; }
        .rate-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
        .rate-badge { display: flex; align-items: center; gap: 8px; }
        .pct-box { background: #ecfdf5; color: #059669; font-size: 15px; font-weight: 800; width: 34px; height: 34px; border-radius: 99px; display: flex; align-items: center; justify-content: center; border: 1px solid #a7f3d0; }
        .rate-label { font-size: 9px; color: var(--text-muted); font-weight: 700; }
        .rate-val { font-size: 18px; font-weight: 800; color: #10b981; margin-top: 1px; }
        .duration-val { font-size: 13px; font-weight: 800; color: var(--text-dark); display: flex; align-items: center; gap: 4px; margin-top: 1px; }
        .limits-box { background: #f8fafc; border: 1px solid var(--card-border); border-radius: 10px; padding: 10px; display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
        .limit-row { display: flex; justify-content: space-between; align-items: center; }
        .limit-label { font-size: 8px; color: var(--text-muted); font-weight: 800; text-transform: uppercase; }
        .limit-val { font-size: 11px; font-weight: 800; color: var(--text-dark); }
        .green { color: #059669; }
        .btn-invest { width: 100%; padding: 11px; background: linear-gradient(135deg,#f59e0b,#d97706); border: 0; border-radius: 10px; color: #fff; font-weight: 800; font-size: 13px; cursor: pointer; box-shadow: 0 6px 14px rgba(245,158,11,.22); display: flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none; }
        .btn-invest:hover { filter: brightness(1.03); }

        @media(max-width: 900px) { .plans-grid { grid-template-columns: repeat(2, 1fr); } }
        @media(max-width: 600px) { 
            .plans-grid { grid-template-columns: 1fr; gap: 12px; }
            .navbar { padding: 6px 6px; gap: 2px; }
            .brand-name { font-size: 12px; }
            .logo-icon { width: 24px; height: 24px; border-radius: 6px; }
            .nav-link { padding: 4px 6px; font-size: 10px; gap: 2px; }
            .btn-auth-login, .btn-auth-signup { padding: 4px 6px; font-size: 10px; gap: 2px; }
        }

        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 100; padding: 16px; }
        .modal-overlay.active { display: flex; }
        .modal-card { background: #FFF; width: 100%; max-width: 380px; border-radius: 20px; padding: 24px; position: relative; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); text-align: center; }
        .close-btn { position: absolute; top: 16px; right: 16px; border: none; background: #F1F5F9; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; justify-content: center; }
        .modal-icon { width: 46px; height: 46px; background: #FEF3C7; color: #D97706; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px auto; }
        .modal-card h3 { font-size: 17px; font-weight: 800; margin-bottom: 8px; }
        .modal-card p { font-size: 12px; color: var(--text-muted); margin-bottom: 18px; }
        .modal-actions { display: flex; flex-direction: column; gap: 10px; }

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

<header class="site-header">
    <div class="site-utility"><span>Investment plans & account services</span><span>Plan details are estimates; outcomes are not guaranteed.</span></div>
    <div class="site-header-inner">
        <a href="index.php<?= $ref_query ?>" class="brand-logo">
            <span class="logo-icon"><img src="brand-icon.svg" alt="" width="22" height="22"></span>
            <span class="brand-lockup"><span class="brand-name">NOVATOLO</span><span class="brand-caption">MEMBER SERVICES</span></span>
        </a>
        <button class="public-menu-toggle" type="button" data-public-nav-toggle aria-controls="public-navigation" aria-expanded="false">
            <i data-lucide="menu" size="20"></i><span>Menu</span>
        </button>
        <nav class="public-navigation" id="public-navigation">
            <div class="nav-center-menu">
                <a href="index.php<?= $ref_query ?>" class="nav-link active"><i data-lucide="layers" size="15"></i><span>Plans</span></a>
                <a href="about.php<?= $ref_query ?>" class="nav-link"><i data-lucide="info" size="15"></i><span>About</span></a>
            </div>
            <div class="nav-right-auth">
                <button type="button" class="btn-auth-login app-install" data-install-app><i data-lucide="download" size="15"></i>Install app</button>
                <a href="login.php<?= $ref_query ?>" class="btn-auth-login"><i data-lucide="log-in" size="15"></i>Sign in</a>
                <a href="register.php<?= $ref_query ?>" class="btn-auth-signup"><i data-lucide="user-plus" size="15"></i>Create account</a>
            </div>
        </nav>
    </div>
</header>

<div class="container">
    <div class="hero-banner">
        <p class="hero-kicker">NOVATOLO · INVESTMENT ACCOUNT</p>
        <h1>Make your next financial decision with clarity.</h1>
        <p>Compare the published tiers, review their terms, and find the starting investment amount before you open an account.</p>
        <?php if ($startingPlanAmount !== null): ?><span class="hero-starting">Plans starting at <?= money($startingPlanAmount) ?></span><?php endif; ?>
        <a class="hero-action" href="register.php<?= $ref_query ?>">Open an account <i data-lucide="arrow-up-right" size="16"></i></a>
    </div>

    <div class="section-title">
        <i data-lucide="chart-no-axes-combined" size="18" color="#176b5d"></i>
        <span>Available investment plans</span>
        <span class="plan-count"><?= count($plans) ?> plans</span>
    </div>

    <div class="plans-grid">
        <?php 
        foreach ($plans as $plan): 
            $pId = (int)$plan['id'];
            $imgFile = $plan_images[$pId] ?? 'florian-media-dock-2898497.jpg';
            $planCategory = $pId <= 8 ? 'Phones' : ($pId <= 16 ? 'Watches' : 'Computers');
            $planAmount = (float)$plan['min_amount'];
            $profitRate = (float)$plan['profit_rate'];
            $durationDays = (int)$plan['duration_days'];
            $totalProfit = $planAmount * ($profitRate / 100);
            $totalReturn = $planAmount + $totalProfit;
            $dailyProfit = $totalProfit / max(1, $durationDays);
        ?>
            <article class="plan">
                <div>
                    <div class="plan-top" style="background-image: url('<?= $imgFile ?>');">
                        <div class="plan-top-overlay"></div>
                        <span class="plan-category"><?= e($planCategory) ?></span>
                        <span class="plan-pill"><?= e($plan['name']) ?></span>
                    </div>
                    <div class="plan-body">
                        <div class="rate-row">
                            <div class="rate-badge"><div class="pct-box">%</div><div><div class="rate-label">Profit Rate</div><div class="rate-val"><?= e($plan['profit_rate']) ?>%</div></div></div>
                            <div style="text-align:right"><div class="rate-label">Duration</div><div class="duration-val"><i data-lucide="clock" size="13"></i><span><?= $durationDays ?> Days</span></div></div>
                        </div>
                        <div class="limits-box">
                            <div class="limit-row"><span class="limit-label">Invest Amount</span><strong class="limit-val"><?= money($planAmount) ?></strong></div>
                            <div class="limit-row"><span class="limit-label">Daily Profit</span><strong class="limit-val green"><?= money($dailyProfit) ?> / day</strong></div>
                            <div class="limit-row"><span class="limit-label">Estimated total return</span><strong class="limit-val"><?= money($totalReturn) ?></strong></div>
                        </div>
                        <button class="btn-invest" onclick="promptLogin()"><i data-lucide="trending-up" size="14"></i> Invest Plan</button>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <p class="risk-note">Plan figures are estimates based on the terms shown. Investment outcomes are not guaranteed. Review the full terms and consider the risks before depositing.</p>
</div>

<div class="modal-overlay" id="authModal">
    <div class="modal-card">
        <button class="close-btn" onclick="closeAuthModal()"><i data-lucide="x" size="16"></i></button>
        <div class="modal-icon"><i data-lucide="lock" size="22"></i></div>
        <h3>Account Required</h3>
        <p>To proceed with investing, please sign in to your NOVATOLO account or create a new one.</p>
        <div class="modal-actions">
            <a href="login.php<?= $ref_query ?>" class="btn-auth-signup" style="justify-content: center; padding: 11px;">Sign In to Invest</a>
            <a href="register.php<?= $ref_query ?>" class="btn-auth-login" style="justify-content: center; padding: 11px;">Create Account</a>
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
            <p>Empowering individuals with reliable and profitable digital investment strategies.</p>
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
            <a href="https://wa.me/447480275490?text=Hello%20NOVATOLO%20Support," target="_blank" class="btn-whatsapp">
                <i data-lucide="message-circle" size="15"></i> Chat on WhatsApp
            </a>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?php echo date("Y"); ?> NOVATOLO. All rights reserved.</p>
    </div>
</footer>

<script>
    lucide.createIcons();

    function promptLogin() {
        document.getElementById('authModal').classList.add('active');
    }

    function closeAuthModal() {
        document.getElementById('authModal').classList.remove('active');
    }
</script>
<script src="install.js" defer></script>
</body>
</html>