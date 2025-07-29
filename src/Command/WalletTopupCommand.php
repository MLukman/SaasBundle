<?php

namespace MLukman\SaasBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use MLukman\SaasBundle\Service\SaasUtil;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
        name: 'saas:wallet:topup',
        description: 'Top up a wallet',
    )]
class WalletTopupCommand extends Command
{
    protected EntityManagerInterface $em;
    protected SaasUtil $saas;

    #[Required]
    public function required(EntityManagerInterface $em, SaasUtil $saas)
    {
        $this->em = $em;
        $this->saas = $saas;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('walletId', InputArgument::REQUIRED, 'The wallet id to top up')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $prepaidManager = $this->saas->getPrepaidManager();

        $io = new SymfonyStyle($input, $output);
        $walletId = $input->getArgument('walletId');
        $balance = $prepaidManager->cacheWalletBalance($walletId);

        $io->title(sprintf("Wallet <options=bold>%s</> has a current balance of <options=bold>%d</>.", $walletId, $balance));

        $topups = [];
        foreach ($prepaidManager->getConfiguration()->getTopups() as $topupId => $topup) {
            $topups[$topupId] = sprintf("%s (%d credits)", $topup->getName(), $topup->getCredit());
        }
        $topupId = $io->choice("Please choose a topup", $topups);

        $units = $io->ask('Please enter the number of units', '1', function (string $input): int {
            if (!is_numeric($input)) {
                throw new RuntimeException('You must type a number.');
            }
            if (($number = (int) $input) < 1) {
                throw new RuntimeException('The number must be more than 0.');
            }
            return $number;
        });

        $total = $prepaidManager->getTopupConfig($topupId)->getCredit() * $units;
        $io->section(sprintf(
                "<options=bold>%d</> units of topup <options=bold>%s</> will be added to wallet <options=bold>%s</>, and the new balance will be <options=bold>%d</>.",
                $units,
                $topupId,
                $walletId,
                $balance + $total
            ));

        if ($io->confirm('Proceed with the top up?')) {
            $prepaidManager->topupCredit($walletId, $topupId, $units);
            $prepaidManager->commitChanges();
            $io->success(sprintf("Done. The new balance is %d.", $prepaidManager->getCreditBalance($walletId)));
            return Command::SUCCESS;
        }

        $io->error('You did not proceed');
        return Command::FAILURE;
    }
}
