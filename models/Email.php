<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once('../config/conexion.php');
require_once('../models/Ticket.php');
require_once('../models/Documento.php');

class Email extends PHPMailer
{
    protected $gcorreo;
    protected $gpass;

    public function __construct($exceptions = null)
    {
        parent::__construct($exceptions);
        $this->gcorreo = $_ENV['EMAIL_USER'];
        $this->gpass = $_ENV['EMAIL_PASS'];
    }

    public function recuperar_contrasena($usu_correo, $link)
    {
        $this->isSMTP();
        $this->Host = 'mail.electrocreditosdelcauca.com';
        $this->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $this->Port = 465;
        $this->SMTPAuth = true;
        $this->Username = $this->gcorreo;
        $this->Password = trim($this->gpass); // Limpiar espacios accidentales
        $this->From = $this->gcorreo;
        $this->SMTPSecure = 'ssl';
        $this->FromName = 'Mesa de Ayuda - Electrocreditos del Cauca';
        $this->CharSet = 'UTF-8';
        $this->addAddress($usu_correo);
        $this->WordWrap = 50;
        $this->isHTML(true);
        $this->Subject = 'Recuperar Contraseña';
        $cuerpo = file_get_contents('../public/recuperarcontrasena.html');

        $cuerpo = str_replace('[Link de recuperacion]', $link, $cuerpo);

        $this->Body = $cuerpo;

        return $this->send();
    }
}
