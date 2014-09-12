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
        $this->accountRepository = new AccountRepository($events);
        $this->events = $events;
    }

    public function createAccount($id)
    {
        $this->accountRepository->addAccount($id);
    }

    public function deposit($accountId, $creditAmount)
    {
        $this->accountRepository->depositToAccount($accountId, $creditAmount);
    }

    public function placeOrder($accountId, $orderId, $amount)
    {
        $this->accountRepository->placeOrderOnAccount($accountId, $orderId, $amount, self::PRICE_OF_BREAD);
    }

    public function cancelOrder($accountId, $orderId)
    {
        $this->accountRepository->cancelOrder($accountId, $orderId, self::PRICE_OF_BREAD);
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
