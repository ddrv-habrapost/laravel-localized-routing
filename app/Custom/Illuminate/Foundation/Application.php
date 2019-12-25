<?php

namespace App\Custom\Illuminate\Foundation;

use App\Custom\Illuminate\Routing\RouteCollection;
use App\Exceptions\UnsupportedLocaleException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application as BaseApplication;
use Illuminate\Routing\UrlGenerator;

class Application extends BaseApplication
{

    private $isLocaleEstablished = false;

    private $cachedRoutes = [];

    public function __construct($basePath = null)
    {
        parent::__construct($basePath);
    }

    public function setLocale($locale)
    {

        /*
         * При попытке сменить локаль на уже установленную, не шевелимся.
         */
        if ($this->getLocale() === $locale && $this->isLocaleEstablished) {
            return;
        }

        /** @var Repository $config */
        $config = $this->get('config');
        $urlGenerator = $this->get('url');

        $defaultLocale = $config->get('app.fallback_locale');
        $supportedLocales = $config->get('app.supported_locales');

        /*
         * Проверяем поддержку выбранной локали
         */
        if (!in_array($locale, $supportedLocales)) {
            throw new UnsupportedLocaleException();
        }

        /*
         * Для дополнительных языков добавляем префикс в генераторе УРЛ
         */
        if ($defaultLocale !== $locale && $urlGenerator instanceof UrlGenerator) {
            $request = $urlGenerator->getRequest();
            $rootUrl = $request->getSchemeAndHttpHost() . '/' . $locale;
            $urlGenerator->forceRootUrl($rootUrl);
        }

        /*
         * Проводим обычную процедуру смены локали
         */
        parent::setLocale($locale);

        if (array_key_exists($locale, $this->cachedRoutes)) {
            $fn = $this->cachedRoutes[$locale];
            $this->get('router')->setRoutes($fn());
        } else {
            $this->get('router')->getRoutes()->localize($locale);
        }
        $this->isLocaleEstablished = true;

    }

    public function bootstrapWith(array $bootstrappers)
    {
        parent::bootstrapWith($bootstrappers);

        /**
         * После бутстрапа роутеру нужно задать конфигурацию локализуемых роутов
         * и задать приложению локаль по умолчанию
         *
         * @var RouteCollection $routes
         */
        $routes = $this->get('router')->getRoutes();
        $routes->setConfig($this->get('config')->get('routes'));
        if ($this->routesAreCached()) {
            /** @noinspection PhpIncludeInspection */
            $this->cachedRoutes = require $this->getCachedRoutesPath();
        }
        $this->setLocale($this->getLocale());
    }
}
