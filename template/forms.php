<?php

use Gann\CDF\Robotics\Requisition\RequisitionURLs;

require_once __DIR__ . '/../vendor/autoload.php';

$request = json_decode(file_get_contents('php://input'), true);
if (empty($request)) {
    $request = $_REQUEST;
}
$form = null;
if (false === empty($request['form'])) {
    $form = $request['form'];
    unset($request['form']);
}
switch ($form) {
    case 'requisition-urls':
        RequisitionURLs::process($request);
        break;
    default:
        $request = var_export($request, true);
        echo <<<EOT
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Form Error</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css"
          integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
</head>
<body>
    <div class="container">
        <!-- TODO more detail, next steps -->
        <div class="jumbotron">
            <h1>Form Error</h1>
        </div>
        <p>Could not process the requested form.</p>
        <pre>{$request}</pre>
    </div>
</body>
</html>
EOT;

}
