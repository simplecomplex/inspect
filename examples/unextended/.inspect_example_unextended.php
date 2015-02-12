<?php

// Simple example of using Inspect directly;
// not extended by a class adapting to context.

/*
 * INSTRUCTIONS
 * 1) Create 'lib' directory in document root.
 * 2) In commandline, execute: composer install
 * 3) Copy this file to the site's document root dir, and rename it removing the leading dot.
 * 4) Go to http://yoursite/inspect_example_unextended.php
 * 5) Remove the copied file from document root when done testing.
 */

// Get all errors.
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

include 'lib/vendor/SimpleComplex/inspect/src/Inspect.php';

use SimpleComplex\Inspect\Inspect;

// Inspect globals to screen, filtered for the _SERVERS array,
// because the existance of this script might be forgotten,
// and _SERVERS may well reveal sensitive environment information.
$html = array(
  'head' => '<!DOCTYPE html>
<html lang="en" dir="ltr">
<link type="text/css" rel="stylesheet" href="/lib/vendor/SimpleComplex/inspect/src/css/inspect_format_output.css" media="all" />
<!-- Include jQuery to get expansible/collapsible object/array listings. -->
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js" ></script>
<script src="/lib/vendor/SimpleComplex/inspect/src/js/inspect.js"></script>
<script src="/lib/vendor/SimpleComplex/inspect/src/js/inspect_format_output.js"></script>
<head>
<meta charset="utf-8" />
<title>Inspect example unextended</title>
</head>
<body>',
  'foot' => '
</body>'
);

echo $html['head'];

$dateTime = new DateTime();
echo Inspect::get($GLOBALS, array('message' => 'globals', 'filter' => '_SERVER'));

echo $html['foot'];
