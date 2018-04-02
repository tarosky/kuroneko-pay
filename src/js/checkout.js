/**
 * Description
 */

(function ($) {
  'use strict';

  // Toggle card information button.
  $(document).on('click', '.kuroneko_cc-card-select', function (e) {
    var $wrapper = $(this).parents('payment_box').find('.kuroneko-cc-info');
    if (this.checked){
      $wrapper.addClass('toggle');
    } else {
      $wrapper.removeClass('toggle');
    }
  });

  // Token input.
  var $currentButton = null;
  $(document).on('click', '.kuroneko-cc-token-trigger', function(event){
    var $button = $(this);
    var $container = $button.parents('.payment_box');
    var $script = $('script.webcollect-token-api');
    // Clear current status.
    $button.removeClass('active');
    $button.next('input').val('');
    // Check if credit card must be saved.
    var values = {
      'trader-cd': '',
      'member-id': '',
      'auth-key': '',
      'auth-div': '',
      'check-sum': '',
      'opt-serv-div': '00',
      'card-key': null,
      'card-no': null,
      'card-owner': null,
      'card-exp': null,
      'last-credit-date': null
    };
    $.each(['trader-cd', 'member-id', 'auth-key', 'auth-div', 'check-sum' ], function(index, key){
      values[key]  = $button.attr('data-' + key);
    });
    // If "register card" is checked, change service div.
    var checkBox = $container.find('input.kuroneko-cc-save-card:checked');
    if ( checkBox.length ) {
      // Checked.
      values['opt-serv-div'] = '01';
    }
    // Using registered cards.
    var $registeredCard = $container.find('.kuroneko_cc-card-select:checked');
    if ( $registeredCard.length ) {
      var keys = ['card-key', 'card-no', 'card-owner', 'card-exp', 'last-credit-date'];
      for(var i = 0, l = keys.length; i < l; i++){
        values[keys[i]] = $registeredCard.attr('data-' + keys[i]);
      }
      values['opt-serv-div'] = '01';
    }

    // Set all values
    for(var prop in values){
      if (!values.hasOwnProperty(prop)) {
        continue;
      }
      var attr = 'data-' + prop;
      if(null === values[prop]){
        $script.removeAttr(attr);
      }else{
        $script.attr(attr, values[prop]);
      }
    }
    // Fires click event.
    $currentButton = $button;
    $('#create-token-launch').trigger('click');
  });


  // checkbox
  $(document).on('click', '.kuroneko_cc-card-select', function(e){
    var $wrapper = $(this).parents('.payment_box');
    var $button = $wrapper.find('.kuroneko-cc-token-trigger');
    $button.next('input').val('');
    $button.removeClass('active');
    $wrapper.find('.kuroneko-cc-info').toggleClass('toggle');
  });

  // Callback
  window.KuronekoCallback = function(){
    $currentButton.next('input').val( $('#webcollect-token').val() );
    $currentButton.addClass('active');
  }

})(jQuery);
