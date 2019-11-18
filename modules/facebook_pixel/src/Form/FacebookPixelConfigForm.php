<?php

namespace Drupal\facebook_pixel\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class FacebookPixelConfigForm.
 *
 * @package Drupal\facebook_pixel\Form
 */
class FacebookPixelConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'facebook_pixel.facebookpixelconfig',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'facebook_pixel_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('facebook_pixel.facebookpixelconfig');
    $form['facebook_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Facebook ID'),
      '#description' => $this->t('Facebook ID'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('facebook_id'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('facebook_pixel.facebookpixelconfig')
      ->set('facebook_id', $form_state->getValue('facebook_id'))
      ->save();
  }

}
