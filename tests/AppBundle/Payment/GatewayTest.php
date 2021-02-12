<?php

namespace Tests\AppBundle\Payment;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Payment\Gateway;
use AppBundle\Payment\GatewayResolver;
use AppBundle\Service\MercadopagoManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Stripe;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Prophecy\Argument;

class GatewayTest extends TestCase
{
    use ProphecyTrait;

    private $eventRecorder;
    private $stripeManager;

    private $handler;

    public function setUp(): void
    {
        $this->eventRecorder = $this->prophesize(RecordsMessages::class);
        $this->stripeManager = $this->prophesize(StripeManager::class);
        $this->mercadopagoManager = $this->prophesize(MercadopagoManager::class);
        $this->gatewayResolver = $this->prophesize(GatewayResolver::class);

        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->messageBus
            ->dispatch(Argument::type('object'), Argument::type('array'))
            ->will(function ($args) {
                return new Envelope($args[0]);
            });

        $this->gateway = new Gateway(
            $this->gatewayResolver->reveal(),
            $this->stripeManager->reveal(),
            $this->mercadopagoManager->reveal(),
            $this->messageBus->reveal()
        );
    }

    public function testAuthorizeLegacy()
    {
        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_CART);

        $charge = Stripe\Charge::constructFrom([
            'id' => 'ch_123456',
            'captured' => true,
        ]);

        $order = new Order();
        $order->addPayment($payment);

        $this->stripeManager
            ->authorize($payment)
            ->willReturn($charge);

        $this->gateway->authorize($payment, ['token' => 'tok_12345678']);

        $this->assertEquals('ch_123456', $payment->getCharge());
    }

    public function testAuthorizeWithPaymentIntent()
    {
        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_CART);

        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_12345678',
            'status' => 'requires_source_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
            'client_secret' => ''
        ]);
        $payment->setPaymentIntent($paymentIntent);

        $order = new Order();
        $order->addPayment($payment);

        $this->stripeManager
            ->confirmIntent($payment)
            ->willReturn($paymentIntent)
            ->shouldBeCalled();

        $this->gateway->authorize($payment, ['token' => 'pi_12345678']);
    }

    public function testAuthorizeWithPaymentIntentMismatch()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment Intent mismatch');

        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_CART);

        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_12345678',
            'status' => 'requires_source_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
            'client_secret' => ''
        ]);
        $payment->setPaymentIntent($paymentIntent);

        $order = new Order();
        $order->addPayment($payment);

        $this->stripeManager
            ->confirmIntent($payment)
            ->shouldNotBeCalled();

        $this->gateway->authorize($payment, ['token' => 'pi_98765432']);
    }
}
