jQuery(function($) {
  $("#tbk_ssl_download").click(function(e) {
    $('#woocommerce_transbank_webpay_test_mode').val('PRODUCCION');
    $('#woocommerce_transbank_webpay_public_cert').val($('#certout').val().slice(0, -1));
    $('#woocommerce_transbank_webpay_private_key').val($('#pkeyout').val().slice(0, -1));

    var element = document.createElement('a');
    var csrout = $('#csrout').val().slice(0, -1);
    var filename = $('#woocommerce_transbank_webpay_commerce_code').val().slice(0, -1) + '.ctr';

    element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(csrout));
    element.setAttribute('download', filename);

    element.style.display = 'none';
    document.body.appendChild(element);

    element.click();
    document.body.removeChild(element);

    e.preventDefault();
  });
});
