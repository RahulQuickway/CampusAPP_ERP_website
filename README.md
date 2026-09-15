# Campus APP ERP — Marketing Website

Static multi-page website for **Campus APP ERP** (`campusapperp.com`).  
Pure HTML + CSS + vanilla JS — no build step.

Professional product-site presentation for Indian school and college decision-makers: outcome-led landing, full module suite, security section, AI product vision, and demo screens with sample data.

## Local preview

Directory-based clean URLs work with Python’s server (each section is a folder with `index.html`):

```bash
cd campusapperp-website
python3 -m http.server 8080
```

Open http://localhost:8080/ then e.g. `/features`, `/contact`, `/modules`.

## Structure

```
campusapperp-website/
├── index.html                 # Home
├── features/ · modules/ · about/ · contact/ · login/
├── pricing/ · demo-dashboard/ # Unlisted from menus (direct URL OK)
├── students/ · fees/ · attendance/ · notices/
├── css/styles.css
├── js/main.js
├── assets/                    # Logo1.svg, favicon.ico/png, apple-touch-icon.png
├── api/
│   ├── contact.php            # Contact form endpoint (PHP + PHPMailer SMTP)
│   ├── config.smtp.example.php  # Copy → config.smtp.php on server (secrets)
│   ├── .htaccess              # Blocks direct access to config.smtp.php
│   └── lib/PHPMailer/         # Composer-free PHPMailer
├── .htaccess                  # Apache clean URLs + legacy redirects
├── robots.txt
├── sitemap.xml
└── README.md
```

## Clean URLs

- **Local / any static host:** folder `index.html` layout → `/features` works as `/features/` or `/features/index.html`.
- **Apache (campusapperp.com):** `.htaccess` sets `DirectoryIndex index.html`, rewrites extensionless paths, and **301-redirects** old `*.html` and `pages/*.html` URLs to the new paths.

Asset and nav links are **root-relative** (`/css/styles.css`, `/features`, `/contact`) so nested folders resolve correctly.

## Navigation (founder preference)

Main / mobile / footer menus list: **Home · Features · Modules · About · Contact** (+ Login & **Request a Demo** CTAs → `/contact`).  
**Pricing** and **Demo** are removed from menus for now but pages remain at `/pricing` and `/demo-dashboard`.

## Contact form (browser → PHP → SMTP)

Static HTML **cannot** call Gmail/SendGrid directly from the browser (SMTP passwords would leak).  
On Apache/PHP hosting the flow is:

1. Visitor submits `/contact` → JavaScript `fetch` POSTs JSON to **`/api/contact.php`**
2. `contact.php` loads secrets from **`/api/config.smtp.php`** (never from JS)
3. PHPMailer sends **two** emails over SMTP:
   - **Admin** → `ADMIN_TO` (default **`kk@quickwayinfosystems.com`**) — HTML **table** with Name, Email, Phone, School/College, Role, Message, Submitted At, Page URL, IP
   - **Auto-reply** → submitter — thank you; team replies within **24 business hours** (brand colours `#003087` / `#1b9aef`)

### Server setup (required)

1. Upload the site (including `api/`) to the document root.
2. On the server only:
   ```bash
   cp api/config.smtp.example.php api/config.smtp.php
   ```
   Edit `config.smtp.php` and replace `CHANGE_ME` values. **Do not commit** real passwords.
3. Ensure **PHP openssl** is enabled (needed for STARTTLS/SSL SMTP).
4. Confirm PHP can write `api/rate_limit.json` (light IP rate limit) or that the `api/` folder is writable.

### SendGrid steps

1. Create a SendGrid account → **Settings → API Keys** → create key with Mail Send.
2. In `config.smtp.php`:
   - `SMTP_PROVIDER` = `sendgrid`
   - `SMTP_HOST` = `smtp.sendgrid.net`
   - `SMTP_PORT` = `587`
   - `SMTP_SECURE` = `tls`
   - `SMTP_USER` = `apikey` *(literal word)*
   - `SMTP_PASS` = `SG.xxxxx…` *(your API key)*
   - `MAIL_FROM` = a verified sender (e.g. `noreply@campusapperp.com`)
   - `ADMIN_TO` = `kk@quickwayinfosystems.com` *(change anytime)*

### Gmail App Password steps

1. Google Account → turn on **2-Step Verification**.
2. **Security → App passwords** → create one for “Mail”.
3. In `config.smtp.php`:
   - `SMTP_HOST` = `smtp.gmail.com`
   - `SMTP_PORT` = `587`
   - `SMTP_SECURE` = `tls`
   - `SMTP_USER` = your Gmail address
   - `SMTP_PASS` = the 16-character app password (**not** your normal Gmail password)
   - `MAIL_FROM` = same Gmail (or an alias Gmail allows)
   - `ADMIN_TO` = `kk@quickwayinfosystems.com`

### Security notes

- **Never** put SMTP password / API key in frontend JS or HTML.
- Ship only `config.smtp.example.php` in git/zips meant for sharing; keep filled `config.smtp.php` on the server.
- `contact.php` sanitizes fields for HTML email, uses a honeypot field, Origin/Referer check, message length limits, and a light IP rate limit.

## Footer social icons

LinkedIn, Facebook, and YouTube icons use `href="#"` with “Coming soon” titles.  
**TODO (Rahul):** paste real profile URLs into the `social-link` anchors in each page footer (or a shared partial when you add a build step).

## Brand

- Primary navy: `#003087`
- Accent sky: `#1b9aef`
- Ink / logo dark: `#1f2937`
- Gradient: `linear-gradient(135deg, #003087 0%, #1b9aef 100%)`
- Font: Inter (Google Fonts)

## Differentiators

- **Lower prices** — affordable quotes for Indian schools & colleges  
- **Better support** — hands-on onboarding and responsive help  
- **AI-powered** — practical insights, reminders, and campus assistant vision  

## Founder / early-stage constraints

- No fake ₹ price tiers or published rate cards  
- No invented traction stats  
- No fake testimonials  
- Demo UI uses **fictional sample data** with clear “Demo preview” banners  
- Contact form sends real mail only after `config.smtp.php` is configured on the server  

## SEO

- Unique title + meta description per page  
- Canonical + Open Graph + Twitter cards  
- `robots.txt` + `sitemap.xml`  
- JSON-LD Organization + WebSite + SoftwareApplication on home (no fake ratings)  
- `lang="en-IN"`  

## Upload to campusapperp.com (Apache)

1. Unzip `campusapperp-website.zip` into the document root so `index.html` and `.htaccess` sit at the site root.  
2. Keep folders (`css/`, `js/`, `assets/`, `features/`, …) intact.  
3. Ensure **mod_rewrite** is enabled (usual on shared hosting).  
4. `.htaccess` provides DirectoryIndex, clean URLs, and redirects from old HTML paths.  
5. Copy `api/config.smtp.example.php` → `api/config.smtp.php` and fill SMTP (SendGrid or Gmail).  
6. Replace placeholder phone and social `href="#"` URLs when live.

**Do not** overwrite production ERP application servers with this marketing site unless that is intentional.

## Licence / data

Demo tables use fictional sample data only.  
© 2026 Campus APP ERP.
