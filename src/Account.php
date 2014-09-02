<?php
namespace Breadshop;

class Account
{
    private $balance = 0;
    private $orders = array();

    public function getBalance()
    {
        return $this->balance;
    }

    /** @return int */
    public function deposit($creditAmount)
    {
        $this->balance += $creditAmount;
        return $this->balance;
    }

    public function addOrder($orderId, $amount)
    {
        $this->orders[$orderId] = $amount;
    }

    /** @return int */
    public function cancelOrder($orderId)
    {
        if (isset($this->orders[$orderId])) {
            $amount = $this->orders[$orderId];
            unset($this->orders[$orderId]);

            return $amount;
        }

        return null;
    }
}
