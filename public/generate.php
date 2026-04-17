<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;
use Google\Cloud\Firestore\FirestoreClient;

/* =========================
   VALIDATE INPUT
   ========================= */
if (!isset($_GET['id'])) {
    die("No record ID provided.");
}

$infantId = $_GET['id'];

/* =========================
   FIREBASE SETUP
   ========================= */
$firebase = getenv('FIREBASE_SERVICE_ACCOUNT');

if (!$firebase) {
    die("Missing FIREBASE_SERVICE_ACCOUNT in environment variables.");
}

$firebasePath = sys_get_temp_dir() . '/firebase.json';
file_put_contents($firebasePath, $firebase);

putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $firebasePath);

/* =========================
   FIRESTORE INIT
   ========================= */
$db = new FirestoreClient([
    'projectId' => 'lacson-infant-records'
]);

/* =========================
   SAFE DATE PARSER
   ========================= */
function parseFirestoreDate($value): ?DateTimeImmutable
{
    try {
        if ($value === null || $value === '') return null;

        if (is_object($value) && method_exists($value, 'get')) {
            $dt = $value->get();

            if ($dt instanceof DateTimeImmutable) return $dt;
            if ($dt instanceof DateTime) return DateTimeImmutable::createFromMutable($dt);
            if ($dt instanceof DateTimeInterface) return DateTimeImmutable::createFromInterface($dt);
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        return null;
    } catch (Throwable $e) {
        return null;
    }
}

/* =========================
   SAFE UPPERCASE HELPER
   ========================= */
function up($v): string
{
    return strtoupper((string)($v ?? ''));
}

/* =========================
   FETCH INFANT
   ========================= */
$infantSnapshot = $db->collection('infant_rec')
    ->document($infantId)
    ->snapshot();

if (!$infantSnapshot->exists()) {
    die("Infant record not found.");
}

$infant = $infantSnapshot->data();

/* =========================
   FETCH PARENT
   ========================= */
$parent = [];

if (!empty($infant['mother_id'])) {
    $parentSnapshot = $db->collection('parent_rec')
        ->document($infant['mother_id'])
        ->snapshot();

    if ($parentSnapshot->exists()) {
        $parent = $parentSnapshot->data();
    }
}

/* =========================
   SAFE DATES
   ========================= */
$bday = parseFirestoreDate($infant['bday'] ?? null);
$marriage = parseFirestoreDate($parent['marriage'] ?? null);

/* =========================
   WEIGHT (KG → GRAMS ONLY, NO "G")
   ========================= */
$weightKg = $infant['weight'] ?? null;
$weightDisplay = '';

if (is_numeric($weightKg)) {
    $weightDisplay = (string) round(((float)$weightKg) * 1000);
}

/* =========================
   TEMPLATE INIT
   ========================= */
$template = new TemplateProcessor(__DIR__ . '/../template.docx');

/* =========================
   PLACEHOLDERS
   ========================= */

$template->setValue('PROVINCE', up('Nueva Ecija'));
$template->setValue('CITY', up('San Leonardo'));
$template->setValue('REGISTRY_NO', up($infantId));

$template->setValue('BABY_FNAME', up($infant['fname']));
$template->setValue('BABY_MNAME', up($infant['mname']));
$template->setValue('BABY_LNAME', up($infant['lname']));
$template->setValue('BABY_SEX', up($infant['gender']));

$template->setValue('BABY_BDAY', $bday?->format('d') ?? '');
$template->setValue('BABY_BMONTH', $bday ? strtoupper($bday->format('F')) : '');
$template->setValue('BABY_BYEAR', $bday?->format('Y') ?? '');

/* =========================
   UPDATED FIELDS (YOUR REQUEST)
   ========================= */
$template->setValue('TYPE_MULTI', up($infant['type_multi']));
$template->setValue('MULT_ORDER', up($infant['type_multi']));
$template->setValue('BIRTH_ORDER', up($infant['birth_order']));

$template->setValue('WEIGHT', $weightDisplay);

/* =========================
   PARENT FIELDS
   ========================= */
$template->setValue('M_FNAME', up($parent['m_fname']));
$template->setValue('M_MNAME', up($parent['m_mname']));
$template->setValue('M_LNAME', up($parent['m_lname']));
$template->setValue('M_CITIZENSHIP', up($parent['m_citizenship']));
$template->setValue('M_RELIGION', up($parent['m_religion']));
$template->setValue('C_ALIVE', up($parent['child_count_all']));
$template->setValue('C_LIVING', up($parent['child_count_alive']));
$template->setValue('C_DEAD', up($parent['child_count_dead']));
$template->setValue('M_OCCUPATION', up($parent['m_occupation']));
$template->setValue('M_AGE', up($parent['m_age']));
$template->setValue('M_ADDRESS', up($parent['m_address']));

$template->setValue('F_FNAME', up($parent['f_fname']));
$template->setValue('F_MNAME', up($parent['f_mname']));
$template->setValue('F_LNAME', up($parent['f_lname']));
$template->setValue('F_CITIZENSHIP', up($parent['f_citizenship']));
$template->setValue('F_RELIGION', up($parent['f_religion']));
$template->setValue('F_OCCUPATION', up($parent['f_occupation']));
$template->setValue('F_AGE', up($parent['f_age']));
$template->setValue('F_ADDRESS', up($parent['f_address']));

$template->setValue(
    'FATHER',
    up(trim(
        ($parent['f_fname'] ?? '') . ' ' .
        ($parent['f_mname'] ?? '') . ' ' .
        ($parent['f_lname'] ?? '')
    ))
);

/* =========================
   EXTRA INFO
   ========================= */
$template->setValue('MARRIAGE_DATE', $marriage ? strtoupper($marriage->format('F d, Y')) : '');
$template->setValue('MARRY_PLACE', up($parent['marriage_place']));
$template->setValue('OB_NAME', up($infant['ob_list']));
$template->setValue('TIME_OF_BIRTH', $bday ? $bday->format('h:i A') : '');
$template->setValue('DATE_TODAY', strtoupper(date('F d, Y')));

/* =========================
   OUTPUT
   ========================= */
$output = sys_get_temp_dir() . "/BirthCertificate_$infantId.docx";

$template->saveAs($output);

header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Disposition: attachment; filename=\"BirthCertificate.docx\"");

readfile($output);
unlink($output);
exit;
