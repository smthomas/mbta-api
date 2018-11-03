<?php

namespace Drupal\mbta_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class MBTAAdminForm.
 */
class MBTAAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mbta_api.mbtaadmin',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mbta_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('mbta_api.mbtaadmin');
    $form['mbta_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MBTA API Key'),
      '#description' => $this->t('The MBTA API Key to use in the API Requests'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('mbta_api_key'),
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

    $this->config('mbta_api.mbtaadmin')
      ->set('mbta_api_key', $form_state->getValue('mbta_api_key'))
      ->save();
  }

}
