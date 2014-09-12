<?php
namespace Breadshop;

class OrderQuantityAccumulator
{
    /** @var OutboundEvents */
    private $events;

    private $orderQuantity = 0;

    public function __construct(OutboundEvents $events)
    {
        $this->events = $events;
    }

    public function addOrderQuantity($orderQuantity)
    {
        $this->orderQuantity += $orderQuantity;
    }

    public function doneAccumulating()
    {
        $this->events->placeWholesaleOrder($this->orderQuantity);
    }
}
