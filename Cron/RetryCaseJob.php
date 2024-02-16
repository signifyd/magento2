<?php

/**
 * Copyright 2016 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Cron;

use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Casedata\FilterCasesByStatusFactory;
use Signifyd\Connect\Model\ProcessCron\CaseData\InReviewFactory;
use Signifyd\Connect\Model\ProcessCron\CaseData\WaitingSubmissionFactory;
use Signifyd\Connect\Model\ProcessCron\CaseData\AsyncWaitingFactory;
use Signifyd\Connect\Model\ProcessCron\CaseData\PreAuthTransactionFactory;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\SignifydFlags;

class RetryCaseJob
{
    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var FilterCasesByStatusFactory
     */
    public $filterCasesByStatusFactory;

    /**
     * @var InReviewFactory
     */
    public $inReviewFactory;

    /**
     * @var WaitingSubmissionFactory
     */
    public $waitingSubmissionFactory;

    /**
     * @var AsyncWaitingFactory
     */
    public $asyncWaitingFactory;

    /**
     * @var SignifydFlags
     */
    public $signifydFlags;

    /**
     * @var PreAuthTransactionFactory
     */
    public $preAuthTransactionFactory;

    /**
     * RetryCaseJob constructor.
     * @param Logger $logger
     * @param FilterCasesByStatusFactory $filterCasesByStatusFactory
     * @param InReviewFactory $inReviewFactory
     * @param WaitingSubmissionFactory $waitingSubmissionFactory
     * @param AsyncWaitingFactory $asyncWaitingFactory
     * @param SignifydFlags $signifydFlags
     * @param PreAuthTransactionFactory $preAuthTransactionFactory
     */
    public function __construct(
        Logger $logger,
        FilterCasesByStatusFactory $filterCasesByStatusFactory,
        InReviewFactory $inReviewFactory,
        WaitingSubmissionFactory $waitingSubmissionFactory,
        AsyncWaitingFactory $asyncWaitingFactory,
        SignifydFlags $signifydFlags,
        PreAuthTransactionFactory $preAuthTransactionFactory
    ) {
        $this->logger = $logger;
        $this->filterCasesByStatusFactory = $filterCasesByStatusFactory;
        $this->inReviewFactory = $inReviewFactory;
        $this->waitingSubmissionFactory = $waitingSubmissionFactory;
        $this->asyncWaitingFactory = $asyncWaitingFactory;
        $this->signifydFlags = $signifydFlags;
        $this->preAuthTransactionFactory = $preAuthTransactionFactory;
    }

    /**
     * Entry point to Cron job
     */
    public function execute()
    {
        $this->logger->debug("CRON: Main retry method called");

        $filterCasesByStatusFactory = $this->filterCasesByStatusFactory->create();
        $asyncWaitingCases = $filterCasesByStatusFactory(Casedata::ASYNC_WAIT);

        $processAsyncWaitingCases = $this->asyncWaitingFactory->create();
        $processAsyncWaitingCases($asyncWaitingCases);

        /**
         * Getting all the cases that were not submitted to Signifyd
         */
        $filterCasesByStatusFactory = $this->filterCasesByStatusFactory->create();
        $waitingCases = $filterCasesByStatusFactory(Casedata::WAITING_SUBMISSION_STATUS);

        $processWaitingSubmission = $this->waitingSubmissionFactory->create();
        $processWaitingSubmission($waitingCases);

        /**
         * Getting all the cases that are awaiting review from Signifyd
         */
        $filterCasesByStatusFactory = $this->filterCasesByStatusFactory->create();
        $inReviewCases = $filterCasesByStatusFactory(Casedata::IN_REVIEW_STATUS);

        $processInReview = $this->inReviewFactory->create();
        $processInReview($inReviewCases);

        /**
         * Getting all the cases that are using pre_auth
         */
        $filterCasesByStatusFactory = $this->filterCasesByStatusFactory->create();
        $preAuthCases = $filterCasesByStatusFactory(Casedata::COMPLETED_STATUS, 'pre_auth');

        $processPreAuth = $this->preAuthTransactionFactory->create();
        $processPreAuth($preAuthCases);

        $this->signifydFlags->updateCronFlag();
        $this->logger->debug("CRON: Main retry method ended");
    }
}
