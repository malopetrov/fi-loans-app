<?php

namespace App\Domain\Loan\Repository;

use PDO;

final class LoanRepository
{
    private $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function isLoanByTypeAndId(string $accountID, string $type): bool
    {
        $sql = "SELECT count(*) total FROM loans WHERE accountID = :accountID AND `type` = :type;";
        $statement = $this->connection->prepare($sql);
        $statement->execute([
            'accountID' => $accountID,
            'type' => $type,
        ]);

        $row = $statement->fetch();
        if ($row) {
           return $row['total'];
        } else {
            return false;
        }
    }

    public function isLoanById(string $accountID): bool
    {
        $sql = "SELECT count(*) total FROM loans WHERE accountID = :accountID;";
        $statement = $this->connection->prepare($sql);
        $statement->execute([
            'accountID' => $accountID,
        ]);

        $row = $statement->fetch();
        if ($row) {
           return $row['total'];
        } else {
            return false;
        }
    }

    public function getLoanById(string $accountID): array
    {
        $sql = "SELECT * from loans WHERE accountID = :accountID;";
        $statement = $this->connection->prepare($sql);
        $statement->execute([
            'accountID' => $accountID,
        ]);

        return $statement->fetch() ?: [];
    }
    
    public function saveLoan(array $data): bool
    {
        $sql = "INSERT INTO loans 
                    (providerID, customerID, financialInstitutionID, `type`, accountID, accountName, originalBalance, balance, terms, nominalInterestRate, installmentCharges, coBorrower, creditLimit, interestBearingBalance, nonInterestBearingBalance)
                VALUES 
                    (:providerID, :customerID, :financialInstitutionID, :type, :accountID, :accountName, :originalBalance, :balance, :terms, :nominalInterestRate, :installmentCharges, :coBorrower, :creditLimit, :interestBearingBalance, :nonInterestBearingBalance)
                ON DUPLICATE KEY UPDATE
                    originalBalance = :originalBalance,
                    balance = :balance,
                    terms = :terms,
                    nominalInterestRate = :nominalInterestRate,
                    installmentCharges = :installmentCharges,
                    coBorrower = :coBorrower,
                    creditLimit = :creditLimit,
                    interestBearingBalance = :interestBearingBalance,
                    nonInterestBearingBalance = :nonInterestBearingBalance
                ";
        $statement = $this->connection->prepare($sql);
        $res = $statement->execute([
            'providerID' => $data['providerID'],
            'customerID' => $data['customerID'],
            'financialInstitutionID' => $data['financialInstitutionID'],
            'type' => $data['type'],
            'accountID' => $data['accountID'],
            'accountName' => ($data['accountName'] ?? "" ),
            'originalBalance' => ($data['originalBalance'] ?? 0),
            'balance' => ($data['balance'] ?? 0),
            'terms' => ($data['terms'] ?? 0),
            'nominalInterestRate' => ($data['nominalInterestRate'] ?? 0),
            'installmentCharges' => ($data['installmentCharges'] ?? 0),
            'coBorrower' => $data['coBorrower'],
            'creditLimit' => ($data['creditLimit'] ?? 0),
            'interestBearingBalance' => ($data['interestBearingBalance'] ?? 0),
            'nonInterestBearingBalance' => ($data['nonInterestBearingBalance'] ?? 0),
        ]);
        return true;
    }
    
    public function payLoan(array $data): bool
    {
        $sql = "INSERT IGNORE INTO loans_paid SELECT *, NOW() FROM loans WHERE accountID = :accountID";
        $statement = $this->connection->prepare($sql);
        $statement->execute(['accountID' => $data['accountID']]);

        $sql = "DELETE FROM loans WHERE accountID = :accountID";
        $statement = $this->connection->prepare($sql);
        $statement->execute(['accountID' => $data['accountID']]);
        return true;
     }
     
     public function updateBalanceInterestLoan(array $data): bool 
     {
         if ($data['type'] == 'repaymentLoan') {
             $sql = "UPDATE loans
                           SET
                             originalBalance = :originalBalance,
                            balance = :balance,
                            nominalInterestRate = :nominalInterestRate,
                        WHERE
                               accountID = :accountID
                               AND `type` = :type";
             $statement = $this->connection->prepare($sql);
             $res = $statement->execute([
                'type' => $data['type'],
                'accountID' => $data['accountID'],
                'originalBalance' => $data['originalBalance'],
                'balance' => $data['balance'], 
                'nominalInterestRate' => $data['nominalInterestRate'],
            ]);    
         } elseif ($data['type'] == 'creditFacility') {
             $sql = "UPDATE loans
                           SET
                            nominalInterestRate = :nominalInterestRate,
                            interestBearingBalance = :interestBearingBalance,
                            nonInterestBearingBalance = :nonInterestBearingBalance
                        WHERE
                            accountID = :accountID
                            AND `type` = :type";
             $statement = $this->connection->prepare($sql);
             $res = $statement->execute([
                'type' => $data['type'],
                'accountID' => $data['accountID'],
                'nominalInterestRate' => $data['nominalInterestRate'],
                'interestBearingBalance' => $data['interestBearingBalance'],
                'nonInterestBearingBalance' => $data['nonInterestBearingBalance'],
            ]);
        } elseif ($data['type'] == 'chargeCard') {
             $sql = "UPDATE loans
                           SET
                            interestBearingBalance = :interestBearingBalance,
                            nonInterestBearingBalance = :nonInterestBearingBalance
                        WHERE
                            accountID = :accountID
                            and `type` = :type";
             $statement = $this->connection->prepare($sql);
             $res = $statement->execute([
                'type' => $data['type'],
                'accountID' => $data['accountID'],
                'interestBearingBalance' => $data['interestBearingBalance'],
                'nonInterestBearingBalance' => $data['nonInterestBearingBalance'],
            ]);
        }
        
        return true;
     }
}