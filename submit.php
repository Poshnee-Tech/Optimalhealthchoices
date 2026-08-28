<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	header('Allow: POST');
	exit('Method Not Allowed');
}

function post_value(string $name): string
{
	return trim((string) ($_POST[$name] ?? ''));
}

// Credentials live in config.php, which is git-ignored — never commit them.
// Copy config.example.php to config.php on the server and fill it in.
$config = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];

$dbHost = $config['db_host'] ?? 'localhost';
$dbPort = $config['db_port'] ?? '3306';
$dbName = $config['db_name'] ?? '';
$dbUser = $config['db_user'] ?? '';
$dbPassword = $config['db_password'] ?? '';

$trusted = post_value('trusted');

if (post_value('ohc_leave_empty') !== '') {
	http_response_code(400);
	exit('Invalid submission.');
}

$lead = [
	'first_name' => post_value('first_name'),
	'last_name' => post_value('last_name'),
	'zip' => post_value('zip'),
	'phone' => post_value('phone'),
	'age_band' => post_value('age_band'),
	'parts_ab' => post_value('parts_ab'),
	'medicaid' => post_value('medicaid'),
	'consent_contact' => isset($_POST['consent_contact']) ? 1 : 0,
	'terms_ack' => isset($_POST['terms_ack']) ? 1 : 0,
	'consent_text_rendered' => post_value('consent_text_rendered'),
	'submitted_at' => post_value('submitted_at'),
];

$phone_digits = preg_replace('/\D+/', '', $lead['phone']) ?? '';
if (strlen($phone_digits) === 11 && $phone_digits[0] === '1') {
	$phone_digits = substr($phone_digits, 1);
}

$allowed_age_bands = ['65_plus', 'turning_65', 'under_65'];
$allowed_answers = ['yes', 'no', 'unsure'];
$valid = $lead['first_name'] !== ''
	&& $lead['last_name'] !== ''
	&& preg_match('/^\d{5}$/', $lead['zip'])
	&& strlen($phone_digits) === 10
	&& in_array($lead['age_band'], $allowed_age_bands, true)
	&& in_array($lead['parts_ab'], $allowed_answers, true)
	&& in_array($lead['medicaid'], $allowed_answers, true)
	&& $lead['consent_contact'] === 1
	&& $lead['terms_ack'] === 1;

if (!$valid) {
	http_response_code(422);
	exit('Please complete all required fields.');
}

$host = getenv('DB_HOST') ?: $dbHost;
$port = getenv('DB_PORT') ?: $dbPort;
$database = getenv('DB_NAME') ?: $dbName;
$username = getenv('DB_USER') ?: $dbUser;
$password = getenv('DB_PASSWORD') ?: $dbPassword;

if ($database === '' || $username === '') {
	error_log('Lead form database configuration is missing.');
	http_response_code(500);
	exit('The form is temporarily unavailable.');
}

try {
	$pdo = new PDO(
		"mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
		$username,
		$password,
		[
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_EMULATE_PREPARES => false,
		]
	);

	$sql = <<<'SQL'
INSERT INTO leads (
	first_name, last_name, zip, phone, age_band, parts_ab, medicaid,
	consent_contact, terms_ack, trusted, consent_text_rendered, submitted_at
) VALUES (
	:first_name, :last_name, :zip, :phone, :age_band, :parts_ab, :medicaid,
	:consent_contact, :terms_ack, :trusted, :consent_text, :submitted_at
)
SQL;
	$statement = $pdo->prepare($sql);
	$statement->execute([
		':first_name' => $lead['first_name'],
		':last_name' => $lead['last_name'],
		':zip' => $lead['zip'],
		':phone' => $phone_digits,
		':age_band' => $lead['age_band'],
		':parts_ab' => $lead['parts_ab'],
		':medicaid' => $lead['medicaid'],
		':consent_contact' => $lead['consent_contact'],
		':terms_ack' => $lead['terms_ack'],
		':trusted' => $trusted,
		':consent_text' => $lead['consent_text_rendered'],
		':submitted_at' => $lead['submitted_at'],
	]);
} catch (PDOException $exception) {
	error_log('Lead form database insert failed: ' . $exception->getMessage());
	http_response_code(500);
	exit('The form is temporarily unavailable.');
}

header('Location: /thank-you', true, 303);
exit;
