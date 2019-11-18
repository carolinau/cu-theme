<?php

namespace Drupal\facebook_pixel_commerce\EventSubscriber;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\facebook_pixel\FacebookEventInterface;
use Drupal\facebook_pixel_commerce\FacebookCommerceInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CartSubscriber implements EventSubscriberInterface {

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The facebook event service.
   *
   * @var \Drupal\facebook_pixel\FacebookEventInterface
   */
  protected $facebookEvent;

  /**
   * The Facebook Commerce service.
   *
   * @var \Drupal\facebook_pixel_commerce\FacebookCommerce
   */
  protected $facebookCommerce;

  /**
   * Constructs a new OrderEventSubscriber object.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\facebook_pixel\FacebookEventInterface $facebook_event
   *   The Facebook Event service.
   * @param \Drupal\facebook_pixel_commerce\FacebookCommerceInterface $facebook_commerce
   *   The Facebook Commerce service.
   */
  public function __construct(CartProviderInterface $cart_provider, FacebookEventInterface $facebook_event, FacebookCommerceInterface $facebook_commerce) {
    $this->cartProvider = $cart_provider;
    $this->facebookEvent = $facebook_event;
    $this->facebookCommerce = $facebook_commerce;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CartEvents::CART_ENTITY_ADD => 'addToCart',
      'commerce_order.place.pre_transition' => 'finalizeCart',
    ];
  }

  /**
   * Add to cart event.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The cart entity add event.
   */
  public function addToCart(CartEntityAddEvent $event) {
    $data = $this->facebookCommerce->getOrderItemData($event->getOrderItem());
    // Trigger the AddToCart event and force a session to be used.
    $this->facebookEvent->addEvent('AddToCart', $data, TRUE);
  }

  /**
   * Finalize cart event.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event.
   */
  public function finalizeCart(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    $data = $this->facebookCommerce->getOrderData($order);
    $this->facebookEvent->addEvent('Purchase', $data);
  }

}
