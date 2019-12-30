<?php
declare(strict_types=1);

namespace Task;

class Fees
{
    // conversion rates currency to EUR
    protected const CONVERSION_RATES = [
        'EUR' => 1,
        'USD' => 1.1497,
        'JPY' => 129.53,
    ];

    // used with sprintf
    protected const CURRENCY_PRECISION = [
        'EUR' => '%0.2f',
        'USD' => '%0.2f',
        'JPY' => '%0.0f',
    ];

    // used with ceil
    protected const CURRENCY_PRECISION_POW = [
        'EUR' => 100,
        'USD' => 100,
        'JPY' => 1,
    ];

    // fee for cash_in operation type in percents
    protected const CASH_IN_FEE = [
        'natural' =>  0.03,
        'legal' => 0.03,
    ];

    // max fee for cash_in operation type in EUR
    protected const MAX_CASH_IN_FEE = [
        'natural' =>  5,
        'legal' => 5,
    ];

    // fee for cash_out operation type in percents
    protected const CASH_OUT_FEE = [
        'natural' =>  0.3,
        'legal' => 0.3,
    ];

    // min fee for cash_out operation type in EUR
    protected const MIN_CASH_OUT_FEE = [
        'natural' => 0, // no min fee
        'legal' => 0.5,
    ];

    // weekly amount free of charge for cash_out operation type in EUR
    protected const WEEKLY_AMOUNT_FREE_OF_CHARGE = [
        'natural' => 1000,
        'legal' => 0, // no weekly amount
    ];

    // weekly number of operations free of charge
    protected const OP_NUMBER_FREE_OF_CHARGE = [
        'natural' => 3,
        'legal' => 0, // no free operations
    ];

    // acceptable operation types
    protected const ACCEPTABLE_OPERATION_TYPES = [
        'cash_in',
        'cash_out',
    ];

    // calculated fees
    protected $final_fees = [];

    // temp array for users fees
    protected $user_fees = [];

    // input .csv file
    protected $csv_file = '';

    /**
     * @param string $file_name
     */
    public function __construct(string $file_name)
    {
        $this->checkCsvFile($file_name);

        $this->csv_file = $file_name;
    }

    /**
     * @param string $file_name
     * @throws \Exception
     * @return void
     */
    private function checkCsvFile($file_name) : void
    {
        if (!is_readable($file_name)) {
            throw new \Exception('The provided file is not readable!');
        }
    }

    /**
     * used for PHPUnit test
     * @param string $file_name
     * @return self
     */
    public static function fromString(string $file_name): self
    {
        return new self($file_name);
    }

    /**
     * public function for calculating fees
     * @return iterable, array with calculated fees
     * @throws ErrorException
     */
    public function calcFees() : iterable
    {
        $data = self::parseCSV();

        // the number of checked row, used when throwing an exception
        $checked_row = 0;

        foreach ($data as $operation) {
            $checked_row ++;

            self::checkLine($operation, $checked_row);

            list($date, $user_id, $user_type, $operation_type, $amount, $currency) = $operation;

            $user_id = (int) $user_id;
            $amount = (float) $amount;

            switch ($operation_type) {
                case 'cash_in':
                    $this->calcCashIn($user_type, $amount, $currency);
                    break;

                case 'cash_out':
                    $this->calcCashOut($date, $user_id, $user_type, $amount, $currency);
                    break;

                default:
                    // the code is not expected to go here as $operation_type is already checked
                    throw new \ErrorException('Not a valid operation type!');
                    break;
            }
        }

        return $this->final_fees;
    }

    /**
     * @param array $row
     * @param int $checked_row
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public static function checkLine(array $row, int $checked_row) : void
    {
        // check for all input values
        if (!isset($row[5])) {
            throw new \InvalidArgumentException(
                sprintf('Invalid values provided!')
            );
        }

        // check date format of operation date
        $dt = \DateTime::createFromFormat("Y-m-d", $row[0]);
        if ($dt === false || array_sum($dt::getLastErrors())) {
            throw new \InvalidArgumentException(
                sprintf('Invalid values for parameter "date" provided at line %s!', $checked_row)
            );
        }

        // check if user_id is int
        if (!preg_match("/^\d+$/", $row[1])) {
            throw new \InvalidArgumentException(
                sprintf('Invalid values for parameter "user_id" provided at line %s!', $checked_row)
            );
        }

        // check if user_type is valid
        if (!in_array($row[2], array_keys(self::CASH_IN_FEE))) {
            throw new \InvalidArgumentException(
                sprintf('Invalid values for parameter "user_type" provided at line %s!', $checked_row)
            );
        }

        // check if operation type is valid
        if (!in_array($row[3], self::ACCEPTABLE_OPERATION_TYPES)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid values for parameter "operation_type" provided at line %s!', $checked_row)
            );
        }

        // check if amount is numeric (int or float)
        if (!is_numeric($row[4])) {
            throw new \InvalidArgumentException(
                sprintf('Invalid values for parameter "amount" provided at line %s!', $checked_row)
            );
        }

        // check if currency is valid
        if (!in_array($row[5], array_keys(self::CURRENCY_PRECISION))) {
            throw new \InvalidArgumentException(
                sprintf('Invalid values for parameter "currency" provided at line %s!', $checked_row)
            );
        }
    }

    /**
     * parse .csv file into array
     * @return iterable, array with parsed csv data
     */
    private function parseCSV() : iterable
    {
        $csv = array_map('str_getcsv', file($this->csv_file));

        return $csv;
    }

    /**
     * calculating cash_in fee
     * the calculated value is added in the temp array $this->final_fees
     *
     * @param string $user_type natural / legal
     * @param float $amount of current operation
     * @param string $currency
     *
     * @return void
     */
    private function calcCashIn(string $user_type, float $amount, string $currency) : void
    {
        $fee = ceil($amount * self::CASH_IN_FEE[$user_type]) / 100;
        $fee_in_eur = $this->convertToEur($fee, $currency);

        $max_cash_in_fee = $this->convertToCurrency(self::MAX_CASH_IN_FEE[$user_type], $currency);
        
        $final_fee = $fee_in_eur < self::MAX_CASH_IN_FEE[$user_type] ? $fee : $max_cash_in_fee;
        
        $this->final_fees[] = $this->addPrecision($final_fee, $currency);
    }

    /**
     * calculating cash_out fee
     * the calculated value is added in the temp array $this->final_fees
     *
     * @param string $date_str in Y-m-d format
     * @param int $user_id
     * @param string $user_type natural / legal
     * @param float $amount of current operation
     * @param string $currency
     *
     * @return void
     */
    private function calcCashOut(
        string $date_str,
        int $user_id,
        string $user_type,
        float $amount,
        string $currency
    ): void {
        $date = new \DateTime($date_str);

        //ISO-8601 week-numbering year + ISO-8601 week number of year
        $week = $date->format("oW");

        $amount_in_eur = $this->convertToEur($amount, $currency);

        $total_amount_in_eur = ($this->user_fees[$user_id][$week]['amount'] ?? 0) + $amount_in_eur;

        // if user has more than OP_NUMBER_FREE_OF_CHARGE (cash_out) operations or
        // total amount of current week operations is more than WEEKLY_AMOUNT_FREE_OF_CHARGE
        if (($this->user_fees[$user_id][$week]['count'] ?? 0) > self::OP_NUMBER_FREE_OF_CHARGE[$user_type]
            || $total_amount_in_eur > self::WEEKLY_AMOUNT_FREE_OF_CHARGE[$user_type]) {
            // calc the amount to be charged in eur
            $amount_to_charge_in_eur = min(
                $total_amount_in_eur - self::WEEKLY_AMOUNT_FREE_OF_CHARGE[$user_type],
                $amount_in_eur
            );

            // convert the amount to be charged in current currency
            $amount_to_charge = $this->convertToCurrency($amount_to_charge_in_eur, $currency);
            
            // calc the biggest fee
            $fee = ceil($amount_to_charge * self::CASH_OUT_FEE[$user_type]) / 100;
            $fee_in_eur = $this->convertToEur($fee, $currency);

            // convert the min cash_out fee in current currency
            $min_cash_out_fee = $this->convertToCurrency(self::MIN_CASH_OUT_FEE[$user_type], $currency);

            $final_fee = $fee_in_eur >= self::MIN_CASH_OUT_FEE[$user_type] ? $fee : $min_cash_out_fee;
            $final_fee = $this->addPrecision($final_fee, $currency);
        } else {
            $final_fee = $this->addPrecision(0, $currency);
        }

        $this->final_fees[] = $final_fee;

        $this->increaseUserAmount($user_id, $week, $amount_in_eur);
    }

    /**
     * increasing values in $this->user_fees
     *
     * @param int $user_id
     * @param string $week in oW format
     * @param float $amount_in_eur of current operation
     *
     * @return void
     */
    private function increaseUserAmount(int $user_id, string $week, float $amount_in_eur) : void
    {
        $this->user_fees[$user_id][$week]['count'] = ($this->user_fees[$user_id][$week]['count'] ?? 0) + 1;
        $this->user_fees[$user_id][$week]['amount'] =
            ($this->user_fees[$user_id][$week]['amount'] ?? 0) +
            $amount_in_eur;
    }

    /**
     * convert amount from $currency to EUR
     *
     * @param float $amount
     * @param string $currency
     *
     * @return float
     */
    private function convertToEur(float $amount, string $currency) : float
    {
        // there is no check for division by zero, because this will be constant error
        // there is no check for isset self::CONVERSION_RATES[$currency]
        // as this is checked by checkLine
        return $amount / self::CONVERSION_RATES[$currency];
    }

    /**
     * convert amount from EUR to $currency
     *
     * @param float $amount
     * @param string $currency
     *
     * @return float
     */
    private function convertToCurrency(float $amount, string $currency) : float
    {
        // there is no check for isset self::CONVERSION_RATES[$currency]
        // as this is checked by checkLine
        return $amount * self::CONVERSION_RATES[$currency];
    }

    /**
     * adding precision depends on smallest $currency item
     *
     * @param float $amount
     * @param string $currency
     *
     * @return string
     */
    private function addPrecision(float $amount, string $currency) : string
    {
        $amount = ceil($amount * self::CURRENCY_PRECISION_POW[$currency]) / self::CURRENCY_PRECISION_POW[$currency];
        return sprintf(self::CURRENCY_PRECISION[$currency], $amount);
    }
}
