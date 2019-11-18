/**
 * @file
 * Contains the definition of the behaviour entityShareClientFullPager.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Attaches the JS behavior to remove unneeded query parameters.
   *
   * Those parameters are added because the pager is loaded in an Ajax request.
   *
   * @see https://www.drupal.org/node/3064252
   * @see https://www.drupal.org/node/2504709
   */
  Drupal.behaviors.entityShareClientFullPager = {
    attach: function (context, settings) {
      $(context).find('.js-pager__items a').once('js--full-pager').each(function () {
        var href = $(this).attr('href');
        href = removeUrlParameter(href, 'ajax_form');
        href = removeUrlParameter(href, '_wrapper_format');
        $(this).attr('href', href);
      });
    }
  };

  /**
   * Helper function to remove a query parameter from a string.
   *
   * @param {string} url
   *   The URL to remove the query parameter.
   * @param {string} parameter
   *   The query parameter to remove.
   *
   * @return {string}
   *   The URL without the query parameter.
   */
  function removeUrlParameter(url, parameter) {
    return url
      .replace(new RegExp('([\?&]{1})' + parameter + '=[^&]*'), '$1') // eslint-disable-line no-useless-escape
      .replace(/\?&/, '?')
      .replace(/&&/, '&')
      .replace(/&$/, '');
  }

})(jQuery, Drupal);
