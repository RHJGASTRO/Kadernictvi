<?php
session_start();

// 1. ZABEZPEČENÍ HESLEM
$spravne_heslo = "maruska123"; // Tady si můžeš heslo změnit

if (isset($_POST['heslo'])) {
    if ($_POST['heslo'] === $spravne_heslo) {
        $_SESSION['prihlasen'] = true;
        header("Location: index.php");
        exit;
    } else {
        $chyba = "Nesprávné heslo!";
    }
}

if (isset($_GET['odhlasit'])) {
    unset($_SESSION['prihlasen']);
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['prihlasen'])): 
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Přihlášení - Kadeřnictví Marie Kučerová</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #fdf2f8; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); width: 320px; text-align: center; }
        h2 { color: #db2777; margin-bottom: 10px; }
        p { color: #6b7280; font-size: 14px; margin-bottom: 20px; }
        input[type="password"] { width: 100%; padding: 12px; margin-bottom: 20px; border: 1px solid #d1d5db; border-radius: 8px; box-sizing: border-box; font-size: 16px; }
        button { background: #db2777; color: white; border: none; padding: 12px; border-radius: 8px; cursor: pointer; width: 100%; font-weight: bold; font-size: 16px; transition: background 0.2s; }
        button:hover { background: #be185d; }
        .error { color: #dc2626; font-size: 14px; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Kadeřnictví Marie</h2>
        <p>Zadej heslo pro přístup do deníku</p>
        <form method="POST">
            <input type="password" name="heslo" placeholder="Heslo" required autofocus>
            <button type="submit">Přihlásit se</button>
            <?php if (isset($chyba)) echo "<div class='error'>$chyba</div>"; ?>
        </form>
    </div>
</body>
</html>
<?php 
    exit;
endif;

// 2. PŘIPOJENÍ K DATABÁZI A LOGIKA APLIKACE
try {
    $db_file = __DIR__ . '/kadernictvi.db';
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Vytvoření tabulky, pokud ještě neexistuje
    $pdo->exec("CREATE TABLE IF NOT EXISTS termin (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        jmeno TEXT NOT NULL,
        telefon TEXT,
        datum TEXT NOT NULL,
        cas TEXT NOT NULL,
        poznamka TEXT
    )");
} catch (Exception $e) {
    die("Chyba připojení k databázi: " . $e->getMessage());
}

// Zpracování přidání termínu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['akce']) && $_POST['akce'] === 'pridat') {
    $jmeno = trim($_POST['jmeno']);
    $telefon = trim($_POST['telefon']);
    $datum_cas = $_POST['datum_cas']; 
    $poznamka = trim($_POST['poznamka']);

    if (!empty($jmeno) && !empty($datum_cas)) {
        $casti = explode('T', $datum_cas);
        $datum = $casti[0];
        $cas = isset($casti[1]) ? $casti[1] : '00:00';

        $stmt = $pdo->prepare("INSERT INTO termin (jmeno, telefon, datum, cas, poznamka) VALUES (:jmeno, :telefon, :datum, :cas, :poznamka)");
        $stmt->execute([
            'jmeno' => $jmeno,
            'telefon' => $telefon,
            'datum' => $datum,
            'cas' => $cas,
            'poznamka' => $poznamka
        ]);
    }
    header("Location: index.php");
    exit;
}

// Zpracování mazání termínu
if (isset($_GET['smazat'])) {
    $id = intval($_GET['smazat']);
    $stmt = $pdo->prepare("DELETE FROM termin WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header("Location: index.php");
    exit;
}

// Výpočet týdne (pondělí až neděle)
$zvolene_datum = isset($_GET['datum']) ? $_GET['datum'] : date('Y-m-d');
$pondeli = date('Y-m-d', strtotime('monday this week', strtotime($zvolene_datum)));
$nedele = date('Y-m-d', strtotime('sunday this week', strtotime($zvolene_datum)));

// Načtení termínů pro celý týden
$stmt = $pdo->prepare("SELECT * FROM termin WHERE datum BETWEEN :pondeli AND :nedele ORDER BY datum ASC, cas ASC");
$stmt->execute(['pondeli' => $pondeli, 'nedele' => $nedele]);
$terminy_tydne = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Kadeřnictví Marie Kučerová</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #fdf2f8; margin: 0; padding: 20px; color: #374151; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f3f4f6; padding-bottom: 20px; margin-bottom: 25px; }
        h1 { color: #db2777; margin: 0; font-size: 24px; }
        .logout-btn { background: #f3f4f6; color: #4b5563; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; }
        .logout-btn:hover { background: #e5e7eb; }
        
        .nav-tisk { display: flex; gap: 10px; margin-bottom: 25px; }
        .nav-tisk a { background: #fdf2f8; color: #db2777; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; border: 1px solid #fbcfe8; }
        .nav-tisk a.active { background: #db2777; color: white; border-color: #db2777; }

        .box { background: #fff; border: 1px solid #f3f4f6; border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .box h3 { margin-top: 0; color: #1f2937; font-size: 18px; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group.full { grid-column: span 2; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; color: #4b5563; }
        input[type="text"], input[type="datetime-local"], textarea { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; box-sizing: border-box; font-size: 14px; }
        textarea { resize: vertical; height: 80px; }
        
        button.btn-primary { background: #16a34a; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 14px; }
        button.btn-primary:hover { background: #15803d; }

        .week-selector { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; background: #fdf2f8; padding: 15px; border-radius: 10px; flex-wrap: wrap; gap: 10px; }
        .week-selector form { display: flex; align-items: center; gap: 10px; margin: 0; }
        .week-selector input[type="date"] { padding: 8px; border: 1px solid #fbcfe8; border-radius: 6px; }
        .week-selector button { background: #db2777; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        th { background: #fdf2f8; color: #db2777; font-weight: 600; }
        tr:hover { background: #fafafa; }
        
        .badge-datum { background: #fce7f3; color: #be185d; padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 12px; display: inline-block; }
        .btn-smazat { background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: bold; }
        .btn-smazat:hover { background: #dc2626; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Kadeřnictví Marie Kučerová 💇‍♀️</h1>
            <a href="?odhlasit=1" class="logout-btn">Odhlásit se</a>
        </header>

        <div class="nav-tisk">
            <a href="#" class="active">Kalendář a týden</a>
            <a href="#">Sklad a dodavatelé</a>
            <a href="#">Příjmy (tržby)</a>
            <a href="#">Nastavení</a>
        </div>

        <!-- Formulář pro nový termín -->
        <div class="box">
            <h3>Nový zákazník / Termín</h3>
            <form method="POST">
                <input type="hidden" name="akce" value="pridat">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Jméno a příjmení:</label>
                        <input type="text" name="jmeno" required placeholder="např. Hana Vetešníková">
                    </div>
                    <div class="form-group">
                        <label>Telefon zákazníka:</label>
                        <input type="text" name="telefon" placeholder="+420 ...">
                    </div>
                    <div class="form-group">
                        <label>Datum a čas termínu:</label>
                        <input type="datetime-local" name="datum_cas" required>
                    </div>
                    <div class="form-group full">
                        <label>Poznámka (barva, střih, specifika):</label>
                        <textarea name="poznamka" placeholder="barva, střih..."></textarea>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Uložit termín a zákazníka</button>
            </form>
        </div>

        <!-- Výběr týdne -->
        <div class="week-selector">
            <div>
                <strong>Zobrazený týden:</strong> <?php echo date('d.m.Y', strtotime($pondeli)); ?> – <?php echo date('d.m.Y', strtotime($nedele)); ?>
            </div>
            <form method="GET">
                <label>Vybrat datum:</label>
                <input type="date" name="datum" value="<?php echo htmlspecialchars($zvolene_datum); ?>">
                <button type="submit">Zobrazit týden</button>
            </form>
        </div>

        <!-- Přehled termínů za celý týden -->
        <div class="box">
            <h3>Přehled termínů (Celý týden)</h3>
            <?php if (empty($terminy_tydne)): ?>
                <p style="color: #6b7280; font-style: italic;">V tomto týdnu nejsou naplánované žádné termíny.</p>
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
                        <?php foreach ($terminy_tydne as $t): ?>
                            <tr>
                                <td>
                                    <span class="badge-datum"><?php echo date('d.m.Y', strtotime($t['datum'])); ?></span><br>
                                    <strong><?php echo htmlspecialchars($t['cas']); ?></strong>
                                </td>
                                <td><strong><?php echo htmlspecialchars($t['jmeno']); ?></strong></td>
                                <td>
                                    <?php if (!empty($t['telefon'])): ?>
                                        <?php echo htmlspecialchars($t['telefon']); ?><br>
                                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $t['telefon']); ?>" target="_blank" style="color: #16a34a; font-size: 12px; text-decoration: none; font-weight: bold;">WhatsApp</a> | 
                                        <a href="sms:<?php echo htmlspecialchars($t['telefon']); ?>" style="color: #2563eb; font-size: 12px; text-decoration: none; font-weight: bold;">SMS</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo nl2br(htmlspecialchars($t['poznamka'])); ?></td>
                                <td>
                                    <a href="?smazat=<?php echo $t['id']; ?>&datum=<?php echo $zvolene_datum; ?>" class="btn-smazat" onclick="return confirm('Opravdu smazat tento termín?');">Smazat</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>