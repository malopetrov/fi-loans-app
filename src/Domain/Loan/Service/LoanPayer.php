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

use DateTime;
use DateTimeZone;
use stdClass;

final class LoanPayer
{
    private LoggerInterface $logger;

    public function __construct(private LoanRepository $repository, private Config $config, LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory
            ->addFileHandler('dic-client.log')
            ->createLogger();
    }
    
    public function payLoan(array $data = []): stdClass
    {
        // Input validation
        $this->validateLoan($data);
        
        $debtInformation = $this->createDebtInformation($this->repository->getLoanById($data['accountID']));
        
        $payLoanResult = (object)[];
        
        $DICResponse = $this->sendDebtInformation($debtInformation);
        
        if ($DICResponse->code == 200) {
            if (!$this->repository->payLoan($data)) {
                $payLoanResult->success = false;
                $payLoanResult->message = 'Failed to write to db';
            } else {
                 $payLoanResult->success = true;
            }
        } else {
            $payLoanResult->success = false;
            $payLoanResult->message = 'DIC request error';
        }
        
        $payLoanResult->DICResponse = $DICResponse;
        
        return $payLoanResult;
    }
    
    private function validateLoan(array $data): void
    {
        if (!$data) {
            $data = [];
        }
        $validator = new Validator();
        $validator
            ->requirePresence('accountID', true, 'Input required')
            ->notEmptyString('accountID', 'Input required')
            ->requirePresence('type', true, 'Input required')
            ->notEmptyString('type', 'Input required');

        if(!isset($data['type']) || !in_array($data['type'], array('repaymentLoan', 'creditFacility', 'chargeCard'))) {
            $data['type'] = '';
            $validator
                ->notEmptyString('type', "One of repaymentLoan, creditFacility or chargeCard");
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
        $this->logger->info('DIC Pay Loan', $info);
        
        return $result;
    }
    
    private function createDebtInformation(array $row): DebtInformationItem
    {
        $result = new DebtInformationItem();
        $result->providerID = $this->config['providerID'];
        
        $customer = new CustomerItem();
        $customer->customerID = $row['customerID'];
        $customer->financialInstitutionID = $this->config['financialInstitutionID'];
                
        $loan = new LoanItem();
        $loan->type = $row['type'];
        $loan->timestamp = (new DateTime())->setTimezone(new DateTimeZone("UTC"))->format("Y-m-d\TH:i:sp");
        $loan->accountID = $row['accountID'];
        $loan->accountName = ($data['accountName'] ?? "");
        
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
        
        $customer->loans[] = (object) array_filter((array) $loan, function ($val) {return !is_null($val);});

        $result->customers[] = $customer;

        return $result;
    }
}