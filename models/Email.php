<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;


require_once('../config/conexion.php');
require_once('../models/Ticket.php');
require_once('../models/Documento.php');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class Email extends PHPMailer
{
    protected $gcorreo = '';
    protected $gpass = ''; // <--- Pon aquí la contraseña de la nueva cuenta

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
        $this->SMTPDebug = 2;
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
