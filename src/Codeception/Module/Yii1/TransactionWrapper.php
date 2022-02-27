<?php

namespace Codeception\Module\Yii1;

class TransactionWrapper
{
    /**
     * @var \components\db\Connection
     */
    private $db;

    public function __construct(\components\db\Connection $db)
    {
        $this->db = $db;
    }

    public function start()
    {
        $this->db->beginTransaction();
    }

    public function rollback()
    {
        if ($this->db->getPdoInstance()->inTransaction()) {
            $this->db->getPdoInstance()->rollBack();
        }
        $this->db->getCurrentTransaction();
    }
}
