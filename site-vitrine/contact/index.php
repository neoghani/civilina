<?php

declare(strict_types=1);

/*
 * Civilina contact form handler (civilina.com/contact/).
 * 1. Validates the POST, 2. stores the request in a private JSON register
 * (outside public_html), 3. emails a notification to Civilina.
 * The visitor is redirected to /?contact=ok#contact once the request is stored;
 * an email failure never loses the request (it is logged privately instead).
 */

$privateDir = '/home/feuvwywx/civilina_private';
$storageFile = $privateDir . '/contact_requests.json';
$mailLogFile = $privateDir . '/contact_mail.log';

const CIVILINA_NOTIFY_TO = 'abdelghani.bouhouche@civilina.com';
const CIVILINA_NOTIFY_FROM = 'contact@civilina.com';

function civilina_value(string $key, int $max = 500): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    if (mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }

    return $value;
}

function civilina_error(string $message): void
{
    http_response_code(422);
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Demande Civilina</title>';
    echo '<style>body{margin:0;font-family:Arial,sans-serif;background:#061523;color:#eef7fb;display:grid;min-height:100vh;place-items:center;padding:24px}.box{max-width:720px;background:rgba(255,255,255,.08);border:1px solid rgba(130,215,237,.28);border-radius:20px;padding:28px}a{color:#7bdcf4}.btn{display:inline-block;margin-top:16px;padding:12px 18px;border-radius:999px;background:#38c6e8;color:#03121e;text-decoration:none;font-weight:700}</style>';
    echo '</head><body><main class="box"><h1>Demande non envoyée</h1><p>' . $safeMessage . '</p><p>Vous pouvez aussi écrire à Civilina via LinkedIn ou réessayer depuis le formulaire.</p><a class="btn" href="/#contact">Retour au formulaire</a></main></body></html>';
    exit;
}

function civilina_log_mail(string $file, string $line): void
{
    @file_put_contents($file, date('c') . ' ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

function civilina_notify(array $request, string $logFile): void
{
    $subject = 'Nouvelle demande Civilina — ' . $request['need'] . ' — ' . $request['name'];

    $lines = [
        'Une nouvelle demande a été envoyée depuis le formulaire de civilina.com.',
        '',
        'Nom        : ' . $request['name'],
        'Entreprise : ' . ($request['company'] !== '' ? $request['company'] : '—'),
        'Fonction   : ' . ($request['position'] !== '' ? $request['position'] : '—'),
        'E-mail     : ' . $request['email'],
        'Téléphone  : ' . ($request['phone'] !== '' ? $request['phone'] : '—'),
        'Besoin     : ' . $request['need'],
        'Date       : ' . $request['created_at'],
        'Référence  : ' . $request['id'],
        '',
        'Message :',
        $request['message'],
        '',
        '—',
        'Répondez directement à cet e-mail pour écrire au demandeur.',
        'Toutes les demandes sont conservées dans civilina_private/contact_requests.json.',
    ];
    $body = implode("\r\n", $lines);

    // Values are already single-line (whitespace collapsed) and the email is validated,
    // so they cannot inject extra headers.
    $headers = [
        'From: Civilina <' . CIVILINA_NOTIFY_FROM . '>',
        'Reply-To: ' . $request['email'],
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: civilina-contact',
    ];

    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
    $sent = @mail(CIVILINA_NOTIFY_TO, $encodedSubject, $body, implode("\r\n", $headers), '-f' . CIVILINA_NOTIFY_FROM);

    civilina_log_mail($logFile, ($sent ? 'sent ' : 'FAILED ') . $request['id'] . ' to ' . CIVILINA_NOTIFY_TO);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /#contact', true, 302);
    exit;
}

if (civilina_value('website', 120) !== '') {
    header('Location: /?contact=ok#contact', true, 303);
    exit;
}

$name = civilina_value('name', 120);
$company = civilina_value('company', 160);
$position = civilina_value('position', 160);
$email = civilina_value('email', 180);
$phone = civilina_value('phone', 80);
$need = civilina_value('need', 160);
$message = trim((string) ($_POST['message'] ?? ''));

if (mb_strlen($message) > 2500) {
    $message = mb_substr($message, 0, 2500);
}

$allowedNeeds = [
    'Diagnostic',
    'Structuration processus',
    'Qualité ISO 9001',
    'QHSE',
    'Management de projet',
    'Supply Chain',
    'Audit',
    'Plateforme Laravel métier',
    'Tableaux de bord',
    'Accompagnement mensuel',
    'Autre',
];

if ($name === '' || $email === '' || $need === '' || $message === '') {
    civilina_error('Merci de renseigner le nom, l’e-mail, le besoin principal et le message.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    civilina_error('Merci de renseigner une adresse e-mail valide.');
}

if (!in_array($need, $allowedNeeds, true)) {
    civilina_error('Merci de sélectionner un besoin principal valide.');
}

if (!is_dir($privateDir) && !mkdir($privateDir, 0750, true) && !is_dir($privateDir)) {
    civilina_error('La demande n’a pas pu être enregistrée. Merci de réessayer plus tard.');
}

$requests = [];
if (is_file($storageFile)) {
    $raw = file_get_contents($storageFile);
    $decoded = json_decode($raw ?: '[]', true);
    if (is_array($decoded)) {
        $requests = array_values($decoded);
    }
}

$request = [
    'id' => bin2hex(random_bytes(8)),
    'created_at' => date('c'),
    'status' => 'new',
    'name' => $name,
    'company' => $company,
    'position' => $position,
    'email' => $email,
    'phone' => $phone,
    'need' => $need,
    'message' => $message,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
];
$requests[] = $request;

$encoded = json_encode($requests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($encoded === false || file_put_contents($storageFile, $encoded, LOCK_EX) === false) {
    civilina_error('La demande n’a pas pu être enregistrée. Merci de réessayer plus tard.');
}

civilina_notify($request, $mailLogFile);

header('Location: /?contact=ok#contact', true, 303);
exit;
