<?php
namespace Breadshop;

interface OutboundEvents
{
    public function accountCreatedSuccessfully($accountId);

    public function newAccountBalance($accountId, $newBalanceAmount);

    public function accountNotFound($accountId);

    public function orderPlaced($accountId, $amount);

    public function orderRejected($accountId);

    public function orderCancelled($accountId, $orderId);

    public function orderNotFound($accountId, $orderId);

    // For Objective A
    public function placeWholesaleOrder($quantity);

    // For Objective B
    public function orderFilled($accountId, $orderId, $quantity);
}
