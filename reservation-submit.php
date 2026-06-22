<?php

declare(strict_types=1);

const RESERVATION_EMAIL = 'maringotkauvody@gmail.com';
const SENDER_EMAIL = 'noreply@maringotkauvody.cz';

function respond(int $status, bool $success, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode(
        ['success' => $success, 'message' => $message],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function post_value(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function clean_header_value(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Formulář je možné pouze odeslat.');
}

if (post_value('website') !== '') {
    respond(200, true, 'Děkujeme, poptávka byla odeslána.');
}

$name = post_value('name');
$email = post_value('email');
$arrival = post_value('arrival');
$departure = post_value('departure');
$guests = post_value('guests');
$anglers = post_value('anglers');
$phone = post_value('phone');
$note = post_value('note');

if ($name === '' || strlen($name) > 240) {
    respond(422, false, 'Vyplňte prosím své jméno.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    respond(422, false, 'Zadejte prosím platnou e-mailovou adresu.');
}

if (!valid_date($arrival) || !valid_date($departure) || $departure <= $arrival) {
    respond(422, false, 'Zkontrolujte prosím zadaný termín pobytu.');
}

if (strlen($phone) > 80 || strlen($note) > 6000) {
    respond(422, false, 'Některý z vyplněných údajů je příliš dlouhý.');
}

$subject = 'Nová poptávka z webu Maringotka u vody';
$encodedSubject = function_exists('mb_encode_mimeheader')
    ? mb_encode_mimeheader($subject, 'UTF-8')
    : $subject;
$safeEmail = clean_header_value($email);

$body = implode("\r\n", [
    'Nová poptávka rezervace z webu maringotkauvody.cz',
    '',
    'Jméno: ' . $name,
    'E-mail: ' . $email,
    'Telefon: ' . ($phone !== '' ? $phone : 'neuveden'),
    'Příjezd: ' . $arrival,
    'Odjezd: ' . $departure,
    'Počet hostů: ' . ($guests !== '' ? $guests : 'neuveden'),
    'Počet rybářů: ' . ($anglers !== '' ? $anglers : 'neuveden'),
    '',
    'Poznámka:',
    $note !== '' ? $note : 'bez poznámky',
]);

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'From: Maringotka u vody <' . SENDER_EMAIL . '>',
    'Reply-To: ' . $safeEmail,
    'X-Mailer: PHP/' . PHP_VERSION,
];

if (!mail(RESERVATION_EMAIL, $encodedSubject, $body, implode("\r\n", $headers))) {
    error_log('Reservation form: mail() returned false.');
    respond(500, false, 'Poptávku se nepodařilo odeslat. Zavolejte nám prosím na +420 603 723 705.');
}

respond(200, true, 'Děkujeme. Poptávka byla odeslána, termín vám potvrdíme e-mailem.');
