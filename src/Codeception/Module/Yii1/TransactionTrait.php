<?php

namespace Codeception\Module\Yii1;

use Codeception\Module\Yii1\TransactionWrapper;
use Yii;

trait TransactionTrait
{
    /**
     * @var ?TransactionWrapper
     */
    protected $transactionWrapper;

    protected function startTransaction(): void
    {
        $app = Yii::app();
        if ($app === null) {
            return;
        }
        $db = $app->getComponent('db');
        if ($db === null) {
            return;
        }
        $this->transactionWrapper = new TransactionWrapper($db);
        $this->transactionWrapper->start();
    }

    protected function rollbackTransaction(): void
    {
        if ($this->transactionWrapper === null) {
            return;
        }
        $this->transactionWrapper->rollback();
        $this->transactionWrapper = null;
    }
}
