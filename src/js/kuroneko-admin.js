/**
 * Description
 */

/* global KuronekoAdmin:false */

jQuery(document).ready(function ($) {

  'use strict';

  $('.kuroneko-message-dismiss').click(function(e){
    e.preventDefault();
    var $btn = $(this);
    $.get( $btn.attr('data-endpoint') ).done(function(response){
      if(response.success){
        $btn.parents('.kuroneko-message').remove();
      }else{
        window.alert('Error');
      }
    }).fail(function(response){
      window.alert( response.responseJSON.data );
    });
  });

  // Capture order
  var loading = false;
  $(document).on('click', '#kuroneko-tracking-change, #kuroneko-capture-order', function(e){
    e.preventDefault();
    if (loading) {
      // Do nothing.
      return;
    }

    var $button = $(this);
    var orderId = $button.attr( 'data-post-id' );
    var storedText = $button.text();
    var url = KuronekoAdmin.rest_route + '/payment/' + $button.attr('data-post-id');
    var method = 'kuroneko-capture-order' === $button.attr('id') ? 'POST' : 'PUT';
    loading = true;
    $button.text(KuronekoAdmin.loading);
    $button.addClass('disabled');
    $.ajax({
      url: url,
      method: method.toLowerCase(),
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', KuronekoAdmin.nonce);
      }
    }).done(function(response){
      var message = [ response.message, KuronekoAdmin.successMsg ];
      if ( window.confirm( message.join( "\n" ) ) ) {
        window.location.reload();
      }
    }).fail(function(response){
      var message = KuronekoAdmin.error;
      if ( response.responseJSON && response.responseJSON.message ) {
        message = response.responseJSON.message;
      }
      alert(message);
    }).always(function(){
      loading = false;
      $button.removeClass('disabled').text(storedText);
    });
  });

});
