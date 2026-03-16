<?php
// ============================================================
//  PANACEA HOSPITAL – Public Website (index.php)
//  Place this file at: C:\xampp\htdocs\panacea\index.php
// ============================================================
require_once dirname(__FILE__) . '/config/database.php';

// Load departments and doctors from database
$pdo = db();
$departments = $pdo->query('SELECT * FROM departments WHERE is_active=1 ORDER BY name')->fetchAll();
$doctors     = $pdo->query('SELECT d.*,dep.name AS dept_name FROM doctors d JOIN departments dep ON d.department_id=dep.id WHERE d.is_active=1 ORDER BY d.full_name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Panacea Hospital – Hawassa, Ethiopia</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --blue-deep:#0a2e5c;--blue-mid:#1a5fa0;--blue-bright:#2e8dd4;
      --green-soft:#3aaa8c;--green-light:#e8f7f3;--white:#ffffff;
      --off-white:#f7f9fc;--gray-light:#edf1f7;--gray-mid:#8899b0;
      --gray-dark:#3d4f6b;--text-main:#1a2b42;
      --shadow-sm:0 2px 12px rgba(10,46,92,0.08);
      --shadow-md:0 8px 32px rgba(10,46,92,0.14);
      --shadow-lg:0 20px 60px rgba(10,46,92,0.18);
      --radius:14px;--radius-lg:24px;
      --transition:0.35s cubic-bezier(0.4,0,0.2,1);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html{scroll-behavior:smooth}
    body{font-family:'DM Sans',sans-serif;color:var(--text-main);background:var(--white);overflow-x:hidden}
    h1,h2,h3,h4{font-family:'Playfair Display',serif}

    /* TOPBAR */
    .topbar{background:var(--blue-deep);color:rgba(255,255,255,.75);font-size:.8rem;padding:7px 0}
    .topbar a{color:rgba(255,255,255,.75);text-decoration:none}
    .topbar a:hover{color:#fff}
    .topbar .sep{margin:0 12px;opacity:.3}

    /* NAVBAR */
    .navbar{background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid rgba(10,46,92,.07);padding:0;transition:box-shadow var(--transition)}
    .navbar.scrolled{box-shadow:var(--shadow-md)}
    .navbar-brand{display:flex;align-items:center;gap:10px;padding:14px 0}
    .brand-icon{width:42px;height:42px;background:linear-gradient(135deg,var(--blue-mid),var(--green-soft));border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem}
    .brand-text strong{display:block;font-family:'Playfair Display',serif;font-weight:700;font-size:1.15rem;color:var(--blue-deep)}
    .brand-text span{font-size:.7rem;color:var(--gray-mid);letter-spacing:.05em;text-transform:uppercase}
    .nav-link{font-size:.875rem;font-weight:500;color:var(--gray-dark)!important;padding:22px 14px!important;position:relative;transition:color var(--transition)}
    .nav-link::after{content:'';position:absolute;bottom:0;left:14px;right:14px;height:2px;background:var(--blue-bright);transform:scaleX(0);transition:transform var(--transition)}
    .nav-link:hover,.nav-link.active{color:var(--blue-mid)!important}
    .nav-link:hover::after,.nav-link.active::after{transform:scaleX(1)}
    .btn-emergency{background:linear-gradient(135deg,#e8334a,#c0162c);color:#fff!important;border-radius:8px;padding:9px 18px!important;font-size:.8rem;font-weight:600;letter-spacing:.03em;text-transform:uppercase;animation:pulse-red 2.5s infinite}
    @keyframes pulse-red{0%,100%{box-shadow:0 0 0 0 rgba(232,51,74,.4)}50%{box-shadow:0 0 0 8px rgba(232,51,74,0)}}
    .btn-emergency::after{display:none}

    /* HERO */
    #hero{min-height:100vh;position:relative;display:flex;align-items:center;overflow:hidden;background:var(--blue-deep)}
    .hero-bg{position:absolute;inset:0;background:linear-gradient(135deg,rgba(10,46,92,.92) 0%,rgba(26,95,160,.7) 50%,rgba(58,170,140,.5) 100%),url('https://images.unsplash.com/photo-1586773860418-d37222d8fce3?w=1800&q=80') center/cover no-repeat}
    .hero-pattern{position:absolute;inset:0;background-image:radial-gradient(circle at 2px 2px,rgba(255,255,255,.04) 1px,transparent 0);background-size:32px 32px}
    .hero-content{position:relative;z-index:2}
    .hero-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:rgba(255,255,255,.9);padding:7px 16px;border-radius:50px;font-size:.8rem;font-weight:500;letter-spacing:.05em;text-transform:uppercase;margin-bottom:24px;backdrop-filter:blur(8px);animation:fadeInUp .6s ease both}
    .hero-badge i{color:var(--green-soft);font-size:.85rem}
    #hero h1{font-size:clamp(2.4rem,5vw,4rem);font-weight:700;color:#fff;line-height:1.15;margin-bottom:20px;animation:fadeInUp .6s .15s ease both}
    #hero h1 span{color:#7dd5c0}
    #hero p.lead{font-size:clamp(1rem,2vw,1.15rem);color:rgba(255,255,255,.8);max-width:560px;line-height:1.7;margin-bottom:36px;animation:fadeInUp .6s .3s ease both;font-weight:300}
    .hero-actions{display:flex;flex-wrap:wrap;gap:14px;animation:fadeInUp .6s .45s ease both}
    .btn-hero-primary{background:linear-gradient(135deg,var(--blue-bright),var(--blue-mid));color:#fff;border:none;padding:14px 30px;border-radius:10px;font-weight:600;font-size:.95rem;transition:all var(--transition);box-shadow:0 4px 20px rgba(46,141,212,.4);display:flex;align-items:center;gap:8px;text-decoration:none}
    .btn-hero-primary:hover{transform:translateY(-2px);box-shadow:0 8px 32px rgba(46,141,212,.5);color:#fff}
    .btn-hero-outline{background:transparent;color:#fff;border:1.5px solid rgba(255,255,255,.5);padding:14px 30px;border-radius:10px;font-weight:600;font-size:.95rem;transition:all var(--transition);display:flex;align-items:center;gap:8px;backdrop-filter:blur(8px);text-decoration:none}
    .btn-hero-outline:hover{background:rgba(255,255,255,.12);border-color:#fff;color:#fff;transform:translateY(-2px)}
    .hero-stats{display:flex;flex-wrap:wrap;gap:24px;margin-top:60px;padding-top:40px;border-top:1px solid rgba(255,255,255,.1);animation:fadeInUp .6s .6s ease both}
    .stat-item{text-align:center}
    .stat-item strong{display:block;font-family:'Playfair Display',serif;font-size:2rem;color:#fff;font-weight:700}
    .stat-item span{font-size:.78rem;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.08em}
    .hero-card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:var(--radius-lg);padding:32px 28px;backdrop-filter:blur(16px);animation:fadeInRight .8s .3s ease both}
    .hero-card h4{font-family:'DM Sans',sans-serif;font-weight:600;font-size:.9rem;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.08em;margin-bottom:20px}
    .quick-service{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:10px;background:rgba(255,255,255,.06);margin-bottom:10px;transition:background var(--transition);cursor:pointer}
    .quick-service:hover{background:rgba(255,255,255,.12)}
    .qs-icon{width:40px;height:40px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
    .quick-service .info strong{display:block;color:#fff;font-size:.88rem;font-weight:600}
    .quick-service .info span{color:rgba(255,255,255,.55);font-size:.75rem}

    /* SECTIONS */
    section{padding:90px 0}
    .section-label{display:inline-flex;align-items:center;gap:8px;color:var(--blue-bright);font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.12em;margin-bottom:12px}
    .section-label::before{content:'';width:28px;height:2px;background:var(--blue-bright);border-radius:2px}
    .section-title{font-size:clamp(1.8rem,3.5vw,2.8rem);font-weight:700;color:var(--blue-deep);line-height:1.2;margin-bottom:16px}
    .section-sub{color:var(--gray-mid);font-size:1rem;line-height:1.7;max-width:560px;font-weight:300}
    .divider{width:60px;height:3px;background:linear-gradient(90deg,var(--blue-bright),var(--green-soft));border-radius:2px;margin:20px 0 32px}
    .divider.mx-auto{margin-left:auto;margin-right:auto}

    /* ABOUT */
    #about{background:var(--off-white)}
    .about-img-wrap{position:relative;border-radius:var(--radius-lg);overflow:hidden}
    .about-img-wrap img{width:100%;border-radius:var(--radius-lg);box-shadow:var(--shadow-lg)}
    .about-badge-float{position:absolute;bottom:28px;right:-20px;background:#fff;border-radius:var(--radius);padding:16px 22px;box-shadow:var(--shadow-md);text-align:center;min-width:140px}
    .about-badge-float strong{display:block;font-family:'Playfair Display',serif;font-size:1.8rem;color:var(--blue-deep)}
    .about-badge-float span{font-size:.75rem;color:var(--gray-mid)}
    .mission-card{background:#fff;border-radius:var(--radius);padding:24px;margin-bottom:16px;border-left:3px solid var(--blue-bright);box-shadow:var(--shadow-sm);transition:all var(--transition)}
    .mission-card:hover{transform:translateX(4px);box-shadow:var(--shadow-md)}
    .mission-card h5{color:var(--blue-deep);font-size:1rem;margin-bottom:8px;font-family:'DM Sans',sans-serif;font-weight:600}
    .mission-card p{color:var(--gray-mid);font-size:.88rem;line-height:1.65;margin:0}
    .mission-card.vision{border-left-color:var(--green-soft)}

    /* DEPARTMENTS */
    #departments{background:#fff}
    .dept-card{background:#fff;border-radius:var(--radius);padding:30px 24px;text-align:center;border:1px solid var(--gray-light);transition:all var(--transition);height:100%;position:relative;overflow:hidden}
    .dept-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--blue-bright),var(--green-soft));transform:scaleX(0);transform-origin:left;transition:transform var(--transition)}
    .dept-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-md);border-color:transparent}
    .dept-card:hover::before{transform:scaleX(1)}
    .dept-icon{width:68px;height:68px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:1.7rem;margin:0 auto 18px;transition:all var(--transition)}
    .dept-card:hover .dept-icon{transform:scale(1.1) rotate(-4deg)}
    .dept-card h5{font-family:'DM Sans',sans-serif;font-weight:600;font-size:.95rem;color:var(--blue-deep);margin-bottom:8px}
    .dept-card p{font-size:.82rem;color:var(--gray-mid);line-height:1.6;margin:0}

    /* WHY */
    #why{background:linear-gradient(135deg,var(--blue-deep) 0%,#0e3d7a 100%);position:relative;overflow:hidden}
    #why::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle at 2px 2px,rgba(255,255,255,.03) 1px,transparent 0);background-size:28px 28px}
    .why-card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:var(--radius);padding:32px 24px;transition:all var(--transition);height:100%}
    .why-card:hover{background:rgba(255,255,255,.1);transform:translateY(-4px)}
    .why-icon{width:56px;height:56px;background:linear-gradient(135deg,rgba(46,141,212,.3),rgba(58,170,140,.3));border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#7dd5c0;margin-bottom:18px}
    .why-card h5{color:#fff;font-family:'DM Sans',sans-serif;font-weight:600;font-size:1rem;margin-bottom:10px}
    .why-card p{color:rgba(255,255,255,.55);font-size:.85rem;line-height:1.65;margin:0}

    /* DOCTORS */
    #doctors{background:var(--off-white)}
    .doctor-card{background:#fff;border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-sm);transition:all var(--transition);height:100%}
    .doctor-card:hover{transform:translateY(-8px);box-shadow:var(--shadow-lg)}
    .doctor-img{height:240px;position:relative;overflow:hidden;background:linear-gradient(135deg,var(--blue-deep),var(--blue-mid))}
    .doctor-img img{width:100%;height:100%;object-fit:cover;object-position:top;transition:transform var(--transition)}
    .doctor-card:hover .doctor-img img{transform:scale(1.05)}
    .doctor-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(10,46,92,.5),transparent)}
    .doctor-specialty-badge{position:absolute;top:16px;right:16px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;font-size:.72rem;font-weight:600;padding:5px 12px;border-radius:20px;backdrop-filter:blur(8px);text-transform:uppercase;letter-spacing:.06em}
    .doctor-body{padding:22px 24px 24px}
    .doctor-body h5{color:var(--blue-deep);font-size:1.05rem;font-weight:700;margin-bottom:4px}
    .doctor-spec{color:var(--blue-bright);font-size:.82rem;font-weight:600;margin-bottom:6px}
    .doctor-exp{color:var(--gray-mid);font-size:.8rem;margin-bottom:12px}
    .doctor-bio{color:var(--gray-dark);font-size:.83rem;line-height:1.6;margin-bottom:16px}

    /* TESTIMONIALS */
    #testimonials{background:var(--off-white)}
    .testi-card{background:#fff;border-radius:var(--radius-lg);padding:32px 28px;box-shadow:var(--shadow-sm);transition:all var(--transition);height:100%}
    .testi-card:hover{box-shadow:var(--shadow-md);transform:translateY(-4px)}
    .testi-quote{font-size:3rem;color:var(--blue-bright);line-height:1;margin-bottom:16px;font-family:'Playfair Display',serif;opacity:.3}
    .testi-text{font-size:.92rem;line-height:1.75;color:var(--gray-dark);margin-bottom:24px;font-style:italic}
    .testi-stars{color:#f6b83d;font-size:.85rem;margin-bottom:16px}
    .testi-author{display:flex;align-items:center;gap:12px}
    .testi-avatar{width:46px;height:46px;border-radius:50%;object-fit:cover;border:2px solid var(--gray-light)}
    .testi-author-info strong{display:block;font-size:.88rem;color:var(--blue-deep);font-weight:600}
    .testi-author-info span{font-size:.78rem;color:var(--gray-mid)}

    /* EMERGENCY */
    #emergency{background:linear-gradient(135deg,#c0162c,#8b0b1e);position:relative;overflow:hidden;padding:80px 0}
    #emergency::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle at 2px 2px,rgba(255,255,255,.04) 1px,transparent 0);background-size:24px 24px}
    #emergency h2{color:#fff;font-size:clamp(1.6rem,3vw,2.4rem);margin-bottom:12px}
    #emergency p{color:rgba(255,255,255,.75);font-size:1rem;margin-bottom:32px}
    .btn-emergency-large{background:#fff;color:#c0162c;border:none;padding:16px 36px;border-radius:10px;font-weight:700;font-size:1rem;display:inline-flex;align-items:center;gap:10px;transition:all var(--transition);box-shadow:0 4px 24px rgba(0,0,0,.2);text-decoration:none}
    .btn-emergency-large:hover{transform:translateY(-3px) scale(1.02);box-shadow:0 10px 40px rgba(0,0,0,.3);color:#c0162c}

    /* APPOINTMENT */
    #appointment{background:#fff}
    .appt-form-wrap{background:var(--off-white);border-radius:var(--radius-lg);padding:40px 36px;box-shadow:var(--shadow-sm)}
    .form-control,.form-select{border:1.5px solid var(--gray-light);border-radius:10px;padding:12px 16px;font-size:.9rem;color:var(--text-main);background:#fff;transition:all var(--transition)}
    .form-control:focus,.form-select:focus{border-color:var(--blue-bright);box-shadow:0 0 0 3px rgba(46,141,212,.12)}
    .form-label{font-size:.82rem;font-weight:600;color:var(--blue-deep);margin-bottom:6px}
    .btn-submit{background:linear-gradient(135deg,var(--blue-mid),var(--blue-bright));color:#fff;border:none;padding:14px 36px;border-radius:10px;font-weight:600;font-size:.95rem;width:100%;transition:all var(--transition);cursor:pointer}
    .btn-submit:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(26,95,160,.4)}
    .appt-info-card{background:linear-gradient(135deg,var(--blue-deep),var(--blue-mid));border-radius:var(--radius-lg);padding:40px 36px;color:#fff;height:100%}
    .hours-list{list-style:none;padding:0;margin:0}
    .hours-list li{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(255,255,255,.1);font-size:.87rem;color:rgba(255,255,255,.8)}
    .hours-list li span{font-weight:600;color:#7dd5c0}

    /* CONTACT */
    #contact{background:var(--off-white)}
    .contact-info-item{display:flex;align-items:flex-start;gap:16px;margin-bottom:28px}
    .contact-icon{width:48px;height:48px;background:linear-gradient(135deg,var(--blue-bright),var(--blue-mid));border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.15rem;flex-shrink:0}
    .contact-info-item h6{font-size:.75rem;font-weight:600;color:var(--gray-mid);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
    .contact-info-item p{color:var(--text-main);font-size:.9rem;line-height:1.6;margin:0}
    .map-wrap{border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-md);margin-top:32px}
    .map-wrap iframe{width:100%;height:300px;border:0;display:block}
    .contact-form-card{background:#fff;border-radius:var(--radius-lg);padding:40px 36px;box-shadow:var(--shadow-sm)}

    /* FOOTER */
    footer{background:var(--blue-deep);color:rgba(255,255,255,.65);padding:70px 0 0}
    .footer-brand-icon{width:48px;height:48px;background:linear-gradient(135deg,var(--blue-mid),var(--green-soft));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;margin-bottom:12px}
    .footer-heading{color:#fff;font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:20px;font-family:'DM Sans',sans-serif}
    .footer-links{list-style:none;padding:0;margin:0}
    .footer-links li{margin-bottom:10px}
    .footer-links a{color:rgba(255,255,255,.55);text-decoration:none;font-size:.85rem;transition:color var(--transition);display:flex;align-items:center;gap:6px}
    .footer-links a:hover{color:#7dd5c0}
    .footer-social{display:flex;gap:10px;margin-top:20px}
    .social-btn{width:38px;height:38px;background:rgba(255,255,255,.08);border-radius:9px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.6);text-decoration:none;font-size:.95rem;transition:all var(--transition)}
    .social-btn:hover{background:var(--blue-bright);color:#fff}
    .footer-emergency{background:rgba(192,22,44,.2);border:1px solid rgba(192,22,44,.3);border-radius:10px;padding:16px 20px;margin-top:16px}
    .footer-emergency strong{color:#ff6b7a;font-size:.82rem;display:block;margin-bottom:4px}
    .footer-emergency span{color:#fff;font-size:1.1rem;font-weight:700}
    .footer-bottom{margin-top:50px;padding:20px 0;border-top:1px solid rgba(255,255,255,.07);font-size:.8rem}

    /* ALERTS */
    .alert-success-custom{background:var(--green-light);border-radius:10px;border:1px solid rgba(58,170,140,.3);padding:16px 20px;display:none;margin-top:16px}
    .alert-error-custom{background:#fff0f0;border-radius:10px;border:1px solid rgba(192,22,44,.3);padding:16px 20px;display:none;margin-top:16px}

    /* ANIMATIONS */
    @keyframes fadeInUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
    @keyframes fadeInRight{from{opacity:0;transform:translateX(24px)}to{opacity:1;transform:translateX(0)}}
    .reveal{opacity:0;transform:translateY(32px);transition:opacity .7s ease,transform .7s ease}
    .reveal.visible{opacity:1;transform:none}
    .reveal-delay-1{transition-delay:.1s}
    .reveal-delay-2{transition-delay:.2s}
    .reveal-delay-3{transition-delay:.3s}
    .reveal-delay-4{transition-delay:.4s}

    /* BACK TO TOP */
    #backToTop{position:fixed;bottom:28px;right:28px;width:44px;height:44px;background:var(--blue-mid);color:#fff;border:none;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;cursor:pointer;opacity:0;pointer-events:none;transition:all var(--transition);z-index:9999;box-shadow:var(--shadow-md)}
    #backToTop.show{opacity:1;pointer-events:all}
    #backToTop:hover{background:var(--blue-deep);transform:translateY(-2px)}

    @media(max-width:991px){.hero-card{margin-top:40px}.about-badge-float{right:0}}
    @media(max-width:767px){section{padding:64px 0}}
    @media(max-width:575px){.appt-form-wrap,.contact-form-card{padding:24px 20px}}
  </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar d-none d-md-block">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <i class="bi bi-geo-alt-fill me-1"></i> Hawassa, Sidama Region, Ethiopia
        <span class="sep">|</span>
        <i class="bi bi-clock me-1"></i> Mon–Sat: 7:00 AM – 9:00 PM
      </div>
      <div>
        <a href="mailto:info@panaceahospital.et"><i class="bi bi-envelope me-1"></i> info@panaceahospital.et</a>
        <span class="sep">|</span>
        <a href="tel:+251917000000"><i class="bi bi-telephone me-1"></i> +251 917 000 000</a>
      </div>
    </div>
  </div>
</div>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg sticky-top" id="mainNav">
  <div class="container">
    <a class="navbar-brand" href="#">
      <div class="brand-icon"><i class="bi bi-hospital"></i></div>
      <div class="brand-text">
        <strong>Panacea Hospital</strong>
        <span>Hawassa, Ethiopia</span>
      </div>
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav align-items-lg-center gap-1">
        <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
        <li class="nav-item"><a class="nav-link" href="#departments">Departments</a></li>
        <li class="nav-item"><a class="nav-link" href="#doctors">Doctors</a></li>
        <li class="nav-item"><a class="nav-link" href="#appointment">Appointment</a></li>
        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
        <li class="nav-item ms-2">
          <li class="nav-item">
  <a class="nav-link" href="/panacea/portal.php">
    <i class="bi bi-person-circle me-1"></i>Patient Portal
  </a>
</li>
          <a class="nav-link btn-emergency" href="tel:+251917000000">
            <i class="bi bi-telephone-fill me-1"></i> Emergency
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- HERO -->
<section id="hero">
  <div class="hero-bg"></div>
  <div class="hero-pattern"></div>
  <div class="container py-5">
    <div class="row align-items-center gy-5">
      <div class="col-lg-7 hero-content">
        <div class="hero-badge"><i class="bi bi-patch-check-fill"></i> Accredited Healthcare Provider · Hawassa</div>
        <h1>Advanced Healthcare<br><span>You Can Trust</span></h1>
        <p class="lead">Panacea Hospital – Delivering quality medical care for the Hawassa community and Sidama Region with compassion, expertise, and modern medical technology.</p>
        <div class="hero-actions">
          <a href="#appointment" class="btn-hero-primary"><i class="bi bi-calendar2-check"></i> Book Appointment</a>
          <a href="tel:+251917000000" class="btn-hero-outline"><i class="bi bi-telephone-fill"></i> Emergency: +251 917 000 000</a>
        </div>
        <div class="hero-stats">
          <div class="stat-item"><strong>15+</strong><span>Years of Service</span></div>
          <div class="stat-item"><strong><?= count($doctors) ?>+</strong><span>Specialists</span></div>
          <div class="stat-item"><strong>50k+</strong><span>Patients Served</span></div>
          <div class="stat-item"><strong>24/7</strong><span>Emergency Care</span></div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="hero-card">
          <h4><i class="bi bi-lightning-charge-fill me-2" style="color:#f6b83d"></i>Quick Access</h4>
          <div class="quick-service" onclick="document.getElementById('appointment').scrollIntoView({behavior:'smooth'})">
            <div class="qs-icon" style="background:rgba(46,141,212,.2);color:#7dd5c0"><i class="bi bi-calendar2-plus"></i></div>
            <div class="info"><strong>Book an Appointment</strong><span>Schedule with a specialist</span></div>
            <i class="bi bi-chevron-right ms-auto" style="color:rgba(255,255,255,.3)"></i>
          </div>
          <div class="quick-service" onclick="window.location.href='tel:+251917000000'">
            <div class="qs-icon" style="background:rgba(192,22,44,.2);color:#ff6b7a"><i class="bi bi-heart-pulse"></i></div>
            <div class="info"><strong>Emergency Department</strong><span>24/7 critical care available</span></div>
            <i class="bi bi-chevron-right ms-auto" style="color:rgba(255,255,255,.3)"></i>
          </div>
          <div class="quick-service" onclick="document.getElementById('doctors').scrollIntoView({behavior:'smooth'})">
            <div class="qs-icon" style="background:rgba(58,170,140,.2);color:#7dd5c0"><i class="bi bi-person-lines-fill"></i></div>
            <div class="info"><strong>Find a Doctor</strong><span>Browse our specialists</span></div>
            <i class="bi bi-chevron-right ms-auto" style="color:rgba(255,255,255,.3)"></i>
          </div>
          <div class="quick-service" onclick="document.getElementById('departments').scrollIntoView({behavior:'smooth'})">
            <div class="qs-icon" style="background:rgba(246,184,61,.2);color:#f6b83d"><i class="bi bi-flask"></i></div>
            <div class="info"><strong>Our Departments</strong><span><?= count($departments) ?> specialized units</span></div>
            <i class="bi bi-chevron-right ms-auto" style="color:rgba(255,255,255,.3)"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ABOUT -->
<section id="about">
  <div class="container">
    <div class="row align-items-center gy-5">
      <div class="col-lg-5 reveal">
        <div class="about-img-wrap">
          <img src="https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=800&q=80" alt="Panacea Hospital"/>
          <div class="about-badge-float"><strong>2009</strong><span>Year Founded</span></div>
        </div>
      </div>
      <div class="col-lg-7 ps-lg-5 reveal reveal-delay-2">
        <div class="section-label">About Us</div>
        <h2 class="section-title">Trusted Healthcare in the Heart of Hawassa</h2>
        <div class="divider"></div>
        <p class="section-sub mb-4">Panacea Hospital has been a cornerstone of healthcare in the Hawassa community and Sidama Region since 2009. We deliver comprehensive, compassionate, and high-quality medical services.</p>
        <p style="color:var(--gray-mid);font-size:.9rem;line-height:1.8;margin-bottom:28px">Our hospital is equipped with modern diagnostic and treatment facilities staffed by dedicated Ethiopian and international medical specialists. We serve patients across all age groups.</p>
        <div class="mission-card">
          <h5><i class="bi bi-bullseye me-2" style="color:var(--blue-bright)"></i>Our Mission</h5>
          <p>To provide accessible, affordable, and advanced medical care to every person in our community, guided by respect, integrity, and clinical excellence.</p>
        </div>
        <div class="mission-card vision">
          <h5><i class="bi bi-eye me-2" style="color:var(--green-soft)"></i>Our Vision</h5>
          <p>To be the leading hospital in Southern Ethiopia, recognized for exceptional patient outcomes, innovative treatments, and a caring environment.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- DEPARTMENTS — loaded from database -->
<section id="departments">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label mx-auto justify-content-center">Our Departments</div>
      <h2 class="section-title">Comprehensive Medical Services</h2>
      <div class="divider mx-auto"></div>
      <p class="section-sub mx-auto text-center">Our specialized departments are staffed by experienced professionals dedicated to the highest level of care.</p>
    </div>
    <?php
    $deptColors = [
      ['bg'=>'#e8f2fb','color'=>'#1a5fa0'],['bg'=>'#fef4e8','color'=>'#e07b1a'],
      ['bg'=>'#edf7f3','color'=>'#3aaa8c'],['bg'=>'#fbe8ec','color'=>'#c0162c'],
      ['bg'=>'#f0ebfc','color'=>'#7c4ddc'],['bg'=>'#e8f7f3','color'=>'#1a9e7a'],
      ['bg'=>'#fbe8ec','color'=>'#e8334a'],['bg'=>'#e8f4fb','color'=>'#2e8dd4'],
    ];
    ?>
    <div class="row g-4">
      <?php foreach ($departments as $i => $dept):
        $c = $deptColors[$i % count($deptColors)];
        $delays = ['','reveal-delay-1','reveal-delay-2','reveal-delay-3'];
      ?>
      <div class="col-6 col-md-4 col-lg-3 reveal <?= $delays[$i % 4] ?>">
        <div class="dept-card">
          <div class="dept-icon" style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>">
            <i class="bi <?= htmlspecialchars($dept['icon']) ?>"></i>
          </div>
          <h5><?= htmlspecialchars($dept['name']) ?></h5>
          <p><?= htmlspecialchars($dept['description'] ?? '') ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- WHY CHOOSE -->
<section id="why">
  <div class="container position-relative">
    <div class="row align-items-center gy-5">
      <div class="col-lg-5 reveal">
        <div class="section-label" style="color:#7dd5c0"><span style="width:28px;height:2px;background:#7dd5c0;border-radius:2px;display:inline-block;margin-right:8px"></span>Why Choose Us</div>
        <h2 class="section-title" style="color:#fff">Why Patients Choose Panacea</h2>
        <div class="divider"></div>
        <p style="color:rgba(255,255,255,.6);font-size:1rem;line-height:1.7;font-weight:300">We provide not just healthcare, but a healing experience — with modern facilities, expert physicians, and genuine compassion.</p>
      </div>
      <div class="col-lg-7">
        <div class="row g-3">
          <div class="col-sm-6 reveal reveal-delay-1"><div class="why-card"><div class="why-icon"><i class="bi bi-person-badge-fill"></i></div><h5>Experienced Medical Specialists</h5><p>Over <?= count($doctors) ?> experienced doctors and specialists trained locally and internationally.</p></div></div>
          <div class="col-sm-6 reveal reveal-delay-2"><div class="why-card"><div class="why-icon"><i class="bi bi-cpu-fill"></i></div><h5>Modern Medical Equipment</h5><p>We invest in the latest diagnostic and treatment technologies for accurate, effective care.</p></div></div>
          <div class="col-sm-6 reveal reveal-delay-3"><div class="why-card"><div class="why-icon"><i class="bi bi-grid-3x3-gap-fill"></i></div><h5>Comprehensive Healthcare</h5><p><?= count($departments) ?> specialized departments covering all aspects of medical care under one roof.</p></div></div>
          <div class="col-sm-6 reveal reveal-delay-4"><div class="why-card"><div class="why-icon"><i class="bi bi-alarm-fill"></i></div><h5>24/7 Emergency Care</h5><p>Our emergency department is always ready with trained staff available around the clock.</p></div></div>
          <div class="col-12 reveal"><div class="why-card" style="flex-direction:row;display:flex;align-items:center;gap:20px"><div class="why-icon flex-shrink-0"><i class="bi bi-heart-fill"></i></div><div><h5>Patient-Centered Treatment</h5><p class="mb-0">Every decision we make puts the patient's well-being, dignity, and comfort first.</p></div></div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- DOCTORS — loaded from database -->
<section id="doctors">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label mx-auto justify-content-center">Our Specialists</div>
      <h2 class="section-title">Meet Our Medical Team</h2>
      <div class="divider mx-auto"></div>
    </div>
    <div class="row g-4">
      <?php
      $doctorPhotos = [
        'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=400&q=80',
        'https://images.unsplash.com/photo-1594824476967-48c8b964273f?w=400&q=80',
        'https://images.unsplash.com/photo-1622253692010-333f2da6031d?w=400&q=80',
        'https://images.unsplash.com/photo-1651008376811-b90baee60c1f?w=400&q=80',
      ];
      foreach ($doctors as $i => $doc):
        $photo = $doctorPhotos[$i % count($doctorPhotos)];
        $delays = ['reveal-delay-1','reveal-delay-2','reveal-delay-3','reveal-delay-4'];
      ?>
      <div class="col-sm-6 col-lg-3 reveal <?= $delays[$i % 4] ?>">
        <div class="doctor-card">
          <div class="doctor-img">
            <img src="<?= $photo ?>" alt="<?= htmlspecialchars($doc['full_name']) ?>"/>
            <div class="doctor-overlay"></div>
            <div class="doctor-specialty-badge"><?= htmlspecialchars($doc['dept_name']) ?></div>
          </div>
          <div class="doctor-body">
            <h5><?= htmlspecialchars($doc['full_name']) ?></h5>
            <div class="doctor-spec"><?= htmlspecialchars($doc['specialization']) ?></div>
            <div class="doctor-exp"><i class="bi bi-clock me-1"></i><?= $doc['years_exp'] ?> Years Experience</div>
            <p class="doctor-bio"><?= htmlspecialchars($doc['bio'] ?? '') ?></p>
            <a href="#appointment" class="btn-hero-primary" style="font-size:.8rem;padding:9px 18px;border-radius:8px;display:inline-flex">
              <i class="bi bi-calendar-check"></i> Book Appointment
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section id="testimonials">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label mx-auto justify-content-center">Patient Stories</div>
      <h2 class="section-title">What Our Patients Say</h2>
      <div class="divider mx-auto"></div>
    </div>
    <div class="row g-4">
      <div class="col-md-4 reveal reveal-delay-1">
        <div class="testi-card">
          <div class="testi-quote">"</div>
          <div class="testi-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
          <p class="testi-text">The doctors at Panacea Hospital were incredibly professional and caring. My surgery went smoothly and the recovery support was excellent. I highly recommend this hospital.</p>
          <div class="testi-author">
            <img src="https://randomuser.me/api/portraits/men/32.jpg" class="testi-avatar" alt="Abrham T."/>
            <div class="testi-author-info"><strong>Abrham Tesfaye</strong><span>Surgical Patient, Hawassa</span></div>
          </div>
        </div>
      </div>
      <div class="col-md-4 reveal reveal-delay-2">
        <div class="testi-card">
          <div class="testi-quote">"</div>
          <div class="testi-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
          <p class="testi-text">Dr. Hiwot Alemu is an exceptional pediatrician. She is patient, thorough, and genuinely caring about my children's health. The hospital is clean and the staff are always kind.</p>
          <div class="testi-author">
            <img src="https://randomuser.me/api/portraits/women/44.jpg" class="testi-avatar" alt="Selam B."/>
            <div class="testi-author-info"><strong>Selam Bekele</strong><span>Mother of Two, Sidama</span></div>
          </div>
        </div>
      </div>
      <div class="col-md-4 reveal reveal-delay-3">
        <div class="testi-card">
          <div class="testi-quote">"</div>
          <div class="testi-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i></div>
          <p class="testi-text">I delivered my baby at Panacea Hospital and the experience was wonderful. The maternity ward is clean and comfortable. Dr. Mekdes made me feel safe throughout.</p>
          <div class="testi-author">
            <img src="https://randomuser.me/api/portraits/women/68.jpg" class="testi-avatar" alt="Tigist G."/>
            <div class="testi-author-info"><strong>Tigist Getahun</strong><span>Maternity Patient, Hawassa</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- EMERGENCY -->
<section id="emergency">
  <div class="container position-relative">
    <i class="bi bi-heart-pulse" style="font-size:5rem;color:rgba(255,255,255,.1);position:absolute;right:80px;top:50%;transform:translateY(-50%)" class="d-none d-md-block"></i>
    <div class="row align-items-center">
      <div class="col-lg-8 reveal">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div style="width:52px;height:52px;background:rgba(255,255,255,.15);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:#fff">
            <i class="bi bi-lightning-charge-fill"></i>
          </div>
          <h2 class="mb-0">24/7 Emergency Medical Services Available</h2>
        </div>
        <p>Our emergency department is staffed around the clock with experienced doctors, nurses, and paramedics ready to respond to any medical crisis.</p>
        <a href="tel:+251917000000" class="btn-emergency-large">
          <i class="bi bi-telephone-fill"></i> Call Emergency: +251 917 000 000
        </a>
      </div>
    </div>
  </div>
</section>

<!-- APPOINTMENT — submits to PHP backend -->
<section id="appointment">
  <div class="container">
    <div class="row g-4 align-items-stretch">
      <div class="col-lg-5 reveal">
        <div class="appt-info-card">
          <div class="section-label" style="color:#7dd5c0"><span style="width:28px;height:2px;background:#7dd5c0;border-radius:2px;display:inline-block;margin-right:8px"></span>Book Appointment</div>
          <h3 style="color:#fff;margin-top:12px;margin-bottom:12px">Schedule Your Visit</h3>
          <p style="color:rgba(255,255,255,.7);font-size:.9rem;margin-bottom:28px">Book with one of our specialists and receive personalized, quality care.</p>
          <ul class="hours-list">
            <li>Monday – Friday <span>7:00 AM – 9:00 PM</span></li>
            <li>Saturday <span>8:00 AM – 6:00 PM</span></li>
            <li>Sunday <span>Emergency Only</span></li>
            <li>Public Holidays <span>Emergency Only</span></li>
          </ul>
          <div class="mt-4 p-3" style="background:rgba(255,255,255,.08);border-radius:10px;border:1px solid rgba(255,255,255,.1)">
            <div class="d-flex align-items-center gap-3">
              <div style="font-size:2rem;color:#7dd5c0"><i class="bi bi-telephone-fill"></i></div>
              <div>
                <div style="color:rgba(255,255,255,.6);font-size:.75rem;text-transform:uppercase;letter-spacing:.08em">Appointment Line</div>
                <div style="color:#fff;font-size:1.1rem;font-weight:700">+251 917 000 000</div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-7 reveal reveal-delay-2">
        <div class="appt-form-wrap h-100">
          <h4 style="color:var(--blue-deep);margin-bottom:8px;font-size:1.4rem">Book an Appointment</h4>
          <p style="color:var(--gray-mid);font-size:.88rem;margin-bottom:28px">Fill in the form and our team will confirm your appointment within 24 hours.</p>
          <form id="appointmentForm">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name *</label>
                <input type="text" name="patient_name" class="form-control" placeholder="Your full name" required/>
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone Number *</label>
                <input type="tel" name="patient_phone" class="form-control" placeholder="+251 9XX XXX XXX" required/>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email Address</label>
                <input type="email" name="patient_email" class="form-control" placeholder="your@email.com"/>
              </div>
              <div class="col-md-6">
                <label class="form-label">Department *</label>
                <select name="department_id" class="form-select" required>
                  <option value="">Select Department</option>
                  <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Preferred Date *</label>
                <input type="date" name="appt_date" class="form-control" min="<?= date('Y-m-d') ?>" required/>
              </div>
              <div class="col-md-6">
                <label class="form-label">Preferred Time</label>
                <select name="appt_time" class="form-select">
                  <option>Morning (7AM-12PM)</option>
                  <option>Afternoon (12PM-5PM)</option>
                  <option>Evening (5PM-9PM)</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Reason / Symptoms</label>
                <textarea name="reason" class="form-control" rows="3" placeholder="Describe your symptoms or reason for visit..."></textarea>
              </div>
              <div class="col-12">
                <button type="submit" class="btn-submit" id="apptBtn">
                  <i class="bi bi-calendar-check me-2"></i>Confirm Appointment
                </button>
              </div>
            </div>
          </form>
          <div id="apptSuccess" class="alert-success-custom">
            <i class="bi bi-check-circle-fill me-2" style="color:var(--green-soft)"></i>
            <strong id="apptMsg" style="color:var(--blue-deep)"></strong>
          </div>
          <div id="apptError" class="alert-error-custom">
            <i class="bi bi-exclamation-triangle me-2" style="color:#c0162c"></i>
            <strong id="apptErrMsg" style="color:#c0162c"></strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CONTACT — submits to PHP backend -->
<section id="contact">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label mx-auto justify-content-center">Get in Touch</div>
      <h2 class="section-title">Contact Panacea Hospital</h2>
      <div class="divider mx-auto"></div>
    </div>
    <div class="row g-4">
      <div class="col-lg-5 reveal">
        <div class="contact-info-item">
          <div class="contact-icon"><i class="bi bi-geo-alt-fill"></i></div>
          <div><h6>Hospital Address</h6><p>Hawassa City, Sidama Region<br>Southern Ethiopia<br>Near Hawassa University</p></div>
        </div>
        <div class="contact-info-item">
          <div class="contact-icon"><i class="bi bi-telephone-fill"></i></div>
          <div><h6>Phone Numbers</h6><p>Main: +251 917 000 000<br>Emergency: +251 917 111 111<br>Outpatient: +251 917 222 222</p></div>
        </div>
        <div class="contact-info-item">
          <div class="contact-icon"><i class="bi bi-envelope-fill"></i></div>
          <div><h6>Email</h6><p>info@panaceahospital.et<br>appointments@panaceahospital.et</p></div>
        </div>
        <div class="contact-info-item">
          <div class="contact-icon"><i class="bi bi-clock-fill"></i></div>
          <div><h6>Opening Hours</h6><p>Mon–Fri: 7:00 AM – 9:00 PM<br>Saturday: 8:00 AM – 6:00 PM<br>Emergency: 24/7</p></div>
        </div>
        <div class="map-wrap">
          <iframe src="https://maps.google.com/maps?q=Hawassa,+Ethiopia&output=embed&z=13" allowfullscreen loading="lazy"></iframe>
        </div>
      </div>
      <div class="col-lg-7 reveal reveal-delay-2">
        <div class="contact-form-card">
          <h4 style="color:var(--blue-deep);margin-bottom:8px;font-size:1.4rem">Send Us a Message</h4>
          <p style="color:var(--gray-mid);font-size:.88rem;margin-bottom:28px">Have a question or need information? We're here to help.</p>
          <form id="contactForm">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" placeholder="Your full name" required/>
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone Number *</label>
                <input type="tel" name="phone" class="form-control" placeholder="+251 9XX XXX XXX" required/>
              </div>
              <div class="col-12">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="your@email.com"/>
              </div>
              <div class="col-12">
                <label class="form-label">Subject</label>
                <input type="text" name="subject" class="form-control" placeholder="How can we help?"/>
              </div>
              <div class="col-12">
                <label class="form-label">Message *</label>
                <textarea name="message" class="form-control" rows="5" placeholder="Write your message here..." required></textarea>
              </div>
              <div class="col-12">
                <button type="submit" class="btn-submit" id="contactBtn">
                  <i class="bi bi-send-fill me-2"></i>Send Message
                </button>
              </div>
            </div>
          </form>
          <div id="contactSuccess" class="alert-success-custom">
            <i class="bi bi-check-circle-fill me-2" style="color:var(--green-soft)"></i>
            <strong id="contactMsg" style="color:var(--blue-deep)"></strong>
          </div>
          <div id="contactError" class="alert-error-custom">
            <i class="bi bi-exclamation-triangle me-2" style="color:#c0162c"></i>
            <strong id="contactErrMsg" style="color:#c0162c"></strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-4">
        <div class="footer-brand-icon"><i class="bi bi-hospital"></i></div>
        <strong style="display:block;font-family:'Playfair Display',serif;color:#fff;font-size:1.3rem">Panacea Hospital</strong>
        <div style="color:rgba(255,255,255,.4);font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;margin-top:4px">Hawassa, Sidama Region, Ethiopia</div>
        <p style="font-size:.85rem;line-height:1.7;max-width:280px;margin-top:12px">Panacea Hospital is committed to providing compassionate, comprehensive, and quality healthcare to the people of Hawassa and the wider Sidama Region.</p>
        <div class="footer-social">
          <a href="#" class="social-btn"><i class="bi bi-facebook"></i></a>
          <a href="#" class="social-btn"><i class="bi bi-twitter-x"></i></a>
          <a href="#" class="social-btn"><i class="bi bi-instagram"></i></a>
          <a href="#" class="social-btn"><i class="bi bi-youtube"></i></a>
        </div>
        <div class="footer-emergency">
          <strong><i class="bi bi-lightning-charge-fill me-1"></i>24/7 Emergency Line</strong>
          <span>+251 917 111 111</span>
        </div>
      </div>
      <div class="col-6 col-md-4 col-lg-2">
        <div class="footer-heading">Quick Links</div>
        <ul class="footer-links">
          <li><a href="#about"><i class="bi bi-chevron-right"></i> About Us</a></li>
          <li><a href="#departments"><i class="bi bi-chevron-right"></i> Departments</a></li>
          <li><a href="#doctors"><i class="bi bi-chevron-right"></i> Our Doctors</a></li>
          <li><a href="#appointment"><i class="bi bi-chevron-right"></i> Appointments</a></li>
          <li><a href="#contact"><i class="bi bi-chevron-right"></i> Contact</a></li>
          <li><a href="/panacea/admin/login.php"><i class="bi bi-shield-lock"></i> Staff Login</a></li>
        </ul>
      </div>
      <div class="col-6 col-md-4 col-lg-2">
        <div class="footer-heading">Departments</div>
        <ul class="footer-links">
          <?php foreach (array_slice($departments, 0, 6) as $dept): ?>
          <li><a href="#departments"><i class="bi bi-chevron-right"></i> <?= htmlspecialchars($dept['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="col-md-4 col-lg-4">
        <div class="footer-heading">Contact Information</div>
        <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:16px">
          <i class="bi bi-geo-alt-fill mt-1" style="color:var(--blue-bright);flex-shrink:0"></i>
          <span style="color:rgba(255,255,255,.55);font-size:.85rem;line-height:1.6">Hawassa City, Sidama Region,<br>Southern Ethiopia</span>
        </div>
        <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px">
          <i class="bi bi-telephone-fill" style="color:var(--blue-bright)"></i>
          <span style="color:rgba(255,255,255,.55);font-size:.85rem">+251 935259622</span>
        </div>
        <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px">
          <i class="bi bi-envelope-fill" style="color:var(--blue-bright)"></i>
          <span style="color:rgba(255,255,255,.55);font-size:.85rem">info@panaceahospital.et</span>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
          <i class="bi bi-clock-fill" style="color:var(--blue-bright)"></i>
          <span style="color:rgba(255,255,255,.55);font-size:.85rem">Mon–Sat: 7AM–9PM | Emergency: 24/7</span>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <span>© <?= date('Y') ?> Panacea Hospital, Hawassa. All rights reserved.</span>
        <span>Serving the healthcare community of Sidama Region, Ethiopia</span>
      </div>
    </div>
  </div>
</footer>

<button id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">
  <i class="bi bi-arrow-up"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Sticky navbar
  const nav = document.getElementById('mainNav');
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 60);
    document.getElementById('backToTop').classList.toggle('show', window.scrollY > 400);
  });

  // Active nav link on scroll
  const sections = document.querySelectorAll('section[id]');
  const navLinks = document.querySelectorAll('.nav-link[href^="#"]');
  window.addEventListener('scroll', () => {
    let current = '';
    sections.forEach(s => { if (window.scrollY >= s.offsetTop - 100) current = s.getAttribute('id'); });
    navLinks.forEach(l => l.classList.toggle('active', l.getAttribute('href') === '#' + current));
  });

  // Scroll reveal
  const observer = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); } });
  }, { threshold: 0.12 });
  document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

  // ── APPOINTMENT FORM → PHP BACKEND ──────────────────────
  document.getElementById('appointmentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('apptBtn');
    btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Submitting...';
    btn.disabled = true;

    try {
      const res  = await fetch('/panacea/submit_appointment.php', { method:'POST', body: new FormData(this) });
      const data = await res.json();
      const ok   = document.getElementById('apptSuccess');
      const err  = document.getElementById('apptError');

      if (data.success) {
        document.getElementById('apptMsg').textContent = data.message;
        ok.style.display = 'block';
        err.style.display = 'none';
        this.reset();
        setTimeout(() => ok.style.display = 'none', 7000);
      } else {
        document.getElementById('apptErrMsg').textContent = data.message;
        err.style.display = 'block';
        ok.style.display  = 'none';
      }
    } catch(ex) {
      document.getElementById('apptErrMsg').textContent = 'Connection error. Please try again.';
      document.getElementById('apptError').style.display = 'block';
    }

    btn.innerHTML = '<i class="bi bi-calendar-check me-2"></i>Confirm Appointment';
    btn.disabled  = false;
  });

  // ── CONTACT FORM → PHP BACKEND ───────────────────────────
  document.getElementById('contactForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('contactBtn');
    btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Sending...';
    btn.disabled = true;

    try {
      const res  = await fetch('/panacea/submit_contact.php', { method:'POST', body: new FormData(this) });
      const data = await res.json();
      const ok   = document.getElementById('contactSuccess');
      const err  = document.getElementById('contactError');

      if (data.success) {
        document.getElementById('contactMsg').textContent = data.message;
        ok.style.display = 'block';
        err.style.display = 'none';
        this.reset();
        setTimeout(() => ok.style.display = 'none', 7000);
      } else {
        document.getElementById('contactErrMsg').textContent = data.message;
        err.style.display = 'block';
        ok.style.display  = 'none';
      }
    } catch(ex) {
      document.getElementById('contactErrMsg').textContent = 'Connection error. Please try again.';
      document.getElementById('contactError').style.display = 'block';
    }

    btn.innerHTML = '<i class="bi bi-send-fill me-2"></i>Send Message';
    btn.disabled  = false;
  });
</script>
</body>
</html>

