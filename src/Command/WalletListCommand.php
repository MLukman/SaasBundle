<?php

namespace MLukman\SaasBundle\Command;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use MLukman\SaasBundle\Entity\Credit;
use MLukman\SaasBundle\Entity\CreditUsage;
use MLukman\SaasBundle\Entity\CreditWithdrawal;
use MLukman\SaasBundle\Service\SaasUtil;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
        name: 'saas:wallet:list',
        description: 'List all wallets',
    )]
class WalletListCommand extends Command
{
    protected EntityManagerInterface $em;
    protected SaasUtil $saas;

    #[Required]
    public function required(EntityManagerInterface $em, SaasUtil $saas)
    {
        $this->em = $em;
        $this->saas = $saas;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $columns = [
            'Wallet',
            '+Topups',
            '+InTransfers',
            '-OutTransfers',
            '-Withdrawals',
            '-OtherUsages',
            '-Expired',
            '=Balance',
        ];
        $wallets = [];
        $today = new DateTime();

        $credits = $this->em->getRepository(Credit::class)->findAll();
        foreach ($credits as $credit) {
            /** @var Credit $credit */
            if (!isset($wallets[$wallet = $credit->getWallet()])) {
                $wallets[$wallet] = [
                    'wallet' => $wallet,
                    'topups' => 0,
                    'intransfers' => 0,
                    'outtransfers' => 0,
                    'withdrawals' => 0,
                    'usages' => 0,
                    'expired' => 0,
                    'balance' => 0,
                ];
            }
            if ($credit->getTransfer()) {
                $wallets[$wallet]['intransfers'] += $credit->getPoints();
            } else {
                $wallets[$wallet]['topups'] += $credit->getPoints();
            }
            if ($credit->getExpiry() > $today) {
                $wallets[$wallet]['balance'] += $credit->getBalance();
            } else {
                $wallets[$wallet]['expired'] += $credit->getBalance();
            }
        }
        $usages = $this->em->getRepository(CreditUsage::class)->findAll();
        foreach ($usages as $usage) {
            /** @var CreditUsage $usage */
            $wallet = $usage->getWallet();
            if ($usage instanceof CreditWithdrawal) {
                $wallets[$wallet]['withdrawals'] += $usage->getPoints();
            } elseif ($usage instanceof \MLukman\SaasBundle\Entity\CreditTransfer) {
                $wallets[$wallet]['outtransfers'] += $usage->getPoints();
            } else {
                $wallets[$wallet]['usages'] += $usage->getPoints();
            }
        }

        $table = new Table($output);
        $table
            ->setHeaders($columns)
            ->setRows($wallets)
        ;
        $table->render();
        return Command::SUCCESS;
    }
}
