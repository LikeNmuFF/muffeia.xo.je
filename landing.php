<?php session_start();
// Generate CSRF token for auth modal
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="icon" href="logo/m-blues.png" type="image/png">
<title>Muffeia — Share Problems. Get Solutions.</title>
<meta name="description" content="Muffeia is a platform where you can share your problems and get solutions from the community. Join us to create safer online communities.">
<meta name="keywords" content="Muffeia, social support, community help, share problems, find solutions, anonymous support">
<link rel="canonical" href="https://muffeia.xo.je/landing.php">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:url" content="https://muffeia.xo.je/landing.php">
<meta property="og:title" content="Muffeia — Share Problems. Get Solutions.">
<meta property="og:description" content="Muffeia is a platform where you can share your problems and get solutions from the community. Join us to create safer online communities.">
<meta property="og:image" content="https://muffeia.xo.je/logo/muffeia.png">

<!-- Twitter -->
<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="https://muffeia.xo.je/landing.php">
<meta property="twitter:title" content="Muffeia — Share Problems. Get Solutions.">
<meta property="twitter:description" content="Muffeia is a platform where you can share your problems and get solutions from the community. Join us to create safer online communities.">
<meta property="twitter:image" content="https://muffeia.xo.je/logo/muffeia.png">
<style>
:root {
    --clr-primary: #D44A6C;
    --clr-primary-dark: #B83A5A;
    --clr-primary-light: #F4D4DE;
    --clr-secondary: #2A9D8F;
    --clr-secondary-light: #C8E8E4;
    --clr-accent: #E9C46A;
    --clr-surface: #FFFCF9;
    --clr-bg: #F7F2ED;
    --clr-text: #1C1C28;
    --clr-text-secondary: #5C5C6E;
    --font-heading: 'Outfit', sans-serif;
    --font-body: 'Source Sans 3', sans-serif;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; }

body {
    font-family: var(--font-body);
    color: var(--clr-text);
    background: var(--clr-bg);
    overflow-x: hidden;
}

/* ── Nav ── */
nav {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 1000;
    padding: 16px 40px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: background 0.4s ease, box-shadow 0.4s ease, padding 0.4s ease;
    background: transparent;
}
nav.scrolled {
    background: rgba(255, 252, 249, 0.92);
    backdrop-filter: blur(12px);
    box-shadow: 0 1px 12px rgba(28,28,40,0.08);
    padding: 12px 40px;
}
nav .logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}
nav .logo img { height: 36px; width: auto; }
nav .logo span {
    font-family: var(--font-heading);
    font-size: 1.4rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    letter-spacing: -0.5px;
}
nav .nav-actions { display: flex; align-items: center; gap: 12px; }
.btn-nav {
    padding: 10px 24px;
    border-radius: 9999px;
    font-family: var(--font-heading);
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
}
.btn-nav-outline {
    border: 2px solid var(--clr-primary);
    color: var(--clr-primary);
    background: transparent;
}
.btn-nav-outline:hover {
    background: var(--clr-primary);
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(212,74,108,0.3);
}
.btn-nav-solid {
    background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary));
    color: #fff;
    border: none;
    box-shadow: 0 4px 14px rgba(212,74,108,0.25);
}
.btn-nav-solid:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(212,74,108,0.35);
}

/* ── Hero ── */
.hero {
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: linear-gradient(135deg, #FFFCF9 0%, #F7F2ED 40%, #F4D4DE 70%, #C8E8E4 100%);
}
.hero-bg-shapes {
    position: absolute;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
}
.hero-bg-shapes .shape {
    position: absolute;
    border-radius: 50%;
    opacity: 0.15;
}
.shape-1 {
    width: 600px; height: 600px;
    background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary));
    top: -200px; right: -150px;
    animation: floatShape 8s ease-in-out infinite;
}
.shape-2 {
    width: 400px; height: 400px;
    background: linear-gradient(135deg, var(--clr-accent), var(--clr-primary));
    bottom: -100px; left: -100px;
    animation: floatShape 10s ease-in-out infinite reverse;
}
.shape-3 {
    width: 200px; height: 200px;
    background: var(--clr-secondary);
    top: 40%; left: 10%;
    animation: floatShape 6s ease-in-out infinite 1s;
}
.shape-4 {
    width: 120px; height: 120px;
    background: var(--clr-primary);
    bottom: 20%; right: 15%;
    animation: floatShape 7s ease-in-out infinite 2s;
}
@keyframes floatShape {
    0%, 100% { transform: translate(0, 0) scale(1); }
    25% { transform: translate(30px, -40px) scale(1.05); }
    50% { transform: translate(-20px, 20px) scale(0.95); }
    75% { transform: translate(40px, 30px) scale(1.02); }
}
.hero-grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(212,74,108,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(42,157,143,0.04) 1px, transparent 1px);
    background-size: 60px 60px;
    pointer-events: none;
}
.hero-content {
    position: relative;
    z-index: 2;
    text-align: center;
    max-width: 780px;
    padding: 120px 24px 60px;
}
.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 20px;
    border-radius: 9999px;
    background: linear-gradient(135deg, rgba(212,74,108,0.1), rgba(42,157,143,0.1));
    color: var(--clr-primary);
    font-size: 0.85rem;
    font-weight: 600;
    font-family: var(--font-heading);
    margin-bottom: 24px;
    animation: fadeInDown 0.8s ease-out;
    border: 1px solid rgba(212,74,108,0.15);
}
.hero-badge i { font-size: 0.75rem; }
.hero h1 {
    font-family: var(--font-heading);
    font-size: clamp(2.4rem, 6vw, 4.2rem);
    font-weight: 900;
    line-height: 1.1;
    margin-bottom: 20px;
    letter-spacing: -1px;
    animation: fadeInUp 0.8s ease-out 0.2s both;
}
.hero h1 .gradient-text {
    background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.hero h1 .typewriter {
    display: inline-block;
    position: relative;
    color: var(--clr-secondary);
}
.hero h1 .typewriter::after {
    content: '|';
    position: absolute;
    right: -8px;
    color: var(--clr-primary);
    animation: blink 0.8s step-end infinite;
}
@keyframes blink {
    50% { opacity: 0; }
}
.hero p {
    font-size: clamp(1rem, 2vw, 1.2rem);
    color: var(--clr-text-secondary);
    line-height: 1.7;
    max-width: 600px;
    margin: 0 auto 36px;
    animation: fadeInUp 0.8s ease-out 0.35s both;
}
.hero-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    flex-wrap: wrap;
    animation: fadeInUp 0.8s ease-out 0.5s both;
}
.btn-hero {
    padding: 16px 36px;
    border-radius: 9999px;
    font-family: var(--font-heading);
    font-weight: 700;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.btn-hero-primary {
    background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary));
    color: #fff;
    border: none;
    box-shadow: 0 6px 20px rgba(212,74,108,0.3);
}
.btn-hero-primary:hover {
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 12px 32px rgba(212,74,108,0.4);
}
.btn-hero-secondary {
    background: transparent;
    color: var(--clr-text);
    border: 2px solid var(--clr-border, #D4CEC8);
}
.btn-hero-secondary:hover {
    border-color: var(--clr-primary);
    color: var(--clr-primary);
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(212,74,108,0.12);
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── Sections Common ── */
section { padding: 100px 24px; }
.section-inner {
    max-width: 1100px;
    margin: 0 auto;
}
.section-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 16px;
    border-radius: 9999px;
    background: linear-gradient(135deg, rgba(212,74,108,0.08), rgba(42,157,143,0.08));
    color: var(--clr-primary);
    font-size: 0.8rem;
    font-weight: 600;
    font-family: var(--font-heading);
    margin-bottom: 16px;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.section-title {
    font-family: var(--font-heading);
    font-size: clamp(1.8rem, 4vw, 2.6rem);
    font-weight: 800;
    margin-bottom: 16px;
    letter-spacing: -0.5px;
}
.section-subtitle {
    font-size: 1.05rem;
    color: var(--clr-text-secondary);
    max-width: 560px;
    line-height: 1.7;
}
.reveal {
    opacity: 0;
    transform: translateY(40px);
    transition: opacity 0.7s ease-out, transform 0.7s ease-out;
}
.reveal.visible {
    opacity: 1;
    transform: translateY(0);
}

/* ── Features ── */
#features {
    background: var(--clr-surface, #FFFCF9);
}
.features-header { text-align: center; margin-bottom: 56px; }
.features-header .section-subtitle { margin: 0 auto; }
.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
}
.feature-card {
    background: var(--clr-bg, #F7F2ED);
    border-radius: 20px;
    padding: 36px 28px;
    text-align: center;
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    border: 1px solid transparent;
    position: relative;
    overflow: hidden;
}
.feature-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--clr-primary), var(--clr-secondary));
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.5s ease;
}
.feature-card:hover::before { transform: scaleX(1); }
.feature-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(28,28,40,0.1);
    border-color: var(--clr-primary-light);
    background: #fff;
}
.feature-card .icon {
    width: 64px; height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 1.5rem;
    transition: all 0.4s ease;
}
.feature-card:nth-child(1) .icon { background: rgba(212,74,108,0.12); color: var(--clr-primary); }
.feature-card:nth-child(2) .icon { background: rgba(42,157,143,0.12); color: var(--clr-secondary); }
.feature-card:nth-child(3) .icon { background: rgba(233,196,74,0.15); color: #B8963A; }
.feature-card:nth-child(4) .icon { background: rgba(212,74,108,0.12); color: var(--clr-primary); }
.feature-card:hover .icon { transform: scale(1.1) rotate(-5deg); }
.feature-card h3 {
    font-family: var(--font-heading);
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 10px;
}
.feature-card p {
    font-size: 0.92rem;
    color: var(--clr-text-secondary);
    line-height: 1.6;
}

/* ── How It Works ── */
#how-it-works {
    background: linear-gradient(135deg, #F7F2ED, #FFFCF9);
    position: relative;
}
#how-it-works::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--clr-primary-light), transparent);
}
.how-header { text-align: center; margin-bottom: 56px; }
.how-header .section-subtitle { margin: 0 auto; }
.steps {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 32px;
    position: relative;
}
.step-card {
    background: var(--clr-surface, #FFFCF9);
    border-radius: 20px;
    padding: 36px 28px;
    text-align: center;
    position: relative;
    border: 1px solid var(--clr-border, #E8E2DC);
    transition: all 0.4s ease;
}
.step-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 32px rgba(28,28,40,0.08);
}
.step-number {
    width: 48px; height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-heading);
    font-weight: 800;
    font-size: 1.2rem;
    margin: 0 auto 20px;
}
.step-card h3 {
    font-family: var(--font-heading);
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 10px;
}
.step-card p {
    font-size: 0.92rem;
    color: var(--clr-text-secondary);
    line-height: 1.6;
}

/* ── FAQ Section ── */
#faq {
    background: var(--clr-surface, #FFFCF9);
}
.faq-header { text-align: center; margin-bottom: 56px; }
.faq-header .section-subtitle { margin: 0 auto; }
.faq-list {
    max-width: 720px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.faq-item {
    border: 1px solid var(--clr-border, #E8E2DC);
    border-radius: 16px;
    overflow: hidden;
    transition: box-shadow 0.3s ease;
    background: var(--clr-bg, #F7F2ED);
}
.faq-item:hover {
    box-shadow: 0 4px 16px rgba(28,28,40,0.06);
}
.faq-question {
    width: 100%;
    padding: 18px 24px;
    background: none;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    font-family: var(--font-heading);
    font-size: 1rem;
    font-weight: 600;
    color: var(--clr-text);
    text-align: left;
    transition: color 0.3s ease;
}
.faq-question:hover { color: var(--clr-primary); }
.faq-question i {
    font-size: 0.85rem;
    color: var(--clr-primary);
    transition: transform 0.3s ease;
    flex-shrink: 0;
}
.faq-item.open .faq-question i {
    transform: rotate(180deg);
}
.faq-answer {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s ease, padding 0.4s ease;
    padding: 0 24px;
    font-size: 0.92rem;
    color: var(--clr-text-secondary);
    line-height: 1.7;
}
.faq-item.open .faq-answer {
    max-height: 300px;
    padding: 0 24px 18px;
}
}

/* ── CTA ── */
#cta {
    text-align: center;
    background: var(--clr-bg, #F7F2ED);
    padding: 100px 24px;
}
#cta h2 {
    font-family: var(--font-heading);
    font-size: clamp(1.8rem, 4vw, 2.6rem);
    font-weight: 800;
    margin-bottom: 16px;
}
#cta p {
    font-size: 1.05rem;
    color: var(--clr-text-secondary);
    max-width: 500px;
    margin: 0 auto 36px;
    line-height: 1.7;
}

/* ── Footer ── */
footer {
    background: var(--clr-text, #1C1C28);
    color: rgba(255,255,255,0.6);
    padding: 40px 24px;
    text-align: center;
}
footer .footer-inner {
    max-width: 1100px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}
footer .footer-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-heading);
    font-weight: 700;
    color: #fff;
    font-size: 1.1rem;
}
footer .footer-logo img { height: 28px; }
footer a {
    color: rgba(255,255,255,0.6);
    text-decoration: none;
    transition: color 0.3s;
    font-size: 0.9rem;
}
footer a:hover { color: #fff; }
footer .footer-links { display: flex; gap: 24px; }

/* ── Responsive ── */
/* ── Values / Why Muffeia ── */
#values {
    background: linear-gradient(135deg, #F7F2ED, #FFFCF9);
}
.values-header { text-align: center; margin-bottom: 56px; }
.values-header .section-subtitle { margin: 0 auto; }
.values-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 24px;
}
.value-card {
    background: var(--clr-surface, #FFFCF9);
    border-radius: 20px;
    padding: 32px 24px;
    text-align: center;
    border: 1px solid var(--clr-border, #E8E2DC);
    transition: all 0.4s ease;
}
.value-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 32px rgba(28,28,40,0.08);
    border-color: var(--clr-primary-light);
}
.value-card .icon {
    width: 56px; height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 1.3rem;
}
.value-card:nth-child(1) .icon { background: rgba(212,74,108,0.12); color: var(--clr-primary); }
.value-card:nth-child(2) .icon { background: rgba(42,157,143,0.12); color: var(--clr-secondary); }
.value-card:nth-child(3) .icon { background: rgba(233,196,74,0.15); color: #B8963A; }
.value-card:nth-child(4) .icon { background: rgba(42,157,143,0.12); color: var(--clr-secondary); }
.value-card h3 {
    font-family: var(--font-heading);
    font-size: 1.05rem;
    font-weight: 700;
    margin-bottom: 8px;
}
.value-card p {
    font-size: 0.88rem;
    color: var(--clr-text-secondary);
    line-height: 1.6;
}

/* ── Suggestion Box ── */
#suggest {
    background: var(--clr-bg, #F7F2ED);
}
.suggest-header { text-align: center; margin-bottom: 48px; }
.suggest-header .section-subtitle { margin: 0 auto; }
.suggest-form {
    max-width: 560px;
    margin: 0 auto;
    background: var(--clr-surface, #FFFCF9);
    border-radius: 20px;
    padding: 40px 36px;
    border: 1px solid var(--clr-border, #E8E2DC);
    box-shadow: 0 8px 24px rgba(28,28,40,0.04);
}
.suggest-form .form-group {
    margin-bottom: 18px;
}
.suggest-form label {
    display: block;
    font-family: var(--font-heading);
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 6px;
    color: var(--clr-text);
}
.suggest-form label .optional {
    font-weight: 400;
    color: var(--clr-text-secondary);
    font-size: 0.8rem;
}
.suggest-form input,
.suggest-form select,
.suggest-form textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--clr-border, #E8E2DC);
    border-radius: 12px;
    font-family: var(--font-body);
    font-size: 0.92rem;
    color: var(--clr-text);
    background: var(--clr-bg, #F7F2ED);
    transition: border-color 0.3s ease;
    box-sizing: border-box;
}
.suggest-form input:focus,
.suggest-form select:focus,
.suggest-form textarea:focus {
    outline: none;
    border-color: var(--clr-primary);
}
.suggest-form textarea {
    resize: vertical;
    min-height: 120px;
}
.suggest-form .submit-btn {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 12px;
    font-family: var(--font-heading);
    font-weight: 700;
    font-size: 1rem;
    color: #fff;
    background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary));
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 14px rgba(212,74,108,0.25);
}
.suggest-form .submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(212,74,108,0.35);
}
.suggest-form .submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}
.suggest-form .form-feedback {
    margin-top: 12px;
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.9rem;
    display: none;
}
.suggest-form .form-feedback.success {
    display: block;
    background: rgba(42,157,143,0.1);
    color: var(--clr-secondary);
    border: 1px solid rgba(42,157,143,0.2);
}
.suggest-form .form-feedback.error {
    display: block;
    background: rgba(212,74,108,0.1);
    color: var(--clr-primary);
    border: 1px solid rgba(212,74,108,0.2);
}

/* ── Auth Modal ── */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.55);
    backdrop-filter: blur(8px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-overlay.active { display: flex; }

.modal-window {
    background: #fff;
    border-radius: 20px;
    width: 100%;
    max-width: 440px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 24px 64px rgba(28,28,40,0.2);
    animation: modalFadeIn 0.25s ease-out;
    padding: 32px;
    position: relative;
}
@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.95) translateY(12px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-close {
    position: absolute;
    top: 16px;
    right: 16px;
    background: var(--clr-bg);
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1rem;
    color: var(--clr-text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}
.modal-close:hover {
    background: var(--clr-primary-light);
    color: var(--clr-primary);
}

.modal-logo {
    text-align: center;
    margin-bottom: 24px;
}
.modal-logo img { height: 36px; }
.modal-logo span {
    font-family: var(--font-heading);
    font-size: 1.3rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    display: block;
    margin-top: 4px;
}

.auth-modal-tabs {
    display: flex;
    gap: 0;
    margin-bottom: 24px;
    background: var(--clr-bg);
    border-radius: 12px;
    padding: 4px;
}
.auth-modal-tab {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: 10px;
    font-family: var(--font-heading);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    background: transparent;
    color: var(--clr-text-secondary);
    transition: all 0.25s ease;
}
.auth-modal-tab.active {
    background: #fff;
    color: var(--clr-primary);
    box-shadow: 0 2px 8px rgba(28,28,40,0.08);
}
.auth-modal-tab i { margin-right: 6px; }

.auth-modal-form { display: none; }
.auth-modal-form.active { display: block; }

.auth-modal-form .form-group {
    margin-bottom: 18px;
}
.auth-modal-form label {
    display: block;
    font-family: var(--font-heading);
    font-size: 0.82rem;
    font-weight: 600;
    margin-bottom: 6px;
    color: var(--clr-text);
}
.auth-modal-form .form-input {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid var(--clr-border, #E8E2DC);
    border-radius: 12px;
    font-family: var(--font-body);
    font-size: 0.92rem;
    color: var(--clr-text);
    background: var(--clr-bg);
    transition: border-color 0.25s ease;
    box-sizing: border-box;
}
.auth-modal-form .form-input:focus {
    outline: none;
    border-color: var(--clr-primary);
}
.auth-modal-form .password-wrap {
    position: relative;
}
.auth-modal-form .password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--clr-text-secondary);
    font-size: 1rem;
}

.auth-modal-form .btn-submit {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 12px;
    font-family: var(--font-heading);
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    background: linear-gradient(135deg, var(--clr-primary), var(--clr-secondary));
    color: #fff;
    box-shadow: 0 4px 14px rgba(212,74,108,0.25);
    transition: all 0.3s ease;
}
.auth-modal-form .btn-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(212,74,108,0.35);
}
.auth-modal-form .btn-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.auth-modal-error {
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 0.85rem;
    display: none;
    margin-bottom: 16px;
    background: rgba(212,74,108,0.1);
    color: var(--clr-primary);
    border: 1px solid rgba(212,74,108,0.2);
}
.auth-modal-error.show { display: block; }

.auth-modal-success {
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 0.85rem;
    display: none;
    margin-bottom: 16px;
    background: rgba(42,157,143,0.1);
    color: var(--clr-secondary);
    border: 1px solid rgba(42,157,143,0.2);
}
.auth-modal-success.show { display: block; }

.auth-modal-switch {
    text-align: center;
    margin-top: 18px;
    font-size: 0.9rem;
    color: var(--clr-text-secondary);
}
.auth-modal-switch a {
    color: var(--clr-primary);
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
}
.auth-modal-switch a:hover { text-decoration: underline; }

/* Password strength */
.password-strength {
    margin-top: 8px;
}
.strength-bar {
    height: 4px;
    background: var(--clr-border, #E8E2DC);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 6px;
}
.strength-bar-fill {
    height: 100%;
    width: 0%;
    border-radius: 4px;
    transition: all 0.3s ease;
}
.strength-bar-fill.weak { width: 20%; background: var(--clr-primary); }
.strength-bar-fill.medium { width: 50%; background: #E9C46A; }
.strength-bar-fill.strong { width: 75%; background: var(--clr-secondary); }
.strength-bar-fill.very-strong { width: 100%; background: var(--clr-secondary); }

.strength-text {
    font-size: 0.78rem;
    font-weight: 600;
    margin-bottom: 4px;
}
.strength-text.weak { color: var(--clr-primary); }
.strength-text.medium { color: #B8963A; }
.strength-text.strong { color: var(--clr-secondary); }
.strength-text.very-strong { color: var(--clr-secondary); }

.strength-requirements {
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: 0.78rem;
}
.strength-requirements li { margin-bottom: 2px; color: var(--clr-text-secondary); }
.strength-requirements li.met { color: var(--clr-secondary); }
.strength-requirements li i { width: 16px; margin-right: 4px; }

@media (max-width: 768px) {
    nav { padding: 14px 20px; }
    nav.scrolled { padding: 10px 20px; }
    nav .logo span { font-size: 1.1rem; }
    .btn-nav { padding: 8px 18px; font-size: 0.8rem; }
    .hero-content { padding-top: 100px; }
    section { padding: 60px 20px; }
    .features-grid { grid-template-columns: 1fr; }
    .steps { grid-template-columns: 1fr; }
    .values-grid { grid-template-columns: 1fr; }
    .suggest-form { padding: 28px 20px; }
    footer .footer-inner { flex-direction: column; text-align: center; }
}

@media (max-width: 400px) {
    nav { padding: 12px 16px; }
    nav .logo span { font-size: 1rem; }
    .btn-nav { padding: 6px 14px; font-size: 0.75rem; }
    .hero h1 { font-size: 2rem; }
    .hero-actions { flex-direction: column; width: 100%; }
    .btn-hero { width: 100%; justify-content: center; }
    .suggest-form { padding: 20px 16px; }
}
</style>
</head>
<body>

<nav id="navbar">
    <a class="logo" href="#">
        <img src="logo/m-blues.png" alt="Muffeia">
        <span>Muffeia</span>
    </a>
    <div class="nav-actions">
        <a class="btn-nav btn-nav-outline" href="javascript:void(0)" onclick="openAuthModal('login')">Log In</a>
        <a class="btn-nav btn-nav-solid" href="javascript:void(0)" onclick="openAuthModal('register')">Get Started</a>
    </div>
</nav>

<section class="hero">
    <div class="hero-bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
    </div>
    <div class="hero-grid"></div>
    <div class="hero-content">
        <div class="hero-badge"><i class="fas fa-sparkles"></i> Community-Powered Problem Solving</div>
        <h1>
            Share <span class="gradient-text">Problems</span>.<br>
            Get <span class="typewriter" id="typewriterText">Solutions</span>.
        </h1>
        <p>Muffeia is a warm, private community where you can share what's troubling you — anonymously or openly — and get meaningful solutions from people who care.</p>
        <div class="hero-actions">
            <a class="btn-hero btn-hero-primary" href="javascript:void(0)" onclick="openAuthModal('register')">
                Join Muffeia <i class="fas fa-arrow-right"></i>
            </a>
            <a class="btn-hero btn-hero-secondary" href="#features">
                Explore Features <i class="fas fa-chevron-down"></i>
            </a>
        </div>
    </div>
</section>

<section id="features">
    <div class="section-inner">
        <div class="features-header reveal">
            <div class="section-label"><i class="fas fa-star"></i> What You Can Do</div>
            <h2 class="section-title">Everything you need to share and solve</h2>
            <p class="section-subtitle">From anonymous posts to encrypted messages — Muffeia gives you a safe space to work through life's challenges.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card reveal">
                <div class="icon"><i class="fas fa-pen-to-square"></i></div>
                <h3>Post Problems</h3>
                <p>Share what's on your mind — anonymously or with your name. Your story, your choice.</p>
            </div>
            <div class="feature-card reveal">
                <div class="icon"><i class="fas fa-lightbulb"></i></div>
                <h3>Get Solutions</h3>
                <p>Receive thoughtful solutions from the community. Upvote the ones that help most.</p>
            </div>
            <div class="feature-card reveal">
                <div class="icon"><i class="fas fa-people-group"></i></div>
                <h3>Community Support</h3>
                <p>Connect with people who understand. Reply to solutions, ask follow-ups, and build on ideas.</p>
            </div>
            <div class="feature-card reveal">
                <div class="icon"><i class="fas fa-lock"></i></div>
                <h3>Private Messaging</h3>
                <p>Take conversations deeper with end-to-end encrypted messages. Your privacy is protected.</p>
            </div>
        </div>
    </div>
</section>

<section id="how-it-works">
    <div class="section-inner">
        <div class="how-header reveal">
            <div class="section-label"><i class="fas fa-compass"></i> How It Works</div>
            <h2 class="section-title">Three steps to clarity</h2>
            <p class="section-subtitle">Getting help is simple. Post, receive, and grow — all in a safe, supportive environment.</p>
        </div>
        <div class="steps">
            <div class="step-card reveal">
                <div class="step-number">1</div>
                <h3>Share Your Problem</h3>
                <p>Write about what you're facing. Choose to post anonymously or openly. No judgment, just support.</p>
            </div>
            <div class="step-card reveal">
                <div class="step-number">2</div>
                <h3>Receive Solutions</h3>
                <p>Community members respond with practical solutions. Like and engage with the ones that resonate.</p>
            </div>
            <div class="step-card reveal">
                <div class="step-number">3</div>
                <h3>Grow &amp; Help Others</h3>
                <p>Apply what you've learned, then pay it forward by solving someone else's problem.</p>
            </div>
        </div>
    </div>
</section>

<section id="faq">
    <div class="section-inner">
        <div class="faq-header reveal">
            <div class="section-label"><i class="fas fa-circle-question"></i> FAQ</div>
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="section-subtitle">Everything you need to know about Muffeia.</p>
        </div>
        <div class="faq-list">
            <div class="faq-item reveal">
                <button class="faq-question" onclick="toggleFaq(this)">
                    What is Muffeia?
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">Muffeia is a community-driven platform where you can share problems you're facing — anonymously or openly — and receive meaningful solutions from people who care. It's a safe, supportive space built around honest conversation.</div>
            </div>
            <div class="faq-item reveal">
                <button class="faq-question" onclick="toggleFaq(this)">
                    Is my identity protected when I post anonymously?
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">Yes. When you choose to post anonymously, your username and profile picture are hidden. The post appears as "Anonymous" to everyone, including other community members. Your identity is never revealed.</div>
            </div>
            <div class="faq-item reveal">
                <button class="faq-question" onclick="toggleFaq(this)">
                    What is badword masking and how does it work?
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">Muffeia automatically masks inappropriate language in real-time as you type. Words are transformed using a first-and-last-character pattern (e.g., "fuck" becomes "f**k"). This works both on the client side as you type and server side as a backup, including detecting obfuscated variants like "f u c k" or embedded bad words.</div>
            </div>
            <div class="faq-item reveal">
                <button class="faq-question" onclick="toggleFaq(this)">
                    Can I edit or delete my posts?
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">Currently, posts cannot be edited after publishing. However, you can engage with solutions by liking, replying, or sharing. If you need a post removed, please contact the community moderators.</div>
            </div>
            <div class="faq-item reveal">
                <button class="faq-question" onclick="toggleFaq(this)">
                    How does the messaging encryption work?
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">Private messages on Muffeia are protected with end-to-end encryption using AES-256. Messages are encrypted on your device before being sent, stored securely in the database, and decrypted only when displayed to the recipient. Your private conversations stay private.</div>
            </div>
            <div class="faq-item reveal">
                <button class="faq-question" onclick="toggleFaq(this)">
                    Is Muffeia free to use?
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">Yes, Muffeia is completely free. There are no subscription fees, no hidden charges, and no premium tiers. Every feature — posting problems, sharing solutions, private messaging, and community support — is available to all members at no cost.</div>
            </div>
        </div>
    </div>
</section>

<section id="values">
    <div class="section-inner">
        <div class="values-header reveal">
            <div class="section-label"><i class="fas fa-shield-heart"></i> Why Muffeia</div>
            <h2 class="section-title">A platform built on trust</h2>
            <p class="section-subtitle">We believe in creating a space where everyone feels safe to share, supported to grow, and empowered to help.</p>
        </div>
        <div class="values-grid">
            <div class="value-card reveal">
                <div class="icon"><i class="fas fa-hand-holding-heart"></i></div>
                <h3>Safe &amp; Supportive</h3>
                <p>A judgment-free environment where every problem is met with empathy, never criticism. Respect is our foundation.</p>
            </div>
            <div class="value-card reveal">
                <div class="icon"><i class="fas fa-eye-slash"></i></div>
                <h3>Anonymous Option</h3>
                <p>Share openly without revealing your identity. Your privacy is your choice — post with your name or stay anonymous.</p>
            </div>
            <div class="value-card reveal">
                <div class="icon"><i class="fas fa-lock"></i></div>
                <h3>End-to-End Encryption</h3>
                <p>Private messages are encrypted with AES-256 before leaving your device. Only you and the recipient can read them.</p>
            </div>
            <div class="value-card reveal">
                <div class="icon"><i class="fas fa-users"></i></div>
                <h3>Community-Driven</h3>
                <p>No algorithms, no bots. Every solution comes from a real person who genuinely wants to help.</p>
            </div>
        </div>
    </div>
</section>

<section id="suggest">
    <div class="section-inner">
        <div class="suggest-header reveal">
            <div class="section-label"><i class="fas fa-lightbulb"></i> Have an Idea?</div>
            <h2 class="section-title">Help us improve Muffeia</h2>
            <p class="section-subtitle">Your feedback shapes the platform. Share your ideas, report bugs, or just tell us what you think.</p>
        </div>
        <form class="suggest-form reveal" id="suggestForm" onsubmit="submitSuggestion(event)">
            <div class="form-group">
                <label for="suggestName">Name <span class="optional">(optional)</span></label>
                <input type="text" id="suggestName" name="name" placeholder="Your name" maxlength="100">
            </div>
            <div class="form-group">
                <label for="suggestEmail">Email <span class="optional">(optional, if you'd like a reply)</span></label>
                <input type="email" id="suggestEmail" name="email" placeholder="your@email.com" maxlength="255">
            </div>
            <div class="form-group">
                <label for="suggestCategory">Category</label>
                <select id="suggestCategory" name="category">
                    <option value="feature">Feature Request</option>
                    <option value="bug">Bug Report</option>
                    <option value="improvement">Improvement Suggestion</option>
                    <option value="feedback">General Feedback</option>
                </select>
            </div>
            <div class="form-group">
                <label for="suggestMessage">Your Message <span style="color: var(--clr-primary);">*</span></label>
                <textarea id="suggestMessage" name="message" placeholder="Tell us what's on your mind..." required></textarea>
            </div>
            <button type="submit" class="submit-btn" id="suggestSubmit">
                <i class="fas fa-paper-plane"></i> Send Suggestion
            </button>
            <div class="form-feedback" id="suggestFeedback"></div>
        </form>
    </div>
</section>

<section id="cta">
    <div class="section-inner reveal">
        <div class="section-label" style="margin: 0 auto 16px;"><i class="fas fa-heart"></i> Join Today</div>
        <h2>Ready to share what's on your mind?</h2>
        <p>Join Muffeia and become part of a community that truly listens and helps.</p>
        <a class="btn-hero btn-hero-primary" href="javascript:void(0)" onclick="openAuthModal('register')" style="display: inline-flex;">
            Create Free Account <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</section>

<footer>
    <div class="footer-inner">
        <div class="footer-logo">
            <img src="logo/m-light.png" alt="Muffeia">
            Muffeia
        </div>
        <div class="footer-links">
            <a href="#">Home</a>
            <a href="#features">Features</a>
            <a href="#how-it-works">How It Works</a>
            <a href="#values">Why Muffeia</a>
            <a href="#faq">FAQ</a>
            <a href="#suggest">Suggest</a>
            <a href="javascript:void(0)" onclick="openAuthModal('login')">Log In</a>
        </div>
        <div>&copy; 2024-<?php echo date('Y'); ?> Muffeia. All rights reserved.</div>
    </div>
</footer>

<script>
// ── Navbar scroll effect ──
const navbar = document.getElementById('navbar');
let lastScroll = 0;
window.addEventListener('scroll', function() {
    const currentScroll = window.pageYOffset;
    if (currentScroll > 80) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
    lastScroll = currentScroll;
}, { passive: true });

// ── Typewriter effect ──
const words = ['Solutions', 'Advice', 'Support', 'Answers', 'Clarity'];
const tw = document.getElementById('typewriterText');
let wordIndex = 0;
let charIndex = words[0].length;
let isDeleting = true;

function typewriterTick() {
    const current = words[wordIndex];
    if (isDeleting) {
        charIndex--;
        tw.textContent = current.substring(0, charIndex);
        if (charIndex === 0) {
            isDeleting = false;
            wordIndex = (wordIndex + 1) % words.length;
            setTimeout(typewriterTick, 400);
            return;
        }
        setTimeout(typewriterTick, 50);
    } else {
        charIndex++;
        tw.textContent = current.substring(0, charIndex);
        if (charIndex === current.length) {
            isDeleting = true;
            setTimeout(typewriterTick, 2000);
            return;
        }
        setTimeout(typewriterTick, 80);
    }
}
setTimeout(typewriterTick, 1500);

// ── Scroll reveal (Intersection Observer) ──
const revealEls = document.querySelectorAll('.reveal');
const observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, { threshold: 0.15 });
revealEls.forEach(function(el) { observer.observe(el); });

// ── FAQ accordion ──
function toggleFaq(btn) {
    var item = btn.parentElement;
    var isOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item.open').forEach(function(el) {
        el.classList.remove('open');
    });
    if (!isOpen) {
        item.classList.add('open');
    }
}

// ── Suggestion form ──
function submitSuggestion(e) {
    e.preventDefault();
    var form = document.getElementById('suggestForm');
    var btn = document.getElementById('suggestSubmit');
    var feedback = document.getElementById('suggestFeedback');
    var data = new FormData(form);

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    feedback.className = 'form-feedback';
    feedback.style.display = 'none';

    fetch('api/submit_suggestion.php', {
        method: 'POST',
        body: data
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            feedback.className = 'form-feedback success';
            feedback.textContent = 'Thank you! Your suggestion has been received.';
            feedback.style.display = 'block';
            form.reset();
        } else {
            feedback.className = 'form-feedback error';
            feedback.textContent = res.message || 'Something went wrong. Please try again.';
            feedback.style.display = 'block';
        }
    })
    .catch(function() {
        feedback.className = 'form-feedback error';
        feedback.textContent = 'Network error. Please check your connection and try again.';
        feedback.style.display = 'block';
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Suggestion';
    });
}

</script>

<!-- ═══ Auth Modal ═══ -->
<div class="modal-overlay" id="authModal">
    <div class="modal-window">
        <button class="modal-close" onclick="closeAuthModal()" aria-label="Close"><i class="fas fa-times"></i></button>

        <div class="modal-logo">
            <img src="logo/m-blues.png" alt="Muffeia">
            <span>Muffeia</span>
        </div>

        <div class="auth-modal-tabs">
            <button class="auth-modal-tab active" data-tab="login" onclick="switchAuthTab('login')"><i class="fas fa-sign-in-alt"></i> Sign In</button>
            <button class="auth-modal-tab" data-tab="register" onclick="switchAuthTab('register')"><i class="fas fa-user-plus"></i> Sign Up</button>
        </div>

        <div id="authModalError" class="auth-modal-error"></div>
        <div id="authModalSuccess" class="auth-modal-success"></div>

        <!-- Login Form -->
        <form class="auth-modal-form active" id="authLoginForm" onsubmit="submitAuthForm(event, 'login')">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="form-group">
                <label for="authLoginEmail">Email Address</label>
                <input type="email" id="authLoginEmail" name="email" class="form-input" placeholder="you@example.com" required>
            </div>

            <div class="form-group">
                <label for="authLoginPassword">Password</label>
                <div class="password-wrap">
                    <input type="password" id="authLoginPassword" name="password" class="form-input" placeholder="Enter your password" required>
                    <i class="fas fa-eye password-toggle" onclick="togglePassword(this)" data-target="authLoginPassword"></i>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="authLoginBtn"><i class="fas fa-sign-in-alt"></i> Sign In</button>
        </form>

        <!-- Register Form -->
        <form class="auth-modal-form" id="authRegisterForm" onsubmit="submitAuthForm(event, 'register')">
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="form-group">
                <label for="authRegUsername">Username</label>
                <input type="text" id="authRegUsername" name="username" class="form-input" placeholder="Choose a username" pattern="[a-zA-Z0-9_]{3,30}" title="Letters, numbers, underscores (3-30 characters)" required>
            </div>

            <div class="form-group">
                <label for="authRegEmail">Email</label>
                <input type="email" id="authRegEmail" name="email" class="form-input" placeholder="you@example.com" required>
            </div>

            <div class="form-group">
                <label for="authRegPassword">Password</label>
                <div class="password-wrap">
                    <input type="password" id="authRegPassword" name="password" class="form-input" placeholder="Create a strong password" required>
                    <i class="fas fa-eye password-toggle" onclick="togglePassword(this)" data-target="authRegPassword"></i>
                </div>
                <div class="password-strength">
                    <div class="strength-bar"><div class="strength-bar-fill" id="authStrengthFill"></div></div>
                    <div class="strength-text" id="authStrengthText"></div>
                    <ul class="strength-requirements" id="authStrengthReqs">
                        <li class="unmet"><i class="far fa-circle"></i> At least 8 characters</li>
                        <li class="unmet"><i class="far fa-circle"></i> One uppercase letter</li>
                        <li class="unmet"><i class="far fa-circle"></i> One lowercase letter</li>
                        <li class="unmet"><i class="far fa-circle"></i> One number</li>
                        <li class="unmet"><i class="far fa-circle"></i> One special character</li>
                    </ul>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="authRegisterBtn" disabled><i class="fas fa-user-plus"></i> Create Account</button>
        </form>

        <div class="auth-modal-switch" id="authModalSwitch">
            Don't have an account? <a onclick="switchAuthTab('register')">Sign up</a>
        </div>
    </div>
</div>

<script>
// ── Auth Modal ──
function openAuthModal(tab) {
    document.getElementById('authModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    switchAuthTab(tab);
    clearAuthMessages();
}

function closeAuthModal() {
    document.getElementById('authModal').classList.remove('active');
    document.body.style.overflow = '';
}

function switchAuthTab(tab) {
    document.querySelectorAll('.auth-modal-tab').forEach(function(t) {
        t.classList.toggle('active', t.dataset.tab === tab);
    });
    document.querySelectorAll('.auth-modal-form').forEach(function(f) {
        f.classList.remove('active');
    });
    if (tab === 'login') {
        document.getElementById('authLoginForm').classList.add('active');
        document.getElementById('authModalSwitch').innerHTML = 'Don\'t have an account? <a onclick="switchAuthTab(\'register\')">Sign up</a>';
    } else {
        document.getElementById('authRegisterForm').classList.add('active');
        document.getElementById('authModalSwitch').innerHTML = 'Already have an account? <a onclick="switchAuthTab(\'login\')">Sign in</a>';
    }
    clearAuthMessages();
}

function clearAuthMessages() {
    var err = document.getElementById('authModalError');
    var suc = document.getElementById('authModalSuccess');
    err.classList.remove('show');
    suc.classList.remove('show');
    err.textContent = '';
    suc.textContent = '';
}

function showAuthError(msg) {
    var el = document.getElementById('authModalError');
    el.textContent = msg;
    el.classList.add('show');
    document.getElementById('authModalSuccess').classList.remove('show');
}

function showAuthSuccess(msg) {
    var el = document.getElementById('authModalSuccess');
    el.textContent = msg;
    el.classList.add('show');
    document.getElementById('authModalError').classList.remove('show');
}

// ── AJAX form submission ──
function submitAuthForm(e, action) {
    e.preventDefault();
    var form = action === 'login' ? document.getElementById('authLoginForm') : document.getElementById('authRegisterForm');
    var btn = action === 'login' ? document.getElementById('authLoginBtn') : document.getElementById('authRegisterBtn');
    var formData = new FormData(form);

    clearAuthMessages();
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    fetch('auth/api.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            if (res.redirect) {
                window.location.href = res.redirect;
            } else {
                showAuthSuccess(res.message || 'Success!');
                if (res.switchTab) {
                    setTimeout(function() { switchAuthTab(res.switchTab); }, 1200);
                }
                form.reset();
                if (action === 'register') {
                    document.getElementById('authRegisterBtn').disabled = true;
                    resetStrengthMeter();
                }
            }
        } else {
            showAuthError(res.message || 'Something went wrong. Please try again.');
        }
    })
    .catch(function() {
        showAuthError('Network error. Please check your connection and try again.');
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = action === 'login'
            ? '<i class="fas fa-sign-in-alt"></i> Sign In'
            : '<i class="fas fa-user-plus"></i> Create Account';
    });
}

// ── Password strength ──
function resetStrengthMeter() {
    document.getElementById('authStrengthFill').className = 'strength-bar-fill';
    document.getElementById('authStrengthText').textContent = '';
    document.querySelectorAll('#authStrengthReqs li').forEach(function(li) {
        li.className = 'unmet';
        li.querySelector('i').className = 'far fa-circle';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    var pwInput = document.getElementById('authRegPassword');
    if (pwInput) {
        pwInput.addEventListener('input', function() {
            var password = this.value;
            var strength = 0;
            var reqs = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };
            Object.values(reqs).forEach(function(v) { if (v) strength++; });

            var level = 'weak';
            if (strength >= 5) level = 'very-strong';
            else if (strength >= 3) level = 'medium';

            var fill = document.getElementById('authStrengthFill');
            var text = document.getElementById('authStrengthText');
            var labels = { weak: 'Weak', medium: 'Medium', strong: 'Strong', 'very-strong': 'Very Strong' };

            if (password.length === 0) {
                fill.className = 'strength-bar-fill';
                text.textContent = '';
            } else {
                fill.className = 'strength-bar-fill ' + level;
                text.className = 'strength-text ' + level;
                text.textContent = labels[level] + ' Password';
            }

            var reqList = Object.entries(reqs);
            document.querySelectorAll('#authStrengthReqs li').forEach(function(li, index) {
                if (index < reqList.length) {
                    if (reqList[index][1]) {
                        li.className = 'met';
                        li.querySelector('i').className = 'fas fa-check-circle';
                    } else {
                        li.className = 'unmet';
                        li.querySelector('i').className = 'far fa-circle';
                    }
                }
            });

            document.getElementById('authRegisterBtn').disabled = level === 'weak' || password.length === 0;
        });
    }
});

// ── Password toggle ──
function togglePassword(el) {
    var target = document.getElementById(el.dataset.target);
    if (target) {
        if (target.type === 'password') {
            target.type = 'text';
            el.classList.remove('fa-eye');
            el.classList.add('fa-eye-slash');
        } else {
            target.type = 'password';
            el.classList.remove('fa-eye-slash');
            el.classList.add('fa-eye');
        }
    }
}

// ── Close modal on overlay click / Escape ──
document.addEventListener('click', function(e) {
    if (e.target === document.getElementById('authModal')) {
        closeAuthModal();
    }
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAuthModal();
});
</script>
<?php if (!empty($_SESSION["is_admin"])): ?><script src="/js/admin-notifications.js"></script><?php endif; ?>
</body>
</html>
