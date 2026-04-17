<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;
use Google\Cloud\Firestore\FirestoreClient;

if (!isset($_GET['id'])) {
    die("No record ID provided.");
}

$infantId = $_GET['id'];

/* =========================
   FIREBASE CREDENTIAL SETUP
   ========================= */

$firebase = getenv('FIREBASE_SERVICE_ACCOUNT');

if (!$firebase) {
    die("Firebase service account not found in Render environment variables.");
}

$firebasePath = sys_get_temp_dir() . '/firebase.json';
file_put_contents($firebasePath, $firebase);

putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $firebasePath);

/* =========================
   BULLETPROOF DATE PARSER
   ========================= */

function parseFirestoreDate($value): ?DateTimeImmutable
{
    try {
        if (empty($value)) return null;

        if (is_object($value) && method_exists($value, 'get')) {
            $dt = $value->get();

            if ($dt instanceof DateTimeImmutable) return $dt;
            if ($dt instanceof DateTime) {
                return DateTimeImmutable::createFromMutable($dt);
            }
        }

        if ($value instanceof DateTimeImmutable) return $value;
        if ($value instanceof DateTime) {
            return DateTimeImmutable::createFromMutable($value);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        return null;
    } catch (Exception $e) {
        return null;
    }
}

/* =========================
   FIRESTORE INIT
   ========================= */

$db = new FirestoreClient([
    'projectId' => 'lacson-infant-records'
]);

/* =========================
   FETCH INFANT
   ========================= */

$infantSnapshot = $db->collection('infant_rec')->document($infantId)->snapshot();

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
   SAFE DATE CONVERSION
   ========================= */

$bday = parseFirestoreDate($infant['bday'] ?? null);
$marriage = parseFirestoreDate($parent['marriage'] ?? null);

/* =========================
   TEMPLATE
   ========================= */

$template = new TemplateProcessor(__DIR__ . '/../template.docx');

/* =========================
   SAFE UPPERCASE HELPER
   ========================= */

function up($value)
{
    return strtoupper((string)($value ?? ''));
}

/* =========================
   PLACEHOLDERS (ALL CAPS OUTPUT)
   ========================= */

$template->setValue('PROVINCE', up('Nueva Ecija'));
$template->setValue('CITY', up('San Leonardo'));
$template->setValue('REGISTRY_NO', up($infantId));

$template->setValue('BABY_FNAME', up($infant['fname']));
$template->setValue('BABY_MNAME', up($infant['mname']));
$template->setValue('BABY_LNAME', up($infant['lname']));
$template->setValue('BABY_SEX', up($infant['gender']));

$template->setValue('BABY_BDAY', $bday ? $bday->format('d') : '');
$template->setValue('BABY_BMONTH', $bday ? strtoupper($bday->format('F')) : '');
$template->setValue('BABY_BYEAR', $bday ? $bday->format('Y') : '');

$template->setValue('DELIVERY_TYPE', up($infant['delivery']));
$template->setValue('MULTI_CHILD', up($infant['type_multi']));
$template->setValue('BIRTH_ORDER', up($infant['birth_order']));
$template->setValue('WEIGHT', up($infant['weight']));

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

$template->setValue('MARRIAGE_DATE', $marriage ? strtoupper($marriage->format('F d, Y')) : '');
$template->setValue('MARRY_PLACE', up($parent['marriage_place']));

$template->setValue('OB_NAME', up($infant['ob_list']));
$template->setValue('TIME_OF_BIRTH', $bday ? $bday->format('h:i A') : '');
$template->setValue('DATE_TODAY', strtoupper(date('F d, Y')));

$template->setValue(
    'FATHER',
    up(
        trim(
            ($parent['f_fname'] ?? '') . ' ' .
            ($parent['f_mname'] ?? '') . ' ' .
            ($parent['f_lname'] ?? '')
        )
    )
);

/* =========================
   OUTPUT
   ========================= */

$output = 'BirthCertificate.docx';
$template->saveAs($output);

header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Disposition: attachment; filename=\"$output\"");
readfile($output);
unlink($output);
exit;
