<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$mail = new PHPMailer(true);

try {
    //Server settings
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;  //Enable verbose debug output
    $mail->isSMTP();                                            //Send using SMTP
    $mail->Host       = 'helpdesk.electrocreditosdelcauca.com';                     //Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
    $mail->Username   = 'mesadeayuda@helpdesk.electrocreditosdelcauca.com';                     //SMTP username
    $mail->Password   = 'v2ecNVPdgNI4';                               //SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    

    //Recipients
    $mail->setFrom('mesadeayuda@helpdesk.electrocreditosdelcauca.com', 'Mailer Test');
    $mail->addAddress('mesadeayuda@helpdesk.electrocreditosdelcauca.com');     //Add a recipient (sending to self)

    //Content
    $mail->isHTML(true);                                  //Set email format to HTML
    $mail->Subject = 'SMTP Test';
    $mail->Body    = 'This is the HTML message body <b>in bold!</b>';

    $mail->send();
    echo 'Message has been sent';
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
