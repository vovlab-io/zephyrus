<?php

use Zephyrus\Security\ContentSecurityPolicy;
use Zephyrus\Security\Uploaders\FileUpload;
use Zephyrus\Network\Request;

/**
 * Redirect user to specified URL. Throws an HTTP "303 See Other" header
 * instead of the default 301. This indicates, more precisely, that the
 * response if elsewhere.
 *
 * @param string $url
 */
function redirect($url)
{
    header('HTTP/1.1 303 See Other');
    header('Location: ' . $url);
    exit();
}

/**
 * Basic filtering to eliminate any tags and empty leading / trailing
 * characters.
 *
 * @param string $data
 * @return string
 */
function purify($data)
{
    return htmlspecialchars(trim(strip_tags($data)), ENT_QUOTES | ENT_HTML401, 'UTF-8');
}

/**
 * Securely print an email address in an HTML page without worrying about email
 * scrapper robots.
 *
 * @param string $email
 * @return string
 */
function secureEmail($email)
{
    $result = '<script type="text/javascript" nonce="' . getRequestNonce() . '">';
    $result .= 'document.write("' . str_rot13($email) . '".replace(/[a-zA-Z]/g, function(c){return String.fromCharCode((c<="Z"?90:122)>=(c=c.charCodeAt(0)+13)?c:c-26);}));';
    $result .= '</script>';
    return $result;
}

/**
 * Securely print an email address inside a mailto anchor in an HTML page
 * without worrying about email scrapper robots. If label is null, the
 * specified email will automatically be used as label.
 *
 * @param string $email
 * @param string $label
 * @return string
 */
function secureEmailAnchor($email, $label = null)
{
    $result = '<script type="text/javascript" nonce="' . getRequestNonce() . '">';
    $result .= 'document.write("<n uers=\"znvygb:' . str_rot13($email) . '\" ery=\"absbyybj\">' . str_rot13((!is_null($label)) ? $label : $email) . '</n>".replace(/[a-zA-Z]/g, function(c){return String.fromCharCode((c<="Z"?90:122)>=(c=c.charCodeAt(0)+13)?c:c-26);}));';
    $result .= '</script>';
    return $result;
}

/**
 * SSE message sending
 *
 * @param int $id
 * @param int $retry
 * @param array $data
 */
function sendMessage($id, $data, $retry = 1000) {
    echo "id: $id" . PHP_EOL;
    echo "retry: " . $retry . PHP_EOL;
    echo "data: " . json_encode($data) . PHP_EOL;
    echo PHP_EOL;
    ob_flush();
    flush();
}

/**
 * Returns a SEO compatible url based on the specified string. Be sure to check the LC_CTYPE locale setting if
 * getting any question marks in result. Run locale -a on server to see full list of supported locales.
 *
 * @param string $name
 * @return string
 */
function seoUrl($name)
{
    setlocale(LC_CTYPE, 'en_CA.utf8');
    $url = strtolower($name);
    $url = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $url);
    $url = preg_replace("/[^a-z0-9_\s-]/", "", $url);
    $url = preg_replace("/[\s-]+/", " ", $url);
    $url = trim($url);
    return preg_replace("/[\s_]/", "-", $url);
}

function getPartialMonthName($index)
{
    $partialMonths = ['jan', 'fév', 'mar', 'avr', 'mai', 'jun', 'jui', 'aoû', 'sep', 'oct', 'nov', 'déc'];
    return $partialMonths[$index];
}

function getMonthName($index)
{
    $fullMonths = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return $fullMonths[$index];
}

function getPartialWeekDayName($index)
{
    $partialDays = ['dim', 'lun', 'mar', 'mer', 'jeu', 'ven', 'sam'];
    return $partialDays[$index];
}

function getWeekDayName($index)
{
    $fullDays = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    return $fullDays[$index];
}

/**
 * @return int
 */
function maxUploadSize()
{
    return FileUpload::getServerMaxUploadSize();
}

/**
 * @return string
 */
function getRequestNonce()
{
    return ContentSecurityPolicy::getRequestNonce();
}

function getCurrentUrl()
{
    return Request::getBaseUrl() . Request::getUri();
}