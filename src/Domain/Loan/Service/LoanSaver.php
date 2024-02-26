<?php

namespace App\Domain\Loan\Service;

use App\Domain\Loan\Repository\LoanRepository;
use App\Domain\Loan\Data\DebtInformationItem;
use App\Domain\Loan\Data\CustomerItem;
use App\Domain\Loan\Data\LoanItem;
use App\Support\Validation\ValidationException;
use Cake\Validation\Validator;
use Psr\Http\Message\ResponseInterface;
use Laminas\Config\Config;
use App\Factory\LoggerFactory;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;

use stdClass;
use DateTime;
use DateTimeZone;

final class LoanSaver
{    
    private LoggerInterface $logger;

    public function __construct(private LoanRepository $repository, private Config $config, LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory
            ->addFileHandler('dic-client.log')
            ->createLogger();
    }
    
    public function saveLoan(array $data = []): stdClass
    {
        // Input validation
        $this->validateLoan($data);
        
        $data['providerID'] = $this->config['providerID'];
        $data['financialInstitutionID'] = $this->config['financialInstitutionID'];
        
        $debtInformation = $this->createDebtInformation($data);
        
        $saveLoanResult = (object)[];
        
        $DICResponse = $this->sendDebtInformation($debtInformation);
        
        if ($DICResponse->code == 200) {
            if (!$this->repository->saveLoan($data)) {
                $saveLoanResult->success = false;
                $saveLoanResult->message = 'Failed to write to db';
            } else {
                 $saveLoanResult->success = true;
            }
        } else {
            $saveLoanResult->success = false;
            $saveLoanResult->message = 'DIC request error';
        }
        
        $saveLoanResult->DICResponse = $DICResponse;
        
        return $saveLoanResult;
    }
    
    private function validateLoan(array $data): void
    {
        if (!$data) {
            $data = [];
        }
        $validator = new Validator();
        $validator
            ->requirePresence('customerID', true, 'Input required')
            ->notEmptyString('customerID', 'Input required')
            ->lengthBetween('customerID', [11,11], 'Length 11 expected')
            ->requirePresence('accountID', true, 'Input required')
            ->notEmptyString('accountID', 'Input required')
            ->requirePresence('coBorrower', true, 'Input required')
            ->notEmptyString('coBorrower', 'Input required')
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
                ->requirePresence('terms', true, 'Input required')
                ->notEmptyString('terms', 'Input required')
                ->requirePresence('nominalInterestRate', true, 'Input required')
                ->notEmptyString('nominalInterestRate', 'Input required')
                ->requirePresence('instalmentCharges', true, 'Input required')
                ->notEmptyString('instalmentCharges', 'Input required')
                ->requirePresence('installmentChargePeriod', true, 'Input required')
                ->notEmptyString('installmentChargePeriod', 'Input required');
        } elseif ($data['type'] == 'creditFacility') {
            $validator
                ->requirePresence('creditLimit', true, 'Input required')
                ->notEmptyString('creditLimit', 'Input required')
                ->requirePresence('interestBearingBalance', true, 'Input required')
                ->notEmptyString('interestBearingBalance', 'Input required')
                ->requirePresence('nonInterestBearingBalance', true, 'Input required')
                ->notEmptyString('nonInterestBearingBalance', 'Input required')
                ->requirePresence('nominalInterestRate', true, 'Input required')
                ->notEmptyString('nominalInterestRate', 'Input required')
                ->requirePresence('installmentCharges', true, 'Input required')
                ->notEmptyString('installmentCharges', 'Input required')
                ->requirePresence('installmentChargePeriod', true, 'Input required')
                ->notEmptyString('installmentChargePeriod', 'Input required');
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
    }
    
    private function sendDebtInformation(DebtInformationItem $debtInformation): stdClass
    {     
        $client = new Client([
            'base_uri' => $this->config['dic_url'],
            'timeout' => 2.0
        ]);
        $result = (object)[];
        
        $options = [];
        
        if ($this->config['dic_cert_full_path_file']) {
            if (!$this->config['dic_cert_password']) {
                $options['cert'] = $this->config['dic_cert_full_path_file'];
            } else {
                $options['cert'] =  [$this->config['dic_cert_full_path_file'], $this->config['dic_cert_password']];
            }
        }
        
        $options['json'] = $debtInformation;
        
        try {
            $response = $client->request( 'POST', $this->config['dic_path'].'/loans', $options);
            $result->code = 200;
            $result->content = json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            $result->code = $e->getResponse()->getStatusCode();
            $result->content = json_decode($e->getResponse()->getBody()->getContents(), true);
        }
        
        $info = [];
        $info['requestBody'] = json_decode(json_encode($options['json']), true);
        $info['responseCode'] = $result->code;
        $info['responseBody'] = $result->content;
        $this->logger->info('DIC New/Change Loan', $info);
        
        return $result;
    }
    
    private function createDebtInformation(array $data): DebtInformationItem
    {
        $result = new DebtInformationItem();
        $result->providerID = $data['providerID'];
        
        $customer = new CustomerItem();
        $customer->customerID = $data['customerID'];
        $customer->financialInstitutionID = $data['financialInstitutionID'];
        
        $loan = new LoanItem();
        $loan->type = $data['type'];
        $loan->timestamp = (new DateTime())->setTimezone(new DateTimeZone("UTC"))->format("Y-m-d\TH:i:sp");
        $loan->accountID = $data['accountID'];
        $loan->accountName = ($data['accountName'] ?? "");
        
        if ($loan->type == 'repaymentLoan') {
            $loan->originalBalance = $data['originalBalance'];
            $loan->balance = $data['balance'];
            $loan->terms = $data['terms'];
            $loan->nominalInterestRate = $data['nominalInterestRate'];
            $loan->installmentCharges = $data['installmentCharges'];
            $loan->installmentChargePeriod = $data['installmentChargePeriod'];
        } elseif ($loan->type == 'creditFacility') {
            $loan->creditLimit = $data['creditLimit'];
            $loan->interestBearingBalance = $data['interestBearingBalance'];
            $loan->nonInterestBearingBalance = $data['nonInterestBearingBalance'];
            $loan->nominalInterestRate = $data['nominalInterestRate'];
            $loan->installmentCharges = $data['installmentCharges'];
            $loan->installmentChargePeriod = $data['installmentChargePeriod'];
        } elseif ($loan->type == 'chargeCard') {
            $loan->interestBearingBalance = $data['interestBearingBalance'];
            $loan->nonInterestBearingBalance = $data['nonInterestBearingBalance'];
            $loan->installmentChargePeriod = null;
        }
        
        $loan->coBorrower = $data['coBorrower'];
        
        $customer->loans[] = (object) array_filter((array) $loan, function ($val) {return !is_null($val);});

        $result->customers[] = $customer;

        return $result;
    }
}