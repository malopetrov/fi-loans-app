<?php

namespace App\Domain\Loan\Service;

use App\Domain\Loan\Data\DebtInformationItem;
use App\Domain\Loan\Data\CustomerItem;
use App\Domain\Loan\Data\LoanItem;
use App\Domain\Loan\Repository\LoanFinderRepository;
use App\Support\Validation\ValidationException;
use Cake\Validation\Validator;

use Laminas\Config\Config;
use DateTime;
use DateTimeZone;

final class LoanFinder
{
    public function __construct(private LoanFinderRepository $repository, private Config $config) {}

    public function findLoans(array $customerHeaders, string $financialInstitutionID): DebtInformationItem
    {
        //validation 
        $customerID = $this->validate(['customerHeaders'=>$customerHeaders, 'financialInstitutionID' => $financialInstitutionID]);

        $loanRows = $this->repository->findLoans($customerID, $financialInstitutionID);

        return $this->createResult($loanRows);
    }

    private function createResult(array $loanRows): DebtInformationItem
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
                $loan->timestamp = (new DateTime())->setTimezone(new DateTimeZone("UTC"))->format("Y-m-d\TH:i:sp");
                $loan->accountID = $row['accountID'];
                if ($row['accountName']) {
                    $loan->accountName = $row['accountName'];
                }
                
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
    
    private function validate(array $data): string
    {    
        if (!preg_match('/^(\d{4}\:|)\d{9}$/', $data['financialInstitutionID'])) {
            $validateData['financialInstitutionID'] = '';
        } else {
            $validateData = ['financialInstitutionID' => $data['financialInstitutionID']];
        }
        
        if (!$data['customerHeaders'] || !preg_match('%^\d{11}$%',$data['customerHeaders'][0])) {
            $validateData['customerID'] = '';
        } else {
            $validateData['customerID'] = $data['customerHeaders'][0];
        }
        
        $validator = new Validator();
        $validator
            ->notEmptyString('customerID', "Invalid input data")
            ->notEmptyString('financialInstitutionID', "Invalid input data");
        $errors = $validator->validate($validateData);
        
        if ($errors) {
            throw new ValidationException('Validation failed. Please check your input.', $errors);
        }
        
        return $validateData['customerID'];
    }
}