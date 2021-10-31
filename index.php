<?php
use DJ\SimplePager;

if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    die("Only local user allowed!");
}

require_once('libs/SimplePager.php');
require_once('libs/Parsedown.php');
require_once('libs/TinyHtmlMinifier.php');

$minifier = new TinyHtmlMinifier(['collapse_whitespace' => true, 'disable_comments' => true]);

$parsedown = new Parsedown();
$parsedown->setSafeMode(true);
$parsedown->setMarkupEscaped(true);
$parsedown->setBreaksEnabled(true);

$pager = new SimplePager($parsedown, [
    'template' => 'templates/dashboard/template.html',
    'use_cache' => true
], [], $minifier);

$code = "";
try {
    if ($pager->applyRoute($_SERVER['REQUEST_URI'])) {
        $code = $pager->getPageCode();
    } else {
        $code = "<h2 style='color: #cf222e'>Route not found!</h2>";
    }
} catch (Exception $ex) {
    $message = $ex->getMessage();
    $trace = $ex->getTraceAsString();
    $code  = "<h2 style='color: #cf222e'>Unexpected error: $message<br />$trace!</h2>";
}

die($code);



