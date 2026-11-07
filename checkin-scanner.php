<?php
/**
 * نظام الاستقبال والماسح الضوئي لتسجيل الدخول يوم الحفل
 * File: includes/checkin-scanner.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تسجيل الشورت كود الرئيسي [tarbwy_checkin_scanner]
 */
add_shortcode('tarbwy_checkin_scanner', 'tarbwy_render_checkin_scanner_page');

function tarbwy_render_checkin_scanner_page() {
    ob_start();

    $settings = function_exists('tarbwy_get_settings') ? tarbwy_get_settings() : ['scanner_pin' => '2025'];
    $is_admin = current_user_can('manage_options');
    $correct_pin = !empty($settings['scanner_pin']) ? $settings['scanner_pin'] : '2025';

    $ajax_url = admin_url('admin-ajax.php');
    $scanner_nonce = wp_create_nonce('tarbwy_scanner_action_nonce');
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        .tarbwy-scanner-root {
            direction: rtl;
            text-align: right;
            font-family: 'Cairo', sans-serif;
            background-color: #081726;
            min-height: 100vh;
        }
        #reader video {
            border-radius: 1.25rem !important;
            object-fit: cover !important;
            width: 100% !important;
        }
        #reader {
            border: 2px dashed #C5A059 !important;
            border-radius: 1.5rem !important;
            background: #020617;
            overflow: hidden;
        }
        @keyframes scanLaser {
            0% { top: 0%; opacity: 0.8; }
            50% { top: 95%; opacity: 1; }
            100% { top: 0%; opacity: 0.8; }
        }
        .laser-line {
            position: absolute;
            left: 5%;
            right: 5%;
            height: 3px;
            background: #C5A059;
            box-shadow: 0 0 14px #C5A059;
            animation: scanLaser 2.2s infinite ease-in-out;
            pointer-events: none;
            z-index: 10;
        }
    </style>

    <div class="tarbwy-scanner-root text-slate-100 p-4 sm:p-6 antialiased">
        
        <!-- شاشة الحماية بـ PIN إذا لم يكن مديراً مسجلاً -->
        <div id="pin-lock-screen" class="<?php echo $is_admin ? 'hidden' : ''; ?> fixed inset-0 bg-slate-950/95 z-50 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 max-w-sm w-full text-center shadow-2xl">
                <div class="w-16 h-16 rounded-2xl bg-[#C5A059]/20 text-[#C5A059] flex items-center justify-center text-3xl mx-auto mb-4">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <h3 class="text-xl font-black text-white mb-2">بوابة استقبال حفل تربوي</h3>
                <p class="text-xs text-slate-400 mb-6">يرجى إدخال رمز المرور (PIN) المصرح به لفريق الاستقبال لتفعيل الماسح الضوئي.</p>
                
                <form id="pin-auth-form" class="space-y-4">
                    <input type="password" id="scanner-pin-input" required maxlength="10" placeholder="أدخل رمز الـ PIN" class="w-full text-center tracking-widest text-2xl font-mono py-3 rounded-xl bg-slate-800 border border-slate-700 text-[#C5A059] focus:outline-none focus:ring-2 focus:ring-[#C5A059]">
                    <button type="submit" class="w-full py-3.5 rounded-xl bg-[#C5A059] hover:bg-[#b08e4c] text-[#0F2942] font-black text-sm transition shadow-lg">
                        فتح البوابة والماسح الضوئي
                    </button>
                    <div id="pin-error-msg" class="hidden text-xs text-rose-400 font-bold mt-2">رمز الـ PIN غير صحيح، يرجى مراجعة المشرف.</div>
                </form>
            </div>
        </div>

        <div class="max-w-4xl mx-auto">
            
            <!-- الهيدر وشريط العدادات الحية المتزامنة -->
            <header class="mb-6 bg-slate-900/90 border border-slate-800 rounded-3xl p-5 shadow-lg backdrop-blur">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-4 pb-4 border-b border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-[#C5A059] text-[#0F2942] flex items-center justify-center text-xl font-black">
                            <i class="fa-solid fa-qrcode"></i>
                        </div>
                        <div>
                            <h1 class="text-lg font-black text-white">بوابة تسجيل الحضور الإلكتروني</h1>
                            <p class="text-xs text-[#C5A059] font-semibold">حفل إشهار «تربوي – الاتحاد العربي للقيادة التربوية»</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs font-bold">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            النظام متصل
                        </span>
                        <button type="button" onclick="fetchLiveStats();" title="تحديث العدادات" class="w-8 h-8 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center transition">
                            <i class="fa-solid fa-arrows-rotate text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- العدادات الـ 4 الحية -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                    <div class="bg-slate-950/60 p-3 rounded-2xl border border-slate-800/80">
                        <div class="text-[11px] text-slate-400 font-semibold">المؤكد حضورهم</div>
                        <div id="stat-expected" class="text-2xl font-black text-white mt-1">--</div>
                    </div>
                    
                    <div class="bg-emerald-950/40 p-3 rounded-2xl border border-emerald-800/40">
                        <div class="text-[11px] text-emerald-300 font-semibold">الحاضرون بالداخل الآن</div>
                        <div id="stat-checked" class="text-2xl font-black text-emerald-400 mt-1">--</div>
                    </div>

                    <div class="bg-amber-950/40 p-3 rounded-2xl border border-amber-800/40">
                        <div class="text-[11px] text-[#C5A059] font-semibold">حضور الـ VIP الفعلي</div>
                        <div id="stat-vip-checked" class="text-2xl font-black text-[#C5A059] mt-1">--</div>
                    </div>

                    <div class="bg-slate-950/60 p-3 rounded-2xl border border-slate-800/80">
                        <div class="text-[11px] text-slate-400 font-semibold">نسبة الاكتمال الحالية</div>
                        <div id="stat-percentage" class="text-2xl font-black text-cyan-400 mt-1">0%</div>
                    </div>
                </div>
            </header>

            <!-- صندوق التنبيه التفاعلي لنتائج المسح -->
            <div id="scan-feedback-container" class="hidden mb-6 transition-all duration-300"></div>

            <!-- شبكة العمليات: كاميرا الماسح + الإدخال والبحث اليدوي -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- عمود كاميرا الـ QR Code -->
                <div class="lg:col-span-7 bg-slate-900 border border-slate-800 p-5 rounded-3xl shadow-xl">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-slate-300 flex items-center gap-2">
                            <i class="fa-solid fa-camera text-[#C5A059]"></i> ماسح الكاميرا الحي
                        </span>
                        <button type="button" id="toggle-camera-btn" class="px-3 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs text-slate-200 transition">
                            إيقاف / تشغيل الكاميرا
                        </button>
                    </div>

                    <div class="relative w-full aspect-square max-h-[380px] rounded-2xl overflow-hidden bg-black flex items-center justify-center">
                        <div id="laser-effect" class="laser-line hidden"></div>
                        <div id="reader" class="w-full h-full"></div>
                    </div>

                    <!-- إدخال التوكن السريع يدوياً -->
                    <form id="token-manual-form" class="mt-4 flex gap-2">
                        <input type="text" id="manual-token-input" placeholder="أو أدخل كود التذكرة يدوياً..." class="flex-1 bg-slate-950 border border-slate-800 text-white px-4 py-2.5 rounded-xl text-xs font-mono tracking-wider focus:outline-none focus:ring-2 focus:ring-[#C5A059]">
                        <button type="submit" class="px-5 py-2.5 bg-[#C5A059] hover:bg-[#b08e4c] text-[#0F2942] font-black text-xs rounded-xl shrink-0 transition">
                            تحقق ودخول
                        </button>
                    </form>
                </div>

                <!-- عمود البحث والتحضير اليدوي السريع -->
                <div class="lg:col-span-5 bg-slate-900 border border-slate-800 p-5 rounded-3xl shadow-xl space-y-4">
                    <div>
                        <h2 class="text-sm font-bold text-white flex items-center gap-2 mb-1">
                            <i class="fa-solid fa-magnifying-glass text-[#C5A059]"></i> التحضير والبحث اليدوي
                        </h2>
                        <p class="text-[11px] text-slate-400">ابحث بالاسم أو البريد أو الجوال للضيوف بدون تذكرة</p>
                    </div>

                    <div class="relative">
                        <input type="text" id="manual-search-box" placeholder="ابحث بالاسم، الإيميل أو الجوال..." class="w-full bg-slate-950 border border-slate-800 text-white pr-10 pl-4 py-3 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-[#C5A059]">
                        <span class="absolute right-3.5 top-3.5 text-slate-500 text-xs">
                            <i class="fa-solid fa-search"></i>
                        </span>
                    </div>

                    <div id="manual-search-results" class="max-h-[320px] overflow-y-auto space-y-2 pr-1">
                        <div class="p-6 text-center text-slate-500 text-xs">
                            ابدأ بكتابة حرفين لإظهار النتائج فوراً.
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var ajaxUrl = '<?php echo esc_url($ajax_url); ?>';
        var scannerNonce = '<?php echo esc_js($scanner_nonce); ?>';
        var correctPin = '<?php echo esc_js($correct_pin); ?>';
        var isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;

        var html5QrCode = null;
        var isCameraScanning = false;
        var feedbackContainer = document.getElementById('scan-feedback-container');
        var laserEffect = document.getElementById('laser-effect');

        // مولد أصوات التنبيه باستخدام Web Audio API
        var audioCtx = null;
        function initAudio() {
            if (!audioCtx) {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
        }

        function playSuccessSound() {
            try {
                initAudio();
                var osc1 = audioCtx.createOscillator();
                var gain1 = audioCtx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(587.33, audioCtx.currentTime);
                osc1.frequency.setValueAtTime(880, audioCtx.currentTime + 0.1);
                gain1.gain.setValueAtTime(0.3, audioCtx.currentTime);
                gain1.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.35);
                osc1.connect(gain1);
                gain1.connect(audioCtx.destination);
                osc1.start();
                osc1.stop(audioCtx.currentTime + 0.35);
            } catch (e) {}
        }

        function playErrorSound() {
            try {
                initAudio();
                var osc = audioCtx.createOscillator();
                var gain = audioCtx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(220, audioCtx.currentTime);
                osc.frequency.setValueAtTime(160, audioCtx.currentTime + 0.15);
                gain.gain.setValueAtTime(0.4, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.4);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.4);
            } catch (e) {}
        }

        // الحماية بـ PIN
        var pinForm = document.getElementById('pin-auth-form');
        var pinInput = document.getElementById('scanner-pin-input');
        var pinLockScreen = document.getElementById('pin-lock-screen');
        var pinErrorMsg = document.getElementById('pin-error-msg');

        if (pinForm) {
            if (sessionStorage.getItem('tarbwy_scanner_auth') === 'granted') {
                if (pinLockScreen) pinLockScreen.classList.add('hidden');
                startScannerCamera();
            }

            pinForm.addEventListener('submit', function(e) {
                e.preventDefault();
                initAudio();
                if (pinInput.value === correctPin) {
                    sessionStorage.setItem('tarbwy_scanner_auth', 'granted');
                    pinLockScreen.classList.add('hidden');
                    startScannerCamera();
                } else {
                    pinErrorMsg.classList.remove('hidden');
                    pinInput.value = '';
                    playErrorSound();
                }
            });
        }

        // العدادات الحية
        window.fetchLiveStats = function() {
            var formData = new FormData();
            formData.append('action', 'tarbwy_get_checkin_stats');
            formData.append('nonce', scannerNonce);

            fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    document.getElementById('stat-expected').innerText = res.data.expected;
                    document.getElementById('stat-checked').innerText = res.data.checked_in;
                    document.getElementById('stat-vip-checked').innerText = res.data.vip_checked;
                    document.getElementById('stat-percentage').innerText = res.data.percentage + '%';
                }
            });
        };

        fetchLiveStats();
        setInterval(fetchLiveStats, 8000);

        function cleanScannedToken(rawString) {
            if (!rawString) return '';
            rawString = rawString.trim();
            if (rawString.indexOf('tarbwy_ticket=') !== -1) {
                var parts = rawString.split('tarbwy_ticket=');
                if (parts[1]) {
                    var tokenPart = parts[1].split('&')[0];
                    return decodeURIComponent(tokenPart);
                }
            }
            return rawString;
        }

        window.processCheckIn = function(tokenOrId, isId) {
            initAudio();
            var formData = new FormData();
            formData.append('action', 'tarbwy_process_checkin');
            formData.append('nonce', scannerNonce);

            if (isId) {
                formData.append('guest_id', tokenOrId);
            } else {
                formData.append('token', cleanScannedToken(tokenOrId));
            }

            fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                renderScanFeedback(res);
                fetchLiveStats();
            })
            .catch(function() {
                playErrorSound();
                feedbackContainer.className = 'p-5 rounded-2xl bg-rose-950/80 border border-rose-500 text-white mb-6';
                feedbackContainer.innerHTML = '<div class="font-bold">❌ خطأ في الاتصال بالخادم.</div>';
                feedbackContainer.classList.remove('hidden');
            });
        };

        function renderScanFeedback(res) {
            feedbackContainer.classList.remove('hidden');

            if (res.success && res.data.code === 'CHECKIN_SUCCESS') {
                playSuccessSound();
                var g = res.data.guest;
                var isVip = (g.guest_type === 'vip');

                feedbackContainer.className = 'p-6 rounded-3xl bg-emerald-950/90 border-2 border-emerald-400 text-white shadow-2xl mb-6';
                feedbackContainer.innerHTML = 
                    '<div class="flex items-center gap-4">' +
                        '<div class="w-16 h-16 rounded-2xl bg-emerald-500 text-[#0F2942] flex items-center justify-center text-3xl font-black shrink-0">' +
                            '<i class="fa-solid fa-circle-check"></i>' +
                        '</div>' +
                        '<div class="flex-1">' +
                            '<div class="flex items-center gap-2 mb-1">' +
                                '<span class="text-xs font-black px-3 py-0.5 rounded-full ' + (isVip ? 'bg-[#C5A059] text-[#0F2942]' : 'bg-emerald-400 text-slate-950') + '">' +
                                    (isVip ? 'VIP كبار الشخصيات' : 'حضور عام معتمد') +
                                '</span>' +
                                '<span class="text-xs text-emerald-300 font-mono">' + res.data.time + '</span>' +
                            '</div>' +
                            '<h3 class="text-2xl font-black text-white">✅ مسموح بالدخول: ' + g.full_name + '</h3>' +
                            '<p class="text-xs text-slate-200 mt-1">' + (g.job_title ? g.job_title + ' - ' : '') + (g.organization || '') + '</p>' +
                            (g.companion_name ? '<div class="mt-2 text-xs font-bold text-[#C5A059]"><i class="fa-solid fa-user-group ml-1"></i> يرافقه: ' + g.companion_name + '</div>' : '') +
                        '</div>' +
                    '</div>';

            } else if (res.data && res.data.code === 'ALREADY_CHECKED_IN') {
                playErrorSound();
                var g = res.data.guest;

                feedbackContainer.className = 'p-6 rounded-3xl bg-amber-950/90 border-2 border-[#C5A059] text-white shadow-2xl mb-6';
                feedbackContainer.innerHTML = 
                    '<div class="flex items-center gap-4">' +
                        '<div class="w-16 h-16 rounded-2xl bg-[#C5A059] text-[#0F2942] flex items-center justify-center text-3xl font-black shrink-0">' +
                            '<i class="fa-solid fa-triangle-exclamation"></i>' +
                        '</div>' +
                        '<div class="flex-1">' +
                            '<span class="text-xs font-black px-3 py-0.5 rounded-full bg-[#C5A059] text-[#0F2942]">تنبيه دخول مكرر</span>' +
                            '<h3 class="text-xl font-black text-amber-200 mt-1">⚠️ تم تسجيل دخول هذا الضيف مسبقاً!</h3>' +
                            '<p class="text-sm font-bold text-white mt-1">الضيف: ' + g.full_name + '</p>' +
                            '<p class="text-xs text-slate-300 mt-1">وقت تسجيل الدخول السابق: <strong class="text-[#C5A059] font-mono" dir="ltr">' + g.checkin_time + '</strong></p>' +
                        '</div>' +
                    '</div>';

            } else {
                playErrorSound();
                feedbackContainer.className = 'p-6 rounded-3xl bg-rose-950/90 border-2 border-rose-500 text-white shadow-2xl mb-6';
                feedbackContainer.innerHTML = 
                    '<div class="flex items-center gap-4">' +
                        '<div class="w-16 h-16 rounded-2xl bg-rose-600 text-white flex items-center justify-center text-3xl font-black shrink-0">' +
                            '<i class="fa-solid fa-ban"></i>' +
                        '</div>' +
                        '<div class="flex-1">' +
                            '<h3 class="text-xl font-black text-white">❌ غير مسموح بالدخول</h3>' +
                            '<p class="text-xs text-rose-200 mt-1">' + (res.data.message || 'التذكرة غير صالحة أو لم يتم اعتماد الحضور.') + '</p>' +
                        '</div>' +
                    '</div>';
            }
        }

        var lastScannedCode = '';
        var scanCooldown = false;

        function startScannerCamera() {
            if (isCameraScanning) return;
            var readerElement = document.getElementById('reader');
            if (!readerElement) return;

            html5QrCode = new Html5Qrcode("reader");
            var config = { fps: 15, qrbox: { width: 250, height: 250 } };

            html5QrCode.start(
                { facingMode: "environment" },
                config,
                function(decodedText) {
                    if (scanCooldown || decodedText === lastScannedCode) return;
                    lastScannedCode = decodedText;
                    scanCooldown = true;
                    processCheckIn(decodedText, false);

                    setTimeout(function() {
                        scanCooldown = false;
                        lastScannedCode = '';
                    }, 3000);
                },
                function(err) {}
            )
            .then(function() {
                isCameraScanning = true;
                if (laserEffect) laserEffect.classList.remove('hidden');
            })
            .catch(function(err) {
                console.log('Camera error: ', err);
            });
        }

        if (isAdmin) {
            startScannerCamera();
        }

        document.getElementById('toggle-camera-btn').addEventListener('click', function() {
            if (isCameraScanning && html5QrCode) {
                html5QrCode.stop().then(function() {
                    isCameraScanning = false;
                    if (laserEffect) laserEffect.classList.add('hidden');
                });
            } else {
                startScannerCamera();
            }
        });

        document.getElementById('token-manual-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var input = document.getElementById('manual-token-input');
            if (input.value.trim()) {
                processCheckIn(input.value.trim(), false);
                input.value = '';
            }
        });

        var searchBox = document.getElementById('manual-search-box');
        var searchResults = document.getElementById('manual-search-results');
        var searchTimer = null;

        searchBox.addEventListener('input', function() {
            clearTimeout(searchTimer);
            var query = this.value.trim();
            if (query.length < 2) {
                searchResults.innerHTML = '<div class="p-6 text-center text-slate-500 text-xs">ابدأ بكتابة حرفين لإظهار النتائج فوراً.</div>';
                return;
            }

            searchTimer = setTimeout(function() {
                var formData = new FormData();
                formData.append('action', 'tarbwy_search_guests_checkin');
                formData.append('nonce', scannerNonce);
                formData.append('query', query);

                fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success && res.data.guests.length > 0) {
                        var html = '';
                        res.data.guests.forEach(function(g) {
                            var isChecked = (g.status === 'checked_in');
                            var isVip = (g.guest_type === 'vip');

                            html += 
                            '<div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between gap-3">' +
                                '<div>' +
                                    '<div class="flex items-center gap-1.5 mb-1">' +
                                        '<span class="font-black text-sm text-white">' + g.full_name + '</span>' +
                                        '<span class="text-[10px] font-bold px-2 py-0.2 rounded-full ' + (isVip ? 'bg-[#C5A059] text-[#0F2942]' : 'bg-slate-700 text-slate-300') + '">' +
                                            (isVip ? 'VIP' : 'عام') +
                                        '</span>' +
                                    '</div>' +
                                    '<div class="text-[11px] text-slate-400">' + (g.organization || g.email) + '</div>' +
                                '</div>' +
                                '<div>' +
                                    (isChecked 
                                        ? '<span class="px-3 py-1.5 rounded-xl bg-teal-500/20 text-teal-300 border border-teal-500/30 text-[11px] font-bold block text-center">تم الحضور مسبقاً</span>' 
                                        : '<button type="button" onclick="processCheckIn(' + g.id + ', true);" class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-[#0F2942] font-black text-xs transition flex items-center gap-1.5 shadow">' +
                                            '<i class="fa-solid fa-check"></i> <span>تسجيل دخول</span>' +
                                          '</button>'
                                    ) +
                                '</div>' +
                            '</div>';
                        });
                        searchResults.innerHTML = html;
                    } else {
                        searchResults.innerHTML = '<div class="p-6 text-center text-slate-500 text-xs">لا يوجد أي ضيف يطابق هذا البحث.</div>';
                    }
                });
            }, 300);
        });

    });
    </script>
    <?php
    return ob_get_clean();
}

/**
 * معالجات AJAX الخلفية لنظام الاستقبال
 */
add_action('wp_ajax_tarbwy_process_checkin', 'tarbwy_ajax_process_checkin_handler');
add_action('wp_ajax_nopriv_tarbwy_process_checkin', 'tarbwy_ajax_process_checkin_handler');

function tarbwy_ajax_process_checkin_handler() {
    check_ajax_referer('tarbwy_scanner_action_nonce', 'nonce');

    global $wpdb;
    $table = $wpdb->prefix . 'tarbwy_guests';

    $token    = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
    $guest_id = isset($_POST['guest_id']) ? intval($_POST['guest_id']) : 0;

    $guest = null;
    if ($guest_id > 0) {
        $guest = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $guest_id));
    } elseif (!empty($token)) {
        $guest = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE unique_token = %s", $token));
    }

    if (!$guest) {
        wp_send_json_error([
            'code'    => 'INVALID_TICKET',
            'message' => 'رمز التذكرة غير موجود في قاعدة البيانات.'
        ]);
    }

    if ($guest->status === 'checked_in') {
        wp_send_json_success([
            'code'  => 'ALREADY_CHECKED_IN',
            'guest' => [
                'full_name'    => $guest->full_name,
                'checkin_time' => $guest->checkin_time ? date_i18n('h:i:s A', strtotime($guest->checkin_time)) : 'وقت سابق'
            ]
        ]);
    }

    if (in_array($guest->status, ['approved', 'confirmed_vip'], true)) {
        $now = current_time('mysql');
        $wpdb->update(
            $table,
            [
                'status'       => 'checked_in',
                'checkin_time' => $now
            ],
            ['id' => $guest->id],
            ['%s', '%s'],
            ['%d']
        );

        wp_send_json_success([
            'code'  => 'CHECKIN_SUCCESS',
            'time'  => date_i18n('h:i A', strtotime($now)),
            'guest' => [
                'full_name'      => $guest->full_name,
                'guest_type'     => $guest->guest_type,
                'organization'   => $guest->organization,
                'job_title'      => $guest->job_title,
                'companion_name' => $guest->companion_name
            ]
        ]);
    }

    $status_labels = [
        'pending'  => 'قيد المراجعة',
        'waitlist' => 'قائمة الانتظار',
        'declined' => 'معتذر عن الحضور'
    ];
    $status_text = isset($status_labels[$guest->status]) ? $status_labels[$guest->status] : $guest->status;

    wp_send_json_error([
        'code'    => 'NOT_APPROVED',
        'message' => 'هذا الطلب غير معتمد حالياً. الحالة: ' . $status_text
    ]);
}

add_action('wp_ajax_tarbwy_get_checkin_stats', 'tarbwy_ajax_get_checkin_stats_handler');
add_action('wp_ajax_nopriv_tarbwy_get_checkin_stats', 'tarbwy_ajax_get_checkin_stats_handler');

function tarbwy_ajax_get_checkin_stats_handler() {
    check_ajax_referer('tarbwy_scanner_action_nonce', 'nonce');

    global $wpdb;
    $table = $wpdb->prefix . 'tarbwy_guests';

    $expected = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('approved', 'confirmed_vip', 'checked_in')");
    $checked_in = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'checked_in'");
    $vip_checked = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'checked_in' AND guest_type = 'vip'");
    $percentage = ($expected > 0) ? round(($checked_in / $expected) * 100) : 0;

    wp_send_json_success([
        'expected'    => $expected,
        'checked_in'  => $checked_in,
        'vip_checked' => $vip_checked,
        'percentage'  => $percentage
    ]);
}

add_action('wp_ajax_tarbwy_search_guests_checkin', 'tarbwy_ajax_search_guests_checkin_handler');
add_action('wp_ajax_nopriv_tarbwy_search_guests_checkin', 'tarbwy_ajax_search_guests_checkin_handler');

function tarbwy_ajax_search_guests_checkin_handler() {
    check_ajax_referer('tarbwy_scanner_action_nonce', 'nonce');

    global $wpdb;
    $table = $wpdb->prefix . 'tarbwy_guests';

    $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
    if (mb_strlen($query) < 2) {
        wp_send_json_success(['guests' => []]);
    }

    $like = '%' . $wpdb->esc_like($query) . '%';
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT id, full_name, email, phone, organization, job_title, guest_type, status 
         FROM $table 
         WHERE (full_name LIKE %s OR email LIKE %s OR phone LIKE %s OR unique_token LIKE %s)
         ORDER BY id DESC LIMIT 15",
        $like, $like, $like, $like
    ));

    wp_send_json_success(['guests' => $results]);
}