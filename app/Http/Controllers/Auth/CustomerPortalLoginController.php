<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerPortalLoginController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isCustomerAccount()) {
            return redirect()->route('portal.home');
        }

        return view('auth.portal-login', ['staffSession' => Auth::check()]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route($request->user()->isCustomerAccount() ? 'portal.home' : 'portal.login');
        }
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ], [
            'username.required' => 'يرجى إدخال اسم المستخدم الخاص بالمعرض.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
        ]);
        $throttleKey = Str::transliterate(Str::lower($validated['username']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return redirect()->route('portal.login')->withErrors(['username' => 'تم تجاوز عدد محاولات الدخول. حاول مجددًا بعد '.RateLimiter::availableIn($throttleKey).' ثانية.'])->withInput($request->only('username', 'remember'));
        }

        if (! Auth::attemptWhen(
            ['username' => $validated['username'], 'password' => $validated['password']],
            fn (User $user): bool => $user->is_active && $user->hasRole('customer_portal') && $user->customer?->is_active === true,
            $request->boolean('remember'),
        )) {
            RateLimiter::hit($throttleKey, 60);

            return redirect()->route('portal.login')->withErrors(['username' => 'تعذر تسجيل الدخول. تحقق من بيانات حساب العميل، أو تواصل مع خدمة العملاء.'])->withInput($request->only('username', 'remember'));
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');
        $request->user()->update(['last_login_at' => now()]);

        return redirect()->route('portal.home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
