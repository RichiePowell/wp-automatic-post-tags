jQuery(document).ready(function ($) {
  let post_content;

  // Define a function to fetch post content
  function fetchPostContent() {
    if (
      typeof wp.data.select("core/block-editor").getEditedPostContent ===
      "function"
    ) {
      // For Gutenberg block editor
      post_content = wp.data.select("core/block-editor").getEditedPostContent();
      console.log("Using block editor content:", post_content); // Debugging check
    } else if (
      typeof wp.data.select("core/editor").getCurrentPost === "function"
    ) {
      // For classic editor
      post_content = wp.data.select("core/editor").getCurrentPost().content;
      console.log("Using classic editor content:", post_content); // Debugging check
    } else {
      console.error("Unable to retrieve post content. Unsupported editor.");
      return;
    }

    // If post content is available, perform the AJAX request
    if (post_content && post_content.length > 0) {
      var data = {
        action: "get_suggested_tags",
        nonce: AutomaticPostTags.nonce,
        post_content: post_content,
      };
      console.log("AJAX data:", data); // Debugging check

      // Perform the AJAX request to fetch suggested tags.
      $.post(AutomaticPostTags.ajax_url, data, function (response) {
        console.log("AJAX response:", response); // Debugging check

        if (response.success) {
          var tags = response.data;
          var suggestedHtml =
            '<div class="editor-post-taxonomies__flat-term-most-used"><h3 class="components-base-control__label editor-post-taxonomies__flat-term-most-used-label" style="margin-top:10px;">Suggested Tags</h3><ul role="list" class="editor-post-taxonomies__flat-term-most-used-list">';
          console.log(tags);
          $.each(tags, function (index, tag) {
            suggestedHtml +=
              '<li><button type="button" class="components-button is-link suggested-tag" data-tag="' +
              tag +
              '">' +
              tag +
              "</button></li>";
          });
          suggestedHtml += "</ul></div>";

          // Inject suggested tags after the "MOST USED" section
          $(".editor-post-taxonomies__flat-term-most-used").after(
            suggestedHtml
          );

          // Handle click event to insert the tag into the tag list in the block editor
          $(".suggested-tag").on("click", function (e) {
            e.preventDefault();
            var tagName = $(this).data("tag");

            // Get all available tags from the REST API
            $.get(
              `${AutomaticPostTags.ajax_url.replace(
                "admin-ajax.php",
                ""
              )}wp/v2/tags?per_page=100`,
              function (availableTags) {
                // Check if the tag exists in the available tags
                var tag = availableTags.find(function (t) {
                  return t.name === tagName;
                });

                if (tag) {
                  // Get the current tag IDs
                  var currentTagIds = wp.data
                    .select("core/editor")
                    .getEditedPostAttribute("tags");

                  // Add the new tag ID
                  var newTagIds = [...currentTagIds, tag.id];

                  // Dispatch the updated tag IDs to the editor
                  wp.data.dispatch("core/editor").editPost({
                    tags: newTagIds,
                  });

                  console.log("Tag added:", tagName); // Debugging check
                } else {
                  console.error("Tag not found:", tagName); // Debugging check
                }
              }
            );
          });
        } else {
          $(".editor-post-taxonomies__flat-term-most-used").after(
            "<p>" + response.data + "</p>"
          );
        }
      });
    } else {
      $(".editor-post-taxonomies__flat-term-most-used").after(
        "<p>Post content is empty.</p>"
      );
    }
  }

  // Use an interval to wait until the post content is fully loaded
  var contentInterval = setInterval(function () {
    fetchPostContent();
    if (post_content && post_content.length > 0) {
      clearInterval(contentInterval); // Stop checking once we have content
    }
  }, 500); // Check every 500ms
});
