function webpay_mode(commerce_code, private_key, public_cert, webpay_cert){

    var private_key_js = private_key.replace(/<br\s*\/?>/mg,"\n");
    var public_cert_js = public_cert.replace(/<br\s*\/?>/mg,"\n");
    var webpay_cert_js = webpay_cert.replace(/<br\s*\/?>/mg,"\n");

    var select = document.getElementById("woocommerce_transbank_webpay_test_mode").value;
            
    if(select != "INTEGRACION"){
        document.getElementById("woocommerce_transbank_webpay_commerce_code").value = "";
        document.getElementById("woocommerce_transbank_webpay_private_key").value   = "";
        document.getElementById("woocommerce_transbank_webpay_public_cert").value   = "";
        document.getElementById("woocommerce_transbank_webpay_webpay_cert").value   = "";
    } else {
        document.getElementById("woocommerce_transbank_webpay_commerce_code").value = commerce_code;
        document.getElementById("woocommerce_transbank_webpay_private_key").value   = private_key_js;
        document.getElementById("woocommerce_transbank_webpay_public_cert").value   = public_cert_js;
        document.getElementById("woocommerce_transbank_webpay_webpay_cert").value   = webpay_cert_js;
    }
}