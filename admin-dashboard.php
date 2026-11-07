<?php
/**
 * لوحة التحكم الإدارية لنظام حفل إشهار تربوي
 * File: includes/admin-dashboard.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تسجيل القوائم الإدارية
 */
add_action('admin_menu', 'tarbwy_register_admin_menus');

function tarbwy_register_admin_menus() {
    add_menu_page(
        'إدارة حفل تربوي',
        'حفل إشهار تربوي',
        'manage_options',
        'tarbwy-guests',
        'tarbwy_render_guests_page',
        'dashicons-tickets-alt',
        25
    );

    add_submenu_page(
        'tarbwy-guests',
        'قائمة الحضور والطلبات',
        'قائمة الحضور والطلبات',
        'manage_options',
        'tarbwy-guests',
        'tarbwy_render_guests_page'
    );

    add_submenu_page(
        'tarbwy-guests',
        'إعدادات الهوية والمتحدثين',
        'الهوية والمتحدثون',
        'manage_options',
        'tarbwy-settings',
        'tarbwy_render_settings_page'
    );
}

/**
 * تحميل مكتبة الوسائط في صفحة الإعدادات
 */
add_action('admin_enqueue_scripts', 'tarbwy_admin_enqueue_media');

function tarbwy_admin_enqueue_media($hook) {
    if (strpos($hook, 'tarbwy-settings') !== false) {
        wp_enqueue_media();
    }
}

/**
 * الصفحة 1: قائمة الحضور والطلبات
 */
function tarbwy_render_guests_page() {
    if (!current_user_can('manage_options')) {
        wp_die('عذراً، لا تملك الصلاحية.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'tarbwy_guests';

    // إضافة VIP يدوي
    $vip_created_msg = '';
    $new_vip_link = '';
    if (isset($_POST['tarbwy_add_vip_nonce']) && wp_verify_nonce($_POST['tarbwy_add_vip_nonce'], 'tarbwy_add_vip_action')) {
        $full_name    = sanitize_text_field(wp_unslash($_POST['vip_name']));
        $email        = sanitize_email(wp_unslash($_POST['vip_email']));
        $phone        = sanitize_text_field(wp_unslash($_POST['vip_phone']));
        $organization = sanitize_text_field(wp_unslash($_POST['vip_org']));
        $job_title    = sanitize_text_field(wp_unslash($_POST['vip_job']));

        if (!empty($full_name) && !empty($email)) {
            $token = wp_generate_password(16, false);
            $wpdb->insert(
                $table_name,
                [
                    'unique_token' => $token,
                    'guest_type'   => 'vip',
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
            $new_vip_link = home_url('/launch/vip/?token=' . $token);
            $vip_created_msg = 'تمت إضافة الضيف VIP وتوليد الرابط المخصص بنجاح!';
        }
    }

    // إحصائيات سريعة
    $total_all     = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $total_pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
    $total_approved= (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'approved'");
    $total_waitlist= (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'waitlist'");
    $total_vip     = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE guest_type = 'vip'");
    $total_conf_vip= (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'confirmed_vip'");
    $total_declined= (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'declined'");
    $total_checked = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'checked_in'");

    $filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
    $search = isset($_GET['s']) ? sanitize_text_field(trim($_GET['s'])) : '';

    $where = [];
    if (!empty($search)) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = $wpdb->prepare("(full_name LIKE %s OR email LIKE %s OR phone LIKE %s OR organization LIKE %s)", $search_like, $search_like, $search_like, $search_like);
    }

    if ($filter === 'vip') {
        $where[] = "guest_type = 'vip'";
    } elseif ($filter !== 'all' && !empty($filter)) {
        $where[] = $wpdb->prepare("status = %s", $filter);
    }

    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $guests = $wpdb->get_results("SELECT * FROM $table_name $where_sql ORDER BY id DESC LIMIT 500");

    $admin_nonce = wp_create_nonce('tarbwy_admin_nonce');
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <div class="wrap text-right font-sans pr-4" dir="rtl" style="font-family: 'Cairo', sans-serif;">
        
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 my-6 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div>
                <h1 class="text-2xl font-black text-[#0F2942] flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-[#C5A059] text-[#0F2942] flex items-center justify-center text-lg shadow-sm">
                        <i class="fa-solid fa-layer-group"></i>
                    </span>
                    إدارة حفل الإشهار «تربوي – الاتحاد العربي للقيادة التربوية»
                </h1>
                <p class="text-xs text-slate-500 mt-1">لوحة متابعة المقاعد، تذاكر الدخول، وتأكيدات الـ VIP</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="<?php echo esc_url(admin_url('admin-post.php?action=tarbwy_export_guests_csv&filter=' . urlencode($filter))); ?>" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-2 shadow transition">
                    <i class="fa-solid fa-file-excel"></i>
                    <span>تصدير Excel (CSV)</span>
                </a>
                <button type="button" onclick="confirmResetAll();" class="px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs flex items-center gap-2 shadow transition">
                    <i class="fa-solid fa-trash-can"></i>
                    <span>تصفير ومسح الجدول</span>
                </button>
            </div>
        </div>

        <!-- بطاقات الـ KPIs -->
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 mb-8">
            <a href="?page=tarbwy-guests&status_filter=all" class="p-4 rounded-xl border transition shadow-sm <?php echo $filter === 'all' ? 'bg-[#0F2942] text-white border-[#0F2942]' : 'bg-white text-slate-800 border-slate-200'; ?>">
                <div class="text-[11px] font-bold opacity-75">إجمالي الطلبات</div>
                <div class="text-2xl font-black mt-1"><?php echo esc_html($total_all); ?></div>
            </a>
            <a href="?page=tarbwy-guests&status_filter=pending" class="p-4 rounded-xl border transition shadow-sm <?php echo $filter === 'pending' ? 'bg-amber-600 text-white' : 'bg-white text-slate-800 border-slate-200'; ?>">
                <div class="text-[11px] font-bold text-amber-600 <?php echo $filter === 'pending' ? 'text-white' : ''; ?>">قيد المراجعة</div>
                <div class="text-2xl font-black mt-1"><?php echo esc_html($total_pending); ?></div>
            </a>
            <a href="?page=tarbwy-guests&status_filter=approved" class="p-4 rounded-xl border transition shadow-sm <?php echo $filter === 'approved' ? 'bg-blue-600 text-white' : 'bg-white text-slate-800 border-slate-200'; ?>">
                <div class="text-[11px] font-bold text-blue-600 <?php echo $filter === 'approved' ? 'text-white' : ''; ?>">مقبول ومؤكد</div>
                <div class="text-2xl font-black mt-1"><?php echo esc_html($total_approved); ?></div>
            </a>
            <a href="?page=tarbwy-guests&status_filter=waitlist" class="p-4 rounded-xl border transition shadow-sm <?php echo $filter === 'waitlist' ? 'bg-purple-600 text-white' : 'bg-white text-slate-800 border-slate-200'; ?>">
                <div class="text-[11px] font-bold text-purple-600 <?php echo $filter === 'waitlist' ? 'text-white' : ''; ?>">قائمة الانتظار</div>
                <div class="text-2xl font-black mt-1"><?php echo esc_html($total_waitlist); ?></div>
            </a>
            <a href="?page=tarbwy-guests&status_filter=vip" class="p-4 rounded-xl border transition shadow-sm <?php echo $filter === 'vip' ? 'bg-[#C5A059] text-[#0F2942]' : 'bg-white text-slate-800 border-slate-200'; ?>">
                <div class="text-[11px] font-bold text-[#C5A059] <?php echo $filter === 'vip' ? 'text-[#0F2942]' : ''; ?>">مدعوين VIP</div>
                <div class="text-2xl font-black mt-1"><?php echo esc_html($total_vip); ?></div>
            </a>
            <a href="?page=tarbwy-guests&status_filter=confirmed_vip" class="p-4 rounded-xl border transition shadow-sm <?php echo $filter === 'confirmed_vip' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-800 border-slate-200'; ?>">
                <div class="text-[11px] font-bold text-emerald-600 <?php echo $filter === 'confirmed_vip' ? 'text-white' : ''; ?>">VIP مؤكد</div>
                <div class="text-2xl font-black mt-1"><?php echo esc_html($total_conf_vip); ?></div>
            </a>
            <a href="?page=tarbwy-guests&status_filter=declined" class="p-4 rounded-xl border transition shadow-sm <?php echo $filter === 'declined' ? 'bg-rose-600 text-white' : 'bg-white text-slate-800 border-slate-200'; ?>">
                <div class="text-[11px] font-bold text-rose-600 <?php echo $filter === 'declined' ? 'text-white' : ''; ?>">معتذر / مرفوض</div>
                <div class="text-2xl font-black mt-1"><?php echo esc_html($total_declined); ?></div>
            </a>
            <a href="?page=tarbwy-guests&status_filter=checked_in" class="p-4 rounded-xl border transition shadow-sm <?php echo $filter === 'checked_in' ? 'bg-teal-600 text-white' : 'bg-white text-slate-800 border-slate-200'; ?>">
                <div class="text-[11px] font-bold text-teal-600 <?php echo $filter === 'checked_in' ? 'text-white' : ''; ?>">حضر بالبوابة</div>
                <div class="text-2xl font-black mt-1"><?php echo esc_html($total_checked); ?></div>
            </a>
        </div>

        <!-- إضافة VIP -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-8">
            <h2 class="text-base font-extrabold text-[#0F2942] mb-4 flex items-center gap-2">
                <i class="fa-solid fa-crown text-[#C5A059]"></i>
                <span>إضافة مدعو VIP وتوليد رابط مخصص</span>
            </h2>

            <?php if (!empty($vip_created_msg)): ?>
                <div class="mb-4 p-4 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div><i class="fa-solid fa-circle-check ml-1"></i> <?php echo esc_html($vip_created_msg); ?></div>
                    <div class="flex items-center gap-2">
                        <input type="text" readonly value="<?php echo esc_url($new_vip_link); ?>" id="tarbwy-new-link" class="px-3 py-1 bg-white rounded border text-xs w-72 text-left font-mono" dir="ltr">
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('tarbwy-new-link').value); alert('تم نسخ الرابط!');" class="px-3 py-1 bg-[#0F2942] text-[#C5A059] rounded text-xs font-bold">نسخ</button>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <?php wp_nonce_field('tarbwy_add_vip_action', 'tarbwy_add_vip_nonce'); ?>
                <input type="text" name="vip_name" required placeholder="الاسم الكريم *" class="px-3 py-2 border rounded-xl text-xs bg-slate-50">
                <input type="email" name="vip_email" required placeholder="البريد الإلكتروني *" class="px-3 py-2 border rounded-xl text-xs bg-slate-50">
                <input type="tel" name="vip_phone" placeholder="الجوال" dir="ltr" class="px-3 py-2 border rounded-xl text-xs bg-slate-50 text-right">
                <input type="text" name="vip_org" placeholder="الجهة / المؤسسة" class="px-3 py-2 border rounded-xl text-xs bg-slate-50">
                <div class="flex gap-2">
                    <input type="text" name="vip_job" placeholder="الصفة / المنصب" class="px-3 py-2 border rounded-xl text-xs bg-slate-50 flex-1">
                    <button type="submit" class="px-4 py-2 bg-[#0F2942] hover:bg-[#1E4E79] text-[#C5A059] rounded-xl text-xs font-bold shrink-0">إضافة</button>
                </div>
            </form>
        </div>

        <!-- جدول الحضور -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs text-slate-700 border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[#0F2942] font-black">
                            <th class="p-3.5">#</th>
                            <th class="p-3.5">الاسم والصفة</th>
                            <th class="p-3.5">التواصل</th>
                            <th class="p-3.5">الجهة</th>
                            <th class="p-3.5">النوع</th>
                            <th class="p-3.5">الحالة</th>
                            <th class="p-3.5">المرافق والملاحظات</th>
                            <th class="p-3.5 text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($guests)): ?>
                            <tr><td colspan="8" class="p-8 text-center text-slate-400">لا توجد بيانات مطابقة.</td></tr>
                        <?php else: foreach ($guests as $g): 
                            $ticket_url = add_query_arg('tarbwy_ticket', $g->unique_token, home_url('/'));
                        ?>
                            <tr id="guest-row-<?php echo esc_attr($g->id); ?>" class="hover:bg-slate-50">
                                <td class="p-3.5 text-slate-400"><?php echo esc_html($g->id); ?></td>
                                <td class="p-3.5">
                                    <div class="font-black text-[#0F2942]"><?php echo esc_html($g->full_name); ?></div>
                                    <div class="text-[11px] text-slate-500"><?php echo esc_html($g->job_title ?: '-'); ?></div>
                                </td>
                                <td class="p-3.5" dir="ltr">
                                    <div><?php echo esc_html($g->email); ?></div>
                                    <div class="text-slate-400 font-mono text-[11px]"><?php echo esc_html($g->phone); ?></div>
                                </td>
                                <td class="p-3.5 font-medium"><?php echo esc_html($g->organization ?: '-'); ?></td>
                                <td class="p-3.5">
                                    <?php echo $g->guest_type === 'vip' 
                                        ? '<span class="px-2 py-0.5 rounded-full bg-amber-100 text-[#0F2942] font-bold border border-[#C5A059]/40">VIP</span>' 
                                        : '<span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-bold">عام</span>'; ?>
                                </td>
                                <td class="p-3.5">
                                    <span class="px-2.5 py-1 rounded-full font-bold text-[10px] 
                                    <?php 
                                        if ($g->status === 'confirmed_vip') echo 'bg-emerald-100 text-emerald-800';
                                        elseif ($g->status === 'approved') echo 'bg-blue-100 text-blue-800';
                                        elseif ($g->status === 'pending') echo 'bg-amber-100 text-amber-800';
                                        elseif ($g->status === 'waitlist') echo 'bg-purple-100 text-purple-800';
                                        elseif ($g->status === 'checked_in') echo 'bg-teal-100 text-teal-800';
                                        else echo 'bg-rose-100 text-rose-800';
                                    ?>">
                                        <?php echo esc_html($g->status); ?>
                                    </span>
                                </td>
                                <td class="p-3.5">
                                    <?php if (!empty($g->companion_name)) echo '<div class="text-[11px] font-bold"><i class="fa-solid fa-user-group text-[#C5A059]"></i> ' . esc_html($g->companion_name) . '</div>'; ?>
                                    <?php if (!empty($g->dietary_notes)) echo '<div class="text-[10px] text-slate-400">' . esc_html($g->dietary_notes) . '</div>'; ?>
                                </td>
                                <td class="p-3.5 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="button" title="قبول" onclick="updateGuestStatus(<?php echo esc_attr($g->id); ?>, 'approved');" class="w-7 h-7 rounded bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white transition"><i class="fa-solid fa-check"></i></button>
                                        <button type="button" title="انتظار" onclick="updateGuestStatus(<?php echo esc_attr($g->id); ?>, 'waitlist');" class="w-7 h-7 rounded bg-purple-50 text-purple-700 hover:bg-purple-600 hover:text-white transition"><i class="fa-solid fa-clock"></i></button>
                                        <button type="button" title="اعتذار/رفض" onclick="updateGuestStatus(<?php echo esc_attr($g->id); ?>, 'declined');" class="w-7 h-7 rounded bg-rose-50 text-rose-700 hover:bg-rose-600 hover:text-white transition"><i class="fa-solid fa-xmark"></i></button>
                                        <a href="<?php echo esc_url($ticket_url); ?>" target="_blank" title="التذكرة والـ QR" class="w-7 h-7 rounded bg-blue-50 text-blue-700 hover:bg-blue-600 hover:text-white transition flex items-center justify-center"><i class="fa-solid fa-ticket"></i></a>
                                        <button type="button" title="حذف" onclick="deleteGuest(<?php echo esc_attr($g->id); ?>);" class="w-7 h-7 rounded bg-red-100 text-red-700 hover:bg-red-700 hover:text-white transition"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
    var tarbwyAdminNonce = '<?php echo esc_js($admin_nonce); ?>';
    var tarbwyAjaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

    function updateGuestStatus(id, st) {
        if (!confirm('تأكيد تغيير الحالة إلى: ' + st + '؟')) return;
        var fd = new FormData();
        fd.append('action', 'tarbwy_change_guest_status');
        fd.append('nonce', tarbwyAdminNonce);
        fd.append('guest_id', id);
        fd.append('new_status', st);
        fetch(tarbwyAjaxUrl, { method: 'POST', body: fd }).then(function(r){return r.json();}).then(function(res){
            alert(res.data.message);
            location.reload();
        });
    }

    function deleteGuest(id) {
        if (!confirm('حذف هذا المسجل نهائياً؟')) return;
        var fd = new FormData();
        fd.append('action', 'tarbwy_delete_guest');
        fd.append('nonce', tarbwyAdminNonce);
        fd.append('guest_id', id);
        fetch(tarbwyAjaxUrl, { method: 'POST', body: fd }).then(function(r){return r.json();}).then(function(res){
            var row = document.getElementById('guest-row-' + id);
            if(row) row.remove();
            alert(res.data.message);
        });
    }

    function confirmResetAll() {
        if (prompt('اكتب كلمة "مسح" لتصفير كافة البيانات:') !== 'مسح') return;
        var fd = new FormData();
        fd.append('action', 'tarbwy_reset_all_guests');
        fd.append('nonce', tarbwyAdminNonce);
        fetch(tarbwyAjaxUrl, { method: 'POST', body: fd }).then(function(r){return r.json();}).then(function(res){
            alert(res.data.message);
            location.reload();
        });
    }
    </script>
    <?php
}

/**
 * الصفحة 2: إعدادات الهوية والمتحدثين (معالجة الحفظ المباشرة الصريحة)
 */
function tarbwy_render_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('غير مصرح.');
    }

    $notice = '';

    // =========================================================================
    // معالجة الحفظ المباشرة الصريحة فور الضغط على زر الحفظ
    // =========================================================================
    if (isset($_POST['tarbwy_save_settings_btn']) && check_admin_referer('tarbwy_settings_nonce_action', 'tarbwy_settings_nonce_field')) {
        
        // تجهيز المتحدثين
        $sanitized_speakers = [];
        if (isset($_POST['speakers']) && is_array($_POST['speakers'])) {
            foreach ($_POST['speakers'] as $sp) {
                if (!empty($sp['name'])) {
                    $sanitized_speakers[] = [
                        'name'  => sanitize_text_field($sp['name']),
                        'title' => sanitize_text_field($sp['title']),
                        'org'   => sanitize_text_field($sp['org']),
                        'photo' => esc_url_raw($sp['photo'])
                    ];
                }
            }
        }

        $new_settings = [
            'logo_url'        => esc_url_raw($_POST['logo_url']),
            'primary_color'   => sanitize_hex_color($_POST['primary_color']),
            'secondary_color' => sanitize_hex_color($_POST['secondary_color']),
            'accent_color'    => sanitize_hex_color($_POST['accent_color']),
            'bg_color'        => sanitize_hex_color($_POST['bg_color']),
            'event_date'      => sanitize_text_field(wp_unslash($_POST['event_date'])),
            'event_location'  => sanitize_text_field(wp_unslash($_POST['event_location'])),
            'scanner_pin'     => sanitize_text_field(wp_unslash($_POST['scanner_pin'])),
            'contact_email'   => sanitize_email($_POST['contact_email']),
            'speakers'        => $sanitized_speakers
        ];

        update_option('tarbwy_event_settings', $new_settings);
        $notice = 'تم حفظ الإعدادات وقائمة المتحدثين بنجاح.';
    }

    $settings = tarbwy_get_settings();
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <div class="wrap text-right font-sans pr-4" dir="rtl" style="font-family: 'Cairo', sans-serif;">
        
        <div class="max-w-4xl my-6 bg-white p-8 rounded-3xl shadow-sm border border-slate-200">
            <h1 class="text-2xl font-black text-[#0F2942] mb-1">إعدادات الهوية، الحدث، والمتحدثين</h1>
            <p class="text-xs text-slate-500 mb-6">يتم تطبيق هذه الإعدادات تلقائياً في صفحة الهبوط، التذاكر، والإيميلات.</p>

            <?php if (!empty($notice)): ?>
                <div class="mb-6 p-4 rounded-2xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-black flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-base"></i> <?php echo esc_html($notice); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <?php wp_nonce_field('tarbwy_settings_nonce_action', 'tarbwy_settings_nonce_field'); ?>

                <!-- الشعار الرسمي مع زر مكتبة الوسائط -->
                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200">
                    <label class="block text-xs font-black text-[#0F2942] mb-2">الشعار الرسمي (Logo)</label>
                    <div class="flex gap-2">
                        <input type="text" id="tarbwy_logo_url" name="logo_url" value="<?php echo esc_attr($settings['logo_url']); ?>" placeholder="رابط صورة الشعار" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-300 text-xs bg-white text-left font-mono" dir="ltr">
                        <button type="button" onclick="openMediaUploader('tarbwy_logo_url');" class="px-4 py-2.5 bg-[#0F2942] text-[#C5A059] font-bold text-xs rounded-xl shrink-0">اختيار من الوسائط</button>
                    </div>
                </div>

                <!-- ألوان الهوية البصرية الرسمية -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                        <label class="block text-[11px] font-black text-[#0F2942] mb-1">الأساسي (Navy)</label>
                        <input type="color" name="primary_color" value="<?php echo esc_attr($settings['primary_color']); ?>" class="w-full h-9 rounded border cursor-pointer">
                    </div>
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                        <label class="block text-[11px] font-black text-[#0F2942] mb-1">الثانوي (Gold)</label>
                        <input type="color" name="secondary_color" value="<?php echo esc_attr($settings['secondary_color']); ?>" class="w-full h-9 rounded border cursor-pointer">
                    </div>
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                        <label class="block text-[11px] font-black text-[#0F2942] mb-1">التمييز (Accent)</label>
                        <input type="color" name="accent_color" value="<?php echo esc_attr($settings['accent_color']); ?>" class="w-full h-9 rounded border cursor-pointer">
                    </div>
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                        <label class="block text-[11px] font-black text-[#0F2942] mb-1">الخلفية (Light)</label>
                        <input type="color" name="bg_color" value="<?php echo esc_attr($settings['bg_color']); ?>" class="w-full h-9 rounded border cursor-pointer">
                    </div>
                </div>

                <!-- الموعد والمكان -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">تاريخ وموعد الحفل</label>
                        <input type="text" name="event_date" value="<?php echo esc_attr($settings['event_date']); ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">مقر ومكان الحفل</label>
                        <input type="text" name="event_location" value="<?php echo esc_attr($settings['event_location']); ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs">
                    </div>
                </div>

                <!-- PIN والبريد -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">رمز PIN لماسح البوابات</label>
                        <input type="text" name="scanner_pin" value="<?php echo esc_attr($settings['scanner_pin']); ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">بريد التواصل للاستفسارات</label>
                        <input type="email" name="contact_email" value="<?php echo esc_attr($settings['contact_email']); ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs text-left" dir="ltr">
                    </div>
                </div>

                <!-- قسم إدارة المتحدثين مع صورهم -->
                <div class="border-t border-slate-200 pt-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-base font-black text-[#0F2942]">المتحدثون والشخصيات البارزة</h3>
                            <p class="text-xs text-slate-500">يمكنك إضافة صورهم من مكتبة الوسائط وستظهر تلقائياً عبر الشورت كود [tarbwy_speakers_grid]</p>
                        </div>
                        <button type="button" onclick="addSpeakerRow();" class="px-4 py-2 rounded-xl bg-[#C5A059] text-[#0F2942] font-black text-xs shadow-sm">+ إضافة متحدث</button>
                    </div>

                    <div id="speakers-container" class="space-y-3">
                        <?php 
                        $speakers = !empty($settings['speakers']) && is_array($settings['speakers']) ? $settings['speakers'] : [];
                        if (empty($speakers)) {
                            $speakers = [
                                ['name' => 'د. عبد الرحمن السليمان', 'title' => 'أستاذ السياسات التعليمية', 'org' => 'متحدث رئيسي', 'photo' => '']
                            ];
                        }
                        foreach ($speakers as $idx => $sp): 
                        ?>
                            <div class="speaker-row bg-slate-50 p-4 rounded-2xl border border-slate-200 grid grid-cols-1 sm:grid-cols-4 gap-2 items-center">
                                <input type="text" name="speakers[<?php echo $idx; ?>][name]" value="<?php echo esc_attr($sp['name']); ?>" placeholder="اسم المتحدث" class="px-3 py-2 border rounded-xl text-xs bg-white">
                                <input type="text" name="speakers[<?php echo $idx; ?>][title]" value="<?php echo esc_attr($sp['title']); ?>" placeholder="الصفة المهنية" class="px-3 py-2 border rounded-xl text-xs bg-white">
                                <input type="text" name="speakers[<?php echo $idx; ?>][org]" value="<?php echo esc_attr($sp['org']); ?>" placeholder="الجهة / المنصب" class="px-3 py-2 border rounded-xl text-xs bg-white">
                                <div class="flex gap-1.5">
                                    <input type="text" id="speaker_photo_<?php echo $idx; ?>" name="speakers[<?php echo $idx; ?>][photo]" value="<?php echo esc_attr($sp['photo']); ?>" placeholder="رابط الصورة" class="flex-1 px-3 py-2 border rounded-xl text-xs bg-white text-left font-mono" dir="ltr">
                                    <button type="button" onclick="openMediaUploader('speaker_photo_<?php echo $idx; ?>');" class="px-2.5 py-2 bg-slate-200 text-slate-700 text-xs rounded-xl shrink-0"><i class="fa-solid fa-image"></i></button>
                                    <button type="button" onclick="this.closest('.speaker-row').remove();" class="px-2.5 py-2 bg-rose-100 text-rose-700 text-xs rounded-xl shrink-0"><i class="fa-solid fa-trash"></i></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" name="tarbwy_save_settings_btn" value="1" class="px-8 py-3.5 rounded-xl bg-[#0F2942] hover:bg-[#1E4E79] text-[#C5A059] font-black text-sm shadow-xl transition">
                        حفظ الإعدادات المحدثة
                    </button>
                </div>
            </form>
        </div>

    </div>

    <!-- كود وسائط ووردبريس لإضافة الصور بسهولة -->
    <script>
    function openMediaUploader(targetInputId) {
        var mediaUploader = wp.media({
            title: 'اختر الصورة من مكتبة الوسائط',
            button: { text: 'استخدام هذه الصورة' },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            document.getElementById(targetInputId).value = attachment.url;
        });

        mediaUploader.open();
    }

    var speakerIndex = <?php echo count($speakers); ?>;
    function addSpeakerRow() {
        var container = document.getElementById('speakers-container');
        var row = document.createElement('div');
        row.className = 'speaker-row bg-slate-50 p-4 rounded-2xl border border-slate-200 grid grid-cols-1 sm:grid-cols-4 gap-2 items-center';
        row.innerHTML = 
            '<input type="text" name="speakers[' + speakerIndex + '][name]" placeholder="اسم المتحدث" class="px-3 py-2 border rounded-xl text-xs bg-white">' +
            '<input type="text" name="speakers[' + speakerIndex + '][title]" placeholder="الصفة المهنية" class="px-3 py-2 border rounded-xl text-xs bg-white">' +
            '<input type="text" name="speakers[' + speakerIndex + '][org]" placeholder="الجهة / المنصب" class="px-3 py-2 border rounded-xl text-xs bg-white">' +
            '<div class="flex gap-1.5">' +
                '<input type="text" id="speaker_photo_' + speakerIndex + '" name="speakers[' + speakerIndex + '][photo]" placeholder="رابط الصورة" class="flex-1 px-3 py-2 border rounded-xl text-xs bg-white text-left font-mono" dir="ltr">' +
                '<button type="button" onclick="openMediaUploader(\'speaker_photo_' + speakerIndex + '\');" class="px-2.5 py-2 bg-slate-200 text-slate-700 text-xs rounded-xl shrink-0"><i class="fa-solid fa-image"></i></button>' +
                '<button type="button" onclick="this.closest(\'.speaker-row\').remove();" class="px-2.5 py-2 bg-rose-100 text-rose-700 text-xs rounded-xl shrink-0"><i class="fa-solid fa-trash"></i></button>' +
            '</div>';
        container.appendChild(row);
        speakerIndex++;
    }
    </script>
    <?php
}