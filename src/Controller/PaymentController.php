<?php

namespace MLukman\SaasBundle\Controller;

use MLukman\SaasBundle\Service\SaasUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class PaymentController extends AbstractController
{
    #[Route('/payment/webhook/{provider}', name: 'saas_payment_webhook')]
    public function webhook(SaasUtil $saas, Request $request, $provider): Response
    {
        if ($saas->getPaymentProvider()->getId() != $provider) {
            return new BadRequestHttpException("Provider $provider is not enabled");
        }
        $saas->getPaymentProvider()->handleWebhook($request);
        return new Response();
    }
}
