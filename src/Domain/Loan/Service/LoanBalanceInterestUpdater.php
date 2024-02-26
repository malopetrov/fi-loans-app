<?php

namespace App\Domain\Loan\Service;

use App\Domain\Loan\Repository\LoanRepository;
use App\Support\Validation\ValidationException;
use Cake\Validation\Validator;
use Psr\Http\Message\ResponseInterface;

use stdClass;

final class LoanBalanceInterestUpdater
{
    public function __construct(private LoanRepository $repository) {}
    
    public function updateBalanceInterestLoan(array $data = []): stdClass
    {
        // Input validation
        $this->validateLoan($data);
        
        $updateBalanceInterestLoanResult = (object)[];
        
        if ($this->repository->updateBalanceInterestLoan((array)$data)) {
            $updateBalanceInterestLoanResult->success = true;
        } else {
            $updateBalanceInterestLoanResult->success = false;
            $updateBalanceInterestLoanResult->message = 'Failed to write to db';
        }
        
        return $updateBalanceInterestLoanResult;
    }
    
    private function validateLoan(array $data): void
    {
        $validator = new Validator();
        $validator
            ->requirePresence('customerID', true, 'Input required')
            ->notEmptyString('customerID', 'Input required')
            ->lengthBetween('customerID', [11,11], 'Input required')
            ->requirePresence('accountID', true, 'Input required')
            ->notEmptyString('accountID', 'Input required')
            ->requirePresence('type', true, 'Input required')
            ->notEmptyString('type', 'Input required');

        if(!isset($data['type']) || !in_array($data['type'], array('repaymentLoan', 'creditFacility', 'chargeCard'))) {
            $data['type'] = '';
            $validator
                ->notEmptyString('type', "One of repaymentLoan, creditFacility or chargeCard");
        } elseif ($data['type'] == 'repaymentLoan') {
            $validator
                ->requirePresence('originalBalance', true, 'Input required')
                ->notEmptyString('originalBalance', 'Input required')
                ->requirePresence('balance', true, 'Input required')
                ->notEmptyString('balance', 'Input required')
                ->requirePresence('nominalInterestRate', true, 'Input required')
                ->notEmptyString('nominalInterestRate', 'Input required');
        } elseif ($data['type'] == 'creditFacility') {
            $validator
                ->requirePresence('interestBearingBalance', true, 'Input required')
                ->notEmptyString('interestBearingBalance', 'Input required')
                ->requirePresence('nonInterestBearingBalance', true, 'Input required')
                ->notEmptyString('nonInterestBearingBalance', 'Input required')
                ->requirePresence('nominalInterestRate', true, 'Input required')
                ->notEmptyString('nominalInterestRate', 'Input required');
        } elseif ($data['type'] == 'chargeCard') {
            $validator
                ->requirePresence('interestBearingBalance', true, 'Input required')
                ->notEmptyString('interestBearingBalance', 'Input required')
                ->requirePresence('nonInterestBearingBalance', true, 'Input required')
                ->notEmptyString('nonInterestBearingBalance', 'Input required');
        }
        
       $errors = $validator->validate($data);

        if ($errors) {
            throw new ValidationException('Validation failed. Please check your input.', $errors);
        }
        
        if(!$this->repository->isLoanByTypeAndId( $data['accountID'], $data['type'])) {
            $data['accountID'] = '';
            $validator
                ->notEmptyString('accountID', 'Loan does not exist or type is wrong');
            $errors = $validator->validate($data);

            if ($errors) {
                throw new ValidationException('Validation failed. Please check your input.', $errors);
            }
        }
    }
}