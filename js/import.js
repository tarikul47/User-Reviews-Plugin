jQuery(document).ready(function ($) {
  $("#urp-import-form").on("submit", function (e) {
    e.preventDefault();

    var formData = new FormData(this);
    formData.append("action", "urp_handle_file_upload_async");
    formData.append("security", urp_import.security);

    $.ajax({
      url: urp_import.ajax_url,
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      success: function (response) {
        if (response.success) {
          $("#import-progress-container").show();
          processChunks();
        } else {
          alert(response.data);
        }
      },
    });
  });

  function processChunks() {
    var totalChunks = $("#total_chunks").val();

    $.ajax({
      url: urp_import.ajax_url,
      type: "POST",
      data: {
        action: "urp_process_chunks_async",
        security: urp_import.security,
      },
      success: function (response) {
        console.log("response", response);
        if (response.success) {
          if (totalChunks == 0) {
            totalChunks = response.data.total_chunks; // Set totalChunks if not already set
            $("#total_chunks").val(totalChunks); // Save it for future use
          }
          var remainingChunks = response.data.remaining;
          console.log("remainingChunks", remainingChunks);
          var progress = ((totalChunks - remainingChunks) / totalChunks) * 100;
          console.log("progress", progress);
          $("#import-progress-bar").css("width", progress + "%");
          $("#import-progress-text").text(progress.toFixed(2) + "% completed");

          if (remainingChunks > 0) {
            processChunks();
          } else {
            alert("Import completed successfully.");
          }
        } else {
          alert(response.data);
        }
      },
    });
  }
});
