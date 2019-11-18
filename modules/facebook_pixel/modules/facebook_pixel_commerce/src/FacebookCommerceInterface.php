<?php

namespace Drupal\facebook_pixel_commerce;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;

/**
 * Interface FacebookCommerceInterface.
 *
 * @package Drupal\facebook_pixel_commerce
 */
interface FacebookCommerceInterface {

  /**
   * Build the Facebook object for orders.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order object.
   *
   * @return array
   *   The data array for an order.
   */
  public function getOrderData(OrderInterface $order);

  /**
   * Build the Facebook object for order items.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item object.
   *
   * @return array
   *   The data array for an order item.
   */
  public function getOrderItemData(OrderItemInterface $order_item);

}
