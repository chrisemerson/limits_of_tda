<?php
namespace Breadshop;

use BadMethodCallException;

class BreadShop
{
    const PRICE_OF_BREAD = 12;

    /** @var OutboundEvents */
    private $events;

    /** @var AccountRepository */
    private $accountRepository;

    public function __construct(OutboundEvents $events)
    {
        $this->accountRepository = new AccountRepository();
        $this->events = $events;
    }

    public function createAccount($id)
    {
        $newAccount = new Account();
        $this->accountRepository->addAccount($id, $newAccount);
        $this->events->accountCreatedSuccessfully($id);
    }

    public function deposit($accountId, $creditAmount)
    {
        $account = $this->accountRepository->getAccount($accountId);

        if ($account != null) {
            $newBalance = $account->deposit($creditAmount);
            $this->events->newAccountBalance($accountId, $newBalance);
        } else {
            $this->events->accountNotFound($accountId);
        }
    }

    public function placeOrder($accountId, $orderId, $amount)
    {
        $account = $this->accountRepository->getAccount($accountId);

        if ($account != null) {
            $cost = $amount * self::PRICE_OF_BREAD;

            if ($account->getBalance() >= $cost) {
                $account->addOrder($orderId, $amount);
                $newBalance = $account->deposit(-$cost);
                $this->events->orderPlaced($accountId, $amount);
                $this->events->newAccountBalance($accountId, $newBalance);
            } else {
                $this->events->orderRejected($accountId);
            }
        } else {
            $this->events->accountNotFound($accountId);
        }
    }

    public function cancelOrder($accountId, $orderId)
    {
        $account = $this->accountRepository->getAccount($accountId);

        if ($account == null) {
            $this->events->accountNotFound($accountId);
            return;
        }

        $cancelledQuantity = $account->cancelOrder($orderId);

        if ($cancelledQuantity == null) {
            $this->events->orderNotFound($accountId, $orderId);
            return;
        }

        $newBalance = $account->deposit($cancelledQuantity * self::PRICE_OF_BREAD);
        $this->events->orderCancelled($accountId, $orderId);
        $this->events->newAccountBalance($accountId, $newBalance);
    }

    public function placeWholesaleOrder()
    {
        throw new BadMethodCallException("Implement me in Objective A");
    }

    public function onWholesaleOrder($quantity)
    {
        throw new BadMethodCallException("Implement me in Objective B");
    }
}
