<?php
/**
 * Aurea Solis SARL - Script de traitement du formulaire de devis
 * Envoi sécurisé via SMTP Hostinger (system@kuilinga.tech)
 */

header('Content-Type: application/json; charset=utf-8');

// Autoriser les requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée. Utilisez POST.']);
    exit;
}

// Récupérer les données POST (FormData ou JSON)
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

// 1. Anti-spam Honeypot
if (!empty($data['antispam_hp'])) {
    // Bot détecté, feindre le succès pour tromper le bot
    echo json_encode([
        'success' => true,
        'message' => 'Merci, votre demande a bien été transmise. Un ingénieur de notre équipe vous répondra sous 48 heures ouvrées.'
    ]);
    exit;
}

// 2. Vérification du Recaptcha Mathématique (ex: 3 + 1 = 4)
$num1 = isset($data['captcha_num1']) ? intval($data['captcha_num1']) : null;
$num2 = isset($data['captcha_num2']) ? intval($data['captcha_num2']) : null;
$answer = isset($data['captcha_answer']) ? intval($data['captcha_answer']) : null;

if ($num1 === null || $num2 === null || $answer === null || ($num1 + $num2 !== $answer)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Le calcul de vérification de sécurité (Recaptcha) est incorrect. Veuillez réessayer.'
    ]);
    exit;
}

// 3. Extraction et validation des champs du formulaire
$nom = trim($data['nom'] ?? '');
$entreprise = trim($data['entreprise'] ?? 'Non spécifiée');
$email = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$telephone = trim($data['telephone'] ?? 'Non renseigné');
$service = trim($data['service'] ?? '');
$secteur = trim($data['secteur'] ?? 'Non renseigné');
$message = trim($data['message'] ?? '');

if (empty($nom) || empty($email) || empty($service) || empty($message)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Veuillez remplir tous les champs obligatoires marqués d’un astérisque (*).'
    ]);
    exit;
}

// Libellés clairs pour le service
$services_labels = [
    'energie-solaire' => 'Énergie solaire',
    'electricite-industrielle' => 'Électricité industrielle',
    'automatisation' => 'Automatisation & sécurité des machines',
    'solutions-minieres' => 'Solutions minières',
    'solutions-thermiques' => 'Solutions thermiques & échangeurs',
    'conseil-audit' => 'Conseil, audit & maintenance',
    'plusieurs' => 'Plusieurs domaines / à préciser'
];
$service_nom = $services_labels[$service] ?? $service;

// Libellés pour le secteur
$secteurs_labels = [
    'mines' => 'Mines & carrières',
    'energie' => 'Énergie & réseaux électriques',
    'industrie' => 'Industrie & agro-industrie',
    'btp' => 'BTP & immobilier',
    'public' => 'Infrastructures publiques',
    'tertiaire' => 'Tertiaire & bureaux',
    'telecom' => 'Télécommunications & TI',
    'residentiel' => 'Résidentiel & particuliers',
    'autre' => 'Autre'
];
$secteur_nom = $secteurs_labels[$secteur] ?? $secteur;

// 4. Configuration SMTP
$smtp_server = 'ssl://smtp.hostinger.com';
$smtp_port = 465;
$smtp_user = 'system@kuilinga.tech';
$smtp_pass = 'Notifis226@';

$destinataire_principal = 'info@aureasolis.com';
$destinataire_copie = 'system@kuilinga.tech'; // Copie de sauvegarde technique

$sujet = "=?UTF-8?B?" . base64_encode("Demande de devis : $service_nom - $nom") . "?=";
$date_envoi = date('d/m/Y à H:i:s');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'Non disponible';

// Corps HTML du courriel
$corps_html = "
<!DOCTYPE html>
<html>
<head>
<meta charset='utf-8'>
<style>
    body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #212529; background-color: #f4f6f9; margin: 0; padding: 20px; }
    .card { max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border: 1px solid #e5e7eb; }
    .header { background: #19191e; color: #ffffff; padding: 25px 30px; border-bottom: 4px solid #FFC107; }
    .header h1 { margin: 0; font-size: 20px; font-weight: 700; color: #ffffff; }
    .header span { color: #FFC107; }
    .header p { margin: 5px 0 0 0; font-size: 13px; color: #9ca3af; }
    .content { padding: 30px; }
    .field-group { margin-bottom: 18px; }
    .field-label { font-size: 12px; text-transform: uppercase; color: #6b7280; font-weight: 700; margin-bottom: 4px; }
    .field-value { font-size: 15px; color: #111827; background: #f9fafb; padding: 10px 14px; border-radius: 8px; border: 1px solid #e5e7eb; }
    .message-box { background: #fffdf5; border-left: 4px solid #FFC107; padding: 15px; border-radius: 6px; font-size: 15px; white-space: pre-wrap; color: #1f2937; }
    .footer { background: #f9fafb; padding: 15px 30px; font-size: 12px; color: #6b7280; text-align: center; border-top: 1px solid #e5e7eb; }
</style>
</head>
<body>
<div class='card'>
    <div class='header'>
        <h1><span>AUREA SOLIS SARL</span> — Demande de devis</h1>
        <p>Reçue le $date_envoi | Adresse IP : $ip</p>
    </div>
    <div class='content'>
        <div class='field-group'>
            <div class='field-label'>Demandeur</div>
            <div class='field-value'><strong>" . htmlspecialchars($nom) . "</strong> (" . htmlspecialchars($entreprise) . ")</div>
        </div>

        <div style='display: flex; gap: 15px;'>
            <div class='field-group' style='flex: 1;'>
                <div class='field-label'>Email</div>
                <div class='field-value'><a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></div>
            </div>
            <div class='field-group' style='flex: 1;'>
                <div class='field-label'>Téléphone</div>
                <div class='field-value'><a href='tel:" . htmlspecialchars($telephone) . "'>" . htmlspecialchars($telephone) . "</a></div>
            </div>
        </div>

        <div style='display: flex; gap: 15px;'>
            <div class='field-group' style='flex: 1;'>
                <div class='field-label'>Service demandé</div>
                <div class='field-value' style='color: #b45309; font-weight: 600;'>" . htmlspecialchars($service_nom) . "</div>
            </div>
            <div class='field-group' style='flex: 1;'>
                <div class='field-label'>Secteur d'activité</div>
                <div class='field-value'>" . htmlspecialchars($secteur_nom) . "</div>
            </div>
        </div>

        <div class='field-group'>
            <div class='field-label'>Description du projet</div>
            <div class='message-box'>" . nl2br(htmlspecialchars($message)) . "</div>
        </div>
    </div>
    <div class='footer'>
        Ce message a été envoyé via le formulaire de devis sur le site officiel d'Aurea Solis SARL.
    </div>
</div>
</body>
</html>
";

/**
 * Fonction d'envoi SMTP natif via socket SSL
 */
function sendSmtpMail($server, $port, $user, $pass, $from, $fromName, $to, $replyTo, $subject, $bodyHtml, $cc = null) {
    $socket = @fsockopen($server, $port, $errno, $errstr, 15);
    if (!$socket) {
        throw new Exception("Connexion SMTP échouée : $errstr ($errno)");
    }

    $read = function() use ($socket) {
        $data = '';
        while ($str = fgets($socket, 512)) {
            $data .= $str;
            if (substr($str, 3, 1) === ' ') break;
        }
        return $data;
    };

    $send = function($cmd) use ($socket, $read) {
        fputs($socket, $cmd . "\r\n");
        return $read();
    };

    $read(); // Bannière de bienvenue

    $res = $send("EHLO " . gethostname());
    if (strpos($res, '250') === false) throw new Exception("EHLO a échoué: $res");

    $res = $send("AUTH LOGIN");
    if (strpos($res, '334') === false) throw new Exception("AUTH LOGIN a échoué: $res");

    $res = $send(base64_encode($user));
    if (strpos($res, '334') === false) throw new Exception("Utilisateur SMTP refusé: $res");

    $res = $send(base64_encode($pass));
    if (strpos($res, '235') === false) throw new Exception("Authentification SMTP refusée: $res");

    $res = $send("MAIL FROM:<$from>");
    if (strpos($res, '250') === false) throw new Exception("MAIL FROM a échoué: $res");

    $res = $send("RCPT TO:<$to>");
    if (strpos($res, '250') === false) throw new Exception("RCPT TO principal a échoué: $res");

    if ($cc) {
        $send("RCPT TO:<$cc>");
    }

    $res = $send("DATA");
    if (strpos($res, '354') === false) throw new Exception("DATA a échoué: $res");

    $headers = [
        "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>",
        "To: <$to>",
        "Reply-To: <$replyTo>",
        "Subject: $subject",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "Content-Transfer-Encoding: base64",
        "Date: " . date('r'),
        "X-Mailer: Aurea Solis Web Engine"
    ];
    if ($cc) {
        $headers[] = "Cc: <$cc>";
    }

    $payload = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($bodyHtml)) . "\r\n.";
    $res = $send($payload);
    if (strpos($res, '250') === false) throw new Exception("Envoi des données échoué: $res");

    $send("QUIT");
    fclose($socket);
    return true;
}

try {
    sendSmtpMail(
        $smtp_server,
        $smtp_port,
        $smtp_user,
        $smtp_pass,
        $smtp_user,
        "Aurea Solis SARL - Web",
        $destinataire_principal,
        $email,
        $sujet,
        $corps_html,
        $destinataire_copie
    );

    echo json_encode([
        'success' => true,
        'message' => 'Merci, votre demande a bien été transmise. Un ingénieur de notre équipe vous répondra sous 48 heures ouvrées. Pour une demande urgente, appelez-nous au +226 66 59 34 74.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "Une erreur est survenue lors de l'envoi de votre demande : " . $e->getMessage()
    ]);
}
