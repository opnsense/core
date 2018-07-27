#!/usr/local/bin/php
<?php

require_once('PHPMailer/PHPMailer.php');
require_once('PHPMailer/Exception.php');
require_once('PHPMailer/SMTP.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$config       = fopen("/usr/local/etc/backupmailer/backupmailer.conf", "r");
$receiver     = fgets($config);
$smtpHost     = fgets($config);
$smtpUsername = fgets($config);
$smtpPassword = fgets($config);
$gpgEmail     = fgets($config);

$gpgPublicKey = '/usr/local/etc/backupmailer/public_key.gpg';

$files  = scandir('/conf/backup', SCANDIR_SORT_DESCENDING);
$backup_config = $files[0];

$date = date('Y-m-d/h:i:sa');

$mail = new PHPMailer(true);

$mail->IsHTML(true);
$mail->IsSMTP();
$mail->SMTPAuth   = true;
$mail->SMTPSecure = 'ssl';
$mail->Host       = $smtpHost;
$mail->Port       = 465;

$mail->Username = $smtpUsername;
$mail->Password = $smtpPassword;

$mail->SetFrom($smtpUsername);
$mail->AddAddress($receiver);
$mail->Subject = 'OPNsense config backup ' . $date;
$mail->Body    = 'Config backup file';

exec('gpg --import ' . $gpgPublicKey);
exec('gpg --output backup --encrypt --recipient ' . $gpgEmail . ' ' . $backup_config);

$mail->AddAttachment( 'backup' , 'config_' . $date . '.xml.asc' );

$mail->Send();
