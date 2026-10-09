# IronCode audit – nasadenie na Hostinger
1. V hPanel → Databases → Management vytvorte databázu MariaDB a používateľa s prístupom len do tejto databázy.
2. Otvorte phpMyAdmin danej databázy a importujte audit_requests.sql.
3. V správcovi súborov vytvorte súbor ironcode-private.php o úroveň vyššie než public_html (súkromný súbor, nikdy do GitHubu). Obsah:
<?php
return [
  'db_host' => 'localhost',
  'db_name' => 'VAS_NAZOV_DB',
  'db_user' => 'VAS_DB_UZIVATEL',
  'db_pass' => 'VAS_SILNE_HESLO'
];
4. Skontrolujte, či sú v public_html súbory objednat-audit.html a odoslat-audit.php.
5. Overte v Hostinger Mail, že schránka info@ironcode.site existuje alebo je nastavené presmerovanie na skutočnú schránku.
6. Otestujte odoslanie: záznam v databáze + e-mail. Hostinger PHP mail() nie je spoľahlivá produkčná doručovacia cesta; ak e-mail nevychádza, konfigurujte SMTP (napr. PHPMailer + SMTP poverenia uložené mimo public_html).
7. Nastavte serverové obmedzenie odosielania (rate-limit / WAF), pravidelné čistenie formulárov a zálohy DB.
8. Formulár vyhláste za funkčný až po end-to-end overení.

Poznámka: Nezverejňujte databázové heslo ani SMTP heslo v chate alebo v GitHub repozitári.
