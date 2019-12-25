Привет, Хабр!

Хочу рассказать вам о том, как в одном проекте возникла проблема с роутингом и как мы её решали.

Сначала наш проект был самым обычным сайтом. Сайт развивался, аудитория расширялась и возникла необходимость поддержки мультиязычности. Проект был на базе фреймворка Laravel и проблем с мультиязычностью не возникло (нужный язык подтягивался из сессии, либо брался дефолтный). Мы написали переводы, прописали ключи переводов вместо захардкоженных фраз и взяли в работу следующие фичи.

<cut />

## Проблема

В какой-то момент команда SEO поняла, что такой подход мешает ранжированию сайта. Тогда команде разработки поступила команда добавить языковые подпапки в УРЛ, кроме языка по умолчанию. Наши роуты приняли примерно такой вид: 

|Страница|Роут ru (язык по умолчанию)|Роут en|Роут fr|
|---|---|---|---|
|О нас|`/o-nas`|`/en/about-us`|`/fr/a-propos-de-nous`|
|Контакты|`/kontakty`|`/en/contacts`|`/fr/coordonnees`|
|Новости|`/novosti`|`/en/news`|`/fr/les-nouvelles`|

Всё встало на свои места и мы снова принялись за новые фичи.
Чуть позже возникла необходимость развернуть приложение на нескольких доменах. В целом эти сайты имеют одну БД, но в зависимости от домена могут меняться некоторые настройки.
Некоторые сайты могут быть мультиязычные (причем с ограниченным набором языком, а не со всеми поддерживаемыми), некоторые - только один язык.

Было принято решение обрабатывать все домены одним приложением (nginx проксирует все домены на один апстрим).

Набор поддерживаемых конкретным сайтом языков и язык по умолчанию должен настраиваться в админке, что на корню зарубило вариант конфига/env-переменных. Стало ясно, что текущее решение не удовлетворяет наши хотелки.

## Решение

> Для упрощения картины и демонстрации решения я развернул новый проект на laravel версии 6.2 и отказался от использования БД. В версиях 5.x отличия незначительные (но расписывать их я, конечно же, не буду).
>
> Код проекта доступен на [GitHub](https://github.com/ddrv/habrapost481726-laravel-localized-routing)

Для начала нам нужно указать в конфигурации приложения все поддерживаемые языки.

<spoiler title="config/app.php">

```php
<?php

return [
// ... 

    'locale' => 'en',
    'fallback_locale' => 'en',
    'supported_locales' => [
        'en',
        'ru',
        'de',
        'fr',
    ],
// ...
];
```
</spoiler>

Нам понадобится сущность сайта `Site` и сервис определения настроек сайта.

<spoiler title="app/Entities/Site.php">

```php
<?php

declare(strict_types=1);

namespace App\Entities;

class Site
{

    /**
     * @var string Домен сайта
     */
    private $domain;

    /**
     * @var string Язык по умолчанию
     */
    private $defaultLanguage;

    /**
     * @var string[] Список поддержиаемых языков
     */
    private $supportedLanguages = [];

    /**
     * @param string   $domain             Домен
     * @param string   $defaultLanguage    Язык по умолчанию
     * @param string[] $supportedLanguages Список поддерживаемых языков
     */
    public function __construct(string $domain, string $defaultLanguage, array $supportedLanguages)
    {
        $this->domain = $domain;
        $this->defaultLanguage = $defaultLanguage;
        if (!in_array($defaultLanguage, $supportedLanguages)) {
            $supportedLanguages[] = $defaultLanguage;
        }
        $this->supportedLanguages = $supportedLanguages;
    }

    /**
     * Возвращает домен сайта
     *
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Возвращает язык по умолчанию для сайта
     *
     * @return string
     */
    public function getDefaultLanguage(): string
    {
        return $this->defaultLanguage;
    }

    /**
     * Возвращает список поддерживаемых сайтом языков
     *
     * @return string[]
     */
    public function getSupportedLanguages(): array
    {
        return $this->supportedLanguages;
    }

    /**
     * Проверяет поддержку сайтом языка
     *
     * @param string $language
     * @return bool
     */
    public function isLanguageSupported(string $language): bool
    {
        return in_array($language, $this->supportedLanguages);
    }

    /**
     * Проверяет, является ли передаваемый язык основным
     *
     * @param string $language
     * @return bool
     */
    public function isLanguageDefault(string $language): bool
    {
        return $language === $this->defaultLanguage;
    }
}

```
</spoiler>

<spoiler title="app/Contracts/SiteDetector.php">

```php
<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Entities\Site;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

interface SiteDetector
{

    /**
     * Определяет сайт по хосту
     *
     * @param string $host Хост
     *
     * @return Site Сущность сайта
     *
     * @throws NotFoundHttpException Если сайт не известен
     */
    public function detect(string $host): Site;
}

```
</spoiler>

<spoiler title="app/Services/SiteDetector/FakeSiteDetector.php">

```php
<?php

declare(strict_types=1);

namespace App\Services\SiteDetector;

use App\Contracts\SiteDetector;
use App\Entities\Site;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Для демонстрации сайты находятся в памяти.
 * В реальном проекте всё хранится в БД, что позволяет изменять настройки через админку.
 */
class FakeSiteDetector implements SiteDetector
{

    /**
     * @var Site[] Хранилище
     */
    private $sites;

    public function __construct()
    {
        $sites = [
            'localhost' => [ // Все языки
                'default' => 'en',
                'support' => ['ru', 'de', 'fr'],
            ],
            'site-all.local' => [ // Все языки
                'default' => 'en',
                'support' => ['ru', 'de', 'fr'],
            ],
            'site-ru.local' => [ // Только русский
                'default' => 'ru',
                'support' => [],
            ],
            'site-en.local' => [
                'default' => 'en', // Только английский
                'support' => [],
            ],
            'site-de.local' => [
                'default' => 'de', // Только немецкий
                'support' => [],
            ],
            'site-fr.local' => [
                'default' => 'fr', // Только французский
                'support' => [],
            ],
            'site-eur.local' => [ // Немецкий и французский
                'default' => 'de',
                'support' => ['fr'],
            ],
        ];
        foreach ($sites as $domain => $site) {
            $default = $site['default'];
            $support = array_merge([$default], $site['support']);
            $this->sites[$domain] = new Site($domain, $default, $support);
        }
    }

    public function detect(string $host): Site
    {
        $host = trim(mb_strtolower($host));
        if (!array_key_exists($host, $this->sites)) {
            throw new NotFoundHttpException();
        }
        return $this->sites[$host];
    }
}
```
</spoiler>

Добавим наш сервис в контейнер

<spoiler title="app/Providers/AppServiceProvider.php">

```php
<?php

namespace App\Providers;

use App\Contracts\SiteDetector;
use App\Services\SiteDetector\FakeSiteDetector;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    // ...

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // ...

        /*
         * Строим сервис.
         */
        $this->app->singleton(FakeSiteDetector::class, function () {
            return new FakeSiteDetector();
        });

        /*
         * Биндим контракт
         */
        $this->app->bind(SiteDetector::class, FakeSiteDetector::class);
        // ...
    }

    // ...
}

```
</spoiler>


Теперь определим роуты.

<spoiler title="routes/web.php">

```php
<?php

// ...

Route::get('/', 'DemoController@home')->name('web.home');
Route::get('/--about--', 'DemoController@about')->name('web.about');
Route::get('/--contacts--', 'DemoController@contacts')->name('web.contacts');
Route::get('/--news--', 'DemoController@news')->name('web.news');

// ...

```
</spoiler>

Части роутов, подлежащие локализации, обрамлены двойными минусами (`--`). Это маски для замены. Теперь законфигурируем эти маски.

<spoiler title="config/routes.php">

```php
<?php

return [
    'web.about' => [ // Имя роута
        'about' => [ // Маска без обрамляющих символов
            'de' => 'uber-uns', // язык => слаг
            'en' => 'about-us',
            'fr' => 'a-propos-de-nous',
            'ru' => 'o-nas',
        ],
    ],
    'web.news' => [
        'news' => [
            'de' => 'nachrichten',
            'en' => 'news',
            'fr' => 'nouvelles',
            'ru' => 'novosti',
        ],
    ],
    'web.contacts' => [
        'contacts' => [
            'de' => 'kontakte',
            'en' => 'contacts',
            'fr' => 'contacts',
            'ru' => 'kontakty',
        ],
    ],
];

```
</spoiler>

Для отображения компонента выбора языка нам нужно передать в шаблон только те языки, которые поддерживаются сайтом. Напишем для этого мидлварь...

<spoiler title="Http/Middleware/ViewData.php">

```php
<?php

namespace App\Http\Middleware;

use App\Contracts\SiteDetector;
use Closure;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ViewData
{

    /**
     * @var ViewFactory
     */
    private $view;

    /**
     * @var SiteDetector
     */
    private $detector;

    public function __construct(ViewFactory $view, SiteDetector $detector)
    {
        $this->view = $view;
        $this->detector = $detector;
    }

    public function handle(Request $request, Closure $next)
    {
        /*
         * Определяем сайт
         */
        $site = $this->detector->detect($request->getHost());

        /*
         * Передаём в шаблон панели выбора языка ссылки
         */
        $languages = [];
        foreach ($site->getSupportedLanguages() as $language) {
            $url = '/';
            if (!$site->isLanguageDefault($language)) {
                $url .= $language;
            }
            $languages[$language] = $url;
        }

        $this->view->composer(['components/languages'], function(View $view) use ($languages) {
            $view->with('languages', $languages);
        });

        return $next($request);
    }
}

```
</spoiler>

Теперь нужно кастомизировать роутер. Вернее не сам роутер, а коллекцию роутов...

<spoiler title="app/Custom/Illuminate/Routing/RouteCollection.php">

```php
<?php

namespace App\Custom\Illuminate\Routing;

use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection as BaseRouteCollection;
use Serializable;

class RouteCollection extends BaseRouteCollection implements Serializable
{

    /**
     * @var array Конфигурация локализации роутов.
     */
    private $config;

    private $localized = [];

    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * Заменяет маски локлизуемых роутов.
     *
     * @param string $language Язык
     */
    public function localize(string $language)
    {
        $this->flushLocalizedRoutes();
        foreach ($this->config as $name => $placeholders) {
            if (!$this->hasNamedRoute($name) || empty($placeholders)) {
                continue;
            }

            /*
             * Получаем именованный роут
             */
            $route = $this->getByName($name);

            /*
             * Запоминаем
             */
            $this->localized[$name] = $route;

            /*
             * Удаляем его из коллекции
             */
            $this->removeRoute($route);

            /*
             * Меняем шаблон
             */
            $new = clone $route;
            $uri = $new->uri();
            foreach ($placeholders as $placeholder => $paths) {
                if (!array_key_exists($language, $paths)) {
                    continue;
                }
                $value = $paths[$language];
                $uri = str_replace('--' . $placeholder . '--', $value, $uri);
            }
            $new->setUri($uri);
            $this->add($new);
        }

        /*
         * Обновляем индексы
         */
        $this->refreshNameLookups();
        $this->refreshActionLookups();
    }

    private function removeRoute(Route $route)
    {
        $uri = $route->uri();
        $domainAndUri = $route->getDomain().$uri;
        foreach ($route->methods() as $method) {
            $key = $method.$domainAndUri;
            if (array_key_exists($key, $this->allRoutes)) {
                unset($this->allRoutes[$key]);
            }
            if (array_key_exists($uri, $this->routes[$method])) {
                unset($this->routes[$method][$uri]);
            }
        }
    }

    private function flushLocalizedRoutes()
    {
        foreach ($this->localized as $name => $route) {
            /*
             * Получаем именованный роут
             */
            $old = $this->getByName($name);

            /*
             * Удаляем его из коллекции
             */
            $this->removeRoute($old);

            /*
             * Добавляем исходный
             */
            $this->add($route);
        }
    }

    /**
     * @inheritDoc
     */
    public function serialize()
    {
        return serialize([
            'routes' => $this->routes,
            'allRoutes' => $this->allRoutes,
            'nameList' => $this->nameList,
            'actionList' => $this->actionList,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->routes = $data['routes'];
        $this->allRoutes = $data['allRoutes'];
        $this->nameList = $data['nameList'];
        $this->actionList = $data['actionList'];
    }
}

```
</spoiler>

... , основной класс приложения ...

<spoiler title="app/Custom/Illuminate/Foundation/Application.php">

```php
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

        /*
         * Применяем локализацию к роутам
         */
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

```
</spoiler>

... и подменить наши кастомные классы.

<spoiler title="bootstrap/app.php">

```php
<?php

// $app = new Illuminate\Foundation\Application($_ENV['APP_BASE_PATH'] ?? dirname(__DIR__));
$app = new App\Custom\Illuminate\Foundation\Application($_ENV['APP_BASE_PATH'] ?? dirname(__DIR__));
$app->get('router')->setRoutes(new App\Custom\Illuminate\Routing\RouteCollection());

// ...
```
</spoiler>

Следующий шаг - определение языка по первой части УРЛ запроса. Для этого перед диспатчингом мы получим первый его сегмент, проверим поддержку такого языка сайтом, и запустим диспатчинг с новым запросом уже без этого сегмента. Немного поправим класс `App\Http\Kernel`, а заодно добавим наш миддлварь `App\Http\Middleware\ViewData` в группу `web`

<spoiler title="app/Http/Kernel.php">

```php
<?php

namespace App\Http;

// ...
use App\Contracts\SiteDetector;
use App\Http\Middleware\ViewData;
use Closure;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
// ...

class Kernel extends HttpKernel
{

    // ...
    
    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        // ...
        'web' => [
            // ...
            ViewData::class,
        ],
        // ...
    ];
    
    // ...

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
             * Запускаем диспатчинг
             */
            $this->app->instance('request', $request);
            return $this->router->dispatch($request);
        };
    }
}

```
</spoiler>

Если не кэшировать роуты, то можно уже работать. Но на бою без кэша - идея не из лучших. Мы уже научили наше приложение получать роуты из кэша, теперь нужно научить правильно его сохранять. Кастомизируем консольную команду `route:cache`

<spoiler title="app/Custom/Illuminate/Foundation/Console/RouteCacheCommand.php">

```php
<?php

declare(strict_types=1);

namespace App\Custom\Illuminate\Foundation\Console;

use App\Custom\Illuminate\Routing\RouteCollection as CustomRouteCollection;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Foundation\Console\RouteCacheCommand as BaseCommand;

class RouteCacheCommand extends BaseCommand
{

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        /*
         * Сначала удаляем старый кэш
         */
        $this->call('route:clear');

        /*
         * Получаем роуты свежего приложения
         */
        $routes = $this->getFreshApplicationRoutes();

        if (count($routes) === 0) {
            $this->error("Your application doesn't have any routes.");
            return;
        }

        /*
         * Подготавливаем кэш и сохраняем
         */
        $this->files->put(
            $this->laravel->getCachedRoutesPath(), $this->buildRouteCacheFile($routes)
        );

        $this->info('Routes cached successfully!');
        return;
    }

    protected function buildRouteCacheFile(RouteCollection $base)
    {
        /*
         * Кэш файл будет представлять собой массив анонимных функций.
         * 
         * Ключ массива - локаль, значение - функция, возвращающая экземпляр класса Illuminate\Routing\RouteCollection
         */

        $code = '<?php' . PHP_EOL . PHP_EOL;
        $code .= 'return [' . PHP_EOL;


        $stub = '    \'{{key}}\' => function() {return unserialize(base64_decode(\'{{routes}}\'));},';
        foreach (config('app.supported_locales') as $locale) {
            /** @var CustomRouteCollection|Route[] $routes */
            $routes = clone $base;
            $routes->localize($locale);
            foreach ($routes as $route) {
                $route->prepareForSerialization();
            }
            $line = str_replace('{{routes}}', base64_encode(serialize($routes)), $stub);
            $line = str_replace('{{key}}', $locale, $line);
            $code .= $line . PHP_EOL;
        }
        $code .= '];' . PHP_EOL;
        return $code;
    }
}

```
</spoiler>

Команда `route:clear` просто удаляет файл кэша, Её мы трогать не будем. А вот команде `route:list` теперь не помешает опция `locale`.
 
<spoiler title="app/Custom/Illuminate/Foundation/Console/RouteListCommand.php">

```php
<?php

declare(strict_types=1);

namespace App\Custom\Illuminate\Foundation\Console;

use Illuminate\Foundation\Console\RouteListCommand as BaseCommand;
use Symfony\Component\Console\Input\InputOption;

class RouteListCommand extends BaseCommand
{

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $locales = $this->option('locale');

        /*
         * Выполняем родительскую команду для каждой локали
         */
        foreach ($locales as $locale) {
            if ($locale && in_array($locale, config('app.supported_locales'))) {
                $this->output->title($locale);
                $this->laravel->setLocale($locale);
                $this->router = $this->laravel->get('router');
                parent::handle();
            }
        }
    }

    protected function getOptions()
    {
        /*
         * Все поддерживаемые приложением локали
         */
        $all = config('app.supported_locales');

        /*
         * Определяем опции родительской команды
         */
        $result = parent::getOptions();

        /*
         * Добавляем опцию локалей
         */
        $result[] = ['locale', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Locales', $all];
        return $result;
    }
}

```
</spoiler>

Теперь нам нужно эти команды заставить работать. Сейчас будут работать вендорные команды. Чтобы заменить реализацию консольных команд, нужно включить в приложение сервис провайдер, реализующий интерфейс `Illuminate\Contracts\Support\DeferrableProvider`. Метод `provides()` должен вернуть массив ключей контейра, соответствующих классам команд.

<spoiler title="app/Providers/CommandsReplaceProvider.php">

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Custom\Illuminate\Foundation\Console\RouteCacheCommand;
use App\Custom\Illuminate\Foundation\Console\RouteListCommand;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class CommandsReplaceProvider extends ServiceProvider implements DeferrableProvider
{

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('command.route.cache', function (Application $app) {
            return new RouteCacheCommand($app->get('files'));
        });

        $this->app->singleton('command.route.list', function (Application $app) {
            return new RouteListCommand($app->get('router'));
        });
        $this->commands($this->provides());
    }

    public function provides()
    {
        return [
            'command.route.cache',
            'command.route.list',
        ];
    }
}

```
</spoiler>

Ну и конечно же, добавляем провайдер в конфигурацию.

<spoiler title="config/app.php">

```php
<?php

return [
    // ...
    'providers' => [
        App\Providers\CommandsReplaceProvider::class,
    ],
    // ...
];
```
</spoiler>

Теперь всё работает!

```text
user@host laravel-localized-routing $ ./artisan route:list

en
==

+--------+----------+----------+--------------+-------------------------+------------+
| Domain | Method   | URI      | Name         | Action                  | Middleware |
+--------+----------+----------+--------------+-------------------------+------------+
|        | GET|HEAD | /        | web.home     | DemoController@home     | web        |
|        | GET|HEAD | about-us | web.about    | DemoController@about    | web        |
|        | GET|HEAD | contacts | web.contacts | DemoController@contacts | web        |
|        | GET|HEAD | news     | web.news     | DemoController@news     | web        |
+--------+----------+----------+--------------+-------------------------+------------+

ru
==

+--------+----------+----------+--------------+-------------------------+------------+
| Domain | Method   | URI      | Name         | Action                  | Middleware |
+--------+----------+----------+--------------+-------------------------+------------+
|        | GET|HEAD | /        | web.home     | DemoController@home     | web        |
|        | GET|HEAD | kontakty | web.contacts | DemoController@contacts | web        |
|        | GET|HEAD | novosti  | web.news     | DemoController@news     | web        |
|        | GET|HEAD | o-nas    | web.about    | DemoController@about    | web        |
+--------+----------+----------+--------------+-------------------------+------------+

de
==

+--------+----------+-------------+--------------+-------------------------+------------+
| Domain | Method   | URI         | Name         | Action                  | Middleware |
+--------+----------+-------------+--------------+-------------------------+------------+
|        | GET|HEAD | /           | web.home     | DemoController@home     | web        |
|        | GET|HEAD | kontakte    | web.contacts | DemoController@contacts | web        |
|        | GET|HEAD | nachrichten | web.news     | DemoController@news     | web        |
|        | GET|HEAD | uber-uns    | web.about    | DemoController@about    | web        |
+--------+----------+-------------+--------------+-------------------------+------------+

fr
==

+--------+----------+------------------+--------------+-------------------------+------------+
| Domain | Method   | URI              | Name         | Action                  | Middleware |
+--------+----------+------------------+--------------+-------------------------+------------+
|        | GET|HEAD | /                | web.home     | DemoController@home     | web        |
|        | GET|HEAD | a-propos-de-nous | web.about    | DemoController@about    | web        |
|        | GET|HEAD | contacts         | web.contacts | DemoController@contacts | web        |
|        | GET|HEAD | nouvelles        | web.news     | DemoController@news     | web        |
+--------+----------+------------------+--------------+-------------------------+------------+

```

На этом всё. Спасибо за внимание!
