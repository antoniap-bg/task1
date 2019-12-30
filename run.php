<?php
declare(strict_types=1);

require_once __DIR__ . "/src/classes/Fees.php";

use Task\Fees;

$output = '';

if (!isset($argv[1])) {
    $output = "It is required to provide .csv file path!" . PHP_EOL;
} else {
    $fees = new Fees($argv[1]);
    $calculated_fees = $fees->calcFees();

    foreach ($calculated_fees as $fee) {
        $output .= $fee . PHP_EOL;
    }
}

fwrite(STDOUT, print_r($output, true));
