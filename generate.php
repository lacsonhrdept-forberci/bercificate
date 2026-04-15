<?php
require 'vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

$template = new TemplateProcessor('template.docx');

$template->setValue('BABY_FNAME', $_POST['baby_fname'] ?? '');
$template->setValue('BABY_LNAME', $_POST['baby_lname'] ?? '');
$template->setValue('M_FNAME', $_POST['m_fname'] ?? '');
$template->setValue('F_FNAME', $_POST['f_fname'] ?? '');
$template->setValue('DATE_TODAY', date('F d, Y'));

$output = 'BirthCertificate.docx';
$template->saveAs($output);

header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
header("Content-Disposition: attachment; filename=\"$output\"");
readfile($output);
unlink($output);
exit;