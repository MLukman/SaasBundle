<?php

namespace MLukman\SaasBundle\Tests;

use MLukman\SaasBundle\Tests\App\TestCaseBase;

class PrepaidManagerTest extends TestCaseBase
{
    public function testCreditTopup(): void
    {
        $wallet = "my_wallet";
        $before = $this->saas->getPrepaidManager()->getCreditBalance($wallet);
        $this->saas->getPrepaidManager()->topupCredit($wallet, 'JOINBONUS', 2);
        $this->saas->getPrepaidManager()->commitChanges();
        $after = $this->saas->getPrepaidManager()->getCreditBalance($wallet);

        $this->assertEquals(200, $after - $before);
    }

    public function testCreditUsage(): void
    {
        $wallet = "my_wallet";
        $this->saas->getPrepaidManager()->topupCredit($wallet, 'JOINBONUS');
        $this->saas->getPrepaidManager()->commitChanges();
        $before = $this->saas->getPrepaidManager()->getCreditBalance($wallet);
        $this->saas->getPrepaidManager()->spendCredit($wallet, 'USE_50', '12345');
        $this->saas->getPrepaidManager()->commitChanges();
        $after = $this->saas->getPrepaidManager()->getCreditBalance($wallet);

        $this->assertEquals(-50, $after - $before);
    }

    public function testCreditTransfer(): void
    {
        $wallet1 = "my_wallet";
        $wallet2 = "your_wallet";
        $this->saas->getPrepaidManager()->topupCredit($wallet1, 'JOINBONUS');
        $this->saas->getPrepaidManager()->commitChanges();
        $before1 = $this->saas->getPrepaidManager()->getCreditBalance($wallet1);
        $before2 = $this->saas->getPrepaidManager()->getCreditBalance($wallet2);
        $this->saas->getPrepaidManager()->transferCredit($wallet1, $wallet2, 25, "SALE", "1234");
        $this->saas->getPrepaidManager()->commitChanges();
        $after1 = $this->saas->getPrepaidManager()->getCreditBalance($wallet1);
        $after2 = $this->saas->getPrepaidManager()->getCreditBalance($wallet2);

        $this->assertEquals(-25, $after1 - $before1);
        $this->assertEquals(25, $after2 - $before2);
    }
}
