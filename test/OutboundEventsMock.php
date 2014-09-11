<?php
namespace Breadshop;

class OutboundEventsMock implements OutboundEvents
{
    private $expectedCalls = array();
    private $actualCalls = array();

    public function __construct()
    {
        $this->expectedCalls = array();
        $this->actualCalls = array();
    }

    public function accountCreatedSuccessfully($accountId)
    {
        $this->addActualCall(__METHOD__, func_get_args());
    }

    public function newAccountBalance($accountId, $newBalanceAmount)
    {
        $this->addActualCall(__METHOD__, func_get_args());
    }

    public function accountNotFound($accountId)
    {
        $this->addActualCall(__METHOD__, func_get_args());
    }

    public function orderPlaced($accountId, $amount)
    {
        $this->addActualCall(__METHOD__, func_get_args());
    }

    public function orderRejected($accountId)
    {
        $this->addActualCall(__METHOD__, func_get_args());
    }

    public function orderCancelled($accountId, $orderId)
    {
        $this->addActualCall(__METHOD__, func_get_args());
    }

    public function orderNotFound($accountId, $orderId)
    {
        $this->addActualCall(__METHOD__, func_get_args());
    }

    public function placeWholesaleOrder($quantity)
    {
        $this->addActualCall(__METHOD__, func_get_args());
    }

    public function orderFilled($accountId, $orderId, $quantity)
    {
        $this->addActualCall(__METHOD__, func_get_args());
    }

    private function addActualCall($methodName, array $args)
    {
        $methodName = str_replace(array('Breadshop\\', 'OutboundEventsMock::'), '', $methodName);

        $this->actualCalls[] = array('method' => $methodName, 'args' => $args);
    }

    public function expects($methodName, array $args)
    {
        $this->expectedCalls[] = array('method' => $methodName, 'args' => $args);
    }

    public function verify()
    {
        foreach ($this->expectedCalls as $expectedCall) {
            $expectedCall = $expectedCall['method'] . "(" . implode(", ", $expectedCall['args']) . ")";

            if (empty($this->actualCalls)) {
                throw new \PHPUnit_Framework_ExpectationFailedException(
                    "Method " . $expectedCall . " was expected to be called, but no further calls were made to the object"
                );
            } else {
                $nextActualCall = array_shift($this->actualCalls);

                $actualCall = $nextActualCall['method'] . "(" . implode(", ", $nextActualCall['args']) . ")";

                if ($actualCall != $expectedCall) {
                    throw new \PHPUnit_Framework_ExpectationFailedException(
                        "Method " . $expectedCall . " was expected to be called, but got " . $actualCall . " instead"
                    );
                }
            }
        }
    }
}