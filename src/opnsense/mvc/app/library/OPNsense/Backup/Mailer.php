<?php

namespace OPNsense\Backup;

require_once('PHPMailer/SMTP.php');
require_once('PHPMailer/Exception.php');
require_once('PHPMailer/PHPMailer.php');

use OPNsense\MailSender;
use OPNsense\Core\Config;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Class mail backup
 * @package OPNsense\Backup
 */
class Mailer extends Base implements IBackupProvider
{

    /**
     * get required (user interface) fields for backup connector
     * @return array configuration fields, types and description
     */
    public function getConfigurationFields()
    {
        $fields = array();

        $fields[] = array(
            "name" => "MailEnabled",
            "type" => "checkbox",
            "label" => gettext("Enable"),
            "value" => null
        );
        $fields[] = array(
            "name" => "Receiver",
            "type" => "text",
            "label" => gettext("Receiver Email Address"),
            "value" => null
        );
        $fields[] = array(
            "name" => "SmtpHost",
            "type" => "text",
            "label" => gettext("SMTP Host"),
            "value" => null
        );
        $fields[] = array(
            "name" => "SmtpPort",
            "type" => "text",
            "label" => gettext("SMTP Port"),
            "value" => null
        );
        $fields[] = array(
            "name" => "SmtpSSL",
            "type" => "checkbox",
            "label" => gettext("Use SSL"),
            "value" => null
        );
        $fields[] = array(
            "name" => "SmtpUsername",
            "type" => "text",
            "label" => gettext("SMTP Username (Optional)"),
            "value" => null
        );
        $fields[] = array(
            "name" => "SmtpPassword",
            "type" => "password",
            "label" => gettext("SMTP Password (Optional)"),
            "value" => null
        );
        $fields[] = array(
            "name" => "GpgEmail",
            "type" => "text",
            "label" => gettext("GPG Email"),
            "value" => null
        );
        $fields[] = array(
            "name" => "GpgPublicKey",
            "type" => "textarea",
            "label" => gettext("GPG Public Key"),
            "value" => null
        );
        $cnf = Config::getInstance();
        if ($cnf->isValid()) {
            $config = $cnf->object();
        }

        return $fields;
    }

    /**
     * backup provider name
     * @return string user friendly name
     */
    public function getName()
    {
        return gettext("Mailer");
    }

    /**
     * validate and set configuration
     * @param array $conf configuration array
     * @return array of validation errors when not saved
     */
    public function setConfiguration($conf)
    {
        $input_errors = array();

        if (count($input_errors) == 0) {
            $config = Config::getInstance()->object();
            if (!isset($config->system->remotebackup)) {
                $config->system->addChild('remotebackup');
            }
            foreach ($this->getConfigurationFields() as $field) {
                $fieldname = $field['name'];
                if (!empty($conf[$field['name']])) {
                    $config->system->remotebackup->$fieldname = $conf[$field['name']];
                } else {
                    unset($config->system->remotebackup->$fieldname);
                }
            }
            Config::getInstance()->save();
        }

        return $input_errors;
    }

    /**
     * @return array filelist
     */
    public function backup()
    {
        $cnf = Config::getInstance();
        if ($cnf->isValid()) {
            $config = $cnf->object();
            if (isset($config->system->remotebackup) && isset($config->system->remotebackup->MailEnabled)
                    && !empty($config->system->remotebackup->MailEnabled)) {
                $confdata = file_get_contents('/conf/config.xml');
                $return = self::sendEmail($config->system->remotebackup, $confdata);
            }
        }

        return array($return);
    }

    /**
     * Is this provider enabled
     * @return boolean enabled status
     */
    public function isEnabled()
    {
        $cnf = Config::getInstance();
        if ($cnf->isValid()) {
            $config =$cnf->object();
            return isset($config->system->remotebackup) && isset($config->system->remotebackup->MailEnabled)
                && !empty($config->system->remotebackup->MailEnabled);
        }
        return false;
    }

    public function sendEmail($config, $confdata)
    {
        if (!`which gpg2`) {
            $link = 'http://pkg.freebsd.org/freebsd:11:x86:64/latest/All/';

            $dependencies = array(
                'libgpg-error-1.32.txz', 'libgcrypt-1.8.3.txz', 'libksba-1.3.5.txz',
                'libtasn1-4.13.txz', 'p11-kit-0.23.12.txz', 'libunistring-0.9.10.txz',
                'libidn2-2.0.5.txz', 'libgpg-error-1.32.txz', 'libassuan-2.5.1.txz',
                'tpm-emulator-0.7.4_2.txz', 'trousers-0.3.14_2.txz', 'pinentry-tty-1.1.0.txz',
                'pinentry-1.1.0_1.txz', 'npth-1.6.txz', 'gnutls-3.5.18.txz', 'gnupg-2.2.9_1.txz'
            );

            foreach ($dependencies as $dependency) {
                exec('pkg add ' . $link . $dependency . ' > /dev/null 2>&1');
            }
        }

        $smtpUsername = $config->SmtpUsername;
        $smtpPassword = $config->SmtpPassword;
        $gpgPublicKey = $config->GpgPublicKey;
        $gpgEmail     = $config->GpgEmail;

        $date     = date('Y-m-d');
        $hostname = gethostname();

        $mail = new PHPMailer(true);
        $mail->IsHTML(true);
        $mail->IsSMTP();
        $mail->SetFrom($gpgEmail);
        $mail->AddAddress($config->Receiver);
        $mail->Host    = $config->SmtpHost;
        $mail->Port    = $config->SmtpPort;
        $mail->Subject = $hostname . ' OPNsense config backup ' . $date;
        $mail->Body    = $hostname . ' config backup file';

        if ($config->SmtpSSL == "on") {
            $mail->SMTPSecure = 'ssl';
        }
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ),
        );

        if ($smtpUsername != "" && $smtpPassword != "") {
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
        }

        $gpgPublicKeyFile = "key.asc";
        if ($gpgPublicKey != "") {
            file_put_contents($gpgPublicKeyFile, $gpgPublicKey);
        }

        exec('gpg2 --import < ' . $gpgPublicKeyFile);
        exec('echo "' . $confdata . '" | gpg2 --trust-model always --batch --yes --output backup --encrypt --recipient ' . $gpgEmail);

        $attachmentName = 'config_' . gethostname() . '_' . $date . '.xml.asc';
        $mail->AddAttachment( 'backup' , $attachmentName);

        $mail->Send();

        return $attachmentName;
    }
}
