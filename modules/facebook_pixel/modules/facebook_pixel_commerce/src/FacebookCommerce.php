<?php

namespace Drupal\facebook_pixel_commerce;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\RounderInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;

/**
 * Helper methods for facebook_pixel_commerce module.
 *
 * @package Drupal\facebook_pixel_commerce
 */
class FacebookCommerce implements FacebookCommerceInterface {

  /**
   * The rounder service.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * FacebookCommerce constructor.
   *
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   The price rounder.
   */
  public function __construct(RounderInterface $rounder) {
    $this->rounder = $rounder;
  }

  /**
   * Build the Facebook object for orders.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order object.
   *
   * @return array
   *   The data array for an order.
   */
  public function getOrderData(OrderInterface $order) {
    $contents = [];
    $content_ids = [];

    $data = [
      'value' => $this->rounder->round($order->getTotalPrice())->getNumber(),
      'currency' => $order->getTotalPrice()->getCurrencyCode(),
      'num_items' => count($order->getItems()),
      'content_name' => 'order',
      'content_type' => 'product',
    ];

    foreach ($order->getItems() as $order_item) {
      $item_data = $this->getOrderItemData($order_item);
      if (!empty($item_data['contents'][0])) {
        $contents[] = $item_data['contents'][0];
        $content_ids[] = $item_data['contents'][0]['id'];
      }
    }

    if (!empty($contents)) {
      $data['contents'] = $contents;
      $data['content_ids'] = $content_ids;
    }

    return $data;
  }

  /**
   * Build the Facebook object for order items.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item object.
   *
   * @return array
   *   The data array for an order item.
   */
  public function getOrderItemData(OrderItemInterface $order_item) {
    $entity = $order_item->getPurchasedEntity();
    $data = [
      'value' => $this->rounder->round($order_item->getUnitPrice())->getNumber(),
      'currency' => $order_item->getTotalPrice()->getCurrencyCode(),
      'order_id' => $order_item->getOrderId(),
      'content_ids' => [$entity->id()],
      'content_name' => $entity->getOrderItemTitle(),
      'content_type' => 'product',
      'contents' => [
        [
          'id' => $entity->id(),
          'quantity' => $order_item->getQuantity(),
        ],
      ],
    ];

    // Use the SKU and title for product variations.
    if ($entity instanceof ProductVariationInterface) {
      $data['content_ids'] = [$entity->getSku()];
      $data['content_name'] = $entity->getTitle();
      $data['contents'][0]['id'] = $entity->getSku();
    }

    return $data;
  }

}
