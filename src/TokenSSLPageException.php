<?php

namespace TokenSSL;

use Exception;
use Throwable;
use TokenSSL\Common\TwigUtils;

class TokenSSLPageException extends Exception
{

    public function __construct($title = "", $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $twig = TwigUtils::initTwig();
        die($twig->render('pageException.html.twig', ['title' => $title, 'message' => $message, 'code' => $code]));
    }
}
