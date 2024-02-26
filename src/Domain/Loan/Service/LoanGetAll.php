<?php

namespace App\Domain\Loan\Service;

use App\Domain\Loan\Data\DebtInformationItem;
use App\Domain\Loan\Data\CustomerItem;
use App\Domain\Loan\Data\LoanItem;
use App\Support\Validation\ValidationException;
use Cake\Validation\Validator;
use Laminas\Config\Config;
use DateTime;
use DateTimeZone;

final class LoanGetAll
{
    public function __construct(private Config $config) {}

    public function getAllLoans(string $financialInstitutionID = null, string $page = "0"): string
    {
        $this->validate(['page' => $page, 'financialInstitutionID' => $financialInstitutionID]);
        
        $loans_full_file_path = $this->config->datapath .'/'. $this->config->file_loans_name. '_latest_page_'.$page.'.json';
        
        if (!file_exists($loans_full_file_path)) {
            $loanRows = [];
            $timestamp = (new DateTime())->setTimezone(new DateTimeZone("UTC"))->format("Y-m-d\TH:i:sp");
            return (string)json_encode(
                $this->createResult($timestamp, $loanRows ),
                JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
        } else {
            $content =  file_get_contents($loans_full_file_path);
            return $content;
        }
    }

    private function createResult(string $timestamp, array $loanRows = []): DebtInformationItem
    {
        $result = new DebtInformationItem();
        $result->providerID = $this->config->providerID;
        
        $customers = [];
        if ($loanRows) {
            foreach ($loanRows as $row) {
                $customerUniqueKey = $row['customerID'].'|'.$this->config->financialInstitutionID;
                
                if (!isset($customers[$customerUniqueKey])) {
                    $customers[$customerUniqueKey] = new CustomerItem();
                    $customers[$customerUniqueKey]->customerID = $row['customerID'];
                    $customers[$customerUniqueKey]->financialInstitutionID = $this->config->financialInstitutionID;
                }
                
                $loan = new LoanItem();
                $loan->type = $row['type'];
                $loan->timestamp = $timestamp;
                $loan->accountID = $row['accountID'];
                if ($row['accountName']) $loan->accountName = $row['accountName'];
                
                if ($loan->type == 'repaymentLoan') {
                    $loan->originalBalance = $row['originalBalance'];
                    $loan->balance = $row['balance'];
                    $loan->terms = $row['terms'];
                    $loan->nominalInterestRate = $row['nominalInterestRate'];
                    $loan->installmentCharges = $row['installmentCharges'];
                    $loan->installmentChargePeriod = $row['installmentChargePeriod'];
                } elseif ($loan->type == 'creditFacility') {
                    $loan->creditLimit = $row['creditLimit'];
                    $loan->interestBearingBalance = $row['interestBearingBalance'];
                    $loan->nonInterestBearingBalance = $row['nonInterestBearingBalance'];
                    $loan->nominalInterestRate = $row['nominalInterestRate'];
                    $loan->installmentCharges = $row['installmentCharges'];
                    $loan->installmentChargePeriod = $row['installmentChargePeriod'];
                } elseif ($loan->type == 'chargeCard') {
                    $loan->interestBearingBalance = $row['interestBearingBalance'];
                    $loan->nonInterestBearingBalance = $row['nonInterestBearingBalance'];
                    $loan->installmentChargePeriod = null;
                }
                
                $loan->coBorrower = $row['coBorrower'];
                
                $customers[$customerUniqueKey]->loans[] = (object) array_filter((array) $loan, function ($val) {return !is_null($val);});
            }
        }
        $result->customers = array_values($customers);

        return $result;
    }
    
    private function validate(array $data): void
    {
        if ($data['financialInstitutionID'] && !preg_match('/^(\d{4}\:|)\d{9}$/', $data['financialInstitutionID'])) {
            $data['financialInstitutionID'] = '';
        } elseif (!$data['financialInstitutionID']) {
            $data['financialInstitutionID'] = $this->config->financialInstitutionID;
        }
        
        if (!preg_match('/^\d+$/', $data['page']) || ($data['page'] > "0" && !file_exists($this->config->datapath .'/'. $this->config->file_loans_name. '_latest_page_'.$data['page'].'.json'))) {
            $data['page'] = "";
        }
        
        $validator = new Validator();
        $validator
            ->notEmptyString('page', "Invalid input data")
            ->notEmptyString('financialInstitutionID', "Invalid input data");
        $errors = $validator->validate($data);
        
        if ($errors) {
            throw new ValidationException('Validation failed. Please check your input.', $errors);
        }
    }
}