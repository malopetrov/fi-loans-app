<?php

namespace App\Domain\Loan\Service;

use App\Domain\Loan\Data\LoanGetItem;
use App\Domain\Loan\Repository\LoanRepository;
use Cake\Validation\Validator;
use Selective\Validation\Converter\CakeValidationConverter;
use Selective\Validation\Exception\ValidationException;
use Laminas\Config\Config;

use DateTime;
use DateTimeZone;

final class LoanGetter
{
    public function __construct(private LoanRepository $repository, private Config $config) {}

    public function getLoan(string $accountID): LoanGetItem
    {
        $this->validateLoan(['accountID' => $accountID]);

        $loanData = $this->repository->getLoanByID($accountID);

        return $this->createResult($loanData);
    }
 
     private function validateLoan(array $data): void
    {
        $validator = new Validator();
        $validator
            ->requirePresence('accountID', true, 'Input required')
            ->notEmptyString('accountID', 'Input required');
        $validationResult = CakeValidationConverter::createValidationResult($validator->validate($data));

        if ($validationResult->fails()) {
            throw new ValidationException('Validation failed. Please check your input.', $validationResult);
        }
        
        if(!$this->repository->isLoanById($data['accountID'])) {
            $data['accountID'] = '';
            $validator
                ->notEmptyString('accountID', 'Loan does not exist');
            $validationResult = CakeValidationConverter::createValidationResult($validator->validate($data));
            
            if ($validationResult->fails()) {
                throw new ValidationException('Validation failed. Please check your input.', $validationResult);
            }
        }
    }

    private function createResult(array $loanData): LoanGetItem
    {
        $loan = new LoanGetItem();
        $loan->customerID = $loanData['customerID'];
        $loan->type = $loanData['type'];
        $loan->accountID = $loanData['accountID'];
        if ($loanData['accountName']) {
            $loan->accountName = $loanData['accountName'];
        }
        
        if ($loan->type == 'repaymentLoan') {
            $loan->originalBalance = $loanData['originalBalance'];
            $loan->balance = $loanData['balance'];
            $loan->terms = $loanData['terms'];
            $loan->nominalInterestRate = $loanData['nominalInterestRate'];
            $loan->installmentCharges = $loanData['installmentCharges'];
            $loan->installmentChargePeriod = $loanData['installmentChargePeriod'];
        } elseif ($loan->type == 'creditFacility') {
            $loan->creditLimit = $loanData['creditLimit'];
            $loan->interestBearingBalance = $loanData['interestBearingBalance'];
            $loan->nonInterestBearingBalance = $loanData['nonInterestBearingBalance'];
            $loan->nominalInterestRate = $loanData['nominalInterestRate'];
            $loan->installmentCharges = $loanData['installmentCharges'];
            $loan->installmentChargePeriod = $loanData['installmentChargePeriod'];
        } elseif ($loan->type == 'chargeCard') {
            $loan->interestBearingBalance = $loanData['interestBearingBalance'];
            $loan->nonInterestBearingBalance = $loanData['nonInterestBearingBalance'];
            $loan->installmentChargePeriod = null;
        }
        
        $loan->coBorrower = $loanData['coBorrower'];

        return $loan;
    }
}