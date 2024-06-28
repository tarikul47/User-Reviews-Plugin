<?php

function urp_import_users_page_async()
{
    ?>
    <div class="wrap">
        <h2>Import Users</h2>
        <form id="urp-import-form" method="post" enctype="multipart/form-data">
            <input type="file" name="user_file" required>
            <input type="hidden" name="total_chunks" id="total_chunks" value="">
            <input type="submit" name="upload_file" class="button button-primary" value="Upload">
        </form>
        <div id="import-progress-container" style="display:none;">
            <h3>Import Progress</h3>
            <div id="import-progress-bar" style="width: 0%; height: 30px; background-color: green;"></div>
            <p id="import-progress-text">0% completed</p>
        </div>
    </div>
    <?php
}



function urp_handle_file_upload_async()
{
    check_ajax_referer('urp_import_nonce', 'security');

    if (!empty($_FILES['user_file']['tmp_name'])) {
        $file = $_FILES['user_file']['tmp_name'];
        $file_type = wp_check_filetype(basename($_FILES['user_file']['name']));

        if ($file_type['ext'] == 'csv' || $file_type['ext'] == 'xls' || $file_type['ext'] == 'xlsx') {
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['path'] . '/' . basename($_FILES['user_file']['name']);
            move_uploaded_file($file, $file_path);

            urp_process_file_async($file_path, $file_type['ext']);
        } else {
            wp_send_json_error('Invalid file type. Please upload a CSV or XLS file.');
        }
    } else {
        wp_send_json_error('No file uploaded. Please upload a CSV or XLS file.');
    }

    wp_send_json_success();
}
add_action('wp_ajax_urp_handle_file_upload_async', 'urp_handle_file_upload_async');


function urp_process_file_async($file_path, $file_type)
{
    $chunks = [];
    $chunk_size = 100;

    // Clear the queue before starting to process the file
    update_option('urp_import_queue', []);

    if ($file_type == 'csv') {
        $file = fopen($file_path, 'r');
        $header = fgetcsv($file);

        /**
         * Array
        (
            [0] => name
            [1] => email
            [2] => review_content
            [3] => rating
        )
         */

        while (($row = fgetcsv($file)) !== false) {
            $chunks[] = $row;

            if (count($chunks) == $chunk_size) {
                urp_enqueue_chunk($chunks);
                //  error_log(print_r(count($chunks), true));
                //  error_log(print_r($chunks, true));
                $chunks = [];
                // error_log(print_r($chunks, true));
            }
        }

        if (count($chunks) > 0) {
            urp_enqueue_chunk($chunks);
        }

        fclose($file);
    } else {
        require_once 'PHPExcel/PHPExcel.php';
        $objPHPExcel = PHPExcel_IOFactory::load($file_path);
        $worksheet = $objPHPExcel->getActiveSheet();

        foreach ($worksheet->getRowIterator() as $row) {
            $row_data = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                $row_data[] = $cell->getValue();
            }

            $chunks[] = $row_data;

            if (count($chunks) == $chunk_size) {
                urp_enqueue_chunk($chunks);
                $chunks = [];
            }
        }

        if (count($chunks) > 0) {
            urp_enqueue_chunk($chunks);
        }
    }
}

/**
 * [28-Jun-2024 20:32:16 UTC] urp_chunk_667f1dd0258b2
[28-Jun-2024 20:32:16 UTC] urp_chunk_667f1dd029a20
[28-Jun-2024 20:32:16 UTC] urp_chunk_667f1dd02c2af
[28-Jun-2024 20:32:16 UTC] urp_chunk_667f1dd02e662
 */


function urp_enqueue_chunk($chunk)
{
    // Store the chunk in the database or session for processing
    // For demonstration, we'll use a transient
    $chunk_key = 'urp_chunk_' . uniqid();
    set_transient($chunk_key, $chunk, 3600);

    error_log('urp_enqueue_chunk ---' . $chunk_key);

    // Add the chunk key to a queue
    $queue = get_option('urp_import_queue', []);
    //  error_log('Initial queue: ' . print_r($queue, true));

    $queue[] = $chunk_key;

    //  error_log('Updated queue: ' . print_r($queue, true));

    update_option('urp_import_queue', $queue);
}




function urp_enqueue_import_scripts()
{
    wp_enqueue_script('urp-import-script', plugins_url('../js/import.js', __FILE__), array('jquery'), '1.0', true);
    wp_localize_script(
        'urp-import-script',
        'urp_import',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'security' => wp_create_nonce('urp_import_nonce')
        )
    );
}
add_action('admin_enqueue_scripts', 'urp_enqueue_import_scripts');

function urp_process_chunks_async()
{
    check_ajax_referer('urp_import_nonce', 'security');

    $queue = get_option('urp_import_queue', []);
    $total_chunks = count($queue) + 1; // Include the current chunk being processed

    if (empty($queue)) {
        wp_send_json_success('Import completed.');
    } else {
        $chunk_key = array_shift($queue);
        $chunk = get_transient($chunk_key);
        delete_transient($chunk_key);

        if ($chunk) {
            global $wpdb;

            foreach ($chunk as $row) {
                // Assuming your CSV has columns: name, email, review_content, rating
                $name = sanitize_text_field($row[0]);
                $email = sanitize_email($row[1]);
                $review_content = sanitize_textarea_field($row[2]);
                $rating = intval($row[3]);


                $content = '<h1>Reviews for ' . $name . '</h1>';
                $content .= '<h2>Review by ' . esc_html(wp_get_current_user()->display_name) . '</h2>';
                $content .= '<p>Review Content: ' . esc_html($review_content) . '</p>';
                $content .= '<p>Rating: ' . esc_html($rating) . '</p>';
                $content .= '<hr>';

                // Generate PDF from review content
                $pdf_url = generate_product_pdf_from_person_review($name, $content);

                // Create downloadable product in WooCommerce
                $product_id = create_or_update_downloadable_product($name, $pdf_url);

                // Insert user into the database
                $wpdb->insert(
                    "{$wpdb->prefix}urp_custom_users",
                    array(
                        'name' => $name,
                        'email' => $email,
                        'product_id' => $product_id,
                    ),
                    array(
                        '%s',
                        '%s',
                        '%d',
                    )
                );

                $user_id = $wpdb->insert_id;

                // Insert review into the database
                $wpdb->insert(
                    "{$wpdb->prefix}urp_custom_reviews",
                    array(
                        'user_id' => $user_id,
                        'reviewer_name' => 'Imported',
                        'review_content' => $review_content,
                        'rating' => $rating,
                        'status' => 'approved',
                    ),
                    array('%d', '%s', '%s', '%d', '%s')
                );
            }

            update_option('urp_import_queue', $queue);

            wp_send_json_success(
                array(
                    'remaining' => count($queue),
                    'completed' => $chunk_key,
                    'total_chunks' => $total_chunks
                )
            );
        } else {
            wp_send_json_error('Chunk data not found.');
        }
    }
}

add_action('wp_ajax_urp_process_chunks_async', 'urp_process_chunks_async');


