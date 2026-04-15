<?php
require 'vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

$template = new TemplateProcessor('template.docx');

// Helper to parse the human-readable timestamp format provided (e.g., "Month Day, Year at Time")
$parseTimestamp = function($ts) {
    if (empty($ts)) return null;
    return strtotime(str_replace(' at ', ' ', $ts));
};

$bdayTime = $parseTimestamp($_POST['bday'] ?? '');
$marriageTime = $parseTimestamp($_POST['marriage'] ?? '');

// Mapping variables to Template Placeholders
$template->setValue('PROVINCE', $_POST['province'] ?? '');
$template->setValue('CITY', $_POST['city'] ?? '');
$template->setValue('REGISTRY_NO', $_POST['registry_no'] ?? '');
$template->setValue('BABY FNAME', $_POST['fname'] ?? '');
$template->setValue('BABY MNAME', $_POST['mname'] ?? '');
$template->setValue('BABY_LNAME', $_POST['lname'] ?? '');
$template->setValue('BABY_SEX', $_POST['gender'] ?? '');
$template->setValue('BABY_BDAY', $bdayTime ? date('d', $bdayTime) : '');
$template->setValue('BABY_BMONTH', $bdayTime ? date('F', $bdayTime) : '');
$template->setValue('BABY_BYEAR', $bdayTime ? date('Y', $bdayTime) : '');
$template->setValue('DELIVERY_TYPE', $_POST['delivery'] ?? '');
$template->setValue('MULTI_CHILD', $_POST['type_multi'] ?? '');
$template->setValue('BIRTH-ORDER', $_POST['birth_order'] ?? '');
$template->setValue('WEIGHT', $_POST['weight'] ?? '');
$template->setValue('M_FNAME', $_POST['m_fname'] ?? '');
$template->setValue('M_MNAME', $_POST['m_mname'] ?? '');
$template->setValue('M_LNAME', $_POST['m_lname'] ?? '');
$template->setValue('M_CITIZENSHIP', $_POST['m_citizenship'] ?? '');
$template->setValue('M_RELIGION', $_POST['m_religion'] ?? '');
$template->setValue('C_ALIVE', $_POST['child_count_all'] ?? '');
$template->setValue('C_UVING', $_POST['child_count_alive'] ?? '');
$template->setValue('C_DEAD', $_POST['child_count_dead'] ?? '');
$template->setValue('M_OCCUPATION', $_POST['m_occupation'] ?? '');
$template->setValue('M_AGE', $_POST['m_age'] ?? '');
$template->setValue('M_ADDRESS', $_POST['m_address'] ?? '');
$template->setValue('F_FNAME', $_POST['f_fname'] ?? '');
$template->setValue('F_MNAME', $_POST['f_mname'] ?? '');
$template->setValue('F_LNAME', $_POST['f_lname'] ?? '');
$template->setValue('F_CITIZENSHIP', $_POST['f_citizenship'] ?? '');
$template->setValue('F_RELIGION', $_POST['f_religion'] ?? '');
$template->setValue('F_OCCUPATION', $_POST['f_occupation'] ?? '');
$template->setValue('F_AGE', $_POST['f_age'] ?? '');
$template->setValue('F_ADDRESS', $_POST['f_address'] ?? '');
$template->setValue('MARRIAGE DATE', $marriageTime ? date('F d, Y', $marriageTime) : '');
$template->setValue('MARRY PLACE', $_POST['marriage_place'] ?? '');
$template->setValue('OB_NAME', $_POST['ob_name'] ?? '');
$template->setValue('TIME_OF_BIRTH', $bdayTime ? date('h:i A', $bdayTime) : '');
$template->setValue('DATE_TODAY', date('F d, Y'));
$template->setValue('FATHER', trim(($_POST['f_fname'] ?? '') . ' ' . ($_POST['f_mname'] ?? '') . ' ' . ($_POST['f_lname'] ?? '')));

$output = 'BirthCertificate.docx';
$template->saveAs($output);

header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Disposition: attachment; filename=\"$output\"");
readfile($output);
unlink($output);
exit;
