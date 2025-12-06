<?php
require_once dirname(__DIR__) . '/app/core/bootstrap.php';

$route = yojaka_route();
$response = yojaka_dispatch($route);

echo $response;
