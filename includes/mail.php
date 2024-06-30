<?php
/**
 * The function to send email ultimate 
 */
function urp_send_email($to, $subject, $message)
{
    $headers = array('Content-Type: text/html; charset=UTF-8');
    return wp_mail($to, $subject, $message, $headers);
}

/**
 * Database add all user list as pending email list 
 */
function urp_enqueue_email($to, $subject, $message)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'urp_email_queue';

    $wpdb->insert(
        $table_name,
        array(
            'to_email' => $to,
            'subject' => $subject,
            'message' => $message,
            'status' => 'pending',
        ),
        array(
            '%s',
            '%s',
            '%s',
            '%s'
        )
    );
}

// function urp_process_email_queue()
// {
//     global $wpdb;
//     $table_name = $wpdb->prefix . 'urp_email_queue';

//     error_log('Processing email queue...');

//     $emails = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'pending' LIMIT 10");

//     error_log('Emails to process: ' . count($emails));

//     foreach ($emails as $email) {
//         $sent = urp_send_email($email->to_email, $email->subject, $email->message);

//         error_log('$sent: ' . print_r($sent, true));
//         error_log('Email to: ' . $email->to_email);
//         error_log('Subject: ' . $email->subject);
//         error_log('Message: ' . $email->message);

//         if ($sent) {
//             $wpdb->update(
//                 $table_name,
//                 array('status' => 'sent'),
//                 array('id' => $email->id),
//                 array('%s'),
//                 array('%d')
//             );
//             error_log('Email sent and status updated to sent for ID: ' . $email->id);
//         } else {
//             $wpdb->update(
//                 $table_name,
//                 array('status' => 'failed'),
//                 array('id' => $email->id),
//                 array('%s'),
//                 array('%d')
//             );
//             error_log('Failed to send email for ID: ' . $email->id);
//         }
//     }
// }
// add_action('urp_process_email_queue_event', 'urp_process_email_queue');


function urp_process_email_queue()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'urp_email_queue';

    // Set the batch size
    $batch_size = 3;

    // Retrieve the next batch of emails to process
    $emails = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE status = 'pending' LIMIT %d", $batch_size));

    // Log the number of emails to process
    error_log('Processing ' . count($emails) . ' emails...');

    foreach ($emails as $email) {
        // Send the email
        $sent = urp_send_email($email->to_email, $email->subject, $email->message);

        // Log the result of the email sending
        //    error_log('$sent: ' . print_r($sent, true));
        //     error_log('Email to: ' . $email->to_email);
        //    error_log('Subject: ' . $email->subject);
        //     error_log('Message: ' . $email->message);

        if ($sent) {
            // Update the status to 'sent' if the email was successfully sent
            $wpdb->update(
                $table_name,
                array('status' => 'sent'),
                array('id' => $email->id),
                array('%s'),
                array('%d')
            );
            error_log('Email sent and status updated to sent for ID: ' . $email->id);
        } else {
            // Update the status to 'failed' if the email failed to send
            $wpdb->update(
                $table_name,
                array('status' => 'failed'),
                array('id' => $email->id),
                array('%s'),
                array('%d')
            );
            error_log('Failed to send email for ID: ' . $email->id);
        }
    }

    // Schedule the next batch processing if there are still pending emails
    $pending_emails_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");

    if ($pending_emails_count > 0) {
        // Schedule the next batch processing in 5 minutes
        if (!wp_next_scheduled('urp_process_email_queue_event')) {
            wp_schedule_single_event(time() + 300, 'urp_process_email_queue_event');
            error_log('Scheduled next batch processing in 5 minutes');
        }
    } else {
        error_log('No more pending emails to process');
    }
}

add_action('urp_process_email_queue_event', 'urp_process_email_queue');


/**
 * Event Scheduled for every 5 minutes 
 */
function urp_schedule_email_processing()
{
    if (!wp_next_scheduled('urp_process_email_queue_event')) {
        wp_schedule_event(time(), 'five_minutes', 'urp_process_email_queue_event');
        error_log('Scheduled email processing event');
    } else {
        error_log('Email processing event already scheduled');
    }
}
add_action('wp', 'urp_schedule_email_processing');


/**
 * Define Custom inetrval 
 */
function add_five_minutes_cron_schedule($schedules)
{
    $schedules['five_minutes'] = array(
        'interval' => 300, // 5 minutes in seconds
        'display' => __('Every Five Minutes')
    );
    return $schedules;
}
add_filter('cron_schedules', 'add_five_minutes_cron_schedule');









// function manual_urp_process_email_queue()
// {
//     if (isset($_GET['manual_email_queue'])) {
//         urp_process_email_queue();
//         echo 'Email queue processed manually';
//         exit;
//     }
// }

// add_action('init', 'test_wp_mail');


// function test_wp_mail()
// {
//     if (isset($_GET['test_email'])) {
//         $sent = wp_mail('your_email@example.com', 'Test Email', 'This is a test email.');
//         if ($sent) {
//             echo 'Email sent successfully';
//         } else {
//             echo 'Failed to send email';
//         }
//         exit;
//     }
// }
// add_action('admin_init', 'manual_urp_process_email_queue');
