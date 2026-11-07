<?php
/**
 * معالج قوالب ورسائل البريد الإلكتروني الآلية لحفل تربوي
 * File: includes/email-handler.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 1. القالب العام الموحد لجميع الرسائل (HTML Responsive + RTL فخم)
 */
function tarbwy_build_email_html($subject, $body_content, $guest = null, $include_ticket_card = false) {
    $settings = function_exists('tarbwy_get_settings') ? tarbwy_get_settings() : [
        'primary_color'   => '#0F2942',
        'secondary_color' => '#C5A059',
        'logo_url'        => '',
        'event_date'      => 'الخميس، 15 مايو 2025 | 10:00 صباحاً',
        'event_location'  => 'الرياض، فندق الفورسيزونز - قاعة المملكة',
        'contact_email'   => 'launch@tarbwy.com'
    ];

    $primary_color   = $settings['primary_color'];
    $secondary_color = $settings['secondary_color'];
    $logo_url        = $settings['logo_url'];
    $event_date      = $settings['event_date'];
    $event_location  = $settings['event_location'];
    $contact_email   = $settings['contact_email'];

    $ticket_block = '';
    if ($include_ticket_card && $guest) {
        $qr_api_url = 'https://quickchart.io/qr?text=' . urlencode($guest->unique_token) . '&size=300&margin=2&format=png';
        $ticket_web_link = add_query_arg('tarbwy_ticket', $guest->unique_token, home_url('/'));
        $badge = ($guest->guest_type === 'vip') ? 'دعوة كبار الشخصيات VIP' : 'بطاقة حضور معتمدة';

        $ticket_block = '
        <table width="100%" cellpadding="0" cellspacing="0" style="margin: 28px 0; background-color: #F8FAFC; border: 2px dashed ' . esc_attr($secondary_color) . '; border-radius: 20px; text-align: center;">
            <tr>
                <td style="padding: 24px;">
                    <div style="display: inline-block; padding: 5px 16px; background-color: #FEF3C7; color: #92400E; font-size: 12px; font-weight: bold; border-radius: 20px; margin-bottom: 14px;">
                        ★ ' . esc_html($badge) . '
                    </div>
                    
                    <h3 style="margin: 0 0 6px 0; font-size: 22px; font-weight: 800; color: ' . esc_attr($primary_color) . ';">' . esc_html($guest->full_name) . '</h3>
                    ' . (!empty($guest->organization) ? '<p style="margin: 0 0 16px 0; font-size: 13px; color: #64748B;">' . esc_html($guest->organization) . '</p>' : '') . '

                    <!-- صورة رمز الـ QR المباشرة -->
                    <div style="background: #FFFFFF; padding: 14px; border-radius: 16px; display: inline-block; margin-bottom: 12px; box-shadow: 0 4px 12px rgba(15, 41, 66, 0.08); border: 1px solid #E2E8F0;">
                        <img src="' . esc_url($qr_api_url) . '" alt="QR Code" width="210" height="210" style="display: block; margin: 0 auto; border: 0;" />
                    </div>

                    <!-- رمز التذكرة النصي -->
                    <div style="font-family: Courier, monospace; font-size: 15px; font-weight: bold; color: ' . esc_attr($primary_color) . '; letter-spacing: 2px; margin-bottom: 18px; background-color: #E2E8F0; display: inline-block; padding: 4px 14px; border-radius: 8px;">
                        ' . esc_html($guest->unique_token) . '
                    </div>

                    <!-- تفاصيل الموعد والمكان داخل البطاقة -->
                    <div style="font-size: 13px; color: #334155; line-height: 2; margin-bottom: 20px; text-align: right; background-color: #FFFFFF; padding: 12px 18px; border-radius: 12px; border: 1px solid #E2E8F0;">
                        📅 <strong>الموعد:</strong> ' . esc_html($event_date) . '<br/>
                        📍 <strong>المقر:</strong> ' . esc_html($event_location) . '
                    </div>

                    <!-- زر فتح وتحميل البطاقة والـ QR في المتصفح -->
                    <div>
                        <a href="' . esc_url($ticket_web_link) . '" target="_blank" style="background-color: ' . esc_attr($secondary_color) . '; color: #0F2942; padding: 14px 28px; text-decoration: none; font-size: 14px; font-weight: 800; border-radius: 10px; display: inline-block; box-shadow: 0 4px 12px rgba(197, 160, 89, 0.35);">
                            🎟️ فتح وتحميل بطاقة الدخول والـ QR Code في المتصفح
                        </a>
                    </div>
                </td>
            </tr>
        </table>';
    }

    $html = '
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>' . esc_html($subject) . '</title>
    </head>
    <body style="margin: 0; padding: 0; background-color: #F1F5F9; font-family: Tahoma, Arial, sans-serif; direction: rtl; text-align: right; color: #0F172A;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #F1F5F9; padding: 35px 12px;">
            <tr>
                <td align="center">
                    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #FFFFFF; border-radius: 24px; overflow: hidden; box-shadow: 0 8px 30px rgba(15, 41, 66, 0.08); border: 1px solid #E2E8F0;">
                        
                        <!-- رأس الإيميل الملكي -->
                        <tr>
                            <td style="background-color: ' . esc_attr($primary_color) . '; padding: 35px 25px; text-align: center; color: #FFFFFF; border-bottom: 4px solid ' . esc_attr($secondary_color) . ';">
                                ' . (!empty($logo_url) ? '<img src="' . esc_url($logo_url) . '" alt="تربوي" style="max-height: 55px; margin-bottom: 12px;" /><br/>' : '') . '
                                <h1 style="margin: 0; font-size: 24px; font-weight: 900; color: #FFFFFF; letter-spacing: 1px;">تـربـوي</h1>
                                <p style="margin: 5px 0 0 0; font-size: 13px; color: ' . esc_attr($secondary_color) . '; font-weight: bold;">الاتحاد العربي للقيادة التربوية</p>
                            </td>
                        </tr>

                        <!-- جسم الرسالة -->
                        <tr>
                            <td style="padding: 35px 30px; line-height: 1.9; font-size: 14px; color: #334155;">
                                ' . $body_content . '
                                ' . $ticket_block . '
                            </td>
                        </tr>

                        <!-- فوتر الإيميل -->
                        <tr>
                            <td style="background-color: #081726; padding: 25px 20px; text-align: center; font-size: 12px; color: #94A3B8; line-height: 1.7; border-top: 1px solid #1E293B;">
                                حفل الإشهار الرسمي والانطلاقة الاستراتيجية لمبادرة «تربوي»<br/>
                                للاستفسارات الرسمية: <a href="mailto:' . esc_attr($contact_email) . '" style="color: ' . esc_attr($secondary_color) . '; text-decoration: none; font-weight: bold;">' . esc_html($contact_email) . '</a> | <a href="' . esc_url(home_url('/launch')) . '" style="color: #94A3B8; text-decoration: underline;">tarbwy.com/launch</a>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    return $html;
}

/**
 * 2. دالة إرسال الإيميل الموحدة
 */
function tarbwy_send_mail($to, $subject, $message_html) {
    $settings = function_exists('tarbwy_get_settings') ? tarbwy_get_settings() : ['contact_email' => 'launch@tarbwy.com'];
    $contact_email = $settings['contact_email'];

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: حفل إشهار تربوي <' . $contact_email . '>',
        'Reply-To: ' . $contact_email
    ];
    return wp_mail($to, $subject, $message_html, $headers);
}

/**
 * 3. الرسالة 1: إيميل استلام طلب الحضور (تسجيل الجمهور العام)
 */
function tarbwy_send_public_received_email($guest_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'tarbwy_guests';
    $guest = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $guest_id));
    if (!$guest) return false;

    $subject = 'تم استلام طلب حضوركم لحفل إشهار تربوي';
    $body = '
    <p>الأستاذ/ة الفاضل/ة: <strong>' . esc_html($guest->full_name) . '</strong>،</p>
    <p>السلام عليكم ورحمة الله وبركاته،</p>
    <p>نشكر لكم اهتمامكم بحضور <strong>حفل الإشهار الرسمي لـ «تربوي – الاتحاد العربي للقيادة التربوية»</strong>.</p>
    <p>نحيطكم علماً بأن طلبكم قيد المراجعة لدى أمانة الحفل نظراً للطاقة الاستيعابية المحددة للقاعة والمقاعد المخصصة للقيادات التربوية.</p>
    <p>سنوافيكم عبر هذا البريد الإلكتروني فور اعتماد مقعدكم وإصدار بطاقة الدخول الرقمية المشفرة الخاصة بكم.</p>
    <p>مع خالص التحية والتقدير،<br/><strong>أمانة حفل إشهار تربوي</strong></p>';

    $html = tarbwy_build_email_html($subject, $body, $guest, false);
    return tarbwy_send_mail($guest->email, $subject, $html);
}

/**
 * 4. الرسالة 2: إيميل قبول الطلب العام + بطاقة الدخول والـ QR Code
 */
function tarbwy_send_public_approved_email($guest_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'tarbwy_guests';
    $guest = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $guest_id));
    if (!$guest) return false;

    $subject = 'تهانينا! تم اعتماد حضوركم - بطاقة دخول حفل إشهار تربوي';
    $body = '
    <p>الأستاذ/ة الفاضل/ة: <strong>' . esc_html($guest->full_name) . '</strong>،</p>
    <p>السلام عليكم ورحمة الله وبركاته،</p>
    <p>يسر الاتحاد العربي للقيادة التربوية إبلاغكم <strong>بقبول واعتماد حضوركم رسمياً</strong> لحفل الإشهار والانطلاقة الاستراتيجية.</p>
    <p>تجدون أدناه بطاقة الدخول الرسمية ورمز الاستجابة السريعة (QR Code). يرجى إبراز هذا الرمز عند مدخل القاعة لتسجيل الدخول السريع:</p>';

    $html = tarbwy_build_email_html($subject, $body, $guest, true);
    return tarbwy_send_mail($guest->email, $subject, $html);
}

/**
 * 5. الرسالة 3: إيميل تأكيد كبار الشخصيات VIP + بطاقة الدخول
 */
function tarbwy_send_vip_confirmed_email($guest_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'tarbwy_guests';
    $guest = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $guest_id));
    if (!$guest) return false;

    $subject = 'تأكيد الحضور - بطاقة دعوة كبار الشخصيات VIP | حفل إشهار تربوي';
    $body = '
    <p>سعادة: <strong>' . esc_html($guest->full_name) . '</strong>، حفظكم الله،</p>
    <p>السلام عليكم ورحمة الله وبركاته،</p>
    <p>تلقينا ببالغ التقدير والاعتزاز تأكيد حضوركم الكريم <strong>لحفل الإشهار الرسمي لـ «تربوي – الاتحاد العربي للقيادة التربوية»</strong>.</p>
    <p>إن تشريفكم لنا يضفي على هذه الانطلاقة أثراً استثنائياً. يسرنا تزويدكم ببطاقة الدخول الرسمية الخاصة بكبار الشخصيات، وتشمل خدمة صف السيارات المخصصة (Valet Parking) عند المدخل الرئيسي للقاعة:</p>';

    $html = tarbwy_build_email_html($subject, $body, $guest, true);
    return tarbwy_send_mail($guest->email, $subject, $html);
}

/**
 * 6. الرسالة 4: تذكير بموعد الحفل (Reminder)
 */
function tarbwy_send_reminder_email($guest_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'tarbwy_guests';
    $guest = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $guest_id));
    if (!$guest) return false;

    $subject = 'تذكير: موعد حفل إشهار تربوي - غداً نلتقي';
    $body = '
    <p>المكرم/ة: <strong>' . esc_html($guest->full_name) . '</strong>،</p>
    <p>السلام عليكم ورحمة الله وبركاته،</p>
    <p>يسعدنا تذكيركم بموعد حفل إشهار «تربوي – الاتحاد العربي للقيادة التربوية». نتطلع بشغف لاستقبالكم ومشاركتكم هذا الحدث التاريخي في مسيرة التعليم العربي.</p>
    <p>نرفق لكم مجدداً بطاقة الدخول الرقمية ورمز الـ QR لتسهيل عبوركم عبر البوابة الإلكترونية:</p>';

    $html = tarbwy_build_email_html($subject, $body, $guest, true);
    return tarbwy_send_mail($guest->email, $subject, $html);
}

/**
 * 7. الرسالة 5: رسالة شكر بعد انتهاء الحفل (Thank You)
 */
function tarbwy_send_thankyou_email($guest_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'tarbwy_guests';
    $guest = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $guest_id));
    if (!$guest) return false;

    $subject = 'خالص الشكر والتقدير لحضوركم حفل إشهار تربوي';
    $body = '
    <p>المكرم/ة: <strong>' . esc_html($guest->full_name) . '</strong>،</p>
    <p>السلام عليكم ورحمة الله وبركاته،</p>
    <p>يتقدم مجلس أمناء الاتحاد العربي للقيادة التربوية بجزيل الشكر وعميق الامتنان لمشاركتكم الكريمة في حفل إشهار «تربوي».</p>
    <p>لقد كان لتواجدكم وتفاعلكم الدور الأكبر في إنجاح هذا الإطلاق الاستراتيجي. سنوافيكم قريباً بمخرجات الحفل، ألبوم الصور الرسمي، ومبادرات التحالف القادمة.</p>
    <p>دمتم برعاية الله وتوفيقه،<br/><strong>فريق تربوي</strong></p>';

    $html = tarbwy_build_email_html($subject, $body, $guest, false);
    return tarbwy_send_mail($guest->email, $subject, $html);
}

/**
 * 8. دوال AJAX للحملات الجماعية (Bulk Emails)
 */
add_action('wp_ajax_tarbwy_send_bulk_reminders', 'tarbwy_ajax_send_bulk_reminders_handler');

function tarbwy_ajax_send_bulk_reminders_handler() {
    check_ajax_referer('tarbwy_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'غير مصرح.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'tarbwy_guests';
    $guests = $wpdb->get_results("SELECT id FROM $table WHERE status IN ('approved', 'confirmed_vip')");

    $count = 0;
    foreach ($guests as $g) {
        if (tarbwy_send_reminder_email($g->id)) {
            $count++;
        }
    }

    wp_send_json_success(['message' => "تم إرسال رسائل التذكير بنجاح إلى ($count) ضيف مؤكد."]);
}

add_action('wp_ajax_tarbwy_send_bulk_thankyou', 'tarbwy_ajax_send_bulk_thankyou_handler');

function tarbwy_ajax_send_bulk_thankyou_handler() {
    check_ajax_referer('tarbwy_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'غير مصرح.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'tarbwy_guests';
    $guests = $wpdb->get_results("SELECT id FROM $table WHERE status = 'checked_in'");

    $count = 0;
    foreach ($guests as $g) {
        if (tarbwy_send_thankyou_email($g->id)) {
            $count++;
        }
    }

    wp_send_json_success(['message' => "تم إرسال رسائل الشكر بنجاح إلى ($count) ضيف حضر بالبوابة."]);
}