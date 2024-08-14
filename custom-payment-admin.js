jQuery(document).ready(function ($) {
  var customUploader;

  $("body").on(
    "click",
    "#woocommerce_Woo_Payp_payment_image_button",
    function (e) {
      e.preventDefault();

      if (customUploader) {
        customUploader.open();
        return;
      }

      customUploader = wp.media.frames.file_frame = wp.media({
        title: "Choose Image",
        button: {
          text: "Choose Image",
        },
        multiple: false,
      });

      customUploader.on("select", function () {
        var attachment = customUploader
          .state()
          .get("selection")
          .first()
          .toJSON();
        $("#woocommerce_Woo_Payp_payment_image").val(attachment.url);
      });

      customUploader.open();
    }
  );
});
