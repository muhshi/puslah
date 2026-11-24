<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description"
        content="DINAMIT — sistem manajemen kepegawaian, surat tugas, bukti dukung, survei, dan sertifikat BPS Kabupaten Demak.">
    <meta name="theme-color" content="#0A2540">
    <title>{{ config('app.name', 'DINAMIT') }} — Sistem Manajemen Kepegawaian BPS Kabupaten Demak</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --font-display: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif;
            --font-sans: 'Inter', system-ui, -apple-system, sans-serif;

            --bg: #f7f9fc;
            --bg-soft: #f1f5fa;
            --surface: #ffffff;
            --nav-bg: rgba(255, 255, 255, 0.94);

            --border: #dce4ed;
            --border-strong: #bccbdb;

            --text: #10243a;
            --text-2: #47596e;
            --text-3: #5f6f85;

            --primary: #005596;
            --primary-dark: #004273;
            --primary-soft: #eaf2f9;

            --accent: #f2911b;
            --accent-text: #a85a06;
            --success: #147d4a;
            --success-soft: #e8f5ee;
            --amber-soft: #fff4e2;

            --navy: #0a2540;
            --navy-2: #123450;
            --on-navy: #e8f0f8;

            --shadow-sm: 0 1px 2px rgba(16, 36, 58, 0.06);
            --shadow-md: 0 12px 28px -14px rgba(16, 36, 58, 0.28);
            --shadow-lg: 0 28px 54px -24px rgba(16, 36, 58, 0.32);

            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;

            color-scheme: light;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 100px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-sans);
            font-size: 15px;
            line-height: 1.6;
        }

        h1,
        h2,
        h3,
        p {
            margin-top: 0;
        }

        h1,
        h2,
        h3 {
            font-family: var(--font-display);
            text-wrap: balance;
        }

        p {
            text-wrap: pretty;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            font: inherit;
        }

        svg {
            display: block;
            flex: none;
        }

        .container {
            width: min(100% - 48px, 1160px);
            margin-inline: auto;
        }

        .skip-link {
            position: absolute;
            top: -80px;
            left: 12px;
            z-index: 100;
            padding: 10px 16px;
            background: var(--surface);
            border: 2px solid var(--primary);
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
        }

        .skip-link:focus {
            top: 12px;
        }

        :focus-visible {
            outline: 3px solid rgba(0, 85, 150, 0.38);
            outline-offset: 3px;
            border-radius: 5px;
        }

        /* ---------- Buttons ---------- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 44px;
            padding: 0 18px;
            border: 1px solid transparent;
            border-radius: 10px;
            font-family: var(--font-sans);
            font-size: 14px;
            font-weight: 600;
            line-height: 1;
            cursor: pointer;
            transition-property: background-color, border-color, color, box-shadow, transform;
            transition-duration: 160ms;
            transition-timing-function: ease-out;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 6px 16px -8px rgba(0, 85, 150, 0.7);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--surface);
            color: var(--text);
            border-color: var(--border-strong);
        }

        .btn-secondary:hover {
            background: var(--bg-soft);
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-ghost {
            background: transparent;
            color: var(--text-2);
        }

        .btn-ghost:hover {
            color: var(--primary);
            background: var(--primary-soft);
        }

        .btn-lg {
            min-height: 50px;
            padding: 0 22px;
            font-size: 14.5px;
            border-radius: 11px;
        }

        .btn-cta {
            background: #f5a63d;
            color: #1b2a3a;
        }

        .btn-cta:hover {
            background: var(--accent);
        }

        .btn-outline-light {
            background: transparent;
            border-color: rgba(232, 240, 248, 0.36);
            color: #fff;
        }

        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(232, 240, 248, 0.7);
        }

        /* ---------- Top bar & header ---------- */
        .utility-bar {
            background: var(--navy);
            color: #cbd9e7;
            font-size: 11px;
            letter-spacing: 0.04em;
        }

        .utility-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 34px;
        }

        .utility-inner strong {
            color: #fff;
            font-weight: 600;
        }

        .utility-meta {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .utility-meta::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--accent);
        }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: var(--nav-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
        }

        .nav-inner {
            display: flex;
            align-items: center;
            gap: 32px;
            min-height: 68px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 11px;
            flex: none;
        }

        .brand img {
            width: 38px;
            height: 38px;
            object-fit: contain;
            outline: 1px solid rgba(16, 36, 58, 0.08);
            outline-offset: -1px;
            border-radius: 6px;
        }

        .brand-name {
            display: block;
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.1;
            color: var(--navy);
        }

        .brand-sub {
            display: block;
            margin-top: 2px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--accent-text);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 26px;
            margin-left: auto;
        }

        .nav-links a {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-2);
            transition-property: color;
            transition-duration: 150ms;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex: none;
        }

        .menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            margin-left: auto;
            padding: 0;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            cursor: pointer;
        }

        .mobile-nav {
            display: none;
            border-top: 1px solid var(--border);
            padding: 10px 24px 18px;
            background: var(--surface);
        }

        .mobile-nav[hidden] {
            display: none;
        }

        .mobile-nav a {
            display: block;
            padding: 12px 0;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-2);
            border-bottom: 1px solid var(--border);
        }

        /* ---------- Hero ---------- */
        .hero {
            position: relative;
            overflow: hidden;
            padding: 84px 0 86px;
            background: linear-gradient(180deg, #fff 0%, var(--bg) 82%);
            border-bottom: 1px solid var(--border);
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.04fr 0.96fr;
            gap: 64px;
            align-items: center;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 7px 13px;
            background: var(--primary-soft);
            border: 1px solid #d3e3f1;
            border-radius: 999px;
            color: var(--primary-dark);
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.075em;
            text-transform: uppercase;
        }

        .eyebrow-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--accent);
        }

        .hero h1 {
            margin: 22px 0 18px;
            font-size: clamp(2.25rem, 4.1vw, 3.45rem);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.038em;
            color: var(--navy);
        }

        .hero h1 .mark {
            color: var(--primary);
            background: linear-gradient(transparent 73%, rgba(242, 145, 27, 0.26) 73%);
        }

        .hero-lead {
            max-width: 556px;
            margin-bottom: 28px;
            font-size: 16px;
            line-height: 1.75;
            color: var(--text-2);
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .hero-note {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            margin: 24px 0 0;
            color: var(--text-3);
            font-size: 12.5px;
            line-height: 1.6;
        }

        .hero-note svg {
            margin-top: 2px;
            color: var(--primary);
        }

        /* ---------- Product mockup ---------- */
        .mock-wrap {
            position: relative;
        }

        .mock-wrap::before {
            content: "";
            position: absolute;
            inset: 12px -12px -12px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            background: rgba(255, 255, 255, 0.5);
        }

        .mock {
            position: relative;
            overflow: hidden;
            background: var(--surface);
            border: 1px solid #d3dde9;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
        }

        .mock-top {
            display: flex;
            align-items: center;
            gap: 12px;
            height: 44px;
            padding: 0 16px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
        }

        .mock-dots {
            display: flex;
            gap: 5px;
        }

        .mock-dots i {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #cbd5e1;
        }

        .mock-path {
            font-size: 11px;
            font-weight: 500;
            color: var(--text-3);
        }

        .mock-sync {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: auto;
            font-size: 10.5px;
            font-weight: 600;
            color: var(--success);
        }

        .mock-sync i {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--success);
        }

        .mock-tabs {
            display: flex;
            gap: 24px;
            padding: 0 20px;
            border-bottom: 1px solid var(--border);
        }

        .mock-tab {
            appearance: none;
            padding: 14px 0 12px;
            margin-bottom: -1px;
            background: transparent;
            border: 0;
            border-bottom: 2px solid transparent;
            color: var(--text-3);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition-property: color, border-color;
            transition-duration: 150ms;
        }

        .mock-tab:hover {
            color: var(--primary);
        }

        .mock-tab[aria-selected="true"] {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .mock-panel {
            min-height: 268px;
            padding: 18px 20px 20px;
        }

        .mock-panel[hidden] {
            display: none;
        }

        .mock-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 6px;
        }

        .mock-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
        }

        .mock-sub {
            font-size: 10.5px;
            color: var(--text-3);
        }

        .pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .pill-ok {
            background: var(--success-soft);
            color: var(--success);
        }

        .pill-wait {
            background: var(--amber-soft);
            color: var(--accent-text);
        }

        .pill-info {
            background: var(--primary-soft);
            color: var(--primary-dark);
        }

        .mock-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 13px 0;
            border-bottom: 1px solid #edf1f6;
        }

        .mock-row:last-child {
            border-bottom: 0;
        }

        .row-code {
            display: block;
            font-size: 11px;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            color: var(--primary);
        }

        .row-name {
            display: block;
            margin-top: 2px;
            font-size: 12px;
            color: var(--text-2);
        }

        .row-time {
            width: 44px;
            flex: none;
            font-size: 11px;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            color: var(--text-3);
        }

        .row-body {
            flex: 1;
            min-width: 0;
        }

        .row-meta {
            display: block;
            margin-top: 2px;
            font-size: 10.5px;
            color: var(--text-3);
        }

        .verify-line {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 14px;
            padding: 14px;
            background: var(--success-soft);
            border: 1px solid #cfe8d9;
            border-radius: var(--radius-md);
        }

        .verify-icon {
            display: grid;
            place-items: center;
            width: 34px;
            height: 34px;
            flex: none;
            border-radius: 10px;
            background: #fff;
            color: var(--success);
        }

        .verify-line strong {
            display: block;
            font-size: 12px;
            color: #0e5c37;
        }

        .verify-line span {
            display: block;
            margin-top: 2px;
            font-size: 10.5px;
            color: #3f7359;
        }

        /* ---------- Stats ---------- */
        .stats {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            padding: 32px 0;
        }

        .stat {
            padding: 0 28px;
            border-left: 1px solid var(--border);
        }

        .stat:first-child {
            padding-left: 0;
            border-left: 0;
        }

        .stat-value {
            display: block;
            font-family: var(--font-display);
            font-size: 30px;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.03em;
            font-variant-numeric: tabular-nums;
            color: var(--primary);
        }

        .stat-label {
            display: block;
            margin-top: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: var(--text-3);
        }

        /* ---------- Shared section headings ---------- */
        .section {
            padding: 92px 0;
        }

        .section-head {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 48px;
            align-items: end;
            margin-bottom: 40px;
        }

        .kicker {
            margin-bottom: 12px;
            color: var(--accent-text);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.13em;
            text-transform: uppercase;
        }

        .section h2 {
            margin-bottom: 0;
            font-size: clamp(1.75rem, 3vw, 2.45rem);
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.032em;
            color: var(--navy);
        }

        .section-intro {
            max-width: 470px;
            margin-bottom: 2px;
            font-size: 14.5px;
            line-height: 1.75;
            color: var(--text-2);
        }

        /* ---------- Services ---------- */
        .service-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .service {
            display: flex;
            flex-direction: column;
            padding: 24px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            transition-property: border-color, box-shadow, transform;
            transition-duration: 180ms;
            transition-timing-function: ease-out;
        }

        .service:hover {
            border-color: #b7cde2;
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .service-icon {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            border-radius: 11px;
            background: var(--primary-soft);
            color: var(--primary);
        }

        .service h3 {
            margin: 18px 0 8px;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: -0.015em;
            color: var(--text);
        }

        .service p {
            margin-bottom: 0;
            font-size: 13.5px;
            line-height: 1.65;
            color: var(--text-2);
        }

        .service-meta {
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid #edf1f6;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.01em;
            color: var(--text-3);
        }

        /* ---------- Workflow ---------- */
        .workflow {
            background: var(--bg-soft);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .workflow-grid {
            display: grid;
            grid-template-columns: 0.82fr 1.18fr;
            gap: 72px;
        }

        .workflow-intro .section-intro {
            margin-top: 18px;
        }

        .steps {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .step {
            display: grid;
            grid-template-columns: 44px 1fr;
            gap: 18px;
            padding: 20px 0;
            border-bottom: 1px solid var(--border-strong);
        }

        .step:first-child {
            padding-top: 0;
        }

        .step:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .step-num {
            display: grid;
            place-items: center;
            width: 40px;
            height: 40px;
            border: 1px solid #c3d3e4;
            border-radius: 50%;
            background: var(--surface);
            color: var(--primary);
            font-family: var(--font-display);
            font-size: 14px;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .step h3 {
            margin: 6px 0 6px;
            font-size: 15.5px;
            font-weight: 700;
        }

        .step p {
            margin-bottom: 0;
            font-size: 13.5px;
            line-height: 1.65;
            color: var(--text-2);
        }

        /* ---------- Access CTA ---------- */
        .access-panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
            padding: 48px 52px;
            overflow: hidden;
            background-color: var(--navy);
            background-image: linear-gradient(rgba(255, 255, 255, 0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.035) 1px, transparent 1px);
            background-size: 32px 32px;
            border: 1px solid var(--navy-2);
            border-radius: var(--radius-xl);
        }

        .access-panel .kicker {
            color: #f6b85f;
        }

        .access-panel h2 {
            margin-bottom: 10px;
            color: #fff;
        }

        .access-panel p {
            max-width: 560px;
            margin-bottom: 0;
            font-size: 14px;
            line-height: 1.7;
            color: var(--on-navy);
        }

        .access-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: none;
        }

        .access-note {
            font-size: 11px;
            color: #a9bfd4;
            text-align: center;
        }

        /* ---------- Help ---------- */
        .help-grid {
            display: grid;
            grid-template-columns: 0.82fr 1.18fr;
            gap: 72px;
        }

        .help-items {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        .help-item {
            padding-top: 18px;
            border-top: 2px solid var(--primary);
        }

        .help-item svg {
            color: var(--primary);
        }

        .help-item h3 {
            margin: 14px 0 7px;
            font-size: 14px;
            font-weight: 700;
        }

        .help-item p {
            margin-bottom: 0;
            font-size: 12.5px;
            line-height: 1.65;
            color: var(--text-2);
        }

        /* ---------- Footer ---------- */
        .site-footer {
            padding: 40px 0;
            background: var(--surface);
            border-top: 1px solid var(--border);
        }

        .footer-grid {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 32px;
        }

        .footer-address {
            margin: 12px 0 0;
            font-size: 12.5px;
            line-height: 1.65;
            color: var(--text-3);
        }

        .footer-meta {
            text-align: right;
            font-size: 12px;
            line-height: 1.7;
            color: var(--text-3);
        }

        .footer-meta strong {
            display: block;
            color: var(--text-2);
            font-weight: 600;
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 1040px) {
            .nav-links {
                gap: 18px;
            }

            .hero-grid,
            .section-head,
            .workflow-grid,
            .help-grid {
                gap: 40px;
            }
        }

        @media (max-width: 900px) {
            .hero-grid {
                grid-template-columns: 1fr;
                gap: 48px;
            }

            .hero-copy {
                max-width: 680px;
            }

            .mock-wrap {
                max-width: 640px;
            }

            .section-head,
            .workflow-grid,
            .help-grid {
                grid-template-columns: 1fr;
                align-items: start;
            }

            .section-intro {
                max-width: 640px;
            }

            .service-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .access-panel {
                flex-direction: column;
                align-items: flex-start;
                padding: 40px;
            }

            .access-actions {
                flex-direction: row;
                flex-wrap: wrap;
                align-items: center;
            }

            .access-note {
                text-align: left;
            }
        }

        @media (max-width: 720px) {
            .container {
                width: min(100% - 32px, 1160px);
            }

            .utility-meta {
                display: none;
            }

            .nav-links,
            .nav-actions {
                display: none;
            }

            .menu-toggle {
                display: inline-flex;
            }

            .mobile-nav {
                display: block;
            }

            .hero {
                padding: 56px 0 64px;
            }

            .hero h1 {
                font-size: clamp(2rem, 9vw, 2.6rem);
            }

            .hero-lead {
                font-size: 15px;
            }

            .section {
                padding: 68px 0;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 24px 0;
            }

            .stat:nth-child(3) {
                padding-left: 0;
                border-left: 0;
            }

            .service-grid {
                grid-template-columns: 1fr;
            }

            .help-items {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .footer-grid {
                flex-direction: column;
            }

            .footer-meta {
                text-align: left;
            }
        }

        @media (max-width: 420px) {
            .hero-actions .btn,
            .access-actions .btn {
                width: 100%;
            }

            .mock-tabs {
                gap: 16px;
                padding: 0 14px;
            }

            .mock-panel {
                padding: 16px 14px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body>
    <a class="skip-link" href="#main">Lewati ke konten</a>

    <div class="utility-bar">
        <div class="container utility-inner">
            <span><strong>Badan Pusat Statistik Kabupaten Demak</strong></span>
            <span class="utility-meta">Sistem internal · Akses terbatas</span>
        </div>
    </div>

    <header class="site-header">
        <div class="container nav-inner">
            <a class="brand" href="#beranda" aria-label="DINAMIT, kembali ke beranda">
                <img src="{{ asset('images/logo_bps.png') }}" alt="Logo Badan Pusat Statistik">
                <span>
                    <span class="brand-name">DINAMIT</span>
                    <span class="brand-sub">BPS Kabupaten Demak</span>
                </span>
            </a>

            <nav class="nav-links" aria-label="Navigasi utama">
                <a href="#beranda">Beranda</a>
                <a href="#layanan">Layanan</a>
                <a href="#alur">Alur Kerja</a>
                <a href="#bantuan">Bantuan</a>
            </nav>

            <div class="nav-actions">
                @auth
                    <a class="btn btn-ghost" href="{{ route('filament.admin.pages.dashboard') }}">Dashboard</a>
                    <a class="btn btn-primary" href="{{ route('filament.admin.pages.dashboard') }}">Buka Panel</a>
                @else
                    <a class="btn btn-ghost" href="{{ route('filament.admin.auth.login') }}">Masuk</a>
                    <a class="btn btn-primary" href="{{ route('filament.admin.auth.login') }}">Akses Sistem</a>
                @endauth
            </div>

            <button class="menu-toggle" type="button" aria-label="Buka menu" aria-expanded="false"
                aria-controls="mobile-nav">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16" />
                </svg>
            </button>
        </div>

        <nav class="mobile-nav" id="mobile-nav" aria-label="Navigasi seluler" hidden>
            <a href="#beranda">Beranda</a>
            <a href="#layanan">Layanan</a>
            <a href="#alur">Alur Kerja</a>
            @auth
                <a href="{{ route('filament.admin.pages.dashboard') }}">Buka Panel</a>
            @else
                <a href="{{ route('filament.admin.auth.login') }}">Akses Sistem</a>
            @endauth
            <a href="#bantuan">Bantuan</a>
        </nav>
    </header>

    <main id="main">
        <!-- Hero -->
        <section class="hero" id="beranda" aria-labelledby="hero-title">
            <div class="container hero-grid">
                <div class="hero-copy">
                    <div class="eyebrow">
                        <span class="eyebrow-dot" aria-hidden="true"></span>
                        Data dan Informasi Mitra Terintegrasi
                    </div>

                    <h1 id="hero-title">Administrasi BPS Demak, <span class="mark">dalam satu sistem.</span></h1>

                    <p class="hero-lead">
                        Susun surat tugas, catat bukti dukung harian, kelola survei, dan verifikasi sertifikat tanpa
                        berpindah aplikasi. Data tersimpan rapi dan dapat ditelusuri kembali saat dibutuhkan.
                    </p>

                    <div class="hero-actions">
                        @auth
                            <a class="btn btn-primary btn-lg" href="{{ route('filament.admin.pages.dashboard') }}">
                                Buka Panel
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M5 12h14m-6-6 6 6-6 6" />
                                </svg>
                            </a>
                        @else
                            <a class="btn btn-primary btn-lg" href="{{ route('filament.admin.auth.login') }}">
                                Akses Sistem
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M5 12h14m-6-6 6 6-6 6" />
                                </svg>
                            </a>
                        @endauth
                        <a class="btn btn-secondary btn-lg" href="#layanan">Lihat layanan</a>
                    </div>

                    <p class="hero-note">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="4" y="10" width="16" height="11" rx="2" />
                            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                        </svg>
                        <span>Akses dengan akun SIPETRA. Diperuntukkan pegawai dan mitra BPS Kabupaten Demak.</span>
                    </p>
                </div>

                <div class="mock-wrap" aria-label="Cuplikan tampilan DINAMIT">
                    <div class="mock">
                        <div class="mock-top">
                            <span class="mock-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                            <span class="mock-path">dinamit / ringkasan</span>
                            <span class="mock-sync"><i aria-hidden="true"></i>Sinkron</span>
                        </div>

                        <div class="mock-tabs" role="tablist" aria-label="Cuplikan layanan">
                            <button class="mock-tab" type="button" role="tab" id="tab-st"
                                aria-controls="panel-st" aria-selected="true" tabindex="0">Surat Tugas</button>
                            <button class="mock-tab" type="button" role="tab" id="tab-dukung"
                                aria-controls="panel-dukung" aria-selected="false" tabindex="-1">Bukti Dukung</button>
                            <button class="mock-tab" type="button" role="tab" id="tab-sertifikat"
                                aria-controls="panel-sertifikat" aria-selected="false" tabindex="-1">Sertifikat</button>
                        </div>

                        <div class="mock-panel" id="panel-st" role="tabpanel" aria-labelledby="tab-st" tabindex="0">
                            <div class="mock-head">
                                <span class="mock-title">Surat tugas aktif</span>
                                <span class="mock-sub">November 2026</span>
                            </div>
                            <div class="mock-row">
                                <div>
                                    <span class="row-code">4451/ST/2026</span>
                                    <span class="row-name">Survei Sosial Ekonomi</span>
                                </div>
                                <span class="pill pill-ok">Disetujui</span>
                            </div>
                            <div class="mock-row">
                                <div>
                                    <span class="row-code">4452/ST/2026</span>
                                    <span class="row-name">Pendataan UMK Kabupaten</span>
                                </div>
                                <span class="pill pill-wait">Menunggu</span>
                            </div>
                            <div class="mock-row">
                                <div>
                                    <span class="row-code">4453/ST/2026</span>
                                    <span class="row-name">Pemantauan Harga Bahan Pokok</span>
                                </div>
                                <span class="pill pill-ok">Disetujui</span>
                            </div>
                            <div class="mock-row">
                                <div>
                                    <span class="row-code">4460/ST/2026</span>
                                    <span class="row-name">Kegiatan Rutin Wilkerstat</span>
                                </div>
                                <span class="pill pill-info">Draft</span>
                            </div>
                        </div>

                        <div class="mock-panel" id="panel-dukung" role="tabpanel" aria-labelledby="tab-dukung"
                            tabindex="0" hidden>
                            <div class="mock-head">
                                <span class="mock-title">Bukti dukung harian</span>
                                <span class="mock-sub">Senin, 24 November 2026</span>
                            </div>
                            <div class="mock-row">
                                <span class="row-time">07:30</span>
                                <div class="row-body">
                                    <span class="row-name">Koordinasi awal di kantor wilayah</span>
                                    <span class="row-meta">Lampiran 2 foto</span>
                                </div>
                                <span class="pill pill-ok">Tersimpan</span>
                            </div>
                            <div class="mock-row">
                                <span class="row-time">10:15</span>
                                <div class="row-body">
                                    <span class="row-name">Wawancara responding unit usaha</span>
                                    <span class="row-meta">Lampiran 4 foto · dokumen PDF</span>
                                </div>
                                <span class="pill pill-ok">Tersimpan</span>
                            </div>
                            <div class="mock-row">
                                <span class="row-time">13:00</span>
                                <div class="row-body">
                                    <span class="row-name">Input data dan penyusunan narasi</span>
                                    <span class="row-meta">Belum ada lampiran</span>
                                </div>
                                <span class="pill pill-wait">Draft</span>
                            </div>
                            <div class="verify-line">
                                <span class="verify-icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m5 12 4 4L19 6" />
                                    </svg>
                                </span>
                                <span>
                                    <strong>Siap masuk Wadah KIPAPP</strong>
                                    <span>3 kegiatan hari ini · tautan publik siap dibagikan</span>
                                </span>
                            </div>
                        </div>

                        <div class="mock-panel" id="panel-sertifikat" role="tabpanel"
                            aria-labelledby="tab-sertifikat" tabindex="0" hidden>
                            <div class="mock-head">
                                <span class="mock-title">Sertifikat terbaru</span>
                                <span class="mock-sub">Diterbitkan bulan ini</span>
                            </div>
                            <div class="mock-row">
                                <div>
                                    <span class="row-code">SRT/2026/0118</span>
                                    <span class="row-name">Pelatihan Pengolahan Data Survei</span>
                                </div>
                                <span class="pill pill-ok">Terverifikasi</span>
                            </div>
                            <div class="mock-row">
                                <div>
                                    <span class="row-code">SRT/2026/0117</span>
                                    <span class="row-name">Pendampingan Pendataan Wilayah</span>
                                </div>
                                <span class="pill pill-ok">Terverifikasi</span>
                            </div>
                            <div class="mock-row">
                                <div>
                                    <span class="row-code">SRT/2026/0116</span>
                                    <span class="row-name">Bimbingan Teknis E-Kinerja</span>
                                </div>
                                <span class="pill pill-wait">Menunggu approval</span>
                            </div>
                            <div class="verify-line">
                                <span class="verify-icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m5 12 4 4L19 6" />
                                    </svg>
                                </span>
                                <span>
                                    <strong>Keaslian dapat dicek publik</strong>
                                    <span>Cukup nomor sertifikat, tanpa perlu login</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="stats" aria-label="Ringkasan penggunaan sistem">
            <div class="container stats-grid">
                <div class="stat">
                    <span class="stat-value">{{ number_format(\App\Models\User::count(), 0, ',', '.') }}</span>
                    <span class="stat-label">Pegawai &amp; Mitra</span>
                </div>
                <div class="stat">
                    <span class="stat-value">{{ number_format(\App\Models\SuratTugas::count(), 0, ',', '.') }}</span>
                    <span class="stat-label">Surat Tugas</span>
                </div>
                <div class="stat">
                    <span class="stat-value">{{ number_format(\App\Models\LaporanPerjalananDinas::count(), 0, ',', '.') }}</span>
                    <span class="stat-label">Laporan Dinas</span>
                </div>
                <div class="stat">
                    <span class="stat-value">{{ number_format(\App\Models\Survey::count(), 0, ',', '.') }}</span>
                    <span class="stat-label">Total Survei/Kegiatan</span>
                </div>
            </div>
        </section>

        <!-- Services -->
        <section class="section" id="layanan" aria-labelledby="layanan-title">
            <div class="container">
                <div class="section-head">
                    <div>
                        <p class="kicker">Layanan</p>
                        <h2 id="layanan-title">Satu tempat untuk proses administrasi harian</h2>
                    </div>
                    <p class="section-intro">
                        Setiap layanan saling terhubung: surat tugas menautkan kegiatan, kegiatan menjadi bukti dukung,
                        dan hasil survei berujung pada sertifikat.
                    </p>
                </div>

                <div class="service-grid">
                    <article class="service">
                        <span class="service-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M7 3h7l5 5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                                <path d="M14 3v5h5M9 13h7M9 17h5" />
                            </svg>
                        </span>
                        <h3>Surat Tugas &amp; SPPD</h3>
                        <p>Penomoran otomatis, approval berjenjang, dan PDF bertanda QR untuk keperluan verifikasi.</p>
                        <div class="service-meta">Penomoran otomatis · Tanda tangan digital</div>
                    </article>

                    <article class="service">
                        <span class="service-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14.5 4.5 19.5 9.5M4 20l4.5-1 11-11a2.1 2.1 0 0 0-3-3l-11 11L4 20Z" />
                                <path d="M4 20h4" />
                            </svg>
                        </span>
                        <h3>Bukti Dukung Harian</h3>
                        <p>Catat kegiatan lewat web atau Telegram, lampirkan foto, lalu susun menjadi satu berkas
                            KIPAPP.</p>
                        <div class="service-meta">Input via bot · Wadah KIPAPP</div>
                    </article>

                    <article class="service">
                        <span class="service-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="5" y="3" width="14" height="18" rx="2" />
                                <path d="M9 8h6M9 12h6M9 16h4" />
                            </svg>
                        </span>
                        <h3>Laporan Perjalanan Dinas</h3>
                        <p>Rangkum kegiatan yang sudah tercatat, lengkapi foto, dan ekspor dokumen Word dengan satu
                            klik.</p>
                        <div class="service-meta">Ekspor Word · Arsip foto</div>
                    </article>

                    <article class="service">
                        <span class="service-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 5H5a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1h-4" />
                                <path d="M9 3h6v4H9zM8 12h8M8 16h5" />
                            </svg>
                        </span>
                        <h3>Survei &amp; Penugasan</h3>
                        <p>Buat survei, tetapkan petugas, dan pantau status pengisian hingga batas waktu berakhir.</p>
                        <div class="service-meta">Assignment petugas · Monitoring</div>
                    </article>

                    <article class="service">
                        <span class="service-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="9" r="5.5" />
                                <path d="M8 14 5 21l7-3 7 3-3-7" />
                            </svg>
                        </span>
                        <h3>Sertifikat</h3>
                        <p>Terbitkan sertifikat dari hasil survei dan cek keasliannya melalui nomor verifikasi publik.</p>
                        <div class="service-meta">Template cetak · Halaman verifikasi</div>
                    </article>

                    <article class="service">
                        <span class="service-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9" />
                                <path d="M12 7v5l3.5 2" />
                            </svg>
                        </span>
                        <h3>Presensi &amp; Kinerja</h3>
                        <p>Rekam kehadiran dan lihat ringkasan kinerja pegawai dalam satu panel yang sama.</p>
                        <div class="service-meta">Presensi · Rekap kehadiran</div>
                    </article>
                </div>
            </div>
        </section>

        <!-- Workflow -->
        <section class="section workflow" id="alur" aria-labelledby="alur-title">
            <div class="container workflow-grid">
                <div class="workflow-intro">
                    <p class="kicker">Alur Penggunaan</p>
                    <h2 id="alur-title">Mulai dari masuk, berakhir pada dokumen yang siap dipakai</h2>
                    <p class="section-intro">
                        Alur ini mengikuti kebiasaan kerja di BPS: identitas terhubung, kegiatan tercatat, dokumen bisa
                        langsung diverifikasi pihak luar.
                    </p>
                </div>

                <ol class="steps">
                    <li class="step">
                        <span class="step-num" aria-hidden="true">1</span>
                        <div>
                            <h3>Masuk dengan akun SIPETRA</h3>
                            <p>Akun pegawai dan mitra tersinkron otomatis, sehingga tidak perlu kata sandi baru.</p>
                        </div>
                    </li>
                    <li class="step">
                        <span class="step-num" aria-hidden="true">2</span>
                        <div>
                            <h3>Pilih layanan dan isi data</h3>
                            <p>Surat tugas, kegiatan harian, atau survei tersedia dari panel yang sama tanpa perpindahan
                                aplikasi.</p>
                        </div>
                    </li>
                    <li class="step">
                        <span class="step-num" aria-hidden="true">3</span>
                        <div>
                            <h3>Unduh, bagikan, dan verifikasi</h3>
                            <p>Dokumen yang sudah disetujui memiliki QR dan nomor yang dapat dicek langsung oleh
                                pihak berwenang.</p>
                        </div>
                    </li>
                </ol>
            </div>
        </section>

        <!-- Access CTA -->
        <section class="section access" id="akses" aria-labelledby="akses-title">
            <div class="container">
                <div class="access-panel">
                    <div>
                        <p class="kicker">Akses Sistem</p>
                        <h2 id="akses-title">Siap bekerja dengan data yang rapi?</h2>
                        <p>
                            Masuk menggunakan akun BPS yang sudah terdaftar. Jika peran atau akses Anda belum aktif,
                            hubungi administrator sistem di kantor.
                        </p>
                    </div>
                    <div class="access-actions">
                        @auth
                            <a class="btn btn-cta btn-lg" href="{{ route('filament.admin.pages.dashboard') }}">Buka Dashboard</a>
                        @else
                            <a class="btn btn-cta btn-lg" href="{{ route('filament.admin.auth.login') }}">Masuk ke DINAMIT</a>
                        @endauth
                        <a class="btn btn-outline-light btn-lg" href="#bantuan">Lihat bantuan</a>
                        <span class="access-note">Login tersedia di halaman administrasi.</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Help -->
        <section class="section help" id="bantuan" aria-labelledby="bantuan-title">
            <div class="container help-grid">
                <div>
                    <p class="kicker">Bantuan</p>
                    <h2 id="bantuan-title">Informasi singkat sebelum mulai</h2>
                    <p class="section-intro">
                        Beberapa hal yang paling sering ditanyakan pegawai dan mitra saat pertama kali menggunakan
                        sistem ini.
                    </p>
                </div>

                <div class="help-items">
                    <div class="help-item">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="4" y="10" width="16" height="11" rx="2" />
                            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                        </svg>
                        <h3>Akun &amp; akses</h3>
                        <p>Akses hanya untuk pegawai dan mitra yang terdaftar di SIPETRA. Hubungi admin jika akun belum
                            aktif.</p>
                    </div>
                    <div class="help-item">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 3 4.5 6v5.5c0 4.8 3.1 8.3 7.5 9.5 4.4-1.2 7.5-4.7 7.5-9.5V6L12 3Z" />
                            <path d="m9 12 2 2 4-4" />
                        </svg>
                        <h3>Verifikasi dokumen</h3>
                        <p>Nomor surat tugas dan sertifikat dapat diverifikasi lewat halaman publik tanpa perlu login.</p>
                    </div>
                    <div class="help-item">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" />
                            <circle cx="12" cy="10" r="2.5" />
                        </svg>
                        <h3>Kantor</h3>
                        <p>BPS Kabupaten Demak, Jalan Sultan Fatah No. 10, Demak.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-grid">
            <div>
                <a class="brand" href="#beranda" aria-label="DINAMIT, kembali ke beranda">
                    <img src="{{ asset('images/logo_bps.png') }}" alt="Logo Badan Pusat Statistik">
                    <span>
                        <span class="brand-name">DINAMIT</span>
                        <span class="brand-sub">BPS Kabupaten Demak</span>
                    </span>
                </a>
                <p class="footer-address">
                    Sistem manajemen kepegawaian dan survei<br>
                    Badan Pusat Statistik Kabupaten Demak
                </p>
            </div>
            <div class="footer-meta">
                <strong>&copy; {{ date('Y') }} DINAMIT BPS Demak</strong>
                Jalan Sultan Fatah No. 10, Demak
            </div>
        </div>
    </footer>

    <script>
        (function () {
            // Tab cuplikan produk
            var tabs = Array.prototype.slice.call(document.querySelectorAll('[role="tab"]'));

            function selectTab(tab) {
                tabs.forEach(function (item) {
                    var selected = item === tab;
                    item.setAttribute('aria-selected', String(selected));
                    item.tabIndex = selected ? 0 : -1;
                    document.getElementById(item.getAttribute('aria-controls')).hidden = !selected;
                });
            }

            tabs.forEach(function (tab, index) {
                tab.addEventListener('click', function () {
                    selectTab(tab);
                });

                tab.addEventListener('keydown', function (event) {
                    if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
                    event.preventDefault();
                    var direction = event.key === 'ArrowRight' ? 1 : -1;
                    var next = tabs[(index + direction + tabs.length) % tabs.length];
                    selectTab(next);
                    next.focus();
                });
            });

            // Menu seluler
            var toggle = document.querySelector('.menu-toggle');
            var mobileNav = document.getElementById('mobile-nav');

            toggle.addEventListener('click', function () {
                var open = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', String(!open));
                toggle.setAttribute('aria-label', open ? 'Buka menu' : 'Tutup menu');
                mobileNav.hidden = open;
            });

            mobileNav.addEventListener('click', function (event) {
                if (event.target.closest('a')) {
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.setAttribute('aria-label', 'Buka menu');
                    mobileNav.hidden = true;
                }
            });

        })();
    </script>
</body>

</html>
