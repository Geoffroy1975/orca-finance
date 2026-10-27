<?php
/**
 * Orca Finance — traitement du formulaire de contact
 * Envoie la demande par e-mail à l’adresse ci-dessous.
 */
declare(strict_types=1);

const DESTINATAIRE = 'contact@orca-finance.fr';
const EXPEDITEUR   = 'contact@orca-finance.fr';
const MAX_ENVOIS_HEURE = 5;

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$enJson = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch')
    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains((string) $_SERVER['HTTP_ACCEPT'], 'application/json'));

function repondre(bool $ok, string $message, bool $enJson, int $code = 200): never
{
    if ($enJson) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: ./?envoi=' . ($ok ? 'ok' : 'erreur') . '#contact', true, 303);
    }
    exit;
}

function champ(string $nom, int $max): string
{
    $v = isset($_POST[$nom]) && is_string($_POST[$nom]) ? trim($_POST[$nom]) : '';
    $v = str_replace("\0", '', $v);
    return mb_substr($v, 0, $max, 'UTF-8');
}

function ligne(string $v): string
{
    // Supprime les retours à la ligne (protection contre l’injection d’en-têtes)
    return trim(preg_replace('/[\r\n\t]+/', ' ', $v) ?? '');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ./#contact', true, 303);
    exit;
}

// Pot de miel : un robot remplit ce champ invisible ; on fait comme si tout allait bien.
if (champ('site_web', 200) !== '') {
    repondre(true, 'Demande envoyée.', $enJson);
}

// Jeton posé par le JavaScript de la page : son absence signale un envoi automatisé.
$jeton = champ('jeton', 20);
if ($jeton === '' || !ctype_digit($jeton)) {
    repondre(false, "La demande n’a pas pu être envoyée. Rechargez la page puis réessayez.", $enJson, 400);
}

// Limite d’envois par adresse IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'inconnue';
$fichierLimite = rtrim(sys_get_temp_dir(), '/') . '/orca_contact_' . hash('sha256', $ip) . '.json';
$maintenant = time();
$envois = [];
if (is_readable($fichierLimite)) {
    $envois = json_decode((string) file_get_contents($fichierLimite), true) ?: [];
    $envois = array_values(array_filter($envois, fn($t) => is_int($t) && $t > $maintenant - 3600));
}
if (count($envois) >= MAX_ENVOIS_HEURE) {
    repondre(false, "Trop de demandes envoyées depuis votre connexion. Réessayez dans une heure ou écrivez-nous à contact@orca-finance.fr.", $enJson, 429);
}

$nom         = ligne(champ('nom', 120));
$societe     = ligne(champ('societe', 120));
$email       = ligne(champ('email', 160));
$telephone   = ligne(champ('telephone', 30));
$objet       = ligne(champ('objet', 60));
$message     = champ('message', 5000);
$consentement = champ('consentement', 5) === 'oui';

$objetsAutorises = ["Financement d’un projet", 'États financiers', 'Pilotage', 'Organisation', 'Autre demande'];
if (!in_array($objet, $objetsAutorises, true)) {
    $objet = 'Autre demande';
}

if ($nom === '' || $societe === '' || $message === '') {
    repondre(false, 'Complétez les champs obligatoires : nom, société et message.', $enJson, 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    repondre(false, 'Indiquez une adresse e-mail valide, par exemple nom@societe.fr.', $enJson, 422);
}
if (!$consentement) {
    repondre(false, 'Cochez la case pour accepter le traitement de votre demande.', $enJson, 422);
}
if (preg_match_all('#https?://#i', $message) > 3) {
    repondre(false, 'Votre message contient trop de liens. Retirez-en quelques-uns puis réessayez.', $enJson, 422);
}

$sujet = '[orca-finance.fr] ' . $objet . ' – ' . $societe;
$sujetEncode = '=?UTF-8?B?' . base64_encode($sujet) . '?=';

$corps = "Nouvelle demande reçue depuis le site orca-finance.fr\n"
    . "------------------------------------------------------\n\n"
    . "Nom et prénom : {$nom}\n"
    . "Société       : {$societe}\n"
    . "E-mail        : {$email}\n"
    . 'Téléphone     : ' . ($telephone !== '' ? $telephone : 'non renseigné') . "\n"
    . "Objet         : {$objet}\n\n"
    . "Message :\n" . $message . "\n\n"
    . "------------------------------------------------------\n"
    . 'Envoyé le ' . date('d/m/Y à H:i') . " – consentement au traitement donné.\n"
    . "Répondez directement à ce message pour écrire au demandeur.\n";

$nomExpediteur = '=?UTF-8?B?' . base64_encode('Site Orca Finance') . '?=';
$entetes = [
    'From: ' . $nomExpediteur . ' <' . EXPEDITEUR . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: orca-finance.fr',
];

$envoye = @mail(DESTINATAIRE, $sujetEncode, $corps, implode("\r\n", $entetes));

if (!$envoye) {
    repondre(false, "La demande n’a pas pu être envoyée. Écrivez-nous directement à contact@orca-finance.fr.", $enJson, 500);
}

$envois[] = $maintenant;
@file_put_contents($fichierLimite, json_encode($envois), LOCK_EX);

repondre(true, 'Demande envoyée. Nous revenons vers vous rapidement.', $enJson);
