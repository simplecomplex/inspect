<?php

// Simple example of using Inspect directly;
// not extended by a class adapting to context.

/*
 * INSTRUCTIONS
 * 1) Copy this file to the site's document root dir, and rename it removing the leading dot.
 * 2) Go to http://yoursite/inspect_example_unextended.php
 * 3) Remove the copied file from document root when done testing.
 */

// Get all errors.
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

// Include SimpleComplex\Inspect\Inspect.php via Composer's autoloader,
// or include it 'manually'.
require 'lib/vendor/autoload.php';
//include 'lib/vendor/SimpleComplex/inspect/src/Inspect.php';

use SimpleComplex\Inspect\Inspect;

$html = array(
  'head' => '<!DOCTYPE html>
<html lang="en" dir="ltr">
<link type="text/css" rel="stylesheet" href="/lib/vendor/SimpleComplex/inspect/css/inspect_format_output.css" media="all" />
<!-- Include jQuery to get expansible/collapsible object/array listings. -->
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js" ></script>
<script src="/lib/vendor/SimpleComplex/inspect/js/inspect.js"></script>
<script src="/lib/vendor/SimpleComplex/inspect/js/inspect_format_output.js"></script>
<head>
<meta charset="utf-8" />
<title>Inspect example unextended</title>
</head>
<body>
<h3>Example of using Inspect directly, and getting the output to screen</h3>
<h4>Extend to accommodate to the context</h4>
<p></p>
<h4>Output targets \'log\', \'file\', \'frontend log\' and \'get\'</h4>
<p>\'get\'ting should be illegal in production</p>

<p>
',
  'foot' => '
</body>'
);

echo $html['head'];

// Inspect globals to screen, filtered for the _SERVERS array,
// because the existance of this script might be forgotten,
// and _SERVERS may well reveal sensitive environment information.
$dateTime = new DateTime();
echo Inspect::get($GLOBALS, array('message' => 'globals', 'filter' => '_SERVER'));

// Trace an exception, to screen.
try {
  $date = new DateTime('Obviously not a parsable date');
}
catch (Exception $xc) {
  echo Inspect::traceGet($xc, 'Stupid but deliberate error');
}

echo $html['foot'];

