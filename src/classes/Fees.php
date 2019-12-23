<?php
declare(strict_types=1);

namespace src\classes;

class Fees
{
	protected const CONVERSION_RATES = [
		'EUR' => 1,
		'USD' => 1.1497,
		'JPY' => 129.53,
	];

    protected const CASH_IN_FEE = [
    	'natural' =>  0.03, // percents
        'legal' => 0.03, // percents
    ]; 

    protected const MAX_CASH_IN_FEE = [
    	'natural' =>  5, // EUR
        'legal' => 5, // EUR
    ];

    protected const CASH_OUT_FEE = [
    	'natural' =>  0.3, // percents
        'legal' => 0.3, // percents
    ]; 

    protected const MIN_CASH_OUT_FEE = [
    	'natural' => 0.5, // EUR
        'legal' => 0.5, // EUR
    ];

    protected const WEEKLY_FREE_OF_CHARGE = [
    	'natural' => 1000, // EUR
    	'legal' => NULL, // EUR
    ];

    protected const DISCOUNT_APPLIED_FOR = 3;

    protected $final_fees = [];
    protected $user_fees_count = [];
    protected $user_fees_amount = [];
    protected $csv_file = '';

    /**
	 * @param string $file_name
	 */
    public function __construct($file_name)
    {
    	$this->csv_file = $file_name;
    }

    /**
	 * @return iterable, array with calculated fees
	 */
    public function calc_fees() : iterable
    {
    	$data = self::parse_csv();

    	foreach ($data as $operation) {

    		list($date, $user_id, $user_type, $operation_type, $amount, $currency) = $operation;

    		switch ($operation_type) {
    			case 'cash_in':
    				$this->calc_cash_in($date, $user_id, $user_type, $operation_type, $amount, $currency);
    				break;

    			case 'cash_out':
    				$this->calc_cash_out($date, $user_id, $user_type, $operation_type, $amount, $currency);
    				break;
    			
    			default:
    				throw new \Exception('Not a valid operation type!');
    				break;
    		}
    	}

    	return $this->final_fees;
    }

    /**
	 * @return iterable, array with parsed csv
	 */
    private function parse_csv() : iterable
    {
    	$csv = array_map('str_getcsv', file($this->csv_file));
    	
    	return $csv;
    }

    private function calc_cash_out($date_str, $user_id, $user_type, $operation_type, $amount, $currency)
    {
    	$date = new \DateTime($date_str);
    	$week = $date->format("oW");

    	print($week . PHP_EOL);

		$amount_in_eur = $this->convert_to_eur($amount, $currency);

    	
    	if ($this->default_fee($user_id, $user_type, $week)) {

			$fee = ceil($amount * self::CASH_OUT_FEE[$user_type]) / 100;

    		$fee_in_eur = $this->convert_to_eur($fee, $currency);

    		$final_fee = $fee_in_eur > self::MIN_CASH_OUT_FEE[$user_type] ? $fee : self::MIN_CASH_OUT_FEE[$user_type];

			$this->final_fees[] = $final_fee;

			$this->increase_user_amount($user_id, $week, $amount_in_eur);
		} else { 

			if (isset($this->user_fees_amount[$user_id][$week]) && $this->user_fees_amount[$user_id][$week] >= self::WEEKLY_FREE_OF_CHARGE[$user_type]) {
				print('2' . PHP_EOL);
				
				$new_amount = $amount - self::WEEKLY_FREE_OF_CHARGE[$user_type];
			}


			

			$fee = ceil($new_amount * self::CASH_OUT_FEE[$user_type]) / 100;

    		$fee_in_eur = $this->convert_to_eur($fee, $currency);

    		$final_fee = $fee_in_eur > self::MIN_CASH_OUT_FEE[$user_type] ? $fee : self::MIN_CASH_OUT_FEE[$user_type];
			
			$this->final_fees[] = $final_fee;
			
			$this->increase_user_amount($user_id, $week, $amount_in_eur);
		}
    }

    private function increase_user_amount($user_id, $week, $amount_in_eur)
    {
    	if (isset($this->user_fees_count[$user_id][$week])) {
			$this->user_fees_count[$user_id][$week] += 1;
			$this->user_fees_amount[$user_id][$week] += $amount_in_eur;    		
    	} else {
   			// $this->user_fees_count[$user_id] = [];
			// $this->user_fees_amount[$user_id] = [];

    		$this->user_fees_count[$user_id][$week] = 1;
			$this->user_fees_amount[$user_id][$week] = $amount_in_eur;
    	}
    }


	private function default_fee($user_id, $user_type, $week)
	{
		$ret = FALSE;

		if ('legal' === $user_type) {
			$ret = TRUE;
		} elseif ( isset($this->user_fees_count[$user_id][$week]) && $this->user_fees_count[$user_id][$week] > self::DISCOUNT_APPLIED_FOR) {
			print('1' . PHP_EOL);
			$ret = TRUE;
		} else {
			print('3' . PHP_EOL);
			$ret = FALSE;
		}

		return $ret;
	}

    private function calc_cash_in($date, $user_id, $user_type, $operation_type, $amount, $currency)
    {
    	$fee = ceil($amount * self::CASH_IN_FEE[$user_type]) / 100;

    	$fee_in_eur = $this->convert_to_eur($fee, $currency);

    	$this->final_fees[] = $fee_in_eur < self::MAX_CASH_IN_FEE[$user_type] ? $fee : self::MAX_CASH_IN_FEE[$user_type];
    }

    private function convert_to_eur($fee_amount, $currency)
    {
    	return $fee_amount / self::CONVERSION_RATES[$currency];
    }

/*
Natural Persons

Default commission fee - 0.3% from cash out amount.

1000.00 EUR per week (from monday to sunday) is free of charge.

If total cash out amount is exceeded - commission is calculated only from exceeded amount (that is, for 1000.00 EUR there is still no commission fee).

This discount is applied only for first 3 cash out operations per week for each user - for forth and other operations commission is calculated by default rules (0.3%) - rule about 1000 EUR is applied only for first three cash out operations.


Legal persons

Commission fee - 0.3% from amount, but not less than 0.50 EUR for operation.
*/

}