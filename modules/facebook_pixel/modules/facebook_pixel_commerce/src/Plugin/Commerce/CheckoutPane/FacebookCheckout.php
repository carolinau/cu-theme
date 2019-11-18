<?php

namespace Drupal\facebook_pixel_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the completion message pane.
 *
 * Hijack the pane form subsystem so that we can
 * call our addEvent for initialize checkout on the
 * first stage of checkout.
 *
 * @CommerceCheckoutPane(
 *   id = "facebook_checkout",
 *   label = @Translation("Facebook Checkout"),
 *   default_step = "order_information",
 * )
 */
class FacebookCheckout extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Only fire the FB event on page load.
    if (!$form_state->getTriggeringElement()) {
      /** @var \Drupal\facebook_pixel\FacebookEvent $facebook_event */
      $facebook_event = \Drupal::service('facebook_pixel.facebook_event');
      /** @var \Drupal\facebook_pixel_commerce\FacebookCommerceInterface $facebook_commerce */
      $facebook_commerce = \Drupal::service('facebook_pixel_commerce.facebook_commerce');
      $data = $facebook_commerce->getOrderData($this->order);
      $facebook_event->addEvent('InitiateCheckout', $data);
    }
    return [];
  }

}
