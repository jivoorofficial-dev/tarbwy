<?php
/**
 * Plugin Name: Tarbwy Launch Event Manager
 * Plugin URI: https://tarbwy.com/launch
 * Description: نظام متكامل لإدارة حفل إشهار «تربوي – الاتحاد العربي للقيادة التربوية»: إدارة التسجيل، مسار كبار الشخصيات VIP Concierge، إصدار التذاكر الرقمية المشفرة بـ QR Code، وإدارة المتحدثين وبوابات الحضور.
 * Version: 2.1.0
 * Author: Tarbwy Executive IT Team
 * Author URI: https://tarbwy.com
 * Text Domain: tarbwy-event-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TARBWY_VERSION', '2.1.0');
define('TARBWY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TARBWY_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * =========================================================================
 * 1. استدعاء الملفات الفرعية للنظام
 * =========================================================================
 */
$required_files = [
    TARBWY_PLUGIN_DIR . 'includes/admin-dashboard.php',
    TARBWY_PLUGIN_DIR . 'includes/email-handler.php',
    TARBWY_PLUGIN_DIR . 'includes/checkin-scanner.php',
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

/**
 * =========================================================================
 * 2. تفعيل الإضافة وإنشاء جدول قاعدة البيانات (wp_tarbwy_guests)
 * =========================================================================
 */
register_activation_hook(__FILE__, 'tarbwy_install_database');

function tarbwy_install_database() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'tarbwy_guests';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT(11) NOT NULL AUTO_INCREMENT,
        unique_token VARCHAR(64) NOT NULL,
        guest_type ENUM('public', 'vip') NOT NULL DEFAULT 'public',
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50) NOT NULL,
        organization VARCHAR(255) DEFAULT '',
        job_title VARCHAR(255) DEFAULT '',
        companion_name VARCHAR(255) DEFAULT NULL,
        dietary_notes TEXT DEFAULT NULL,
        status ENUM('pending', 'approved', 'waitlist', 'declined', 'confirmed_vip', 'checked_in') NOT NULL DEFAULT 'pending',
        checkin_time DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_token_idx (unique_token),
        KEY email_idx (email),
        KEY status_idx (status)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // ضبط الإعدادات الافتراضية الموحدة في خيار مصفوفة واحد
    if (false === get_option('tarbwy_event_settings')) {
        $default_settings = [
            'logo_url'        => '',
            'primary_color'   => '#0F2942',
            'secondary_color' => '#C5A059',
            'accent_color'    => '#1E4E79',
            'bg_color'        => '#F8FAFC',
            'event_date'      => 'الخميس، 15 مايو 2025 | 10:00 صباحاً',
            'event_location'  => 'الرياض، فندق الفورسيزونز - قاعة المملكة',
            'scanner_pin'     => '2025',
            'contact_email'   => 'launch@tarbwy.com',
            'speakers'        => []
        ];
        update_option('tarbwy_event_settings', $default_settings);
    }
}

/**
 * دالة مساعدة عامة لجلب إعدادات الهوية والحدث
 */
function tarbwy_get_settings() {
    $defaults = [
        'logo_url'        => '',
        'primary_color'   => '#0F2942',
        'secondary_color' => '#C5A059',
        'accent_color'    => '#1E4E79',
        'bg_color'        => '#F8FAFC',
        'event_date'      => 'الخميس، 15 مايو 2025 | 10:00 صباحاً',
        'event_location'  => 'الرياض، فندق الفورسيزونز - قاعة المملكة',
        'scanner_pin'     => '2025',
        'contact_email'   => 'launch@tarbwy.com',
        'speakers'        => []
    ];
    $saved = get_option('tarbwy_event_settings', []);
    return wp_parse_args($saved, $defaults);
}

/**
 * =========================================================================
 * 3. بوابة التذكرة الرقمية المستقلة الملكية (?tarbwy_ticket=TOKEN)
 * =========================================================================
 */
add_action('template_redirect', 'tarbwy_render_digital_ticket_page');

function tarbwy_render_digital_ticket_page() {
    if (!isset($_GET['tarbwy_ticket'])) {
        return;
    }

    $token = sanitize_text_field(wp_unslash($_GET['tarbwy_ticket']));
    if (empty($token)) {
        wp_die('رمز التذكرة غير صالح.', 'خطأ في التذكرة', ['response' => 404]);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'tarbwy_guests';
    $guest = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE unique_token = %s", $token));

    $settings = tarbwy_get_settings();
    $qr_api_url = 'https://quickchart.io/qr?text=' . urlencode($token) . '&size=320&margin=2&format=png';
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $guest ? 'بطاقة دخول: ' . esc_html($guest->full_name) : 'تذكرة غير موجودة'; ?> | حفل إشهار تربوي</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <style>
            body {
                font-family: 'Cairo', sans-serif;
                background-color: #0B132B;
                background-image: radial-gradient(rgba(197, 160, 89, 0.15) 1px, transparent 1px);
                background-size: 28px 28px;
            }
            @media print {
                body { background: #fff !important; }
                .no-print { display: none !important; }
                .print-card { box-shadow: none !important; border: 2px solid #C5A059 !important; width: 100% !important; max-width: 100% !important; margin: 0 !important; }
            }
        </style>
    </head>
    <body class="min-h-screen py-10 px-4 flex flex-col items-center justify-center text-slate-800">

        <?php if (!$guest): ?>
            <div class="bg-white rounded-3xl p-8 max-w-md w-full text-center shadow-2xl border-t-8 border-rose-600">
                <div class="w-20 h-20 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center text-3xl mx-auto mb-4">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h1 class="text-2xl font-black text-slate-900 mb-2">التذكرة غير متوفرة</h1>
                <p class="text-slate-500 text-sm mb-6 leading-relaxed">لم يتم العثور على أي بطاقة دعوة مرتبطة بهذا الرمز البرمجي.</p>
                <a href="<?php echo esc_url(home_url('/launch')); ?>" class="inline-block px-6 py-3 rounded-xl bg-[#0F2942] text-white font-bold text-xs">العودة لصفحة الحفل</a>
            </div>
        <?php else: 
            $is_vip = ($guest->guest_type === 'vip');
        ?>
            <div class="print-card bg-white rounded-3xl overflow-hidden max-w-md w-full shadow-2xl border border-slate-200 relative">
                
                <!-- رأس البطاقة الملكي -->
                <div class="p-8 text-center text-white relative overflow-hidden" style="background: linear-gradient(135deg, #0F2942 0%, #081726 100%);">
                    <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-[#C5A059]/10 pointer-events-none"></div>
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-[#C5A059] text-[#0F2942] flex items-center justify-center text-2xl font-black mb-3 shadow-lg">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                    <h2 class="text-2xl font-black tracking-wide text-white">تـربـوي</h2>
                    <p class="text-xs font-semibold text-[#C5A059] mt-0.5">الاتحاد العربي للقيادة التربوية</p>
                    <div class="mt-4 inline-block px-4 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider <?php echo $is_vip ? 'bg-[#C5A059] text-[#0F2942]' : 'bg-white/10 text-slate-200 border border-white/20'; ?>">
                        <?php echo $is_vip ? 'بطاقة دعوة كبار الشخصيات VIP' : 'بطاقة حضور معتمدة'; ?>
                    </div>
                </div>

                <!-- فاصل الزخرفة الذهبي -->
                <div class="h-1.5 w-full bg-gradient-to-r from-[#0F2942] via-[#C5A059] to-[#0F2942]"></div>

                <!-- محتوى البطاقة وبيانات الضيف -->
                <div class="p-6 text-center">
                    <span class="text-[11px] font-bold text-slate-400 block mb-1">الاسم الكريم</span>
                    <h3 class="text-2xl font-black text-[#0F2942] mb-1"><?php echo esc_html($guest->full_name); ?></h3>
                    
                    <?php if (!empty($guest->job_title) || !empty($guest->organization)): ?>
                        <p class="text-xs font-semibold text-slate-600 mb-6">
                            <?php echo esc_html($guest->job_title); ?>
                            <?php if (!empty($guest->job_title) && !empty($guest->organization)) echo ' - '; ?>
                            <?php echo esc_html($guest->organization); ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($guest->companion_name)): ?>
                        <div class="mb-5 p-2.5 rounded-xl bg-amber-50/70 border border-amber-200/60 text-[#0F2942] text-xs font-bold inline-block">
                            <i class="fa-solid fa-user-group text-[#C5A059] ml-1"></i> يرافقه: <?php echo esc_html($guest->companion_name); ?>
                        </div>
                    <?php endif; ?>

                    <!-- مربع الـ QR Code مع إطار أنيق -->
                    <div class="bg-slate-50 p-4 rounded-3xl border-2 border-dashed border-[#C5A059]/60 inline-block mx-auto mb-3 shadow-inner">
                        <img id="qr-online-img" src="<?php echo esc_url($qr_api_url); ?>" alt="QR Pass" class="w-56 h-56 mx-auto block" onerror="renderJsQr();">
                        <div id="qr-fallback" class="hidden w-56 h-56 mx-auto"></div>
                    </div>

                    <div class="font-mono text-xs font-bold tracking-widest text-[#0F2942] bg-slate-100 py-1.5 px-4 rounded-lg inline-block mb-6 border border-slate-200">
                        <?php echo esc_html($guest->unique_token); ?>
                    </div>

                    <div class="bg-slate-50 rounded-2xl p-4 text-right space-y-2 border border-slate-200 text-xs">
                        <div class="flex items-center gap-2 text-slate-700">
                            <i class="fa-regular fa-calendar-check text-[#C5A059] text-sm w-4"></i>
                            <span class="font-bold"><?php echo esc_html($settings['event_date']); ?></span>
                        </div>
                        <div class="flex items-center gap-2 text-slate-700">
                            <i class="fa-solid fa-location-dot text-[#C5A059] text-sm w-4"></i>
                            <span class="font-bold"><?php echo esc_html($settings['event_location']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- أزرار الإجراءات والطباعة -->
                <div class="p-6 pt-0 flex flex-col gap-2 no-print">
                    <button onclick="window.print();" class="w-full py-3.5 rounded-xl bg-[#C5A059] hover:bg-[#b08e4c] text-[#0F2942] font-black text-sm flex items-center justify-center gap-2 transition shadow-md">
                        <i class="fa-solid fa-print"></i>
                        <span>طباعة / حفظ البطاقة كـ PDF</span>
                    </button>
                    <a href="<?php echo esc_url(home_url('/launch')); ?>" class="w-full py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs text-center transition">
                        العودة للموقع الرئيسي
                    </a>
                </div>
            </div>

            <!-- سكريبت الرسم الاحتياطي للـ QR Code -->
            <script>
                function renderJsQr() {
                    var onlineImg = document.getElementById('qr-online-img');
                    var fallback = document.getElementById('qr-fallback');
                    if (onlineImg) onlineImg.style.display = 'none';
                    if (fallback) {
                        fallback.classList.remove('hidden');
                        fallback.innerHTML = '';
                        new QRCode(fallback, {
                            text: "<?php echo esc_js($guest->unique_token); ?>",
                            width: 224,
                            height: 224,
                            colorDark : "#0F2942",
                            colorLight : "#F8FAFC",
                            correctLevel : QRCode.CorrectLevel.H
                        });
                    }
                }
            </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}

/**
 * =========================================================================
 * 4. مسار كبار الشخصيات VIP Concierge UX الشورت كود: [tarbwy_vip_rsvp]
 * =========================================================================
 */
add_shortcode('tarbwy_vip_rsvp', 'tarbwy_render_vip_rsvp_shortcode');

function tarbwy_render_vip_rsvp_shortcode() {
    ob_start();
    global $wpdb;
    $table_name = $wpdb->prefix . 'tarbwy_guests';

    $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
    $guest = null;
    $has_token = false;

    if (!empty($token)) {
        $guest = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE unique_token = %s", $token));
        if ($guest) {
            $has_token = true;
        }
    }

    $settings = function_exists('tarbwy_get_settings') ? tarbwy_get_settings() : [
        'event_date'     => 'الخميس، 15 مايو 2025 | 10:00 صباحاً',
        'event_location' => 'الرياض، فندق الفورسيزونز - قاعة المملكة',
    ];

    $nonce    = wp_create_nonce('tarbwy_vip_nonce');
    $ajax_url = admin_url('admin-ajax.php');
    ?>
    <div id="tarbwy-vip-wrapper" class="w-full py-8 px-4 flex justify-center text-right font-sans antialiased" dir="rtl" style="font-family: 'Cairo', sans-serif;">
        
        <!-- البطاقة المركزية الفخمة (Max-Width: 580px) -->
        <div class="w-full max-w-[580px] bg-white rounded-3xl shadow-[0_20px_50px_rgba(15,41,66,0.12)] border border-slate-200/80 overflow-hidden relative transition-all duration-300">
            
            <!-- الرأس الملكي للبطاقة -->
            <div class="p-8 text-center text-white relative overflow-hidden" style="background: linear-gradient(135deg, #081726 0%, #0F2942 55%, #1E4E79 100%);">
                <div class="absolute -right-12 -top-12 w-36 h-36 rounded-full bg-[#C5A059]/10 pointer-events-none"></div>
                
                <!-- شارة VIP الذهبية المضيئة -->
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-[#C5A059]/20 border border-[#C5A059]/40 text-[#C5A059] text-xs font-black mb-4 shadow-sm">
                    <i class="fa-solid fa-crown text-[#C5A059]"></i>
                    <span>دعوة خاصة – كبار الشخصيات VIP</span>
                </div>

                <h1 class="text-2xl sm:text-3xl font-black text-white leading-tight">حفل الإشهار الرسمي</h1>
                <p class="text-xs sm:text-sm font-semibold text-[#C5A059] mt-1 tracking-wide">تربوي – الاتحاد العربي للقيادة التربوية</p>
                
                <div class="mt-4 flex items-center justify-center gap-4 text-[11px] text-slate-300">
                    <span class="flex items-center gap-1.5"><i class="fa-regular fa-calendar-days text-[#C5A059]"></i> <?php echo esc_html($settings['event_date']); ?></span>
                </div>
            </div>

            <!-- خط الزخرفة الذهبي الملكي -->
            <div class="h-1.5 w-full bg-gradient-to-r from-[#0F2942] via-[#C5A059] to-[#0F2942]"></div>

            <div class="p-6 sm:p-8">

                <!-- 1. شاشة النجاح والتذكرة (مخفية إجبارياً عند التحميل) -->
                <div id="vip-ticket-success-screen" class="text-center py-4" style="display: none !important;">
                    <div class="w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-3xl mx-auto mb-4 border border-emerald-200 shadow-inner">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h2 class="text-2xl font-black text-[#0F2942] mb-1">تم تأكيد حضوركم الكريم بنجاح!</h2>
                    <p class="text-xs text-slate-500 mb-6">يسعدنا ويشرفنا تواجدكم معنا. تم تفعيل بطاقة الدخول الرقمية المشفرة وإرسالها لبريدكم الإلكتروني.</p>

                    <!-- بطاقة الـ QR الرقمية المدمجة -->
                    <div class="bg-slate-50 p-6 rounded-3xl border border-slate-200/80 inline-block mx-auto mb-6 shadow-inner">
                        <img id="vip-qr-img" src="" alt="بطاقة الدخول" class="w-48 h-48 mx-auto block mb-3 rounded-xl">
                        <div id="vip-token-badge" class="font-mono text-xs font-bold text-[#0F2942] bg-slate-200/60 py-1.5 px-4 rounded-lg inline-block border border-slate-300/60"></div>
                    </div>

                    <div class="space-y-2">
                        <a id="vip-ticket-page-link" href="#" target="_blank" class="w-full py-4 rounded-2xl bg-[#C5A059] hover:bg-[#b08e4c] text-[#0F2942] font-black text-sm flex items-center justify-center gap-2 shadow-lg transition">
                            <i class="fa-solid fa-print"></i>
                            <span>فتح / طباعة بطاقة الدخول كـ PDF</span>
                        </a>
                        <p class="text-[11px] text-slate-400 mt-2">يرجى إبراز رمز الاستجابة عند المدخل المخصص للقاعة.</p>
                    </div>
                </div>

                <!-- 2. شاشة الاعتذار (مخفية إجبارياً عند التحميل) -->
                <div id="vip-decline-screen" class="text-center py-8" style="display: none !important;">
                    <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center text-3xl mx-auto mb-4 border border-slate-200">
                        <i class="fa-solid fa-handshake-angle text-[#C5A059]"></i>
                    </div>
                    <h2 class="text-2xl font-black text-[#0F2942] mb-2">نشكر سعادتكم على إبلاغنا</h2>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-sm mx-auto">
                        نقدر لكم وقتكم واعتذاركم بكل مودة، ونتطلع بشوق للقائكم في قادم المبادرات والملتقيات لـ «تربوي».
                    </p>
                </div>

                <!-- 3. النموذج التفاعلي السلس (VIP Concierge Form) -->
                <form id="tarbwy-vip-concierge-form" class="space-y-6">
                    <input type="hidden" name="action" value="tarbwy_submit_vip_rsvp">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
                    <input type="hidden" name="token" value="<?php echo esc_attr($has_token ? $guest->unique_token : ''); ?>">
                    <input type="hidden" name="attendance_decision" id="vip_attendance_decision" value="yes">

                    <!-- رسالة الخطأ العامة إن وجدت -->
                    <div id="vip-form-alert" class="p-3.5 rounded-xl text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200" style="display: none !important;"></div>

                    <?php if ($has_token): ?>
                        <!-- الحالة الذكية A: وجود رابط مسبق للضيف (Smart Personalized Greeting) -->
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-11 h-11 rounded-xl bg-[#0F2942] text-[#C5A059] flex items-center justify-center text-lg font-black shrink-0">
                                    <i class="fa-solid fa-user-tie"></i>
                                </div>
                                <div>
                                    <div class="text-[11px] font-bold text-[#C5A059]">مرحباً بكم سعادة:</div>
                                    <div class="text-base font-black text-[#0F2942]"><?php echo esc_html($guest->full_name); ?></div>
                                    <?php if (!empty($guest->organization) || !empty($guest->job_title)): ?>
                                        <div class="text-[11px] text-slate-500 font-semibold mt-0.5">
                                            <?php echo esc_html($guest->job_title); ?>
                                            <?php if (!empty($guest->job_title) && !empty($guest->organization)) echo ' - '; ?>
                                            <?php echo esc_html($guest->organization); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button type="button" id="btn-toggle-profile-fields" class="text-xs font-bold text-slate-500 hover:text-[#0F2942] underline shrink-0 transition">
                                تعديل بياناتي
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- حقول البيانات (مخفية إذا كان الضيف مسجلاً بتوكن، ومفتوحة إذا دخل بدون توكن) -->
                    <div id="vip-profile-fields" class="space-y-4 <?php echo $has_token ? 'hidden' : ''; ?>">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">الاسم الكريم كاملاً <span class="text-rose-500">*</span></label>
                            <input type="text" name="full_name" required value="<?php echo esc_attr($guest ? $guest->full_name : ''); ?>" placeholder="الاسم واللقب" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#C5A059] text-sm text-slate-800 bg-white transition">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">البريد الإلكتروني <span class="text-rose-500">*</span></label>
                                <input type="email" name="email" required value="<?php echo esc_attr($guest ? $guest->email : ''); ?>" placeholder="name@example.com" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#C5A059] text-sm text-slate-800 bg-white transition" dir="ltr">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">رقم الجوال <span class="text-rose-500">*</span></label>
                                <input type="tel" name="phone" required value="<?php echo esc_attr($guest ? $guest->phone : ''); ?>" placeholder="966500000000+" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#C5A059] text-sm text-slate-800 bg-white text-right transition" dir="ltr">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">الجهة الرسمية</label>
                                <input type="text" name="organization" value="<?php echo esc_attr($guest ? $guest->organization : ''); ?>" placeholder="الجهة أو الوزارة أو الجامعة" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#C5A059] text-sm text-slate-800 bg-white transition">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">المسمى والصفة</label>
                                <input type="text" name="job_title" value="<?php echo esc_attr($guest ? $guest->job_title : ''); ?>" placeholder="الصفة الأكاديمية أو الإدارية" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#C5A059] text-sm text-slate-800 bg-white transition">
                            </div>
                        </div>
                    </div>

                    <!-- بطاقات القرار التفاعلية الكبيرة بلمسة واحدة (One-Tap Decision Cards) -->
                    <div>
                        <label class="block text-xs font-extrabold text-[#0F2942] mb-2.5">تأكيد الموقف من الحضور الكريم:</label>
                        <div class="grid grid-cols-2 gap-3">
                            
                            <!-- بطاقة الحضور -->
                            <div id="card-decision-yes" class="cursor-pointer p-4 rounded-2xl border-2 border-[#C5A059] bg-[#C5A059]/10 text-center transition-all duration-200 shadow-sm flex flex-col items-center justify-center gap-1.5 select-none">
                                <span class="text-xl">✅</span>
                                <span class="text-xs sm:text-sm font-black text-[#0F2942]">يشرفني تأكيد الحضور</span>
                                <span class="text-[10px] text-slate-500 font-semibold">تأكيد فوري وإصدار التذكرة</span>
                            </div>

                            <!-- بطاقة الاعتذار -->
                            <div id="card-decision-no" class="cursor-pointer p-4 rounded-2xl border-2 border-slate-200 bg-slate-50 hover:bg-slate-100 text-center transition-all duration-200 flex flex-col items-center justify-center gap-1.5 select-none">
                                <span class="text-xl">🙏</span>
                                <span class="text-xs sm:text-sm font-black text-slate-600">أعتذر عن الحضور</span>
                                <span class="text-[10px] text-slate-400 font-semibold">إبلاغ اللجنة المنظمة</span>
                            </div>

                        </div>
                    </div>

                    <!-- قسم المرافق والملاحظات (يظهر فقط عند اختيار الحضور مع إمكانية التوسيع) -->
                    <div id="vip-attendance-options" class="space-y-3 pt-1">
                        <label class="flex items-center gap-2.5 cursor-pointer text-xs font-bold text-slate-700 select-none">
                            <input type="checkbox" id="toggle-companion-box" class="w-4 h-4 rounded text-[#C5A059] focus:ring-[#C5A059] border-slate-300">
                            <span>سيحضر معي مرافق أو لدي تفضيلات ضيافة خاصة</span>
                        </label>

                        <div id="companion-dietary-fields" class="space-y-3 p-4 rounded-2xl bg-slate-50 border border-slate-200/80 transition-all duration-200" style="display: none;">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-1">اسم المرافق الكريم</label>
                                <input type="text" name="companion_name" value="<?php echo esc_attr($guest ? $guest->companion_name : ''); ?>" placeholder="الاسم الكامل للمرافق" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs bg-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 mb-1">الملاحظات الغذائية أو المتطلبات الخاصة</label>
                                <input type="text" name="dietary_notes" value="<?php echo esc_attr($guest ? $guest->dietary_notes : ''); ?>" placeholder="مثال: حمية خاصة، كرسي متحرك، إلخ..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs bg-white">
                            </div>
                        </div>
                    </div>

                    <!-- زر الإجراء الرئيسي الذكي -->
                    <button type="submit" id="vip-submit-btn" class="w-full py-4 rounded-2xl bg-[#0F2942] hover:bg-[#1E4E79] text-[#C5A059] font-black text-base transition-all duration-300 flex items-center justify-center gap-2 shadow-xl shadow-[#0F2942]/15">
                        <i class="fa-solid fa-check-double text-[#C5A059]"></i>
                        <span id="vip-btn-text">تأكيد الحضور وإصدار البطاقة</span>
                    </button>

                </form>

            </div>

        </div>

    </div>

    <!-- المنطق التفاعلي السلس للواجهة (Pure Vanilla JS) -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var cardYes = document.getElementById('card-decision-yes');
        var cardNo  = document.getElementById('card-decision-no');
        var decisionInput = document.getElementById('vip_attendance_decision');
        var attendanceOptions = document.getElementById('vip-attendance-options');
        var toggleCompanionBox = document.getElementById('toggle-companion-box');
        var companionFields = document.getElementById('companion-dietary-fields');
        var submitBtn = document.getElementById('vip-submit-btn');
        var btnText = document.getElementById('vip-btn-text');
        var form = document.getElementById('tarbwy-vip-concierge-form');
        var alertBox = document.getElementById('vip-form-alert');
        var ticketScreen = document.getElementById('vip-ticket-success-screen');
        var declineScreen = document.getElementById('vip-decline-screen');
        var toggleProfileBtn = document.getElementById('btn-toggle-profile-fields');
        var profileFields = document.getElementById('vip-profile-fields');

        if (!cardYes || !cardNo) return;

        // تبديل إظهار حقول البيانات في حال وجود توكن مسبق
        if (toggleProfileBtn && profileFields) {
            toggleProfileBtn.addEventListener('click', function() {
                if (profileFields.classList.contains('hidden')) {
                    profileFields.classList.remove('hidden');
                    toggleProfileBtn.innerText = 'إخفاء الحقول';
                } else {
                    profileFields.classList.add('hidden');
                    toggleProfileBtn.innerText = 'تعديل بياناتي';
                }
            });
        }

        // اختيار بطاقة: يشرفني الحضور
        cardYes.addEventListener('click', function() {
            decisionInput.value = 'yes';
            cardYes.className = 'cursor-pointer p-4 rounded-2xl border-2 border-[#C5A059] bg-[#C5A059]/10 text-center transition-all duration-200 shadow-sm flex flex-col items-center justify-center gap-1.5 select-none';
            cardNo.className = 'cursor-pointer p-4 rounded-2xl border-2 border-slate-200 bg-slate-50 hover:bg-slate-100 text-center transition-all duration-200 flex flex-col items-center justify-center gap-1.5 select-none';
            
            attendanceOptions.style.display = 'block';
            btnText.innerText = 'تأكيد الحضور وإصدار البطاقة';
            submitBtn.className = 'w-full py-4 rounded-2xl bg-[#0F2942] hover:bg-[#1E4E79] text-[#C5A059] font-black text-base transition-all duration-300 flex items-center justify-center gap-2 shadow-xl shadow-[#0F2942]/15';
        });

        // اختيار بطاقة: أعتذر عن الحضور
        cardNo.addEventListener('click', function() {
            decisionInput.value = 'no';
            cardNo.className = 'cursor-pointer p-4 rounded-2xl border-2 border-slate-400 bg-slate-100 text-center transition-all duration-200 shadow-sm flex flex-col items-center justify-center gap-1.5 select-none';
            cardYes.className = 'cursor-pointer p-4 rounded-2xl border-2 border-slate-200 bg-slate-50 hover:bg-slate-100 text-center transition-all duration-200 flex flex-col items-center justify-center gap-1.5 select-none';
            
            attendanceOptions.style.display = 'none';
            btnText.innerText = 'إرسال الاعتذار';
            submitBtn.className = 'w-full py-4 rounded-2xl bg-slate-700 hover:bg-slate-800 text-white font-black text-base transition-all duration-300 flex items-center justify-center gap-2 shadow-md';
        });

        // إظهار وإخفاء حقول المرافق والملاحظات
        if (toggleCompanionBox && companionFields) {
            toggleCompanionBox.addEventListener('change', function() {
                companionFields.style.display = this.checked ? 'block' : 'none';
            });
        }

        // إرسال الفورم بالـ AJAX السريع
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBtn.disabled = true;
            btnText.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري حفظ الرد...';
            alertBox.style.setProperty('display', 'none', 'important');

            var formData = new FormData(form);

            fetch('<?php echo esc_url($ajax_url); ?>', {
                method: 'POST',
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                submitBtn.disabled = false;
                btnText.innerText = 'تأكيد الرد وحفظ البيانات';

                if (res.success) {
                    form.style.setProperty('display', 'none', 'important');

                    if (res.data.decision === 'yes') {
                        ticketScreen.style.setProperty('display', 'block', 'important');
                        document.getElementById('vip-qr-img').src = res.data.qr_url;
                        document.getElementById('vip-token-badge').innerText = 'رمز التذكرة: ' + res.data.token;
                        document.getElementById('vip-ticket-page-link').href = res.data.ticket_url;
                    } else {
                        declineScreen.style.setProperty('display', 'block', 'important');
                    }
                } else {
                    alertBox.style.setProperty('display', 'block', 'important');
                    alertBox.innerText = res.data.message || 'حدث خطأ أثناء معالجة الرد، يرجى المحاولة ثانية.';
                }
            })
            .catch(function() {
                submitBtn.disabled = false;
                btnText.innerText = 'تأكيد الرد وحفظ البيانات';
                alertBox.style.setProperty('display', 'block', 'important');
                alertBox.innerText = 'تعذر الاتصال بالخادم، يرجى التحقق من اتصال الإنترنت.';
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

/**
 * =========================================================================
 * 5. نموذج تسجيل الجمهور العام الشورت كود: [tarbwy_public_form]
 * =========================================================================
 */
add_shortcode('tarbwy_public_form', 'tarbwy_render_public_form_shortcode');

function tarbwy_render_public_form_shortcode() {
    ob_start();
    $nonce = wp_create_nonce('tarbwy_public_nonce');
    $ajax_url = admin_url('admin-ajax.php');
    ?>
    <div id="tarbwy-public-app" class="w-full text-right" dir="rtl" style="font-family: 'Cairo', sans-serif;">
        <div id="tarbwy-public-alert" class="mb-6 p-4 rounded-xl text-sm font-bold flex items-start gap-3" style="display: none !important;"></div>

        <form id="tarbwy-public-form-element" class="space-y-4">
            <input type="hidden" name="action" value="tarbwy_submit_public_registration">
            <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">الاسم الكريم كاملاً <span class="text-rose-500">*</span></label>
                <input type="text" name="full_name" required placeholder="أدخل اسمك الكريم الثلاثي أو الرباعي" class="w-full px-4 py-3.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#C5A059] text-sm text-slate-800 bg-white transition">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">البريد الإلكتروني <span class="text-rose-500">*</span></label>
                    <input type="email" name="email" required placeholder="name@example.com" class="w-full px-4 py-3.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#C5A059] text-sm text-slate-800 bg-white transition" dir="ltr">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رقم الجوال <span class="text-rose-500">*</span></label>
                    <input type="tel" name="phone" required placeholder="966500000000+" dir="ltr" class="w-full px-4 py-3.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#C5A059] text-sm text-slate-800 bg-white text-right transition">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الجهة / المؤسسة التعليمية</label>
                    <input type="text" name="organization" placeholder="مثال: وزارة التعليم / جامعة..." class="w-full px-4 py-3.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#C5A059] text-sm text-slate-800 bg-white transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">المسمى الوظيفي</label>
                    <input type="text" name="job_title" placeholder="مثال: قائد مدرسة / مستشار تربوي" class="w-full px-4 py-3.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#C5A059] text-sm text-slate-800 bg-white transition">
                </div>
            </div>

            <button type="submit" id="tarbwy-public-submit-btn" class="w-full py-4 px-6 rounded-xl bg-[#0F2942] hover:bg-[#1E4E79] text-[#C5A059] font-black text-base transition flex items-center justify-center gap-2 shadow-xl mt-6">
                <i class="fa-solid fa-paper-plane text-[#C5A059]"></i>
                <span>إرسال طلب الحضور</span>
            </button>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var form = document.getElementById('tarbwy-public-form-element');
        var alertBox = document.getElementById('tarbwy-public-alert');
        var btn = document.getElementById('tarbwy-public-submit-btn');

        if (!form) return;

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-[#C5A059]"></i> جاري إرسال الطلب...';
            alertBox.style.setProperty('display', 'none', 'important');

            var formData = new FormData(form);

            fetch('<?php echo esc_url($ajax_url); ?>', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane text-[#C5A059]"></i> إرسال طلب الحضور';
                alertBox.style.setProperty('display', 'flex', 'important');

                if (data.success) {
                    alertBox.className = 'mb-6 p-4 rounded-xl text-sm font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-start gap-3';
                    alertBox.innerHTML = '<i class="fa-solid fa-circle-check text-xl text-emerald-600 shrink-0"></i><div>' + data.data.message + '</div>';
                    form.reset();
                } else {
                    alertBox.className = 'mb-6 p-4 rounded-xl text-sm font-bold bg-rose-50 text-rose-800 border border-rose-200 flex items-start gap-3';
                    alertBox.innerHTML = '<i class="fa-solid fa-circle-xmark text-xl text-rose-600 shrink-0"></i><div>' + (data.data.message || 'حدث خطأ.') + '</div>';
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane text-[#C5A059]"></i> إرسال طلب الحضور';
                alertBox.style.setProperty('display', 'flex', 'important');
                alertBox.className = 'mb-6 p-4 rounded-xl text-sm font-bold bg-rose-50 text-rose-800 border border-rose-200 flex items-start gap-3';
                alertBox.innerHTML = '<div>تعذر الاتصال بالخادم.</div>';
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

/**
 * =========================================================================
 * 6. شبكة المتحدثين من لوحة التحكم الشورت كود: [tarbwy_speakers_grid]
 * =========================================================================
 */
add_shortcode('tarbwy_speakers_grid', 'tarbwy_render_speakers_grid_shortcode');

function tarbwy_render_speakers_grid_shortcode() {
    $settings = tarbwy_get_settings();
    $speakers = !empty($settings['speakers']) && is_array($settings['speakers']) ? $settings['speakers'] : [];

    if (empty($speakers)) {
        return '<div class="text-center py-8 text-slate-400 text-sm">سيتم الإعلان عن قائمة المتحدثين قريباً.</div>';
    }

    ob_start();
    ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6" dir="rtl" style="font-family: 'Cairo', sans-serif;">
        <?php foreach ($speakers as $sp): 
            $photo = !empty($sp['photo']) ? $sp['photo'] : 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80';
        ?>
            <div class="bg-white rounded-3xl p-6 border border-slate-200 text-center shadow-md hover:shadow-xl transition-all duration-300">
                <div class="w-28 h-28 mx-auto rounded-2xl overflow-hidden mb-4 border-2 border-[#C5A059] shadow-inner">
                    <img src="<?php echo esc_url($photo); ?>" alt="<?php echo esc_attr($sp['name']); ?>" class="w-full h-full object-cover">
                </div>
                <h4 class="text-lg font-black text-[#0F2942]"><?php echo esc_html($sp['name']); ?></h4>
                <p class="text-xs text-[#C5A059] font-bold mt-1"><?php echo esc_html($sp['title']); ?></p>
                <?php if (!empty($sp['org'])): ?>
                    <p class="text-[11px] text-slate-500 mt-1"><?php echo esc_html($sp['org']); ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * =========================================================================
 * 7. معالجات AJAX لتسجيل الاستمارات (Public & VIP)
 * =========================================================================
 */

// معالجة تسجيل الحضور العام
add_action('wp_ajax_tarbwy_submit_public_registration', 'tarbwy_handle_public_registration');
add_action('wp_ajax_nopriv_tarbwy_submit_public_registration', 'tarbwy_handle_public_registration');

function tarbwy_handle_public_registration() {
    check_ajax_referer('tarbwy_public_nonce', 'nonce');

    $full_name    = isset($_POST['full_name']) ? sanitize_text_field(wp_unslash($_POST['full_name'])) : '';
    $email        = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $phone        = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $organization = isset($_POST['organization']) ? sanitize_text_field(wp_unslash($_POST['organization'])) : '';
    $job_title    = isset($_POST['job_title']) ? sanitize_text_field(wp_unslash($_POST['job_title'])) : '';

    if (empty($full_name) || empty($email) || !is_email($email) || empty($phone)) {
        wp_send_json_error(['message' => 'يرجى إكمال جميع الحقول الإلزامية بدقة.']);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'tarbwy_guests';

    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE email = %s", $email));
    if ($exists) {
        wp_send_json_error(['message' => 'هذا البريد الإلكتروني مسجل لدينا بالفعل. سنوافيكم بالتحديثات عبره.']);
    }

    $unique_token = wp_generate_password(16, false);

    $inserted = $wpdb->insert(
        $table_name,
        [
            'unique_token' => $unique_token,
            'guest_type'   => 'public',
            'full_name'    => $full_name,
            'email'        => $email,
            'phone'        => $phone,
            'organization' => $organization,
            'job_title'    => $job_title,
            'status'       => 'pending',
            'created_at'   => current_time('mysql')
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );

    if ($inserted === false) {
        wp_send_json_error(['message' => 'تعذر حفظ البيانات في قاعدة البيانات.']);
    }

    $guest_id = $wpdb->insert_id;

    if (function_exists('tarbwy_send_public_received_email')) {
        tarbwy_send_public_received_email($guest_id);
    }

    wp_send_json_success([
        'message' => 'تم استلام طلب الحضور بنجاح وهو قيد المراجعة، سيتم إشعاركم عبر البريد الإلكتروني فور الاعتماد.'
    ]);
}

// معالجة تأكيد كبار الشخصيات VIP
add_action('wp_ajax_tarbwy_submit_vip_rsvp', 'tarbwy_handle_vip_rsvp');
add_action('wp_ajax_nopriv_tarbwy_submit_vip_rsvp', 'tarbwy_handle_vip_rsvp');

function tarbwy_handle_vip_rsvp() {
    check_ajax_referer('tarbwy_vip_nonce', 'nonce');

    $token          = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
    $decision       = isset($_POST['attendance_decision']) ? sanitize_text_field(wp_unslash($_POST['attendance_decision'])) : 'yes';
    $full_name      = isset($_POST['full_name']) ? sanitize_text_field(wp_unslash($_POST['full_name'])) : '';
    $email          = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $phone          = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $organization   = isset($_POST['organization']) ? sanitize_text_field(wp_unslash($_POST['organization'])) : '';
    $job_title      = isset($_POST['job_title']) ? sanitize_text_field(wp_unslash($_POST['job_title'])) : '';
    $companion_name = isset($_POST['companion_name']) ? sanitize_text_field(wp_unslash($_POST['companion_name'])) : '';
    $dietary_notes  = isset($_POST['dietary_notes']) ? sanitize_textarea_field(wp_unslash($_POST['dietary_notes'])) : '';

    global $wpdb;
    $table_name = $wpdb->prefix . 'tarbwy_guests';

    $target_guest_id = null;
    $target_token    = $token;

    if (!empty($token)) {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id, unique_token, full_name, email, phone FROM $table_name WHERE unique_token = %s", $token));
        if ($existing) {
            $target_guest_id = $existing->id;
            $target_token    = $existing->unique_token;
            if (empty($full_name)) $full_name = $existing->full_name;
            if (empty($email))     $email     = $existing->email;
            if (empty($phone))     $phone     = $existing->phone;
        }
    }

    if (!$target_guest_id && !empty($email)) {
        $existing_by_email = $wpdb->get_row($wpdb->prepare("SELECT id, unique_token FROM $table_name WHERE email = %s", $email));
        if ($existing_by_email) {
            $target_guest_id = $existing_by_email->id;
            $target_token    = $existing_by_email->unique_token;
        }
    }

    if (empty($full_name) || empty($email) || !is_email($email) || empty($phone)) {
        wp_send_json_error(['message' => 'يرجى إكمال البيانات الأساسية المطلوبة بدقة (الاسم، البريد، ورقم الجوال).']);
    }

    $new_status = ($decision === 'yes') ? 'confirmed_vip' : 'declined';

    if ($target_guest_id) {
        $wpdb->update(
            $table_name,
            [
                'full_name'      => $full_name,
                'email'          => $email,
                'phone'          => $phone,
                'organization'   => $organization,
                'job_title'      => $job_title,
                'guest_type'     => 'vip',
                'companion_name' => ($decision === 'yes') ? $companion_name : '',
                'dietary_notes'  => ($decision === 'yes') ? $dietary_notes : '',
                'status'         => $new_status
            ],
            ['id' => $target_guest_id],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    } else {
        $target_token = wp_generate_password(16, false);
        $wpdb->insert(
            $table_name,
            [
                'unique_token'   => $target_token,
                'guest_type'     => 'vip',
                'full_name'      => $full_name,
                'email'          => $email,
                'phone'          => $phone,
                'organization'   => $organization,
                'job_title'      => $job_title,
                'companion_name' => ($decision === 'yes') ? $companion_name : '',
                'dietary_notes'  => ($decision === 'yes') ? $dietary_notes : '',
                'status'         => $new_status,
                'created_at'     => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        $target_guest_id = $wpdb->insert_id;
    }

    if ($decision === 'yes') {
        if (function_exists('tarbwy_send_vip_confirmed_email')) {
            tarbwy_send_vip_confirmed_email($target_guest_id);
        }

        $ticket_url = add_query_arg('tarbwy_ticket', $target_token, home_url('/'));
        $qr_url     = 'https://quickchart.io/qr?text=' . urlencode($target_token) . '&size=300&margin=2&format=png';

        wp_send_json_success([
            'decision'   => 'yes',
            'token'      => $target_token,
            'qr_url'     => $qr_url,
            'ticket_url' => $ticket_url
        ]);
    } else {
        wp_send_json_success([
            'decision' => 'no'
        ]);
    }
}

/**
 * =========================================================================
 * 8. دوال AJAX الإدارية (تغيير الحالة، حذف، تصفير شامل)
 * =========================================================================
 */
add_action('wp_ajax_tarbwy_change_guest_status', 'tarbwy_ajax_change_guest_status');

function tarbwy_ajax_change_guest_status() {
    check_ajax_referer('tarbwy_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'غير مصرح.']);
    }

    $guest_id   = isset($_POST['guest_id']) ? intval($_POST['guest_id']) : 0;
    $new_status = isset($_POST['new_status']) ? sanitize_text_field(wp_unslash($_POST['new_status'])) : '';

    $allowed = ['pending', 'approved', 'waitlist', 'declined', 'confirmed_vip', 'checked_in'];
    if (!$guest_id || !in_array($new_status, $allowed, true)) {
        wp_send_json_error(['message' => 'معطيات غير صالحة.']);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'tarbwy_guests';

    $updated = $wpdb->update(
        $table_name,
        ['status' => $new_status],
        ['id' => $guest_id],
        ['%s'],
        ['%d']
    );

    if ($updated !== false) {
        if ($new_status === 'approved' && function_exists('tarbwy_send_public_approved_email')) {
            tarbwy_send_public_approved_email($guest_id);
        }
        wp_send_json_success(['message' => 'تم تحديث حالة الضيف بنجاح.']);
    }

    wp_send_json_error(['message' => 'تعذر تحديث الحالة.']);
}

add_action('wp_ajax_tarbwy_delete_guest', 'tarbwy_ajax_delete_guest');

function tarbwy_ajax_delete_guest() {
    check_ajax_referer('tarbwy_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'غير مصرح.']);
    }

    $guest_id = isset($_POST['guest_id']) ? intval($_POST['guest_id']) : 0;
    global $wpdb;
    $table_name = $wpdb->prefix . 'tarbwy_guests';

    $deleted = $wpdb->delete($table_name, ['id' => $guest_id], ['%d']);
    if ($deleted) {
        wp_send_json_success(['message' => 'تم حذف السجل بنجاح.']);
    }
    wp_send_json_error(['message' => 'تعذر حذف السجل.']);
}

add_action('wp_ajax_tarbwy_reset_all_guests', 'tarbwy_ajax_reset_all_guests');

function tarbwy_ajax_reset_all_guests() {
    check_ajax_referer('tarbwy_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'غير مصرح.']);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'tarbwy_guests';
    $wpdb->query("TRUNCATE TABLE $table_name");

    wp_send_json_success(['message' => 'تم مسح وتصفير جميع بيانات الحضور بنجاح.']);
}