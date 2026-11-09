<?php
session_start();

// --- ZABEZPEČENÍ HESLEM ---
$spravne_heslo = "maruska123"; // Zde si můžeš heslo kdykoliv změnit

if (isset($_POST['heslo'])) {
    if ($_POST['heslo'] === $spravne_heslo) {
        $_SESSION['prihlasen'] = true;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $chybaHeslo = "Nesprávné heslo!";
    }
}

if (isset($_GET['odhlasit'])) {
    unset($_SESSION['prihlasen']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Pokud uživatel není přihlášen, zobrazíme přihlašovací obrazovku
if (!isset($_SESSION['prihlasen'])):
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přihlášení - Kadeřnictví Marie Kučerová</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #faf2f8;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(186, 85, 211, 0.15);
            border-top: 5px solid #c71585;
            width: 320px;
            text-align: center;
        }
        h2 { color: #c71585; margin-top: 0; }
        p { color: #666; font-size: 14px; margin-bottom: 20px; }
        input[type="password"] {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid #d8b4d0;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 16px;
        }
        button {
            background-color: #c71585;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            width: 100%;
        }
        button:hover { background-color: #a0106c; }
        .error { color: #db7093; font-size: 14px; margin-top: 10px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Kadeřnictví Marie</h2>
        <p>Zadejte heslo pro přístup do deníku</p>
        <form method="POST">
            <input type="password" name="heslo" placeholder="Heslo" required autofocus>
            <button type="submit">Přihlásit se</button>
            <?php if (isset($chybaHeslo)) echo '<div class="error">' . $chybaHeslo . '</div>'; ?>
        </form>
    </div>
</body>
</html>
<?php
    exit;
endif;
// --- Konec ověření hesla ---

// Připojení k SQLite databázi
$dbFile = 'kadernictvi.db';
try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Vytvoření tabulky zákazníků
    $db->exec("CREATE TABLE IF NOT EXISTS zakaznici (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        jmeno TEXT NOT NULL,
        telefon TEXT,
        termin TEXT,
        poznamka TEXT
    )");

    // Vytvoření tabulky pro nastavení notifikací
    $db->exec("CREATE TABLE IF NOT EXISTS nastaveni (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        mobil TEXT,
        whatsapp TEXT
    )");

    // Vytvoření tabulky pro sklad a dodavatele
    $db->exec("CREATE TABLE IF NOT EXISTS sklad (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nazev_polozky TEXT NOT NULL,
        mnozstvi TEXT,
        dodavatel TEXT,
        kontakt_dodavatele TEXT,
        odkaz_web TEXT
    )");

    // Vytvoření tabulky pro příjmy (tržby)
    $db->exec("CREATE TABLE IF NOT EXISTS prijmy (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        datum TEXT NOT NULL,
        popis TEXT,
        castka REAL NOT NULL
    )");

    // --- ZPRACOVÁNÍ AKCÍ (POST / GET) ---

    // 1. Mazání zákazníka
    if (isset($_GET['delete_zakaznik'])) {
        $delId = intval($_GET['delete_zakaznik']);
        $stmtDel = $db->prepare("DELETE FROM zakaznici WHERE id = ?");
        $stmtDel->execute([$delId]);
        
        $redirUrl = $_SERVER['PHP_SELF'] . '?tab=kalendar';
        if (!empty($_GET['filtr_den'])) {
            $redirUrl .= '&filtr_den=' . urlencode($_GET['filtr_den']);
        }
        header("Location: " . $redirUrl);
        exit;
    }

    // 2. Mazání položky ze skladu
    if (isset($_GET['delete_sklad'])) {
        $delId = intval($_GET['delete_sklad']);
        $stmtDel = $db->prepare("DELETE FROM sklad WHERE id = ?");
        $stmtDel->execute([$delId]);
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=sklad");
        exit;
    }

    // 3. Mazání příjmu
    if (isset($_GET['delete_prijem'])) {
        $delId = intval($_GET['delete_prijem']);
        $stmtDel = $db->prepare("DELETE FROM prijmy WHERE id = ?");
        $stmtDel->execute([$delId]);
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=prijmy");
        exit;
    }

    // 4. Přidání zákazníka
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_zakaznik') {
        $jmeno = trim($_POST['jmeno'] ?? '');
        $telefon = trim($_POST['telefon'] ?? '');
        $termin = trim($_POST['termin'] ?? '');
        $poznamka = trim($_POST['poznamka'] ?? '');

        if (!empty($jmeno)) {
            $stmt = $db->prepare("INSERT INTO zakaznici (jmeno, telefon, termin, poznamka) VALUES (?, ?, ?, ?)");
            $stmt->execute([$jmeno, $telefon, $termin, $poznamka]);
            
            // Zachováme zobrazení týdne podle zadaného data termínu, pokud existuje
            $zadanýDen = !empty($termin) ? substr($termin, 0, 10) : date('Y-m-d');
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=kalendar&filtr_den=" . $zadanýDen);
            exit;
        }
    }

    // 5. Uložení nastavení notifikací
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
        $mobil = trim($_POST['mobil'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');

        $db->exec("DELETE FROM nastaveni");
        $stmt = $db->prepare("INSERT INTO nastaveni (mobil, whatsapp) VALUES (?, ?)");
        $stmt->execute([$mobil, $whatsapp]);
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=nastaveni");
        exit;
    }

    // 6. Přidání položky do skladu
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_sklad') {
        $nazev = trim($_POST['nazev_polozky'] ?? '');
        $mnozstvi = trim($_POST['mnozstvi'] ?? '');
        $dodavatel = trim($_POST['dodavatel'] ?? '');
        $kontakt = trim($_POST['kontakt_dodavatele'] ?? '');
        $odkaz = trim($_POST['odkaz_web'] ?? '');

        if (!empty($nazev)) {
            $stmt = $db->prepare("INSERT INTO sklad (nazev_polozky, mnozstvi, dodavatel, kontakt_dodavatele, odkaz_web) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nazev, $mnozstvi, $dodavatel, $kontakt, $odkaz]);
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=sklad");
            exit;
        }
    }

    // 7. Přidání příjmu
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_prijem') {
        $datum = trim($_POST['datum'] ?? date('Y-m-d'));
        $popis = trim($_POST['popis'] ?? '');
        $castka = floatval($_POST['castka'] ?? 0);

        if ($castka > 0) {
            $stmt = $db->prepare("INSERT INTO prijmy (datum, popis, castka) VALUES (?, ?, ?)");
            $stmt->execute([$datum, $popis, $castka]);
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=prijmy");
            exit;
        }
    }

    // --- NAČTENÍ DAT PRO ZOBRAZENÍ (CELÝ TÝDEN) ---

    $zvoleneDatum = isset($_GET['filtr_den']) && !empty($_GET['filtr_den']) ? trim($_GET['filtr_den']) : date('Y-m-d');
    
    // Výpočet pondělí a neděle vybraného týdne
    $pondeli = date('Y-m-d', strtotime('monday this week', strtotime($zvoleneDatum)));
    $nedele = date('Y-m-d', strtotime('sunday this week', strtotime($zvoleneDatum)));

    // Načtení zákazníků za celý týden (od pondělí do neděle)
    $stmt = $db->prepare("SELECT * FROM zakaznici WHERE SUBSTR(termin, 1, 10) BETWEEN ? AND ? ORDER BY termin ASC");
    $stmt->execute([$pondeli, $nedele]);
    $zakaznici = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtSettings = $db->query("SELECT * FROM nastaveni LIMIT 1");
    $nastaveni = $stmtSettings->fetch(PDO::FETCH_ASSOC);

    $stmtSklad = $db->query("SELECT * FROM sklad ORDER BY id DESC");
    $skladPolozky = $stmtSklad->fetchAll(PDO::FETCH_ASSOC);

    $stmtPrijmy = $db->query("SELECT * FROM prijmy ORDER BY datum DESC, id DESC");
    $prijmySeznam = $stmtPrijmy->fetchAll(PDO::FETCH_ASSOC);

    // Výpočet celkových příjmů
    $celkemPrijmy = 0;
    foreach ($prijmySeznam as $p) {
        $celkemPrijmy += $p['castka'];
    }

    // Určení aktivní záložky
    $aktivniTab = isset($_GET['tab']) ? $_GET['tab'] : 'kalendar';

} catch (Exception $e) {
    die("Chyba databáze: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kadeřnictví Marie Kučerová</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #faf2f8;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(186, 85, 211, 0.15);
            border-top: 5px solid #c71585;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        h1 {
            color: #c71585;
            margin: 0;
            font-size: 26px;
        }
        h2 {
            color: #8b008b;
            font-size: 20px;
            margin-top: 20px;
        }
        p {
            color: #666;
        }
        .logout-btn {
            background-color: #f3e5f5;
            color: #8b008b;
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.9em;
            border: 1px solid #e1bee7;
            transition: all 0.2s;
        }
        .logout-btn:hover {
            background-color: #e1bee7;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #f0d6ee;
            padding-bottom: 12px;
            flex-wrap: wrap;
        }
        .tab-btn {
            background-color: #f3e5f5;
            color: #8b008b;
            padding: 10px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.95em;
            transition: all 0.2s;
        }
        .tab-btn:hover {
            background-color: #e1bee7;
        }
        .tab-btn.active {
            background-color: #c71585;
            color: white;
            box-shadow: 0 3px 8px rgba(199, 21, 133, 0.3);
        }
        form {
            background: #fdf7fc;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #f1d4ed;
            margin-bottom: 30px;
        }
        .filter-box {
            background: #faeaf6;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #f0bfe5;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .form-group {
            flex: 1;
            min-width: 200px;
            margin-bottom: 0;
        }
        .form-group-full {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input[type="text"], input[type="datetime-local"], input[type="date"], input[type="tel"], input[type="number"], textarea {
            width: 100%;
            padding: 9px;
            box-sizing: border-box;
            border: 1px solid #d8b4d0;
            border-radius: 6px;
            background-color: #fff;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #c71585;
            box-shadow: 0 0 5px rgba(199, 21, 133, 0.2);
        }
        textarea {
            resize: vertical;
            height: 70px;
        }
        button {
            background-color: #c71585;
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background 0.2s;
        }
        button:hover {
            background-color: #a0106c;
        }
        .btn-green {
            background-color: #2e8b57;
        }
        .btn-green:hover {
            background-color: #246d44;
        }
        .btn-secondary {
            background-color: #9370db;
            padding: 8px 15px;
            font-size: 14px;
            text-decoration: none;
            color: white;
            border-radius: 6px;
            display: inline-block;
        }
        .btn-secondary:hover {
            background-color: #7b58c6;
        }
        .btn-danger {
            background-color: #db7093;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85em;
            display: inline-block;
        }
        .btn-danger:hover {
            background-color: #c85a7f;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0d6ee;
            text-align: left;
        }
        th {
            background-color: #c71585;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background-color: #fdf7fc;
        }
        .termin-badge {
            background-color: #f3e5f5;
            color: #8b008b;
            padding: 5px 10px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 0.9em;
            border: 1px solid #e1bee7;
        }
        .action-link {
            padding: 5px 10px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.8em;
            display: inline-block;
            margin-top: 4px;
            margin-right: 4px;
            color: white;
            font-weight: 500;
        }
        .whatsapp-link { background-color: #25d366; }
        .whatsapp-link:hover { background-color: #1ebe5d; }
        .sms-link { background-color: #17a2b8; }
        .sms-link:hover { background-color: #138496; }
        .tel-link { background-color: #8b008b; }
        .tel-link:hover { background-color: #6b006b; }
        .web-link { background-color: #ba55d3; }
        .web-link:hover { background-color: #a43ec0; }
        .summary-box {
            background: #f3e5f5;
            border: 1px solid #e1bee7;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 1.1em;
            color: #6a1b9a;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-top">
        <div>
            <h1>Kadeřnictví Marie Kučerová 💇‍♀️</h1>
            <p style="margin: 5px 0 0 0;">Přehledná správa zákazníků, skladu a příjmů vytvořená na míru.</p>
        </div>
        <div>
            <a href="?odhlasit=1" class="logout-btn">🔒 Odhlásit se</a>
        </div>
    </div>

    <!-- Horní přepínací záložky -->
    <div class="tabs">
        <a href="?tab=kalendar" class="tab-btn <?php echo ($aktivniTab === 'kalendar') ? 'active' : ''; ?>">📅 Kalendář a týden</a>
        <a href="?tab=sklad" class="tab-btn <?php echo ($aktivniTab === 'sklad') ? 'active' : ''; ?>">📦 Sklad a dodavatelé</a>
        <a href="?tab=prijmy" class="tab-btn <?php echo ($aktivniTab === 'prijmy') ? 'active' : ''; ?>">💰 Příjmy (tržby)</a>
        <a href="?tab=nastaveni" class="tab-btn <?php echo ($aktivniTab === 'nastaveni') ? 'active' : ''; ?>">⚙️ Nastavení</a>
    </div>

    <?php if ($aktivniTab === 'nastaveni'): ?>
        <!-- ZÁLOŽKA: NASTAVENÍ -->
        <form method="POST">
            <h2>Nastavení notifikací pro kadeřnici</h2>
            <input type="hidden" name="action" value="save_settings">
            <div class="form-row">
                <div class="form-group">
                    <label for="mobil">Mobilní číslo kadeřnice:</label>
                    <input type="tel" id="mobil" name="mobil" value="<?php echo htmlspecialchars($nastaveni['mobil'] ?? ''); ?>" placeholder="+420 ...">
                </div>
                <div class="form-group">
                    <label for="whatsapp">WhatsApp číslo kadeřnice:</label>
                    <input type="tel" id="whatsapp" name="whatsapp" value="<?php echo htmlspecialchars($nastaveni['whatsapp'] ?? ''); ?>" placeholder="+420 ...">
                </div>
            </div>
            <button type="submit" class="btn-green">Uložit nastavení</button>
        </form>

    <?php elseif ($aktivniTab === 'sklad'): ?>
        <!-- ZÁLOŽKA: SKLAD A DODAVATELÉ -->
        <form method="POST">
            <h2>Nová položka / materiál na skladě</h2>
            <input type="hidden" name="action" value="add_sklad">
            <div class="form-row">
                <div class="form-group">
                    <label for="nazev_polozky">Název materiálu / produktu:</label>
                    <input type="text" id="nazev_polozky" name="nazev_polozky" placeholder="např. Barva na vlasy 6.0" required>
                </div>
                <div class="form-group">
                    <label for="mnozstvi">Množství / Stav:</label>
                    <input type="text" id="mnozstvi" name="mnozstvi" placeholder="např. 2 ks / zbytek 50%">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="dodavatel">Název dodavatele:</label>
                    <input type="text" id="dodavatel" name="dodavatel" placeholder="např. Kadeřnický velkoobchod">
                </div>
                <div class="form-group">
                    <label for="kontakt_dodavatele">Telefon na dodavatele:</label>
                    <input type="text" id="kontakt_dodavatele" name="kontakt_dodavatele" placeholder="+420 ...">
                </div>
            </div>
            <div class="form-group-full">
                <label for="odkaz_web">Webový odkaz (prolink na e-shop / produkt):</label>
                <input type="text" id="odkaz_web" name="odkaz_web" placeholder="https://...">
            </div>
            <button type="submit" class="btn-green">Přidat na sklad</button>
        </form>

        <h2>Přehled skladu a dodavatelů</h2>
        <?php if (empty($skladPolozky)): ?>
            <p>Sklad je zatím prázdný.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Materiál</th>
                        <th>Množství</th>
                        <th>Dodavatel & Kontakt</th>
                        <th>Odkaz na e-shop</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($skladPolozky as $s): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($s['nazev_polozky']); ?></strong></td>
                            <td><?php echo htmlspecialchars($s['mnozstvi']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($s['dodavatel']); ?>
                                <?php if (!empty($s['kontakt_dodavatele'])): 
                                    $cistyTelDod = preg_replace('/[^0-9+]/', '', $s['kontakt_dodavatele']);
                                ?>
                                    <br>
                                    <a href="tel:<?php echo $cistyTelDod; ?>" class="action-link tel-link">📞 <?php echo htmlspecialchars($s['kontakt_dodavatele']); ?></a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($s['odkaz_web'])): ?>
                                    <a href="<?php echo htmlspecialchars($s['odkaz_web']); ?>" target="_blank" class="action-link web-link">🌐 Otevřít e-shop</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?tab=sklad&delete_sklad=<?php echo $s['id']; ?>" class="btn-danger" onclick="return confirm('Opravdu chcete tuto položku smazat?');">🗑️ Smazat</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php elseif ($aktivniTab === 'prijmy'): ?>
        <!-- ZÁLOŽKA: PŘÍJMY -->
        <form method="POST">
            <h2>Zaznamenat příjem za práci</h2>
            <input type="hidden" name="action" value="add_prijem">
            <div class="form-row">
                <div class="form-group">
                    <label for="datum">Datum:</label>
                    <input type="date" id="datum" name="datum" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="castka">Částka (Kč):</label>
                    <input type="number" step="1" id="castka" name="castka" placeholder="např. 850" required>
                </div>
            </div>
            <div class="form-group-full">
                <label for="popis">Popis (za co to je / zákaznice):</label>
                <input type="text" id="popis" name="popis" placeholder="např. Střih + barva - Hana Vetešníková">
            </div>
            <button type="submit" class="btn-green">Uložit příjem</button>
        </form>

        <div class="summary-box">
            ✨ Celkové zaznamenané příjmy: <?php echo number_format($celkemPrijmy, 0, ',', ' '); ?> Kč
        </div>

        <h2>Historie příjmů</h2>
        <?php if (empty($prijmySeznam)): ?>
            <p>Zatím nejsou zaznamenané žádné příjmy.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Popis</th>
                        <th>Částka</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prijmySeznam as $p): ?>
                        <tr>
                            <td><span class="termin-badge"><?php echo date('d.m.Y', strtotime($p['datum'])); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($p['popis']); ?></strong></td>
                            <td><span style="color: #c71585; font-weight: bold;"><?php echo number_format($p['castka'], 0, ',', ' '); ?> Kč</span></td>
                            <td>
                                <a href="?tab=prijmy&delete_prijem=<?php echo $p['id']; ?>" class="btn-danger" onclick="return confirm('Opravdu chcete tento záznam smazat?');">🗑️ Smazat</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php else: ?>
        <!-- ZÁLOŽKA: KALENDÁŘ A ZÁKAZNÍCI (CELÝ TÝDEN) -->
        
        <form method="GET" action="" class="filter-box">
            <input type="hidden" name="tab" value="kalendar">
            <div style="flex: 1;">
                <label for="filtr_den" style="margin-bottom: 3px; font-size: 14px; color: #8b008b;">📅 Zobrazit týden podle data:</label>
                <input type="date" id="filtr_den" name="filtr_den" value="<?php echo htmlspecialchars($zvoleneDatum); ?>" onchange="this.form.submit()">
            </div>
            <div>
                <a href="?tab=kalendar" class="btn-secondary">Tento týden</a>
            </div>
        </form>

        <form method="POST">
            <h2>Nový zákazník / Termín</h2>
            <input type="hidden" name="action" value="add_zakaznik">
            <div class="form-row">
                <div class="form-group">
                    <label for="jmeno">Jméno a příjmení:</label>
                    <input type="text" id="jmeno" name="jmeno" required>
                </div>
                <div class="form-group">
                    <label for="telefon">Telefon zákazníka:</label>
                    <input type="text" id="telefon" name="telefon" placeholder="+420 ...">
                </div>
            </div>
            
            <div class="form-group-full">
                <label for="termin">Datum a čas termínu:</label>
                <input type="datetime-local" id="termin" name="termin">
            </div>

            <div class="form-group-full">
                <label for="poznamka">Poznámka (barva, střih, specifika):</label>
                <textarea id="poznamka" name="poznamka"></textarea>
            </div>
            
            <button type="submit" class="btn-green">Uložit termín a zákazníka</button>
        </form>

        <h2>
            Přehled termínů (Celý týden: <?php echo date('d.m.Y', strtotime($pondeli)); ?> – <?php echo date('d.m.Y', strtotime($nedele)); ?>)
        </h2>

        <?php if (empty($zakaznici)): ?>
            <p>V tomto týdnu nejsou naplánováni žádní zákazníci.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Termín</th>
                        <th>Jméno</th>
                        <th>Kontakt a rychlé akce</th>
                        <th>Poznámka / Receptura</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($zakaznici as $z): ?>
                        <tr>
                            <td>
                                <?php if (!empty($z['termin'])): ?>
                                    <span class="termin-badge"><?php echo date('d.m.Y H:i', strtotime($z['termin'])); ?></span>
                                <?php else: ?>
                                    <span style="color: #888;">Bez termínu</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($z['jmeno']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($z['telefon']); ?>
                                <?php if (!empty($z['telefon'])): 
                                    $cistyTel = preg_replace('/[^0-9+]/', '', $z['telefon']);
                                    $textMsg = urlencode("Dobrý den, připomínám Váš termín v kadeřnictví.");
                                ?>
                                    <br>
                                    <a href="https://wa.me/<?php echo $cistyTel; ?>?text=<?php echo $textMsg; ?>" target="_blank" class="action-link whatsapp-link">💬 WhatsApp</a>
                                    <a href="sms:<?php echo $cistyTel; ?>?body=<?php echo $textMsg; ?>" class="action-link sms-link">📩 SMS</a>
                                    <a href="tel:<?php echo $cistyTel; ?>" class="action-link tel-link">📞 Volat</a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo nl2br(htmlspecialchars($z['poznamka'])); ?></td>
                            <td>
                                <?php 
                                    $delLink = "?tab=kalendar&delete_zakaznik=" . $z['id'] . '&filtr_den=' . urlencode($zvoleneDatum);
                                ?>
                                <a href="<?php echo $delLink; ?>" class="btn-danger" onclick="return confirm('Opravdu chcete tohoto zákazníka smazat?');">🗑️ Smazat</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>