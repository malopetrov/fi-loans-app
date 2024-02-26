<?php
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    
    // DIC reads data (no token, but cert - mutual TSL)
    $baseDICget = "/debt-information/v1";
   
    $app->get( $baseDICget.'/hi', \App\Action\HiAction::class);
    $app->get( $baseDICget.'/loans[/{financialInstitutionID}]', \App\Action\Loan\LoanGetAllAction::class)->add(\App\Middleware\DebtInformationMiddleware::class);
    $app->group($baseDICget.'/loans/{financialInstitutionID}', function(RouteCollectorProxy $group) {
        $group->get('/customer', \App\Action\Loan\LoanFinderAction::class)->add(\App\Middleware\DebtInformationMiddleware::class);
    });
    $app->get( $baseDICget.'/ssn/{financialInstitutionID}', \App\Action\Loan\SsnGetAllAction::class)->add(\App\Middleware\DebtInformationMiddleware::class);

    // adding/updating loans (no token, but BASIC auth and requests to DIC API with cert file)
    $baseUpdatePost = "/debt-updates/v1"; 
    
    $app->get( $baseUpdatePost.'/hi', \App\Action\HiAction::class);
    $app->get( $baseUpdatePost.'/loans/{accountID}', \App\Action\Loan\LoanGetAction::class); // get a loan by id
    $app->post( $baseUpdatePost.'/loans', \App\Action\Loan\LoanSaveAction::class); // save new or updated loan, and within: post Request to DIC
    $app->post( $baseUpdatePost.'/loans/pay', \App\Action\Loan\LoanPayAction::class); // set paid and within: post Request to DIC
    $app->post( $baseUpdatePost.'/loans/balanceInterestUpdate', \App\Action\Loan\LoanBalanceInterestUpdateAction::class); // balance or interest change
    
};
