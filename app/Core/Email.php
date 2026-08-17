<?php

namespace App\Core;

use \App\Core\Config;
use \PHPMailer\PHPMailer\PHPMailer;
use \PHPMailer\PHPMailer\Exception;

class Email
{
    /** @var array */
    protected $data;

    /** @var Config */
    protected $config;

    /** @var PHPMailer */
    protected $mail;

    public function __construct(?string $account = null)
    {
        $this->config = Config::get("mail");

        $config = $account
            ? $this->config["accounts"][$account]
            : $this->config["accounts"][$this->config["default"]];

        $this->mail = new PHPMailer(true);

        $this->data = [];

        // Setup
        $this->mail->isSMTP();
        $this->mail->setLanguage($config["option"]["lang"]);
        $this->mail->isHTML($config["option"]["html"]);
        $this->mail->SMTPAuth = $config["option"]["auth"];
        $this->mail->CharSet = $config["option"]["charset"];

        if ($config["option"]["debug"]) {
            $this->mail->SMTPDebug = $config["option"]["debug"];
            $this->data["debug_die"] = $config["option"]["debug_die"];
        }

        //Auth
        $this->mail->Host = $config["smtp"]["host"];
        $this->mail->Port = $config["smtp"]["port"];
        $this->mail->Username = $config["smtp"]["user"];
        $this->mail->Password = $config["smtp"]["pass"];
        $this->mail->SMTPSecure = $config["smtp"]["secure"];

        // Sender
        $this->mail->Sender = $config["sender"]["email"];

        $this->data["fromEmail"] = $config["sender"]["email"];
        $this->data["fromName"]  = $config["sender"]["name"];

    }

    public function setDebug($debug, bool $die = false)
    {
        $this->mail->SMTPDebug = $debug;
        if ($die) {
            $this->data["debug_die"] = $die;
        }
        return $this;
    }

    public function setSubject(string $subject): Email
    {
        $this->data["subject"] = $subject;
        return $this;
    }

    public function subject(string $subject): Email
    {
        return $this->setSubject($subject);
    }

    public function setSender(string $email, ?string $name = null): Email
    {
        $this->mail->Sender = $email;
        return $this;
    }

    public function setMessage(string $message): Email
    {
        $this->data["message"] = $message;
        return $this;
    }

    public function setBody(string $message): Email
    {
        return $this->setMessage($message);
    }

    public function message(string $message): Email
    {
        $this->data["message"] = $message;
        return $this;
    }

    public function body(string $message): Email
    {
        return $this->setMessage($message);
    }

    public function address(string $email, ?string $name = null): Email
    {

        if ($email) {

            $email = (array)$email;
            $name = (array)$name;

            foreach ($email as $k => $m) {

                if (!is_email($m)) {

                    die("O e-mail {$m} é inválido");

                } else {

                    $this->data["address"][$m] = $name[$k] ?? null;

                }
            }
        }
        return $this;
    }

    public function to(string $email, ?string $name = null): Email
    {
        return $this->address($email, $name);
    }

    public function cc(string $email, ?string $name = null): Email
    {

        if ($email) {

            $email = (array)$email;
            $name = (array)$name;

            foreach ($email as $k => $m) {

                if (!is_email($m)) {
                    die("O e-mail {$m} é inválido");
                } else {
                    $this->data["cc"][$m] = $name[$k] ?? null;
                }
            }
        }
        return $this;
    }

    public function bcc(string $email, ?string $name = null): Email
    {
        if ($email) {

            $email = (array)$email;
            $name = (array)$name;

            foreach ($email as $k => $m) {

                if (!is_email($m)) {
                    die("O e-mail {$m} é inválido");
                } else {
                    $this->data["bcc"][$m] = $name[$k] ?? null;
                }
            }
        }
        return $this;
    }

    public function attach(string $filePath, string $fileName): Email
    {
        $this->data["attach"][$filePath] = $fileName;
        return $this;
    }

    public function setFrom(string $email, ?string $name = null): Email
    {
        $this->data["fromEmail"] = $email;
        if ($name) {
            $this->data["fromName"] = $name;
        }
        return $this;
    }

    public function setFromName(string $name): Email
    {
        $this->data["fromName"] = $name;
        return $this;
    }

    public function reply(string $email, ?string $name = null): Email
    {
        $this->data["replyEmail"] = $email;
        if ($name) {
            $this->data["replyName"] = $name;
        }
        return $this;
    }

    public function send(
        ?string $subject = null,
        ?string $body = null,
        ?string $toEmail = null,
        ?string $toName = null
    ): bool
    {

        if (empty($this->data)) {
            return false;
        }

        if ($toEmail) {
            $this->address($toEmail, $toName);
        }

        if (empty($this->data["address"])) {
            echo "E-mail de destinatário inválido";
            return false;
        }

        try {

            if ($subject) {
                $this->mail->Subject = $subject;
            } else {
                $this->mail->Subject = $this->data["subject"];
            }

            if ($body) {
                $this->mail->Body = $body;
            } else {
                $this->mail->Body = $this->data["message"];
            }

            foreach ($this->data["address"] as $email => $name) {
                $this->mail->addAddress($email, $name);
            }

            if (!empty($this->data["cc"])) {
                foreach ($this->data["cc"] as $email => $name) {
                    $this->mail->addCC($email, $name);
                }
            }

            if (!empty($this->data["bcc"])) {
                foreach ($this->data["bcc"] as $email => $name) {
                    $this->mail->addBCC($email, $name);
                }
            }

            $this->mail->setFrom($this->data["fromEmail"], $this->data["fromName"]);

            if (!empty($this->data["attach"])) {
                foreach ($this->data["attach"] as $path => $name) {
                    $this->mail->addAttachment($path, $name);
                }
            }

            if (!empty($this->data["replyEmail"])) {

                $this->mail->AddReplyTo($this->data["replyEmail"], $this->data["replyName"]);
            }

            $this->mail->send();

            $this->mail->ClearAllRecipients();
            $this->mail->ClearAttachments();

            return true;

        } catch (Exception $e) {

            if ($this->data["debug_die"]) {
                die();
            }

            echo $e->getMessage();
            return false;
        }

    }

    public function mail(): PHPMailer
    {
        return $this->mail;
    }

    public function template(
        string $text,
        bool $center = false,
        bool $date = true,
        ?string $extra = null
    )
    {

        $body = "<html>
                    <body style=\"color:#000;font-size:16px;line-height:24px;font-family:Arial;\">";

        $body .= $center ? "<center>" : "";

        $body .= $text;

        $body .= $date ? "<br><br><small>Enviado em: " . date("d/m/Y \à\s H:i") . ($extra ? " - " . $extra : "") : "";

        $body .= $center ? "</center>" : "";

        $body .= "</body>
                </html>";

        return $body;
    }

}
