<?php
declare(strict_types=1);

require_once __DIR__ . "/src/classes/Fees.php";

print('Start' . PHP_EOL);
$fees = new src\classes\Fees('input.csv');
$calculated_fees = $fees->calc_fees();

fwrite(STDOUT, print_r($calculated_fees, true));