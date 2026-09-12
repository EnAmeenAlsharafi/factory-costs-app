<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>تسجيل الدخول - مصنع مفروشات سدير</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --font-cairo: 'Cairo', 'Tajawal', 'Segoe UI', Tahoma, -apple-system, sans-serif;
            --color-primary: #0f172a;
            --color-accent: #eab308;
            --color-accent-hover: #ca8a04;
        }

        body {
            font-family: var(--font-cairo);
            background: linear-gradient(135deg, #090d16 0%, #0f172a 50%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            color: #1e293b;
        }

        .login-card {
            background: #ffffff;
            border-radius: 1.5rem;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.1);
            width: 100%;
            max-width: 460px;
            overflow: hidden;
            animation: cardAppear 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes cardAppear {
            from { opacity: 0; transform: translateY(20px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-header {
            background: linear-gradient(180deg, #0b1120 0%, #0f172a 100%);
            color: #ffffff;
            padding: 2.75rem 2rem 2.25rem;
            text-align: center;
            position: relative;
            border-bottom: 3px solid #eab308;
        }

        .brand-badge {
            width: 64px;
            height: 64px;
            background: #eab308;
            color: #0f172a;
            border-radius: 1.25rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.9rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 8px 25px rgba(234, 179, 8, 0.4);
            transform: rotate(-3deg);
            transition: transform 0.3s ease;
        }

        .login-card:hover .brand-badge {
            transform: rotate(0deg) scale(1.05);
        }

        .form-control {
            border-radius: 0.65rem;
            padding: 0.75rem 1rem;
            font-size: 0.92rem;
            border: 1px solid #cbd5e1;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: #eab308;
            box-shadow: 0 0 0 0.25rem rgba(234, 179, 8, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, #eab308 0%, #ca8a04 100%);
            color: #0f172a;
            font-weight: 700;
            padding: 0.85rem;
            border-radius: 0.65rem;
            border: none;
            width: 100%;
            font-size: 1rem;
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(234, 179, 8, 0.3);
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #ca8a04 0%, #a16207 100%);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(234, 179, 8, 0.4);
        }

        .demo-pill {
            cursor: pointer;
            border-radius: 2rem;
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
            transition: all 0.15s ease;
            font-weight: 600;
        }

        .demo-pill:hover {
            background: #eab308;
            color: #0f172a;
            border-color: #eab308;
            transform: translateY(-1px);
        }
    </style>
</head>
<body x-data="{ 
    username: '{{ old('username', '') }}', 
    password: '', 
    showPassword: false,
    fillAccount(u, p) {
        this.username = u;
        this.password = p;
    }
}">

    <div class="login-card">
        
        <!-- Header -->
        <div class="login-header">
            <div class="brand-badge">
                <i class="fas fa-bed"></i>
            </div>
            <h4 class="fw-bold mb-1" style="letter-spacing: -0.5px;">مصنع مفروشات سدير</h4>
            <p class="text-white-50 mb-0 fs-7">نظام إدارة الإنتاج والتكاليف الداخلي</p>
        </div>

        <!-- Form Body -->
        <div class="p-4 p-md-5">
            
            <div class="text-center mb-4">
                <h5 class="fw-bold text-dark mb-1">تسجيل الدخول للنظام</h5>
                <small class="text-muted">أدخل بيانات الدخول للوصول إلى لوحة التشغيل</small>
            </div>

            <!-- Error Alerts -->
            @if ($errors->any())
                <div class="alert alert-danger py-2.5 px-3 rounded-3 fs-7 mb-3 d-flex align-items-center gap-2">
                    <i class="fas fa-exclamation-circle text-danger fs-6 flex-shrink-0"></i>
                    <div>
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger py-2.5 px-3 rounded-3 fs-7 mb-3 d-flex align-items-center gap-2">
                    <i class="fas fa-exclamation-circle text-danger fs-6 flex-shrink-0"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            <form action="{{ route('login') }}" method="POST">
                @csrf

                <!-- Username -->
                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold text-dark fs-7">اسم المستخدم للدخول</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-user"></i></span>
                        <input type="text" 
                               name="username" 
                               id="username" 
                               x-model="username"
                               class="form-control border-start-0 ps-2 @error('username') is-invalid @enderror" 
                               placeholder="مثال: admin" 
                               required 
                               autofocus>
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold text-dark fs-7">كلمة المرور</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-lock"></i></span>
                        <input :type="showPassword ? 'text' : 'password'" 
                               name="password" 
                               id="password" 
                               x-model="password"
                               class="form-control border-start-0 border-end-0 px-2 @error('password') is-invalid @enderror" 
                               placeholder="أدخل كلمة المرور" 
                               required>
                        <button type="button" 
                                @click="showPassword = !showPassword" 
                                class="input-group-text bg-light border-start-0 text-muted" 
                                :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'عرض كلمة المرور'"
                                :aria-pressed="showPassword.toString()"
                                title="عرض / إخفاء كلمة المرور">
                            <i class="fas" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="d-flex justify-content-between align-items-center mb-4 fs-7">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                        <label class="form-check-label text-secondary" for="remember">
                            تذكرني على هذا الجهاز
                        </label>
                    </div>
                    <span class="text-muted fs-8"><i class="fas fa-shield-alt text-success me-1"></i>نظام داخلي محمي</span>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-login mb-4">
                    <i class="fas fa-sign-in-alt me-1"></i> تسجيل الدخول
                </button>
            </form>

            <!-- Quick Demo Accounts (Development & Testing Helper) -->
            @env(['local', 'testing'])
            <div class="bg-light p-3 rounded-3 border mb-3" aria-label="حسابات تجريبية للبيئة المحلية فقط">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <small class="fw-bold text-dark fs-8"><i class="fas fa-bolt text-warning me-1"></i> حسابات تجريبية سريعة (انقر للتعبئة):</small>
                </div>
                <div class="d-flex flex-wrap gap-1.5">
                    <button type="button" @click="fillAccount('admin', 'admin123')" class="demo-pill btn p-0 border">
                        👑 مدير النظام
                    </button>
                    <button type="button" @click="fillAccount('prod.manager', 'password')" class="demo-pill btn p-0 border">
                        🏭 مدير الإنتاج
                    </button>
                    <button type="button" @click="fillAccount('cs.agent', 'password')" class="demo-pill btn p-0 border">
                        📞 خدمة العملاء
                    </button>
                    <button type="button" @click="fillAccount('warehouse.keeper', 'password')" class="demo-pill btn p-0 border">
                        📦 أمين المستودع
                    </button>
                    <button type="button" @click="fillAccount('worker.carpenter', 'password')" class="demo-pill btn p-0 border">
                        🔨 نجار الإنتاج
                    </button>
                </div>
            </div>
            @endenv

            <div class="text-center text-muted fs-8 pt-2">
                &copy; {{ date('Y') }} مصنع مفروشات سدير &bull; جميع الحقوق محفوظة
            </div>
        </div>

    </div>

</body>
</html>
