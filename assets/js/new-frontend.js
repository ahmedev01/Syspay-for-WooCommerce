var apiUrl = syspay_params.baseUrl.concat('api/v1/public/');
		syspay.tokenizer.setBaseUrl(apiUrl);

        // The public key can be found from your merchant backend
        syspay.tokenizer.setPublicKey(syspay_params.publicKey);

        // The following function will be called back once the card data will have been submitted to the SysPay API
        var callback = function(response) {
            if (null === response.error) {
                // The request was successfully processed. Add the returned token to the form before submitting it for real.
				//var token = response.token;
                //jQuery('#sys-tok').val(token);
				//document.getElementById('sys-tok').value = token ;
				// .attr('name','syspay-token')
				// .attr('value', response.token)
                // .appendTo('form.checkout');
				jQuery('#sys-tokn').val(response.token);
                // Submit the form
				//alert(jQuery('#sys-tokn').val());
				//jQuery( document.body ).trigger( 'update_checkout' );
                jQuery('form.checkout').submit();
				//return true;
				//$(pl).trigger('click');
            } else {
                alert('An error occured: ' + response.message + '(Code: ' + response.error + ')');
                return false;
            }
        };

        jQuery(function() {
            var fc = 'form.checkout',
            pl = 'button[type="submit"][name="woocommerce_checkout_place_order"]';

        jQuery(fc).on( 'click', pl, function(e){
            e.preventDefault(); // Disable "Place Order" button
            if(jQuery('form[name="checkout"] input[name="payment_method"]:checked').val() == 'syspay'){
        console.log("Using my gateway");
        alert( apiUrl + syspay_params.publicKey + jQuery('.card-number').val() + jQuery('.name').val() + jQuery('.expiry-month').val() + jQuery('.expiry-year').val() + jQuery('.cvc').val());
                syspay.tokenizer.tokenizeCard({
                    number:     jQuery('.card-number').val(),
                    cardholder: jQuery('.name').val(),
                    exp_month:  jQuery('.expiry-month').val(),
                    exp_year:   jQuery('.expiry-year').val(),
                    cvc:        jQuery('.cvc').val()
                }, callback);
    }else{
         console.log("Not using my gateway. Proceed as usual");
		 $(pl).trigger('click');
    }
                // if (result.value) {
                    // $(fc).off(); // Enable back "Place Order button
                    // $(pl).trigger('click'); // Trigger submit
                // } else {
                    // $('body').trigger('update_checkout'); // Refresh "Checkout review"
                // }
        });
    });
		
		function usingGateway(){
    console.log(jQuery("input[name='payment_method']:checked").val());
    
}