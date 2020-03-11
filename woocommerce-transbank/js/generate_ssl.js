jQuery(function($) {
  $("#tbk_ssl_download").click(function(e) {
    $('#woocommerce_transbank_webpay_test_mode').val('PRODUCCION');
    $('#woocommerce_transbank_webpay_public_cert').val($('#certout').val().slice(0, -1));
    $('#woocommerce_transbank_webpay_private_key').val($('#pkeyout').val().slice(0, -1));

    /* var element = document.createElement('a');
    var csrout = $('#csrout').val().slice(0, -1);
    var filename = $('#woocommerce_transbank_webpay_commerce_code').val().slice(0, -1) + '.ctr';

    element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(csrout));
    element.setAttribute('download', filename);

    element.style.display = 'none';
    document.body.appendChild(element);

    element.click();
    document.body.removeChild(element); */

    var $temp = $("<div>");
    $("body").append($temp);
    $temp.attr("contenteditable", true)
          .html($("#tb_ssl").text()).select()
          .on("focus", function() { document.execCommand('selectAll', false, null) })
          .focus();
    document.execCommand("copy");
    $temp.remove();

    e.preventDefault();
  });


  $("#gen_ssl").click( function(e) {
    $('#tb_productivo').modal('hide');
    var commerce_code = $('#commerce_code_input').val();
    $('#commerce_code').val(commerce_code);

    $.post(ajax_object.ajax_url, {action: 'generate_ssl', commerce_code: commerce_code}, function(response){

      if(response.status == "OK") {
        $('#tb_ssl').modal('show');

        $('#certout').val(response.message.certout);
        $('#pkeyout').val(response.message.pkeyout);
        $('#csrout').html(response.message.csrout);
        $('#commerce_code').html(response.message.commerce_code);

      } else {

        /* $("#row_response_status_text").addClass("label-danger").text("ERROR").show();
        $("#row_error_message_text").append(response.message.error);
        $("#row_error_detail_text").append('<pre>'+response.message.detail+'</pre>');

        $("#row_error_message").show();
        $("#row_error_detail").show(); */
      }

    },'json');

    e.preventDefault();
  });
});
