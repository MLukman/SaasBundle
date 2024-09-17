<?php

namespace MLukman\SaasBundle\Payment;

use MLukman\SaasBundle\Config\TopupConfig;
use MLukman\SaasBundle\Service\SaasUtil;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AutoconfigureTag('saas.payment.provider')]
interface ProviderInterface
{

    public function getId(): string;

    public function initialize(SaasUtil $saas, array $paymentConfigParams): bool;

    public function initiateTopupTransaction(TopupConfig $topup, string $redirectBackUrl): ?TransactionInterface;

    public function retrieveTopupTransaction(string $reference): ?TransactionInterface;

    public function generateRedirectForTransaction(TransactionInterface $transaction): Response;

    public function handleWebhook(Request $request);
}
