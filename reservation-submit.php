<?php

declare(strict_types=1);

const RESERVATION_EMAIL = 'maringotkauvody@gmail.com';
const SENDER_EMAIL = 'noreply@maringotkauvody.cz';
const RATE_LIMIT_MINUTE = 60;
const RATE_LIMIT_HOUR_MAX = 5;
const DUPLICATE_LIMIT = 600;

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

function display_date(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false ? $date->format('j. n. Y') : $value;
}

function request_source_is_allowed(): bool
{
    $allowedHosts = ['maringotkauvody.cz', 'www.maringotkauvody.cz'];
    $sources = [$_SERVER['HTTP_ORIGIN'] ?? '', $_SERVER['HTTP_REFERER'] ?? ''];

    foreach ($sources as $source) {
        if (!is_string($source) || $source === '') {
            continue;
        }

        $host = strtolower((string) parse_url($source, PHP_URL_HOST));
        if ($host === '' || !in_array($host, $allowedHosts, true)) {
            return false;
        }
    }

    return true;
}

function rate_limit_directory(): string
{
    $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'maringotka-reservation-rate-limit';

    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        error_log('Reservation form: cannot create rate-limit directory.');
        respond(503, false, 'Formulář je dočasně nedostupný. Zavolejte nám prosím na +420 603 723 705.');
    }

    return $directory;
}

function enforce_rate_limit(string $ipAddress): void
{
    $file = rate_limit_directory() . DIRECTORY_SEPARATOR . 'ip-' . hash('sha256', $ipAddress) . '.json';
    $handle = fopen($file, 'c+');

    if ($handle === false || !flock($handle, LOCK_EX)) {
        error_log('Reservation form: cannot lock rate-limit file.');
        respond(503, false, 'Formulář je dočasně nedostupný. Zavolejte nám prosím na +420 603 723 705.');
    }

    $now = time();
    $stored = json_decode((string) stream_get_contents($handle), true);
    $attempts = is_array($stored) ? $stored : [];
    $attempts = array_values(array_filter(
        $attempts,
        static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - 3600
    ));

    if ($attempts !== [] && max($attempts) > $now - RATE_LIMIT_MINUTE) {
        header('Retry-After: 60');
        respond(429, false, 'Poptávku jste právě odeslali. Další lze odeslat nejdříve za jednu minutu.');
    }

    if (count($attempts) >= RATE_LIMIT_HOUR_MAX) {
        header('Retry-After: 3600');
        respond(429, false, 'Byl dosažen limit pěti poptávek za hodinu. Zkuste to prosím později nebo nám zavolejte.');
    }

    $attempts[] = $now;
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, (string) json_encode($attempts));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function reserve_duplicate(string $email, string $arrival, string $departure): string
{
    $key = strtolower($email) . '|' . $arrival . '|' . $departure;
    $file = rate_limit_directory() . DIRECTORY_SEPARATOR . 'request-' . hash('sha256', $key) . '.txt';
    $handle = fopen($file, 'c+');

    if ($handle === false || !flock($handle, LOCK_EX)) {
        error_log('Reservation form: cannot lock duplicate file.');
        respond(503, false, 'Formulář je dočasně nedostupný. Zavolejte nám prosím na +420 603 723 705.');
    }

    $now = time();
    $lastSubmission = (int) trim((string) stream_get_contents($handle));

    if ($lastSubmission > $now - DUPLICATE_LIMIT) {
        header('Retry-After: 600');
        respond(429, false, 'Stejnou poptávku jsme již přijali. Není potřeba ji posílat znovu.');
    }

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, (string) $now);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $file;
}

function encoded_subject(string $subject): string
{
    return function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subject, 'UTF-8')
        : $subject;
}

function send_plain_email(string $recipient, string $subject, string $body, string $replyTo): bool
{
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: Maringotka u vody <' . SENDER_EMAIL . '>',
        'Reply-To: ' . clean_header_value($replyTo),
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    return mail(
        clean_header_value($recipient),
        encoded_subject($subject),
        $body,
        implode("\r\n", $headers)
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Formulář je možné pouze odeslat.');
}

if (!request_source_is_allowed()) {
    respond(403, false, 'Požadavek se nepodařilo ověřit. Obnovte prosím stránku a zkuste to znovu.');
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

$allowedGuests = ['1 osoba', '2 osoby', '3 osoby', '4 osoby', '5 osob', '6 osob', '7 osob', '8 osob', '9 osob', '10 osob'];
$allowedAnglers = ['0 rybářů', '1 rybář', '2 rybáři', '3 rybáři', '4 rybáři', '5 rybářů', '6 rybářů', '7 rybářů', '8 rybářů', '9 rybářů', '10 rybářů'];

if (!in_array($guests, $allowedGuests, true) || !in_array($anglers, $allowedAnglers, true)) {
    respond(422, false, 'Zkontrolujte prosím počet hostů a rybářů.');
}

if (strlen($phone) > 80 || strlen($note) > 6000) {
    respond(422, false, 'Některý z vyplněných údajů je příliš dlouhý.');
}

$ipAddress = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
enforce_rate_limit($ipAddress);
$duplicateFile = reserve_duplicate($email, $arrival, $departure);

$ownerBody = implode("\r\n", [
    'Nová poptávka rezervace z webu maringotkauvody.cz',
    '',
    'Jméno: ' . $name,
    'E-mail: ' . $email,
    'Telefon: ' . ($phone !== '' ? $phone : 'neuveden'),
    'Příjezd: ' . display_date($arrival),
    'Odjezd: ' . display_date($departure),
    'Počet hostů: ' . $guests,
    'Počet rybářů: ' . $anglers,
    '',
    'Poznámka:',
    $note !== '' ? $note : 'bez poznámky',
]);

if (!send_plain_email(
    RESERVATION_EMAIL,
    'Nová poptávka z webu Maringotka u vody',
    $ownerBody,
    $email
)) {
    @unlink($duplicateFile);
    error_log('Reservation form: owner email mail() returned false.');
    respond(500, false, 'Poptávku se nepodařilo odeslat. Zavolejte nám prosím na +420 603 723 705.');
}

$confirmationBody = implode("\r\n", [
    'Dobrý den,',
    '',
    'děkujeme za vaši poptávku pobytu v Maringotce u vody.',
    '',
    'Poptávku jsme úspěšně přijali. Zvolený termín od ' . display_date($arrival) . ' do ' . display_date($departure) . ' nyní zkontrolujeme a co nejdříve se vám ozveme s jeho potvrzením.',
    '',
    'Upozorňujeme, že odesláním formuláře ještě nevzniká závazná rezervace. Termín je rezervovaný až po našem potvrzení.',
    '',
    'Těšíme se na Vás u vody.',
    '',
    'Maringotka u vody',
    '+420 603 723 705',
    RESERVATION_EMAIL,
    'https://maringotkauvody.cz/',
]);

if (!send_plain_email(
    $email,
    'Poptávku jsme přijali | Maringotka u vody',
    $confirmationBody,
    RESERVATION_EMAIL
)) {
    error_log('Reservation form: confirmation email mail() returned false.');
}

respond(200, true, 'Děkujeme. Poptávku jsme přijali a na váš e-mail poslali potvrzení. Termín vám následně potvrdíme.');
