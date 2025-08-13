var apiUrl = syspay_params.baseUrl.concat('api/v1/public/');
syspay.tokenizer.setBaseUrl(apiUrl);
syspay.tokenizer.setPublicKey(syspay_params.publicKey);
var callback = function(response) {
if (null === response.error) {
jQuery('<input type="hidden" name="sys-tok" />')
.val(response.token)
.appendTo('form.checkout');
jQuery('form.checkout').submit();
} else {
alert('An error occured: ' + response.message + '(Code: ' + response.error + ')');
return false;
}
};
jQuery(function() {
var fc = 'form.checkout',
pl = 'button[type="submit"][name="woocommerce_checkout_place_order"]';
jQuery(fc).on( 'click', pl, function(e){
e.preventDefault();
if(jQuery('form[name="checkout"] input[name="payment_method"]:checked').val() == 'syspay'){
console.log("Using my gateway");
var today = new Date();
var currentYear = "" + today.getFullYear();
var year = currentYear.substring(0, 2)+""+jQuery('.expiry-year').val();
jQuery('.expiry-year').val(year);
console.log( apiUrl + syspay_params.publicKey + jQuery('.card-number').val() + jQuery('.name').val() + jQuery('.expiry-month').val() + jQuery('.expiry-year').val() + jQuery('.cvc').val());
syspay.tokenizer.tokenizeCard({
number:     jQuery('.card-number').val().replace(/\s/g,''),
cardholder: jQuery('.name').val(),
exp_month:  jQuery('.expiry-month').val(),
exp_year:   jQuery('.expiry-year').val(),
cvc:        jQuery('.cvc').val()
}, callback);
}else{
console.log("Not using my gateway. Proceed as usual");
jQuery('form.checkout').submit();
}
});
});