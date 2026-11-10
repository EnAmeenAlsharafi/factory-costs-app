<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictCustomerPortalAccess
{
    /**
     * Public routes the portal domain still serves: what makes the portal installable as an app
     * (its manifest, icons, service worker and offline page) and the company logo.
     */
    public const PORTAL_DOMAIN_SHARED_ROUTES = [
        'webapp.portal-manifest',
        'webapp.icon',
        'webapp.service-worker',
        'webapp.offline',
        'company.logo',
        'public-file',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    /**
     * Portal pages opened on the staff system's domain (e.g. sadirfactory.com/portal) move to the
     * portal's own subdomain. Sharing the staff app's origin puts the portal inside that installed
     * app's scope, so phones open portal links in the staff app and never offer to install the portal.
     */
    private function portalBelongsOnItsOwnDomain(Request $request, ?string $portalDomain, bool $onPortalDomain): bool
    {
        if ($onPortalDomain || blank($portalDomain) || ! $request->isMethod('GET') || ! $request->routeIs('portal.*', 'webapp.portal-manifest')) {
            return false;
        }
        $staffDomain = preg_replace('/^www\./', '', mb_strtolower($request->getHost()));

        return str_ends_with(mb_strtolower($portalDomain), '.'.$staffDomain);
    }

    /**
     * The portal page's address on the portal's own domain when the staff site links to it, so the
     * link leaves the installed staff app instead of opening the portal inside it.
     */
    public static function portalUrl(Request $request, string $routeName): string
    {
        $portalDomain = config('app.customer_portal_domain');
        $path = route($routeName, [], false);
        $staffDomain = preg_replace('/^www\./', '', mb_strtolower($request->getHost()));

        return filled($portalDomain) && str_ends_with(mb_strtolower($portalDomain), '.'.$staffDomain)
            ? $request->getScheme().'://'.$portalDomain.$path
            : route($routeName);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $portalDomain = config('app.customer_portal_domain');
        $onPortalDomain = filled($portalDomain) && mb_strtolower($request->getHost()) === mb_strtolower($portalDomain);
        if ($this->portalBelongsOnItsOwnDomain($request, $portalDomain, $onPortalDomain)) {
            return redirect()->away($request->getScheme().'://'.$portalDomain.$request->getRequestUri());
        }
        if ($onPortalDomain) {
            if ($request->isMethod('GET') && $request->routeIs('home', 'dashboard', 'login')) {
                return redirect()->route($user?->isCustomerAccount() ? 'portal.home' : 'portal.login');
            }
            abort_unless($request->routeIs('portal.*', 'logout', ...self::PORTAL_DOMAIN_SHARED_ROUTES), 404);
        }
        $portalAuthentication = $request->routeIs('portal.login', 'portal.login.store', 'portal.logout');
        if (! $portalAuthentication && $user?->isCustomerAccount()) {
            if ($request->routeIs('logout')) {
                return $next($request);
            }
            abort_unless($user->hasRole('customer_portal') && $user->customer?->is_active, 403, 'حساب العميل غير مرتبط بعميل نشط.');
            if ($request->routeIs('home', 'dashboard', 'login')) {
                return redirect()->route('portal.home');
            }
            abort_unless($request->routeIs('portal.*', ...self::PORTAL_DOMAIN_SHARED_ROUTES), 403, 'هذا الحساب مخصص لبوابة العملاء.');
        } elseif (! $portalAuthentication && $user && $request->routeIs('portal.*')) {
            abort_unless($request->isMethod('GET'), 403, 'بوابة العملاء مخصصة لحسابات العملاء المرتبطة.');

            return redirect()->route('portal.login');
        }

        $response = $next($request);
        if ($request->routeIs('portal.*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
