<?php

namespace App\Domain\Loan\Repository;

use PDO;

final class LoanFinderRepository
{
    private $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function findLoans(string $customerID, string $financialInstitutionID): array
    {
        
        $sql = "SELECT * FROM loans WHERE customerID = :customerID AND financialInstitutionID = :financialInstitutionID;";
        $statement = $this->connection->prepare($sql);
        $statement->execute([
            'customerID' => $customerID,
            'financialInstitutionID' => $financialInstitutionID,
        ]);

        return $statement->fetchAll() ?: [];
    }
}