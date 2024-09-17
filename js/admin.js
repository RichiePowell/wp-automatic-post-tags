jQuery(document).ready(function ($) {
  $("#automatic-post-tags-refresh").on("click", function (e) {
    e.preventDefault();

    // Disable the button.
    $(this).prop("disabled", true).text(AutomaticPostTags.refreshing_text);

    var data = {
      action: "get_suggested_tags",
      nonce: AutomaticPostTags.nonce,
      post_content: $("#content").val(),
    };

    $.post(AutomaticPostTags.ajax_url, data, function (response) {
      if (response.success) {
        var tags = response.data;
        var checkboxes = "";
        $.each(tags, function (index, tag) {
          checkboxes +=
            '<label><input type="checkbox" name="automatic_post_tags[]" value="' +
            tag +
            '"> ' +
            tag +
            "</label><br>";
        });
        $("#automatic-post-tags-checkboxes").html(checkboxes);
      } else {
        $("#automatic-post-tags-checkboxes").html(
          "<p>" + response.data + "</p>"
        );
      }

      // Enable the button.
      $("#automatic-post-tags-refresh")
        .prop("disabled", false)
        .text(AutomaticPostTags.refresh_button_text);
    });
  });
});
