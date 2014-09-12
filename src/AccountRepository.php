<?php
namespace Breadshop;

class AccountRepository
{
    /** @var OutboundEvents */
    private $events;

    private $accounts = array();

    public function __construct(OutboundEvents $events)
    {
        $this->events = $events;
    }

    public function addAccount($id)
    {
        $this->accounts[$id] = new Account($this->events);
        $this->events->accountCreatedSuccessfully($id);
    }

    public function placeOrderOnAccount($accountId, $orderId, $amount, $priceOfBread)
    {
        $account = $this->getAccount($accountId);

        if ($account != null) {
            $account->addOrder($orderId, $amount, $accountId, $priceOfBread);
        } else {
            $this->events->accountNotFound($accountId);
        }
    }

    public function depositToAccount($accountId, $creditAmount)
    {
        $account = $this->getAccount($accountId);

        if ($account != null) {
            $account->deposit($creditAmount, $accountId);
        } else {
            $this->events->accountNotFound($accountId);
        }
    }

    public function cancelOrder($accountId, $orderId, $priceOfBread)
    {
        $account = $this->getAccount($accountId);

        if ($account == null) {
            $this->events->accountNotFound($accountId);
        } else {
            $account->cancelOrder($orderId, $accountId, $priceOfBread);
        }
    }

    /** @return Account */
    private function getAccount($accountId)
    {
        if (isset($this->accounts[$accountId])) {
            return $this->accounts[$accountId];
        }

        return null;
    }
}
