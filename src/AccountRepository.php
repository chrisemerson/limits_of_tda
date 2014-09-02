<?php
namespace Breadshop;

class AccountRepository
{
    private $accounts = array();

    public function __construct()
    {
    }

    public function addAccount($id, Account $newAccount)
    {
        $this->accounts[$id] = $newAccount;
    }

    /** @return Account */
    public function getAccount($accountId)
    {
        if (isset($this->accounts[$accountId])) {
            return $this->accounts[$accountId];
        }

        return null;
    }
}
