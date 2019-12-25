<?php

namespace App\Http;

use App\Contracts\SiteDetector;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CheckForMaintenanceMode;
use App\Http\Middleware\ViewData;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\VerifyCsrfToken;
use Closure;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class Kernel extends HttpKernel
{

    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        TrustProxies::class,
        CheckForMaintenanceMode::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            ViewData::class,
        ],

        'api' => [
            'throttle:60,1',
            'bindings',
        ],
    ];

    // ...

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'bindings' => SubstituteBindings::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'password.confirm' => RequirePassword::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
    ];

    /**
     * The priority-sorted list of middleware.
     *
     * This forces non-global middleware to always be in the given order.
     *
     * @var array
     */
    protected $middlewarePriority = [
        StartSession::class,
        ShareErrorsFromSession::class,
        Authenticate::class,
        ThrottleRequests::class,
        AuthenticateSession::class,
        SubstituteBindings::class,
        Authorize::class,
    ];

    /**
     * Get the route dispatcher callback.
     *
     * @return Closure
     */
    protected function dispatchToRouter()
    {
        return function (Request $request) {
            /*
             * Определяем сайт
             */
            /** @var SiteDetector $siteDetector */
            $siteDetector = $this->app->get(SiteDetector::class);
            $site = $siteDetector->detect($request->getHost());

            /*
             * Определяем первый сегмент УРЛ
             */
            $segment = (string)$request->segment(1);

            /*
             * Если первый сегмент УРЛ совпадает с одним из поддерживаемых сайтом языков, значит это язык
             */
            if ($segment && $site->isLanguageSupported($segment)) {
                $language = $segment;
            } else {
                $language = $site->getDefaultLanguage();
            }

            /*
             * Задаём приложению список поддерживаемых локалей
             */
            $this->app->get('config')->set('app.supported_locales', $site->getSupportedLanguages());

            /*
             * Задаём приложению локаль по умолчанию
             */
            $this->app->get('config')->set('app.fallback_locale', $site->getDefaultLanguage());

            /*
             * Задаём приложению локаль
             */
            $this->app->setLocale($language);

            /*
             * Если текущий язык не совпадает с языком сайта по умолчанию
             */
            if (!$site->isLanguageDefault($language)) {
                /*
                 * Вырезаем первый сегмент из УРЛ запроса.
                 */
                $server = $request->server();
                $server['REQUEST_URI'] = mb_substr($server['REQUEST_URI'], mb_strlen($language) + 1);
                $request = $request->duplicate(
                    $request->query->all(),
                    $request->all(),
                    $request->attributes->all(),
                    $request->cookies->all(),
                    $request->files->all(),
                    $server
                );
            }

            /*
             * Запускаем пайплайн
             */
            $this->app->instance('request', $request);
            return $this->router->dispatch($request);
        };
    }
}
