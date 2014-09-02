<?php
namespace Breadshop;

use PHPUnit_Framework_TestCase;

class BreadShopTest extends PHPUnit_Framework_TestCase
{
    /** @var OutboundEvents */
    private $events;

    /** @var BreadShop */
    private $breadShop;

    const ACCOUNT_ID_ONE = 1;
    const ACCOUNT_ID_TWO = 2;
    const ORDER_ID_ONE = 1;
    const ORDER_ID_TWO = 2;

    public function setUp()
    {
        $this->events = $this->getMock('Breadshop\OutboundEvents');
        $this->breadShop = new Breadshop($this->events);
    }

    public function tearDown()
    {
        $this->breadShop = null;
        $this->events = null;
    }

    public function testCreateAnAccount()
    {
        $this->expectAccountCreationSuccess(self::ACCOUNT_ID_ONE);

        $this->breadShop->createAccount(self::ACCOUNT_ID_ONE);
    }

    public function testDepositSomeMoney()
    {
        $this->createAccount(self::ACCOUNT_ID_ONE);

        $depositAmount = 300;
        $this->expectNewBalance(self::ACCOUNT_ID_ONE, $depositAmount);
        $this->breadShop->deposit(self::ACCOUNT_ID_ONE, $depositAmount);
    }

    public function testRejectDepositsForNonexistentAccounts()
    {
        $nonExistentAccountId = -5;
        $this->expectAccountNotFound($nonExistentAccountId);

        $this->breadShop->deposit($nonExistentAccountId, 4000);
    }

    public function testDepositsAddUp()
    {
        $this->createAccountWithBalance(self::ACCOUNT_ID_ONE, 300);

        $this->expectNewBalance(self::ACCOUNT_ID_ONE, 600);
        $this->breadShop->deposit(self::ACCOUNT_ID_ONE, 300);
    }

    public function testPlaceAnOrderSucceedsIfThereIsEnoughMoney()
    {
        $this->createAccountWithBalance(self::ACCOUNT_ID_ONE, 500);

        $this->expectOrderPlaced(self::ACCOUNT_ID_ONE, 40);
        $this->expectNewBalance(self::ACCOUNT_ID_ONE, 500 - ($this->cost(40)));

        $this->breadShop->placeOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, 40);
    }

    public function testCannotPlaceOrderForNonexistentAccount()
    {
        $this->expectAccountNotFound(-5);
        $this->breadShop->placeOrder(-5, self::ORDER_ID_ONE, 40);
    }

    public function testCannotPlaceAnOrderForMoreThanOneAccountCanAfford()
    {
        $this->createAccountWithBalance(self::ACCOUNT_ID_ONE, 500);

        // 42 * 12 = 504
        $this->expectOrderRejected(self::ACCOUNT_ID_ONE);
        $this->breadShop->placeOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, 42);
    }

    public function testCancelAnOrderByID()
    {
        $balance = 500;
        $this->createAccountWithBalance(self::ACCOUNT_ID_ONE, $balance);

        $amount = 40;
        $this->placeOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $amount, $balance);

        $this->expectOrderCancelled(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE);
        $this->expectNewBalance(self::ACCOUNT_ID_ONE, $balance);

        $this->breadShop->cancelOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE);
    }

    public function testCannotCancelAnOrderForNonexistentAccount()
    {
        $this->expectAccountNotFound(-5);

        $this->breadShop->cancelOrder(-5, self::ORDER_ID_ONE);
    }

    public function testCannotCancelANonexistentOrder()
    {
        $this->createAccount(self::ACCOUNT_ID_ONE);

        $this->expectOrderNotFound(-5);
        $this->breadShop->cancelOrder(self::ACCOUNT_ID_ONE, -5);
    }

    public function testCancellingAnOrderAllowsBalanceToBeReused()
    {
        $balance = 500;
        $this->createAccountWithBalance(self::ACCOUNT_ID_ONE, $balance);

        $amount = 40;
        $this->placeOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $amount, $balance);
        $this->cancelOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $balance);

        // it's entirely possible that the balance in the resulting event doesn't match the internal
        // state of the system, so we ensure the balance has really been restored
        // by trying to place a new order with it.
        $this->expectOrderPlaced(self::ACCOUNT_ID_ONE, $amount);
        $this->expectNewBalance(self::ACCOUNT_ID_ONE, $balance - ($this->cost($amount)));
        $this->breadShop->placeOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_TWO, $amount);
    }

    /**
     * @group ObjectiveA
     */
    public function testAnEmptyShopPlacesAnEmptyWholesaleOrder()
    {
        $this->expectWholesaleOrder(0);

        $this->breadShop->placeWholesaleOrder();
    }

    /**
     * @group ObjectiveA
     */
    public function testWholesaleOrdersAreMadeForTheSumOfTheQuantitiesOfOutstandingOrdersInOneAccount()
    {
        $this->expectWholesaleOrder(40 + 55);

        $balance = $this->cost(40 + 55);
        $this->createAccountWithBalance(self::ACCOUNT_ID_ONE, $balance);
        $this->placeOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, 40, $balance);
        $this->placeOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_TWO, 55, $balance - $this->cost(40));

        $this->breadShop->placeWholesaleOrder();
    }

    /**
     * @group ObjectiveA
     */
    public function testWholesaleOrdersAreMadeForTheSumOfTheQuantitiesOfOutstandingOrdersAcrossAccounts()
    {
        $this->expectWholesaleOrder(40 + 55);

        $this->createAccountAndPlaceOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, 40);
        $this->createAccountAndPlaceOrder(self::ACCOUNT_ID_TWO, self::ORDER_ID_TWO, 55);

        $this->breadShop->placeWholesaleOrder();
    }

    /**
     * @group ObjectiveB
     */
    public function testArrivalOfWholesaleOrderTriggerFillsOfASingleOutstandingOrder()
    {
        $quantity = 40;
        $this->createAccountAndPlaceOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantity);

        $this->expectOrderFilled(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantity);
        $this->breadShop->onWholesaleOrder($quantity);
    }

    /**
     * @group ObjectiveB
     */
    public function testWholesaleOrderQuantitiesMightOnlyFillAnOutstandingOrderPartially()
    {
        $quantity = 40;
        $this->createAccountAndPlaceOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantity);

        $wholesaleOrderQuantity = $quantity / 2;
        $this->expectOrderFilled(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $wholesaleOrderQuantity);
        $this->breadShop->onWholesaleOrder($wholesaleOrderQuantity);
    }

    /**
     * @group ObjectiveB
     */
    public function testAnOrderCanBeFilledByTwoConsecutiveWholesaleOrders()
    {
        $quantity = 40;
        $this->createAccountAndPlaceOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantity);

        $wholesaleOrderQuantity = $quantity / 2;
        $this->expectOrderFilled(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $wholesaleOrderQuantity);
        $this->breadShop->onWholesaleOrder($wholesaleOrderQuantity);

        $this->expectOrderFilled(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $wholesaleOrderQuantity);
        $this->breadShop->onWholesaleOrder($wholesaleOrderQuantity);
    }

    /**
     * @group ObjectiveB
     */
    public function testOrdersDoNotOverfill()
    {
        $quantity = 40;
        $wholesaleOrderQuantity = 42;
        $this->createAccountAndPlaceOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantity);

        $this->expectOrderFilled(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantity);
        $this->breadShop->onWholesaleOrder($wholesaleOrderQuantity);
    }

    /**
     * @group ObjectiveB
     */
    public function testFullyFilledOrdersAreRemovedAndThereforeCannotBeCancelled()
    {
        $quantity = 40;
        $this->createAccountAndPlaceOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantity);

        $this->expectOrderFilled(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantity);
        $this->breadShop->onWholesaleOrder($quantity);

        $this->expectOrderNotFound(self::ORDER_ID_ONE);
        $this->breadShop->cancelOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE);
    }

    /**
     * @group ObjectiveB
     */
    public function testOrdersDoNotOverfillAcrossTwoWholesaleOrders()
    {
        $quantity = 40;
        $wholesaleOrderQuantityOne = 21;
        $this->createAccountAndPlaceOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantity);

        $this->expectOrderFilled(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $wholesaleOrderQuantityOne);
        $this->breadShop->onWholesaleOrder($wholesaleOrderQuantityOne);

        $wholesaleOrderQuantityTwo = 33; // This will fill the remaining quantity
        $this->expectOrderFilled(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantity - $wholesaleOrderQuantityOne);
        $this->breadShop->onWholesaleOrder($wholesaleOrderQuantityTwo);
    }

    /**
     * @group ObjectiveB
     */
    public function testOrdersAcrossDifferentAccountsAreFilled()
    {
        $quantityOne = 40;
        $quantityTwo = 55;
        $this->createAccountAndPlaceOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantityOne);
        $this->createAccountAndPlaceOrder(self::ACCOUNT_ID_TWO, self::ORDER_ID_TWO, $quantityTwo);

        $this->expectOrderFilled(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantityOne);
        $this->expectOrderFilled(self::ACCOUNT_ID_TWO, self::ORDER_ID_TWO, $quantityTwo);

        $this->breadShop->onWholesaleOrder($quantityOne + $quantityTwo);
    }

    /**
     * @group ObjectiveB
     */
    public function testOrdersFillInAConsistentOrderAcrossDifferentAccounts()
    {
        $quantityOne = 40;
        $quantityTwo = 55;
        $this->createAccountAndPlaceOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantityOne);
        $this->createAccountAndPlaceOrder(self::ACCOUNT_ID_TWO, self::ORDER_ID_TWO, $quantityTwo);

        $this->expectOrderFilled(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantityOne);
        $secondFillQuantity = 8;
        $this->expectOrderFilled(self::ACCOUNT_ID_TWO, self::ORDER_ID_TWO, $secondFillQuantity);

        $this->breadShop->onWholesaleOrder($quantityOne + $secondFillQuantity);
    }

    /**
     * @group ObjectiveB
     */
    public function testOrdersFillInAConsistentOrderAcrossOrdersInTheSameAccount()
    {
        $quantityOne = 40;
        $quantityTwo = 50;
        $balance = $this->cost($quantityOne) + $this->cost($quantityTwo);
        $this->createAccountWithBalance(self::ACCOUNT_ID_ONE, $balance);
        $this->placeOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantityOne, $balance);
        $this->placeOrder(self::ACCOUNT_ID_ONE, self::ORDER_ID_TWO, $quantityTwo, $balance - $this->cost($quantityOne));

        $this->expectOrderFilled(self::ACCOUNT_ID_ONE, self::ORDER_ID_ONE, $quantityOne);
        $secondFillQuantity = 8;
        $this->expectOrderFilled(self::ACCOUNT_ID_ONE, self::ORDER_ID_TWO, $secondFillQuantity);

        $this->breadShop->onWholesaleOrder($quantityOne + $secondFillQuantity);
    }

    private function cost($quantity)
    {
        return $quantity * 12;
    }

    private function expectOrderFilled($accountId, $orderId, $quantity)
    {
        $this->events
            ->expects($this->once())
            ->method('orderFilled')
            ->with($accountId, $orderId, $quantity);
    }

    private function cancelOrder($accountId, $orderId, $expectedBalanceAfterCancel)
    {
        $this->expectOrderCancelled($accountId, $orderId);
        $this->expectNewBalance($accountId, $expectedBalanceAfterCancel);

        $this->breadShop->cancelOrder($accountId, self::ORDER_ID_ONE);
    }

    private function expectOrderNotFound($orderId)
    {
        $this->events
            ->expects($this->once())
            ->method('orderNotFound')
            ->with(self::ACCOUNT_ID_ONE, $orderId);
    }

    private function expectOrderCancelled($accountId, $orderId)
    {
        $this->events
            ->expects($this->once())
            ->method('orderCancelled')
            ->with($accountId, $orderId);
    }

    private function placeOrder($accountId, $orderId, $amount, $balanceBefore)
    {
        $this->expectOrderPlaced($accountId, $amount);
        $this->expectNewBalance($accountId, $balanceBefore - $this->cost($amount));
        $this->breadShop->placeOrder($accountId, $orderId, $amount);
    }

    private function expectOrderRejected($accountId)
    {
        $this->events
            ->expects($this->once())
            ->method('orderRejected')
            ->with($accountId);
    }

    private function expectOrderPlaced($accountId, $amount)
    {
        $this->events
            ->expects($this->once())
            ->method('orderPlaced')
            ->with($accountId, $amount);
    }

    private function createAccountWithBalance($accountId, $initialBalance)
    {
        $this->createAccount($accountId);

        $this->expectNewBalance($accountId, $initialBalance);
        $this->breadShop->deposit($accountId, $initialBalance);
    }

    private function expectAccountNotFound($accountId)
    {
        $this->events
            ->expects($this->once())
            ->method('accountNotFound')
            ->with($accountId);
    }

    private function createAccount($accountId)
    {
        $this->expectAccountCreationSuccess($accountId);

        $this->breadShop->createAccount($accountId);
    }

    private function expectNewBalance($accountId, $newBalanceAmount)
    {
        $this->events
            ->expects($this->once())
            ->method('newAccountBalance')
            ->with($accountId, $newBalanceAmount);
    }

    private function expectAccountCreationSuccess($accountId)
    {
        $this->events
            ->expects($this->once())
            ->method('accountCreatedSuccessfully')
            ->with($accountId);
    }

    private function createAccountAndPlaceOrder($accountId, $orderId, $amount)
    {
        $balance = $this->cost($amount);
        $this->createAccountWithBalance($accountId, $balance);
        $this->placeOrder($accountId, $orderId, $amount, $balance);
    }

    private function expectWholesaleOrder($quantity)
    {
        $this->events
            ->expects($this->once())
            ->method('placeWholesaleOrder')
            ->with($quantity);
    }
}
