$(document).ready(function () {

    /**
     * Create preview image after select file.
     *
     * Necessary wrap file input in the following way:
     *
     * <div class="image-preview-block">
     *     <div class="image-preview-image"></div>
     *     {!! Form::file('image', ['class' => 'image-preview-input']) !!}
     * </div>
     */
    $('.image-preview-block').on('change', '.image-preview-input', function () {

        var image = $('.image-preview-input')[0].files[0];

        // Upload file on the server.
        data = new FormData();
        data.append("file", image);
        $.ajax({
            data: data,
            type: "POST",
            url: "/ajax/uploader/preview",
            cache: false,
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            contentType: false,
            processData: false,
            success: function (image) {

                // Clear old data.
                $('.image-preview-block .image-preview-image').html('');

                // If exist error then show it.
                if (typeof image['error'] == 'undefined') {
                    var preview = '<img src="' + image['url'] + '" class="img-rounded"/>';
                }
                // Show image preview.
                else {
                    var preview = '<div class="alert alert-danger">' + image['error'] + '</div>';
                }

                // Show data (image or error).
                $('.image-preview-block .image-preview-image').html(preview);
            }
        });
    });

});