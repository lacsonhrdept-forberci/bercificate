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
   FIRESTORE INIT
   ========================= */
function parseFirestoreDate($value): ?DateTimeImmutable
{
    try {
        if (empty($value)) {
            return null;
        }

        // Case 1: Firestore Timestamp object
        if (is_object($value) && method_exists($value, 'get')) {
            $dt = $value->get();

            if ($dt instanceof DateTimeImmutable) {
                return $dt;
            }

            if ($dt instanceof DateTime) {
                return DateTimeImmutable::createFromMutable($dt);
            }
        }

        // Case 2: Already DateTimeImmutable
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        // Case 3: PHP DateTime
        if ($value instanceof DateTime) {
            return DateTimeImmutable::createFromMutable($value);
        }

        // Case 4: String date (Firestore sometimes returns this depending on setup)
        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        return null;

    } catch (Exception $e) {
        return null;
    }
}
$db = new FirestoreClient([
    'projectId' => 'lacson-infant-records'
]);

/* =========================
   FETCH INFANT RECORD
   ========================= */

$infantSnapshot = $db->collection('infant_rec')->document($infantId)->snapshot();

if (!$infantSnapshot->exists()) {
    die("Infant record not found.");
}

$infant = $infantSnapshot->data();

/* =========================
   FETCH PARENT RECORD
   ========================= */

$parent = [];

if (!empty($infant['mother_id'])) {
    $parentSnapshot = $db->collection('parent_rec')->document($infant['mother_id'])->snapshot();
    if ($parentSnapshot->exists()) {
        $parent = $parentSnapshot->data();
    }
}

/* =========================
   DATE HANDLING
   ========================= */

$bdayTime = isset($infant['bday']) ? $infant['bday']->get()->formatAsString() : null;
$bday = $bdayTime ? strtotime($bdayTime) : null;

$marriageTime = isset($parent['marriage']) ? $parent['marriage']->get()->formatAsString() : null;
$marriage = $marriageTime ? strtotime($marriageTime) : null;

/* =========================
   WORD TEMPLATE
   ========================= */

$template = new TemplateProcessor(__DIR__ . '/../template.docx');

/* =========================
   PLACEHOLDER VALUES
   ========================= */

$template->setValue('PROVINCE', 'Nueva Ecija');
$template->setValue('CITY', 'San Leonardo');
$template->setValue('REGISTRY_NO', $infantId);

$template->setValue('BABY_FNAME', $infant['fname'] ?? '');
$template->setValue('BABY_MNAME', $infant['mname'] ?? '');
$template->setValue('BABY_LNAME', $infant['lname'] ?? '');
$template->setValue('BABY_SEX', ucfirst($infant['gender'] ?? ''));

$template->setValue('BABY_BDAY', $bday ? date('d', $bday) : '');
$template->setValue('BABY_BMONTH', $bday ? date('F', $bday) : '');
$template->setValue('BABY_BYEAR', $bday ? date('Y', $bday) : '');

$template->setValue('DELIVERY_TYPE', ucfirst($infant['delivery'] ?? ''));
$template->setValue('MULTI_CHILD', $infant['type_multi'] ?? '');
$template->setValue('BIRTH_ORDER', $infant['birth_order'] ?? '');
$template->setValue('WEIGHT', $infant['weight'] ?? '');

$template->setValue('M_FNAME', $parent['m_fname'] ?? '');
$template->setValue('M_MNAME', $parent['m_mname'] ?? '');
$template->setValue('M_LNAME', $parent['m_lname'] ?? '');
$template->setValue('M_CITIZENSHIP', $parent['m_citizenship'] ?? '');
$template->setValue('M_RELIGION', $parent['m_religion'] ?? '');
$template->setValue('C_ALIVE', $parent['child_count_all'] ?? '');
$template->setValue('C_UVING', $parent['child_count_alive'] ?? '');
$template->setValue('C_DEAD', $parent['child_count_dead'] ?? '');
$template->setValue('M_OCCUPATION', $parent['m_occupation'] ?? '');
$template->setValue('M_AGE', $parent['m_age'] ?? '');
$template->setValue('M_ADDRESS', $parent['m_address'] ?? '');

$template->setValue('F_FNAME', $parent['f_fname'] ?? '');
$template->setValue('F_MNAME', $parent['f_mname'] ?? '');
$template->setValue('F_LNAME', $parent['f_lname'] ?? '');
$template->setValue('F_CITIZENSHIP', $parent['f_citizenship'] ?? '');
$template->setValue('F_RELIGION', $parent['f_religion'] ?? '');
$template->setValue('F_OCCUPATION', $parent['f_occupation'] ?? '');
$template->setValue('F_AGE', $parent['f_age'] ?? '');
$template->setValue('F_ADDRESS', $parent['f_address'] ?? '');

$template->setValue('MARRIAGE_DATE', $marriage ? date('F d, Y', $marriage) : '');
$template->setValue('MARRY_PLACE', $parent['marriage_place'] ?? '');

$template->setValue('OB_NAME', $infant['ob_list'] ?? '');
$template->setValue('TIME_OF_BIRTH', $bday ? date('h:i A', $bday) : '');
$template->setValue('DATE_TODAY', date('F d, Y'));

$template->setValue(
    'FATHER',
    trim(($parent['f_fname'] ?? '') . ' ' .
         ($parent['f_mname'] ?? '') . ' ' .
         ($parent['f_lname'] ?? ''))
);

/* =========================
   OUTPUT FILE
   ========================= */

$output = 'BirthCertificate.docx';
$template->saveAs($output);

header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Disposition: attachment; filename=\"$output\"");
readfile($output);
unlink($output);
exit;
