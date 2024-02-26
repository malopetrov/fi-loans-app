<?php

namespace App\Domain\Loan\Service;

use App\Domain\Loan\Data\SsnCollectionItem;
use App\Support\Validation\ValidationException;
use Cake\Validation\Validator;
use Laminas\Config\Config;
use DateTime;
use DateTimeZone;

final class SsnGetAll
{
    public function __construct(private Config $config) {}

    public function getAllSsn(string $financialInstitutionID): string
    {
        //validation 
        $this->validate(['financialInstitutionID' => $financialInstitutionID]);

        $ssn_full_file_path = $this->config->datapath .'/'. $this->config->file_ssns_name. '_latest_page_0.json';
        
        if (!file_exists($ssn_full_file_path)) {
            $ssnRows = [];
            $timestamp = (new DateTime())->setTimezone(new DateTimeZone("UTC"))->format("Y-m-d\TH:i:sp");
            return (string)json_encode(
                $this->createResult($timestamp, $ssnRows ),
                JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
        
        } else {
            $content = file_get_contents($ssn_full_file_path);
            return $content;
        }
    }

    private function createResult(string $timestamp, array $ssnRows = []): SsnCollectionItem
    {
        $result = new SsnCollectionItem();
        $result->providerID = $this->config->providerID;
        $result->financialInstitutionID = $this->config->financialInstitutionID;
        $result->timestamp = $timestamp;
        
        $customers = [];
        if ($ssnRows) {
            foreach ($ssnRows as $ssn) {
                $customers[] = $ssn['customerID'];
            }
        }

        $result->customers = array_values($customers);

        return $result;
    }
    
    private function validate(array $data): void
    {
        
        if (!preg_match('/^(\d{4}\:|)\d{9}$/', $data['financialInstitutionID'])) {
            $data['financialInstitutionID'] = '';
        }
        
        $validator = new Validator();
        $validator
            ->notEmptyString('financialInstitutionID', "Invalid input data");
        $errors = $validator->validate($data);
        
        if ($errors) {
            throw new ValidationException('Validation failed. Please check your input.', $errors);
        }
    }
}