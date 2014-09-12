<?php
namespace Breadshop;

class Account
{
    /** @var OutboundEvents */
    private $events;

    private $balance = 0;
    private $orders = array();

    public function __construct(OutboundEvents $events)
    {
        $this->events = $events;
    }

    public function deposit($creditAmount, $accountId)
    {
        $this->balance += $creditAmount;
        $this->events->newAccountBalance($accountId, $this->balance);
    }

    public function addOrder($orderId, $amount, $accountId, $priceOfBread)
    {
        $cost = $amount * $priceOfBread;

        if ($this->balance >= $cost) {
            $this->orders[$orderId] = $amount;
            $this->events->orderPlaced($accountId, $amount);

            $this->deposit(-$cost, $accountId);
        } else {
            $this->events->orderRejected($accountId);
        }
    }

    public function addOrderQuantity(OrderQuantityAccumulator $oqa)
    {
        foreach ($this->orders as $quantity) {
            $oqa->addOrderQuantity($quantity);
        }
    }

    public function fillOrders($remainingQuantity, $accountID, array $accounts)
    {
        foreach ($this->orders as $orderID => $orderQuantity) {
            if ($orderQuantity <= $remainingQuantity) {
                $fillQuantity = $orderQuantity;
            } else {
                $fillQuantity = $remainingQuantity;
            }

            $remainingQuantity -= $fillQuantity;
            $this->orders[$orderID] -= $fillQuantity;

            $this->events->orderFilled($accountID, $orderID, $fillQuantity);

            if ($this->orders[$orderID] == 0) {
                unset($this->orders[$orderID]);
            }
        }

        if (next($accounts)) {
            self::onWholesaleOrder($remainingQuantity, $accounts);
        }
    }

    public static function onWholesaleOrder($remainingQuantity, array $accounts)
    {
        if (($accountID = key($accounts)) && ($account = current($accounts))) {
            $account->fillOrders($remainingQuantity, $accountID, $accounts);
        }
    }

    public function cancelOrder($orderId, $accountId, $priceOfBread)
    {
        if (isset($this->orders[$orderId])) {
            $cancelledQuantity = $this->orders[$orderId];

            unset($this->orders[$orderId]);

            $this->events->orderCancelled($accountId, $orderId);
            $this->deposit($cancelledQuantity * $priceOfBread, $accountId);
        } else {
            $this->events->orderNotFound($accountId, $orderId);
        }
    }
}
