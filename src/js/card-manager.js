/**
 * Description
 */

/*global KuronekoCC: true*/
/*global wpApiSettings: true*/


(function ($) {
  'use strict';

  $(document).on('click', '.delete-cc', function (e) {
    e.preventDefault();
    var $container = $(this).parents('.my_kuroneko_cc'),
        $row       = $(this).parents('tbody'),
        cardKey    = $(this).attr('data-card-id'),
        method     = $(this).attr('data-card-method');
    if (window.confirm(KuronekoCC.confirm)) {
      $container.block({
        message   : null,
        overlayCSS: {
          background: '#fff url(' + KuronekoCC.ajaxLoaderImage + ') no-repeat center',
          opacity   : 0.6
        }
      });
      $.ajax({
        url       : wpApiSettings.root + 'kuroneko-pay/v1/card/?kuroneko-card-key=' + cardKey + '&method=' + method,
        method    : 'delete',
        beforeSend: function (xhr) {
          xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
        }
      }).done(function (result) {
        if (result.success) {
          $('.my_kuroneko_cc tbody').html(result.html);
          if (!( $container.find('tbody tr').length )) {
            $('.my_kuroneko_cc').addClass('my_kuroneko_cc--empty');
          }
        } else {
          window.alert(result.message);
        }
      }).fail(function (response) {
        var message = KuronekoCC.failureDelete;
        if (response.responseJSON.message) {
          message = response.responseJSON.message;
        }
        window.alert(message);
      }).always(function () {
        $container.unblock();
      });
    }
  });

  /**
   * Show error message
   * @param {String} msg
   * @param {Boolean} [error]
   */
  function appendMessage(msg, error) {
    var $div = $('<div class="kuroneko-msg"><p></p><a href="#">&times;</a></div>');
    if (error) {
      $div.addClass('kuroneko-msg--error')
    }
    $div.find('p').text(msg);
    $div.on('click', 'a', function (e) {
      e.preventDefault();
      $div.remove();
    });
    $('#kuroneko-add-card-form').before($div);
    setTimeout(function () {
      $div.remove();
    }, 10000);
  }

  // Close btn
  $(document).on('click', '.kuroneko-msg a', function (e) {
    e.preventDefault();
    $(this).parents('.kuroneko-msg').remove();
  });

})(jQuery);
